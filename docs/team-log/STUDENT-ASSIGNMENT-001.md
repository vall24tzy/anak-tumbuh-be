# STUDENT-ASSIGNMENT-001 — Log

**Status:** Selesai (TODO -> DONE)
**PIC:** Orang 1
**Tanggal:** 23 Sep 2026

## Ringkasan

- student_class_assignments final (DB Design v1.5 §5.9): partial unique index duplicate+conflict sekaligus, partial index roster aktif, FK rombel_id RESTRICT
- StudentClassAssignmentService reusable: createAssignment(), closeActiveAssignment() -- disiapkan untuk STUDENT-001/IMPORT-001/STUDENT-LIFECYCLE-001, jangan duplikasi query assignment di service lain
- Endpoint: POST, GET list (scope SA/HM/Teacher/Student), GET /students/{id}/class-assignments (404 utk scope mismatch, sesuai API Contract §26)
- PATCH /student-class-assignments/{id}: awalnya NOTE-05 (gap, API Contract v1.2 §19 tidak merinci body PATCH) -- di-resolve via Keputusan Tim eksplisit 23 Sep 2026 (bukan literal SoT, dicatat terpisah di db-gap-analysis.md): body cuma { "status": "ended" }, field lain 422 (code field_not_accepted), no auto-move, no reactivation, idempotent, scope Teacher own active rombel (403 utk scope mismatch krn ini write op, beda dari GET yg 404)

## Test Case (manual review -- tidak ada PHP/DB live di sandbox, perlu dijalankan ulang di dev env)

### Positive
- POST rombel_id = own active rombel, academic_year_id sesuai rombel, student_id sekolah sama -> 201, status=active
- GET /student-class-assignments sbg Student -> hanya baris milik sendiri
- GET /students/{id}/class-assignments sbg Teacher, student ada di own active rombel -> 200, histori lengkap
- PATCH { "status": "ended" } pada assignment aktif milik own active rombel -> 200, status berubah ended
- PATCH { "status": "ended" } pada assignment yang SUDAH ended -> 200 idempotent, tidak ada perubahan lain

### Negative
- POST rombel_id != own active rombel -> 403
- POST student dari sekolah lain -> 403
- POST academic_year_id tidak match rombel.academic_year_id -> 422 rombel_mismatch
- POST ke rombel+tahun yg sudah ada assignment aktif sama -> 409 duplicate_assignment
- POST ke rombel lain, tahun sama dgn assignment aktif lain -> 409 assignment_conflict
- PATCH { "status": "active" } (reactivation) -> 422 (in:ended gagal)
- PATCH { "status": "ended", "rombel_id": 9 } -> 422 field_not_accepted utk rombel_id
- PATCH assignment id yang tidak exist -> 404
- PATCH assignment di luar own active rombel Teacher -> 403 (bukan 404, krn write op)
- GET /students/{id}/class-assignments utk student di luar scope requester -> 404

## Status Orang 2 (info manual, Task Board tidak diupdate)

- SETUP-001, ARCH-001, AUTH-001, AUTH-002, UPLOAD-001, SCHOOL-001, HEADMASTER-001, TEACHER-001, ACADEMICYEAR-001, ROMBEL-001, TEACHER-ASSIGNMENT-001, TEACHER-LIFECYCLE-001 -- selesai

## Patch 8 — GAP #5-scope reuse dari STUDENT-001 patch 7 (24 Sep 2026)

`studentInTeacherActiveRombel()` (dipakai `historyForStudent()`, endpoint `GET /students/{id}/class-assignments`)
diganti dari `$student->activeClassAssignment?->rombel_id` ke `Student::hasActiveAssignmentInRombel()` — sama
seperti perbaikan di `StudentService.php` (STUDENT-001 patch 7). Detail lengkap gap & alasan di
`docs/db-gap-analysis.md` bagian "STUDENT-001 — GAP #5-scope". Tidak ada perubahan pada `indexForTeacher()`,
`store()`, `createAssignment()`, `closeActiveAssignment()`, atau `endAssignment()` — semuanya sudah query
langsung ke `student_class_assignments`, tidak pernah pakai relasi ambigu ini.

Belum diverifikasi runtime.
