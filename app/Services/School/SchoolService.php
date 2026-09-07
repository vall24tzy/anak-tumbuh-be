<?php

namespace App\Services\School;

use App\Models\School;
use App\Models\Headmaster;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchoolService
{
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
        $hm = Headmaster::where('user_id', $user->id)->first();
        return $hm?->school_id;
    }

    public function index()
    {
        $user = $this->currentUser();
        $query = School::withCount(['teachers', 'students']);

        if ($this->isHeadmaster($user)) {
            $query->where('id', $this->headmasterSchoolId($user));
        }

        return $query->get();
    }

    public function store(array $data)
    {
        $user = $this->currentUser();

        if (!$this->isSuperAdmin($user)) {
            throw new HttpException(403, 'Hanya Super Admin yang boleh membuat sekolah baru');
        }

        return School::create($data);
    }

    public function show(int $id)
    {
        $user = $this->currentUser();
        $school = School::withCount(['teachers', 'students'])->findOrFail($id);

        if ($this->isHeadmaster($user) && $school->id !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Anda tidak berhak mengakses sekolah ini');
        }

        return $school;
    }

    public function update(int $id, array $data)
    {
        $user = $this->currentUser();
        $school = School::findOrFail($id);

        if ($this->isHeadmaster($user) && $school->id !== $this->headmasterSchoolId($user)) {
            throw new HttpException(403, 'Anda hanya boleh mengubah sekolah sendiri');
        }

        $school->update($data);
        return $school->fresh()->loadCount(['teachers', 'students']);
    }

    public function destroy(int $id)
    {
        $user = $this->currentUser();

        if (!$this->isSuperAdmin($user)) {
            throw new HttpException(403, 'Hanya Super Admin yang boleh menghapus sekolah');
        }

        $school = School::findOrFail($id);

        $teacherCount = $school->teachers()->count();
        $studentCount = $school->students()->count();
        $hasHeadmaster = $school->headmaster()->exists();

        if ($teacherCount > 0 || $studentCount > 0 || $hasHeadmaster) {
            throw new HttpException(
                422,
                "Sekolah masih memiliki {$teacherCount} guru, {$studentCount} siswa"
                . ($hasHeadmaster ? ', dan headmaster' : '')
                . " — kosongkan dulu sebelum dihapus."
            );
        }

        $school->delete();
        return true;
    }
}