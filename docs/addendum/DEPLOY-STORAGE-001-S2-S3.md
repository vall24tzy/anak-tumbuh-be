# DEPLOY-STORAGE-001 — Tindak lanjut review silang S-2 dan S-3

**Tanggal:** 16 Nov 2026 · **PIC:** Orang 1 · **Cabang:** `deploy/vercel-storage-log` (dari `deploy/vercel-pilot` @ 28f4800)
**Dasar:** review silang Orang 2 atas STORAGE/LIMIT/RUNTIME/IMAGE; tanggapan Pemilik: S-2 setuju (`Log::error` disk+path, ditambah `'report' => true`), S-3 cukup test, S-1 menjadi task terpisah (D-38), S-4 hanya catatan.
**Sifat perubahan:** konfigurasi dan logging saja. Respons, endpoint, kode error, dan skema tidak berubah (disk S3 tetap `'throw' => false`).

## Perubahan

| Berkas | Perubahan |
|---|---|
| `config/filesystems.php` | Disk S3 (`private`, `public`) mendapat `'report' => true`: galat dasar S3 yang ditelan `throw=false` tetap dilaporkan ke log (Laravel 11.56.1, `FilesystemAdapter::shouldReport`). |
| `app/Services/Upload/UploadService.php` | Saat `put() === false`, `Log::error('Gagal menyimpan file upload ke storage', ['disk', 'path'])` sebelum `RuntimeException` yang sama seperti sebelumnya. Isi file dan kredensial tidak dicatat. |
| `tests/Feature/Upload/StorageDiskConfigTest.php` | 3 test baru (di bawah). |

## Test baru
1. Semua disk S3 memiliki `report => true` dan tetap `throw => false`.
2. Kegagalan `put()` (endpoint lokal yang menolak koneksi): `Log::error` berisi persis `disk` dan `path`; exception Flysystem (`UnableToWriteFile`) ikut dilaporkan; tidak ada log, pesan, atau exception berantai yang memuat `kunci-uji` atau `rahasia-uji`.
3. S-3, test karakterisasi: `STORAGE_PUBLIC_DRIVER=s3` dengan `AWS_PUBLIC_URL` kosong membuat `url` null, sehingga URL logo dibentuk dari endpoint S3 API (bukan URL publik). Ini kasus yang harus dihindari lewat konfigurasi: `AWS_PUBLIC_URL` wajib diisi bersama `STORAGE_PUBLIC_DRIVER=s3` (catat di DEPLOY-DOC-001). Tidak ada penjaga saat boot (keputusan: cukup test dan dokumentasi); bila penjaga ditambahkan, test ini diganti dengan pengujian penjaganya.

## Batas bukti (kebocoran kunci)
Test 2 membuktikan tidak ada kebocoran pada galat koneksi. Galat S3 jenis lain (mis. `SignatureDoesNotMatch`, 403) belum dibangkitkan di test. Rahasia (secret key) tidak pernah dikirim dalam request sehingga tidak muncul di pesan galat; access key ID dapat muncul pada sebagian badan respons galat S3, dan Guzzle memotong ringkasan badan respons. Bila ingin kepastian untuk jenis galat itu, cek log di Preview pada kegagalan unggah yang disengaja.

## Tidak berubah
`throw => false`, bentuk respons, `RuntimeException` dan pesannya, disk lokal, S-1 (task terpisah), S-4 (catatan).
