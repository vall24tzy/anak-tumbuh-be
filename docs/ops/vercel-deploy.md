# Panduan image dan konfigurasi Vercel (DEPLOY-IMAGE-001)

**Status dokumen:** disusun 4 Nov 2026 oleh Orang 2 di sandbox tanpa Docker, PHP, composer, dan jaringan. **Tidak ada perintah di sini yang sudah dijalankan.** Semua keputusan dasar (D-26 sampai D-33) masih berstatus usulan. Fakta tentang Vercel dan Supabase diambil dari README Task Board Deployment v1.2 (dibaca saat board disusun) dan **harus diverifikasi ulang** terhadap dokumentasi vendor sebelum pilot.

## 1. Versi PHP dan ekstensi

- **PHP 8.3** (D-26 usulan). `composer.json` memuat `"php": "^8.3"`; `composer.lock` mengunci `intervention/image` 4.3.2, `intervention/gif` 5.0.1, dan `laravel/pint` yang mensyaratkan `^8.3`. `phpoffice/phpspreadsheet` 1.30.6 membatasi PHP `<8.5`.
- Setelah `composer.json` dinaikkan, `composer.lock` **harus** diperbarui lewat `composer update --lock` (hanya memperbarui `content-hash` dan `platform.php`). Hal ini tidak dikerjakan di sandbox (tidak boleh menulis `composer.lock` tanpa composer). `ImageFilesTest` gagal bila langkah ini terlewat.
- Base image: `dunglas/frankenphp:1-php8.3` (**tag belum diverifikasi** ke registry).

Ekstensi (turunan statis dari `composer.lock`, bukan keluaran `composer check-platform-reqs`):

| Ekstensi | Dibutuhkan oleh | Asal di image |
|---|---|---|
| ctype, filter, hash, json, openssl, pcre, session, tokenizer, mbstring, iconv, fileinfo, zlib | laravel/framework, jwt, flysystem, spreadsheet | bawaan image PHP |
| dom, libxml, simplexml, xml, xmlreader, xmlwriter | phpoffice/phpspreadsheet, css-to-inline-styles | bawaan image PHP |
| **gd** | phpoffice/phpspreadsheet; `intervention/image` (driver GD) | `install-php-extensions` |
| **zip** | phpoffice/phpspreadsheet (xlsx) | `install-php-extensions` |
| **pdo_pgsql** | koneksi PostgreSQL (tidak diwajibkan lock) | `install-php-extensions` |
| intl | opsional (tidak diwajibkan lock) | `install-php-extensions` |
| opcache | performa | `install-php-extensions` |

`Dockerfile.vercel` menjalankan `composer check-platform-reqs --no-dev` saat build sehingga build gagal bila ada ekstensi yang kurang. Keluaran perintah itu (butir 5 acceptance criteria) **belum ada**.

## 2. Berkas

| Berkas | Fungsi |
|---|---|
| `Dockerfile.vercel` | image FrankenPHP PHP 8.3: ekstensi, `composer install --no-dev`, autoload dioptimalkan, `package:discover` saat build, direktori tulis, `CMD` eksplisit |
| `Caddyfile` | root `public/`, `php_server`, TLS dihentikan di platform, port `{$PORT:8080}`, log akses ke stderr |
| `docker/php/zz-vercel.ini` | `upload_max_filesize=5M`, `post_max_size=6M`, `upload_tmp_dir=/tmp`, produksi (tanpa display_errors) |
| `.dockerignore` | mengecualikan `.env`, `.env.*` (kecuali `.env.example`), `.git`, `vendor`, `tests`, `docs` |
| `config/excel.php` | file sementara Excel diarahkan lewat `EXCEL_TEMP_PATH` |
| `vercel.json` | **BELUM dibuat** (lihat bagian 8, G1) |

## 3. Build dan pemeriksaan image (belum dijalankan)

```
docker build -f Dockerfile.vercel -t anaktumbuh-be:vercel .
docker run --rm anaktumbuh-be:vercel php -v
docker run --rm anaktumbuh-be:vercel php -m
docker run --rm anaktumbuh-be:vercel composer check-platform-reqs --no-dev
docker run --rm anaktumbuh-be:vercel php -i
docker run --rm anaktumbuh-be:vercel sh -c "ls -a /app | grep -E '^\.env'"
```

- `php -v` harus 8.3.x. `php -m` memuat `gd`, `zip`, `pdo_pgsql`.
- `php -i`: `upload_max_filesize` 5M, `post_max_size` 6M, `upload_tmp_dir` /tmp.
- Butir "tanpa rahasia di layer": perintah `ls` terakhir hanya boleh menampilkan `.env.example`. Periksa juga `docker history --no-trunc anaktumbuh-be:vercel` agar tidak ada nilai rahasia pada baris `ENV`.
- Smoke lokal: jalankan container dengan `--env-file` yang berada **di luar repo** (berkas env tidak boleh disalin ke image), lalu `GET /up` dan login Super Admin (`POST /api/v1/auth/login/staff`) terhadap database uji. Belum dijalankan.

## 4. Variabel environment (nama saja, tanpa nilai)

Kolom "Nilai yang dimaksud" memuat pilihan nilai, bukan nilai rahasia. Rahasia hanya diisi di environment Vercel.

| Variabel | Perlu di Vercel? | Nilai yang dimaksud |
|---|---|---|
| `APP_NAME` | opsional | nama aplikasi (dipakai prefiks cache dan log) |
| `APP_ENV` | ya | `production` (juga diset di Dockerfile) |
| `APP_DEBUG` | ya | `false` (juga diset di Dockerfile) |
| `APP_KEY` | ya | dibuat per lingkungan (rahasia) |
| `APP_URL` | ya | `https://...` (host dipakai redirect `EnforceHttps`) |
| `APP_TIMEZONE` | ya | **`UTC` dan tidak boleh diubah ke `Asia/Jakarta`**: Contract v1.3 bagian 7 menetapkan timestamp UTC; `habit_date` memakai `Asia/Jakarta` eksplisit di kode |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | opsional | bawaan Laravel |
| `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE` | tidak | bawaan Laravel (mode maintenance tidak dipakai) |
| `BCRYPT_ROUNDS` | opsional | biaya hash password |
| `JWT_SECRET` | ya | dibuat per lingkungan, berbeda dari dev (rahasia) |
| `CORS_ALLOWED_ORIGINS` | ya | origin frontend (daftar dipisah koma) |
| `FORCE_HTTPS` | ya | `true` |
| `TRUSTED_PROXIES` | **ya, wajib** | `*` untuk Vercel (tanpa IP tetap); tanpa ini `EnforceHttps` mengalihkan tanpa henti dan semua pengguna terbaca satu IP |
| `DB_CONNECTION` | ya | `pgsql` |
| `DB_URL` | ya | URL pooler Supabase mode transaksi (rahasia) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | alternatif `DB_URL` | dikalahkan `DB_URL` bila keduanya diisi |
| `DB_SSLMODE` | ya | `require` atau `verify-full` |
| `DB_SSLROOTCERT` | opsional | untuk `verify-full` |
| `DB_DISABLE_PREPARES` | ya (D-27) | `true` bila lewat pooler mode transaksi (`PDO::PGSQL_ATTR_DISABLE_PREPARES`); belum dibuktikan terhadap pooler nyata |
| `DB_EMULATE_PREPARES` | tidak | biarkan `false`: `true` merusak perbandingan boolean (suite gagal; `docs/addendum/DEPLOY-RUNTIME-001.md` bagian 3) |
| `DB_DIRECT_URL`, `DB_DIRECT_HOST`, `DB_DIRECT_PORT`, `DB_DIRECT_DATABASE`, `DB_DIRECT_USERNAME`, `DB_DIRECT_PASSWORD`, `DB_DIRECT_SSLMODE`, `DB_DIRECT_SSLROOTCERT` | **hanya langkah rilis** | koneksi langsung atau pooler mode session untuk migrate dan seed; tidak perlu di runtime aplikasi |
| `CACHE_STORE` | ya, wajib | `database` (blacklist JWT dan rate limit login) |
| `CACHE_PREFIX` | opsional | prefiks kunci cache |
| `STORAGE_PRIVATE_DRIVER`, `STORAGE_PUBLIC_DRIVER` | ya | `s3` di Vercel (disk lokal tidak awet) |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | ya | kunci S3 Supabase Storage (rahasia) |
| `AWS_DEFAULT_REGION`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | ya | region, endpoint S3 Supabase, `true` |
| `AWS_PRIVATE_BUCKET`, `AWS_PUBLIC_BUCKET`, `AWS_PUBLIC_URL` | ya | bucket privat (foto), bucket publik (logo), URL dasar objek publik |
| `AWS_BUCKET` | tidak | disk `s3` bawaan, tidak dipakai aplikasi |
| `FILESYSTEM_DISK` | tidak | bawaan; aplikasi memakai disk bernama `private` dan `public` |
| `LOG_CHANNEL` | ya | `stderr` (juga diset di Dockerfile) |
| `LOG_LEVEL` | opsional | mis. `warning` |
| `LOG_STACK`, `LOG_DAILY_DAYS`, `LOG_DEPRECATIONS_CHANNEL` | tidak | hanya relevan untuk channel stack/daily |
| `SEED_SUPER_ADMIN_PASSWORD` | **hanya langkah seed** | password awal Super Admin (rahasia; dibaca `config/app.php` dan `DatabaseSeeder`) |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN` | tidak | sesi tidak dipakai (API JWT stateless) |
| `QUEUE_CONNECTION`, `BROADCAST_CONNECTION` | tidak | antrean dan broadcast tidak dipakai; import sinkron, tanpa scheduler |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | tidak | tidak ada pemanggilan `Mail::` di `app/` |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`, `MEMCACHED_HOST` | tidak | tidak dipakai |
| `VITE_APP_NAME` | tidak | bawaan Laravel (tidak ada build frontend di image) |
| `VIEW_COMPILED_PATH` | image | `/tmp/views` (diset di Dockerfile) |
| `EXCEL_TEMP_PATH` | image | `/tmp/laravel-excel` (diset di Dockerfile) |
| `PORT` | bergantung platform | port dalam container; default Caddyfile 8080 (belum diverifikasi, G2) |

## 5. Direktori tulis (butir 8)

Hanya `/tmp` yang diandalkan saat runtime. **Perilaku sistem berkas Vercel belum diverifikasi**; periksa dokumentasi Vercel dan uji saat pilot.

| Butir | Direktori | Cara | Status |
|---|---|---|---|
| (a) view terkompilasi | `storage/framework/views` | `VIEW_COMPILED_PATH=/tmp/views` (diset di Dockerfile). API-only; hanya route `/` yang merender `welcome.blade.php` | belum diuji |
| (b) file sementara import/export Excel | `storage/framework/cache/laravel-excel` | `config/excel.php` membaca `EXCEL_TEMP_PATH=/tmp/laravel-excel` | belum diuji; import 200 baris di image belum dijalankan |
| (c) file sementara upload | `upload_tmp_dir` PHP | `upload_tmp_dir=/tmp` dan `sys_temp_dir=/tmp` di `docker/php/zz-vercel.ini` | belum diuji; foto dan logo kemudian diteruskan ke S3 (STORAGE-001) |
| (d) cache bootstrap | `bootstrap/cache` | `php artisan package:discover` dan `composer dump-autoload` saat **build**; tidak ada `config:cache` | belum diuji |
| (e) log | `storage/logs` | tidak dipakai; `LOG_CHANNEL=stderr` | belum diuji |
| tambahan | data/config Caddy | `XDG_CONFIG_HOME=/tmp`, `XDG_DATA_HOME=/tmp` | belum diuji |
| tambahan | disk lokal `private`/`public` | tidak dipakai di Vercel (`STORAGE_*_DRIVER=s3`) | n/a |

## 6. Langkah rilis (tidak saat container start)

1. Build image dari `composer.lock` yang sudah memuat `league/flysystem-aws-s3-v3` (DEPLOY-STORAGE-001 butir 1); tanpa paket itu disk S3 gagal saat runtime.
2. Isi environment Vercel (bagian 4).
3. **Langkah rilis terpisah** (bukan saat start): `php artisan migrate --database=pgsql_direct --force`, lalu `php artisan db:seed --database=pgsql_direct --force` (butuh `SEED_SUPER_ADMIN_PASSWORD`). Dijalankan dari lingkungan yang dapat menjangkau koneksi langsung (IPv6 atau pooler mode session).
4. Deploy dan smoke (bagian 3, 7).

## 7. Batasan platform (dari Task Board; verifikasi ulang)

Container Runtime Vercel berstatus Beta; body request/response 4,5 MB; durasi default 300 detik; tanpa Static IP dan Secure Compute. Konsekuensinya: batas import aplikasi 4 MB (D-29), `TRUSTED_PROXIES=*`, dan akses database tidak bisa dibatasi per IP (bergantung kredensial dan SSL).

## 8. Gap dan hal yang belum dikerjakan

| ID | Gap |
|---|---|
| G1 | `vercel.json` belum dibuat: skema konfigurasi Container Runtime (Beta) tidak diketahui dan tidak dapat diperiksa tanpa jaringan; tidak diisi dengan tebakan |
| G2 | Port yang diharapkan Vercel untuk container belum diketahui; Caddyfile memakai `{$PORT:8080}` |
| G3 | Tag base image `dunglas/frankenphp:1-php8.3` belum diverifikasi ke registry; kunci digest setelah verifikasi |
| G4 | Container berjalan sebagai root (belum ada `USER` non-root); penguatan ini belum diuji dengan FrankenPHP |
| G5 | `composer.lock` perlu `composer update --lock` setelah `composer.json` dinaikkan, dan `composer require league/flysystem-aws-s3-v3` (STORAGE-001) belum dijalankan |
| G6 | Tidak ada `HEALTHCHECK` di Dockerfile (route `/up` tersedia dan dikecualikan dari `EnforceHttps`) |
| G7 | Image belum pernah dibangun atau dijalankan; seluruh butir acceptance criteria yang butuh image masih terbuka |
| G8 | Catatan "PHP 8.2.12" pada hasil run lama berasal dari PowerShell (XAMPP); server di Cmder memakai Laragon 8.3.30 (dikonfirmasi Orang 2; `docs/addendum/DEPLOY-IMAGE-001.md` bagian 5). Berkas Orang 1 belum dikonfirmasi. |
