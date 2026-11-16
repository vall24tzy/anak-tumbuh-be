# Team-log DEPLOY-PILOT-001

Pelaksana: Orang 2 (Pemilik), dibantu asisten AI. Hasil dan angka ada di `docs/testing/DEPLOY-PILOT-001-result.md`. Berkas ini hanya mencatat urutan kerja, keputusan, dan cara mengulang. Tidak ada rahasia di dalamnya.

## 1. Urutan kerja

| Tanggal | Langkah | Hasil |
|---|---|---|
| 12 Nov | Tahap 1: Node 18.8 bawaan Laragon gagal untuk Vercel CLI 65 (butuh Node 20 ke atas). Dipasang Node 24 LTS, `vercel login` akun pribadi 2FA, clone bersih `anak-tumbuh-pilot` (cabang `deploy/vercel-verify`, `8404e01`, tanpa `.env`) | Selesai |
| 12 Nov | Tahap 2: kunci S3 baru; `APP_KEY`, `JWT_SECRET`, dan sandi seed dibuat baru, disimpan di pengelola kata sandi | Selesai |
| 12 Nov | Tahap 3: `vercel link` tanpa koneksi Git, project `anaktumbuh-be`; Function Region diubah iad1 ke **sin1**; Protection Bypass for Automation dibuat | Selesai |
| 12 Nov | Tahap 4: 24 variabel Preview diisi lewat dasbor | Selesai |
| 12 Nov | Tahap 5: `migrate:fresh` dan `db:seed` lewat Session pooler (5432), dua bucket dikosongkan. Sandi database sempat tampil di keluaran `db:show` yang ditempel ke percakapan; **segera diganti** | Selesai |
| 12 Nov | Tahap 6: deploy pertama otomatis jadi Production (tanpa variabel), lalu deploy Preview. `/up` 200, `/auth/me` 401 sesuai envelope, `x-vercel-id` menunjukkan sin1 | Selesai |
| 12 sampai 13 Nov | TEST-002 run 1 sampai 5. Penyebab kegagalan diurai berurutan: header bypass pada `pm.sendRequest`, NIP data uji, upload S3 500 | Run 5 mendekati bersih |
| 12 Nov | Diagnosis upload S3: skrip sementara menghidupkan `throw` di disk S3, galat asli = kredensial tidak terbaca (T-03). Perbaikan: `STORAGE_S3_KEY` dan `STORAGE_S3_SECRET`; skrip diagnostik dibatalkan lewat `git checkout` | Selesai |
| 13 Nov | Bukti dasbor Supabase (koneksi dan log level) untuk run 4 dan 5. Uji rate limit A dan C; B tidak selesai | Selesai kecuali B |
| 13 Nov | TEST-001 run 1 tidak valid (sandi seed berbeda dari `sa_password`); run 2: import siswa 150 baris 500 di batas 30 detik | Temuan T-06 |
| 14 Nov | Perubahan kode lewat patch dari asisten AI lain: `max_execution_time = 120` dan lainnya. Run 3: ECONNRESET | Belum terjelaskan |
| 14 Nov | Run 4 dengan `BCRYPT_ROUNDS=4` (diagnostik): 2 gagal (413), import 150 baris 5,95 detik | Penyebab biaya hash terbukti |

## 2. Keputusan selama pilot

- Cara deploy PILOT: CLI dari folder bersih di komputer Orang 2 (keputusan Pemilik 12 Nov), bukan integrasi Git.
- Tes B (dua klien berbeda) **ditunda** demi kecepatan dan dicatat "belum dijalankan"; wajib paling lambat di smoke test DEPLOYMENT-001.
- Koleksi Postman di repo **tidak diubah**. Penyesuaian untuk Vercel ada di salinan hasil `patch-collection.js`.

## 3. Alat bantu (di `D:\anaktumbuh\pilot-tools\`, di luar repo)

| Berkas | Fungsi |
|---|---|
| `patch-collection.js` | Salinan koleksi: header bypass pada request dan `pm.sendRequest`, jeda login (75 detik, dihitung dari respons), sinkron `sa_password` dan `base_url` ke variabel koleksi, buang prefiks `/PATH/KE/` pada berkas upload (TEST-001) |
| `slow-requests.js`, `pilot-stats.js` | Ringkasan: jumlah 5xx, request lambat, median, p90, p95 |
| `rl-probe.cmd` | Percobaan login palsu bernomor untuk Tes B (IPv4 dipaksa) |
| `s3-keys.js` | Mengubah `config/filesystems.php` agar membaca `STORAGE_S3_*` |
| `s3-diag.js` | Diagnostik sementara (`STORAGE_S3_THROW`), sudah dibatalkan |

## 4. Cara mengulang pengujian (tanpa nilai rahasia)

1. **Reset database** di Cmder Laragon, folder `anak-tumbuh-be`, dengan `DB_DIRECT_URL` (Session pooler) dan `SEED_SUPER_ADMIN_PASSWORD` yang **sama dengan** `sa_password`:
   `php artisan migrate:fresh --database=pgsql_direct --force`, lalu `php artisan db:seed --database=pgsql_direct --force`. Periksa dulu bahwa `DB_DIRECT_URL` terisi dan menunjuk `pooler.supabase.com`; tanpa itu perintah menghapus database lokal.
2. Kosongkan **isi** dua bucket Supabase (bucket jangan dihapus).
3. Di `cmd`: isi `URL` (URL Preview terbaru tanpa garis miring), `BYPASS`, `SA_PW` dengan `set "NAMA=nilai"`, lalu uji login `superadmin1` (harus 200) setelah menunggu 60 detik.
4. TEST-002: `python generate_test_files.py` di `docs\postman` (wajib tiap run), buat salinan koleksi dengan `patch-collection.js`, jalankan newman dengan `--env-var` untuk `base_url`, `sa_password`, `vercel_bypass`.
5. TEST-001: jalankan dari folder `sample-files`, tanpa file environment. Data statis, jadi **reset database di setiap run**.
6. Jangan menjalankan percobaan login lain selama newman berjalan, dan tunggu lebih dari 75 detik antar run (pembatas login 5 per menit per IP).

## 5. Catatan keamanan

- Sandi database tampil di percakapan (langkah Tahap 5) dan sudah diganti.
- Zip repo yang diunggah ke percakapan AI memuat `.env` dan `.env.testing` lokal. Ganti nilainya bila masih dipakai; zip berikutnya jangan menyertakan berkas itu.
- Keluaran newman dan `.env.local` (token OIDC Vercel) tidak boleh masuk repo.
