# STUDENT-001 — Log

**Status:** Selesai (REWORK -> DONE)
**PIC:** Orang 1
**Tanggal:** 23 Sep 2026

## Ringkasan

- Rewrite total StudentController/Service/Request/Resource -- dari level angka + classroom/grade_level mentah + is_active + qr_user plaintext, ke skema final MIGRATION-002 + assignment-based (student_class_assignments)
- POST /students: Teacher only, own active rombel; 1 DB transaction user+student(+qr_tokens kalau baru)+student_class_assignments; NISN existing sekolah sama -> reuse (tidak bikin identity baru), cross-school -> 403 cross_school_nisn
- PATCH /students/{id}: hanya fullname/gender/photo; field lain -> 422 protected_field (tidak silent-drop)
- GET index/show: scope SA global, HM own school, Teacher own active rombel (via TeacherScopeService), Student OWN saja (tanpa classmates list); scope mismatch di GET by id -> 404 (bukan 403, API Contract §26)
- Tidak ada destroy()/DELETE -- hard delete DENY semua role (lifecycle di STUDENT-LIFECYCLE-001)
- classroom & grade_level: tidak ada input, tidak ada kolom, tidak ada kode yang membaca/menulis
- Reuse TeacherScopeService::activeAssignment() dan StudentClassAssignmentService::createAssignment() -- tidak duplikasi logic
- NOTE-04 didokumentasikan: QR token dibuat SYSTEM saat create, raw token TIDAK di response (hash saja), raw token via QR-001 regenerate (belum dikerjakan)
- Breaking (expected, di luar scope): StudentImportService::store() masih panggil signature lama (school_id/classroom/grade_level) -- akan diperbaiki saat IMPORT-001 dikerjakan

## Test Case (manual review -- tidak ada PHP/DB live di sandbox, perlu dijalankan ulang di dev env)

### Positive
- POST {rombel_id: own active rombel, name, nisn baru, gender} -> 201, user+student+qr_tokens+assignment ke-create dalam 1 transaksi
- POST NISN existing sekolah sama, rombel/tahun berbeda dari assignment aktif existing -> 201, Student di-reuse, assignment baru dibuat, TIDAK ada User/Student baru
- PATCH {fullname/gender/photo} pada Student di own active rombel -> 200, hanya field itu yang berubah
- GET index sbg Student -> hanya 1 baris (diri sendiri)
- GET index sbg Teacher -> hanya Student di own active rombel
- GET /{id} sbg SA -> 200 utk Student manapun

### Negative
- POST rombel_id != own active rombel Teacher -> 403
- POST NISN existing di sekolah LAIN -> 403 cross_school_nisn
- POST NISN existing sekolah sama, assignment aktif SAMA rombel & tahun -> 409 duplicate_assignment, transaksi rollback total (user/student baru tidak ke-create kalau ini kasus NISN baru + race; utk existing tidak ada yg dibuat)
- PATCH body berisi nisn/user_id/school_id/poin/level/classroom -> 422 protected_field (per field)
- PATCH Student di luar own active rombel Teacher -> 403
- GET /{id} sbg Teacher utk Student di luar own active rombel -> 404 (bukan 403)
- GET /{id} sbg HM utk Student sekolah lain -> 404
- GET /{id} sbg Student utk Student lain -> 404
- SA/HM coba POST/PATCH -> 403 (role:teacher middleware)
- DELETE /students/{id} -> 404 (route tidak ada)

## Status Orang 2 (info manual, Task Board tidak diupdate)

- SETUP-001, ARCH-001, AUTH-001, AUTH-002, UPLOAD-001, SCHOOL-001, HEADMASTER-001, TEACHER-001, ACADEMICYEAR-001, ROMBEL-001, TEACHER-ASSIGNMENT-001, TEACHER-LIFECYCLE-001 -- selesai

## NOTE-06 (24 Sep 2026)

- Bug ditemukan saat review: `UpdateStudentRequest` pakai key `fullname`, padahal API Contract §17
  cuma kenal `name` (POST sudah benar pakai `name`, PATCH tidak konsisten). Fix: `UpdateStudentRequest`
  + `StudentService::update()` diselaraskan ke `name` (mapping ke kolom DB `fullname`). Detail &
  alasan di `docs/db-gap-analysis.md` NOTE-06.
- Test: `tests/Feature/Student/StudentUpdateNameFieldTest.php` (2 case: PATCH `name` → 200 fullname
  berubah; PATCH `fullname` → 422 protected_field). **BELUM dijalankan** di sandbox (tidak ada
  PHP/Postgres live) — perlu run manual: `php artisan test --filter=StudentUpdateNameFieldTest`
