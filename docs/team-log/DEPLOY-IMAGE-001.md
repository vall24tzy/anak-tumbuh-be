# DEPLOY-IMAGE-001 — Log

**Status:** berkas image, konfigurasi, panduan, test, dan addendum selesai; `ImageFilesTest` (10) dan suite penuh lulus di PHP 8.3.30 (670 passed, dilaporkan Orang 2). **Image belum dibangun atau dijalankan** (kebutuhan Docker), `vercel.json` belum dibuat, dan task **belum bisa DONE** (lihat "Belum selesai").
**PIC:** Orang 2 · **Tanggal:** 4 Nov 2026
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2; D-26 dan D-30 (usulan); `docs/addendum/DEPLOY-IMAGE-001.md`; `docs/ops/vercel-deploy.md`.
**Catatan jujur:** persetujuan pemilik atas D-26 dan D-30 belum tercatat; task dikerjakan atas arahan Orang 1.

## Yang dikerjakan
1. `composer.json`: PHP `^8.3`.
2. `Dockerfile.vercel`, `Caddyfile`, `docker/php/zz-vercel.ini`, `.dockerignore`.
3. `config/excel.php`: file sementara Excel lewat `EXCEL_TEMP_PATH`.
4. `docs/ops/vercel-deploy.md` (env, ekstensi, build, batasan, direktori tulis, gap) dan `docs/addendum/DEPLOY-IMAGE-001.md`.
5. `tests/Feature/Deploy/ImageFilesTest.php` (10 test statis).
6. Tidak ada perubahan di `app/`, `.env.example`, endpoint, field, aturan bisnis, atau skema DB.

## Yang diperiksa dan yang tidak
- Diperiksa dengan membaca: `vendor/composer/platform_check.php` (mewajibkan PHP >= 8.3.0, sehingga catatan "PHP 8.2.12" pada hasil run lama kemungkinan dari terminal lain; inferensi), `composer.lock` (daftar `ext-*` per paket, batas PHP, `platform`, `content-hash`), config `maatwebsite/excel` di vendor (bagian `temporary_files`), `config/logging.php` (channel `stderr` ada), `config/filesystems.php` hasil STORAGE-001, pemakaian queue/session/mail/redis di `app/`, dan route `/` (satu-satunya pemakai view).
- **Tidak dijalankan:** sandbox tanpa Docker, PHP, composer, dan jaringan. Image tidak dibangun; `composer check-platform-reqs`, `php -v`, smoke, dan test tidak dijalankan; sintaks PHP hanya diperiksa dengan menghitung kurung.
- **Tidak dikerjakan (gap):** `vercel.json` (skema Container Runtime tidak diketahui, tidak ditebak); verifikasi tag base image; pengujian direktori tulis di image; upload foto dan import 200 baris di image; koreksi berkas hasil run yang menulis PHP 8.2.12 (butuh keterangan pemilik laptop).
- **Tidak dikerjakan:** pembaruan `composer.lock` (tidak boleh ditulis tanpa composer).

## Hasil yang dilaporkan Orang 2
- `composer update --lock` dijalankan; `composer.lock` berubah 4 baris: `content-hash`, `platform.php` `^8.2` menjadi `^8.3`, dan URL repositori `vlucas/phpdotenv` menjadi `phpdotenv/phpdotenv` (referensi commit sama `301c079...`; pembaruan metadata dari Composer, **bukan** perubahan versi paket). `ImageFilesTest` (termasuk konsistensi `composer.lock`) lulus.
- `composer diagnose` di Cmder: PHP 8.3.30, path Laragon. Suite penuh 670 passed (3842 assertions).
- `composer audit` **gagal** (curl timeout 10 detik ke packagist.org); belum ada hasil audit.
- Versi PHP pada hasil run lama: koreksi berkas Orang 2 dilakukan; berkas Orang 1 menunggu konfirmasi (addendum bagian 5).

## Belum selesai (syarat DONE)
1. `composer check-platform-reqs --no-dev`, `composer validate`: keluaran belum dikirim.
2. Build image, periksa isinya (tanpa `.env`), smoke lokal, uji upload foto dan import 200 baris di image (butuh Docker).
3. `vercel.json` setelah membaca dokumentasi Container Runtime Vercel.
4. Konfirmasi versi PHP laptop Orang 1 untuk `INTEG-O1-runner-result.md` dan `INTEG-TEST-001.md`.
5. Review silang Orang 1.

## Temuan
1. `composer.lock` `platform.php` masih `^8.2` (usang setelah `composer.json` dinaikkan).
2. Base image tag dan lokasi Caddyfile bawaan FrankenPHP belum diverifikasi; karena itu `CMD` menyebut berkas konfigurasi secara eksplisit.
3. `intl` tidak diwajibkan oleh `composer.lock`; dipasang karena Task Board menyebutnya (opsional).
4. **Repo GitHub berstatus publik** (dijawab Orang 2). Pemindaian berkas yang dilacak dan berkas baru tidak menemukan rahasia (hanya placeholder `<project-ref>` di `.env.example` dan nilai palsu di test), dan `.env`, `.env.testing`, `.env.production` tidak pernah masuk riwayat. Namun commit pertama (`38df220`) memuat `DB_PASSWORD=` berisi nilai password PostgreSQL lokal di `.env.example`, dihapus di `5918c61`; nilainya tetap terbaca di riwayat publik. Jangan dipakai di Supabase atau tempat lain, dan ganti password lokal bila pernah dipakai di luar mesin sendiri. Pemindaian hanya mencakup berkas dan pola yang disebut; bukan audit riwayat penuh.
