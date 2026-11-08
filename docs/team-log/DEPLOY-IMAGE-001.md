# DEPLOY-IMAGE-001 — Log

**Status:** berkas image, konfigurasi, panduan, test, dan addendum selesai; `ImageFilesTest` (10) dan suite penuh lulus di PHP 8.3.30 (670 passed, dilaporkan Orang 2). **Image belum dibangun atau dijalankan** (kebutuhan Docker), `vercel.json` dan port mengikuti dokumentasi resmi Vercel tetapi **belum diuji di Vercel**, dan task **belum bisa DONE** (lihat "Belum selesai").
**PIC:** Orang 2 · **Tanggal:** 4 Nov 2026
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2; D-26 dan D-30 (diputuskan pemilik, tabel keputusan pemilik yang diterima Orang 2 pada 7 Nov 2026); `docs/addendum/DEPLOY-IMAGE-001.md`; `docs/ops/vercel-deploy.md`.
**Catatan jujur:** tabel keputusan pemilik belum ada di repo; bagian 10 dokumen keputusan diisi pemilik.

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
- **Tidak dikerjakan (gap):** verifikasi tag base image; pengujian direktori tulis di image; upload foto dan import 200 baris di image; koreksi berkas hasil run yang menulis PHP 8.2.12 (butuh keterangan pemilik laptop).
- **Tidak dikerjakan:** pembaruan `composer.lock` (tidak boleh ditulis tanpa composer).

## Hasil yang dilaporkan Orang 2
- `composer update --lock` dijalankan; `composer.lock` berubah 4 baris: `content-hash`, `platform.php` `^8.2` menjadi `^8.3`, dan URL repositori `vlucas/phpdotenv` menjadi `phpdotenv/phpdotenv` (referensi commit sama `301c079...`; pembaruan metadata dari Composer, **bukan** perubahan versi paket). `ImageFilesTest` (termasuk konsistensi `composer.lock`) lulus.
- `composer diagnose` di Cmder: PHP 8.3.30, path Laragon. Suite penuh 670 passed (3842 assertions).
- `composer check-platform-reqs --no-dev` (lingkungan lokal Laragon, bukan image): semua `success` pada PHP 8.3.30 (`php`, `php-64bit`, `ext-dom`, `ext-fileinfo`, `ext-filter`, `ext-gd`, `ext-hash`, `ext-iconv`, `ext-json`, `ext-libxml`, `ext-openssl`, `ext-pcre`, `ext-session`, `ext-simplexml`, `ext-tokenizer`, `ext-xml`, `ext-xmlreader`, `ext-xmlwriter`, `ext-zip`, `ext-zlib`; `ext-ctype` dan `ext-mbstring` lewat polyfill). `composer validate`: valid. Pemeriksaan yang sama di dalam image terjadi saat build (belum dijalankan).
- `composer audit`: **20 advisory pada 8 paket** (`laravel/framework` 5, `league/commonmark` 2, `phpoffice/phpspreadsheet` 3, `symfony/http-foundation` 5, `symfony/http-kernel` 1, `symfony/mime` 1, `symfony/routing` 1, `symfony/yaml` 2). Penilaian keterjangkauan dan keputusan ada pada Orang 1 dan pemilik (task keamanan terpisah, di luar scope IMAGE); sengaja tidak diuraikan di repo publik ini.
- Versi PHP pada hasil run lama: koreksi berkas Orang 2 dilakukan; berkas Orang 1 menunggu konfirmasi (addendum bagian 5).

## Belum selesai (syarat DONE)
1. Build image, periksa isinya (tanpa `.env`), smoke lokal, uji upload foto dan import 200 baris di image (butuh Docker).
2. Review silang Orang 1.

## Temuan
1. `composer.lock` `platform.php` masih `^8.2` (usang setelah `composer.json` dinaikkan).
2. Base image tag dan lokasi Caddyfile bawaan FrankenPHP belum diverifikasi; karena itu `CMD` menyebut berkas konfigurasi secara eksplisit.
3. `intl` tidak diwajibkan oleh `composer.lock`; dipasang karena Task Board menyebutnya (opsional).
4. **Repo GitHub berstatus publik; D-35 memutuskan dijadikan privat** (disampaikan Orang 2). Pemindaian seluruh riwayat (164 commit) dengan pola kunci AWS, JWT, kunci privat, URL database berpassword, dan nilai `JWT_SECRET`/`APP_KEY`/`AWS_SECRET_ACCESS_KEY`/`SEED_SUPER_ADMIN_PASSWORD`/`DB_PASSWORD` tidak menemukan rahasia, kecuali satu: commit pertama (`38df220`) memuat `DB_PASSWORD=` berisi password PostgreSQL lokal di `.env.example` (dihapus di `5918c61`). `.env`, `.env.testing`, `.env.production` tidak pernah masuk riwayat. Cocokan `SEED_SUPER_ADMIN_PASSWORD=` di `tests/Feature/Auth/SuperAdminSeedTest.php` adalah pola pemeriksaan regex, bukan nilai. Pemindaian terbatas pada pola itu; bukan jaminan riwayat bersih. Tindak lanjut D-35 (pemilik/Orang 2): ganti password lokal, jadikan repo privat, tambahkan collaborator, izinkan Vercel membaca repo privat; setelah privat, riwayat tetap terbawa ke siapa pun yang pernah meng-clone atau mem-fork saat publik.

## Revisi dari review silang Orang 1 (pembacaan statis; dikerjakan Orang 2)

**Temuan Orang 1:** tidak ada yang memblokir; tiga perbaikan wajib (port, `vercel.json` dan komentar status, risiko root) dan dua opsional.

**Perubahan:**
1. **Port:** `Caddyfile` memakai `:{$PORT:80}` (sebelumnya 8080) dengan komentar yang merujuk dokumentasi Vercel; `Dockerfile.vercel` memakai `EXPOSE 80` dan `PORT=80` pada blok `ENV`; sisa `8080` di Dockerfile, Caddyfile, `docs/ops/vercel-deploy.md` diperbarui. `G2` (port) ditutup. Dua test baru di `ImageFilesTest` mengunci `:{$PORT:80}`, `PORT=80` pada instruksi `ENV`, `EXPOSE 80`, dan tidak adanya `8080`.
2. **`vercel.json`:** dibuat persis seperti ringkasan Orang 1 (satu service container, `entrypoint` `Dockerfile.vercel`, rewrite `/(.*)` ke service `app`). Test statis baru: JSON valid, `runtime` `container`, `entrypoint` `Dockerfile.vercel` dan berkasnya ada, rewrite ke `app`, tanpa pola rahasia, tidak dikecualikan `.dockerignore`. `G1` ditutup. Sumber: https://vercel.com/kb/guide/laravel-php-with-docker dan https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker; **isinya belum diuji di Vercel**.
3. **Komentar `STATUS EKSEKUSI`** diperbarui pada `TrustedProxiesDisabledTest`, `TrustedProxiesEnabledTest`, `DatabaseConnectionConfigTest`, dan `ImageFilesTest` (dua test baru ImageFilesTest ditulis belum dijalankan), serta pada `StorageDiskConfigTest` dan `ImportFileSizeLimitTest` (milik Orang 1; hanya baris itu).
4. **Risiko root:** dicatat sebagai butir `G4` di `docs/ops/vercel-deploy.md` (panduan resmi memakai non-root dengan `setcap` dan `chown`; root dipertahankan, ditinjau setelah PILOT; Dockerfile tidak diubah).
5. **Opsional (4):** komentar di `config/database.php` bahwa nama `pgsql_direct` tidak berarti koneksi langsung (isinya pooler mode session, D-27). Tidak mengubah perilaku.
6. **Opsional (5):** teks tabel keputusan D-26 sampai D-35 dari pemilik dikirim apa adanya kepada Orang 1 lewat pesan terpisah (tidak ditulis ke repo; bagian 10 dokumen keputusan tidak diisi atas nama pemilik).

**Keputusan konservatif yang diambil:**
- Halaman dokumentasi Vercel **tidak dapat dibuka** dari sandbox (tanpa jaringan), jadi tidak dicocokkan; ringkasan Orang 1 dipakai apa adanya. Bila halaman resmi berbeda, ikuti halaman resmi dan laporkan.
- `vercel.json` dibiarkan tidak dikecualikan oleh `.dockerignore` (tidak perlu masuk image, tetapi tidak berisi rahasia sehingga aman jika ikut tersalin); `.dockerignore` tidak diubah.
- Untuk `StorageDiskConfigTest` dan `ImportFileSizeLimitTest`: ringkasan `Tests:` pada suite penuh (670 passed) tidak menyebut dilewati, tetapi jumlah dilewati per test tidak tercatat; status ditulis "lulus dalam suite penuh 670; jumlah dilewati tidak tercatat".

**Belum dijalankan:** dua test baru `ImageFilesTest` (port dan `vercel.json`) dan seluruh perubahan pada revisi ini; patch dihitung dari rekonstruksi branch dan **belum diuji `git apply --check` terhadap commit `1a184e4`**; image belum dibangun (Orang 1 akan membangunnya setelah Docker terpasang); `vercel.json` dan port belum diuji di Vercel.
