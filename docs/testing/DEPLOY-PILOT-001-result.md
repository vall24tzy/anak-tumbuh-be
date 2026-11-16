# DEPLOY-PILOT-001: hasil pilot di Vercel Preview + Supabase uji

| | |
|---|---|
| Tanggal pengujian | 12 sampai 14 November 2026 |
| Pelaksana | Orang 2 (Pemilik), dibantu asisten AI untuk panduan dan analisis |
| Status | Pengujian selesai. **KEPUTUSAN PEMILIK (D-31): [GO / GO bersyarat]. Syarat: [K1 sampai K6]. DIPUTUSKAN dan TERTANDA: Faisal Nisrina, 16 November 2026** |
| Rekomendasi | **GO bersyarat** (syarat K1 sampai K6 di bagian 9). Ini rekomendasi, bukan keputusan. |
| Commit kode yang di-deploy | **9728b68** hasil `git rev-parse --short HEAD` di `D:\anaktumbuh\anak-tumbuh-pilot` (basis `8404e01` ditambah perubahan di bagian 2) |

## 1. Ringkasan

Backend Laravel berjalan di Vercel Container Runtime (Preview) dengan database dan storage Supabase uji. Seluruh koleksi Postman (TEST-002: 318 request, TEST-001: 211 request) selesai dijalankan terhadap Preview.

- **0 respons 5xx** pada run akhir kedua koleksi. Koneksi database puncak 10 sampai 11 dari batas 60.
- Wilayah fungsi **sin1 (Singapura)** terbukti lewat header `x-vercel-id` (`sin1::sin1::...`). Import guru 200 baris: **4,0 detik** (VERIFY dari laptop: 128,8 detik).
- Median respons 113 sampai 122 ms. Cold start 4,4 sampai 6,4 detik (satu pencilan 16,2 detik).
- **Tiga hal belum bersih:** (a) import siswa 150 baris gagal dengan konfigurasi bcrypt bawaan dan lulus (5,95 detik) hanya dengan `BCRYPT_ROUNDS=4` yang dipasang sementara untuk diagnosis (T-06); (b) perubahan kode untuk kunci S3 belum tercatat di repo (T-03); (c) Tes B (dua klien berbeda) belum dijalankan (bagian 6).

## 2. Lingkungan yang diuji

| Komponen | Nilai |
|---|---|
| Vercel | Hobby, project `nisrina1/anaktumbuh-be`, deploy Preview lewat Vercel CLI 65.14.0 dari folder bersih (bukan integrasi Git) |
| Runtime | Container Runtime (Beta), `vercel.json` dengan service `app [container]`, FrankenPHP, PHP 8.3.36 |
| Wilayah fungsi | **sin1** (bawaan project baru adalah iad1; diubah di Settings, Hobby hanya satu wilayah) |
| Build | 102 detik (tanpa cache, di Vercel), siap di Preview 38 sampai 40 detik pada deploy berikutnya |
| Proteksi | Deployment Protection (Vercel Authentication) aktif; klien otomatis memakai header `x-vercel-protection-bypass` |
| Supabase uji | Free, ap-southeast-1. Aplikasi lewat **Transaction pooler (6543)** dengan `DB_DISABLE_PREPARES`; migrate dan seed lewat **Session pooler (5432)** koneksi `pgsql_direct` |
| Storage | Supabase Storage lewat S3: `anaktumbuh-foto-uji` (privat), `anaktumbuh-logo-uji` (publik) |
| Variabel Preview | 24 variabel awal, ditambah `STORAGE_S3_KEY` dan `STORAGE_S3_SECRET` (T-03). `BCRYPT_ROUNDS=4` dipasang **sementara** untuk diagnosis T-06 |
| Perubahan kode di atas `8404e01` | `config/filesystems.php` (kunci S3 dari `STORAGE_S3_*` dengan cadangan `AWS_*`), `docker/php/zz-vercel.ini` (`max_execution_time = 120`), `.gitignore`. Isi lengkap patch `DEPLOY-PILOT-code-fixes.patch` belum ditinjau Orang 1 |

## 3. Kriteria D-31 dan buktinya

| Kriteria D-31 | Bukti | Status |
|---|---|---|
| Postman lulus | TEST-002 run 5: 318 request, 0 respons 5xx, 11 baris gagal yang semuanya sudah dijelaskan (7 error `await` di newman, 1.10 dan 7.06 pengecekan usang, 10.11 dua baris 413). TEST-001 run 4: 211 request, 0 respons 5xx, 2 gagal (413 file di atas 4,5 MB) | **Terpenuhi bersyarat**: TEST-001 hijau hanya dengan `BCRYPT_ROUNDS=4` (T-06) |
| Upload/unduh dan import berhasil | Upload logo dan foto 201/200 dalam 0,4 sampai 2,0 detik (setelah T-03). Unduh foto 0,65 sampai 1,7 detik. Import guru 101 baris 2,0 sampai 2,5 detik, 200 baris 4,0 sampai 4,1 detik. Import siswa 150 baris 5,95 detik dengan round 4; **gagal dengan round bawaan** | **Bersyarat** (T-06) |
| Tanpa error koneksi | 0 respons 5xx terkait database. Koneksi puncak 10 sampai 11 dari 60. Supavisor/PgBouncer TEST-002 run 4 dan 5: semua 2xx, 0 4xx, 0 5xx (dilaporkan Orang 2 dari dasbor) | **Terpenuhi** |
| Latensi dan cold start dicatat | Bagian 4. Angka batas yang diterima belum ditetapkan | **Dicatat**, menunggu batas dari Pemilik: **Pemilik menerima: median ≤ 300 ms dan p95 ≤ 1.000 ms untuk request biasa; request pertama setelah sepi (cold start) sampai 8 detik (pencilan 16 detik dicatat dan dipantau); serta import massal 200 baris ≤ 15 detik.** |

## 4. Pengukuran

### 4.1 TEST-002 (folder 12 dikeluarkan dari statistik karena memakai `pm.sendRequest`)

| Run | Hasil | Catatan |
|---|---|---|
| 1 | 318 req, 114 assertion gagal, 3m19s, rata-rata 393 ms | Alat uji: header bypass belum ada di `pm.sendRequest`, NIP data uji tidak cocok; upload S3 500 (T-03) |
| 2 | 97 gagal, 2m51s, rata-rata 279 ms, median 122, p90 359, p95 488 ms | Upload dan import sudah lulus |
| 3 | 16 gagal, 3m26s | Impor gagal karena NIP data uji terpakai (generator tidak dijalankan ulang) |
| 4 | 39 gagal, 3m17s | 32 kasus Student folder 12 gagal akibat 429 di 6.26 (jeda login dihitung dari saat kirim; cold start login pertama 16,2 detik) |
| **5** | **4 assertion gagal + 7 error skrip**, 3m21s, 0 respons 5xx | **Run akhir.** n=214: median **113**, p90 **395**, p95 **652 ms**; cold start login 4,6 detik; import 101 baris 2,0 detik; import 200 baris 4,0 detik; unduh foto 0,65 detik |

### 4.2 TEST-001

| Run | Hasil | Catatan |
|---|---|---|
| 1 | Tidak valid | Login Super Admin 401: sandi seed tidak sama dengan `sa_password` |
| 2 | 4 gagal, 3m52s, median 122, p90 378, p95 509 ms, **1 respons 5xx** | 413 (2) dan import siswa 150 baris: **500 setelah 30,1 detik**, log: `Maximum execution time of 30 seconds exceeded` di `BcryptHasher.php:49` |
| 3 | 4 gagal, 3m58s, median 116, p90 405, p95 461 ms, 0 5xx | Setelah `max_execution_time = 120`: import 150 baris **ECONNRESET** tanpa respons (perkiraan terputus di kisaran 30-an detik dari selisih durasi total; tidak ada log) |
| **4** | **2 gagal (hanya 413)**, 3m24s, median 120, p90 260, p95 373 ms, 0 5xx | Dengan `BCRYPT_ROUNDS=4` (diagnostik): import siswa 150 baris 200 OK dalam **5,95 detik** |

### 4.3 Koneksi database (dasbor Supabase, batas 60)

| Rentang | Dasar | Puncak |
|---|---|---|
| TEST-002 run 4 dan 5 | 8 | 10 |
| TEST-001 run 2 (13 Nov 19:19 sampai 19:25 WIB) | 7 sampai 8 | 10 |
| TEST-001 run 3 (14 Nov 06:15 sampai 06:19 WIB) | 7 | 10 sampai 11 |
| TEST-001 run 4 (14 Nov 11:53 sampai 11:56 WIB) | 7 | 10 |

### 4.4 Cold start
Request pertama setelah diam sekitar 30 detik atau lebih (dipicu jeda login 75 detik pada tes): 4,4; 4,5; 4,6; 4,7; 5,0; 5,1; 5,2; 5,9; 6,4 detik. Satu pencilan **16,2 detik** (TEST-002 run 4).

## 5. Temuan

| ID | Temuan | Bukti | Status |
|---|---|---|---|
| T-01 | Wilayah bawaan fungsi iad1 | Diubah ke sin1; `x-vercel-id: sin1::sin1::...` | Selesai |
| T-02 | Deployment Protection menghadang klien otomatis, termasuk `pm.sendRequest` di skrip uji | Tanpa header: 302 ke login Vercel; dengan header: 200 | Dipahami. FE di dev: proxy sisi server yang menambah header |
| T-03 | **`AWS_ACCESS_KEY_ID` dan `AWS_SECRET_ACCESS_KEY` tidak sampai ke Laravel** di runtime Vercel: AWS SDK jatuh ke metadata EC2 (`169.254.169.254`), timeout, upload 500 setelah sekitar 11 detik | Log Laravel: `Error retrieving credentials from the instance profile metadata service`. Setelah memakai `STORAGE_S3_KEY` dan `STORAGE_S3_SECRET`: 9.01 201 dalam 1,4 detik | Terbukti. Penyebab "nama `AWS_*` dibatasi platform" adalah **dugaan** dari dokumentasi Vercel. Perbaikan kode perlu commit dan review |
| T-04 | Disk S3 memakai `throw => false`: galat dasar S3 ditelan, log hanya `Gagal menyimpan file upload` | Perlu skrip diagnostik sementara untuk melihat galat asli | Usulan: catat galat dasar sebelum `put()` mengembalikan false |
| T-05 | Body di atas 4,5 MB ditolak Vercel dengan **413 dan isi teks polos** `Request Entity Too Large`, bukan envelope JSON | TEST-002 10.11 dan TEST-001 folder 6: 2 assertion gagal masing-masing | Konsekuensi D-29; ekspektasi tes dan Contract perlu disesuaikan (DEPLOY-LIMIT-001, DEPLOY-DOC-001) |
| T-06 | **Import siswa 150 baris tidak selesai dengan bcrypt bawaan (round 12).** Jalur import memanggil `Hash::make` round penuh per siswa di `StudentService.php:235` (hash placeholder token QR); password awal sudah round 4 (F-55). Import guru tidak punya hash token, sehingga cepat (4 detik) | Run 2: 500 di batas 30 detik. Run 3: ECONNRESET walau batas PHP 120 detik (pemutusan di kisaran 30-an detik **belum dijelaskan**; bisa batas platform). Run 4: 5,95 detik dengan round 4 | Penyebab biaya hash **terbukti**; pemutusan di sekitar 30 detik **belum terbukti**. Perbaikan lewat D-36 (usulan) dan DEPLOY-IMPORT-001. Menaikkan `max_execution_time` saja tidak cukup |
| T-07 | Cold start 4,4 sampai 6,4 detik, pencilan 16,2 detik | Bagian 4.4 | Dicatat |
| T-08 | Pembatas login 5 per menit per IP dan per identifier. Satu sekolah dengan satu IP publik berbagi 5 percobaan per menit untuk semua siswa. Respons 429 **tanpa header `Retry-After`** | Bagian 6 | Risiko operasional untuk Pemilik (F-23 final) |
| T-09 | Alat uji: `await` tingkat atas tidak jalan di newman; 1.10 dan 7.06 usang; `pm.sendRequest` tanpa header bypass; jeda login dihitung dari saat kirim, bukan saat server memproses; data TEST-001 statis (wajib reset DB per run); `generate_test_files.py` wajib sebelum tiap run TEST-002 | Identik dengan VERIFY untuk `await`, 1.10, 7.06 | Ditangani lewat salinan koleksi `patch-collection.js` (bukan mengubah koleksi di repo) |
| T-10 | Deploy pertama project otomatis menjadi **Production** tanpa variabel lingkungan (alias `anaktumbuh-be.vercel.app`) | Log CLI: "first deployment ... assigned to production" | Terbuka: periksa perlindungannya dan tangani di PRODPREP |

## 6. Uji rate limit dan proxy (dijalankan Orang 2)

| Uji | Hasil | Arti |
|---|---|---|
| A: `X-Forwarded-For` palsu diputar dari satu klien | 401 lima kali, lalu 429 tiga kali (request 6 sampai 8) | Header palsu tidak mereset counter; IP klien berasal dari Vercel |
| C: 6 siswa dengan NISN berbeda dari satu IP | 401 lima kali, lalu 429 pada siswa ke-6 | Sesuai F-23 final; berdampak pada satu sekolah satu IP publik |
| TEST-001 request 79 (F-23) | 429 pada percobaan ke-6, lulus | Pembatas aktif di Vercel |
| **B: dua klien dengan IP berbeda** | **BELUM DIJALANKAN.** Satu jaringan IPv4: 5 kali 401 lalu 429, kirim ulang saat terblokir tetap 429. IPv6 dari laptop tidak terjangkau (kode 000) | **Belum terbukti** bahwa klien kedua tetap lolos saat klien pertama terblokir. Satu-satunya uji yang membedakan penghitung per IP klien dari satu penghitung global |

## 7. Belum dibuktikan

- Tes B (di atas), wajib paling lambat di smoke test DEPLOYMENT-001.
- Isi header `Authorization` dan `Cookie` pada log akses Caddy **di Vercel** (hanya terlihat sebagian).
- Beban paralel: semua uji berjalan berurutan dari satu klien.
- Import siswa tepat 200 baris di Vercel (diukur 150 baris).
- Pemakaian kuota Hobby (CPU aktif) dan perilaku Container Runtime saat berstatus Beta berubah.
- Deployment Production belum pernah diuji.

## 8. Kondisi sementara yang harus ditutup

1. **`BCRYPT_ROUNDS=4` di variabel Preview**: hapus, lalu deploy ulang. Round 4 melemahkan hash password pengguna sungguhan.
2. Deployment Production awal tanpa variabel (T-10).
3. Berkas `.vercel` dan `.env.local` (token OIDC) di folder pilot: jangan di-commit.
4. Keluaran newman (`pilot-run*.json`, `test001-run*.json`) memuat token dan header: simpan di luar repo, jangan di-commit.
5. `TEST-002.postman_environment.json` diubah lokal oleh `generate_test_files.py`: jangan di-commit.
6. Sandi database sempat tampil di keluaran yang ditempel ke percakapan dan sudah diganti. Zip repo yang diunggah ke percakapan AI memuat `.env` dan `.env.testing` lokal: ganti nilainya bila masih dipakai.

## 9. Rekomendasi

**GO bersyarat.** Platform layak untuk dilanjutkan: tanpa respons 5xx pada run akhir, latensi rendah, koneksi database jauh di bawah batas, dan wilayah tepat. Syarat sebelum DEPLOY-PRODPREP-001:

| | Syarat | Pelaksana |
|---|---|---|
| K1 | Commit dan review silang perubahan kode yang berjalan di Vercel (T-03, `zz-vercel.ini`) dan catat hash di bagian atas | Orang 2, Orang 1 |
| K2 | Putuskan D-36 dan kerjakan DEPLOY-IMPORT-001; buktikan TEST-001 hijau di Preview **tanpa** `BCRYPT_ROUNDS=4`, import 150 dan 200 baris di bawah batas durasi yang Pemilik tetapkan | Pemilik, Orang 1 |
| K3 | Hapus `BCRYPT_ROUNDS=4` dari Preview dan deploy ulang | Orang 2 |
| K4 | Jalankan Tes B paling lambat di smoke test DEPLOYMENT-001; sampai itu tercatat "belum dibuktikan" | Orang 2 |
| K5 | Pemilik menerima atau menangani risiko T-08 (satu sekolah satu IP) dan T-05 (413 teks polos) | Pemilik |
| K6 | Periksa deployment Production awal dan perlindungannya (T-10) | Orang 2 |

Bila K2 tidak dapat dipenuhi, jalan mundur D-31 (host Laravel konvensional) tetap tersedia; hasil STORAGE, RUNTIME, dan LIMIT tetap berguna.
