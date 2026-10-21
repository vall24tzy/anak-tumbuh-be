# INTEG-TEACHER-001 — Log

**Status:** kode, PHPUnit, dan Postman folder `INTEG-O2` selesai dan lulus (dilaporkan Orang 2). Dua usulan di addendum
bagian 5 (409 untuk guru inactive; body kosong = 200 no-op) dan review silang Orang 1 masih menunggu; status task di
Task Board dinaikkan ke DONE oleh pemilik setelah itu.
**PIC:** Orang 2 · **Reviewer:** Orang 1 · **Dasar:** D-02, D-03, D-04, D-14, D-15, D-16; Matrix v5.3 #66 (merujuk #61)

## Ringkasan

- `PATCH /api/v1/teachers/{id}`: ubah `name` (kolom `fullname`) dan `gender`, Headmaster own school. Respons memakai
  `TeacherResource` yang ada.
- NIP, username, school_id, status, password: 422 `protected_field`. Guru sekolah lain / id tidak ada: 404 (D-02).
  Guru inactive: 409 `teacher_inactive_readonly`. Teacher/Student/Super Admin: 403. Gender salah / name tidak
  valid: 422 `validation_failed`.
- Audit lewat `AuditLogService`: action `teacher_update`, `target_user_id` = user guru, hanya bila ada nilai
  berubah (D-14); nilai sama / body kosong = 200 tanpa baris audit.
- Tidak ada rate limit baru (D-04), migration, atau perubahan field/endpoint lama.
- Addendum: `docs/addendum/API-Contract-v1.2-Addendum-INTEG-TEACHER-001.md` (revisi Contract §15, dua usulan yang
  menunggu konfirmasi pemilik: 409 untuk guru inactive, body kosong = no-op 200).
- Koreksi: dokumen tugas FE (19 Okt 2026) menyebut `nip` pada update guru; keputusan board melarang NIP diubah.

## File

- `routes/v1/teacher.php`, `app/Http/Controllers/V1/School/TeacherController.php`,
  `app/Services/School/TeacherService.php`, `app/Http/Requests/School/UpdateTeacherRequest.php` (baru)
- Test: `tests/Feature/School/TeacherUpdateTest.php` (baru, 19 test)
- `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json`: 17 request baru di folder `INTEG-O2` (membuat guru uji
  dengan NIP unik per run di sekolah Headmaster; data bersama tidak diubah); variabel `integ_teacher_id`,
  `integ_teacher_nip`; request lama tidak diubah
- `docs/addendum/API-Contract-v1.2-Addendum-INTEG-TEACHER-001.md`, `docs/team-log/INTEG-TEACHER-001.md`

## Test

- Baseline sebelum task: `php artisan test` **564 passed (3089 assertions)**, dilaporkan Orang 2 (setelah
  INTEG-SCHOOL-001).
- Suite penuh setelah task ini, dijalankan setelah Orang 2 melakukan pull perubahan Orang 1 (INTEG-REKAP-001, HEAD
  `976a55e`) dan memasang patch kode: `php artisan test` **598 passed (3323 assertions)**, 104,44 s, dilaporkan
  Orang 2. Selisih +34 dari 564 = 19 test `TeacherUpdateTest` + 15 test dari perubahan Orang 1 (dugaan:
  INTEG-REKAP-001; belum diverifikasi per test). Perkiraan awal 583 tidak memperhitungkan pull itu.
- Postman folder `INTEG-O2` (Collection Runner, environment none, 1 iterasi, jam 08:27 waktu lokal Orang 2):
  **85 passed, 0 failed, 0 errors**, durasi 30,2 s. 85 = 52 assertion yang sudah ada + 33 assertion dari 17 request
  baru INTEG-TEACHER-001. Dilaporkan Orang 2 dari layar Runner; tanggal tidak tertera di layar (hanya "today") dan
  file hasil Runner tidak disimpan di repo (D-12: disimpan oleh INTEG-TEST-002).
- Postman tidak dapat memeriksa baris `audit_logs` (tidak ada endpoint baca) maupun kasus guru lintas sekolah (tidak
  ada sekolah kedua di data Postman); keduanya dibuktikan PHPUnit.

## Belum dilakukan / di luar task

- Baris #66 di `docs/authorization-matrix.md` dan penggabungan ke API Contract v1.3: INTEG-DOC-001.
- Review silang Orang 1 dan pembaruan status Task Board: belum.
