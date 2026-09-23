<?php

namespace App\Services\School;

use App\Exceptions\ApiException;
use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Models\Rombel;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RombelService
{
    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function schoolIdForUser($user): ?int
    {
        if ($user->role === 'headmaster') {
            return Headmaster::where('user_id', $user->id)->value('school_id');
        }

        return $user->teacher?->school_id;
    }

    protected function assertAcademicYearBelongsToSchool(int $academicYearId, int $schoolId): void
    {
        $belongs = AcademicYear::where('id', $academicYearId)
            ->where('school_id', $schoolId)
            ->exists();

        if (!$belongs) {
            throw new ApiException('academic_year_mismatch', 422, 'Academic Year tidak dimiliki sekolah ini');
        }
    }

    public function index()
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());

        return Rombel::where('school_id', $schoolId)->get();
    }

    public function show(int $id)
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());
        $rombel = Rombel::where('school_id', $schoolId)->find($id);

        if (!$rombel) {
            throw new HttpException(404, 'Rombel tidak ditemukan');
        }

        return $rombel;
    }

    public function store(array $data): Rombel
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());

        if ((int) $data['school_id'] !== $schoolId) {
            throw new HttpException(403, 'Anda hanya boleh membuat rombel untuk sekolah sendiri');
        }

        $this->assertAcademicYearBelongsToSchool($data['academic_year_id'], $schoolId);

        return Rombel::create([
            'school_id'        => $schoolId,
            'academic_year_id' => $data['academic_year_id'],
            'name'             => $data['name'],
            'grade_level'      => $data['grade_level'],
        ]);
    }

    public function update(int $id, array $data): Rombel
    {
        $rombel = $this->show($id); // scope check + 404 dulu

        if (isset($data['academic_year_id'])) {
            $this->assertAcademicYearBelongsToSchool($data['academic_year_id'], $rombel->school_id);
        }

        $rombel->update($data);

        return $rombel->fresh();
    }

    public function destroy(int $id): void
    {
        $rombel = $this->show($id);

        try {
            $rombel->delete();
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                throw new ApiException('rombel_has_active_assignment', 409, 'Rombel masih memiliki assignment yang mereferensikannya');
            }

            throw $e;
        }
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        return $e->getCode() === '23503'; // kode SQLSTATE PostgreSQL untuk foreign_key_violation
    }
}