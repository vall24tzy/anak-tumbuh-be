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
sendiri sudah dijalankan Orang 1 (7-8 Okt 2026; laporan: Folder 0-8 PASS, 0 Failed; hasil run tidak tersimpan di file ini). Kolom "Code Review" tetap hasil pembacaan kode, bukan hasil run.

| # | Item AC | Cakupan | Detail |
|---|---|---|---|
| 1 | Authorization seluruh role/scope | [Postman] | Folder 1-7, tiap domain ada minimal 1 request per-role-ditolak |
| 2 | Cross-school & cross-rombel isolation | [Postman] | Folder 1 (Teacher B akses Student A → 404), Folder 3 (bulk-graduate rombel lain → 403), Folder 6 (status batch import Teacher B → 404, F-32), Folder 8 (foto Student Teacher B → 404 F-06; Teacher A ke Rombel B → 404 F-21) |
| 3 | Teacher own active rombel | [Postman] | Semua folder 1-6 |
| 4 | Duplicate & conflict assignment (409, no auto-move) | [Postman] | Folder 2 |
| 5 | Existing NISN reuse | [Postman] (1 Academic Year) | Folder 1 "NISN sama, rombel & tahun sama" → 409 duplicate_assignment (jalur reuse, bukan 422 staf). **Baru:** Folder 2 (request 43-44) mengakhiri assignment lalu `POST /students` dengan NISN existing → **201, id Student sama, fullname tidak ditimpa**. Reuse LINTAS Academic Year (butuh 2 tahun ajaran) tetap belum tercakup di Postman |
| 6 | PATCH Student hanya fullname/gender/photo, protected_field | [Postman] | Folder 1 — assertion diperbaiki: `error.code = protected_field` dan `details[].reason = protected_field` (assertion lama memeriksa `message`, yang berisi kalimat, sehingga usang). Folder 8 menguji upload foto via `POST + _method=PATCH` (F-46) |
| 7 | Inactive/graduated/bulk-graduation scope + retention + login ditolak | [Postman] | Folder 3 — bulk-graduate menguji Student D (aktif) → `total_students=1`, `graduated_count=1`; panggilan kedua → 0 (idempotent). Login password Student inactive: kredensial BENAR → 403, kredensial SALAH → 401 (request 47-48). Snapshot `GET /dashboard/teacher` (request 56) membuktikan atribusi inactive/graduated (F-41). Login QR Student inactive: PHPUnit `QrTokenTest` |
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
| 24 | QR (invalid/regenerated/rate-limit/inactive) | [Postman, parsial] + [PHPUnit] | Folder 5 — invalid, regenerated (payload lama 401 `unauthorized`), payload dipalsukan, Teacher B 403, id tidak ada 404. **Rate limit (F-23):** request 80 kini punya assertion nyata (pre-request mengirim 5 percobaan, request utama = ke-6 → **429 `rate_limit_exceeded`**); hanya counter PER IP yang bisa dibuktikan dari satu IP, counter per-identifier independen terbukti di PHPUnit `LoginRateLimitTest`. **Skenario login QR Student inactive TIDAK ada request Postman** (setelah inactive, assignment aktif ditutup sehingga Teacher tidak bisa regenerate lagi) → PHPUnit `QrTokenTest` |
| 25 | Login username+password NISN/NISN | [Postman] | Folder 1 |
| 26 | Reset password Teacher→Student, SA & HM ditolak | [Postman] | Folder 1 — **F-29 (baru):** setelah reset, token Student LAMA → 401 (request 30), password lama (NISN) → 401 (31), password baru → 200 (32) |
| 27 | Change-own-password Student → 403 | [Postman] | Folder 1 |
| 28 | Import (invalid/>5MB/>200 baris/rombel tidak ada vs di luar scope/partial/errors[]/existing NISN/**sinkron >100 baris**/revalidasi/rombel lain ditolak/.xlsx saja/xlsx rusak/header hilang/trim/pemilik batch) | [Postman, parsial] | Folder 6 — semua ada requestnya, TAPI semua file uji (valid, baris invalid, >5MB, 201 baris, 150 baris, .xls, xlsx rusak, tanpa kolom NISN, trim) perlu Anda buat di luar Postman (`gen_sample_xlsx.py`) dan isi manual ke formdata `src`. **F-38:** tidak ada lagi antrean/`status:pending`; request 96 memeriksa 200 + lima field langsung; request 97 membaca HASIL batch (`status:done`), bukan polling. **N-1:** `rombel_id` tidak ada → 422 `validation_failed`; ada tapi di luar scope → 403 `forbidden` (kode `unknown_rombel` tidak ada) |
| 29 | Dashboard student/teacher sesuai scope | [Postman] | Folder 7 + snapshot Folder 3 — `dashboard/student` memuat `gamification_level` (poin 120 → Level 2, sama dengan `GET /gamification/me`, F-40); `dashboard/teacher` mengikuti assignment TERAKHIR (F-41): snapshot deterministik active 0 / inactive 1 / graduated 2 |
| 30 | Weekend/holiday tetap diterima | [Code Review] | Tidak ada validasi hari (`Carbon::dayOfWeek` atau sejenis) di manapun pada `StudentHabitService`/`StoreHabitEntryRequest`/`StoreBermasyarakatEntryRequest` — dipastikan lewat pembacaan kode langsung, bukan ada test eksplisit yang "membolehkan", karena memang tidak ada mekanisme block sama sekali |
| 31 | F-53 NISN Student baru = username akun staf → 422 `nisn_conflicts_with_staff_username`; NISN Student lain tetap reuse | [Postman] | Folder 1 (request 26: NISN = NIP Teacher A → 422, tanpa data); kontrol reuse: request 25 (409 duplicate_assignment) dan Folder 2 (request 44: 201). Race window (akun staf dibuat di antara cek dan INSERT) dan preview import tidak mendeteksi bentrok: batas yang diketahui (baseline §12), PHPUnit |
| 32 | F-29 token dicabut setelah reset password | [Postman] | Folder 1 request 30-32 (lihat #26) |
| 33 | F-32 batch import hanya terbaca pemilik | [Postman] | Folder 6 request 97 (pemilik 200) dan 98 (Teacher B → 404) |
| 34 | F-33 .xlsx saja; F-34 xlsx rusak / header hilang; F-36 trim | [Postman] | Folder 6 request 83-84 (.txt, .xls preview), 91 (.xls commit), 92 (rusak → `import_file_unparseable`), 93 (header → `import_header_invalid`), 94-95 (trim). File uji perlu dibuat manual |
| 35 | F-40 gamification_level di dashboard/student real-time | [Postman] | Folder 7 request 99-100 |
| 36 | F-41 atribusi inactive/graduated = assignment terakhir | [Postman, parsial] + [PHPUnit] | Folder 3 request 56 dan Folder 7 request 101. Skenario "pindah A→B lalu inactive di B dihitung di B" tidak bisa dibuat dari scope Teacher A saja → PHPUnit `DashboardTeacherAttributionTest` |
| 37 | F-09/F-11 nama School > 150 dan duplikat → 422 | [Postman] | Folder 8 request 104-105 (di luar cluster Siswa & Kebiasaan; tambahan) |
| 38 | F-15/F-10 paginasi 6 endpoint list: `{data, meta}`, default 20, `per_page` maks 100 (101 → 422), `page=0` → 422, halaman di luar rentang → data kosong | [Postman] | Folder 8 request 106-115 (teachers, rombels, academic-years, teacher-class-assignments, schools, headmasters). `GET /students` dan `GET /student-class-assignments` TIDAK dipaginasi (gap terpisah, tidak diuji sebagai paginasi) |
| 39 | F-18 satu Rombel maksimal satu Teacher aktif per tahun → 409 `rombel_already_has_active_teacher` | [Postman] | Folder 8 request 116 (pindahkan assignment Teacher B ke Rombel A) |
| 40 | F-21 Teacher hanya melihat Rombel dari active assignment-nya; di luar itu 404 | [Postman] | Folder 8 request 117-119 |
| 41 | F-06 foto Student privat: URL endpoint terautentikasi; tanpa token 401; di luar scope 404; header private/no-store/nosniff | [Postman, parsial] | Folder 8 request 120-124. **Tidak tercakup:** command migrasi foto lama ke disk privat pada data nyata (staging/produksi) — bukan perilaku HTTP |
| 42 | F-23 dua counter rate limit login independen | [Postman, parsial] + [PHPUnit] | Lihat #24. Counter per-identifier dari IP berbeda hanya di PHPUnit |

## Ringkasan
- **127 request** di Postman Collection (revisi Okt 2026; sebelumnya 86; +3 request pagination 10 Okt 2026), mencakup 7 domain modul Orang 1 secara end-to-end (termasuk bootstrap data Folder 0) plus Folder 8 (regresi lintas-modul Stage 1-5 + F-53).
- **2 item** butuh setup manual tambahan di luar Postman (concurrency race #8/#21; pembuatan file uji import #28 dan foto #41). Rate-limit (#24) kini punya assertion otomatis (hanya counter per IP).
- **6 item** diverifikasi lewat pembacaan kode (bukan request HTTP) karena sifatnya "pembuktian ketiadaan sesuatu" (tidak ada validasi X) atau "mekanisme internal" (transaksi atomik, computed field) yang lebih valid dibuktikan dari kode daripada dari 1 response HTTP.
- **Status keseluruhan: DIJALANKAN Orang 1 (7-8 Okt 2026); laporan: Folder 0-8 PASS, 0 Failed.** Hasil run tidak tersimpan di repo, jadi angka itu laporan, bukan bukti tersimpan. Item [Code Review] dan [Manual/Env] di tabel tidak tercakup oleh run Postman.

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

## Catatan Revisi Collection (Okt 2026, sinkron dengan perilaku FINAL Stage 1-5 + F-53)

Sumber acuan: `docs/addendum/API-Contract-v1.2-Errata-Stage5.md` (§7, §9, §10, §17, §18/§28, §23/§25, §26) dan `docs/testing/BASELINE-STAGE-0C.md` §14. Setiap ekspektasi baru juga dicocokkan ke kode (`app/`), bukan hanya ke dokumen. Kode backend tidak diubah. Jumlah request 86 → 124. Daftar perubahan per request ada di `TEST-001-perubahan-per-request.md` (dokumen kerja patch, tidak masuk repo).

**Keamanan (F-30).** Request 1 tidak lagi memuat password Super Admin; memakai `{{sa_username}}` dan `{{sa_password}}` (type secret, value kosong di file). Password lama tetap ada di riwayat git (7396a1a dan seeder 25cc67f); riwayat TIDAK ditulis ulang; rotasi di semua lingkungan dikoordinasikan pemilik project. Jangan commit environment/export yang berisi secret.

**Assertion LAMA yang usang (a), sudah diperbaiki, bukan dilonggarkan** (nomor = nomor BARU di collection ini):
1. Request 22/23: memeriksa `details[].message === 'protected_field'`; di kode `message` berisi kalimat, nilai `protected_field` ada di `details[].reason` dan `error.code`.
2. Request 96: mengharapkan `status:'pending'` (antrean async) — F-38 menghapus antrean; kini 200 sinkron tanpa field status.
3. Request 97: polling status antrean — kini membaca hasil batch (`status:done`).
4. Request 80: `catatan manual` tanpa assertion — kini assertion 429 nyata.

**Hal yang tidak berubah (diperiksa):** request 2 (`POST /schools` → 201), 11/12 (assignment Teacher ke Rombel berbeda tetap valid di bawah F-18), 47 dan 58 (nomor lama 41 dan 50; memakai password/token yang sah), tidak ada request yang memakai field `photo` sebelum Folder 8, tidak ada request yang membaca list School/Headmaster/Teacher/Academic Year/Rombel/Teacher Assignment sebagai setup (jadi tidak perlu `per_page=100`), `GET /students` tidak dipaginasi.

**Catatan menjalankan (login berbagi counter per IP, 5/menit).** Jumlah login per folder: Folder 0 = 4; Folder 1 = 3; Folder 3 = 2; Folder 4 = 1; Folder 5 = 4 (+ request 80 sengaja menghabiskan counter). Jalankan `php artisan cache:clear` sebelum Folder 1, 4, dan 5. Folder 8 tidak punya login. Setelah request 80 counter login habis ~1 menit.

**Tidak dapat diverifikasi otomatis di Postman:**
- Reuse NISN lintas Academic Year (butuh 2 tahun ajaran).
- Counter rate limit per-identifier yang independen (butuh IP berbeda) — PHPUnit.
- Konkurensi/race (F-03, F-11, F-16, F-18, F-53 race window) — simulasi di PHPUnit, bukan paralel nyata.
- F-38 waktu respons file besar: request 96 hanya mencatat waktu (console); file 200 baris (batas `ImportService::MAX_ROWS`) dengan `BCRYPT_ROUNDS` produksi diukur manual.
- F-06 migrasi foto lama pada data nyata.
- Pre-request script request 80 (`pm.sendRequest` berantai): tidak ada log terpisah; ikut berjalan dalam run Folder 5 menurut laporan Orang 1.

Status tetap: **BELUM DIJALANKAN**.

## Catatan Revisi (batas import 200 baris)

Commit `082d5d0` menurunkan batas baris import dari 1.000 ke 200 (keputusan pemilik setelah pengukuran F-38). Perubahan di TEST-001:

1. Folder 6, request "file > 1.000 baris" menjadi "file 201 baris (satu di atas batas 200)". File uji `gen('3004', 1001)` di `gen_sample_xlsx.py` diganti 201 baris, nama file `file-201-baris.xlsx`. Ditambah assertion pesan 422 menyebut `200`.
2. Teks "1.000 baris" di baris AC #28 dan catatan F-38 diganti 200.
3. Batas tepat 200 baris untuk Student Import belum punya request Postman (TEST-002 14.01/14.02 hanya memakai `POST /teachers/import`); status: belum tercakup, bukan diklaim lulus.

Status tetap: request yang berubah (satu request di Folder 6) BELUM DIJALANKAN ulang.

## Catatan Status (8 Okt 2026)

Pernyataan "BELUM DIJALANKAN" di catatan revisi di atas bersifat historis (benar saat ditulis) dan sudah tidak berlaku:

1. Orang 1 menjalankan Folder 0-8 lewat Postman Runner pada DB hasil `migrate:fresh --seed`; laporan: semua PASS, 0 Failed. Hasil run tidak tersimpan di file, jadi tidak dapat diverifikasi dari repo.
2. Request Folder 6 "file 201 baris" dijalankan manual setelah batas 200 baris: 422 `validation_failed`, pesan "File melebihi batas maksimal 200 baris", 3/3 test lulus.
3. Nama request #96 diubah dari "(<=1000)" menjadi "(<=200)" (hanya nama; assertion tidak berubah).
4. Password Super Admin lama ("ganti_ini_di_production") sudah tidak ada di tree. Nilai itu masih ada di riwayat git (commit 25cc67f seeder, 7396a1a collection) dan dianggap bocor; rotasi di environment mana pun yang pernah memakainya adalah keputusan pemilik, bukan bagian file ini.


## Catatan Revisi (pagination C-02, 10 Okt 2026)
`GET /students` dan `GET /student-class-assignments` kini dipaginasi (lihat `docs/addendum/API-Contract-v1.2-Addendum-Pagination-Students.md`); kalimat "`GET /students` tidak dipaginasi" pada catatan revisi Okt 2026 di atas adalah riwayat. Perubahan collection: (1) `GET /students - Teacher A` menambah asersi `meta`; (2) `GET /students - hasil import trim` memakai `?per_page=100` supaya NISN uji tidak tergeser ke halaman 2; (3) tiga request baru di Folder 8: `GET /students?per_page=101` -> 422, `GET /student-class-assignments?per_page=1` -> 200 + meta, `GET /student-class-assignments?per_page=101` -> 422. Belum dijalankan di Postman.
