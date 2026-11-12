# DEPLOY-VERIFY-001 — Hasil uji lokal terhadap Supabase uji

**Tanggal:** 10 sampai 11 Nov 2026 · **Penyusun:** Orang 1 · **Koleksi:** TEST-002 (`docs/postman/`) dan TEST-001 (`docs/testing/`)
**Isi:** nama request, kode HTTP, hasil assertion, dan angka. Tanpa token, password, kunci, atau alamat project. JSON mentah newman disimpan di luar repo karena memuat token.
**Status:** dijalankan; **belum DONE** (lihat bagian 6).

## Ringkasan

- Migrasi dan seed lewat `pgsql_direct` (pooler session 5432): 24 migrasi berstatus `Ran`, tabel `cache` ada.
- Aplikasi lokal lewat pooler transaksi (6543) dengan `DB_DISABLE_PREPARES=true`: koneksi berhasil, login Super Admin 200, **0 respons 5xx** pada run valid.
- S3 Supabase: tulis, baca, dan hapus berhasil di bucket privat dan publik; unggah dari aplikasi masuk ke bucket; URL logo publik dapat dibuka tanpa login (200). Header `x-amz-acl` tidak ditolak.
- TEST-002 run 6: 318 request, 696 assertion, 2 gagal (+7 error skrip), 0 respons 5xx.
- TEST-001: 211 request, 381 assertion, 2 gagal, 0 respons 5xx.
- Suite penuh: 672 lulus (3860 assertion), PHP 8.3.30.
- **Tidak terbukti atau tidak dijalankan:** empat request TEST-002 yang memakai `await` (tidak jalan di newman) dan empat endpoint daftar pada 13.10. Rinci di bagian 5.

## 1. Lingkungan

| Hal | Nilai |
|---|---|
| Aplikasi | lokal (`php artisan serve --no-reload`), branch `deploy/vercel-verify` di atas branch Orang 2 |
| PHP | 8.3.30 (Laragon, ZTS, Windows); suite penuh dicatat dengan versi yang sama |
| Database | Supabase uji, region ap-southeast-1; pooler transaksi 6543 (`DB_URL`), pooler session 5432 (`DB_DIRECT_URL`) |
| Pengaturan | `DB_DISABLE_PREPARES=true`, `DB_EMULATE_PREPARES=false`, `DB_SSLMODE=require`, `CACHE_STORE=database` |
| Penyimpanan | `STORAGE_PRIVATE_DRIVER=s3` dan `STORAGE_PUBLIC_DRIVER=s3`, path-style, dua bucket (privat dan publik) |
| Variabel | diisi lewat terminal (bukan berkas `.env`); tidak ada rahasia di repo |
| Berkas uji TEST-002 | dibuat `generate_test_files.py` (Python 3.10.6); skrip itu mengubah `TEST-002.postman_environment.json` secara lokal dan perubahan itu **tidak di-commit** |
| Berkas uji TEST-001 | folder `sample-files`; salinan koleksi di luar repo dengan `/PATH/KE/` dikosongkan dan variabel koleksi diisi |
| newman | versi tidak dicatat pada run ini |

## 2. Run newman

| Run | Waktu (WIB) | Hasil | Dipakai sebagai bukti |
|---|---|---|---|
| 1 (TEST-002, 10 Nov) | 12:35 sampai 12:38 | 448 gagal, 12 respons 500 | **Tidak.** `DB_URL` berawalan ganda; server membaca database lokal |
| 2 (TEST-002, 10 Nov) | 13:21 sampai 13:24 | 446 gagal, sama | **Tidak.** Penyebab sama |
| 3 (TEST-002, 10 Nov) | 13:51 sampai 14:06 | 57 entri gagal, 0 respons 5xx | Sebagian. Folder 9, 10, 13, 14 gagal; dugaan penyebab: berkas uji (`test-files`) belum dibuat (setelah dibuat, folder itu lulus di run 5 dan 6) |
| 4 (TEST-002, 10 Nov) | 17:16 sampai 17:21 | 446 gagal | **Tidak.** `SA_PW` kosong, semua login 401 |
| 5 (TEST-002, 10 Nov) | 17:34 sampai 17:43 | 9 entri, 0 respons 5xx | Untuk database dan pooler. **Tidak untuk S3**: status variabel S3 saat itu tidak terverifikasi |
| **6 (TEST-002, 11 Nov)** | **08:21:08 sampai 08:37:02** | **9 entri, 0 respons 5xx** | **Ya** (S3 aktif: `s3 s3`) |
| TEST-001 (11 Nov) | 10:37:50 sampai 11:33:35 | 2 gagal, 0 respons 5xx | Ya (seluruh 11 folder, jeda 13 detik) |

Run 1, 2, dan 4 gagal karena kesalahan penyiapan di sisi penguji, bukan karena kode atau Supabase. Hasilnya dicatat apa adanya dan tidak dipakai sebagai bukti.

## 3. Hasil per butir Acceptance Criteria

| Butir | Hasil | Bukti |
|---|---|---|
| (0) paket mendukung S3 dan pooler | **Terbukti** dengan operasi nyata | migrate lewat 5432, login lewat 6543, tulis dan baca S3 |
| (1) migrate dan seed | 24 migrasi `Ran` (keluaran `migrate:status` terlihat); seeder dilaporkan selesai (keluarannya tidak saya lihat langsung, login Super Admin 200 menunjukkan seeder akun berjalan) | keluaran perintah |
| (2) aplikasi lewat pooler transaksi | `DB::select('select 1')` berhasil; `/up` 200; login Super Admin 200 | keluaran perintah |
| (3) newman per folder | TEST-002 run 6: kegagalan hanya di folder 1, 2, 7, 13; folder lain 0 gagal. TEST-001: kegagalan hanya di folder 5; folder lain 0 gagal | bagian 4 |
| (4) foto, logo, import | Folder 9 (logo dan foto, termasuk penggantian foto), folder 10 (import guru), folder 14 (import 200 baris) lulus pada run 6. TEST-001: folder 6 (import siswa, termasuk file lebih dari 5 MB) dan folder 8 (unduh foto lewat endpoint terautentikasi) lulus. Tulis, baca, hapus S3 `[true,true,true,true,true]`. URL logo publik: **200**, waktu ubah berkas berada di dalam jendela run 6 | keluaran perintah dan newman |
| (5) koneksi dan error pooler | `laravel.log`: 0 baris `prepared statement` atau `08P01` di seluruh berkas. Puncak koneksi total 11 (dasar sekitar 7 milik layanan Supabase, kenaikan akibat run sekitar 4; pool 15 hanya konteks); hitungan Level log Supabase tanpa Warning atau Error (bagian 6, dilaporkan Orang 2) | perintah di laptop, laporan Orang 2 |
| (6) suite penuh | **672 lulus (3860 assertion)**, PHP 8.3.30, 163,31 detik, di jendela tanpa variabel Supabase dan `.env` tanpa Supabase (database lokal) | keluaran perintah |
| (7) team-log | `docs/team-log/DEPLOY-VERIFY-001.md` | berkas ini |

## 4. Sisa kegagalan dan artinya

**TEST-002 run 6 (9 entri):**

| Jumlah | Request | Penyebab |
|---|---|---|
| 7 | 1.06, 2.09, 2.17, 13.10 | `SyntaxError: await is only valid in async functions`. Skrip memakai `await` yang berjalan di aplikasi Postman, tidak di newman. Bukan kegagalan backend atau Supabase |
| 2 | 1.10 dan 7.06 | Pengecekan usang: mengharap 404 atau 405 untuk PATCH, tetapi `PATCH /schools/{id}` dan `PATCH /teachers/{id}` kini ada (Matrix #65 dan #66) dan menjawab 422. Dikonfirmasi Orang 2 |

**TEST-001 (2 entri, satu request):** "Rate limit login (F-23): request ke-6 dalam 1 menit" dijawab 401, bukan 429. Dicek manual terpisah: tujuh request cepat ke `/auth/login/student/qr` menghasilkan `401 401 401 401 401 429 429`, jadi pembatas laju bekerja dengan cache di Supabase. Penyebab kegagalan di newman belum dipastikan; dugaan: cara skrip mengisi penghitung. Dicatat sebagai belum terjelaskan.

## 5. Yang belum terbukti atau tidak dijalankan

1. **Empat request `await`** tidak dijalankan di Postman desktop. Pengganti yang dijalankan (bukan setara persis):
   - Paginasi School dan Headmaster: login 200, `data` berupa array, `meta` memuat `page`, `per_page`, `total`, `total_pages`.
   - Aturan "satu Headmaster aktif per School": indeks `uq_headmasters_one_active_per_school` ada di Supabase dan tidak ada School dengan lebih dari satu Headmaster berstatus aktif (pemeriksaan hanya-baca).
2. **13.10** mencakup enam endpoint daftar; hanya `/schools` dan `/headmasters` yang dicek ulang. **`/teachers`, `/academic-years`, `/rombels`, dan `/teacher-class-assignments` (butuh token Headmaster) belum diverifikasi ulang di Supabase.**
3. **Postman desktop** tidak dipakai.
4. **Pembatas laju F-23 di newman** belum terjelaskan (bagian 4).
5. **Log pooler Supabase** hanya dibaca lewat hitungan Level, bukan isi satu per satu (bagian 6).
6. **Beban bersamaan** tidak diuji. Semua run adalah satu klien berurutan; beban nyata dari banyak instance diukur di PILOT.

## 6. Angka dari dasbor Supabase (dilaporkan Orang 2, 11 Nov 2026)

Semua angka dibaca dari grafik dasbor, bukan diukur di aplikasi. Pada rentang sempit titik data berjarak sekitar 1 sampai 2 menit (perkiraan), jadi puncak sesaat yang lebih singkat bisa terlewat.

| Jendela (WIB) | Koneksi database |
|---|---|
| Run 3 (10 Nov 13:51 sampai 14:06) | dasar sekitar 6 sampai 7, puncak 9 |
| Run 5 (10 Nov 17:34 sampai 17:43) | dasar sekitar 6, naik ke 9 pada 17:35, bertahan sekitar 8 sampai sekitar 17:46, lalu turun ke 6. Mengoreksi laporan sebelumnya ("tidak tampak kenaikan") yang berasal dari grafik 24 jam yang titiknya jarang |
| Run 6 (11 Nov 08:21 sampai 08:37) | dasar sekitar 7, puncak sekitar 11 |
| TEST-001 (11 Nov 10:37 sampai 11:33) | dasar sekitar 7 sampai 8, puncak 11 pada 11:16 |

- Puncak total tertinggi 11: dasar sekitar 7 milik layanan Supabase sendiri, kenaikan akibat run sekitar 4, jauh di bawah garis batas sekitar 60 di dasbor. Angka pool 15 adalah batas pooler ke Postgres per user dan database, bukan ukuran yang sama dengan total koneksi, jadi hanya konteks. Pengaturan pooler: pool 15 (default compute Nano), maksimum klien 200, berlaku bersama untuk semua pooler. Dasar sekitar 7 adalah proses Supabase sendiri.
- `pg_stat_activity` setelah semua run: 15 baris, tidak ada koneksi yang tampak dari aplikasi (tidak diketahui apakah aplikasi sedang berjalan saat itu).

**Log pooler.** Filter teks di halaman Logs Supabase tidak berfungsi untuk keperluan ini: pencarian kontrol (`exposed_schemas`, yang jelas ada di log) juga menghasilkan "No results found". Karena itu hasil kosong untuk `prepared statement` dan `08P01` di sisi Supabase **tidak dipakai sebagai bukti**. Sebagai gantinya, hitungan Level tanpa filter teks:

| Jendela (WIB) | Supavisor | PgBouncer |
|---|---|---|
| Run 5 (10 Nov 17:30 sampai 17:50) | 1,3 ribu entri, semua Success, 0 Warning, 0 Error | 0 entri |
| Run 6 (11 Nov 08:15 sampai 08:45) | 1,4 ribu entri, semua Success, 0 Warning, 0 Error | 47 entri, semua Success |
| TEST-001 (11 Nov 10:30 sampai 11:40) | 852 entri, semua Success, 0 Warning, 0 Error | 20 entri, semua Success |

- Run 3 tidak diperiksa (kemungkinan sudah lewat batas 24 jam paket Free).
- Batas: hanya sekitar 20 baris teratas dari tiap tangkapan yang dibaca, dan hitungan Level bergantung klasifikasi Supabase. Ini petunjuk, bukan bukti mutlak bahwa tidak ada error pooler.
- Log PgBouncer berisi koneksi layanan Storage Supabase sendiri, bukan lalu lintas aplikasi.
- Entri error berulang tiap sekitar 32 detik (kode `3F000`, `schema "pg_pgrst_no_exposed_schemas" does not exist`) diduga dari PostgREST karena Data API dimatikan, bukan dari aplikasi; penyebabnya belum diperiksa. Jangan memfilter log berdasarkan level Error untuk mencari masalah pooler.
- Bukti pooler yang dipegang: 0 respons 5xx pada semua run valid, 0 baris `prepared statement` atau `08P01` di `laravel.log`, dan hitungan Level di atas.

## 7. Waktu respons (diukur dari laptop ke Supabase Singapura; untuk PILOT)

Angka ini bukan angka di Vercel. Dugaan, belum diukur: tiap query dari laptop memakan sekitar setengah detik.

| Waktu | Request |
|---|---|
| 128,8 detik | TEST-001: `POST /student-imports/commit`, file lebih dari 100 baris (sampai 200) |
| 85,9 detik | TEST-002 (run 5): 14.01 "Import tepat 200 baris (batas atas)" |
| 40,6 detik | TEST-001: `POST /students/{id}/reset-password` |
| 24,6 detik | TEST-001: `GET /student-imports/template` |
| 12,3 detik | TEST-001: `GET /schools?per_page=100` |

TEST-001: rata-rata 2,7 detik (min 285 ms, maks 2 menit 8,7 detik). Batas waktu fungsi Vercel dan wilayah fungsi belum diverifikasi. **Tidak ada perubahan kode yang dibuat.**

## 8. Setelah VERIFY

- Data uji (School, Headmaster, Guru, Siswa, logo, foto) tersisa di database dan bucket Supabase uji; pembersihan oleh Orang 2.
- Sandi database dan kunci S3 diganti Orang 2 setelah semua run selesai (belum dilakukan saat berkas ini ditulis).
