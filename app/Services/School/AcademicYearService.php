<?php

namespace App\Services\School;

use App\Models\AcademicYear;
use App\Models\Headmaster;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AcademicYearService
{
    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function schoolIdForUser($user): ?int
    {
        // Headmaster & Teacher sama-sama discope ke school masing-masing.
        if ($user->role === 'headmaster') {
            return Headmaster::where('user_id', $user->id)->value('school_id');
        }

        return $user->teacher?->school_id;
    }

    public function index()
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());

        return AcademicYear::where('school_id', $schoolId)->get();
    }

    public function show(int $id)
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());
        $academicYear = AcademicYear::where('school_id', $schoolId)->find($id);

        if (!$academicYear) {
            throw new HttpException(404, 'Tahun ajaran tidak ditemukan');
        }

        return $academicYear;
    }

    public function store(array $data): AcademicYear
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());

        return AcademicYear::create([
            'school_id'  => $schoolId,
            'name'       => $data['name'],
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'is_active'  => false,
        ]);
    }

    public function update(int $id, array $data): AcademicYear
    {
        $academicYear = $this->show($id); // reuse scope check + 404

        $academicYear->update($data);

        return $academicYear->fresh();
    }

    public function activate(int $id): AcademicYear
    {
        $academicYear = $this->show($id); // scope check + 404 dulu

        return DB::transaction(function () use ($academicYear) {
            AcademicYear::where('school_id', $academicYear->school_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $academicYear->update(['is_active' => true]);

            return $academicYear->fresh();
        });
    }
}