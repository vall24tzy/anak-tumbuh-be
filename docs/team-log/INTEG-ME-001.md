# INTEG-ME-001 — Log

**Status:** kode, PHPUnit, dan Postman folder `INTEG-O2` selesai dan lulus (dilaporkan Orang 2, 20 Okt 2026).
Menunggu review silang Orang 1; status task di Task Board dinaikkan ke DONE oleh pemilik setelah itu.
**PIC:** Orang 2 · **Reviewer:** Orang 1 (rombel aktif = domain Student Class Assignment; foto = F-06)
**Tanggal:** 20 Okt 2026 · **Dasar:** D-08, Matrix v5.3 #67, Task Board INTEG v1.5

## Ringkasan

- `/auth/me` (Student) dan `/dashboard/student` memuat `rombel` {id,name}|null dan `photo` (URL terproteksi|null).
  Field lama tidak berubah; field baru di akhir. Role lain tidak berubah.
- `rombel` memakai resolver yang sudah ada (`activeAssignmentInCurrentAcademicYear`), bukan query baru;
  deterministik untuk Student dengan assignment aktif di dua tahun ajaran (DB Design §5.9).
- `photo` memakai route `students.photo` yang sama dengan `StudentResource`; segmen id = `students.id`.
- Tidak ada migration, tidak ada route baru, tidak ada perubahan `StudentResource`.
- Addendum: `docs/addendum/API-Contract-v1.2-Addendum-INTEG-ME-001.md` (termasuk catatan FE: foto harus
  di-fetch dengan header Authorization).

## File

- `app/Http/Resources/User/MeResource.php`, `app/Services/Dashboard/DashboardService.php`
- Test: `tests/Feature/Auth/MeStudentRombelPhotoTest.php` (baru), `DashboardStudentTest`, `StudentPhotoPrivacyTest` (disesuaikan sengaja)
- `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json`: folder baru `INTEG-O2 (Orang 2): ...` (6 request,
  koleksi 127 -> 133), variabel `integ_me_rombel` dan `integ_me_photo`; request lama tidak diubah
- `docs/addendum/API-Contract-v1.2-Addendum-INTEG-ME-001.md`, `docs/team-log/INTEG-ME-001.md`

## Test

- `php artisan test` (seluruh suite) setelah patch kode: **532 passed (2887 assertions)**, 76.04 s, dilaporkan
  Orang 2. Baseline sebelumnya 518 / 2814; selisih +14 test = test baru `MeStudentRombelPhotoTest`.
- Postman folder `INTEG-O2` (Collection Runner, 20 Okt 2026 11:23, environment none, 1 iterasi, 6 request):
  **15 passed, 0 failed, 0 errors**, durasi 6,5 s. Dilaporkan Orang 2 dari layar Runner; file hasil Runner
  tidak disimpan di repo (D-12: disimpan oleh INTEG-TEST-002). Urutan jalan: `migrate:fresh --seed`, Folder 0,
  Folder 1, lalu INTEG-O2, dengan `php artisan cache:clear` di antaranya; `sa_password` diisi di variabel koleksi.
  Siswa uji (Student A) tidak punya foto, jadi `photo` = null; kasus foto berisi dicakup PHPUnit.
- Kasus siswa dengan dua assignment aktif lintas tahun ajaran hanya dicakup PHPUnit (tidak praktis di Postman).

## Belum dilakukan / di luar task

- Baris #67 di `docs/authorization-matrix.md` dan penggabungan ke API Contract v1.3: INTEG-DOC-001.
- Review silang Orang 1 dan pembaruan status Task Board: belum.
