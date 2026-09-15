<?php

namespace App\Services\Student;

use App\Models\Headmaster;
use App\Models\QrUser;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Upload\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StudentService
{
    public function __construct(protected UploadService $uploadService) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function isSuperAdmin($user): bool
    {
        return (string) $user->level === '1';
    }

    protected function isHeadmaster($user): bool
    {
        return (string) $user->level === '2';
    }

    protected function isTeacher($user): bool
    {
        return (string) $user->level === '3';
    }

    protected function headmasterSchoolId($user): ?int
    {
        return Headmaster::where('user_id', $user->id)->first()?->school_id;
    }

    protected function currentTeacher($user): ?Teacher
    {
        return Teacher::where('user_id', $user->id)->first();
    }

    protected function canAccess($user, Student $student): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->isHeadmaster($user)) {
            return $student->school_id === $this->headmasterSchoolId($user);
        }

        if ($this->isTeacher($user)) {
            $teacher = $this->currentTeacher($user);
            return $teacher
                && $student->school_id === $teacher->school_id
                && $student->classroom === $teacher->classroom;
        }

        return false;
    }

    public function index()
    {
        $user = $this->currentUser();
        $query = Student::with(['school', 'user']);

        if ($this->isHeadmaster($user)) {
            $query->where('school_id', $this->headmasterSchoolId($user));
        } elseif ($this->isTeacher($user)) {
            $teacher = $this->currentTeacher($user);
            $query->where('school_id', $teacher?->school_id)
                  ->where('classroom', $teacher?->classroom);
        }

        return $query->get();
    }

    public function show(int $id)
    {
        $user = $this->currentUser();
        $student = Student::with(['school', 'user'])->find($id);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        if (!$this->canAccess($user, $student)) {
            throw new HttpException(403, 'Anda tidak berhak mengakses siswa ini');
        }

        return $student;
    }

    public function store(array $data): Student
    {
        $user = $this->currentUser();

        if ($this->isHeadmaster($user) && (int) $data['school_id'] !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Anda hanya boleh menambahkan siswa ke sekolah sendiri');
        }

        if ($this->isTeacher($user)) {
            $teacher = $this->currentTeacher($user);

            if (!$teacher
                || (int) $data['school_id'] !== $teacher->school_id
                || $data['classroom'] !== $teacher->classroom) {
                throw new HttpException(403, 'Kamu cuma boleh menambahkan siswa di rombelmu sendiri');
            }
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        $student = DB::transaction(function () use ($data) {
            $newUser = User::create([
                'uuid'      => (string) Str::uuid(),
                'username'  => $data['nisn'],
                'email'     => $data['nisn'] . '@siswa.anaktumbuh.id',
                'password'  => $data['nisn'], // Opsi B1: password = NISN, otomatis ke-hash
                'level'     => 4,
                'is_active' => 1,
            ]);

            $student = Student::create([
                'user_id'     => $newUser->id,
                'school_id'   => $data['school_id'],
                'fullname'    => $data['fullname'],
                'nisn'        => $data['nisn'],
                'gender'      => $data['gender'],
                'classroom'   => $data['classroom'],
                'grade_level' => $data['grade_level'],
                'poin'        => 0,
            ]);

            // Token QR: independen total dari NISN/password di atas
            $token = (string) Str::uuid();

            QrUser::create([
                'user_id' => $newUser->id,
                'token'   => $token,
                'link_qr' => rtrim(config('app.url'), '/') . '/qr?token=' . $token,
            ]);

            return $student;
        });

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'students', $student->id);
            $student->update(['photo' => $path]);
        }

        return $student->load(['school', 'user']);
    }

    public function update(int $id, array $data)
    {
        $user = $this->currentUser();
        $student = Student::find($id);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        if (!$this->canAccess($user, $student)) {
            throw new HttpException(403, 'Anda tidak berhak mengubah siswa ini');
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        if (!empty($data)) {
            $student->update($data);

            // Kalau NISN berubah, sinkronkan juga username DAN password (NISN lama) di tabel users
            if (array_key_exists('nisn', $data)) {
                $student->user->update([
                    'username' => $data['nisn'],
                    'password' => Hash::make($data['nisn']),
                ]);
            }
        }

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'students', $student->id, $student->photo);
            $student->update(['photo' => $path]);
        }

        return $student->fresh()->load(['school', 'user']);
    }

    public function destroy(int $id)
    {
        $user = $this->currentUser();
        $student = Student::find($id);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        if (!$this->canAccess($user, $student)) {
            throw new HttpException(403, 'Anda tidak berhak menghapus siswa ini');
        }

        $student->user->update(['is_active' => 0]);

        return true;
    }
}