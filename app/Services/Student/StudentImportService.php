<?php

namespace App\Services\Student;

use App\Exceptions\ApiException;
use App\Services\Import\ImportService;
use App\Services\School\TeacherScopeService;
use Symfony\Component\HttpKernel\Exception\HttpException;

// IMPORT-001 — API Contract v1.2 §18/§28, Dokumen v4.0 §6.2. Teacher-only,
// own active rombel. rombel_id adalah parameter REQUEST-LEVEL (sama untuk
// SELURUH baris file, bukan per-baris) — Teacher cuma punya 1 active rombel
// per Academic Year (TEACHER-ASSIGNMENT-001), jadi tidak ada "resolve rombel
// per baris dari nama Excel"; template juga TIDAK punya kolom Rombel.
//
// NISN existing (sekolah sama) di-REUSE (bukan skip/failed) — dicapai dengan
// REUSE StudentService::store() APA ADANYA (tidak dimodifikasi sama sekali)
// sebagai rowProcessor, supaya business rule Create Student (NISN reuse,
// cross-school reject, DB transaction per-baris, QrToken, assignment) hanya
// ADA SATU implementasi — bukan diduplikasi di sini.

class StudentImportService
{
    public function __construct(
        protected StudentService $studentService,
        protected TeacherScopeService $teacherScope,
    ) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    /**
     * Template Import Siswa (FINAL): Nama Lengkap, NISN, Gender saja.
     * TIDAK ADA Classroom/Grade Level/Email — grade_level kini murni dari
     * rombels.grade_level (Master Rombel), bukan input per-Student.
     */
    protected function buildImporter(?int $rombelId = null): ImportService
    {
        return new ImportService(
            columnMap: [
                'nama_lengkap' => 'name',
                'nisn'         => 'nisn',
                'gender'       => 'gender',
            ],
            rules: [
                'name' => ['required', 'string', 'max:150'],
                // TIDAK ADA 'unique:students,nisn' — NISN existing (sekolah
                // sama) di-REUSE, bukan direjek di level validasi (Dokumen
                // v4.0 §12: "Import selalu reject existing NISN" = REMOVE).
                'nisn'   => ['required', 'string', 'max:20'],
                'gender' => ['required', 'in:laki-laki,perempuan'],
            ],
            rowProcessor: $rombelId ? function (array $row) use ($rombelId) {
                return $this->processRow($row, $rombelId);
            } : null,
        );
    }

    /**
     * Bungkus exception dari StudentService::store() jadi RuntimeException
     * berisi JSON {code, field, message} — supaya lolos lewat catch generik
     * ImportService::generate() (yang cuma ambil ->getMessage()) dan bisa
     * dibongkar lagi di normalizeErrors(), TANPA mengubah ImportService.php
     * sama sekali (tetap generik untuk IMPORT-002 nanti).
     */
    protected function processRow(array $row, int $rombelId)
    {
        $row['rombel_id'] = $rombelId;

        try {
            return $this->studentService->store($row);
        } catch (ApiException $e) {
            // F-53: error yang menyebut field (details[0].field, mis. nisn_conflicts_with_staff_username)
            // dilaporkan per-baris dengan field itu; yang tidak punya details tetap field null seperti sebelumnya.
            throw new \RuntimeException(json_encode([
                'code'    => $e->errorCode(),
                'field'   => $e->details()[0]['field'] ?? null,
                'message' => $e->getMessage(),
            ]));
        } catch (HttpException $e) {
            // Secara desain seharusnya tidak pernah kena di alur normal —
            // rombel_id sudah divalidasi SEKALI lewat assertOwnActiveRombel()
            // sebelum loop dimulai. Safety net saja.
            throw new \RuntimeException(json_encode([
                'code'    => 'student_create_rejected',
                'field'   => null,
                'message' => $e->getMessage(),
            ]));
        }
    }

    /**
     * Validasi SEKALI di awal (bukan per-baris) — rombel_id request wajib
     * sama dengan own active rombel Teacher. Kalau tidak, TOLAK SELURUH
     * request (403) sebelum satu baris pun diproses — API Contract v1.2 §18:
     * "403 — Teacher mencoba import ke rombel yang bukan own active rombel".
     * Dipanggil ulang di commit() (bukan cuma di controller preview) supaya
     * revalidasi server-side tidak bergantung pada urutan panggilan caller.
     */
    public function assertOwnActiveRombel(int $rombelId): void
    {
        $user = $this->currentUser();
        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment || $assignment->rombel_id !== $rombelId) {
            throw new HttpException(403, 'rombel_id bukan own active rombel Anda');
        }
    }

    public function countRows($file): int
    {
        return count($this->buildImporter()->parseRows($file));
    }

    /**
     * Preview HANYA validasi struktural/format (Form Request-style per
     * $this->rules) — TIDAK ada business logic/scope check di sini (tidak
     * memanggil StudentService::store() sama sekali), sesuai API Contract
     * §18: "Preview bukan authorization... belum final terhadap race
     * condition." Makanya preview tidak butuh rombel_id.
     */
    public function preview($file): array
    {
        return $this->buildImporter()->preview($file);
    }

    /**
     * Commit — WAJIB revalidasi penuh (authorization + business rule) di
     * server, tidak percaya hasil preview client (API Contract §18/§28).
     * Revalidasi tercapai otomatis karena SETIAP baris lewat
     * StudentService::store() yang scope-check-nya berjalan penuh tiap kali.
     */
    public function commit($file, int $rombelId): array
    {
        $this->assertOwnActiveRombel($rombelId);

        $result = $this->buildImporter($rombelId)->generate($file);

        return $this->normalizeResult($result);
    }

    /**
     * Transformasi shape generik ImportService
     * ({total, success, failed, failed_rows: [{row, errors: {field: [msg]}}]})
     * -> shape final API Contract §9/§18:
     * {total_rows, processed_rows, successful_rows, rejected_rows,
     *  errors: [{row, field, code, message}]}.
     */
    protected function normalizeResult(array $result): array
    {
        return [
            'total_rows'      => $result['total'],
            'processed_rows'  => $result['total'],
            'successful_rows' => $result['success'],
            'rejected_rows'   => $result['failed'],
            'errors'          => $this->normalizeErrors($result['failed_rows']),
        ];
    }

    protected function normalizeErrors(array $failedRows): array
    {
        $errors = [];

        foreach ($failedRows as $failure) {
            $row = $failure['row'];

            foreach ($failure['errors'] as $field => $messages) {
                foreach ((array) $messages as $message) {
                    if ($field !== 'general') {
                        // Kegagalan validasi Form-level ($this->rules) —
                        // field-nya sudah field asli (name/nisn/gender).
                        $errors[] = [
                            'row'     => $row,
                            'field'   => $field,
                            'code'    => 'validation_failed',
                            'message' => $message,
                        ];
                        continue;
                    }

                    $decoded = json_decode($message, true);

                    if (is_array($decoded) && isset($decoded['code'], $decoded['message'])) {
                        $errors[] = [
                            'row'     => $row,
                            'field'   => $decoded['field'] ?? null,
                            'code'    => $decoded['code'],
                            'message' => $decoded['message'],
                        ];
                        continue;
                    }

                    // Throwable tak terduga (bukan hasil processRow(), mis.
                    // QueryException mentah) — tetap dilaporkan, jangan
                    // silent-drop, tapi code generik karena tidak terstruktur.
                    $errors[] = [
                        'row'     => $row,
                        'field'   => null,
                        'code'    => 'unexpected_error',
                        'message' => $message,
                    ];
                }
            }
        }

        return $errors;
    }
}
