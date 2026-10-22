# INTEG-PANDUAN-001 — Log

**Status:** Selesai di kode, test, dan Postman (TODO -> DONE); menunggu tinjau silang Orang 2 (menambah tabel baru).
**PIC:** Orang 1
**Tanggal:** 21 Okt 2026 (pembaruan 22 Okt 2026: D-19)
**Dasar:** Matrix v5.3 #63 dan #64; Dokumen Keputusan v4.0 amandemen 20 Okt 2026 (a); D-06, D-13; D-19 (pemilik, 22 Okt 2026); Task Board Tambahan Integrasi FE v1.5.

## Ringkasan

- Panduan kebiasaan teks polos per (rombel x kebiasaan), maksimal 2.000 karakter. `GET /rombels/{rombel_id}/habit-guides` (Teacher own active rombel, Student rombel sendiri) dan `PATCH /rombels/{rombel_id}/habit-guides/{habit_id}` (Teacher saja). `content` kosong/null/spasi menghapus panduan (D-13).
- Tabel baru `habit_guides` (UNIQUE rombel_id+habit_id, CHECK 1-2000 karakter, FK rombel ON DELETE CASCADE sejak D-19; migration awal RESTRICT diganti migration baru `2026_10_22_100000_habit_guides_rombel_fk_cascade`). Errata: `docs/addendum/DB-Design-v1.5-Errata-Habit-Guides.md`. **Wajib `php artisan migrate`.**
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
- Skenario lintas sekolah (404), Teacher inactive, Student tanpa rombel, penghapusan rombel (D-19), dan constraint DB ada di PHPUnit, bukan Postman (koleksi tidak punya sekolah kedua). Di Postman 404 diuji dengan `rombel_id=999999` (identik dengan sekolah lain, D-11).
- Request PANDUAN di Postman menulis lalu menghapus panduan bangun_cepat rombel A, jadi state akhir kosong dan aman diulang.
- `DELETE /rombels/{id}` (D-19): rombel tanpa assignment ikut menghapus panduannya; rombel dengan assignment guru/siswa (aktif maupun riwayat) tetap 409 `rombel_has_active_assignment` dan panduannya tetap ada. Perilaku ini dibuktikan di PHPUnit dan tidak ditambahkan ke Postman: tidak ada jalur API untuk membuat rombel berpanduan tanpa assignment (PATCH panduan butuh Teacher dengan assignment aktif, dan assignment tidak bisa dihapus lewat API), serta koleksi tidak punya rombel kosong siap pakai.
- Hasil Runner INTEG-O1 sudah disimpan di `docs/testing/INTEG-O1-runner-result.md` (INTEG-TEST-001, run laptop tim).

## Tinjau silang (Orang 2)

Periksa: migration awal (tipe `habit_id` smallint, CHECK), migration D-19 (hanya FK `rombel_id` menjadi CASCADE; `habit_id` tetap RESTRICT; `down()` mengembalikan RESTRICT), scope 404/403 sesuai D-11, tidak ada perubahan kode lama selain satu baris `routes/api.php`; D-19 tidak mengubah kode aplikasi selain migration.

## Pembaruan 22 Okt 2026 — D-19 (FK rombel_id menjadi CASCADE)

**Keputusan:** menghapus rombel ikut menghapus panduan rombel itu; rombel dengan assignment guru/siswa (aktif maupun riwayat) tetap ditolak 409 `rombel_has_active_assignment` oleh FK tabel assignment, dan panduannya tidak ikut terhapus; `habit_id` tetap RESTRICT. **Disetujui pemilik, 22 Okt 2026** (D-19). Dicatat di addendum PANDUAN §8 dan errata DB Design.

**Perubahan:**
- Baru: `database/migrations/2026_10_22_100000_habit_guides_rombel_fk_cascade.php` (`dropForeign(['rombel_id'])` lalu `cascadeOnDelete()`; `down()` kembali `restrictOnDelete()`). Nama constraint `habit_guides_rombel_id_foreign` diperiksa di database sebelum ditulis. Migration awal tidak diedit. Database yang sudah menjalankan migration awal wajib `php artisan migrate`.
- Test: `rombel_yang_punya_panduan_tidak_bisa_dihapus_restrict` diganti dua test di `HabitGuideTest`: `rombel_kosong_dengan_panduan_bisa_dihapus_dan_hanya_panduannya_yang_ikut_terhapus` (rombel kosong terhapus beserta panduannya, panduan rombel lain tetap) dan `rombel_dengan_panduan_dan_assignment_tetap_ditolak_409_dan_panduannya_tidak_terhapus` (empat kasus: assignment guru aktif, guru riwayat, siswa aktif, siswa riwayat; memakai savepoint karena error FK membatalkan transaksi test PostgreSQL).
- Docs: addendum PANDUAN (§6, §7, §8 baru, §9), errata DB Design (tabel, migration, dampak), team-log ini. Tidak ada perubahan kode aplikasi selain migration. Postman tidak diubah (alasan di bagian Batas).

**Test (dijalankan 22 Okt 2026):** PHP 8.3.6, PostgreSQL 16.15 (bukan lingkungan tim: PostgreSQL 17.11). Dasar kode HEAD 8d5ffcf.
- `php artisan test`: **623 passed (3502 assertions)**; baseline sebelumnya 622/3485. Selisih: satu test diganti dua test (+1), assertion +17. `MailExposureGuardTest` dan `FrameworkAdvisoryGuardTest` hijau (5 test, 43 assertion).
- `HabitGuideTest`: 20 test (sebelumnya 19).
- Migration: `up()` menghasilkan CASCADE, `down()` (`migrate:rollback --step=1`) kembali ke RESTRICT, `migrate` ulang kembali ke CASCADE; FK `habit_id` tetap RESTRICT pada ketiga keadaan.
- Uji mutasi (kode dipulihkan setelahnya): `up()` tetap `restrictOnDelete()` -> test rombel kosong gagal; migration baru dihapus -> test yang sama gagal.

**Catatan dampak (untuk pemilik dan tinjau silang):** panduan hanya bisa ditulis lewat API oleh Teacher dengan assignment aktif di rombel itu, dan assignment tidak bisa dihapus lewat API, sehingga rombel yang pernah diberi panduan lewat API selalu punya riwayat assignment guru dan tetap ditolak 409. Efek CASCADE praktis terjadi pada panduan di rombel yang tidak punya assignment sama sekali.
