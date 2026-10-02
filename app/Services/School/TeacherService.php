<?php

namespace App\Services\School;

use App\Services\User\AuditLogService;
use App\Models\TeacherClassAssignment;
use App\Models\Headmaster;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherService
{
    public function __construct(protected AuditLogService $auditLog) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function headmasterSchoolId($user): ?int
    {
        // F-14 residual: hanya Headmaster AKTIF yang punya scope; selain itu 403 (pola R7).
        return $user->activeHeadmasterSchoolId()
            ?? throw new HttpException(403, 'Profil Headmaster tidak aktif atau tidak ditemukan');
    }

    public function index()
    {
        $schoolId = $this->headmasterSchoolId($this->currentUser());

        return Teacher::with('school')
            ->where('school_id', $schoolId)
            ->get();
    }

    public function show(int $id)
    {
        $schoolId = $this->headmasterSchoolId($this->currentUser());
        $teacher = Teacher::with('school')
            ->where('school_id', $schoolId)
            ->find($id);

        if (!$teacher) {
            throw new HttpException(404, 'Guru tidak ditemukan');
        }

        return $teacher;
    }

    /**
     * Fetch GLOBAL (tanpa filter school_id) — beda dari show(). Dipakai untuk
     * operasi WRITE yang scope-nya jelas dari path (bukan GET), supaya "tidak
     * ditemukan" (404) dan "ada tapi di luar scope" (403) tetap bisa dibedakan
     * (API Contract v1.2 §10: "403 bila target di luar scope actor (cross-school
     * untuk Headmaster→Teacher); 404 bila target tidak ditemukan"). show() di
     * atas SENGAJA tidak diubah — GET memang harus 404 untuk keduanya (§26,
     * mencegah enumerasi lintas-scope), beda konvensi dari write endpoint ini.
     */
    protected function findTeacherOrFail(int $id): Teacher
    {
        $teacher = Teacher::with('school')->find($id);

        if (!$teacher) {
            throw new HttpException(404, 'Guru tidak ditemukan');
        }

        return $teacher;
    }

    public function store(array $data): Teacher
    {
        $schoolId = $this->headmasterSchoolId($this->currentUser());

        if ((int) $data['school_id'] !== $schoolId) {
            throw new HttpException(403, 'Anda hanya boleh menambahkan guru ke sekolah sendiri');
        }

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'role'     => 'teacher',
                'username' => $data['nip'],
                'password' => Hash::make($data['nip']),
            ]);

            return Teacher::create([
                'user_id'   => $user->id,
                'school_id' => $data['school_id'],
                'fullname'  => $data['name'],
                'nip'       => $data['nip'],
                'gender'    => $data['gender'],
                'status'    => 'active',
            ])->load('school');
        });
    }

    public function inactive(int $id): Teacher
    {
        $teacher = $this->show($id); // reuse: scope own-school + 404 kalau di luar

        return DB::transaction(function () use ($teacher) {
            $teacher->update(['status' => 'inactive']);

            TeacherClassAssignment::where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return $teacher->fresh('school');
        });
    }

    public function resetPassword(int $id, array $data): Teacher
    {
        $teacher = $this->findTeacherOrFail($id);
        $actor = $this->currentUser();

        if ($teacher->school_id !== $this->headmasterSchoolId($actor)) {
            throw new HttpException(403, 'Guru ini di luar sekolah Anda');
        }

        $teacher->user->update(['password' => $data['new_password']]);

        $this->auditLog->record($actor, 'password_reset', $teacher->user);

        return $teacher;
    }
}