<?php

namespace App\Services\Dashboard;

use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\StudentHabit;
use App\Services\School\TeacherScopeService;
use App\Services\Student\StudentClassAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

// DASHBOARD-001 — API Contract v1.2 §25 (Metric minimal set, assignment-based,
// Dokumen v4.0 §9). §25 cuma kasih contoh response untuk dashboard/student;
// bentuk dashboard/teacher dirancang dari instruksi Task Board + prinsip
// "seluruhnya derivable dari resource yang sudah ada" (tidak menambah data baru).
//
// gamification_level SENGAJA belum ada di sini — field ini butuh LevelService
// dari LEVEL-RANKING-001 (belum dikerjakan), dan Task Board eksplisit menunda
// integrasinya ke tahap MERGE-001. Jangan tambahkan field ini sendiri sebelum
// LevelService benar-benar ada.

class DashboardService
{
    public function __construct(
        protected TeacherScopeService $teacherScope,
        protected StudentClassAssignmentService $assignments,
    ) {}

    protected function today(): string
    {
        // Konsisten dengan StudentHabitService::today() — Asia/Jakarta,
        // server-determined, walau APP_TIMEZONE belum di-set (Dokumen v4.0 §7).
        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    protected function currentStudent(): Student
    {
        $student = auth('api')->user()?->student;

        if (!$student) {
            throw new HttpException(403, 'Profil Student tidak ditemukan untuk akun ini');
        }

        return $student;
    }

    /**
     * GET /api/v1/dashboard/student — Student, own data (§25).
     */
    public function forStudent(): array
    {
        $student = $this->currentStudent();
        $assignment = $this->assignments->activeAssignmentInCurrentAcademicYear($student);

        $waliKelas = null;

        if ($assignment) {
            $teacherAssignment = \App\Models\TeacherClassAssignment::where('rombel_id', $assignment->rombel_id)
                ->where('academic_year_id', $assignment->academic_year_id)
                ->where('is_active', true)
                ->with('teacher')
                ->first();

            if ($teacherAssignment?->teacher) {
                $waliKelas = [
                    'id'   => $teacherAssignment->teacher->id,
                    'name' => $teacherAssignment->teacher->fullname,
                ];
            }
        }

        $totalHabit = \App\Models\Habit::count();
        $submittedToday = StudentHabit::where('student_id', $student->id)
            ->where('habit_date', $this->today())
            ->distinct('habit_id')
            ->count('habit_id');

        return [
            'wali_kelas'          => $waliKelas,
            'habit_summary_today' => [
                'submitted' => $submittedToday,
                'total'     => $totalHabit,
            ],
            'poin' => $student->poin,
        ];
    }

    /**
     * GET /api/v1/dashboard/teacher — Teacher, own active rombel (§25).
     */
    public function forTeacher(): array
    {
        $user = auth('api')->user();
        $activeAssignment = $this->teacherScope->activeAssignment($user);

        if (!$activeAssignment) {
            throw new HttpException(403, 'Anda belum memiliki active rombel.');
        }

        $rombelId = $activeAssignment->rombel_id;
        $academicYearId = $activeAssignment->academic_year_id;

        // "active": Student yang MASIH punya assignment aktif di rombel ini.
        $activeCount = StudentClassAssignment::where('rombel_id', $rombelId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'active')
            ->distinct('student_id')
            ->count('student_id');

        // "inactive"/"graduated": Student yang assignment-nya di rombel ini
        // sudah 'ended' KARENA transisi lifecycle (bukan pindah rombel) —
        // dibuktikan dari status Student SEKARANG yang memang inactive/graduated.
        $endedStudentIds = StudentClassAssignment::where('rombel_id', $rombelId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'ended')
            ->pluck('student_id');

        $statusBreakdown = Student::whereIn('id', $endedStudentIds)
            ->whereIn('status', ['inactive', 'graduated'])
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $studentCount = [
            'active'    => $activeCount,
            'inactive'  => (int) ($statusBreakdown['inactive'] ?? 0),
            'graduated' => (int) ($statusBreakdown['graduated'] ?? 0),
        ];

        // Rata-rata habit yang sudah diisi HARI INI, di antara Student yang
        // MASIH aktif di rombel ini (Student inactive/graduated tidak lagi
        // relevan buat "hari ini").
        $activeStudentIds = StudentClassAssignment::where('rombel_id', $rombelId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'active')
            ->pluck('student_id');

        $totalHabit = \App\Models\Habit::count();
        $averageSubmitted = 0.0;

        if ($activeStudentIds->isNotEmpty()) {
            $submittedSum = StudentHabit::whereIn('student_id', $activeStudentIds)
                ->where('habit_date', $this->today())
                ->select('student_id', DB::raw('count(distinct habit_id) as c'))
                ->groupBy('student_id')
                ->get()
                ->sum('c');

            $averageSubmitted = round($submittedSum / $activeStudentIds->count(), 2);
        }

        return [
            'rombel' => [
                'id'   => $rombelId,
                'name' => $activeAssignment->rombel->name,
            ],
            'student_count' => $studentCount,
            'habit_submission_today' => [
                'average_submitted' => $averageSubmitted,
                'total'             => $totalHabit,
            ],
        ];
    }

    protected function currentHeadmaster(): Headmaster
    {
        $headmaster = auth('api')->user()?->headmaster;

        if (!$headmaster) {
            throw new HttpException(403, 'Profil Headmaster tidak ditemukan untuk akun ini');
        }

        return $headmaster;
    }

    /**
     * GET /api/v1/dashboard/headmaster — Headmaster, own school (§25).
     * Per rombel: jumlah Student (assignment aktif) & rata-rata submission
     * habit hari ini. Pakai agregasi query (bukan query per-rombel di PHP
     * loop), sesuai instruksi Task Board.
     */
    public function forHeadmaster(): array
    {
        $headmaster = $this->currentHeadmaster();

        $activeYear = \App\Models\AcademicYear::where('school_id', $headmaster->school_id)
            ->where('is_active', true)
            ->first();

        // Belum ada tahun ajaran aktif -> tidak error, cukup list kosong
        // (school baru/belum setup ACADEMICYEAR-001, bukan kondisi salah).
        if (!$activeYear) {
            return ['rombels' => []];
        }

        $rombels = Rombel::where('school_id', $headmaster->school_id)
            ->where('academic_year_id', $activeYear->id)
            ->get(['id', 'name', 'grade_level']);

        if ($rombels->isEmpty()) {
            return ['rombels' => []];
        }

        $rombelIds = $rombels->pluck('id');

        // Query 1: jumlah Student aktif per rombel — 1 query untuk SEMUA
        // rombel sekaligus (groupBy), bukan 1 query per rombel.
        $studentCounts = StudentClassAssignment::whereIn('rombel_id', $rombelIds)
            ->where('status', 'active')
            ->select('rombel_id', DB::raw('count(distinct student_id) as total'))
            ->groupBy('rombel_id')
            ->pluck('total', 'rombel_id');

        // Query 2: assignment aktif per rombel (buat tau siapa student-nya),
        // lalu Query 3: submission hari ini per student — sama-sama 1 query
        // untuk semua rombel, hasilnya baru digabung di PHP (bukan query
        // ke DB lagi per rombel).
        $activeAssignments = StudentClassAssignment::whereIn('rombel_id', $rombelIds)
            ->where('status', 'active')
            ->get(['rombel_id', 'student_id'])
            ->groupBy('rombel_id');

        $allActiveStudentIds = StudentClassAssignment::whereIn('rombel_id', $rombelIds)
            ->where('status', 'active')
            ->pluck('student_id');

        $submittedPerStudent = StudentHabit::whereIn('student_id', $allActiveStudentIds)
            ->where('habit_date', $this->today())
            ->select('student_id', DB::raw('count(distinct habit_id) as c'))
            ->groupBy('student_id')
            ->pluck('c', 'student_id');

        $result = $rombels->map(function (Rombel $rombel) use ($studentCounts, $activeAssignments, $submittedPerStudent) {
            $studentsInRombel = $activeAssignments->get($rombel->id, collect());
            $totalStudent = $studentCounts[$rombel->id] ?? 0;

            $averageSubmitted = 0.0;
            if ($studentsInRombel->isNotEmpty()) {
                $sum = $studentsInRombel->sum(fn ($a) => $submittedPerStudent[$a->student_id] ?? 0);
                $averageSubmitted = round($sum / $studentsInRombel->count(), 2);
            }

            return [
                'id'                      => $rombel->id,
                'name'                    => $rombel->name,
                'grade_level'             => $rombel->grade_level,
                'student_count'           => $totalStudent,
                'average_submitted_today' => $averageSubmitted,
            ];
        });

        return ['rombels' => $result->values()->all()];
    }

    /**
     * GET /api/v1/dashboard/super-admin — Super Admin, global (§25).
     */
    public function forSuperAdmin(): array
    {
        return [
            'school_count'     => School::count(),
            'headmaster_count' => Headmaster::count(),
        ];
    }
}