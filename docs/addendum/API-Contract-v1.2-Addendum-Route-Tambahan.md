# API Contract v1.2 — Addendum: Route Tambahan di Luar Daftar Endpoint (menutup A-1, A-2, A-3 MERGE-001)

**Tanggal:** 10 Okt 2026 · **Sumber:** dicek ke kode (`routes/`, controller, export), bukan dijalankan.
**Status:** didokumentasikan; tidak ada perubahan kode.

## 1. `GET /api/v1/teachers/import/template`

- **Role:** Headmaster saja (`jwt` + `role:headmaster`, `routes/v1/teacher-import.php`). Tidak membawa data sekolah.
- **Respons:** unduhan berkas `template_import_guru.xlsx` (Content-Type spreadsheetml), bukan envelope JSON.
- **Isi berkas:** header kolom `Nama Lengkap`, `NIP`, `Gender`, dan satu baris contoh
  (`Contoh Nama Guru`, `199001012020121001`, `laki-laki`).
- **Dasar:** Contract §15 menyatakan Teacher import "mengikuti pola Bagian 28"; Contract §18 hanya menyebut template
  untuk Student. Endpoint kembarnya, `GET /teachers/import/status/{batchId}`, sudah masuk errata §18/§28.
- **Teruji:** collection TEST-002 request 10.01 (status 200, Content-Type berisi `spreadsheetml`).

## 2. `GET /api/test`

- **Bukan endpoint bisnis.** Route diagnostik di `routes/api.php` (di luar prefix `v1`), middleware `jwt`
  (`AuthenticateActiveSession`), semua role terautentikasi, respons teks `ok`.
- **Dipertahankan sengaja** sebagai pemeriksa gerbang sesi (residual F-14: tidak boleh menjadi jalur bypass untuk akun
  inactive/graduated atau token usang). Diuji 7 test di `tests/Feature/Auth/ApiTestRouteGateTest.php`.
- **Keputusan (10 Okt 2026):** diterima apa adanya. Dihapus atau dipertahankan sebelum production ditinjau di SECURITY-001
  (kebersihan route); bila dihapus, 7 test itu disesuaikan dalam task yang sama.

## 3. Route infrastruktur (non-API)

- `GET /up`: health check bawaan framework (`bootstrap/app.php`).
- `GET /`: view `welcome` bawaan Laravel (`routes/web.php`).
- `GET /login`: JSON 401 `Unauthenticated.`, route bernama `login` sebagai target redirect autentikasi (`routes/web.php`).

**Keputusan (10 Okt 2026):** diakui sebagai infrastruktur, bukan endpoint bisnis, sehingga tidak perlu masuk Contract.
Catatan untuk SECURITY-001: halaman `/` masih view welcome bawaan; pertimbangkan dihapus atau dialihkan sebelum production.
