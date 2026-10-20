<?php

namespace App\Http\Resources\User;

use App\Models\Student;
use App\Services\Student\StudentClassAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return match ($this->role) {
            'headmaster' => $this->staffProfile($this->headmaster),
            'teacher'    => $this->staffProfile($this->teacher),
            'student'    => [
                'id'     => $this->id,
                'role'   => $this->role,
                'name'   => $this->student?->fullname,
                'nisn'   => $this->student?->nisn,
                'school' => $this->schoolData($this->student?->school),
                // INTEG-ME-001 (Matrix #67, D-08): hanya Student; field lama di atas tidak berubah.
                'rombel' => $this->activeRombel($this->student),
                'photo'  => $this->photoUrl($this->student),
            ],
            default => [ // super_admin — belum punya tabel profil & belum punya school
                'id'   => $this->id,
                'role' => $this->role,
                'name' => $this->username,
            ],
        };
    }

    private function staffProfile($profile): array
    {
        return [
            'id'     => $this->id,
            'role'   => $this->role,
            'name'   => $profile?->fullname,
            'nip'    => $profile?->nip,
            'school' => $this->schoolData($profile?->school),
        ];
    }

    private function schoolData($school): ?array
    {
        return $school ? ['id' => $school->id, 'name' => $school->name] : null;
    }

    /**
     * INTEG-ME-001: rombel dari assignment aktif pada Academic Year yang sedang active di
     * sekolah Student. Memakai resolver yang SAMA dengan dashboard/student (deterministik;
     * DB Design §5.9 membolehkan assignment aktif di >1 tahun ajaran), bukan query baru.
     * null bila tidak ada assignment aktif.
     */
    private function activeRombel(?Student $student): ?array
    {
        if (!$student) {
            return null;
        }

        $rombel = app(StudentClassAssignmentService::class)
            ->activeAssignmentInCurrentAcademicYear($student)?->rombel;

        return $rombel ? ['id' => $rombel->id, 'name' => $rombel->name] : null;
    }

    /**
     * INTEG-ME-001 (D-08, F-06): URL endpoint terproteksi (butuh Authorization), sama dengan
     * StudentResource; BUKAN path berkas dan BUKAN URL storage publik. Segmen id = students.id
     * (bukan users.id, F-26). null bila Student belum punya foto.
     */
    private function photoUrl(?Student $student): ?string
    {
        return $student?->photo_path ? route('students.photo', ['id' => $student->id]) : null;
    }
}