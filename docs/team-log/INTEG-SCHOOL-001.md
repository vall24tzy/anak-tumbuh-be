# INTEG-SCHOOL-001 — Log

**Status:** kode, PHPUnit, dan Postman folder `INTEG-O2` selesai dan lulus (dilaporkan Orang 2, 20 Okt 2026).
Menunggu review silang Orang 1; status task di Task Board dinaikkan ke DONE oleh pemilik setelah itu.
**PIC:** Orang 2 · **Reviewer:** Orang 1 · **Dasar:** D-09, D-02, D-03, D-04, D-14, D-15, D-16; Matrix v5.3 #65

## Ringkasan

- `PATCH /api/v1/schools/{id}`: ubah nama sekolah, Super Admin saja, field `name` saja. Role lain 403, tanpa token
  401, id tidak ada 404, `name` tidak valid atau nama kembar 422, field lain 422 `protected_field`.
- Audit lewat `AuditLogService`: action `school_name_update`, `target_user_id` null, hanya bila nama berubah
  (D-14); nama sama = 200 tanpa baris audit; beda huruf besar/kecil = berubah.
- Tidak ada rate limit baru (D-04), migration, atau perubahan field/endpoint lama.
- Gap audit (tidak ada id sekolah / nilai lama-baru) diterima tanpa perubahan skema (D-16); dicatat di addendum.
- Addendum: `docs/addendum/API-Contract-v1.2-Addendum-INTEG-SCHOOL-001.md` (revisi Contract §11, D-14/D-15/D-16).

## File

- `routes/v1/school.php`, `app/Http/Controllers/V1/School/SchoolController.php`,
  `app/Services/School/SchoolService.php`, `app/Http/Requests/School/UpdateSchoolRequest.php` (baru)
- Test: `tests/Feature/School/SchoolUpdateTest.php` (baru); `SchoolNameIntegrityTest` hanya komentar
- `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json`: 15 request baru di folder `INTEG-O2` (membuat sekolah
  uji dengan nama unik per run, sehingga data bersama tidak diubah); variabel `integ_school_id`,
  `integ_school_name`, `integ_school_case`, `integ_school_long`; request lama tidak diubah
- `docs/addendum/API-Contract-v1.2-Addendum-INTEG-SCHOOL-001.md`, `docs/team-log/INTEG-SCHOOL-001.md`

## Test

- Baseline HEAD sebelum task (setelah INTEG-ME-001, INTEG-QR-001, INTEG-DASHHM-001): `php artisan test`
  **550 passed (2977 assertions)**, dilaporkan Orang 2. Menggantikan 518 / 2814 di Task Board.
- Setelah patch kode pertama (12 test di `SchoolUpdateTest`): **562 passed (3080 assertions)**, 99,17 s,
  dilaporkan Orang 2. Action saat itu masih `school_name_updated`.
- Setelah patch keputusan D-14 sampai D-16 (action menjadi `school_name_update`, test nama sama setelah trim, test
  beda huruf besar/kecil, assertion nol baris audit memakai `AuditLog::count()`; `SchoolUpdateTest` = 14 test):
  `php artisan test` **564 passed (3089 assertions)**, 73,38 s, dilaporkan Orang 2. Selisih +2 dari 562 =
  2 test baru. Test "sama setelah trim" lulus, jadi middleware `TrimStrings` aktif untuk request JSON.
- Postman folder `INTEG-O2` (Collection Runner, 20 Okt 2026 16:22, environment none, 1 iterasi): **52 passed,
  0 failed, 0 errors**, durasi 18,5 s. 52 = 23 assertion INTEG-ME-001 + INTEG-DASHHM-001 yang sudah ada + 29 assertion
  dari 15 request baru INTEG-SCHOOL-001 (koleksi 144 -> 159). Dilaporkan Orang 2 dari layar Runner; file hasil Runner
  tidak disimpan di repo (D-12: disimpan oleh INTEG-TEST-002).

## Belum dilakukan / di luar task

- Postman tidak dapat memeriksa baris `audit_logs` (tidak ada endpoint baca); bukti audit hanya dari PHPUnit.
- Baris #65 di `docs/authorization-matrix.md` dan penggabungan ke API Contract v1.3: INTEG-DOC-001.
- Review silang Orang 1 dan pembaruan status Task Board: belum.
