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
        return Headmaster::where('user_id', $user->id)->value('school_id');
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
        $teacher = $this->show($id);
        $actor = $this->currentUser();

        $teacher->user->update(['password' => $data['new_password']]);

        $this->auditLog->record($actor, 'password_reset', $teacher->user);

        return $teacher;
    }
}