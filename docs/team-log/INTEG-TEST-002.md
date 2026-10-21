# INTEG-TEST-002 — Log

**Status:** verifikasi kelengkapan (statis) selesai dan suite PHPUnit penuh lulus (dilaporkan Orang 2). File hasil Runner
folder `INTEG-O2` **tidak disimpan di repo**: Export Results Postman berbayar di akun Orang 2, dan Orang 2 memilih
(21 Okt 2026) membebaskan syarat file D-12 untuk run ini dengan mencatat hasil sebagai angka di team-log. Pemilik
menyetujui pengecualian itu (21 Okt 2026, disampaikan Orang 2). Review silang Orang 1 masih menunggu; status di Task
Board dinaikkan oleh pemilik.
**PIC:** Orang 2 · **Reviewer:** Orang 1 · **Dasar:** AC INTEG-TEST-002 (Task Board INTEG v1.5), D-12; merujuk Matrix #65-#68

## Ringkasan

- Kelengkapan request Postman diperiksa dengan skrip terhadap file koleksi: folder `INTEG-O2` memuat 42 request
  (INTEG-ME-001: 6, INTEG-DASHHM-001: 4, INTEG-SCHOOL-001: 15, INTEG-TEACHER-001: 17), semuanya punya `pm.test`,
  variabel terdeklarasi, dan jumlah `pm.test` statis (85) sama dengan jumlah assertion pada run yang dilaporkan.
- Matriks AC per endpoint (happy, 401, 403, 404 lintas sekolah, 422, audit_logs, dua assignment aktif) disusun di
  `docs/testing/INTEG-O2-traceability.md`. Satu celah ditemukan: tidak ada test untuk `name` yang hanya berisi spasi
  pada PATCH sekolah dan PATCH guru. Ditutup dengan dua test baru, tanpa mengubah kode.
- Tidak ada perubahan kode aplikasi, migration, atau request Postman.

## File

- `docs/testing/INTEG-O2-traceability.md` (baru)
- Test: `tests/Feature/School/SchoolUpdateTest.php` (+1 test), `tests/Feature/School/TeacherUpdateTest.php` (+1 test)
- `docs/team-log/INTEG-TEST-002.md`

## Test

- Suite penuh setelah dua test baru, dijalankan Orang 2 setelah pull lagi perubahan Orang 1: `php artisan test`
  **622 passed (3485 assertions)**, 100,11 s. Sebelumnya 598 / 3323 (setelah INTEG-TEACHER-001). Selisih +24 = 2 test
  baru task ini + 22 test dari perubahan Orang 1 yang di-pull (dugaan; belum diverifikasi per test). Perkiraan awal
  600 tidak memperhitungkan pull itu.
- Postman folder `INTEG-O2` (Runner, environment none): **85 passed, 0 failed, 0 errors**, 30,2 s, dilaporkan Orang 2
  dari layar Runner (lihat `docs/team-log/INTEG-TEACHER-001.md`). Folder belum dijalankan ulang setelah task ini
  karena tidak ada request yang berubah.
- `MailExposureGuardTest` dan `FrameworkAdvisoryGuardTest` berada di dalam suite penuh yang lulus.

## Belum dilakukan / di luar task

- File hasil Runner folder `INTEG-O2` tidak disimpan (D-12), karena Export Results Postman berbayar. Pengecualian:
  hasil dicatat sebagai angka di team-log (85 passed, 0 failed; lihat bagian Test), mengikuti pola TEST-001 v23 (hasil
  Runner tidak wajib tersimpan). Pengecualian terhadap D-12 (FINAL) ini disetujui pemilik 21 Okt 2026 (disampaikan
  Orang 2); persetujuan tertulis pemilik belum ada di repo.
- Review silang Orang 1 dan pembaruan status Task Board: belum.
- INTEG-DOC-001 (penggabungan Contract v1.3, baris Matrix #65-#68) bukan bagian task ini.
