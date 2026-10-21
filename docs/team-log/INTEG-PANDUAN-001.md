# INTEG-PANDUAN-001 — Log

**Status:** Selesai di kode, test, dan Postman (TODO -> DONE); menunggu tinjau silang Orang 2 (menambah tabel baru).
**PIC:** Orang 1
**Tanggal:** 20 Okt 2026
**Dasar:** Matrix v5.3 #63 dan #64; Dokumen Keputusan v4.0 amandemen 20 Okt 2026 (a); D-06, D-13; Task Board Tambahan Integrasi FE v1.5.

## Ringkasan

- Panduan kebiasaan teks polos per (rombel x kebiasaan), maksimal 2.000 karakter. `GET /rombels/{rombel_id}/habit-guides` (Teacher own active rombel, Student rombel sendiri) dan `PATCH /rombels/{rombel_id}/habit-guides/{habit_id}` (Teacher saja). `content` kosong/null/spasi menghapus panduan (D-13).
- Tabel baru `habit_guides` (UNIQUE rombel_id+habit_id, CHECK 1-2000 karakter, FK rombel RESTRICT). Errata: `docs/addendum/DB-Design-v1.5-Errata-Habit-Guides.md`. **Wajib `php artisan migrate`.**
- Scope: Super Admin/Headmaster 403, Student PATCH 403; rombel lain di sekolah yang sama 403; rombel sekolah lain atau tidak ada 404 identik (D-11).
- Addendum: `docs/addendum/API-Contract-v1.2-Addendum-Habit-Guide.md`.

## Hal yang ditetapkan penyusun (tidak ada di SoT; mohon dikonfirmasi saat tinjau silang)

1. Path final dan bentuk respons: GET = daftar `[{habit_id, content}]` hanya panduan yang ada (tanpa pagination); PATCH selalu 200 `{habit_id, content}` (`content` null setelah hapus), tanpa 201. FE perlu menyesuaikan.
2. Key `content` wajib ada pada PATCH (422 bila hilang) supaya salah kirim tidak menghapus panduan.
3. Teks polos: tag HTML ditolak 422 (bukan dibersihkan diam-diam); markdown tidak diproses dan disimpan apa adanya.
4. Menghapus panduan yang tidak ada tetap 200 (idempoten). `habit_id` tidak dikenal 404.
5. Tidak ada audit_logs dan tidak ada rate limit (amandemen 20 Okt; D-04).
6. Logika scope 404/403 ditulis ulang di `HabitGuideService` (mirip `HabitRecapService`) agar patch ini tidak bergantung pada kode REKAP; scope Teacher tetap hanya dari `TeacherScopeService`. Jika Orang 2 ingin, penyatuan ke satu helper bisa jadi task kecil terpisah.

## File

- Baru: `app/Http/Controllers/V1/Habit/HabitGuideController.php`, `app/Http/Requests/Habit/UpdateHabitGuideRequest.php`, `app/Services/Habit/HabitGuideService.php`, `app/Models/HabitGuide.php`, `database/migrations/2026_10_20_100000_create_habit_guides_table.php`, `routes/v1/habit-guide.php`, `tests/Feature/Student/HabitGuideTest.php`, `docs/addendum/API-Contract-v1.2-Addendum-Habit-Guide.md`, `docs/addendum/DB-Design-v1.5-Errata-Habit-Guides.md`, `docs/team-log/INTEG-PANDUAN-001.md`
- Diubah: `routes/api.php` (satu baris require), `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` (menambah 20 request di folder INTEG-O1; request lama tidak diubah)

## Test (dijalankan)

Lingkungan penyusun: PHP 8.3.6, PostgreSQL 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1) (bukan lingkungan tim: baseline tim memakai PostgreSQL 17.11). Dasar kode: HEAD 976a55e (sudah memuat INTEG-REKAP-001 dan task Orang 2).

- `php artisan test`: **596 passed (3286 assertions)** = baseline 579/3163 + 17 test baru (123 assertion); termasuk MailExposureGuardTest dan FrameworkAdvisoryGuardTest.
- Uji mutasi (kode dipulihkan setelahnya): tanpa rule `present`, tanpa penghapusan saat kosong (D-13), tanpa cek HTML, rombel tanpa filter sekolah, scope rombel sendiri dilepas -> masing-masing membuat test gagal.
- Postman (newman, server lokal, cache array): folder 0-4 lama + INTEG-O1 dalam satu run = 108 request, 167 assertion, **0 gagal**. INTEG-O1 PANDUAN (20 request): tulis, baca Teacher+Student, Student tulis 403, 422 x3 (2001 karakter, HTML, tanpa content), 404 habit, hapus (content null), baca setelah hapus x2, 403 lintas rombel x3, 404 rombel, 403 Headmaster x2 dan Super Admin, 401.

## Batas yang perlu diketahui

- Folder 5-8 tidak dijalankan ulang di sandbox (request rate limit di folder 5 butuh cache persisten); PANDUAN hanya bergantung pada variabel folder 0-4. Jalankan seluruh koleksi di environment tim.
- Skenario lintas sekolah (404), Teacher inactive, Student tanpa rombel, RESTRICT rombel, dan constraint DB ada di PHPUnit, bukan Postman (koleksi tidak punya sekolah kedua). Di Postman 404 diuji dengan `rombel_id=999999` (identik dengan sekolah lain, D-11).
- Request PANDUAN di Postman menulis lalu menghapus panduan bangun_cepat rombel A, jadi state akhir kosong dan aman diulang.
- `DELETE /rombels/{id}` pada rombel yang punya panduan mengembalikan 409 dengan kode/pesan assignment yang kurang tepat (pemetaan generik yang sudah ada; tidak diubah).
- Hasil Runner belum disimpan di repo (D-12: INTEG-TEST-001).

## Tinjau silang (Orang 2)

Periksa: migration baru (tipe `habit_id` smallint, RESTRICT, CHECK), scope 404/403 sesuai D-11, tidak ada perubahan kode lama selain satu baris `routes/api.php`.
