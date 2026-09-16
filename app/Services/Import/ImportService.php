<?php

namespace App\Services\Import;

use App\Imports\GenericHeadingRowImport;
use Illuminate\Database\QueryException;
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
    ) {}

    /**
     * Baca file jadi array baris mentah (belum divalidasi/dipetakan).
     * $file boleh UploadedFile (dari request) ATAU string path (dari Job/queue).
     */
    public function parseRows($file): array
    {
        $sheets = Excel::toArray(new GenericHeadingRowImport(), $file);
        return $sheets[0] ?? [];
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

            $mapped[$internalKey] = $value;
        }

        return $mapped;
    }

    protected function validateRows($file): array
    {
        $rawRows = $this->parseRows($file);
        $valid = [];
        $invalid = [];

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