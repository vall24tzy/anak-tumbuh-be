# INTEG-REKAP-001 — Log

**Status:** Selesai di kode, test, dan Postman (TODO -> DONE); menunggu tinjau silang Orang 2.
**PIC:** Orang 1
**Tanggal:** 20 Okt 2026
**Dasar:** Matrix v5.3 #62; Dokumen Keputusan v4.0 amandemen 20 Okt 2026 (b); D-07, D-10, D-11; Task Board Tambahan Integrasi FE v1.5.

## Ringkasan

- Endpoint baru `GET /api/v1/habits/recap?rombel_id=&from=&to=` (Teacher own active rombel, hanya baca). Metrik: persentase terisi per hari (entri / siswa x 7), total poin per siswa, terisi per kebiasaan (Bermasyarakat = satu kebiasaan). Maks 31 hari, default 7 hari terakhir (Asia/Jakarta).
- Role lain 403 (Headmaster tidak monitor habit); rombel lain di sekolah yang sama 403; rombel sekolah lain atau tidak ada 404 yang identik (D-11, tanpa `exists:rombels`).
- Roster = siswa active + assignment aktif sekarang; siswa inactive/graduated tidak masuk penyebut. Rekap tanggal lampau memakai roster sekarang (keterbatasan dicatat di addendum).
- Scoring memakai ulang `StudentHabitService::habitPoin()/bermasyarakatPoin()` (dijadikan `public`); tanpa formula kedua. Agregasi SQL `GROUP BY`, jumlah query tetap.
- Addendum: `docs/addendum/API-Contract-v1.2-Addendum-Habit-Recap.md`.

## Hal yang ditetapkan penyusun (tidak ada di SoT; mohon dikonfirmasi saat tinjau silang)

1. Path final `GET /habits/recap` dan bentuk respons (`rombel`, `from`, `to`, `student_count`, `per_day`, `per_student`, `per_habit`) — board hanya menyebut metrik, bukan bentuk JSON. FE guru perlu menyesuaikan.
2. `rombel_id` wajib (422 bila tidak ada). Hanya `from` -> `to` = hari ini; hanya `to` -> `from` = `to` - 6 hari.
3. `percentage` = angka 0-100, 2 desimal; 0 bila roster kosong. Tanggal masa depan tidak dibatasi.
4. Teacher aktif tanpa assignment: rombel sekolahnya 403, rombel lain/tidak ada 404.
5. Visibilitas `habitPoin` dan `bermasyarakatPoin` di `StudentHabitService` diubah dari `protected` ke `public` (satu-satunya perubahan pada file lama, tanpa perubahan perilaku).

## File

- Baru: `app/Http/Controllers/V1/Habit/HabitRecapController.php`, `app/Http/Requests/Habit/HabitRecapRequest.php`, `app/Services/Habit/HabitRecapService.php`, `tests/Feature/Student/HabitRecapTest.php`, `docs/addendum/API-Contract-v1.2-Addendum-Habit-Recap.md`, `docs/team-log/INTEG-REKAP-001.md`
- Diubah: `routes/v1/habit.php` (route baru), `app/Services/Student/StudentHabitService.php` (visibilitas dua method), `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` (menambah 10 request di folder INTEG-O1; request lama tidak diubah)

## Test (dijalankan)

Lingkungan penyusun: PHP 8.3.6, PostgreSQL 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1) (bukan lingkungan tim: baseline tim memakai PostgreSQL 17.11).

- `php artisan test`: **555 passed (3011 assertions)** = baseline 540/2937 (HEAD 852c2f1) + 15 test baru (74 assertion); termasuk MailExposureGuardTest dan FrameworkAdvisoryGuardTest.
- Uji mutasi (kode dipulihkan setelahnya): roster tanpa filter status siswa -> gagal; query Bermasyarakat tanpa filter rentang -> gagal (setelah data uji di luar rentang ditambahkan); N+1 (find indikator per baris) -> test jumlah query gagal.
- Postman (newman, server lokal, cache array): folder 0-4 lama + INTEG-O1 dalam satu run = 88 request, 140 assertion, **0 gagal**. INTEG-O1 REKAP (10 request): default 7 hari 200 + konsistensi, 422 x3 (32 hari, from>to, tanpa rombel_id), 403 lintas rombel x2, 404, 403 Headmaster, 403 Super Admin, 401.

## Batas yang perlu diketahui

- Folder 5 (QR Login) tidak dijalankan ulang di sandbox (request rate limit-nya butuh cache yang menyimpan antar-request); INTEG-REKAP tidak bergantung padanya. Jalankan folder 0-5 lalu INTEG-O1 di environment tim seperti biasa.
- Skenario lintas sekolah (404), Student 403, roster inactive/graduated, dan Teacher inactive ada di PHPUnit, bukan Postman (koleksi tidak punya sekolah kedua; token Student folder 1-3 tidak dijamin aktif). Di Postman, 404 diuji dengan `rombel_id=999999` (respons identik dengan sekolah lain, D-11).
- Performa 43.400 entri belum diukur di server (D-10: asumsi batas atas).
- Hasil Runner belum disimpan di repo (D-12: INTEG-TEST-001).

## Tinjau silang (Orang 2)

Periksa: scope (404/403) sesuai D-11, tidak ada `exists:rombels`, scoring tidak diduplikasi, perubahan `StudentHabitService` hanya visibilitas.
