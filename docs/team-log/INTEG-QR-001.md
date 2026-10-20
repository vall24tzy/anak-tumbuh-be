# INTEG-QR-001 — Log

**Status:** Selesai di kode, test, dan Postman (TODO -> DONE); menunggu tinjau silang Orang 2 (menyentuh resource bersama).
**PIC:** Orang 1
**Tanggal:** 20 Okt 2026
**Dasar:** Matrix v5.3 #69; Dokumen Keputusan v4.0 amandemen 20 Okt 2026 (g); Task Board Tambahan Integrasi FE v1.5.

## Ringkasan

- Field `qr_regenerated_at` (ISO 8601 UTC, `null` bila belum pernah regenerate) pada `GET /students` (list), **hanya Teacher**. Sumber: `qr_tokens.regenerated_at` (sudah ada; tanpa migration).
- `StudentListResource` baru (turunan `StudentResource`) dipakai hanya oleh `StudentController::index()`. `StudentResource` tidak diubah supaya `GET /students/{id}` dan `POST /students` tidak ikut memuat field.
- `StudentService::indexQueryForTeacher()`: eager load `qrToken` (tanpa N+1).
- Role lain (Super Admin, Headmaster, Student): key tidak ada. Field lama, scope, urutan, pagination tidak berubah.
- Addendum: `docs/addendum/API-Contract-v1.2-Addendum-QR-Regenerated-At.md`.

## File

- Baru: `app/Http/Resources/Student/StudentListResource.php`, `tests/Feature/Student/StudentListQrRegeneratedAtTest.php`, `docs/addendum/API-Contract-v1.2-Addendum-QR-Regenerated-At.md`, `docs/team-log/INTEG-QR-001.md`
- Diubah: `app/Http/Controllers/V1/Student/StudentController.php` (index memakai StudentListResource), `app/Services/Student/StudentService.php` (eager load qrToken, satu baris), `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` (hanya menambah folder INTEG-O1; request lama tidak diubah)

## Test (dijalankan)

Lingkungan penyusun: PHP 8.3.6, PostgreSQL 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1), Laravel dari `vendor/` repo (bukan lingkungan tim: baseline tim memakai PostgreSQL 17.11).

- `php artisan test`: **526 passed (2864 assertions)** = baseline 518/2814 + 8 test baru (50 assertion); termasuk MailExposureGuardTest dan FrameworkAdvisoryGuardTest.
- Uji mutasi: eager load dihapus sementara -> test N+1 gagal (16 query vs 10), lalu dikembalikan.
- Postman (newman, server lokal, cache array): folder 0-4 lama + INTEG-O1 dalam satu run = 78 request, 127 assertion, **0 gagal**. INTEG-O1: 7 request (regenerate -> list terisi ISO 8601; show tanpa field; Teacher B tidak melihat siswa Teacher A; Headmaster dan Super Admin tanpa field; tanpa token 401).

## Batas yang perlu diketahui

- Folder 5 (QR Login) **tidak** dijalankan ulang di sini: request rate limit F-23-nya butuh cache yang menyimpan antar-request, sedangkan run ini memakai cache array. INTEG-O1 tidak bergantung pada folder 5. Jalankan folder 5 dan INTEG-O1 di environment tim seperti biasa (per folder, `php artisan cache:clear` sebelum folder 1, 4, 5).
- Skenario Student dan lintas sekolah ada di PHPUnit, bukan di Postman (Postman koleksi ini tidak punya sekolah kedua).
- Hasil Runner belum disimpan di repo (D-12: dikerjakan INTEG-TEST-001).

## Tinjau silang (Orang 2)

Periksa: `StudentResource` tidak berubah; field hanya di list; tidak bocor ke role lain.
