<?php

namespace App\Services\School;

use App\Models\Headmaster;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherService
{
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
}