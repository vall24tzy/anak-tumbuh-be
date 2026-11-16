# DEPLOY-PILOT-001: catatan untuk Orang 1 (perubahan kode dan tindak lanjut)

Dari: Orang 2 (Pemilik). Sumber bukti: `docs/testing/DEPLOY-PILOT-001-result.md`. Berkas ini hanya memuat apa yang perlu ditinjau atau dikerjakan.

## A. Perubahan kode yang sudah berjalan di Preview (perlu commit dan review silang)

| Berkas | Perubahan | Bukti |
|---|---|---|
| `config/filesystems.php` | Disk S3 membaca `STORAGE_S3_KEY` dan `STORAGE_S3_SECRET` lebih dulu, lalu `AWS_ACCESS_KEY_ID` dan `AWS_SECRET_ACCESS_KEY` sebagai cadangan. Hanya dua baris, sehingga lokal dan test tidak berubah | Upload 9.01 dan 9.02: 500 setelah 11 detik menjadi 201 dalam 0,6 sampai 1,4 detik |
| `docker/php/zz-vercel.ini` | `max_execution_time = 120` | Terverifikasi ada di folder deploy. **Efeknya belum terbukti** (T-06, run 3) |

Patch `DEPLOY-PILOT-code-fixes.patch` dari asisten AI lain juga memuat `.gitignore`, `.env.example`, dua dokumen ops, dan dua test baru di `StorageDiskConfigTest`. Isi itu **belum saya tinjau**. Mohon tinjau, dan jalankan `php artisan test` penuh, karena pengujian di sisi pembuat patch tidak memakai PostgreSQL.

Dokumen yang perlu diselaraskan: `docs/ops/vercel-deploy.md` dan `docs/addendum/DEPLOY-STORAGE-001.md` masih menyebut `AWS_*` sebagai nama variabel kunci.

## B. Tindak lanjut yang butuh keputusan: import siswa lewat batas waktu (T-06, D-36 usulan)

**Fakta:**
- Di jalur import, `StudentService.php:235` memanggil `Hash::make((string) Str::uuid())` dengan round bawaan (12) untuk setiap siswa. `QrTokenService.php:63` melakukan hal serupa di jalur regenerate. Password awal sudah round 4 (`InitialPasswordHasher`, F-55).
- Dengan round bawaan: 500 pada batas 30 detik (`BcryptHasher.php:49`); setelah batas PHP 120 detik, koneksi tetap terputus di kisaran 30-an detik (penyebab belum diketahui). Dengan `BCRYPT_ROUNDS=4`: 150 baris dalam 5,95 detik.
- Biaya per hash di Vercel **belum diukur terpisah**. Dari fakta di atas, perkiraannya 0,2 sampai 0,3 detik; itu perkiraan.

**Opsi:**
1. **Usulan:** nilai acak berentropi tinggi (UUID placeholder, token QR) di-hash dengan round rendah, memakai pola yang sama seperti `InitialPasswordHasher`. Round tinggi tidak menambah keamanan untuk nilai acak panjang, dan `Hash::check` membaca biaya dari hash itu sendiri. Perlu test: login QR lama dan baru tetap lolos, regenerate tetap valid, biaya hash rendah.
2. Turunkan `MAX_ROWS`, hanya sebagai tambahan bila setelah opsi 1 angka belum di bawah batas yang Pemilik tetapkan.
3. Menaikkan `max_execution_time` **bukan solusi tunggal** (run 3).

**Bukti yang diminta setelah perubahan:** TEST-001 hijau di Preview **tanpa** `BCRYPT_ROUNDS=4`, import 150 dan 200 baris di bawah batas durasi Pemilik.

## C. Catatan untuk dokumen dan tes

| Hal | Dampak |
|---|---|
| Vercel menolak body di atas 4,5 MB dengan 413 dan isi teks polos `Request Entity Too Large` (bukan JSON) | Ekspektasi "file > 5 MB → 422" (TEST-001 folder 6, TEST-002 10.11) tidak berlaku di Vercel; Contract, Panduan, dan tes perlu disesuaikan di DEPLOY-DOC-001 |
| Respons 429 login tidak memuat `Retry-After` | Catat di Contract bila FE perlu menampilkan waktu tunggu |
| Disk S3 `throw => false` menelan galat dasar S3 | Usulan: catat galat dasar sebelum `put()` mengembalikan false |
| Skrip koleksi: `await` tingkat atas (1.06, 2.09, 2.17, 13.10) tidak jalan di newman; 1.10 dan 7.06 usang; `pm.sendRequest` butuh header bypass di Vercel; jeda login sebaiknya dihitung dari respons | Penyesuaian ada di salinan koleksi (`patch-collection.js`); koleksi di repo tidak diubah |

## D. Yang masih terbuka

- Review silang kode empat task deployment (tercatat terbuka sejak v1.8).
- Tes B (dua klien dengan IP berbeda), paling lambat di smoke test DEPLOYMENT-001.
