<?php

namespace App\Services\Student;

use App\Services\User\AuditLogService;
use App\Exceptions\ApiException;
use App\Models\Headmaster;
use App\Models\QrToken;
use App\Models\Student;
use App\Models\User;
use App\Services\School\TeacherScopeService;
use App\Services\Upload\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §17, DB Design v1.5 §5.5. Student = permanent identity;
// Teacher operator dalam own active rombel. Reuse TeacherScopeService (School
// domain) & StudentClassAssignmentService — JANGAN duplikasi query scope/assignment
// di sini.

class StudentService
{
    public function __construct(
        protected UploadService $uploadService,
        protected TeacherScopeService $teacherScope,
        protected StudentClassAssignmentService $assignmentService,
        protected AuditLogService $auditLog,
    ) {}

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function headmasterSchoolId($user): ?int
    {
        return Headmaster::where('user_id', $user->id)->value('school_id');
    }

    /**
     * GET /api/v1/students — scope Matrix #9: SA global, HM own school,
     * Teacher own active rombel, Student OWN (tanpa classmates list).
     */
    public function index()
    {
        $user = $this->currentUser();

        return match ($user->role) {
            'super_admin' => Student::with(['school', 'user'])->get(),
            'headmaster'  => Student::with(['school', 'user'])
                                ->where('school_id', $this->headmasterSchoolId($user))->get(),
            'teacher'     => $this->indexForTeacher($user),
            'student'     => Student::with(['school', 'user'])
                                ->where('id', $user->student?->id)->get(),
            default       => collect(),
        };
    }

    protected function indexForTeacher($user)
    {
        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment) {
            return collect();
        }

        return Student::with(['school', 'user'])
            ->whereHas('activeClassAssignment', fn ($q) => $q->where('rombel_id', $assignment->rombel_id))
            ->get();
    }

    /**
     * GET /api/v1/students/{id} — scope mismatch -> 404 (resource existence
     * leak, API Contract §26), bukan 403.
     */
    public function show(int $id)
    {
        $user = $this->currentUser();
        $student = Student::with(['school', 'user'])->find($id);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        if (!$this->canView($user, $student)) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        return $student;
    }

    protected function canView($user, Student $student): bool
    {
        return match ($user->role) {
            'super_admin' => true,
            'headmaster'  => $student->school_id === $this->headmasterSchoolId($user),
            'teacher'     => $this->studentInTeacherActiveRombel($user, $student),
            'student'     => $user->student?->id === $student->id,
            default       => false,
        };
    }

    /**
     * GAP #5-scope (24 Sep 2026): sebelumnya pakai
     * $student->activeClassAssignment?->rombel_id (hasOne tanpa urutan tahun,
     * non-deterministik kalau Student punya >1 assignment aktif lintas Academic
     * Year — DB Design v1.5 §5.9). Diganti EXISTS check via
     * Student::hasActiveAssignmentInRombel() — deterministik, tanpa mengubah
     * skema/constraint. Business rule "own active rombel" TIDAK berubah.
     */
    protected function studentInTeacherActiveRombel($user, Student $student): bool
    {
        $teacherRombelId = $this->teacherScope->activeAssignment($user)?->rombel_id;

        return $teacherRombelId !== null && $student->hasActiveAssignmentInRombel($teacherRombelId);
    }

    /**
     * POST /api/v1/students — Teacher only, own active rombel (Matrix #7).
     * 1 DB transaction: user + student (+ qr_tokens kalau Student baru) +
     * student_class_assignments. NISN existing (sekolah sama) -> reuse Student,
     * TIDAK membuat identity baru; cross-school -> 403 (Dokumen v4.0 §5.4/§12).
     */
    public function store(array $data): Student
    {
        $user = $this->currentUser();
        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment) {
            throw new HttpException(403, 'Anda belum memiliki active rombel');
        }

        if ((int) $data['rombel_id'] !== $assignment->rombel_id) {
            throw new HttpException(403, 'Anda hanya boleh create Student pada own active rombel');
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        $existing = Student::where('nisn', $data['nisn'])->first();

        if ($existing && $existing->school_id !== $assignment->school_id) {
            throw new ApiException('cross_school_nisn', 403, 'NISN sudah terdaftar di sekolah lain');
        }

        $student = DB::transaction(function () use ($data, $assignment, $existing) {
            if ($existing) {
                // Reuse — identity permanent, field administratif TIDAK ditimpa
                // diam-diam (mencegah data loss dari kesamaan NISN yang tidak
                // disengaja saat re-import/re-create).
                $student = $existing;
            } else {
                $newUser = User::create([
                    'role'     => 'student',
                    'username' => $data['nisn'],
                    'password' => $data['nisn'], // auto-hash via cast 'hashed' (User model)
                ]);

                $student = Student::create([
                    'user_id'   => $newUser->id,
                    'school_id' => $assignment->school_id,
                    'fullname'  => $data['name'],
                    'nisn'      => $data['nisn'],
                    'gender'    => $data['gender'],
                    'poin'      => 0,
                    'status'    => 'active',
                ]);

                // QR token SYSTEM-generated saat create (DB Design v1.5 §5.15).
                // Raw token TIDAK di-expose di response ini (NOTE-04) — hanya
                // hash yang disimpan; raw token didapat lewat regenerate (QR-001).
                QrToken::create([
                    'student_id' => $student->id,
                    'token_hash' => Hash::make((string) Str::uuid()),
                ]);
            }

            // student_class_assignments — method reusable STUDENT-ASSIGNMENT-001.
            // 409 duplicate_assignment/assignment_conflict ditegakkan di sana;
            // exception di sini otomatis rollback seluruh transaksi (termasuk
            // User+Student+QrToken yang baru dibuat kalau ini Student baru).
            $this->assignmentService->createAssignment(
                $student->id,
                $assignment->rombel_id,
                $assignment->academic_year_id,
            );

            return $student;
        });

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'students', $student->id, $student->photo_path);
            $student->update(['photo_path' => $path]);
        }

        return $student->fresh()->load(['school', 'user']);
    }

    /**
     * PATCH /api/v1/students/{id} — Teacher only, Student dalam own active
     * rombel. Hanya field administratif (protected field ditolak di Request).
     */
    public function update(int $id, array $data)
    {
        $user = $this->currentUser();
        $student = Student::find($id);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        // GAP #5-scope (24 Sep 2026) — lihat studentInTeacherActiveRombel() di atas;
        // sama-sama diganti EXISTS check, bukan fetch-satu-baris-lalu-compare.
        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment || !$student->hasActiveAssignmentInRombel($assignment->rombel_id)) {
            throw new HttpException(403, 'Anda hanya boleh mengubah Student pada own active rombel');
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        if (array_key_exists('name', $data)) {
            $data['fullname'] = $data['name'];
            unset($data['name']);
        }

        if (!empty($data)) {
            $student->update($data);
        }

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'students', $student->id, $student->photo_path);
            $student->update(['photo_path' => $path]);
        }

        return $student->fresh()->load(['school', 'user']);
    }

    public function resetPassword(int $id, array $data): Student
    {
        $user = $this->currentUser();
        $student = Student::find($id);

        if (!$student) {
            throw new HttpException(404, 'Siswa tidak ditemukan');
        }

        if (!$this->studentInTeacherActiveRombel($user, $student)) {
            throw new HttpException(403, 'Student ini di luar own active rombel Anda');
        }

        $student->user->update(['password' => $data['new_password']]);

        $this->auditLog->record($user, 'password_reset', $student->user);

        return $student;
    }
}
