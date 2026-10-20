<?php

namespace App\Services\Habit;

use App\Models\Habit;
use App\Models\Indicator;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Services\School\TeacherScopeService;
use App\Services\Student\StudentHabitService;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// INTEG-REKAP-001 — Matrix #62; Dokumen Keputusan v4.0 amandemen 20 Okt 2026 (D-07, D-10, D-11).
// Rekap kebiasaan per rombel, Teacher own active rombel, HANYA BACA (tidak menulis
// student_habits maupun students.poin).
//
// Metrik v1 (D-07):
//   1. persentase terisi per hari = entri / (jumlah siswa x jumlah habit), jumlah habit = 7
//   2. total poin per siswa dalam rentang
//   3. jumlah terisi per kebiasaan (Bermasyarakat = satu kebiasaan dari 7)
// Roster (penyebut) = siswa berstatus active DAN punya assignment aktif di rombel itu
// SEKARANG; rekap tanggal lampau memakai roster sekarang (keterbatasan tercatat di addendum).
//
// Scoring: memakai ulang StudentHabitService::habitPoin()/bermasyarakatPoin() (formula yang
// sama dengan POST entry dan GET entries). Agregasi di SQL (GROUP BY), jumlah query tetap
// (tidak tergantung jumlah siswa/entri), tanpa cache, tanpa index baru (D-10).

class HabitRecapService
{
    public const MAX_DAYS = 31;
    public const DEFAULT_DAYS = 7;

    public function __construct(
        protected TeacherScopeService $teacherScope,
        protected StudentHabitService $scoring,
    ) {}

    public function recap(array $params): array
    {
        // 422 (rentang) dievaluasi sebelum 404/403 rombel, sama seperti validasi FormRequest.
        [$from, $to] = $this->resolveRange($params);

        $rombel = $this->resolveRombel((int) $params['rombel_id']);

        $students = Student::query()
            ->where('status', 'active')
            ->whereIn('id', StudentClassAssignment::query()
                ->where('rombel_id', $rombel->id)
                ->where('status', 'active')
                ->select('student_id'))
            ->orderBy('fullname')
            ->orderBy('id')
            ->get(['id', 'fullname']);

        $studentIds = $students->pluck('id')->all();
        $habits     = Habit::orderBy('sort_order')->orderBy('id')->get(['id', 'code', 'name', 'allows_multi_select']);
        $habitCount = $habits->count();

        $filledByDay   = []; // 'Y-m-d' => jumlah entri
        $filledByHabit = []; // habit_id => jumlah entri
        $poinByStudent = []; // student_id => total poin

        if ($studentIds !== []) {
            foreach ($this->countPerDayAndHabit($studentIds, $from, $to) as $row) {
                $date                  = substr((string) $row->habit_date, 0, 10);
                $filledByDay[$date]    = ($filledByDay[$date] ?? 0) + (int) $row->filled;
                $filledByHabit[$row->habit_id] = ($filledByHabit[$row->habit_id] ?? 0) + (int) $row->filled;
            }

            $poinByStudent = $this->totalPoinPerStudent($studentIds, $habits, $from, $to);
        }

        $studentCount = $students->count();
        $denominator  = $studentCount * $habitCount;

        $perDay = [];
        $cursor = CarbonImmutable::parse($from, 'UTC');
        $days   = (int) $cursor->diffInDays(CarbonImmutable::parse($to, 'UTC')) + 1;

        for ($i = 0; $i < $days; $i++) {
            $date   = $cursor->addDays($i)->toDateString();
            $filled = $filledByDay[$date] ?? 0;

            $perDay[] = [
                'date'       => $date,
                'filled'     => $filled,
                'percentage' => $denominator > 0 ? round($filled / $denominator * 100, 2) : 0.0,
            ];
        }

        return [
            'rombel'        => ['id' => $rombel->id, 'name' => $rombel->name],
            'from'          => $from,
            'to'            => $to,
            'student_count' => $studentCount,
            'per_day'       => $perDay,
            'per_student'   => $students->map(fn (Student $s) => [
                'student_id' => $s->id,
                'fullname'   => $s->fullname,
                'total_poin' => $poinByStudent[$s->id] ?? 0,
            ])->all(),
            'per_habit'     => $habits->map(fn (Habit $h) => [
                'habit_id' => $h->id,
                'code'     => $h->code,
                'name'     => $h->name,
                'filled'   => $filledByHabit[$h->id] ?? 0,
            ])->all(),
        ];
    }

    /**
     * Default: to = hari ini (Asia/Jakarta), from = to - 6 hari (7 hari termasuk hari ini).
     * Hanya `from` diberikan -> to tetap hari ini. Maksimal 31 hari (inklusif), from <= to.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveRange(array $params): array
    {
        $to   = $params['to'] ?? Carbon::now('Asia/Jakarta')->toDateString();
        $from = $params['from'] ?? CarbonImmutable::parse($to, 'UTC')->subDays(self::DEFAULT_DAYS - 1)->toDateString();

        if ($from > $to) {
            throw ValidationException::withMessages([
                'from' => ['from tidak boleh setelah to'],
            ]);
        }

        $days = (int) CarbonImmutable::parse($from, 'UTC')->diffInDays(CarbonImmutable::parse($to, 'UTC')) + 1;

        if ($days > self::MAX_DAYS) {
            throw ValidationException::withMessages([
                'to' => ['Rentang tanggal maksimal ' . self::MAX_DAYS . ' hari'],
            ]);
        }

        return [$from, $to];
    }

    /**
     * D-11: rombel tidak ada ATAU milik sekolah lain -> 404 (pesan sama, anti-enumerasi).
     * Rombel lain di sekolah yang sama (bukan own active rombel) -> 403.
     * Scope Teacher HANYA dari TeacherScopeService::activeAssignment().
     */
    protected function resolveRombel(int $rombelId): Rombel
    {
        $user    = auth('api')->user();
        $teacher = $user->teacher()->where('status', 'active')->first();

        if (!$teacher) {
            // F-14 residual: profil Teacher inactive tidak punya scope (pola R7).
            throw new HttpException(403, 'Profil Teacher tidak aktif atau tidak ditemukan');
        }

        $rombel = Rombel::where('id', $rombelId)->where('school_id', $teacher->school_id)->first();

        if (!$rombel) {
            throw new HttpException(404, 'Rombel tidak ditemukan');
        }

        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment || (int) $assignment->rombel_id !== (int) $rombel->id) {
            throw new HttpException(403, 'Rombel ini di luar own active rombel Anda');
        }

        return $rombel;
    }

    /** Satu query: jumlah entri per (tanggal, habit), semua 7 habit termasuk Bermasyarakat. */
    protected function countPerDayAndHabit(array $studentIds, string $from, string $to)
    {
        return DB::table('student_habits')
            ->whereIn('student_id', $studentIds)
            ->whereBetween('habit_date', [$from, $to])
            ->selectRaw('habit_date, habit_id, COUNT(*) AS filled')
            ->groupBy('habit_date', 'habit_id')
            ->get();
    }

    /**
     * Total poin per siswa. Query dikelompokkan di SQL; formula poin dipanggil dari
     * StudentHabitService (satu sumber), dikali jumlah entri per kelompok.
     *
     * @return array<int, int> student_id => total poin
     */
    protected function totalPoinPerStudent(array $studentIds, $habits, string $from, string $to): array
    {
        $poin = [];

        // 6 habit biasa: poin = indicators.point + bonus initiative, per (siswa, indikator, initiative).
        $rows = DB::table('student_habits')
            ->whereIn('student_id', $studentIds)
            ->whereBetween('habit_date', [$from, $to])
            ->whereNotNull('indicator_id')
            ->selectRaw('student_id, indicator_id, initiative, COUNT(*) AS filled')
            ->groupBy('student_id', 'indicator_id', 'initiative')
            ->get();

        if ($rows->isNotEmpty()) {
            $indicators = Indicator::whereIn('id', $rows->pluck('indicator_id')->unique()->all())->get()->keyBy('id');

            foreach ($rows as $row) {
                $poin[$row->student_id] = ($poin[$row->student_id] ?? 0)
                    + $this->scoring->habitPoin($indicators[$row->indicator_id], $row->initiative) * (int) $row->filled;
            }
        }

        // Bermasyarakat: poin berbasis jumlah indikator terpilih per entri (BUKAN SUM(point)).
        // SQL mengelompokkan entri menurut (siswa, initiative, jumlah positif, ada exclusive);
        // formula tetap dihitung oleh bermasyarakatPoin().
        $bermasyarakat = $habits->firstWhere('allows_multi_select', true);

        if ($bermasyarakat) {
            $perEntry = DB::table('student_habits AS sh')
                ->leftJoin('student_habit_indicators AS shi', 'shi.student_habit_id', '=', 'sh.id')
                ->leftJoin('indicators AS i', 'i.id', '=', 'shi.indicator_id')
                ->where('sh.habit_id', $bermasyarakat->id)
                ->whereIn('sh.student_id', $studentIds)
                ->whereBetween('sh.habit_date', [$from, $to])
                ->groupBy('sh.id', 'sh.student_id', 'sh.initiative')
                ->selectRaw('sh.student_id, sh.initiative, '
                    . 'SUM(CASE WHEN i.is_exclusive THEN 1 ELSE 0 END) AS excl, '
                    . 'SUM(CASE WHEN i.id IS NOT NULL AND NOT i.is_exclusive THEN 1 ELSE 0 END) AS pos');

            $groups = DB::query()
                ->fromSub($perEntry, 'e')
                ->selectRaw('student_id, initiative, excl, pos, COUNT(*) AS filled')
                ->groupBy('student_id', 'initiative', 'excl', 'pos')
                ->get();

            if ($groups->isNotEmpty()) {
                $all        = Indicator::where('habit_id', $bermasyarakat->id)->orderBy('sort_order')->get();
                $positives  = $all->where('is_exclusive', false)->values();
                $exclusives = $all->where('is_exclusive', true)->values();

                foreach ($groups as $g) {
                    $selected = (int) $g->excl > 0
                        ? $exclusives->take(1)
                        : $positives->take((int) $g->pos);

                    $poin[$g->student_id] = ($poin[$g->student_id] ?? 0)
                        + $this->scoring->bermasyarakatPoin($selected, $g->initiative) * (int) $g->filled;
                }
            }
        }

        return $poin;
    }
}
