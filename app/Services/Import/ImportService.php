<?php

namespace App\Services\Import;

use App\Exceptions\ApiException;
use App\Imports\GenericHeadingRowImport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ImportService — mesin reusable: Baca Excel -> Validasi per baris -> Preview -> Generate.
 *
 * CARA PAKAI (contoh nanti untuk Teacher di IMPORT-002):
 *
 *   $importer = new ImportService(
 *       columnMap: [
 *           'nama_lengkap' => 'fullname',   // key kiri = slug header Excel, key kanan = field internal
 *           'nip'          => 'nip',
 *           'gender'       => 'gender',
 *       ],
 *       rules: [
 *           'fullname' => ['required', 'string', 'max:255'],
 *           'nip'      => ['required', 'string', 'max:50'],
 *           'gender'   => ['required', 'in:laki-laki,perempuan'],
 *       ],
 *       rowProcessor: fn (array $row) => app(TeacherService::class)->store($row),
 *   );
 *
 *   $preview = $importer->preview($file);   // cuma cek, tidak nyimpen apapun
 *   $result  = $importer->generate($file);  // beneran diproses & disimpan
 *
 * PENTING soal $columnMap: key di sebelah kiri HARUS persis sama dengan hasil
 * "slug" yang dibikin Laravel Excel dari header asli file (huruf besar jadi
 * kecil, spasi/simbol jadi underscore). Kalau ragu, jalankan dulu:
 *   dd((new ImportService([], [], null))->parseRows($file));
 * untuk lihat key aslinya SEBELUM finalisasi $columnMap.
 */
class ImportService
{
    public function __construct(
        protected array $columnMap,
        protected array $rules,
        protected ?\Closure $rowProcessor = null,
        // F-35: field internal yang harus unik ANTAR-BARIS dalam satu file (mis. ['nip']). Kosong =
        // tidak ada pengecekan (Student: NISN sama justru di-REUSE, jadi tidak diisi).
        protected array $uniqueInFile = [],
    ) {}

    /**
     * Baca file jadi array baris mentah (belum divalidasi/dipetakan).
     * $file boleh UploadedFile (dari request) ATAU string path (dari Job/queue).
     */
    public function parseRows($file): array
    {
        // F-34: kegagalan level-file harus 422 terstruktur, bukan 500. File yang lolos `mimes` tapi
        // tidak bisa diparse (korup, bukan xlsx sungguhan) -> import_file_unparseable. Hanya
        // Throwable dari proses baca file yang dibungkus; tidak ada baris yang sudah diproses.
        try {
            $sheets = Excel::toArray(new GenericHeadingRowImport(), $file);
        } catch (\Throwable $e) {
            Log::warning('import file unparseable', ['error' => get_class($e) . ': ' . $e->getMessage()]);

            throw new ApiException(
                'import_file_unparseable',
                422,
                'File tidak dapat dibaca. Pastikan file adalah .xlsx yang valid dan tidak rusak'
            );
        }

        $rows = $sheets[0] ?? [];

        $this->assertRequiredHeaders($rows);

        return $rows;
    }

    /**
     * F-34: header kolom wajib hilang/salah ketik -> SATU error struktural 422, bukan banjir error
     * `required` per baris. Kolom wajib diturunkan dari $rules (field yang punya rule `required`),
     * jadi tidak ada daftar kedua yang bisa melenceng. Key header berasal dari baris pertama hasil
     * parse (WithHeadingRow memuat semua header sebagai key, walau selnya kosong). File tanpa baris
     * data tidak punya apa-apa untuk diperiksa dan tetap diproses seperti sebelumnya (total 0).
     */
    protected function assertRequiredHeaders(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $present = array_keys($rows[0]);
        $missing = [];

        foreach ($this->columnMap as $excelKey => $internalKey) {
            $rules = $this->rules[$internalKey] ?? [];

            if (is_array($rules) && in_array('required', $rules, true) && !in_array($excelKey, $present, true)) {
                $missing[] = $excelKey;
            }
        }

        if ($missing !== []) {
            throw new ApiException(
                'import_header_invalid',
                422,
                'Kolom wajib tidak ditemukan di header file: ' . implode(', ', $missing),
                array_map(fn ($c) => ['field' => $c, 'reason' => 'import_header_missing', 'message' => "Kolom '{$c}' tidak ada di header"], $missing)
            );
        }
    }

    protected function mapRow(array $rawRow): array
    {
        $mapped = [];

        foreach ($this->columnMap as $excelKey => $internalKey) {
            $value = $rawRow[$excelKey] ?? null;

            // Excel Laravel kadang balikin float untuk kolom yang sebenarnya teks
            // (NISN, NIP, dsb). Kita samakan jadi string bersih di sini, biar:
            // 1) rule validasi 'string' tidak salah nolak
            // 2) angka besar tidak berubah jadi notasi ilmiah (1.23E+10)
            if (is_float($value) && floor($value) === $value) {
                $value = number_format($value, 0, '', '');
            } elseif ($value !== null) {
                $value = (string) $value;
            }

            // Normalisasi gender: sekolah nulis manual di Excel, gaya kapitalisasi
            // bervariasi (Laki-laki / LAKI-LAKI / laki-laki). Rule validasi
            // 'in:laki-laki,perempuan' case-sensitive, jadi disamakan dulu ke
            // huruf kecil semua di sini sebelum masuk Validator.
            if ($internalKey === 'gender' && $value !== null) {
                $value = strtolower(trim($value));
            }

            // F-36: trim semua field teks dari Excel (NIP/NISN/nama). NIP bertepi-spasi tersimpan
            // sebagai users.username -> beda dari "123" dan akun tidak bisa login. Dilakukan sebelum
            // validasi, cek duplikat (F-35), dan penyimpanan, jadi ketiganya melihat nilai yang sama.
            if (is_string($value)) {
                $value = trim($value);
            }

            $mapped[$internalKey] = $value;
        }

        return $mapped;
    }

    protected function validateRows($file): array
    {
        $rawRows = $this->parseRows($file);
        $valid = [];
        $invalid = [];
        $seenInFile = []; // F-35: field => [nilai => nomor baris pertama yang valid]

        foreach ($rawRows as $index => $rawRow) {
            $rowNumber = $index + 2; // +2: baris 1 Excel = header, index array mulai dari 0
            $data = $this->mapRow($rawRow);

            $validator = Validator::make($data, $this->rules);

            if ($validator->fails()) {
                $invalid[] = [
                    'row'    => $rowNumber,
                    'errors' => $validator->errors()->toArray(),
                ];
                continue;
            }

            // F-35: duplikat ANTAR-BARIS dalam file yang sama dideteksi di sini, sebelum ada yang
            // diproses, supaya tidak bergantung urutan insert (dulu: QueryException generik
            // `unexpected_error`). Baris pertama yang valid tetap diproses; baris berikutnya
            // dengan nilai sama gagal dengan code `duplicate_in_file` yang menyebut baris asal.
            $duplicate = null;
            foreach ($this->uniqueInFile as $field) {
                $value = $data[$field] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                if (isset($seenInFile[$field][$value])) {
                    $duplicate = [
                        'field'   => $field,
                        'message' => "Nilai '{$value}' kembar dengan baris {$seenInFile[$field][$value]} di file ini",
                    ];
                    break;
                }
            }

            if ($duplicate) {
                $invalid[] = [
                    'row'    => $rowNumber,
                    'errors' => [$duplicate['field'] => [$duplicate['message']]],
                    'codes'  => [$duplicate['field'] => 'duplicate_in_file'],
                ];
                continue;
            }

            foreach ($this->uniqueInFile as $field) {
                $value = $data[$field] ?? null;
                if ($value !== null && $value !== '') {
                    $seenInFile[$field][$value] = $rowNumber;
                }
            }

            $valid[] = ['row' => $rowNumber, 'data' => $data];
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    public function preview($file): array
    {
        $result = $this->validateRows($file);

        return [
            'total_rows'    => count($result['valid']) + count($result['invalid']),
            'valid_count'   => count($result['valid']),
            'invalid_count' => count($result['invalid']),
            'invalid_rows'  => $result['invalid'],
            'sample_valid'  => array_slice($result['valid'], 0, 5),
        ];
    }

    public function generate($file): array
    {
        if (!$this->rowProcessor) {
            throw new \LogicException('rowProcessor wajib diisi untuk generate()');
        }

        $result = $this->validateRows($file);
        $failed = $result['invalid'];
        $successCount = 0;

        foreach ($result['valid'] as $row) {
            try {
                ($this->rowProcessor)($row['data']);
                $successCount++;
            } catch (QueryException $e) {
                // Kemungkinan besar: NISN/NIP bentrok sesama baris di file yang sama
                $failed[] = [
                    'row'    => $row['row'],
                    'errors' => ['general' => ['Gagal disimpan — kemungkinan data duplikat dengan baris lain di file ini.']],
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'row'    => $row['row'],
                    'errors' => ['general' => [$e->getMessage()]],
                ];
            }
        }

        return [
            'total'       => count($result['valid']) + count($result['invalid']),
            'success'     => $successCount,
            'failed'      => count($failed),
            'failed_rows' => $failed,
        ];
    }
}