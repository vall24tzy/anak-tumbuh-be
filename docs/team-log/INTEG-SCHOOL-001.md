# INTEG-SCHOOL-001 — Log

**Status:** kode dan PHPUnit ditulis; hasil suite terakhir yang dilaporkan (562 passed, 3080 assertions) adalah
sebelum keputusan D-14 sampai D-16. Perubahan setelahnya (rename action, 2 test baru, assertion audit diperketat)
**belum dijalankan**. Request Postman folder `INTEG-O2` untuk task ini **belum ditulis dan belum dijalankan**.
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
- `docs/addendum/API-Contract-v1.2-Addendum-INTEG-SCHOOL-001.md`, `docs/team-log/INTEG-SCHOOL-001.md`

## Test

- Baseline HEAD sebelum task (setelah INTEG-ME-001, INTEG-QR-001, INTEG-DASHHM-001): `php artisan test`
  **550 passed (2977 assertions)**, dilaporkan Orang 2. Menggantikan 518 / 2814 di Task Board.
- Setelah patch kode pertama (12 test di `SchoolUpdateTest`): **562 passed (3080 assertions)**, 99,17 s,
  dilaporkan Orang 2. Action saat itu masih `school_name_updated`.
- Patch kedua (action menjadi `school_name_update`, test nama sama setelah trim, test beda huruf besar/kecil,
  assertion nol baris audit memakai `AuditLog::count()`): hasil BELUM ada. Angka final dicatat setelah dijalankan.
- Test "sama setelah trim" bergantung pada middleware `TrimStrings` aktif untuk request JSON; bila gagal,
  laporkan dulu sebelum mengubah service.
- Postman: belum.

## Belum dilakukan / di luar task

- Request Postman di folder `INTEG-O2`, dan menjalankannya.
- Baris #65 di `docs/authorization-matrix.md` dan penggabungan ke API Contract v1.3: INTEG-DOC-001.
- Review silang Orang 1 dan pembaruan status Task Board: belum.
