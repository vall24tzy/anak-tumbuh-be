# API Contract v1.2 — Addendum: field `qr_regenerated_at` pada `GET /students` (INTEG-QR-001)

**Tanggal:** 20 Okt 2026 · **Dasar keputusan:** Dokumen Keputusan v4.0 (amandemen 20 Okt 2026, butir g) · Authorization Matrix v5.3 baris **#69**.
**Status:** kode, test PHPUnit, dan request Postman sudah dijalankan (lihat `docs/team-log/INTEG-QR-001.md`).

## 1. Perubahan

`GET /api/v1/students` (list) menambah **satu** field pada tiap elemen `data`, **hanya bila pemanggil berperan Teacher**:

| Field | Tipe | Arti |
|---|---|---|
| `qr_regenerated_at` | string ISO 8601 UTC (contoh `2026-10-20T03:27:47Z`) atau `null` | Waktu terakhir QR siswa diregenerasi (`qr_tokens.regenerated_at`). `null` bila belum pernah diregenerasi. Terisi setelah `POST /qr-tokens/{student_id}/regenerate`. |

Tidak ada endpoint baru, tidak ada kolom/tabel baru. "Status akun" sudah ada lewat field `status` (`active` / `inactive` / `graduated`) dan tidak berubah.

## 2. Aturan

- Hanya **Teacher** (scope tetap own active rombel). Super Admin, Headmaster, dan Student **tidak** menerima key ini sama sekali (key tidak ada, bukan `null`).
- Hanya pada **list**. `GET /students/{id}` dan `POST /students` **tidak** memuat field ini.
- Field lama (`id`, `fullname`, `nisn`, `gender`, `photo`, `poin`, `status`, `school`, `username`) dan `meta` pagination tidak berubah. Scope per role, urutan (`id` naik), dan pagination tidak berubah.
- Tidak ada N+1: relasi `qrToken` di-eager-load pada query list Teacher (jumlah query sama untuk 2 maupun 8 siswa; diuji).

## 3. Kode

`StudentListResource` (baru, turunan `StudentResource`), `StudentController::index()` (memakai `StudentListResource`), `StudentService::indexQueryForTeacher()` (eager load `qrToken`). `StudentResource` sengaja **tidak diubah** karena juga dipakai `show` dan `store`.

## 4. Test

`tests/Feature/Student/StudentListQrRegeneratedAtTest.php` (8 test): null sebelum regenerate; terisi (format ISO 8601 UTC, sama dengan nilai DB) setelah regenerate dan hanya siswa itu; `show` tanpa field; field lama utuh; Headmaster/Super Admin/Student tanpa field; lintas rombel dan lintas sekolah (Teacher hanya melihat siswa rombelnya, regenerate siswa rombel lain 403 dan tidak mengubah apa pun); tanpa N+1; tanpa token 401.
Postman: folder `INTEG-O1` di `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` (7 request).

## 5. Untuk penggabungan ke API Contract v1.3 (INTEG-DOC-001)

Tambahkan `qr_regenerated_at` (khusus Teacher, list saja) pada skema respons `GET /students` di §17.
