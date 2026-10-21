# INTEG-TEST-002 — Log

**Status:** verifikasi kelengkapan (statis) selesai, suite PHPUnit penuh lulus, dan hasil run newman folder `INTEG-O2`
tersimpan di `docs/testing/INTEG-O2-runner-result.md` (D-12 terpenuhi, tanpa pengecualian); semuanya dilaporkan Orang 2.
Review silang Orang 1 masih menunggu; status di Task Board dinaikkan oleh pemilik.
**PIC:** Orang 2 · **Reviewer:** Orang 1 · **Dasar:** AC INTEG-TEST-002 (Task Board INTEG v1.5), D-12; merujuk Matrix #65-#68

## Ringkasan

- Kelengkapan request Postman diperiksa dengan skrip terhadap file koleksi: folder `INTEG-O2` memuat 42 request
  (INTEG-ME-001: 6, INTEG-DASHHM-001: 4, INTEG-SCHOOL-001: 15, INTEG-TEACHER-001: 17), semuanya punya `pm.test`,
  variabel terdeklarasi, dan jumlah `pm.test` statis (85) sama dengan jumlah assertion pada run yang dilaporkan.
- Matriks AC per endpoint (happy, 401, 403, 404 lintas sekolah, 422, audit_logs, dua assignment aktif) disusun di
  `docs/testing/INTEG-O2-traceability.md`. Satu celah ditemukan: tidak ada test untuk `name` yang hanya berisi spasi
  pada PATCH sekolah dan PATCH guru. Ditutup dengan dua test baru, tanpa mengubah kode.
- Tidak ada perubahan kode aplikasi, migration, atau request Postman.
- D-12: Export Results Postman berbayar di akun Orang 2, sehingga sempat dipilih pengecualian (hasil dicatat sebagai
  angka; disetujui pemilik 21 Okt 2026, disampaikan Orang 2). Pengecualian itu **tidak dipakai**: hasil disimpan lewat
  newman (gratis), seperti `docs/testing/INTEG-O1-runner-result.md` milik Orang 1.

## File

- `docs/testing/INTEG-O2-traceability.md` (baru)
- `docs/testing/INTEG-O2-runner-result.md` (baru; hasil run newman folder `INTEG-O2`, dibuat dari JSON newman dengan skrip
  bantu lokal yang tidak disimpan di repo; hanya memuat nama request, kode HTTP, dan hasil assertion)
- Test: `tests/Feature/School/SchoolUpdateTest.php` (+1 test), `tests/Feature/School/TeacherUpdateTest.php` (+1 test)
- `docs/team-log/INTEG-TEST-002.md`

## Test

- Suite penuh setelah dua test baru, dijalankan Orang 2 setelah pull lagi perubahan Orang 1: `php artisan test`
  **622 passed (3485 assertions)**, 100,11 s. Sebelumnya 598 / 3323 (setelah INTEG-TEACHER-001). Selisih +24 = 2 test
  baru task ini + 22 test dari perubahan Orang 1 yang di-pull (dugaan; belum diverifikasi per test). Perkiraan awal
  600 tidak memperhitungkan pull itu.
- Run newman 6.2.3 (21 Okt 2026, dijalankan Orang 2 di laptop sendiri: PHP 8.2.12, PostgreSQL 17.11, database baru
  `migrate:fresh --seed`, `CACHE_STORE=database`, `--delay-request 13000`; satu sesi folder 0 -> folder 1 -> folder
  `INTEG-O2`). Ringkasan newman (`run.stats`): **76 request, 0 request gagal; 137 assertion, 1 gagal**.
  Folder `INTEG-O2` sendiri: **42 request, 85 assertion, 0 gagal** (rincian di `docs/testing/INTEG-O2-runner-result.md`),
  sama dengan run Runner Postman sebelumnya (85 passed, `docs/team-log/INTEG-TEACHER-001.md`).
- Satu assertion yang gagal ada di Folder 0, bukan di `INTEG-O2`: request "Login Super Admin ..." memeriksa bahwa
  `sa_password` sudah diisi manual di Variables koleksi. Pada run newman password diberikan lewat `--env-var`, sehingga
  variabel koleksi tetap kosong dan pemeriksaan itu gagal, padahal login-nya sendiri 200. Request dan assertion
  folder 0 dan 1 tidak diubah oleh task ini. Folder 0 dan 1 menyumbang 52 assertion (137 - 85), 51 lulus.
- File JSON mentah newman tidak disimpan di repo karena memuat token dan password hasil login.
- `MailExposureGuardTest` dan `FrameworkAdvisoryGuardTest` berada di dalam suite penuh yang lulus.

## Belum dilakukan / di luar task

- Review silang Orang 1 dan pembaruan status Task Board: belum.
- INTEG-DOC-001 (penggabungan Contract v1.3, baris Matrix #65-#68) bukan bagian task ini.
