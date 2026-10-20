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
use App\Services\User\InitialPasswordHasher;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §17, DB Design v1.5 §5.5. Student = permanent identity;
// Teacher operator dalam own active rombel. Reuse TeacherScopeService (School
// domain) & StudentClassAssignmentService — JANGAN duplikasi query scope/assignment
// di sini.

class StudentService
{
    // F-06: foto Student disimpan di disk privat (config/filesystems.php 'private'),
    // tidak pernah di storage publik. Logo School tetap di disk 'public'.
    public const PHOTO_DISK = 'private';

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
        // F-14 residual: hanya Headmaster AKTIF yang punya scope; selain itu 403 (pola R7).
        return $user->activeHeadmasterSchoolId()
            ?? throw new HttpException(403, 'Profil Headmaster tidak aktif atau tidak ditemukan');
    }

    /**
     * GET /api/v1/students — scope Matrix #9: SA global, HM own school,
     * Teacher own active rombel, Student OWN (tanpa classmates list).
     */
    /**
     * GET /students — dipaginasi (API Contract v1.2 §7: page, per_page default 20,
     * maksimum 100, meta). Scope per role TIDAK berubah, hanya dibungkus paginator.
     * orderBy('id') = urutan deterministik supaya halaman tidak bergeser/ganda
     * (detail teknis, bukan sort baru untuk client).
     */
    public function index(array $params = []): LengthAwarePaginator
    {
        $user = $this->currentUser();

        $query = match ($user->role) {
            'super_admin' => Student::with(['school', 'user']),
            'headmaster'  => Student::with(['school', 'user'])
                                ->where('school_id', $this->headmasterSchoolId($user)),
            'teacher'     => $this->indexQueryForTeacher($user),
            'student'     => Student::with(['school', 'user'])
                                ->where('id', $user->student?->id),
            default       => Student::with(['school', 'user'])->whereRaw('1 = 0'),
        };

        return $query->orderBy('id')->paginate(
            (int) ($params['per_page'] ?? 20),
            ['*'],
            'page',
            (int) ($params['page'] ?? 1),
        );
    }

    protected function indexQueryForTeacher($user): Builder
    {
        $assignment = $this->teacherScope->activeAssignment($user);

        if (!$assignment) {
            // Teacher tanpa active assignment: 200 + daftar kosong (perilaku lama
            // dipertahankan, hanya kini lewat paginator).
            return Student::with(['school', 'user'])->whereRaw('1 = 0');
        }

        // INTEG-QR-001: eager load qrToken (qr_regenerated_at di GET /students, khusus Teacher) -> tanpa N+1.
        return Student::with(['school', 'user', 'qrToken'])
            ->whereHas('activeClassAssignment', fn ($q) => $q->where('rombel_id', $assignment->rombel_id));
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

    /**
     * F-06: sajikan foto Student dari disk privat. Otorisasi memakai show() yang SAMA
     * dengan GET /students/{id} (Super Admin; Headmaster own school; Teacher own active
     * rombel; Student diri sendiri), termasuk perbaikan scope F-14. Di luar scope = 404
     * (API Contract §26, konvensi GET). Tidak ada fallback ke disk publik.
     */
    public function photo(int $id): StreamedResponse
    {
        $student = $this->show($id);

        if (!$student->photo_path || !Storage::disk(self::PHOTO_DISK)->exists($student->photo_path)) {
            throw new HttpException(404, 'Foto siswa tidak ditemukan');
        }

        return Storage::disk(self::PHOTO_DISK)->response($student->photo_path, null, [
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

        // F-53: NISN Student BARU (belum ada Student ber-NISN ini) tidak boleh sama dengan username akun
        // non-Student (SA/Headmaster/Teacher). Validasi aktif di service, SEBELUM insert. NISN yang sama
        // dengan NISN Student lain (re-enrollment) bukan collision; itu jalur reuse di bawah dan tidak disentuh.
        if (!$existing) {
            $this->assertNisnNotStaffUsername($data['nisn']);
        }

        $student = $this->guardStaffUsernameRace($data['nisn'], fn () => DB::transaction(function () use ($data, $assignment, $existing) {
            if ($existing) {
                // Reuse — identity permanent, field administratif TIDAK ditimpa
                // diam-diam (mencegah data loss dari kesamaan NISN yang tidak
                // disengaja saat re-import/re-create).
                $student = $existing;
            } else {
                $newUser = User::create([
                    'role'     => 'student',
                    'username' => $data['nisn'],
                    // F-55: password awal = NISN (guessable by design) -> round rendah, di-hash eksplisit SEKALI di sini;
                    // cast 'hashed' (User) menerima hash yang cost-nya <= round konfigurasi dan tidak meng-hash ulang.
                    // Satu titik untuk create biasa DAN import (StudentImportService memanggil store()).
                    'password' => InitialPasswordHasher::make($data['nisn']),
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
        }));

        if ($logoFile instanceof UploadedFile) {
            $oldPhotoPath = $student->photo_path;
            $path = $this->uploadService->upload($logoFile, 'students', $student->id, $oldPhotoPath, self::PHOTO_DISK);
            $student->update(['photo_path' => $path]);

            // F-06: sisa foto lama di disk publik (sebelum relokasi) ikut dihapus setelah
            // upload baru sukses, supaya tidak ada salinan publik yang tertinggal.
            $this->uploadService->delete($oldPhotoPath, 'public');
        }

        return $student->fresh()->load(['school', 'user']);
    }

    /**
     * F-53: collision NISN vs username role LAIN. users.username = NISN untuk Student, dan username unik
     * lintas role, jadi NISN yang sama dengan username SA/Headmaster/Teacher tidak bisa dipakai. Ini LOGIC,
     * bukan constraint: NISN yang sama dengan NISN Student lain sah (reuse) dan tidak boleh ikut ditolak,
     * jadi query hanya melihat baris yang role-nya BUKAN 'student'. 422 (business validation), bukan 500.
     */
    protected function assertNisnNotStaffUsername(string $nisn): void
    {
        if (User::where('username', $nisn)->where('role', '!=', 'student')->exists()) {
            $message = 'NISN sama dengan username akun staf (Super Admin/Kepala Sekolah/Guru) sehingga tidak dapat dipakai';

            throw new ApiException(
                'nisn_conflicts_with_staff_username',
                422,
                $message,
                [['field' => 'nisn', 'reason' => 'nisn_conflicts_with_staff_username', 'message' => $message]]
            );
        }
    }

    /**
     * F-53 (jaring pengaman race, bukan pengganti validasi di atas): pre-check bukan atomik dengan insert,
     * jadi akun staf yang dibuat di antara cek dan INSERT tetap menabrak constraint users.username yang SUDAH
     * ada (bukan constraint baru; NISN tetap tidak unique global). Pelanggaran itu hanya diterjemahkan jadi
     * 422 bila penyebabnya memang akun non-Student; penyebab lain (mis. dua Student baru ber-NISN sama
     * bersamaan) dilempar ulang apa adanya, tidak salah dilabeli sebagai bentrok dengan staf.
     */
    protected function guardStaffUsernameRace(string $nisn, Closure $callback)
    {
        try {
            return $callback();
        } catch (UniqueConstraintViolationException $e) {
            $this->assertNisnNotStaffUsername($nisn);

            throw $e;
        }
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
            $oldPhotoPath = $student->photo_path;
            $path = $this->uploadService->upload($logoFile, 'students', $student->id, $oldPhotoPath, self::PHOTO_DISK);
            $student->update(['photo_path' => $path]);

            // F-06: sisa foto lama di disk publik (sebelum relokasi) ikut dihapus setelah
            // upload baru sukses, supaya tidak ada salinan publik yang tertinggal.
            $this->uploadService->delete($oldPhotoPath, 'public');
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

        return $student->load(['school', 'user']);
    }
}
