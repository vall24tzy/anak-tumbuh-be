# DEPLOY-IMAGE-001 — Image kontainer dan konfigurasi Vercel untuk Laravel

**Tanggal:** 4 Nov 2026 · **PIC:** Orang 2 · **Sifat perubahan:** berkas image dan konfigurasi baru, kenaikan syarat PHP, dan satu config paket; **tidak ada** perubahan endpoint, field respons, aturan bisnis, skema DB, maupun kode di `app/`.
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2 (DEPLOY-IMAGE-001), D-26 (PHP 8.3, usulan di `SoT-Amendment-DEPLOY-D26-D33.md`), D-30, D-29 (batas import 4 MB).
**Catatan dependency:** D-26 (PHP 8.3 saja; `composer.json` `^8.3`, image di minor 8.3, suite lulus di 8.3.30) dan D-30 diputuskan pemilik (tabel keputusan pemilik yang diterima Orang 2 pada 7 Nov 2026); syarat D-26 terpenuhi: `composer.json` `^8.3`, image `php8.3`, suite 670 passed di PHP 8.3.30. Persetujuan tertulis di bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` diisi pemilik. Panduan lengkap (env, ekstensi, direktori tulis, gap) ada di `docs/ops/vercel-deploy.md`.

## 1. Perubahan

| Berkas | Isi |
|---|---|
| `composer.json` | `php` dari `^8.2` menjadi `^8.3` (D-26). **`composer.lock` tidak diubah** (butuh `composer update --lock`, lihat bagian 3) |
| `Dockerfile.vercel` | image FrankenPHP PHP 8.3; ekstensi `pdo_pgsql gd zip intl opcache`; `composer check-platform-reqs --no-dev` saat build; `package:discover` saat build; log ke stderr; tanpa `.env`, tanpa migrate/seed/config:cache |
| `Caddyfile` | root `public/`, `php_server`, TLS dihentikan di platform, port `:{$PORT:80}`, log akses ke stderr |
| `vercel.json` | satu service container dan rewrite ke service itu (dokumentasi resmi Vercel; belum diuji di Vercel) |
| `docker/php/zz-vercel.ini` | `upload_max_filesize=5M`, `post_max_size=6M`, `upload_tmp_dir=/tmp`, `sys_temp_dir=/tmp`, `display_errors=Off` |
| `.dockerignore` | mengecualikan `.env`, `.env.*` (kecuali `.env.example`), `.git`, `vendor`, `tests`, `docs` |
| `config/excel.php` | file sementara Excel lewat `EXCEL_TEMP_PATH` (hanya bagian `temporary_files`; default tanpa env = perilaku lama) |
| `docs/ops/vercel-deploy.md` | daftar env (nama saja), versi PHP dan ekstensi, langkah build, batasan platform, tabel direktori tulis, gap |
| `tests/Feature/Deploy/ImageFilesTest.php` | 10 test statis (bagian 4) |

`.env.example` **sengaja tidak diubah** di task ini (diubah oleh RUNTIME dan STORAGE) agar patch tidak bentrok.

## 2. Status per acceptance criteria

| Butir | Status |
|---|---|
| (1) Dockerfile dan konfigurasi di repo tanpa `.env`/rahasia (dibuktikan dengan memeriksa isi image) | berkas ada; image dibangun Orang 1 dan di dalamnya hanya `.env.example` (tanpa `.env`), dilaporkan Orang 1; pemeriksaan riwayat layer (`docker history`) tidak dilaporkan; `ImageFilesTest` (12) lulus |
| (2) Image berjalan lokal dan lolos smoke (endpoint infrastruktur, login Super Admin) | image dibangun dan smoke kontainer lokal lolos (dilaporkan Orang 1, 8 Nov 2026): `/up` 200, `/api/v1/auth/me` tanpa token 401 dengan envelope `unauthorized`, Caddy di `:80`. **Login Super Admin tidak diuji** (`APP_KEY` placeholder, tanpa database); dipindah ke VERIFY dan PILOT |
| (3) `docs/ops/vercel-deploy.md` (env, versi PHP, ekstensi, langkah build, batasan, direktori tulis) | ada; semua isi berstatus belum diverifikasi terhadap image dan Vercel |
| (4) `LOG_CHANNEL=stderr`; migrate sebagai langkah rilis terpisah | diset di Dockerfile; tidak ada migrate di image; langkah rilis ditulis di panduan bagian 6 |
| (5) hasil `composer check-platform-reqs` dilampirkan | lokal (Laragon, PHP 8.3.30): semua `success`; di dalam image: `composer check-platform-reqs --no-dev` semua `success` (dilaporkan Orang 1) |
| (6) ketidakcocokan versi PHP: suite dijalankan di versi image | suite 670 passed di PHP 8.3.30 (laptop Orang 2) dan 672 lulus di laptop Orang 1 (versi minor tidak tercatat); image memakai PHP 8.3.36; suite di dalam image tidak dijalankan |
| (7) PHP 8.3: `composer.json` `^8.3`, `composer.lock` konsisten, `check-platform-reqs` hijau, environment tim PHP 8.3 | `composer.json` `^8.3` dan `composer.lock` konsisten (`composer update --lock` dijalankan; `ImageFilesTest` lulus); `composer diagnose` di Cmder = PHP 8.3.30 Laragon; suite penuh lulus di 8.3.30 (670 passed). `composer check-platform-reqs --no-dev` hijau dan `composer validate` valid di lingkungan lokal; hijau di dalam image belum diuji |
| (7) koreksi berkas hasil run yang menulis PHP 8.2.12 | berkas Orang 2 (`INTEG-O2-runner-result.md`, `INTEG-TEST-002.md`) dikoreksi (bagian 5); berkas Orang 1 dikoreksi menjadi "PHP 8.3" atas konfirmasi yang disampaikan Orang 2 (versi minor belum dicatat) |
| (8) direktori tulis eksplisit (a sampai e) dan uji upload foto + import 200 baris di image | tabel ada di panduan bagian 5; pengaturannya ditulis; **uji di image belum dijalankan**; autosave Caddy menulis ke `/tmp/caddy` pada smoke lokal (dilaporkan Orang 1) |

## 3. Yang harus dilakukan setelah patch ini diterapkan

1. `composer update --lock` (di terminal PHP 8.3). Perintah ini hanya boleh mengubah `content-hash` dan `platform.php` di `composer.lock`; **jangan** menjalankan `composer update` tanpa `--lock` (akan menaikkan versi paket). Periksa `git diff composer.lock` sebelum commit.
2. Bila STORAGE-001 butir 1 belum dikerjakan: `composer require league/flysystem-aws-s3-v3 "^3.0"` (image dibangun dari `composer.lock`; tanpa paket itu disk S3 gagal saat runtime).
3. `composer check-platform-reqs --no-dev` dan `composer validate`.
4. `php artisan test --filter=ImageFilesTest`, lalu suite penuh.
5. Build dan periksa image (panduan bagian 3), lalu smoke lokal.

## 4. Test (`ImageFilesTest`, 10 test, statis)

`composer.json` `^8.3` dan `composer.lock` `platform.php` sama (**gagal bila lupa `composer update --lock`**, sengaja); Dockerfile memakai FrankenPHP PHP 8.3, memasang `pdo_pgsql gd zip intl`, dan menjalankan `check-platform-reqs --no-dev`; Dockerfile tanpa menyalin `.env`, tanpa `migrate`, `db:seed`, `config:cache`, dan dengan `LOG_CHANNEL=stderr`; Dockerfile tanpa nilai rahasia; `.dockerignore` mengecualikan `.env`, `.env.*`, `.git`, `vendor`, `tests`, `docs` dengan `!.env.example` setelah `.env.*`; Caddyfile (`root * /app/public`, `php_server`, `auto_https off`); `php.ini` image (`upload_max_filesize` ≥ 5M, `post_max_size` ≥ upload, `upload_tmp_dir=/tmp`); `config/excel.php` (default tidak berubah, `EXCEL_TEMP_PATH` bekerja, semua kunci `temporary_files` ada); bagian `exports` dan `imports` bawaan paket tetap ada; `vercel-deploy.md` menyebut setiap variabel `.env.example` dan `APP_TIMEZONE` UTC.

**Status eksekusi: belum dijalankan.** Test hanya membaca berkas; tidak ada yang membangun image.

## 5. Versi PHP pada hasil run lama (butir 7)

Task Board meminta berkas hasil run dan team-log yang menulis PHP 8.2.12 dikoreksi bila server ternyata PHP 8.3.

**Bukti (dikonfirmasi Orang 2):**
- `php artisan serve` dan `php artisan test` dijalankan di **Cmder**. Di Cmder, `php -v` = PHP 8.3.30 (Laragon, ZTS), `where php` mendaftar `C:\laragon\bin\php\php-8.3.30\php.exe` lebih dulu, dan `composer diagnose` = PHP 8.3.30 dengan path Laragon.
- Di **PowerShell**, `composer diagnose` = PHP **8.2.12** di `C:\xampp\php\php.exe`. Angka 8.2.12 pada catatan lama berasal dari terminal ini (`php -r` saat menulis catatan), bukan dari server yang melayani.
- Cocok dengan bukti statis: `vendor/composer/platform_check.php` mewajibkan PHP ≥ 8.3.0, sehingga aplikasi tidak dapat berjalan di 8.2. Cocok juga dengan `docs/ops/ops-001-bukti-lokal.md`, yang sudah mencatat selisih XAMPP 8.2.12 versus Laragon.

**Koreksi yang dilakukan (berkas Orang 2):** `docs/testing/INTEG-O2-runner-result.md` dan `docs/team-log/INTEG-TEST-002.md` menulis "PHP 8.3.30 (Laragon, Cmder)". Versi Laragon saat run tidak dicatat waktu itu; 8.3.30 adalah versi yang terpasang sekarang.

**Berkas Orang 1 (`INTEG-O1-runner-result.md`, `INTEG-TEST-001.md`):** dikoreksi menjadi "PHP 8.3" atas konfirmasi yang disampaikan Orang 2 (7 Nov 2026) bahwa PHP laptop Orang 1 juga 8.3; versi minor dan terminalnya belum dicatat. Minta Orang 1 mengonfirmasi dengan `php -v` dan `composer diagnose` di terminal yang menjalankan `php artisan serve`.

## 6. Temuan

1. Batas efektif unggah ditentukan platform (body 4,5 MB menurut Task Board), bukan `php.ini`; nilai 5M/6M hanya memastikan PHP tidak menolak file 2 sampai 4 MB lebih dulu (D-29).
2. `composer.lock` mencantumkan `platform.php` `^8.2`; setelah `composer.json` `^8.3` nilai itu usang sampai `composer update --lock`.
3. Image dibangun dari `composer.lock`: kelengkapan S3 adapter (STORAGE-001) dan lock yang konsisten adalah prasyarat build yang benar.
