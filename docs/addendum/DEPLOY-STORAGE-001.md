# DEPLOY-STORAGE-001 — Penyimpanan foto Student dan logo School di S3 (Supabase Storage)

**Tanggal:** 1 Nov 2026 · **PIC:** Orang 1 · **Sifat perubahan:** konfigurasi dan test; **tidak ada** perubahan endpoint, field respons, aturan bisnis, skema DB, maupun kode di `app/`.
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2 (DEPLOY-STORAGE-001), D-28 (usulan di `SoT-Amendment-DEPLOY-D26-D33.md`), F-06 (foto Student privat), F-07/F-08 (upload). **Catatan dependency:** tabel persetujuan pemilik (bagian 10 berkas D-26 sampai D-33) belum diisi; task dikerjakan atas arahan Orang 1 di chat (1 Nov 2026). D-28 tetap berstatus usulan sampai pemilik menandatanganinya.

## 1. Perubahan

| Berkas | Isi |
|---|---|
| `config/filesystems.php` | Disk logis `private` (foto Student) dan `public` (logo School) dapat dialihkan ke S3 lewat env. **Nama disk tidak berubah**, jadi semua kode dan test yang memakai `Storage::disk('private'\|'public')` tetap berlaku. Default tanpa env = disk lokal persis seperti sebelumnya. |
| `.env.example` | Variabel baru (nama saja, tanpa nilai rahasia). `AWS_USE_PATH_STYLE_ENDPOINT` diubah `false` menjadi `true` (Supabase wajib path-style; hanya memengaruhi disk S3). |
| `tests/Feature/Upload/StorageDiskConfigTest.php` | 7 test (lihat bagian 4). |

Tidak ada perubahan di `app/` (`UploadService`, `StudentService`, `SchoolResource`, `MigrateStudentPhotosToPrivate`).

## 2. Variabel environment (nama)

| Variabel | Fungsi |
|---|---|
| `STORAGE_PRIVATE_DRIVER` | `local` (default) atau `s3`: tempat foto Student |
| `STORAGE_PUBLIC_DRIVER` | `local` (default) atau `s3`: tempat logo School |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` | Kredensial dan region S3 (hanya di environment platform) |
| `AWS_ENDPOINT` | `https://<project-ref>.supabase.co/storage/v1/s3` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` untuk Supabase (default `true` pada disk S3 baru) |
| `AWS_PRIVATE_BUCKET` | Bucket **privat** untuk foto Student |
| `AWS_PUBLIC_BUCKET` | Bucket **publik** untuk logo School |
| `AWS_PUBLIC_URL` | URL dasar objek publik, dipakai membentuk URL logo (**wajib** bila `STORAGE_PUBLIC_DRIVER=s3`) |

Disk `s3` bawaan (`AWS_BUCKET`, `AWS_URL`) tidak diubah dan tidak dipakai aplikasi.

## 3. Perilaku yang sengaja tidak berubah

- `GET /students/{id}/photo` tetap satu-satunya jalan membuka foto (otorisasi, 404 di luar scope, header `Cache-Control: private, no-store`). Respons tidak memuat path atau URL bucket.
- Bentuk field `logo` pada respons School tetap URL; hanya asal URL berubah (dari `AWS_PUBLIC_URL`) bila logo di S3.
- Disk S3 memakai `'throw' => false`, sama dengan disk lokal, sehingga `UploadService` tetap memeriksa `put() === false` dan melempar `RuntimeException` yang sama.
- Disk S3 **tidak** memberi `visibility` (akses diatur lewat bucket privat/publik).
- Tidak ada migrasi data: deployment baru dengan database kosong. `php artisan photos:migrate-to-private` **tidak dipakai** pada deployment baru (perintah itu memindahkan antar disk lokal `public` dan `private` untuk data lama).

## 4. Test

Tujuh test di `StorageDiskConfigTest`: (1) default tanpa env tetap lokal; (2) `private` S3: bucket privat, tanpa `url`, `throw` false, `public` tetap lokal; (3) `public` S3: bucket publik dan `url` dari `AWS_PUBLIC_URL`, `private` tetap lokal; (4) dua bucket tidak sama dan semua disk S3 `throw` false; (5) path-style dapat dimatikan; (6) URL logo dibentuk dari `AWS_PUBLIC_URL`; (7) kegagalan `put()` pada disk S3 (endpoint lokal yang menolak koneksi, tanpa trafik keluar) menghasilkan `RuntimeException "Gagal menyimpan file upload"`. Test 6 dan 7 **dilewati otomatis** bila `league/flysystem-aws-s3-v3` belum terpasang.

**Status eksekusi: belum dijalankan.** Sandbox penyusun tidak punya PHP maupun akses ke packagist. Jalankan: `php artisan test --filter=StorageDiskConfigTest`, lalu suite penuh.

## 5. Yang harus dilakukan di environment tim

1. `composer require league/flysystem-aws-s3-v3 "^3.0"` lalu commit `composer.json` dan `composer.lock`; periksa `composer audit` untuk dependensi baru. **Belum dikerjakan** (tidak ada akses packagist di sandbox).
2. Jalankan `StorageDiskConfigTest` (7 test lulus, tidak ada yang dilewati) dan suite penuh, termasuk `FrameworkAdvisoryGuardTest` dan `MailExposureGuardTest`.
3. Pembuktian terhadap Supabase Storage nyata dilakukan di DEPLOY-VERIFY-001.

## 6. Risiko dan hal yang dicatat untuk VERIFY dan PILOT

1. **Header ACL.** Flysystem AWS S3 mengirim `x-amz-acl: private` pada tiap unggah bila visibility tidak diset. Belum terbukti apakah endpoint S3 Supabase (termasuk paket Free) menerima atau mengabaikannya. Bila menolak, `put()` mengembalikan `false` dan unggah gagal; diuji di VERIFY. Perbaikannya menjadi task kode tersendiri, tidak ditebak di sini.
2. **Jumlah panggilan S3 untuk satu foto.** Task Board menulis dua; dari kode sebenarnya `StudentService::photo()` memanggil `exists()`, lalu `Filesystem::response()` Laravel memanggil `mimeType()` dan `size()`, lalu `readStream()`: **sampai empat permintaan ke S3 per unduhan foto**. Dicatat latensinya di PILOT. Optimasi hanya bila perilaku 404 dan header tidak berubah.
3. **Penghapusan foto lama di disk `public`.** `StudentService` memanggil `delete($oldPhotoPath, 'public')` setelah unggah foto (sisa relokasi F-06). Bila `STORAGE_PUBLIC_DRIVER=s3` ini menambah satu panggilan `exists()` ke bucket publik per penggantian foto; tidak berbahaya di deployment baru. Dibiarkan agar perilaku tidak berubah.
4. **`AWS_PUBLIC_URL` kosong.** Bila `STORAGE_PUBLIC_DRIVER=s3` tetapi `AWS_PUBLIC_URL` tidak diisi, URL logo akan dibentuk dari endpoint S3 (bukan URL publik) dan tidak dapat dibuka. Config tidak melempar error; pastikan variabel ini terisi (dicek di VERIFY).
5. **Kuota.** Foto dan logo dibatasi 2 MB (`max:2048`), jauh di bawah batas 50 MB per file paket Free; total penyimpanan 1 GB diawasi di VERIFY dan PILOT.

## 7. Dampak ke dokumen lain

API Contract v1.3: tidak ada perubahan (bentuk respons tidak berubah). DB Design: tidak ada. DEPLOY-DOC-001: catat variabel baru pada panduan deployment.
