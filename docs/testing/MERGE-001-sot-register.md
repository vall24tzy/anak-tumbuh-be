# MERGE-001 — Register SoT, audit endpoint, audit migrasi/seeder

**Basis:** HEAD `6974b29` (8 Okt 2026). **Metode: pembacaan kode statis** (tidak ada PHP/PostgreSQL/Postman di sandbox penyusun).
Setiap hasil di sini berstatus **[dicek ke kode]**, BUKAN "lulus dijalankan". Tidak ada kode, migration, seeder, route, Task Board xlsx,
maupun file Orang 2 yang diubah. Keputusan FINAL (F-49, F-18, F-38 + batas 200 baris, N-1/N-2/N-3, F-53, ranking `name`/`level`,
`/gamification/me` hanya Student, habit entry immutable) **tidak dibuka ulang** dan tidak dilaporkan sebagai temuan; yang dicatat hanya
selisih teks dokumen terhadap keputusan itu.

Hierarki SoT: Dokumen v4.0 > Authorization Matrix v5.2 > API Contract v1.2 > DB Design v1.5 > Task Board v23.
Errata resmi di repo: `docs/addendum/` dan `docs/testing/BASELINE-STAGE-0C.md` §14–§16.

---

## 0. Langkah 0 — dependency MERGE-001

| Dependency | Bukti di repo | Status |
|---|---|---|
| TEST-001 | `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` = 124 request (commit `d62dbb8`, assertion batas 200 baris `6974b29`). Literal password Super Admin sudah tidak ada di file yang dilacak git. | Collection ada. **Hasil Runner TIDAK tercatat di repo**; `docs/team-log/TEST-001.md` masih menulis "86 request" dan "BELUM DIJALANKAN" (teks usang, milik Orang 1). [perlu konfirmasi user] |
| TEST-002 | `docs/postman/TEST-002.postman_collection.json` = **218** request (baseline §15 menulis 217; selisih 1 = folder 14.02). PHPUnit 434/2136 dan Postman 712/712 hanya **dilaporkan** di baseline §16 (laporan Orang 2). | Ada. Angka run tidak dapat diverifikasi dari repo. |
| LEVEL-RANKING-001 | `docs/team-log/LEVEL-RANKING-001.md` (Selesai, revisi 3), commit `e153856`; route `gamification/me`, `students/{id}/gamification`, `rankings/rombel|angkatan` ada (Bagian A). | Terpenuhi. |
| AUTHORIZATION-MATRIX | `docs/team-log/AUTHORIZATION-MATRIX.md` + `docs/authorization-matrix.md` (61/61 baris); revisi F-49 `2c05904` terdokumentasi. | Terpenuhi. |
| Audit Stage 0–6 "tertutup" | Baseline §14–§16 mencatat seluruh F-01..F-53 **FIXED — VERIFIED**; label **CLOSED** formal baru tertulis untuk F-14 (CLOSED — OBSOLETE). 22 baris masih berbunyi "belum CLOSED" (mis. F-53, F-38, F-15/F-10, F-06). | Fix selesai; penutupan formal = keputusan pemilik setelah Runner. |
| "Semua task REWORK sudah revisi" (Task Board) | Task Board xlsx tidak diupdate (21 TODO, 10 REWORK, 1 DONE). Kode tidak lagi memakai skema/endpoint lama (Bagian A/B). | Terpenuhi di kode; Task Board usang (C-07). |

---

## A. Audit endpoint (DoD 4) — [dicek ke kode]

Sumber route: `routes/api.php` + `routes/v1/*.php` (parser statis, dicocokkan dengan hitungan manual: 69 route). Prefix efektif `/api` + `v1`.
Klasifikasi: **(a)** ada di API Contract v1.2 · **(b)** ditambahkan errata/addendum · **(c)** tidak ada di dokumen manapun.

**Ringkasan:** 69 route terparse → **(a) 64 · (b) 3 · (c) 2**. Endpoint kontrak tanpa route: **0 dari 64**.
Mismatch role vs Matrix v5.2: **0 pelanggaran**; 1 selisih teks vs Contract (`GET /gamification/me`, ditutup addendum; hak Matrix #38 tetap terpenuhi).
Di luar `routes/`: `GET /up` (health bawaan framework, `bootstrap/app.php`), `GET /` (view welcome) dan `GET /login` (JSON 401 untuk redirect auth) di `routes/web.php` — tidak ada di dokumen manapun (c-infra).

### Temuan Bagian A

| ID | Temuan | Bukti | Kelas | Pemilik |
|---|---|---|---|---|
| A-1 | `GET /api/test` (jwt, semua role) tidak ada di dokumen manapun. Sengaja dipertahankan dan di-gate (F-14 residual, `tests/Feature/Auth/ApiTestRouteGateTest.php`), tetapi tidak pernah didokumentasikan sebagai endpoint non-kontrak. | `routes/api.php:11` | (c) | Orang 2 / keputusan pemilik: hapus atau dokumentasikan sebagai endpoint teknis | **DITUTUP 9 Okt 2026 (dokumen saja):** dipertahankan dan didokumentasikan sebagai endpoint teknis non-kontrak (instruksi pemilik); `API-Contract-v1.2-Errata-Stage5.md` Lampiran A. Kode tidak diubah. |
| A-2 | `GET /api/v1/teachers/import/template` (HM) tidak ada di Contract maupun errata. Contract §18 hanya menyebut template untuk Student; §15 hanya "mengikuti pola §28". Status Teacher import `.../status/{batchId}` sudah masuk errata §18/§28; template belum. | `routes/v1/teacher-import.php` | (c) | Orang 2 (IMPORT-002) / keputusan pemilik | **DITUTUP 9 Okt 2026 (dokumen saja):** template Teacher ditambahkan ke errata API (Lampiran A, route aktual `TeacherImportController::template`). |
| A-3 | `GET /up`, `GET /`, `GET /login` (non-API, infrastruktur). | `bootstrap/app.php:17`, `routes/web.php` | (c) infra | Keputusan pemilik (cukup diakui sebagai infrastruktur) | **DITUTUP 9 Okt 2026 (dokumen saja):** diakui sebagai pengecualian resmi route infrastruktur, tidak dihapus (errata API Lampiran A). |
| A-4 | `GET /gamification/me` hanya `role:student`; Contract §23 menulis 4 role. Ditutup `API-Contract-v1.2-Addendum-Level-Ranking.md` §1 (keputusan final). Matrix #38 tidak dilanggar: SA/HM/Teacher memakai `GET /students/{id}/gamification`. | `routes/v1/gamification.php` | (b) | Tertutup |
| A-5 | Info untuk SECURITY-001 (bukan temuan MERGE): `reset-password` (3 route) dan `change-password` tidak memakai middleware `throttle`; AC SECURITY-001 meminta rate limiting di sana. | `routes/v1/*.php` | — | Bersama (SECURITY-001) | **DITUTUP 9 Okt 2026 (kode):** limiter `password-change` (10/menit, kunci ID user, middleware `throttle.password`) dipasang di 3 route reset-password dan `change-password`; `tests/Feature/Auth/PasswordRateLimitTest.php`. Login tidak diubah. |

Catatan: `GET /habits` (semua role ber-token) tidak punya baris Matrix sendiri; Contract §21 menyatakannya eksplisit sebagai read-only exposure — tidak dihitung sebagai pelanggaran.

### Tabel seluruh route

| # | Method | Path | Role di route | Kelas | Rujukan dokumen | Matrix |
|---|---|---|---|---|---|---|
| 1 | GET | `/api/test` | (semua role ber-token) | c | TIDAK ADA di dokumen manapun | — |
| 2 | GET | `/api/v1/academic-years` | headmaster, teacher | a | §13 | #19 |
| 3 | GET | `/api/v1/academic-years/{id}` | headmaster, teacher | a | §13 | #19 |
| 4 | POST | `/api/v1/academic-years` | headmaster | a | §13 | #17 |
| 5 | PATCH | `/api/v1/academic-years/{id}` | headmaster | a | §13 | #18 |
| 6 | POST | `/api/v1/academic-years/{id}/activate` | headmaster | a | §13 | #18 |
| 7 | POST | `/api/v1/auth/login/staff` | PUBLIK | a | §10 | — (tidak ada baris Matrix; login staff) |
| 8 | POST | `/api/v1/auth/login/student/password` | PUBLIK | a | §10 | #52 |
| 9 | POST | `/api/v1/auth/login/student/qr` | PUBLIK | a | §10 | #51 |
| 10 | POST | `/api/v1/auth/logout` | (semua role ber-token) | a | §10 | #53 |
| 11 | GET | `/api/v1/auth/me` | (semua role ber-token) | a | §10 | — |
| 12 | POST | `/api/v1/auth/change-password` | super_admin | a | §10 | #47 |
| 13 | GET | `/api/v1/bermasyarakat/entries` | teacher, student | a | §22 | #33 |
| 14 | POST | `/api/v1/bermasyarakat/entries` | student | a | §22 | #36 |
| 15 | GET | `/api/v1/dashboard/student` | student | a | §25 | — |
| 16 | GET | `/api/v1/dashboard/teacher` | teacher | a | §25 | — |
| 17 | GET | `/api/v1/dashboard/headmaster` | headmaster | a | §25 | — |
| 18 | GET | `/api/v1/dashboard/super-admin` | super_admin | a | §25 | — |
| 19 | GET | `/api/v1/gamification/me` | student ⚠️ selisih vs Contract §23 (addendum) | a | §23 | #38 |
| 20 | GET | `/api/v1/students/{id}/gamification` | super_admin, headmaster, teacher | a | §23 | #38 |
| 21 | GET | `/api/v1/rankings/rombel` | teacher, student | a | §24 | #39 |
| 22 | GET | `/api/v1/rankings/angkatan` | teacher, student | a | §24 | #39 |
| 23 | GET | `/api/v1/habits` | (semua role ber-token) | a | §21 | — |
| 24 | GET | `/api/v1/habits/entries` | teacher, student | a | §21 | #33 |
| 25 | GET | `/api/v1/habits/entries/{id}` | teacher, student | a | §21 | #33 |
| 26 | POST | `/api/v1/habits/{habit_id}/entries` | student | a | §21 | #32 |
| 27 | GET | `/api/v1/headmasters` | super_admin | a | §12 (OD-02) | — |
| 28 | POST | `/api/v1/headmasters` | super_admin | a | §12 | #3 |
| 29 | GET | `/api/v1/headmasters/{id}` | super_admin | a | §12 | — |
| 30 | POST | `/api/v1/headmasters/{id}/reset-password` | super_admin | a | §10/§12 | #48 |
| 31 | POST | `/api/v1/qr-tokens/{studentId}/regenerate` | teacher | a | §10 (OD-17) | #50 (menumpang) |
| 32 | GET | `/api/v1/rombels` | headmaster, teacher | a | §14 | #16 |
| 33 | GET | `/api/v1/rombels/{id}` | headmaster, teacher | a | §14 | #16 |
| 34 | POST | `/api/v1/rombels` | headmaster | a | §14 | #13 |
| 35 | PATCH | `/api/v1/rombels/{id}` | headmaster | a | §14 | #14 |
| 36 | DELETE | `/api/v1/rombels/{id}` | headmaster | a | §14 | #15 |
| 37 | GET | `/api/v1/schools` | super_admin | a | §11 (OD-01) | — |
| 38 | POST | `/api/v1/schools` | super_admin | a | §11 | #1 |
| 39 | GET | `/api/v1/schools/{id}` | super_admin | a | §11 | — |
| 40 | GET | `/api/v1/student-class-assignments` | (semua role ber-token) | a | §19 | #25 |
| 41 | POST | `/api/v1/student-class-assignments` | teacher | a | §19 | #23 |
| 42 | PATCH | `/api/v1/student-class-assignments/{id}` | teacher | a | §19 | #24 |
| 43 | GET | `/api/v1/students/{id}/class-assignments` | (semua role ber-token) | a | §19 | #25 |
| 44 | POST | `/api/v1/students/{id}/inactive` | teacher | a | §20 | #41 |
| 45 | POST | `/api/v1/students/{id}/graduate` | teacher | a | §20 | #42 |
| 46 | POST | `/api/v1/rombels/{id}/bulk-graduate` | teacher | a | §20 | #43 |
| 47 | GET | `/api/v1/students` | (semua role ber-token) | a | §17 | #9 |
| 48 | GET | `/api/v1/students/{id}` | (semua role ber-token) | a | §17 | #9 |
| 49 | GET | `/api/v1/students/{id}/photo` | (semua role ber-token) | b | Errata Stage 5 §17 (F-06): foto = URL endpoint terautentikasi | — |
| 50 | POST | `/api/v1/students` | teacher | a | §17 | #7 |
| 51 | PATCH | `/api/v1/students/{id}` | teacher | a | §17 | #10 |
| 52 | POST | `/api/v1/students/{id}/reset-password` | teacher | a | §10 | #50 |
| 53 | GET | `/api/v1/student-imports/template` | teacher | a | §18 (peran tidak eksplisit) | #26-31 |
| 54 | POST | `/api/v1/student-imports/preview` | teacher | a | §18 | #26-31 |
| 55 | POST | `/api/v1/student-imports/commit` | teacher | a | §18 | #8, #26-31 |
| 56 | GET | `/api/v1/student-imports/status/{batchId}` | teacher | b | Errata Stage 5 §18/§28 (keputusan pemilik): endpoint status = baca hasil/histori batch | — |
| 57 | GET | `/api/v1/teacher-class-assignments` | headmaster, teacher | a | §16 (OD-04) | — |
| 58 | GET | `/api/v1/teacher-class-assignments/{id}` | headmaster, teacher | a | §16 | — |
| 59 | POST | `/api/v1/teacher-class-assignments` | headmaster | a | §16 | #20 |
| 60 | PATCH | `/api/v1/teacher-class-assignments/{id}` | headmaster | a | §16 | #21 |
| 61 | POST | `/api/v1/teacher-class-assignments/{id}/deactivate` | headmaster | a | §16 | #22 |
| 62 | GET | `/api/v1/teachers/import/template` | headmaster | c | TIDAK ADA di dokumen manapun | — |
| 63 | POST | `/api/v1/teachers/import` | headmaster | a | §15 | #6 |
| 64 | GET | `/api/v1/teachers/import/status/{batchId}` | headmaster | b | Errata Stage 5 §18/§28 (baris "Endpoint status", berlaku untuk Teacher & Student) | — |
| 65 | GET | `/api/v1/teachers` | headmaster | a | §15 (OD-03) | — |
| 66 | POST | `/api/v1/teachers` | headmaster | a | §15 | #5 |
| 67 | GET | `/api/v1/teachers/{id}` | headmaster | a | §15 | — |
| 68 | POST | `/api/v1/teachers/{id}/inactive` | headmaster | a | §15 Addendum v1.1 | #61 |
| 69 | POST | `/api/v1/teachers/{id}/reset-password` | headmaster | a | §10/§15 | #49 |

Role di route dibaca dari `middleware('role:...')`; "(semua role ber-token)" = hanya `jwt` (scope ditegakkan di Service); `PUBLIK` = tanpa autentikasi.

---

## B. Audit migrasi dan seeder (DoD 2 dan 3) — [dicek ke kode], BUKAN "lulus dijalankan"

**Urutan FK.** 22 file migration; setiap tabel dibuat SETELAH semua tabel yang direferensikan FK-nya:
`users → schools → teachers/headmasters/students → habits → indicators → student_habits → qr_tokens → import_batches → academic_years → rombels → teacher_class_assignments → student_class_assignments → student_habit_indicators → audit_logs → gamification_levels`, lalu 3 migration 4 Okt (unique rombel, unique `schools.name`, `audit_logs.target_user_id` nullable). Sesuai DB Design §10 yang menyatakan urutan ditentukan dependency FK; nomor 001–017 di v1.5 tidak ditiru literal (DB errata mencatat migration baru di luar daftar itu). **Tidak ada pelanggaran dependency.**

**Struktur akhir vs DB Design v1.5 + errata** (semua cocok kecuali yang ditandai):

| Tabel / objek | Hasil |
|---|---|
| `users` | role CHECK 4 nilai, `username` unique, `password` 255, INDEX(role) — cocok |
| `schools` | `name` 150; `schools_name_unique` (errata F-11) — cocok |
| `headmasters` | status CHECK + `uq_headmasters_one_active_per_school` (partial) — cocok |
| `teachers` | gender/status CHECK, INDEX(school_id,status), tanpa kolom classroom — cocok |
| `students` | tanpa `classroom`/`grade_level`; `poin >= 0`; status 3 nilai; INDEX(school_id,status) — cocok (Risk #1) |
| `habits`, `indicators` | kolom, `point` NULL atau IN (10,15,25,50), UNIQUE(habit_id,sort_order) — cocok |
| `student_habits` | `classroom` snapshot, `initiative` NOT NULL CHECK, **tanpa `description`**, `uq_student_habit_date` — cocok |
| `student_habit_indicators` | UNIQUE(student_habit_id, indicator_id) + INDEX — cocok |
| `academic_years` | CHECK end>start, `uq_academic_years_one_active_per_school` — cocok |
| `rombels` | `grade_level > 0`, INDEX(school,AY) dan (school,grade) — cocok |
| `teacher_class_assignments` | `uq_tca_one_active_per_teacher_year` **dan** `uq_tca_one_active_per_rombel_year` (errata F-18, migration `2026_10_04_100000`); FK rombel RESTRICT — cocok |
| `student_class_assignments` | `uq_sca_one_active_per_student_year`, `idx_sca_active_rombel`, FK rombel RESTRICT — cocok |
| `qr_tokens`, `gamification_levels`, `audit_logs` | cocok (`target_user_id` nullable via migration 4 Okt) |
| `import_batches` | **Tidak ada di DB Design v1.5 §4 (16 entitas) dan tidak didefinisikan di errata** — lihat C-11 |
| `jobs`, `job_batches`, `failed_jobs`, `cache` | bawaan framework (errata menyebutnya). Aplikasi tidak memakai queue sama sekali setelah F-38 (`grep ShouldQueue|dispatch` kosong); tabel jobs dan `QUEUE_CONNECTION=database` di `.env.example` sisa konfigurasi. Cache `database` dipakai rate limiter. |

**Seeder (`DatabaseSeeder` → `HabitIndicatorSeeder`, `GamificationLevelSeeder`):**

| Item | Hasil |
|---|---|
| Super Admin | `superadmin1` dan `superadmin2` (2 akun); password dari `config('app.seed_super_admin_password')` = `env('SEED_SUPER_ADMIN_PASSWORD')`; bila kosong → `Str::random(32)` + peringatan. Tidak ada password literal. |
| Habit | 7 (`code` unik, hanya Bermasyarakat `allows_multi_select`) |
| Indikator | 28; 4 `point` NULL (Bermasyarakat), 1 `is_exclusive` |
| Level | 101 baris (Level 0 = 0 … Level 100 = 750.592); Level 1 = 50, Level 2 = 100 |
| Grade Advancement / mass promotion / recreate Student | **Tidak ada** tabel, kolom, route, atau kode (grep `promot|advancement|kenaikan|naik_kelas` = kosong) |
| Import School / Import Headmaster | **Tidak ada** (grep nama route/kelas/tabel = kosong; route list Bagian A) |
| `students.classroom` / `students.grade_level` | Tidak ada; `classroom` hanya di `student_habits` (snapshot) |
| `gamification_level` di dashboard student | `DashboardService` memanggil `LevelService::levelForPoin(poin)` (DoD 6, statis) |

Catatan informasional (DB Design diam): FK profil → `users`/`schools` memakai `cascadeOnDelete`; tidak ada endpoint DELETE untuk entitas itu sehingga tidak terjangkau.

---

## C. Register kontradiksi SoT (DoD 5)

Format: [no | dokumen A vs B | isi | pemenang menurut hierarki | ditutup errata? | status]. Dokumen sumber tidak ditulis ulang.

| No | A vs B | Isi kontradiksi | Menang (hierarki) | Ditutup errata? | Status |
|---|---|---|---|---|---|
| C-01 | Dokumen v4.0 §9, Matrix #9, Role Summary (Student: "Other students" DENY, "no classmates list") vs Addendum Level-Ranking §2 / `db-gap-analysis` NOTE-11 | Ranking menampilkan `name` Student lain. | Dokumen v4.0 dan Matrix (di atas Contract) | **Tidak** — penyimpangan disadari tim, tetapi Dokumen v4.0/Matrix belum diamandemen | **TERBUKA (administratif).** Keputusan tim final tidak dibuka; butuh **pemilik** mengamandemen Dokumen v4.0 §9, Matrix #9, Role Summary |
| C-02 | API Contract §7 (konvensi pagination) vs errata §7 ("`GET /students` tidak berubah oleh F-15") | `GET /students` dan `GET /student-class-assignments` tanpa pagination; cabang Super Admin tidak berbatas (`StudentService::index` → `Student::…->get()`, `StudentClassAssignmentService::index` → `StudentClassAssignment::all()`). Errata tidak memutuskan apakah dikecualikan. | Contract §7 | Hanya dicatat "tidak berubah" | **TERBUKA — butuh keputusan pemilik** (paginasi atau pengecualian tertulis); implementasi Orang 1. Sudah dilaporkan ke Orang 2 sebelumnya |
| C-03 | Contract §15 (404 DAN 403 untuk lintas sekolah dalam satu bagian) vs F-49 | Teks §15 saling bertentangan; keputusan final: `POST /teachers/{id}/inactive` lintas sekolah = 404, `resetPassword()` tetap 403. | Keputusan F-49 | **Ya** — errata §15 | Tertutup. Sisa: `docs/authorization-matrix.md` dan `docs/team-log/AUTHORIZATION-MATRIX.md` menyimpan teks historis 403 beserta catatan pembatalan (bukan kontradiksi aktif) |
| C-04 | Contract §18/§28 (maks 1.000 baris; >100 baris async/queue) vs errata §18/§28 (200 baris, semua sinkron) | Dua parameter berubah. Kode: `ImportService::MAX_ROWS = 200`, tidak ada job/queue. | Errata (keputusan pemilik) | **Ya** | Tertutup di errata. Teks usang tersisa: Task Board v23 (IMPORT-001, IMPORT-002, TEST-001, TEST-002), `docs/db-gap-analysis.md` NOTE-09 ("polling queue"), `docs/team-log/IMPORT-001.md`. Verifikasi timeout 180 dtk di server nyata = belum dilakukan (baseline §16, sebelum deploy) |
| C-05 | Contract §23/§25 (contoh 340 poin = Level 4) vs seed (Level 3 = 200, Level 4 = 400) | Contoh salah. | Seed/DB Design §5.14 | **Ya** — errata §23 (340 = Level 3) | Tertutup; teks Contract asli tidak diedit |
| C-06 | Teks usang milik Orang 2 (tidak diedit): `docs/postman/README-TEST-002.md` baris 22; `BASELINE-STAGE-0C.md` §15 | README: "LEVEL-RANKING-001 belum ada endpoint … tidak ada yang bisa dites" — endpoint sudah ada. Baseline §15: menyebut TEST-001 masih memuat literal password (sudah dihapus `d62dbb8`); "217 request" (aktual 218); judul "709/709" vs §16 "712/712". | Kode (fakta) | Tidak | **TERBUKA — Orang 2** merevisi teksnya sendiri | **DITUTUP 9 Okt 2026 (dokumen saja):** dihitung ulang dari file sumber: README-TEST-002 "187 request, 13 folder" (baris 8) menjadi 218 request, 15 folder; Baseline §15: 217 menjadi 218, judul 709/709 diberi keterangan 712/712, klaim TEST-001 masih memuat password dihapus. Catatan: pada HEAD `31df767` README baris 22 sudah benar (LEVEL-RANKING-001 sudah dinyatakan ada); yang usang adalah baris 8. |
| C-07 | Task Board v23 vs kondisi aktual | Status 21 TODO / 10 REWORK / 1 DONE padahal seluruh task terimplementasi; teks AC memuat `unknown_rombel`, 1.000 baris, queue, `habit_already_locked`; catatan DASHBOARD-001 "gamification_level diintegrasikan saat MERGE-001" padahal sudah ada (F-40). | Dokumen v4.0/Matrix/Contract+errata | Sebagian (via errata) | **TERBUKA — keputusan pemilik** (F-48 administratif; xlsx sengaja tidak diedit) |
| C-08 | Dokumen v4.0 §7, Matrix #35 ("Daily lock / Historical view"), Contract §21 (`habit_already_locked`, PATCH ditolak setelah lock) vs implementasi immutable | Tidak ada mekanisme lock; entry immutable (tanpa PATCH/DELETE), tanggal ditentukan server. | Dokumen v4.0 (efek sama: tak ada edit/backfill) | **Ya** — Addendum-Habit-GET §4 | Tertutup (efek identik); teks Matrix #35 dan Contract §21 tidak diamandemen |
| C-09 | Contract §23 (`/gamification/me` 4 role) vs Addendum Level-Ranking §1 | Lihat A-4. | Matrix #38 (terpenuhi via endpoint lain) | **Ya** | Tertutup |
| C-10 | Contract §24 (`academic_year_id` bebas) vs Addendum Level-Ranking §3 (hanya Academic Year aktif, selain itu 403) | Penyempitan parameter; dasar Dokumen v4.0 §9. | Dokumen v4.0 §9 | **Ya** (addendum, keputusan tim) | Tertutup |
| C-11 | DB Design v1.5 §4 ("tidak ada entity lain", 16 entitas) vs tabel `import_batches` | Tabel ada sejak `2026_09_15`; dipakai endpoint status import (kini formal di errata §18/§28), tetapi DB errata hanya menyebut "tetap dipakai" tanpa definisi kolom (`type`, `status`, `total`, `success`, `failed`, `failed_rows`, `created_by`). Komentar migration masih "pending, processing, done" dan "nanti 'teacher'". | DB Design | Sebagian | **TERBUKA kecil** — pemilik memutuskan memasukkan definisi `import_batches` ke errata DB; implementasi Orang 1/2 | **DITUTUP 9 Okt 2026 (dokumen saja):** definisi `import_batches` dibaca dari migration aktual dan ditambahkan ke `DB-Design-v1.5-Errata-Stage5.md`; tiga komentar migration usang dicatat (migration tidak diubah). Pemetaan tipe PostgreSQL belum dicocokkan dengan skema nyata (`php artisan db:table import_batches`). |
| C-12 | Contract §18 / Matrix #29 ("Unknown Rombel") / Task Board vs N-1 | Kode `unknown_rombel` tidak ada; `rombel_id` tidak ada = 422 `validation_failed`, di luar scope = 403. | Errata N-1 (Matrix #29 tetap benar secara semantik: ditolak) | **Ya** | Tertutup |
| C-13 | Dokumen v4.0 §5.2 / Matrix #16 (Teacher VIEW Master Rombel "sekolah/rombel yang relevan") vs F-21 (hanya Rombel dari active assignment) | Penyempitan di dalam rentang "relevan". | Dokumen v4.0 (tidak dilanggar) | **Ya** — errata §14 | Tertutup |
| C-14 | DB Design §10.A (urutan 001–017) vs urutan migration repo | Nomor berbeda; dependency FK terpenuhi. | DB Design (prinsip dependency) | Ya (DB errata) | Tertutup, bukan pelanggaran |
| C-15 | Rujukan sumber di errata/baseline | Banyak keputusan dikutip dari "Matrix v3.1" (`MASTER_AUDIT_REMEDIATION_MATRIX_v3_1_STAGE0_STATUS.md`) dan "keputusan pemilik" (F-18, F-38, N-1..N-3, batas 200). Dokumen itu **tidak ada di repo**; kutipan tidak dapat ditelusuri dari repo. | — | — | **Informasi:** keputusan ditandai FINAL oleh pemilik; sumber tertulis berada di luar repo (tidak diverifikasi) |

Ringkasan status C: **tertutup 9** (C-03, C-05, C-08, C-09, C-10, C-12, C-13, C-14 dan C-04 untuk keputusannya), **terbuka 5** (C-01, C-02, C-06, C-07, C-11) ditambah 1 butir informasi (C-15).

**Pembaruan 9 Okt 2026 (setelah Patch A/B/C):** C-06 dan C-11 ditutup; terbuka 3 (C-01, C-02, C-07). A-1, A-2, A-3 tertutup di tingkat dokumen; A-5 (rate limit) tertutup di kode.

---

## D. Checklist DoD MERGE-001 (status jujur)

| DoD | Status |
|---|---|
| 1. Alur penuh end-to-end | **[dilaporkan lulus Orang 1, 9 Okt 2026]** — `docs/testing/MERGE-001-Postman-Collection.json` (63 request), Folder 1-6 0 Failed; laporan, bukan bukti tersimpan |
| 2. Regression + `migrate:fresh` (Fresh) + seeder lulus | Struktur migration dan seeder **[dicek ke kode]** (Bagian B). Eksekusi `migrate:fresh --seed` dan `php artisan test` **[dilaporkan lulus Orang 1, 9 Okt 2026]**: 443 passed / 2216 assertions (naik dari 434 / 2136 karena `InitialPasswordCostTest` F-55, +9 test / +80 assertions) |
| 3. Tidak ada task aktif bergantung pada Grade Advancement | **[dicek ke kode]** — kosong di `app/ database/ routes/`. Task Board: KENAIKAN-001 berstatus REMOVED |
| 4. Tidak ada endpoint di luar API Contract v1.2 | **[dicek ke kode]** — 2 route (c): A-1, A-2 (+ infra A-3) → **[terbuka, butuh keputusan pemilik]** → **[tertutup 9 Okt 2026]** keputusan pemilik: didokumentasikan di errata API Lampiran A |
| 5. Matrix, Contract, DB Design, Task Board tidak saling bertentangan | **[terbuka, butuh keputusan]** — C-01, C-02, C-06, C-07, C-11 → **[sebagian, 9 Okt 2026]** C-06 dan C-11 ditutup; tersisa C-01, C-02, C-07 |
| 6. `gamification_level` pada dashboard student | Kode **[dicek ke kode]** (`DashboardService::forStudent`, LevelService); runtime **[dilaporkan lulus Orang 1, 9 Okt 2026]** (request 4.01 dan 3.10 di collection MERGE-001) |

## E. Perintah yang harus dijalankan user

1. `php artisan migrate:fresh --seed` (isi `SEED_SUPER_ADMIN_PASSWORD` di `.env` lebih dulu).
2. `php artisan test` — seluruh suite; bandingkan dengan baseline **434 passed / 2136 assertions**.
3. Collection `MERGE-001-Postman-Collection.json`: file import `merge001-import-3-baris.xlsx` dibuat user di luar repo; isi `sa_password`; `php artisan cache:clear` sebelum tiap folder; jalankan folder 1→6 berurutan sekali jalan.
