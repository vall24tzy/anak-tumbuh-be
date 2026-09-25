# Team Log — STUDENT-LIFECYCLE-001

Dependency: STUDENT-ASSIGNMENT-001 ✅, TEACHER-ASSIGNMENT-001 ✅, STUDENT-001 ✅, AUTH-001/002 ✅ (semua sudah DONE).

## Apa yang dikerjakan
- `app/Services/Student/StudentLifecycleService.php` (baru): `markInactive()`,
  `markGraduated()`, `bulkGraduate()`. Reuse penuh `TeacherScopeService::activeAssignment()`
  dan `StudentClassAssignmentService::closeActiveAssignment()` — tidak duplikasi
  query scope/assignment.
- `app/Http/Controllers/V1/Student/StudentLifecycleController.php` (baru).
- `routes/v1/student-lifecycle.php` (baru) + didaftarkan di `routes/api.php`.
  `POST /students/{id}/inactive`, `POST /students/{id}/graduate`,
  `POST /rombels/{id}/bulk-graduate` — semua `role:teacher`.
- `tests/Feature/Student/StudentLifecycleTest.php` (baru, 10 test case).
- `docs/db-gap-analysis.md`: entri NOTE-07 (lihat di bawah).

## CONFLICT yang ditemukan sebelum implementasi, dan resolusinya

Lihat detail lengkap di `docs/db-gap-analysis.md` NOTE-07. Ringkas: scope Teacher
atas Student berbasis active assignment (StudentService, sudah ada sejak
STUDENT-001) bentrok dengan requirement idempotent lifecycle begitu assignment
Student ditutup oleh transisi lifecycle itu sendiri. Diselesaikan via Keputusan
Tim (user, 24 Sep 2026): idempotency check (school-scope only) dipisah dari
authorization transisi state sungguhan (active-rombel scope penuh, tidak berubah).

## Konvensi 403 vs 404 yang dipakai
- Student/Rombel id benar-benar tidak ada -> 404.
- Ada tapi di luar own active rombel Teacher (transisi state nyata) -> 403.
- Ada, sudah di target state, tapi beda sekolah dari Teacher (idempotent path) -> 403.
- Untuk `bulk-graduate`, rombel ada tapi bukan own active rombel Teacher -> 403
  (mengikuti konvensi umum §26 write-op, bukan bacaan literal "404" di teks
  Bagian 20 API Contract yang agak ambigu untuk kasus Rombel — dicatat di
  NOTE-07 sebagai keputusan penerapan, bukan literal SoT).

## Yang TIDAK diubah (sesuai batas task)
- `StudentService::studentInTeacherActiveRombel()` — TIDAK disentuh (Keputusan
  Tim poin 6). GET/PATCH Student yang sudah inactive/graduated tetap tidak
  otomatis accessible oleh Teacher pemiliknya dulu — ini bukan bug baru,
  melainkan konsekuensi eksplisit dari Keputusan Tim (poin 7: tidak melonggarkan
  scope ke level sekolah untuk kasus umum).
- Tidak ada endpoint reactivation (Matrix #41-43, konsisten pola Teacher
  Lifecycle #61).
- Tidak ada hard delete di jalur mana pun.
- Tidak ada promote/kenaikan kelas (KENAIKAN-001 tetap REMOVE).

## Status eksekusi — PENTING
- **`git apply --check`**: sukses, sudah diverifikasi di clone bersih
  (anak-tumbuh-be_1.zip + STUDENT-ASSIGNMENT-001.patch + STUDENT-001.patch +
  STUDENT-001-NOTE-06.patch + STUDENT-001-NOTE-06-test.patch, lalu patch ini).
- **`php -l`**: SEMUA file baru/berubah (Service, Controller, route,
  test, routes/api.php) lulus syntax check nyata — PHP 8.3 CLI benar-benar
  terpasang & dijalankan di sandbox ini (bukan asumsi).
- **PostgreSQL 16**: berhasil dipasang & dinyalakan di sandbox ini, DB test
  `anaktumbuh_test` sudah dibuat.
- **`php artisan migrate:fresh` / `php artisan test` — TIDAK bisa dijalankan**:
  Composer tidak bisa diinstal (`getcomposer.org` di luar allowed network
  domains sandbox ini) sehingga `vendor/` (framework Laravel) tidak bisa
  ditarik sama sekali — bukan soal DB, murni keterbatasan akses jaringan.
  Ini WAJIB dijalankan manual di dev env Anda:
  `php artisan migrate:fresh && php artisan test --filter=StudentLifecycleTest`
- Logic ditelusuri manual baris-per-baris terhadap kode StudentService/
  StudentClassAssignmentService/TeacherScopeService yang sudah ada dan sudah
  diverifikasi sebelumnya, bukan ditulis dari asumsi kosong.
