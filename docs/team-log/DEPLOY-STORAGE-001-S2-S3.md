# DEPLOY-STORAGE-001 S-2/S-3 — Log

**Status:** kode, test, addendum selesai; 12 test lulus di PHPUnit (sandbox); suite penuh belum dijalankan.
**PIC:** Orang 1 · **Tanggal:** 16 Nov 2026 · **Cabang:** `deploy/vercel-storage-log` dari 28f4800.

## Dikerjakan
`'report' => true` di disk S3, `Log::error` (disk, path) di `UploadService`, 3 test baru (report, log dan tanpa kebocoran kunci, `AWS_PUBLIC_URL` kosong), addendum `docs/addendum/DEPLOY-STORAGE-001-S2-S3.md`.

## Diperiksa
- `report` didukung Laravel 11.56.1 (`FilesystemAdapter::shouldReport` membaca `config['report']`).
- `StorageDiskConfigTest`: 12 test, 59 assertion lulus (PHPUnit 10.5, PHP 8.3.6). Dua test S-2 gagal pada kode lama dan lulus pada kode baru.
- **Belum dijalankan:** suite penuh (`php artisan test`); sandbox tanpa PostgreSQL.

## Belum selesai
1. `php artisan test` penuh hijau di lokal (acuan: 674 passed di 47a4d44; 681 di deploy/vercel-import).
2. Review silang Orang 2.
3. Catat di DEPLOY-DOC-001: `AWS_PUBLIC_URL` wajib bersama `STORAGE_PUBLIC_DRIVER=s3`; S-1 (D-38, DEPLOY-STORAGE-003) dan S-4 sebagai catatan.

## Temuan
Pengujian kebocoran kunci hanya mencakup galat koneksi; galat S3 jenis lain belum dibangkitkan (lihat addendum).
