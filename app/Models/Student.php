<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// classroom & grade_level DIHAPUS (DB Design v1.5 Risk #1) — digantikan sepenuhnya
// oleh student_class_assignments + rombels.grade_level. level/gamification_level
// tidak disimpan sebagai kolom — dihitung real-time (LEVEL-RANKING-001).

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'school_id',
        'fullname',
        'nisn',
        'gender',
        'photo_path',
        'poin',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function studentHabits()
    {
        return $this->hasMany(StudentHabit::class);
    }

    // QR token terikat ke Student (bukan User generik) — DB Design v1.5 §5.15.
    public function qrToken()
    {
        return $this->hasOne(QrToken::class);
    }

    // Histori lengkap (current + past) — student_class_assignments SATU-SATUNYA
    // source of truth placement (DB Design v1.5 §5.9, STUDENT-ASSIGNMENT-001).
    public function studentClassAssignments()
    {
        return $this->hasMany(StudentClassAssignment::class);
    }

    // Current placement saja (status='active'). PERINGATAN (GAP #5-scope, 24 Sep
    // 2026): ini hasOne TANPA urutan academic_year. DB Design v1.5 §5.9 mengizinkan
    // Student punya LEBIH DARI SATU assignment aktif sekaligus (lintas Academic
    // Year berbeda) — kalau itu terjadi, baris mana yang diambil relasi ini TIDAK
    // deterministik. JANGAN pakai relasi ini untuk scope/authorization check
    // ("apakah Student ada di rombel X") — pakai hasActiveAssignmentInRombel()
    // di bawah (EXISTS query, deterministik). Relasi ini hanya aman dipakai kalau
    // kamu genuinely butuh SATU baris snapshot dan sudah sadar batasannya — untuk
    // itu pun lebih baik StudentClassAssignmentService::activeAssignmentInCurrentAcademicYear()
    // (scoped ke Academic Year aktif sekolah, dipakai StudentHabitService untuk
    // classroom snapshot).
    public function activeClassAssignment()
    {
        return $this->hasOne(StudentClassAssignment::class)->where('status', 'active');
    }

    /**
     * Scope/authorization check: apakah Student ini punya assignment
     * status='active' pada rombel_id tertentu — EXISTS query, BUKAN fetch-satu-
     * baris-lalu-compare seperti activeClassAssignment(). Deterministik walau
     * Student punya >1 assignment aktif lintas Academic Year (DB Design §5.9),
     * karena mengecek keberadaan baris yang cocok, bukan mengambil "salah satu"
     * baris lalu membandingkan atributnya. Dipakai StudentService, StudentClassAssignmentService::
     * studentInTeacherActiveRombel(), StudentLifecycleService — JANGAN duplikasi
     * query ini, selalu panggil method ini (GAP #5-scope, 24 Sep 2026; skema/
     * constraint TIDAK diubah).
     */
    public function hasActiveAssignmentInRombel(int $rombelId): bool
    {
        return $this->studentClassAssignments()
            ->where('rombel_id', $rombelId)
            ->where('status', 'active')
            ->exists();
    }
}
