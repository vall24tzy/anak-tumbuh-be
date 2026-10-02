<?php

namespace App\Services\School;

use App\Models\TeacherClassAssignment;

class TeacherScopeService
{
    /**
     * Assignment aktif milik Teacher pada Academic Year yang sedang aktif
     * di sekolahnya. Return null kalau Teacher belum di-assign ke rombel
     * manapun (belum punya scope sama sekali).
     *
     * Dipakai ulang di task lain (STUDENT-001, StudentHabit, Import,
     * Lifecycle, Ranking, Dashboard) - JANGAN duplikasi query ini di
     * Service lain, selalu panggil method ini.
     */
    public function activeAssignment($teacherUser): ?TeacherClassAssignment
    {
        // F-14 residual: Teacher inactive tidak punya scope, walau assignment-nya
        // masih tercatat aktif (status profil diubah tanpa menutup assignment).
        $teacher = $teacherUser->teacher()->where('status', 'active')->first();

        if (!$teacher) {
            return null;
        }

        return TeacherClassAssignment::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->whereHas('academicYear', fn ($q) => $q->where('is_active', true))
            ->first();
    }
}