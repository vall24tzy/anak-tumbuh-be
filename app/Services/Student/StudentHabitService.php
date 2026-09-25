<?php

namespace App\Services\Student;

use App\Exceptions\ApiException;
use App\Models\Habit;
use App\Models\Indicator;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\StudentHabit;
use App\Services\School\TeacherScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §21-§23, DB Design v1.5 §5.12-§5.13, §12. Student-only
// write (Matrix #32/#36); GET monitor/view (Matrix #33/#35): Teacher own
// active rombel, Student own. Tidak ada PATCH/DELETE untuk siapa pun
// (Matrix #34, API Contract §21 Removed section).

class StudentHabitService
{
    public function __construct(
        protected StudentClassAssignmentService $assignments,
        protected TeacherScopeService $teacherScope,
    ) {}

    protected function currentStudent(): Student
    {
        $user = auth('api')->user();
        $student = $user?->student;

        if (!$student) {
            throw new HttpException(403, 'Profil Student tidak ditemukan untuk akun ini');
        }

        return $student;
    }

    /**
     * Scoring SATU sumber untuk POST dan GET (poin_awarded derived, tidak
     * dipersist — API Contract §21/§22). 6 habit: indicators.point + bonus
     * initiative.
     */
    protected function habitPoin(Indicator $indicator, string $initiative): int
    {
        return (int) $indicator->point + ($initiative === 'mandiri' ? 10 : 0);
    }

    /**
     * Bermasyarakat: count-based (3 positif=50, 2=25, 1=15, "Kurang
     * bermasyarakat"=10) + bonus initiative — BUKAN SUM(indicators.point)
     * (point 4 indikator Bermasyarakat selalu NULL, DB Design §5.11).
     */
    protected function bermasyarakatPoin(Collection $indicators, string $initiative): int
    {
        $basePoin = $indicators->where('is_exclusive', true)->isNotEmpty()
            ? 10
            : match ($indicators->where('is_exclusive', false)->count()) {
                3 => 50,
                2 => 25,
                1 => 15,
                default => 0,
            };

        return $basePoin + ($initiative === 'mandiri' ? 10 : 0);
    }

    protected function today(): string
    {
        // Server-determined, timezone Asia/Jakarta (Dokumen v4.0 §7, API
        // Contract v1.2 §7) — TIDAK menerima habit_date dari client, dan
        // TIDAK bergantung pada config('app.timezone') supaya konsisten
        // walau APP_TIMEZONE di .env belum di-set ke Asia/Jakarta.
        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    protected function classroomSnapshot(Student $student): ?string
    {
        // Historical snapshot, diisi SYSTEM saat entry dibuat (DB Design v1.5
        // §5.12) — TIDAK PERNAH di-UPDATE setelahnya, BUKAN source of truth
        // placement. Sumber: assignment aktif pada Academic Year yang sedang
        // active di school Student (resolver di StudentClassAssignmentService,
        // bukan hasOne activeClassAssignment yang ambigu lintas tahun). NULL
        // bila tidak ada assignment aktif pada tahun tsb.
        return $this->assignments->activeAssignmentInCurrentAcademicYear($student)?->rombel?->name;
    }

    /**
     * Guard bersama sebelum insert: unique (student,habit,date) -> 409,
     * dan daily limit 7/hari -> 422 (API Contract §21). Daily limit ini
     * secara struktural TIDAK BISA terlampaui (hanya ada 7 habit_id tetap,
     * unique constraint sudah menutup slot ke-8), tapi tetap diimplementasikan
     * eksplisit sesuai instruksi kontrak ("validasi eksplisit tetap
     * dicantumkan untuk kejelasan kontrak", DB Design v1.5 §21 Validation).
     */
    protected function assertCanCreateEntry(Student $student, int $habitId, string $habitDate): void
    {
        $duplicate = StudentHabit::where('student_id', $student->id)
            ->where('habit_id', $habitId)
            ->where('habit_date', $habitDate)
            ->exists();

        if ($duplicate) {
            throw new ApiException(
                'duplicate_habit_entry',
                409,
                'Habit ini sudah diisi hari ini',
            );
        }

        $countToday = StudentHabit::where('student_id', $student->id)
            ->where('habit_date', $habitDate)
            ->count();

        if ($countToday >= 7) {
            throw new ApiException(
                'daily_limit_exceeded',
                422,
                'Maksimal 7 habit per hari sudah tercapai',
            );
        }
    }

    /**
     * Insert + update students.poin dalam SATU DB::transaction. Guard
     * assertCanCreateEntry() berjalan sebelum transaksi (check-then-insert),
     * jadi dua request bersamaan bisa sama-sama lolos guard; unique index
     * uq_student_habit_date (DB Design v1.5 §5.12) yang jadi penentu akhir.
     * Pelanggarannya diubah jadi 409 (bukan 500), transaksi ter-rollback
     * sehingga students.poin tidak bertambah dua kali.
     */
    protected function runInTransaction(\Closure $callback): StudentHabit
    {
        try {
            return DB::transaction($callback);
        } catch (UniqueConstraintViolationException $e) {
            throw new ApiException(
                'duplicate_habit_entry',
                409,
                'Habit ini sudah diisi hari ini',
            );
        }
    }

    /**
     * POST /api/v1/habits/{habit_id}/entries — 6 habit SELAIN Bermasyarakat
     * (API Contract v1.2 §21). Bermasyarakat -> 422 wrong_habit_endpoint,
     * arahkan ke POST /bermasyarakat/entries (Keputusan Tim, GAP #2).
     */
    public function storeHabitEntry(int $habitId, array $data): StudentHabit
    {
        $student = $this->currentStudent();

        $habit = Habit::find($habitId);
        if (!$habit) {
            throw new HttpException(404, 'Habit tidak ditemukan');
        }

        if ($habit->allows_multi_select) {
            throw new ApiException(
                'wrong_habit_endpoint',
                422,
                'Habit ini (Bermasyarakat) wajib memakai POST /api/v1/bermasyarakat/entries',
                [['field' => 'habit_id', 'message' => 'wrong_habit_endpoint']],
            );
        }

        $indicator = Indicator::find($data['indicator_id']);
        if (!$indicator || (int) $indicator->habit_id !== (int) $habit->id) {
            throw new ApiException(
                'unknown_indicator',
                422,
                'indicator_id tidak sesuai dengan habit ini',
                [['field' => 'indicator_id', 'message' => 'unknown_indicator']],
            );
        }

        $habitDate = $this->today();
        $this->assertCanCreateEntry($student, $habit->id, $habitDate);

        // Scoring (API Contract v1.2 §21, Addendum v1.2.1): indicator.point +
        // bonus initiative (10 bila 'mandiri', 0 bila 'disuruh') — terpisah
        // dari poin indikator, dijumlahkan SYSTEM.
        $poinAwarded = $this->habitPoin($indicator, $data['initiative']);

        $studentHabit = $this->runInTransaction(function () use ($student, $habit, $indicator, $data, $habitDate, $poinAwarded) {
            $entry = StudentHabit::create([
                'student_id'   => $student->id,
                'habit_id'     => $habit->id,
                'indicator_id' => $indicator->id,
                'habit_date'   => $habitDate,
                'note'         => $data['note'] ?? null,
                'classroom'    => $this->classroomSnapshot($student),
                'initiative'   => $data['initiative'],
            ]);

            $student->increment('poin', $poinAwarded);

            return $entry;
        });

        $studentHabit->poin_awarded = $poinAwarded;

        return $studentHabit;
    }

    /**
     * POST /api/v1/bermasyarakat/entries — multi-select, native array
     * (API Contract v1.2 §22). Scoring count-based, BUKAN SUM(indicators.point)
     * (4 indikator Bermasyarakat point-nya SELALU NULL, DB Design v1.5 §5.11).
     */
    public function storeBermasyarakatEntry(array $data): StudentHabit
    {
        $student = $this->currentStudent();

        $habit = Habit::where('code', 'bermasyarakat')->first();
        if (!$habit) {
            throw new HttpException(500, 'Habit Bermasyarakat belum ter-seed');
        }

        // Duplikat sudah ditolak Form Request (rule distinct -> 422) — tidak ada dedupe diam-diam.
        $ids = array_values($data['indicator_ids']);

        $indicators = Indicator::whereIn('id', $ids)->where('habit_id', $habit->id)->get();

        if ($indicators->count() !== count($ids)) {
            throw new ApiException(
                'unknown_indicator',
                422,
                'Salah satu indicator_ids tidak dikenal / bukan milik habit Bermasyarakat',
                [['field' => 'indicator_ids', 'message' => 'unknown_indicator']],
            );
        }

        $exclusive = $indicators->where('is_exclusive', true);
        $positive = $indicators->where('is_exclusive', false);

        // "Kurang bermasyarakat" (is_exclusive) hanya boleh dipilih sendirian
        // (API Contract v1.2 §22).
        if ($exclusive->isNotEmpty() && $indicators->count() > 1) {
            throw new ApiException(
                'mutually_exclusive_violation',
                422,
                "'Kurang bermasyarakat' hanya boleh dipilih sendirian, tidak bersama indikator lain",
            );
        }

        // Maksimal 3 indikator positif (API Contract v1.2 §22). Tanpa error code
        // custom: memakai validation failure standar (422 validation_failed).
        if ($exclusive->isEmpty() && $positive->count() > 3) {
            throw ValidationException::withMessages([
                'indicator_ids' => ['Maksimal 3 indikator positif dapat dipilih sekaligus'],
            ]);
        }

        $habitDate = $this->today();
        $this->assertCanCreateEntry($student, $habit->id, $habitDate);

        $poinAwarded = $this->bermasyarakatPoin($indicators, $data['initiative']);

        $studentHabit = $this->runInTransaction(function () use ($student, $habit, $ids, $data, $habitDate, $poinAwarded) {
            $entry = StudentHabit::create([
                'student_id'   => $student->id,
                'habit_id'     => $habit->id,
                'indicator_id' => null,
                'habit_date'   => $habitDate,
                'note'         => null,
                'classroom'    => $this->classroomSnapshot($student),
                'initiative'   => $data['initiative'],
            ]);

            foreach ($ids as $indicatorId) {
                $entry->selectedIndicators()->create(['indicator_id' => $indicatorId]);
            }

            $student->increment('poin', $poinAwarded);

            return $entry;
        });

        $studentHabit->poin_awarded = $poinAwarded;

        return $studentHabit->load('selectedIndicators');
    }

    // ------------------------------------------------------------------
    // GET monitor/view — Matrix #33 (Teacher own active rombel, Student own),
    // #35 (past dates view-only = tanggal lampau tetap terbaca). Hanya scope,
    // pagination, sort (API Contract §7). TIDAK ADA filter date/student_id/
    // rombel_id (Keputusan Tim 24 Sep 2026).
    // ------------------------------------------------------------------

    /** meta pagination (API Contract §7): { page, per_page, total, total_pages }. */
    public static function paginationMeta(LengthAwarePaginator $p): array
    {
        return [
            'page'        => $p->currentPage(),
            'per_page'    => $p->perPage(),
            'total'       => $p->total(),
            'total_pages' => $p->lastPage(),
        ];
    }

    /** Sort yang diizinkan = field yang ada di response (technical convention §7). */
    public const SORTABLE = ['habit_date', 'id'];

    /**
     * Query dasar entry yang boleh dilihat requester. Scope Teacher =
     * Student yang SEKARANG aktif di own active rombel (assignment aktif,
     * TeacherScopeService) — bukan student_habits.classroom (snapshot
     * historis, bukan dasar authorization). Student = own. Role lain tidak
     * pernah sampai sini (middleware role:teacher,student).
     */
    protected function scopedEntries($user): Builder
    {
        $query = StudentHabit::query();

        if ($user->role === 'student') {
            $studentId = $user->student?->id;

            return $query->where('student_id', $studentId ?? 0);
        }

        if ($user->role === 'teacher') {
            $assignment = $this->teacherScope->activeAssignment($user);

            if (!$assignment) {
                return $query->whereRaw('1 = 0'); // belum punya scope -> kosong
            }

            return $query->whereIn(
                'student_id',
                StudentClassAssignment::where('rombel_id', $assignment->rombel_id)
                    ->where('status', 'active')
                    ->select('student_id')
            );
        }

        return $query->whereRaw('1 = 0');
    }

    protected function paginateEntries(Builder $query, array $params): LengthAwarePaginator
    {
        $sort  = $params['sort'] ?? 'habit_date';
        $order = $params['order'] ?? 'desc';

        $query->orderBy($sort, $order);
        if ($sort !== 'id') {
            $query->orderBy('id', $order); // tie-breaker deterministik untuk pagination
        }

        return $query->paginate(
            (int) ($params['per_page'] ?? 20),
            ['*'],
            'page',
            (int) ($params['page'] ?? 1),
        );
    }

    /** GET /api/v1/habits/entries — 6 habit; Bermasyarakat TIDAK ikut (shape beda, §21 vs §22). */
    public function listHabitEntries(array $params): LengthAwarePaginator
    {
        $user = auth('api')->user();

        $paginator = $this->paginateEntries(
            $this->scopedEntries($user)
                ->whereHas('habit', fn ($q) => $q->where('allows_multi_select', false))
                ->with('indicator'),
            $params,
        );

        $paginator->getCollection()->each(function (StudentHabit $entry) {
            $entry->poin_awarded = $this->habitPoin($entry->indicator, $entry->initiative);
        });

        return $paginator;
    }

    /** GET /api/v1/habits/entries/{id} — di luar scope / bukan habit 6-jenis -> 404 (§26). */
    public function showHabitEntry(int $id): StudentHabit
    {
        $user = auth('api')->user();

        $entry = $this->scopedEntries($user)
            ->whereHas('habit', fn ($q) => $q->where('allows_multi_select', false))
            ->with('indicator')
            ->find($id);

        if (!$entry) {
            throw new HttpException(404, 'Entry habit tidak ditemukan');
        }

        $entry->poin_awarded = $this->habitPoin($entry->indicator, $entry->initiative);

        return $entry;
    }

    /** GET /api/v1/bermasyarakat/entries — API Contract §22 (indicator_ids[]). */
    public function listBermasyarakatEntries(array $params): LengthAwarePaginator
    {
        $user = auth('api')->user();

        $paginator = $this->paginateEntries(
            $this->scopedEntries($user)
                ->whereHas('habit', fn ($q) => $q->where('allows_multi_select', true))
                ->with('selectedIndicators.indicator'),
            $params,
        );

        $paginator->getCollection()->each(function (StudentHabit $entry) {
            $entry->poin_awarded = $this->bermasyarakatPoin(
                $entry->selectedIndicators->pluck('indicator')->filter(),
                $entry->initiative,
            );
        });

        return $paginator;
    }
}
