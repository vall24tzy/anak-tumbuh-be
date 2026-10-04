<?php

namespace App\Services\School;

use App\Models\Headmaster;
use App\Services\Import\ImportService;

// IMPORT-002 — API Contract v1.2 §15/§28, Dokumen v4.0 §3/§4.2. Headmaster
// only, own school. TIDAK ADA rombel_id — Teacher tidak diikat rombel lewat
// import (itu urusan Teacher Class Assignment, task terpisah), jadi school_id
// diambil LANGSUNG dari akun Headmaster yang login, tidak pernah dipercayakan
// ke input file/request.
//
// REUSE TeacherService::store() APA ADANYA sebagai rowProcessor — supaya
// business rule Create Teacher (User+Teacher 1 transaksi, password
// hash(nip), gender wajib) hanya ADA SATU implementasi, sama pola seperti
// StudentImportService/StudentService di IMPORT-001. TIDAK ADA endpoint
// preview terpisah — API Contract tidak merinci path preview/template untuk
// Teacher (beda dari Student §18), jadi tidak diputuskan sendiri; hanya 1
// endpoint import yang diminta task board yang diimplementasikan di sini.

class TeacherImportService
{
    public function __construct(protected TeacherService $teacherService) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function headmasterSchoolId($user): ?int
    {
        // F-14 residual: hanya Headmaster AKTIF yang punya scope; selain itu 403 (pola R7).
        return $user->activeHeadmasterSchoolId()
            ?? throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Profil Headmaster tidak aktif atau tidak ditemukan');
    }

    /**
     * Template Import Guru (FINAL): Nama Lengkap, NIP, Gender saja. TIDAK ADA
     * Classroom/Email.
     */
    protected function buildImporter(?int $schoolId = null): ImportService
    {
        return new ImportService(
            columnMap: [
                'nama_lengkap' => 'name',
                'nip'          => 'nip',
                'gender'       => 'gender',
            ],
            rules: [
                'name'   => ['required', 'string', 'max:150'],
                // unique:teachers,nip — NIP kembar (baik dengan Teacher lain
                // yang sudah ada, maupun antar-baris di file yang sama lewat
                // QueryException di ImportService::generate()) SELALU
                // ditolak, tidak ada konsep "reuse" seperti NISN di Student.
                // F-13: NIP yang sudah menjadi users.username role lain (SA/Headmaster/Student)
                // ditolak sebagai error baris field `nip` (validation_failed), bukan lolos lalu gagal
                // generik `unexpected_error` saat User::create(). `bail` menjaga duplikat per-tabel
                // tetap satu error. NIP kembar ANTAR-baris dalam file ditangani F-35 (uniqueInFile).
                'nip'    => ['bail', 'required', 'string', 'max:30', 'unique:teachers,nip', 'unique:users,username'],
                'gender' => ['required', 'in:laki-laki,perempuan'],
            ],
            rowProcessor: $schoolId ? function (array $row) use ($schoolId) {
                $row['school_id'] = $schoolId;

                return $this->teacherService->store($row);
            } : null,
            // F-35: NIP kembar ANTAR-BARIS dalam file dideteksi sebelum proses (code duplicate_in_file).
            uniqueInFile: ['nip'],
        );
    }

    public function countRows($file): int
    {
        return count($this->buildImporter()->parseRows($file));
    }

    /**
     * Proses import. school_id diambil dari akun Headmaster yang sedang
     * login — bukan dari input mana pun, jadi tidak ada jalur untuk
     * cross-school terjadi (beda dari kasus rombel_id di Student Import).
     */
    public function commit($file): array
    {
        $schoolId = $this->headmasterSchoolId($this->currentUser());

        $result = $this->buildImporter($schoolId)->generate($file);

        return $this->normalizeResult($result);
    }

    /**
     * Transformasi shape generik ImportService -> shape final API Contract
     * §9/§28: {total_rows, processed_rows, successful_rows, rejected_rows,
     * errors: [{row, field, code, message}]}.
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
                    // Field validasi asli (name/nip/gender) dari $this->rules
                    // -> code jelas. Key 'general' datang dari ImportService
                    // (QueryException/Throwable tak terduga saat rowProcessor
                    // dipanggil, mis. NIP kembar antar-baris di file yang
                    // sama) -> tidak ada field/code terstruktur, tetap
                    // dilaporkan (jangan silent-drop).
                    // F-35: code spesifik dari ImportService (mis. duplicate_in_file) menang atas default.
                    $errors[] = [
                        'row'     => $row,
                        'field'   => $field !== 'general' ? $field : null,
                        'code'    => $failure['codes'][$field] ?? ($field !== 'general' ? 'validation_failed' : 'unexpected_error'),
                        'message' => $message,
                    ];
                }
            }
        }

        return $errors;
    }
}