# INTEG-O1 — Hasil run koleksi Postman (INTEG-TEST-001)

**Tanggal:** 21 Okt 2026 · **Penyusun:** Orang 1 · **Koleksi:** `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` (folder `INTEG-O1`, 37 request)

## Ringkasan

- Folder **INTEG-O1: 37 request, 53 assertion, 0 gagal** (QR 7, REKAP 10, PANDUAN 20).
- Dijalankan setelah folder 0-8 dalam satu sesi Runner (variabel koleksi seperti `student_a_new_id` dan token dibawa dari folder sebelumnya; INTEG-O1 tidak bergantung pada folder 5-8).
- Hasil di bawah adalah run di **sandbox penyusun**, bukan run di environment tim: newman 6.2.3, server `php artisan serve`, PHP 8.3.6, PostgreSQL 16.15 (tim: 17.11), database baru (`migrate:fresh --seed`), `CACHE_STORE=array`. Isi file ini hanya nama request, kode HTTP, dan hasil assertion (tanpa token, password, atau isi respons).
- Jalankan ulang di environment tim sebagai konfirmasi akhir dan ganti file ini bila hasil berbeda.

## Rincian folder INTEG-O1

| No | Task | Request | HTTP | Assertion lulus |
|---|---|---|---|---|
| 1 | QR | POST /qr-tokens/{student_a_new_id}/regenerate - Teacher A -> 200 (menyiapkan qr_regenerated_at terisi) | 200 | 1/1 |
| 2 | QR | GET /students - Teacher A -> 200, tiap item punya qr_regenerated_at; siswa yang baru diregenerasi terisi ISO 8601 UTC | 200 | 3/3 |
| 3 | QR | GET /students/{student_a_new_id} - Teacher A -> 200, TANPA qr_regenerated_at (field hanya di list) | 200 | 2/2 |
| 4 | QR | GET /students - Teacher B (rombel lain) -> 200, tidak memuat siswa Teacher A | 200 | 2/2 |
| 5 | QR | GET /students - Headmaster -> 200, TANPA qr_regenerated_at | 200 | 2/2 |
| 6 | QR | GET /students - Super Admin -> 200, TANPA qr_regenerated_at | 200 | 2/2 |
| 7 | QR | GET /students - tanpa token -> 401 | 401 | 1/1 |
| 8 | REKAP | GET /habits/recap?rombel_id={rombel_a_id} - Teacher A (default 7 hari) -> 200, bentuk rekap + konsisten (siswa baru folder 4 ikut roster) | 200 | 4/4 |
| 9 | REKAP | GET /habits/recap - rentang 32 hari -> 422 validation_failed (maks 31 hari, D-07) | 422 | 1/1 |
| 10 | REKAP | GET /habits/recap - from setelah to -> 422 validation_failed | 422 | 1/1 |
| 11 | REKAP | GET /habits/recap - tanpa rombel_id -> 422 validation_failed | 422 | 1/1 |
| 12 | REKAP | GET /habits/recap?rombel_id={rombel_b_id} - Teacher A (rombel lain, sekolah sama) -> 403 | 403 | 1/1 |
| 13 | REKAP | GET /habits/recap?rombel_id={rombel_a_id} - Teacher B (rombel lain, sekolah sama) -> 403 | 403 | 1/1 |
| 14 | REKAP | GET /habits/recap?rombel_id=999999 - Teacher A (rombel tidak ada / sekolah lain) -> 404 not_found (D-11) | 404 | 1/1 |
| 15 | REKAP | GET /habits/recap - Headmaster -> 403 (Headmaster tidak monitor habit) | 403 | 1/1 |
| 16 | REKAP | GET /habits/recap - Super Admin -> 403 | 403 | 1/1 |
| 17 | REKAP | GET /habits/recap - tanpa token -> 401 | 401 | 1/1 |
| 18 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - Teacher A (sebelum menulis) -> 200, data array | 200 | 2/2 |
| 19 | PANDUAN | PATCH /rombels/{rombel_a_id}/habit-guides/{bangun_cepat} - Teacher A tulis panduan -> 200 | 200 | 2/2 |
| 20 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - Teacher A -> 200, memuat panduan yang ditulis | 200 | 2/2 |
| 21 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - Student rombel sendiri -> 200, memuat panduan | 200 | 2/2 |
| 22 | PANDUAN | PATCH /rombels/{rombel_a_id}/habit-guides/{bangun_cepat} - Student -> 403 (Student hanya baca) | 403 | 1/1 |
| 23 | PANDUAN | PATCH habit-guides - content 2001 karakter -> 422 validation_failed (maks 2.000) | 422 | 1/1 |
| 24 | PANDUAN | PATCH habit-guides - content memuat tag HTML -> 422 validation_failed (teks polos) | 422 | 1/1 |
| 25 | PANDUAN | PATCH habit-guides - tanpa key content -> 422 validation_failed (tidak menghapus) | 422 | 1/1 |
| 26 | PANDUAN | PATCH habit-guides/{habit_id tidak dikenal} - Teacher A -> 404 not_found | 404 | 1/1 |
| 27 | PANDUAN | PATCH habit-guides - content kosong -> 200 content null (hapus panduan, D-13) | 200 | 2/2 |
| 28 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - Teacher A setelah dihapus -> 200, panduan hilang | 200 | 2/2 |
| 29 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - Student setelah dihapus -> 200, panduan hilang | 200 | 2/2 |
| 30 | PANDUAN | PATCH /rombels/{rombel_a_id}/habit-guides/{bangun_cepat} - Teacher B (rombel lain, sekolah sama) -> 403 | 403 | 1/1 |
| 31 | PANDUAN | GET /rombels/{rombel_b_id}/habit-guides - Teacher A (rombel lain, sekolah sama) -> 403 | 403 | 1/1 |
| 32 | PANDUAN | GET /rombels/{rombel_b_id}/habit-guides - Student rombel A (rombel lain) -> 403 | 403 | 1/1 |
| 33 | PANDUAN | GET /rombels/999999/habit-guides - Teacher A (tidak ada / sekolah lain) -> 404 not_found (D-11) | 404 | 1/1 |
| 34 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - Headmaster -> 403 | 403 | 1/1 |
| 35 | PANDUAN | PATCH /rombels/{rombel_a_id}/habit-guides/{bangun_cepat} - Headmaster -> 403 | 403 | 1/1 |
| 36 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - Super Admin -> 403 | 403 | 1/1 |
| 37 | PANDUAN | GET /rombels/{rombel_a_id}/habit-guides - tanpa token -> 401 | 401 | 1/1 |

## Catatan folder lain

Folder 0-8 bukan bagian dari bukti task ini (D-12 hanya mewajibkan hasil folder INTEG). Pada run sandbox di atas, sebagian request folder 5, 6, dan 8 gagal karena keterbatasan sandbox: rate limit F-23 butuh cache persisten, dan upload memakai file placeholder `/PATH/KE/...`. Pada run di laptop tim tanggal 20 Okt 2026, folder 0 sampai 8 seluruhnya 0 gagal (screenshot disimpan pemilik di luar repo).
