<?php

namespace App\Services\School;

use App\Exceptions\ApiException;
use App\Services\User\AuditLogService;
use App\Services\User\InitialPasswordHasher;
use App\Traits\PaginatesResults;
use App\Models\TeacherClassAssignment;
use App\Models\Headmaster;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TeacherService
{
    use PaginatesResults;

    public function __construct(protected AuditLogService $auditLog) {}

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

    // F-15: paginasi bersama (default 20, max 100, urutan id). total = Teacher di sekolah Headmaster ini
    // (scope diterapkan SEBELUM paginasi), bukan jumlah global.
    public function index(array $params = [])
    {
        $schoolId = $this->headmasterSchoolId($this->currentUser());

        return $this->paginateQuery(Teacher::with('school')->where('school_id', $schoolId), $params);
    }

    public function show(int $id)
    {
        $schoolId = $this->headmasterSchoolId($this->currentUser());
        $teacher = Teacher::with('school')
            ->where('school_id', $schoolId)
            ->find($id);

        if (!$teacher) {
            throw new HttpException(404, 'Guru tidak ditemukan');
        }

        return $teacher;
    }

    /**
     * Fetch GLOBAL (tanpa filter school_id) — beda dari show(). Dipakai untuk
     * operasi WRITE yang scope-nya jelas dari path (bukan GET), supaya "tidak
     * ditemukan" (404) dan "ada tapi di luar scope" (403) tetap bisa dibedakan
     * (API Contract v1.2 §10: "403 bila target di luar scope actor (cross-school
     * untuk Headmaster→Teacher); 404 bila target tidak ditemukan"). show() di
     * atas SENGAJA tidak diubah — GET memang harus 404 untuk keduanya (§26,
     * mencegah enumerasi lintas-scope), beda konvensi dari write endpoint ini.
     */
    protected function findTeacherOrFail(int $id): Teacher
    {
        $teacher = Teacher::with('school')->find($id);

        if (!$teacher) {
            throw new HttpException(404, 'Guru tidak ditemukan');
        }

        return $teacher;
    }

    public function store(array $data): Teacher
    {
        $schoolId = $this->headmasterSchoolId($this->currentUser());

        if ((int) $data['school_id'] !== $schoolId) {
            throw new HttpException(403, 'Anda hanya boleh menambahkan guru ke sekolah sendiri');
        }

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'role'     => 'teacher',
                'username' => $data['nip'],
                // F-55: password awal = NIP (guessable by design) -> round rendah; reset-password tetap round penuh.
                // Satu titik untuk create biasa DAN import (TeacherImportService memanggil store()).
                'password' => InitialPasswordHasher::make($data['nip']),
            ]);

            return Teacher::create([
                'user_id'   => $user->id,
                'school_id' => $data['school_id'],
                'fullname'  => $data['name'],
                'nip'       => $data['nip'],
                'gender'    => $data['gender'],
                'status'    => 'active',
            ])->load('school');
        });
    }

    /**
     * F-49 (MASTER_AUDIT_REMEDIATION_MATRIX v3.1, keputusan FINAL): Teacher
     * cross-school pada endpoint ini = 404, sama dengan "tidak ada". show()
     * menyaring school_id sebelum find(), jadi keberadaan Teacher di sekolah
     * lain tidak bocor. Sengaja TIDAK memakai pola resetPassword() (403); F-49
     * melarang digeneralisasi ke endpoint write lain.
     */
    public function inactive(int $id): Teacher
    {
        $teacher = $this->show($id); // reuse: scope own-school + 404 kalau di luar

        return DB::transaction(function () use ($teacher) {
            $teacher->update(['status' => 'inactive']);

            TeacherClassAssignment::where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return $teacher->fresh('school');
        });
    }

    /**
     * INTEG-TEACHER-001 (keputusan FINAL, Matrix #66): ubah name (kolom fullname) dan gender, Headmaster
     * own school. Hanya dua field itu yang sampai ke sini (UpdateTeacherRequest menolak sisanya 422).
     *
     * - Scope: reuse show() -> Teacher di sekolah lain = 404, sama dengan "tidak ada" (D-02 FINAL, pola F-49).
     * - Teacher inactive tidak boleh diedit: 409 `teacher_inactive_readonly` (pola
     *   `assignment_inactive_readonly`; inactive tidak punya reaktivasi, Matrix #61). Dicek SETELAH scope,
     *   jadi lintas sekolah tetap 404.
     * - D-03/D-14/D-15 FINAL: dicatat ke audit_logs (action `teacher_update`, target_user_id = user guru)
     *   HANYA bila ada nilai yang benar-benar berubah. Beda huruf besar/kecil dihitung berubah; body tanpa
     *   perubahan (nilai sama atau kosong) = 200 tanpa baris audit. Bila kelak semua PATCH harus tercatat,
     *   cukup ubah blok `isDirty` di bawah. Baris audit tidak membawa nilai lama/baru (gap D-16).
     * - nip, username, password, status, school_id tidak tersentuh; token Teacher tidak dicabut.
     */
    public function update(int $id, array $data): Teacher
    {
        $actor = $this->currentUser();
        $teacher = $this->show($id);

        if ($teacher->status !== 'active') {
            throw new ApiException(
                'teacher_inactive_readonly',
                409,
                'Guru yang sudah nonaktif tidak dapat diubah'
            );
        }

        $attributes = [];

        if (array_key_exists('name', $data)) {
            $attributes['fullname'] = $data['name'];
        }

        if (array_key_exists('gender', $data)) {
            $attributes['gender'] = $data['gender'];
        }

        $teacher->fill($attributes);

        if (!$teacher->isDirty()) {
            return $teacher;
        }

        $teacher->save();

        $this->auditLog->record($actor, 'teacher_update', $teacher->user);

        return $teacher->fresh('school');
    }

    public function resetPassword(int $id, array $data): Teacher
    {
        $teacher = $this->findTeacherOrFail($id);
        $actor = $this->currentUser();

        if ($teacher->school_id !== $this->headmasterSchoolId($actor)) {
            throw new HttpException(403, 'Guru ini di luar sekolah Anda');
        }

        $teacher->user->update(['password' => $data['new_password']]);

        $this->auditLog->record($actor, 'password_reset', $teacher->user);

        return $teacher;
    }
}