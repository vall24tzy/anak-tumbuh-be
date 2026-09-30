# TEST-001 — Traceability: Acceptance Criteria vs Cakupan Test

Dokumen ini memetakan setiap item AC (Task Board v23, Dokumen v4.0 §14) ke salah satu dari:
- **[Postman]** — ada request otomatis di `TEST-001-ANAKTUMBUH-Postman-Collection.json`, folder disebutkan.
- **[Code Review]** — sudah diverifikasi benar lewat pembacaan kode langsung (bukan run), karena tidak
  praktis/tidak mungkin diuji lewat HTTP request tunggal di Postman.
- **[Manual/Env]** — butuh setup di luar kemampuan satu request Postman (file besar, concurrency,
  worker queue, rate-limit timing) — dicatat caranya, tapi PERLU dijalankan manual oleh Anda/tim.

**PENTING:** Seluruh isi dokumen ini (termasuk kolom "Code Review") adalah hasil pembacaan kode
manual di sandbox tanpa PHP/PostgreSQL live — BUKAN hasil eksekusi nyata. "Code Review: OK" berarti
"logikanya secara pembacaan kode sudah benar", bukan "sudah dites jalan". Postman Collection-nya
sendiri belum pernah dijalankan sama sekali.

| # | Item AC | Cakupan | Detail |
|---|---|---|---|
| 1 | Authorization seluruh role/scope | [Postman] | Folder 1-7, tiap domain ada minimal 1 request per-role-ditolak |
| 2 | Cross-school & cross-rombel isolation | [Postman] | Folder 1 (Teacher B akses Student A → 404), Folder 3 (bulk-graduate rombel lain → 403) |
| 3 | Teacher own active rombel | [Postman] | Semua folder 1-6 |
| 4 | Duplicate & conflict assignment (409, no auto-move) | [Postman] | Folder 2 |
| 5 | Existing NISN reuse | [Manual/Env] | Request Folder 1 ("NISN sama") hanya menguji **409 duplicate_assignment** (rombel & tahun sama), BUKAN reuse. Skenario reuse murni butuh 2 Academic Year (bootstrap cuma 1) — belum tercakup di Postman |
| 6 | PATCH Student hanya fullname/gender/photo, protected_field | [Postman] | Folder 1 |
| 7 | Inactive/graduated/bulk-graduation scope + retention + login ditolak | [Postman] | Folder 3 — bulk-graduate kini menguji Student D (aktif) → `total_students=1`, `graduated_count=1`; panggilan kedua → 0 (idempotent). Login QR Student inactive: PHPUnit `QrTokenTest` |
| 8 | Unique Student+Habit+Date (concurrency) | [Postman] utk single-request; **[Manual/Env]** utk concurrency | Constraint DB (partial/unique index) sudah diverifikasi ada lewat code review migration `student_habits`; race-condition test (2 request bersamaan persis) butuh tool load-test (k6/Postman Runner paralel), tidak disertakan sebagai 1 request biasa |
| 9 | habit_date Asia/Jakarta, tanggal lampau locked | [Code Review] | `StudentHabitService` pakai `now('Asia/Jakarta')->toDateString()` server-side, tidak menerima `habit_date` dari client — tidak ada mekanisme "submit utk tanggal lampau" sama sekali (bukan di-reject, tapi memang tidak ada endpoint yg terima tanggal manual) |
| 10 | Max 7/hari | [Code Review] | Cuma ada 7 habit FIXED (seed), unique constraint (student,habit,date) otomatis membatasi 1 entry/habit/hari → maksimal 7 entry/hari by construction, tidak perlu validasi eksplisit terpisah |
| 11 | Student-only write, Teacher monitor-only, HM 403 | [Postman] | Folder 4 — Teacher POST habit → 403, Headmaster GET → 403. (Teacher POST `/bermasyarakat/entries` tidak punya request sendiri; route memakai middleware `role:student` yang sama) |
| 12 | note optional max 1000 | [Code Review] | `StoreHabitEntryRequest`/`StoreBermasyarakatEntryRequest` rule `'note' => ['nullable','string','max:1000']` — tidak dibuat request khusus (validasi generik Laravel, risiko rendah) |
| 13 | description ditolak (field_not_accepted) | [Postman] | Folder 4 |
| 14 | Seed 28 baris persis (nama, point 50/25/15/10, Bermasyarakat NULL, Tidur Cepat Tier3) | [Postman] | Folder 0 (GET /habits) |
| 15 | initiative wajib 7 habit, kosong/invalid → 422 | [Postman] | Folder 4 (tanpa initiative → 422); nilai invalid (bukan mandiri/disuruh) tidak ada request terpisah — pola sama dgn enum invalid lain, risiko rendah |
| 16 | Formula 6 habit (tier+initiative) | [Postman] | Folder 4 (tier1+mandiri=60) — kombinasi lain (tier2/3/4 x mandiri/disuruh) tidak semua dibuat request terpisah, formulanya linear & seragam per code review (`indicator.point + (initiative==='mandiri'?10:0)`) |
| 17 | Formula Bermasyarakat (3/2/1/Kurang, tidak SUM) | [Postman] | Folder 4 (3 positif+mandiri=60); 2/1/Kurang tidak semua dibuat request terpisah, sama alasan #16 |
| 18 | Kurang bermasyarakat exclusive | [Postman] | Folder 4 |
| 19 | >3 positif ditolak | [Code Review] + [Postman, parsial] | Hanya ada 3 indikator positif dan rule `distinct` menolak id ganda, jadi cabang `positive->count() > 3` di `StudentHabitService` tidak bisa dicapai lewat API. Postman menguji id duplikat → 422 (distinct), bukan >3 positif |
| 20 | array kosong ditolak | [Postman] | Folder 4 |
| 21 | students.poin bertambah atomik | [Code Review] | `StudentHabitService` pakai `DB::transaction()` + `$student->increment('poin', ...)` — atomik di level DB, tidak ada request Postman terpisah utk verifikasi ini (butuh cek race condition, sama seperti item #8) |
| 22 | classroom snapshot terisi, NULL bila tidak ada assignment, tidak berubah setelah pindah rombel | [Postman, parsial] | Folder 4 verifikasi "terisi"; skenario "tidak berubah setelah pindah rombel" butuh submit habit → pindah assignment → submit habit lagi → bandingkan classroom snapshot baris pertama masih sama — TIDAK dibuat requestnya (perlu >10 request tambahan utk 1 skenario), catat sbg **[Manual/Env]** |
| 23 | poin_awarded di response tapi tidak dipersist | [Code Review] | `HabitEntryResource`/`BermasyarakatEntryResource` hitung `poin_awarded` on-the-fly dari `indicator.point`+`initiative`, bukan baca kolom DB (tidak ada kolom `poin_awarded` di migration `student_habits`) |
| 24 | QR (invalid/regenerated/rate-limit/inactive) | [Postman, parsial] + [PHPUnit] | Folder 5 — invalid, regenerated (payload lama 401), payload dipalsukan, Teacher B 403, id tidak ada 404. Rate-limit: catatan manual (butuh Runner 6x request cepat). **Skenario login QR Student inactive TIDAK ada request Postman** (setelah inactive, assignment aktif ditutup sehingga Teacher tidak bisa regenerate lagi — payload harus diambil SEBELUM inactive); skenario ini tercakup di PHPUnit `QrTokenTest::login_qr_untuk_student_inactive_ditolak_403` |
| 25 | Login username+password NISN/NISN | [Postman] | Folder 1 |
| 26 | Reset password Teacher→Student, SA & HM ditolak | [Postman] | Folder 1 |
| 27 | Change-own-password Student → 403 | [Postman] | Folder 1 |
| 28 | Import (invalid/>5MB/>1000 baris/unknown rombel/partial/errors[]/existing NISN/queue >100/revalidasi/rombel lain ditolak/.xlsx saja) | [Postman, parsial] | Folder 6 — semua ada requestnya, TAPI file besar (>5MB, >1000 baris, 150 baris) perlu Anda generate sendiri di luar Postman (openpyxl/pandas script) dan isi manual ke formdata `src`, diberi catatan eksplisit di tiap request |
| 29 | Dashboard student/teacher sesuai scope | [Postman] | Folder 7 |
| 30 | Weekend/holiday tetap diterima | [Code Review] | Tidak ada validasi hari (`Carbon::dayOfWeek` atau sejenis) di manapun pada `StudentHabitService`/`StoreHabitEntryRequest`/`StoreBermasyarakatEntryRequest` — dipastikan lewat pembacaan kode langsung, bukan ada test eksplisit yang "membolehkan", karena memang tidak ada mekanisme block sama sekali |

## Ringkasan
- **86 request** di Postman Collection, mencakup 7 domain modul Orang 1 secara end-to-end (termasuk bootstrap data Folder 0).
- **3 item** butuh setup manual tambahan di luar Postman (concurrency race #8/#21, file besar #28, rate-limit timing #24) — sudah diberi catatan jelas di request/dokumen ini, bukan diam-diam dilewati.
- **6 item** diverifikasi lewat pembacaan kode (bukan request HTTP) karena sifatnya "pembuktian ketiadaan sesuatu" (tidak ada validasi X) atau "mekanisme internal" (transaksi atomik, computed field) yang lebih valid dibuktikan dari kode daripada dari 1 response HTTP.
- **Status keseluruhan: BELUM DIJALANKAN.** Ini deliverable "siap dites", bukan laporan "sudah lulus tes".

## Catatan Revisi Collection (27 Sep 2026, hasil review terhadap kode)

Tiga cacat ditemukan saat collection dicocokkan dengan kode (bukan dijalankan) dan sudah diperbaiki di versi ini; jumlah request saat itu 84:

1. **Variabel `student_a_new_nisn` sama dengan `student_a_nisn` (1000000001).** `StudentService::store()` menganggapnya NISN existing dan me-reuse identitas Student A (yang sudah inactive, password sudah direset) tanpa mengubah status/password — akibatnya "Login Student baru" (Folder 4) gagal dan Folder 4/5/7 memakai Student yang salah. Diganti `1000000003`.
2. **Folder 3: `graduate` dijalankan pada Student A yang sudah inactive.** `StudentLifecycleService::transition()` menutup assignment aktif saat inactive, sehingga graduate berikutnya oleh Teacher A mendapat 403 (bukan 200). Diperbaiki: request "Login QR ... inactive" (yang sebenarnya memanggil regenerate, bukan login QR) diganti menjadi "Create Student C"; `graduate` dan GET-after-graduated kini memakai Student C, dan GET kini menegaskan `status: graduated`.
3. **Folder 0: kredensial Super Admin** diisi dari seeder (`superadmin1`).

Catatan menjalankan: tiga route login (`login/staff`, `login/student/password`, `login/student/qr`) sama-sama `throttle:5,1` dan **berbagi satu counter per IP** (Laravel v11.56.1: kunci = domain|ip untuk request tanpa login). Satu pass Runner melewati batas 5 login/menit; jalankan per folder dan bersihkan counter dengan `php artisan cache:clear` sebelum Folder 1, 3, 4, dan 5.

## Catatan Revisi Collection (28 Sep 2026, review lanjutan terhadap kode)

Jumlah request naik 84 → 86. Empat perbaikan:

1. **Folder 3, bulk-graduate tidak menguji apa-apa.** Saat itu Rombel A tidak punya Student aktif (A inactive, C graduated), jadi `graduated_count` = 0 di kedua panggilan. Ditambah request "Create Student D"; request bulk-graduate pertama kini meng-assert `total_students=1` dan `graduated_count=1`.
2. **Folder 4, ">3 indikator positif" tidak tercapai.** Request itu sebenarnya menguji exclusive (mengulang #55). Diganti menjadi uji id duplikat (422, rule `distinct`), dan klaim AC #19 dikoreksi.
3. **Folder 4, ditambah "Teacher POST habit → 403"** (sebelumnya klaim AC #11 tidak punya request Teacher-write).
4. **Folder 1, request "NISN sama" di-rename** agar sesuai isinya (409 duplicate_assignment, bukan reuse); AC #5 dipindah ke [Manual/Env].

Variabel baru: `student_d_nisn` (1000000005), `student_d_id`. Status tetap: **BELUM DIJALANKAN**.
