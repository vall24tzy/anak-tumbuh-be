<?php

namespace App\Services\Student;

use App\Models\Headmaster;
use App\Models\Teacher;
use App\Services\Import\ImportService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StudentImportService
{
    public function __construct(protected StudentService $studentService) {}

    protected function buildImporter(?int $schoolId = null): ImportService
    {
        return new ImportService(
            columnMap: [
                'nama_lengkap'        => 'fullname',
                'nisn'                => 'nisn',
                'gender'              => 'gender',
                'classroom'           => 'classroom',
                'tingkatgrade_level'  => 'grade_level', // <-- CEK ULANG, lihat catatan di bawah kode ini
                'email'               => 'email',
            ],
            rules: [
                'fullname'    => ['required', 'string', 'max:255'],
                'nisn'        => ['required', 'string', 'max:20', 'unique:students,nisn'],
                'gender'      => ['required', 'in:laki-laki,perempuan'],
                'classroom'   => ['required', 'string', 'max:50'],
                'grade_level' => ['required', 'integer', 'min:1'],
                'email'       => ['nullable', 'email'],
            ],
            rowProcessor: $schoolId ? function (array $row) use ($schoolId) {
                $row['school_id'] = $schoolId;
                return $this->studentService->store($row);
            } : null,
        );
    }

    public function resolveSchoolId($user, $requestedSchoolId): int
    {
        $level = (string) $user->level;

        if ($level === '1') { // Super Admin
            if (!$requestedSchoolId) {
                throw new HttpException(422, 'school_id wajib diisi untuk Super Admin');
            }
            return (int) $requestedSchoolId;
        }

        if ($level === '2') { // Headmaster
            $schoolId = Headmaster::where('user_id', $user->id)->first()?->school_id;
            if (!$schoolId) {
                throw new HttpException(403, 'Akun Headmaster ini tidak terhubung ke sekolah manapun');
            }
            return $schoolId;
        }

        if ($level === '3') { // Guru
            $schoolId = Teacher::where('user_id', $user->id)->first()?->school_id;
            if (!$schoolId) {
                throw new HttpException(403, 'Akun Guru ini tidak terhubung ke sekolah manapun');
            }
            return $schoolId;
        }

        throw new HttpException(403, 'Peran ini tidak diizinkan melakukan import siswa');
    }

    public function countRows($file): int
    {
        return count($this->buildImporter()->parseRows($file));
    }

    public function preview($file): array
    {
        return $this->buildImporter()->preview($file);
    }

    public function generate($file, int $schoolId): array
    {
        return $this->buildImporter($schoolId)->generate($file);
    }
}