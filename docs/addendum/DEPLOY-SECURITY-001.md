# DEPLOY-SECURITY-001 — Tinjauan 20 advisory `composer audit`

**Tanggal:** 18 Nov 2026 · **PIC:** Orang 1 (usulan) · **Review silang dan tanda tangan penerimaan risiko:** Orang 2 · **Cabang:** `deploy/vercel-security` (dari `deploy/vercel-pilot` @ 60aac12)
**Dasar:** Task Board Deployment v1.14 (DEPLOY-SECURITY-001), D-34 (menahan go-live DEPLOYMENT-001, tidak menahan PILOT), D-35 (repo privat, selesai 17 Nov 2026), keputusan pemilik soal Laravel 11 (10 Nov 2026).
**Sifat perubahan:** hanya `composer.lock` (update terbatas pada 7 paket); `composer.json`, kode aplikasi, endpoint, dan skema tidak berubah.

## 1. Hasil `composer audit`

| | Entri | Isu berbeda | Paket terdampak |
|---|---|---|---|
| **Sebelum** (17 Nov, lock di `60aac12`) | 20 | 19 | 8 |
| **Sesudah** `composer update` terbatas | 5 | 4 | 1 (`laravel/framework`) |

Dua dari 20 entri awal adalah advisory yang sama (`CVE-2026-48019` dan `GHSA-5vg9-5847-vvmq` tercatat dua kali), sehingga 20 entri = 19 isu berbeda; hal yang sama berlaku untuk 5 entri sisa = 4 isu. Rincian per paket sebelum: laravel/framework 5, symfony/http-foundation 5, phpoffice/phpspreadsheet 3, league/commonmark 2, symfony/yaml 2, symfony/http-kernel 1, symfony/mime 1, symfony/routing 1.

## 2. Perubahan `composer.lock` (update terbatas, bukan update penuh)

Perintah: `composer update symfony/http-foundation symfony/http-kernel symfony/mime symfony/routing symfony/yaml league/commonmark phpoffice/phpspreadsheet`, dijalankan setelah `--dry-run` (hanya 7 paket naik, 0 pasang, 0 hapus).

| Paket | Sebelum | Sesudah | Batas perbaikan menurut advisory |
|---|---|---|---|
| phpoffice/phpspreadsheet | 1.30.6 | 1.30.8 | 1.30.7 |
| league/commonmark | 2.10.0 | 2.10.7 | 2.10.2 |
| symfony/http-foundation | v7.4.17 | v7.4.23 | 7.4.23 (ReDoS: 7.4.19) |
| symfony/http-kernel | v7.4.17 | v7.4.23 | 7.4.23 |
| symfony/routing | v7.4.17 | v7.4.23 | 7.4.23 |
| symfony/yaml | v7.4.17 | v7.4.23 | 7.4.23 (CVE-2026-109413: 7.4.18) |
| symfony/mime | v7.4.17 | v7.4.22 | 7.4.22 |

`git diff --stat` setelah update: hanya `composer.lock` (41 baris ditambah, 41 dihapus). `composer.json` tidak berubah (semua versi baru masih dalam rentang yang diizinkan). `php artisan test` setelah update: **695 passed (4.324 assertions)**, termasuk `FrameworkAdvisoryGuardTest` dan `MailExposureGuardTest`.

## 3. Status per advisory (20 entri)

### 3a. Diperbaiki lewat update terbatas (15 entri)

| # | Paket | Advisory | Judul singkat | Tingkat | Versi sesudah |
|---|---|---|---|---|---|
| 1 | phpoffice/phpspreadsheet | CVE-2026-108358 (GHSA-3x94-m842-937c) | XmlScanner dilewati lewat escape ISO-2022: XXE dan DoS | tinggi | 1.30.8 |
| 2 | phpoffice/phpspreadsheet | CVE-2026-108359 (GHSA-j2w7-387v-m2rq) | Pembaca ODS: `number-rows-repeated` tak terbatas, DoS | tinggi | 1.30.8 |
| 3 | phpoffice/phpspreadsheet | CVE-2026-108360 (GHSA-2phh-27fw-q4gc) | SSRF: whitelist dilewati lewat redirect pada `Drawing::setPath` | sedang | 1.30.8 |
| 4 | league/commonmark | GHSA-3q6v-r5mr-hxv8 | DoS kuadratik pada ekstensi tabel GFM | tinggi | 2.10.7 |
| 5 | league/commonmark | GHSA-97jj-33gv-5xf9 | `DisallowedRawHtml` dilewati | sedang | 2.10.7 |
| 6 | symfony/http-foundation | CVE-2026-108082 | ReDoS di `HeaderUtils::split()` lewat header Cache-Control | tinggi | v7.4.23 |
| 7 | symfony/http-foundation | CVE-2026-121619 | Pintasan `private_ranges` pada trusted proxies | n/a | v7.4.23 |
| 8 | symfony/http-foundation | CVE-2026-118514 | Request HEAD melewati opsi `methods` pada `access_control` | n/a | v7.4.23 |
| 9 | symfony/http-foundation | GHSA-3h27-hjv7-fqjx | Aturan path di depan PHP dapat dilewati dengan menambah path ke nama script | n/a | v7.4.23 |
| 10 | symfony/http-foundation | GHSA-5mx8-mwrp-5693 | URL yang dibentuk dapat mengarah ke host pilihan klien | n/a | v7.4.23 |
| 11 | symfony/http-kernel | CVE-2026-123339 | Fragmen inline: header forwarded tak tepercaya menentukan host, skema, dan port | n/a | v7.4.23 |
| 12 | symfony/mime | CVE-2026-45070 | Injeksi header email lewat karakter non-token pada nama parameter MIME | sedang | v7.4.22 |
| 13 | symfony/routing | CVE-2026-118516 | Placeholder host dapat cocok dengan awal path pada URL matcher terkompilasi | n/a | v7.4.23 |
| 14 | symfony/yaml | CVE-2026-109413 | Rekursi tak terbatas di lexer inline YAML | tinggi | v7.4.23 |
| 15 | symfony/yaml | CVE-2026-123792 | Waktu parse eksponensial dan kuadratik pada parser YAML | n/a | v7.4.23 |

Catatan keterjangkauan (pembacaan statis, bukan jaminan): item 1 paling relevan karena import `.xlsx` (guru dan siswa) memakai phpspreadsheet; import hanya menerima `.xlsx` (`mimes:xlsx`), jadi item 2 kemungkinan tidak terjangkau, tetapi tetap diperbaiki. `symfony/yaml` hanya ada di `require-dev`, bukan di image produksi. Untuk item 8 (access_control Symfony Security) dan item 11 (fragmen inline) tidak ditemukan pemakaian di kode aplikasi; semuanya tetap diperbaiki karena versi perbaikan tersedia dalam rentang yang diizinkan.

### 3b. Tidak diperbaiki (5 entri = 4 isu): menunggu upgrade Laravel, diterima pemilik

Tidak ada rilis `laravel/framework` 11.x yang memperbaikinya; versi perbaikan hanya ada di 12.x dan 13.x. Laravel 11.56.1 adalah rilis 11.x terakhir.

| # | Advisory | Judul | Tingkat | Versi perbaikan | Keterjangkauan dan penjaga |
|---|---|---|---|---|---|
| 16 | CVE-2026-128083 (GHSA-63hm-g96r-2m2q), dilaporkan 13 Nov 2026 | Task Output Injection pada proses `Concurrency` | rendah | 12.71.0, 13.40.0 | Tidak ada pemakaian `Concurrency` di `app/`, `routes/`, `bootstrap/` (pencarian statis). **Belum ada tes penjaga**; hanya judul advisory yang dibaca. |
| 17 | CVE-2026-102279 (GHSA-jh5r-qr3c-85q8) | XSS pada informasi halaman debug | rendah | 12.69.0, 13.30.0 | Hanya terjadi saat debug aktif. **`APP_DEBUG=false` wajib** di Preview dan produksi (default `config/app.php` false; `ErrorDisclosureTest`). |
| 18 | GHSA-crmm-hgp2-wgrp | Temporary Signed URL Path Confusion | sedang | 12.61.1, 13.12.0 | Aplikasi tidak memakai signed URL; dijaga `FrameworkAdvisoryGuardTest`. |
| 19 dan 20 | GHSA-5vg9-5847-vvmq (juga CVE-2026-48019; satu isu, dua entri) | Injeksi CRLF pada aturan validasi `email` bawaan | tinggi | 12.60.0, 13.10.0 | Aplikasi tidak mengirim email dan tidak memakai aturan `email`; dijaga `MailExposureGuardTest`. `symfony/mime` yang ikut diperbaiki (item 12) menutup sisi MIME. |

## 4. Patokan waktu upgrade Laravel (AC 4)

Keputusan pemilik (10 Nov 2026): tetap Laravel 11 untuk sekarang; upgrade ke 12 atau 13 paling lambat **4 minggu setelah go-live**; target 12 atau 13 dipertimbangkan saat upgrade. Tahap `composer update` terbatas di dokumen ini dilakukan **sebelum** go-live. Fakta yang dicatat di board (diverifikasi 10 Nov 2026 dari laravel.com): Laravel 11 di luar dukungan keamanan sejak 12 Maret 2026; perbaikan keamanan Laravel 12 berakhir 24 Feb 2027; Laravel 13 dirilis 17 Mar 2026. Versi minimum yang memperbaiki kelima entri laravel di atas: **12.71.0** (Laravel 12) atau **13.40.0** (Laravel 13). Fakta tanggal dukungan di atas tidak saya verifikasi ulang.

## 5. Penerimaan risiko (AC 5) — ditandatangani Orang 2 sebelum go-live

Pemilik menerima empat isu pada 3b (5 entri) selama syarat berikut terpenuhi (selaras `docs/ops/security-001-env.md`):
1. `APP_DEBUG=false` di Preview dan produksi.
2. `FrameworkAdvisoryGuardTest` dan `MailExposureGuardTest` tetap hijau (gagal bila kode mulai memakai signed URL, markdown, atau email).
3. `composer audit` dijalankan saat deploy dan hasilnya dicatat.
4. Evaluasi ulang bila ada advisory baru yang menyentuh fitur yang dipakai aplikasi; upgrade Laravel sesuai bagian 4.
5. Pemakaian `Concurrency` (isu 16) belum dijaga tes; evaluasi ulang sebelum memakainya.

| Isu | Diterima | Nama | Tanggal | Tanda tangan |
|---|---|---|---|---|
| 16 (Concurrency) | ☐ | | | |
| 17 (XSS debug) | ☐ | | | |
| 18 (signed URL) | ☐ | | | |
| 19 dan 20 (CRLF email) | ☐ | | | |

## 6. Batas bukti dan yang belum dikerjakan
- Daftar 20 entri berasal dari `composer audit` Orang 1 (17 Nov 2026). Analisis keterjangkauan berupa pembacaan statis; hanya judul advisory yang dibaca untuk isu 16 dan 17 (isi advisory GitHub tidak dibuka).
- phpspreadsheet naik dua versi minor patch (1.30.6 ke 1.30.8); `php artisan test` hijau, tetapi import `.xlsx` belum diuji di Preview setelah update. Usul: Orang 2 menjalankan import 150 dan 200 baris (TEST-001) pada redeploy berikutnya.
- Tidak ada perubahan kode aplikasi, endpoint, atau skema.
