# DEPLOY-STORAGE-001 — Log

**Status:** kode, config, test, dan addendum selesai ditulis; **belum dijalankan** dan **belum bisa DONE** (lihat "Belum selesai").
**PIC:** Orang 1 · **Tanggal:** 1 Nov 2026
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2; D-28 (usulan); `docs/addendum/DEPLOY-STORAGE-001.md`.
**Dependency:** DEPLOY-DECISION-001. **Catatan jujur:** bagian 10 (persetujuan pemilik) pada `SoT-Amendment-DEPLOY-D26-D33.md` masih kosong. Task dikerjakan atas arahan Orang 1 di chat; keputusan D-28 tetap usulan sampai pemilik menandatangani. Bila pemilik mengubah D-28 (mis. logo lewat endpoint, bukan bucket publik), config dan addendum perlu disesuaikan.

## Yang dikerjakan
1. `config/filesystems.php`: disk `private` dan `public` dapat dialihkan ke S3 lewat env, nama disk tetap, default lokal. Disk S3 `throw => false`, tanpa `visibility`.
2. `.env.example`: variabel baru, path-style `true`.
3. `tests/Feature/Upload/StorageDiskConfigTest.php`: 7 test.
4. `docs/addendum/DEPLOY-STORAGE-001.md`.
5. Tidak ada perubahan di `app/`, endpoint, field, aturan bisnis, atau skema DB.

## Yang diperiksa dan yang tidak
- Diperiksa dengan membaca kode: semua pemakai disk di `app/` (`StudentService`, `UploadService`, `SchoolResource`, `MigrateStudentPhotosToPrivate`), `Filesystem::response()` Laravel (jumlah panggilan S3), dan test yang ada (semuanya memakai `Storage::fake('public'|'private')`, tidak terdampak).
- **Tidak dijalankan:** sandbox tanpa PHP dan tanpa akses packagist. Tidak ada test, `php -l`, atau suite yang dijalankan. Sintaks dan perilaku test baru belum terbukti.
- **Tidak dikerjakan:** `composer require league/flysystem-aws-s3-v3` dan pembaruan `composer.lock` (butir 1 acceptance criteria).
- Tidak ada koneksi ke Supabase atau layanan eksternal.

## Belum selesai (syarat DONE)
1. Jalankan `composer require league/flysystem-aws-s3-v3 "^3.0"`, commit `composer.json` dan `composer.lock`, periksa `composer audit`.
2. Jalankan `php artisan test --filter=StorageDiskConfigTest` (7 lulus, 0 dilewati) dan suite penuh hijau (baseline 623 passed / 3502 assertions, versi PHP dicatat; PHP 8.3 sesuai D-26).
3. Review silang Orang 2.
4. Pembuktian S3 nyata di DEPLOY-VERIFY-001, termasuk risiko header ACL (addendum bagian 6, butir 1).

## Temuan
1. Task Board menulis dua panggilan S3 per unduhan foto; kode sebenarnya sampai empat (`exists`, `mimeType`, `size`, `readStream`). Dicatat untuk PILOT.
2. `.env.example` sebelumnya `AWS_USE_PATH_STYLE_ENDPOINT=false`; diubah `true` karena Supabase wajib path-style.

## Review silang Orang 2 (7 Nov 2026, statis dari kode di vendor dan hasil run)

1. **D-28 menetapkan "tanpa ACL" (x-amz-acl tidak didukung Supabase), tetapi implementasi saat ini tetap mengirim header itu.** Di `vendor/league/flysystem-aws-s3-v3/AwsS3V3Adapter.php`, `upload()` memakai `$acl = $options['params']['ACL'] ?? $this->determineAcl($config)` (baris 155) dan `copy()` memakai `visibilityToAcl($visibility ?: 'private')` (baris 444); nilai bawaannya `private`, jadi setiap unggah ke disk S3 membawa `x-amz-acl: private` walau `visibility` tidak diset di `config/filesystems.php`. Komentar config ("akses diatur lewat bucket, bukan ACL") benar untuk konfigurasinya, bukan untuk header yang dikirim adapter. Perilaku Supabase terhadap header itu **tidak diverifikasi di sini**; bila Supabase menolaknya, unggah foto dan logo gagal. Opsi: buktikan lebih dulu dengan unggah nyata di DEPLOY-VERIFY-001; bila gagal, buang header di sisi klien (middleware/handler AWS SDK atau adapter turunan) sebagai perubahan di task ini atau task lanjutan.
2. Test: suite penuh 670 passed (3842 assertions) tanpa baris dilewati pada ringkasan, sehingga `StorageDiskConfigTest` berjalan penuh (adapter terpasang); dugaan dari ringkasan, bukan dari daftar per test.
3. Tidak ditemukan masalah lain pada `config/filesystems.php`, tiga Request import (`max:4096`), dan test baru dalam pembacaan statis.
