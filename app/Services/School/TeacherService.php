<?php

namespace App\Services\School;

use App\Models\Headmaster;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Upload\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherService
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

    protected function headmasterSchoolId($user): ?int
    {
        return Headmaster::where('user_id', $user->id)->first()?->school_id;
    }

    public function index()
    {
        $user = $this->currentUser();
        $query = Teacher::with(['school', 'user']);

        if ($this->isHeadmaster($user)) {
            $query->where('school_id', $this->headmasterSchoolId($user));
        }

        return $query->get();
    }

    public function show(int $id)
    {
        $user = $this->currentUser();
        $teacher = Teacher::with(['school', 'user'])->find($id);

        if (!$teacher) {
            throw new HttpException(404, 'Guru tidak ditemukan');
        }

        if ($this->isHeadmaster($user) && $teacher->school_id !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Anda tidak berhak mengakses guru ini');
        }

        return $teacher;
    }

    public function store(array $data): array
    {
        $user = $this->currentUser();

        if ($this->isHeadmaster($user) && (int) $data['school_id'] !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Anda hanya boleh menambahkan guru ke sekolah sendiri');
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        $plainPassword = Str::random(8);

        $teacher = DB::transaction(function () use ($data, $plainPassword) {
            $newUser = User::create([
                'uuid'      => (string) Str::uuid(),
                'username'  => $data['username'],
                'email'     => $data['email'],
                'password'  => $plainPassword,
                'level'     => 3,
                'is_active' => 1,
            ]);

            return Teacher::create([
                'user_id'   => $newUser->id,
                'school_id' => $data['school_id'],
                'fullname'  => $data['fullname'],
                'nip'       => $data['nip'],
                'gender'    => $data['gender'],
                'classroom' => $data['classroom'] ?? null,
            ]);
        });

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'teachers', $teacher->id);
            $teacher->update(['photo' => $path]);
        }

        return [$teacher->load(['school', 'user']), $plainPassword];
    }

    public function update(int $id, array $data)
    {
        $user = $this->currentUser();
        $teacher = Teacher::find($id);

        if (!$teacher) {
            throw new HttpException(404, 'Guru tidak ditemukan');
        }

        if ($this->isHeadmaster($user) && $teacher->school_id !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Anda hanya boleh mengubah guru di sekolah sendiri');
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        $userFields = array_intersect_key($data, array_flip(['username', 'email']));
        $teacherFields = array_diff_key($data, $userFields);

        if (!empty($userFields)) {
            $teacher->user->update($userFields);
        }

        if (!empty($teacherFields)) {
            $teacher->update($teacherFields);
        }

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'teachers', $teacher->id, $teacher->photo);
            $teacher->update(['photo' => $path]);
        }

        return $teacher->fresh()->load(['school', 'user']);
    }

    public function destroy(int $id)
    {
        $user = $this->currentUser();
        $teacher = Teacher::find($id);

        if (!$teacher) {
            throw new HttpException(404, 'Guru tidak ditemukan');
        }

        if ($this->isHeadmaster($user) && $teacher->school_id !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Anda hanya boleh menghapus guru di sekolah sendiri');
        }

        $teacher->user->update(['is_active' => 0]);

        return true;
    }
}