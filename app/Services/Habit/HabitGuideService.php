<?php

namespace App\Services\Habit;

use App\Models\Habit;
use App\Models\HabitGuide;
use App\Models\Rombel;
use App\Services\School\TeacherScopeService;
use App\Services\Student\StudentClassAssignmentService;
use Symfony\Component\HttpKernel\Exception\HttpException;

// INTEG-PANDUAN-001 — Matrix #63 (tulis: Teacher own active rombel) dan #64 (lihat: Teacher own
// active rombel, Student rombel sendiri); D-06, D-13; Dokumen Keputusan v4.0 amandemen 20 Okt 2026 (a).
// Panduan = teks polos per (rombel x habit). Bukan input Student, tidak mengubah aturan note.
//
// Scope (D-02 / pola scope Teacher): rombel tidak ada ATAU milik sekolah lain -> 404 (respons
// identik, anti-enumerasi); rombel lain di sekolah yang sama -> 403. Scope Teacher hanya dari
// TeacherScopeService::activeAssignment(); rombel Student dari
// StudentClassAssignmentService::activeAssignmentInCurrentAcademicYear().
// Role lain (Super Admin, Headmaster) dan Student pada PATCH ditolak middleware (403).
// Tidak ada rate limit baru (D-04) dan tidak ada audit_logs (bukan aksi sensitif, amandemen 20 Okt).

class HabitGuideService
{
    public function __construct(
        protected TeacherScopeService $teacherScope,
        protected StudentClassAssignmentService $assignments,
    ) {}

    /**
     * GET: hanya panduan yang ADA, urut sort_order habit. Rombel tanpa panduan -> [].
     *
     * @return array<int, array{habit_id: int, content: string}>
     */
    public function list(int $rombelId): array
    {
        $rombel = $this->resolveRombel($rombelId);

        return HabitGuide::query()
            ->join('habits', 'habits.id', '=', 'habit_guides.habit_id')
            ->where('habit_guides.rombel_id', $rombel->id)
            ->orderBy('habits.sort_order')
            ->orderBy('habit_guides.habit_id')
            ->get(['habit_guides.habit_id', 'habit_guides.content'])
            ->map(fn ($g) => ['habit_id' => (int) $g->habit_id, 'content' => $g->content])
            ->all();
    }

    /**
     * PATCH: content terisi -> simpan/timpa (upsert atomik); kosong/null -> hapus panduan (D-13).
     * Respons sama bentuknya; setelah hapus content = null, identik dengan "belum ada panduan".
     * Menghapus panduan yang memang tidak ada tetap 200 (idempoten).
     *
     * @return array{habit_id: int, content: ?string}
     */
    public function write(int $rombelId, int $habitId, ?string $content): array
    {
        $rombel = $this->resolveRombel($rombelId);

        if (!Habit::whereKey($habitId)->exists()) {
            throw new HttpException(404, 'Habit tidak ditemukan');
        }

        if ($content === null || $content === '') {
            HabitGuide::where('rombel_id', $rombel->id)->where('habit_id', $habitId)->delete();

            return ['habit_id' => $habitId, 'content' => null];
        }

        HabitGuide::upsert(
            [['rombel_id' => $rombel->id, 'habit_id' => $habitId, 'content' => $content]],
            ['rombel_id', 'habit_id'],
            ['content'],
        );

        return ['habit_id' => $habitId, 'content' => $content];
    }

    protected function resolveRombel(int $rombelId): Rombel
    {
        $user = auth('api')->user();

        if ($user->role === 'teacher') {
            $teacher = $user->teacher()->where('status', 'active')->first();

            if (!$teacher) {
                // F-14 residual: profil Teacher inactive tidak punya scope (pola R7).
                throw new HttpException(403, 'Profil Teacher tidak aktif atau tidak ditemukan');
            }

            $schoolId = $teacher->school_id;
        } else {
            // Middleware role hanya meloloskan teacher dan student sampai sini.
            $student = $user->student;

            if (!$student) {
                throw new HttpException(403, 'Profil Student tidak ditemukan untuk akun ini');
            }

            $schoolId = $student->school_id;
        }

        $rombel = Rombel::where('id', $rombelId)->where('school_id', $schoolId)->first();

        if (!$rombel) {
            throw new HttpException(404, 'Rombel tidak ditemukan');
        }

        $ownRombelId = $user->role === 'teacher'
            ? $this->teacherScope->activeAssignment($user)?->rombel_id
            : $this->assignments->activeAssignmentInCurrentAcademicYear($student)?->rombel_id;

        if ($ownRombelId === null || (int) $ownRombelId !== (int) $rombel->id) {
            throw new HttpException(403, 'Rombel ini di luar rombel aktif Anda');
        }

        return $rombel;
    }
}
