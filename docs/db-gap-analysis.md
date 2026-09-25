# DB Gap Analysis — MIGRATION-002

Scope: `users`, `schools`, `headmasters`, `teachers`, `students`, `qr_tokens` (Fresh Deployment, DB Design v1.5 §10.A).
Tabel lain (academic_years, rombels, teacher_class_assignments, student_class_assignments, habits, indicators,
student_habits, student_habit_indicators, gamification_levels, audit_logs) **di luar scope task ini**.

| Existing (legacy repo) | Required Final (DB Design v1.5) | Action | Reason |
|---|---|---|---|
| `users`: uuid, username, email UNIQUE, password, level(int), is_active(int), access_token(text), timestamps | `users`: id, role varchar(20) CHECK IN (super_admin/headmaster/teacher/student), username UNIQUE, password, timestamps, INDEX(role) | EDIT migration + Model | §5.1 — role menggantikan level; uuid/email/is_active/access_token tidak ada di final schema |
| `schools`: name, address (NOT NULL), logo, timestamps | `schools`: name varchar(150), address text NULL, logo_path varchar(255) NULL, timestamps | EDIT migration + Model | §5.2 — address jadi nullable, logo→logo_path |
| `headmaster` (singular table), tanpa status | `headmasters`: +status active/inactive DEFAULT active + partial unique index (school_id) WHERE status='active'; gender NOT NULL CHECK | EDIT migration (rename table+file) + Model | §5.3, R1/R2 — lifecycle Headmaster & "1 aktif per school" |
| `teachers`: +classroom, tanpa status | `teachers`: DROP classroom, +status active/inactive, INDEX(school_id), INDEX(status); gender NOT NULL CHECK | EDIT migration + Model | §5.4, R4 — assignment-based (classroom bukan sumber scope Teacher lagi), lifecycle Teacher (Matrix #61) |
| `students`: +classroom, +grade_level (migration ALTER terpisah), poin default 0 (tanpa CHECK), tanpa status | `students`: DROP classroom & grade_level, +status active/inactive/graduated DEFAULT active, poin CHECK(poin>=0), UNIQUE(nisn), INDEX(school_id), INDEX(status), INDEX(school_id,status); gender NOT NULL CHECK | EDIT migration (fold, tanpa ALTER terpisah) + Model | §5.5, Risk #1 — classroom/grade_level digantikan student_class_assignments + rombels.grade_level |
| `qr_user`: user_id FK, token(unique), link_qr(text) | `qr_tokens`: student_id FK UNIQUE (bukan user_id), token_hash UNIQUE (hash, bukan token mentah), regenerated_at nullable | EDIT migration (rename table+file) + Model (QrUser→QrToken) | §5.15 — QR terikat ke Student (bukan User generik), token wajib hashed, link_qr tidak ada di final schema |
| `password_reset_tokens` (migration ada) | Tidak ada di 16 entity DB Design v1.5 | DROP FILE | Forgot/self-service password reset = DENY total (Dokumen v4.0 §4.4, Matrix #55); tidak ada mekanisme email reset |
| `student_habit_backups` (migration ada) | Tidak ada di 16 entity DB Design v1.5 | DROP FILE | Bagian 15 DB Design v1.5: entity ini tidak termasuk Final Entity List; terkait KENAIKAN-001 yang sudah REMOVE |
| `2026_09_15_102922_add_grade_level_to_students_table.php` (migration ALTER terpisah) | grade_level tidak ada sama sekali di `students` | DROP FILE | Jalur Fresh Deployment: kolom final langsung dibuat di migration `create_students_table`, tanpa ALTER menyusul; grade_level lresmi ada di `rombels`, bukan `students` |
| `database/seeders/DatabaseSeeder.php`: `User::factory()->create(['name'=>..,'email'=>..])` | Tidak ada kolom `name`/`email` di `users` final | EDIT (kosongkan pemanggilan lama, jadi coordinator kosong) | Field tidak ada di skema baru; seeding Super Admin milik AUTH-003, habits/indicators milik HABIT-001 (sudah ada `HabitIndicatorSeeder`, belum di-wire — bukan scope task ini), gamification_levels milik LEVEL-RANKING-001 |
| `database/factories/UserFactory.php`: name/email/email_verified_at/remember_token | Field final: role/username/password | EDIT | Konsisten dengan Model User baru, supaya factory tidak error dipakai task/test lain |
| `App\Models\School::headmaster()` → `hasOne(Headmaster::class)` (tanpa filter status) | Relasi harus sadar histori (1:N) tapi hanya 1 `active` | EDIT (tambah `headmasters()` hasMany + `headmaster()` hasOne di-scope `where('status','active')`) | §8 — schools↔headmasters 1:N secara skema, hanya 1 baris `status='active'` per school |
| `App\Models\User::qrUser()` (relasi ke qr_user via user_id) | qr_tokens terikat ke `students.id`, bukan `users.id` | EDIT (hapus dari User, pindah ke Student sebagai `qrToken()`) | §5.15 — QR token adalah kredensial Student, bukan User generik |

## Revisi hasil review (akun lain)

1. **`user_id` di `headmasters`, `teachers`, `students` kelewat `->unique()`.** DB Design v1.5 §5.3/5.4/5.5 eksplisit "FK→users.id, **UNIQUE**" (relasi 1:1 dengan `users`) — sudah ditambahkan di ketiga migration.
2. **Patch sebelumnya tidak portable.** Root cause: dibuat dari `git diff` (working-tree vs index) setelah `git mv`, sehingga isi patch untuk file yang di-rename ter-diff relatif ke path baru yang sudah "seolah-olah ada" di index (bukan dari kondisi asli repo). Hasilnya: hunk untuk `QrToken.php`/`headmasters` table muncul sebagai **modifikasi file existing**, padahal di repo target file itu belum ada — `git apply` akan gagal. Diperbaiki dengan generate ulang pakai `git diff HEAD -M`, sehingga file yang berubah total tampil sebagai `deleted file mode` (path lama) + `new file mode` (path baru) yang valid dan self-contained. **Sudah diverifikasi ulang**: `git apply --check` & `git apply` sukses di clone bersih dari `anak-tumbuh-be.zip` asli.

## HABIT-001 (task terpisah, dependency: MIGRATION-002 + ARCH-001)

| Existing (legacy) | Required Final (DB Design v1.5 §5.10-§5.11, API v1.2 §21) | Action | Reason |
|---|---|---|---|
| `habits`: id, name, timestamps | `habits`: smallIncrements id, code UNIQUE, name UNIQUE, allows_multi_select, sort_order UNIQUE, TANPA timestamps | EDIT migration + Model | Fixed reference, seed 7 baris |
| `indicator` (singular), poin NOT NULL, tanpa is_exclusive/sort_order/code | `indicators` (plural): id serial, habit_id smallint FK, name, point NULLABLE CHECK IN(10,15,25,50), is_exclusive, sort_order, UNIQUE(habit_id,sort_order) | EDIT migration (rename) + Model | R13 — point NULL utk 4 indikator Bermasyarakat (scoring count-based, bukan SUM) |
| `HabitController`/`IndicatorController`: full CRUD (store/update/destroy) | Tidak ada CRUD sama sekali untuk role manapun; **satu-satunya** endpoint: GET /api/v1/habits (tanpa show, tanpa /indicators standalone) | DELETE write methods, DELETE IndicatorController/Service/Request, EDIT route | API Contract v1.2 §21 eksplisit: "GET /api/v1/habits adalah satu-satunya endpoint untuk resource ini" |
| `HabitIndicatorSeeder`: 7 habit x 4 indikator (nama beda dari dokumen scoring final, field `poin`) | 28 nama indikator persis sesuai dokumen Keputusan Scoring 7 Habit, field `point` | EDIT seeder + file data baru `database/seeders/data/indicators.php` (satu file, idempotent `updateOrCreate`) | AC HABIT-001: nama harus persis (termasuk en-dash "–"), Bermasyarakat point=NULL |
| `student_habits` migration: FK `->constrained('indicator')` | Tabel target sudah di-rename ke `indicators` | EDIT (hanya nama tabel FK) | Konsekuensi langsung rename tabel indicator→indicators; sisa kolom `student_habits` (classroom/description/note/inisiative) **tidak diubah**, itu scope STUDENTHABIT-001 |
| `DatabaseSeeder`: kosong (dari MIGRATION-002) | Wire `HabitIndicatorSeeder` | EDIT `$this->call([...])` | Sesuai catatan MIGRATION-002 sebelumnya |

Boundary: field lain `student_habits` (classroom, description, note, inisiative, relasi StudentHabit↔Indicator) **tidak disentuh** — itu scope STUDENTHABIT-001.

## Catatan Boundary
- Tabel `habits`, `indicator`, `student_habits`, `student_habit_backups` terkait (di luar `student_habit_backups` yang di-DROP) **tidak diubah** di task ini — milik HABIT-001/STUDENTHABIT-001.
- Service/Controller/Request/Resource yang masih membaca kolom lama (`level`, `is_active`, `classroom` di Teacher/Student, `QrUser`, tabel `headmaster` singular) **tidak diubah** di task ini — masing-masing milik AUTH-001, SCHOOL-001, HEADMASTER-001, TEACHER-001, STUDENT-001. File-file itu akan error terhadap skema baru sampai task REWORK masing-masing dikerjakan; ini sudah diketahui dan dicatat di Dependency Task Board ("mendarat sebagai satu batch sebelum migrate:fresh dijalankan di DB dev bersama").
- `migrate:fresh` **belum dijalankan** dari sandbox ini (tidak ada koneksi PostgreSQL live di sini). Perlu dijalankan manual di environment dev oleh Anda/tim setelah review.

## STUDENT-ASSIGNMENT-001 (dependency: ROMBEL-001, TEACHER-ASSIGNMENT-001, MIGRATION-002 — semua ✅)

Migration `student_class_assignments` + Model + `StudentClassAssignmentService` (reusable, dipakai ulang
nanti oleh STUDENT-001/IMPORT-001/STUDENT-LIFECYCLE-001 via `createAssignment()` & `closeActiveAssignment()`)
+ Controller + Route, sesuai API Contract v1.2 §19 & DB Design v1.5 §5.9.

**NOTE-05 — RESOLVED via Keputusan Tim eksplisit (23 Sep 2026, bukan tertulis literal di SoT asli)**

Gap semula:

```
CONFLICT
SOURCE A: API Contract v1.2 §19 mendaftarkan "PATCH /api/v1/student-class-assignments/{id} —
          Update assignment (Matrix #24). Tidak ada auto-move." tanpa request body/skema field.
SOURCE B: DB Design v1.5 §5.9 hanya menjelaskan proses konseptual "pindah kelas" (UPDATE baris
          lama status='ended' + INSERT baris baru status='active') sebagai APA YANG TERJADI saat
          pindah kelas — bukan definisi payload PATCH endpoint tersebut.
IMPACT: Tidak ada dasar tertulis field apa yang boleh diubah oleh PATCH ini.
```

**Keputusan Tim (ditetapkan user, divalidasi bersama tim, 23 Sep 2026)** — bukan hasil pembacaan
literal SoT, melainkan inferensi tim atas 3 petunjuk tidak langsung di SoT (dicatat supaya jejaknya
jelas ini keputusan tambahan, bukan aturan yang sudah tertulis eksplisit):
1. API Contract §19 eksplisit "Tidak ada auto-move" → PATCH tidak boleh menerima `rombel_id`.
2. API Contract §19 (Validation & Errors, assignment_conflict) menyebut caller harus "resolve secara
   eksplisit (mis. deactivate assignment lama lebih dulu, sebagai request terpisah)" — tapi tidak ada
   endpoint deactivate terpisah untuk Student Class Assignment (beda dari Teacher Class Assignment
   yang punya `POST .../deactivate` sendiri) → PATCH ini yang mengisi peran itu.
3. DB Design §5.9 menjelaskan "pindah kelas" sebagai 2 operasi terpisah (tutup lama via UPDATE
   status='ended', lalu POST baris baru) — bukan 1 endpoint otomatis.

Ditetapkan:
- Body PATCH **hanya** `{ "status": "ended" }`.
- Field lain (`rombel_id`, `student_id`, `academic_year_id`, `school_id`, dst.) → **422**, code
  `field_not_accepted` (konsisten vocabulary dengan API Contract §21 utk kasus field yang eksplisit
  ditolak, bukan silent-drop).
- Hanya transisi `active` → `ended`; tidak ada reactivation (konsisten prinsip Teacher Lifecycle,
  Matrix #61).
- Idempotent: assignment yang sudah `ended`, PATCH ulang → 200 tanpa efek tambahan (pola sama
  seperti `POST /teachers/{id}/inactive`).
- Scope: Teacher only, own active rombel (Dokumen v4.0 §5.4) — assignment di luar scope → 403
  (bukan 404; ini operasi WRITE dengan scope jelas dari path, sesuai API Contract §26: "Untuk
  operasi write... digunakan 403"). Assignment id yang benar-benar tidak ada → 404.

Endpoint final: POST, GET (list, scope-based), GET /students/{id}/class-assignments, **PATCH** (hanya `status: ended`).

## STUDENT-001 (dependency: SCHOOL-001, UPLOAD-001, MIGRATION-002, STUDENT-ASSIGNMENT-001, TEACHER-ASSIGNMENT-001, ARCH-001 — semua ✅)

Rewrite total `StudentController`/`StudentService`/Request/Resource dari implementasi lama (level angka,
`classroom`/`grade_level` mentah, `is_active`, `qr_user` plaintext) ke skema final MIGRATION-002 + assignment-based
placement, sesuai API Contract v1.2 §17 & DB Design v1.5 §5.5. Reuse `TeacherScopeService::activeAssignment()`
dan `StudentClassAssignmentService::createAssignment()` — tidak duplikasi query scope/assignment.

**NOTE-04 (referensi dari Prompt Siap Pakai Task Board — didokumentasikan di sini karena belum ada
sebelumnya):** POST /students men-generate QR token SYSTEM saat create (DB Design v1.5 §5.15), tapi
response **tidak** menyertakan raw token — hanya `token_hash` yang disimpan (hash, bukan plaintext).
Raw token hanya didapat lewat endpoint regenerate (`QR-001`, belum dikerjakan). Ini konsisten langsung
dengan DB Design v1.5 §5.15 ("raw token hanya dikirim sekali ke Student saat generate/regenerate,
tidak pernah disimpan plaintext"), bukan aturan baru.

**Boundary — dampak ke IMPORT-001 (belum dikerjakan, di luar scope):** `StudentImportService::store()`
memanggil `StudentService::store($row)` dengan shape lama (`school_id`, `classroom`, `grade_level`).
Signature baru `StudentService::store()` butuh `rombel_id` (bukan `school_id`/`classroom`/`grade_level`).
`routes/v1/student.php` prefix `students/import` **tidak disentuh** (masih `role:1,2,3` lama) — akan
error sampai IMPORT-001 dikerjakan ulang untuk menyesuaikan ke signature baru. Ini expected, bukan bug
baru dari task ini.

## NOTE-06 (24 Sep 2026) — STUDENT-001, PATCH /students/{id}

**Bug ditemukan saat review kode** (bukan gap SoT): `UpdateStudentRequest` sempat memvalidasi key
`fullname`, sementara `CreateStudentRequest` (POST /students) memvalidasi key `name` — inkonsisten
untuk field yang sama. API Contract v1.2 §17 eksplisit hanya mengenal satu field API: **`name`**
(tabel Administrative Fields: `name (fullname)` — `name` adalah field API, `fullname` adalah nama
kolom DB). POST sudah benar; PATCH salah.

**Fix:**
- `UpdateStudentRequest`: `$allowed` dan rule diganti dari `fullname` → `name`.
- `StudentService::update()`: tambah mapping `name` → `fullname` sebelum `$student->update($data)`,
  konsisten dengan pola yang sudah ada di `store()`.
- Key `fullname` pada body PATCH sekarang eksplisit ditolak 422 `protected_field` (bukan diam-diam
  diterima seperti sebelumnya).

Test: `tests/Feature/Student/StudentUpdateNameFieldTest.php` (2 case: PATCH `name` → 200, kolom
`fullname` berubah; PATCH `fullname` → 422 `protected_field`).


## STUDENTHABIT-001 — POST saja (dependency: STUDENT-001, HABIT-001, STUDENT-ASSIGNMENT-001, TEACHER-ASSIGNMENT-001, MIGRATION-002)

| Existing (legacy) | Required Final (DB Design v1.5 §5.12-§5.13, API v1.2 §21-§22) | Action | Reason |
|---|---|---|---|
| `student_habits`: description, inisiative nullable, note longText, indicator_id NOT NULL, tanpa habit_date | habit_date NOT NULL, note varchar(1000), classroom varchar(30) NULL, initiative NOT NULL CHECK IN (mandiri,disuruh), indicator_id NULL, UNIQUE uq_student_habit_date, index (student_id,habit_date) & (habit_id,habit_date); TANPA description | REWRITE migration (Fresh) | DB v1.5 R11/R17, Dokumen v4.0 §7 |
| (tidak ada) | `student_habit_indicators` (pivot Bermasyarakat) | ADD migration + Model | DB v1.5 §5.13 |
| StudentHabit model: fillable lama | Fillable final + relasi selectedIndicators | EDIT | — |

Keputusan Tim 24 Sep 2026: GAP #1 `habit_already_locked` tidak diimplementasikan di POST (kontrak mendefinisikan error untuk PATCH yang tidak ada); GAP #2 `wrong_habit_endpoint` (422); GAP #3 `/habits/entries` tidak memuat Bermasyarakat, filter GET ditunda sampai audit kontrak; GAP #4 tidak ada perubahan (enum `mandiri|disuruh`).
GAP #5 RESOLVED (Keputusan Tim 24 Sep 2026): snapshot `classroom` diambil dari assignment aktif pada Academic Year active school Student (`StudentClassAssignmentService::activeAssignmentInCurrentAcademicYear`), tanpa fallback; tanpa perubahan skema/constraint. `duplicate_habit_entry` (409) disetujui sebagai technical code; `too_many_indicators` dibatalkan; duplicate `indicator_ids` -> 422 validation_failed.

GET (24 Sep 2026): `/habits/entries` (+/{id}) dan `/bermasyarakat/entries` diimplementasikan hanya dengan scope (Matrix #33/#35), pagination, dan sort §7; tanpa filter. Sort allowlist (`habit_date`,`id`) = technical decision (SoT tidak menyebut field sortable). GAP GET-1 RESOLVED via perubahan kontrak (Keputusan Tim 24 Sep 2026): `student_id` (saja) ditambahkan ke response GET habit/bermasyarakat entries. Gap filter `date` (dulu ditunda) — lihat NOTE-09.

## NOTE-09 (25 Sep 2026) — Addendum: Habit GET Response & Date Filter

**Keputusan Tim (25 Sep 2026)**, menindaklanjuti GAP GET-1 di atas. Dokumen lengkap:
`docs/addendum/API-Contract-v1.2-Addendum-Habit-GET.md`.

Ringkasan:
- `student_id` di response GET (habits/entries, habits/entries/{id}, bermasyarakat/entries) —
  **sudah diimplementasikan sejak GAP GET-1**, dikonfirmasi tetap seperti itu (bukan perubahan baru).
  Tidak ada `student_name`/`nisn`/nested Student/data rombel.
- Filter opsional `?date=YYYY-MM-DD` — **baru diimplementasikan** (`ListHabitEntriesRequest` +
  `StudentHabitService::applyDateFilter()`), untuk `habits/entries` dan `bermasyarakat/entries`.
  Filter murni mempersempit hasil DALAM scope yang sudah ditentukan (Teacher own active rombel /
  Student own) — tidak pernah menentukan/mengubah authorization scope. Format salah → 422.
- `?student_id=...` dan `?rombel_id=...` sebagai query filter — **DITOLAK, tidak diimplementasikan**
  (client parameter tidak boleh menjadi penentu authorization scope; belum ada kebutuhan eksplisit
  di SoT).
- Habit entry tetap **immutable** (NOTE-08) — tidak ada PATCH/DELETE/Daily Lock akibat perubahan ini.
- Tidak ada migration baru — `student_id` (kolom `student_id` di `student_habits`) sudah ada sejak
  schema awal; ini murni perubahan response/filtering di layer API.

Test: `tests/Feature/Student/StudentHabitGetFilterTest.php` (10 test case: date filter habit &
bermasyarakat, scope Teacher/Student/cross-rombel, `student_id` di response, 404 detail di luar
scope, invalid date format, `student_id` query diabaikan, tidak ada route PATCH/DELETE).

## STUDENT-LIFECYCLE-001 (dependency: STUDENT-ASSIGNMENT-001, TEACHER-ASSIGNMENT-001, STUDENT-001, AUTH-001/002 — semua ✅)

`StudentLifecycleService` (`markInactive()`, `markGraduated()`, `bulkGraduate()`) +
`StudentLifecycleController` + route, sesuai API Contract v1.2 §20, Dokumen v4.0 §6.3,
Matrix #41-43. Reuse penuh `TeacherScopeService::activeAssignment()` dan
`StudentClassAssignmentService::closeActiveAssignment()`.

### NOTE-07 — RESOLVED (Keputusan Tim, 24 Sep 2026)

CONFLICT awal:

```
SOURCE A (Dokumen v4.0 §5.3, diimplementasikan di StudentService::studentInTeacherActiveRombel(),
          STUDENT-001): scope Teacher atas Student = assignment AKTIF Student saat ini
          (Student.activeClassAssignment.rombel_id === Teacher.activeAssignment.rombel_id).
SOURCE B (Task Board AC STUDENT-LIFECYCLE-001; API Contract v1.2 §15 pola Teacher Lifecycle
          Matrix #61, dianalogikan): pemanggilan ulang endpoint lifecycle pada state yang
          sama HARUS idempotent 200 tanpa error.
IMPACT: begitu /inactive atau /graduate berhasil sekali, student_class_assignments aktif
        Student ditutup (status 'ended') -> activeClassAssignment jadi null -> pemanggilan
        ulang (skenario idempotent yang diminta SOURCE B) otomatis gagal scope-check
        SOURCE A (403), bukan 200 idempotent. Dua requirement SoT ini tidak bisa dipenuhi
        bersamaan pakai satu basis scope yang sama.
```

Keputusan Tim (user, 24 Sep 2026 — bukan kutipan literal SoT, SoT tidak membahas edge case ini):

1. Scope Teacher terhadap Student TETAP berbasis active assignment (tidak berubah dari STUDENT-001).
2. Assignment yang sudah `ended` TIDAK boleh jadi dasar authorization untuk GET/PATCH Student
   biasa setelah Student inactive/graduated — `StudentService::studentInTeacherActiveRombel()`
   **TIDAK diubah**.
3. Idempotency lifecycle **dipisah** dari authorization Student biasa: kalau Student SUDAH di
   target state (`inactive`/`graduated`), request lifecycle → 200 idempotent tanpa butuh
   `activeClassAssignment` lagi, tanpa efek tambahan.
4. Untuk transisi state yang BENAR-BENAR terjadi (belum di target state), Teacher tetap wajib
   lolos scope active-rombel penuh seperti biasa (403 kalau tidak lolos).
5. Pada idempotent path tetap wajib **school-scope check** (`Student.school_id === Teacher.school_id`)
   supaya Teacher tidak bisa dapat 200 untuk Student dari sekolah lain.
6. `studentInTeacherActiveRombel()` **TIDAK** diubah jadi berbasis assignment terakhir/ended.
7. Scope Teacher **TIDAK** dilonggarkan ke level sekolah untuk kasus umum (GET/PATCH biasa).

Konsekuensi yang disadari & diterima secara eksplisit: GET/PATCH Student yang sudah
inactive/graduated **tetap tidak otomatis accessible** oleh Teacher yang dulu memilikinya —
ini bukan bug, melainkan hasil langsung dari poin 2 & 7 di atas. Tidak ada perluasan
authorization hanya untuk menyelesaikan masalah idempotency (poin 7).

### Konvensi 403/404 tambahan yang diterapkan (bukan literal SoT, penerapan §26)

Teks API Contract §20 ("404 — Student/Rombel tidak ditemukan pada scope requester") sedikit
ambigu untuk kasus **Rombel** di `bulk-graduate` (bisa dibaca sebagai "di luar scope -> 404").
Diterapkan konsisten dengan konvensi umum §26 (write-op, scope jelas dari path): Rombel/Student
id benar-benar tidak ada → 404; ada tapi di luar own active rombel Teacher → 403. Ini pola yang
sama dipakai konsisten di STUDENT-ASSIGNMENT-001 dan STUDENT-001.

## NOTE-08 (24 Sep 2026) — Reconciliation: Habit submission IMMUTABLE, GAP #1 dicabut

**Koreksi atas GAP #1 STUDENTHABIT-001 di atas.** Review sebelumnya sempat mengangkat konsep "edit
sebelum daily lock" (API Contract v1.2 §21: *"Tanggal sebelum hari ini: view-only... PATCH ditolak
untuk entry ber-habit_date < hari ini server, atau setelah daily lock"*) sebagai kemungkinan fitur
yang hilang dari implementasi POST-only ini.

**Keputusan Bisnis Final (Keputusan Tim, 24 Sep 2026) — mencabut/reconcile teks tersebut:**

Habit submission bersifat **IMMUTABLE**. Setelah Student submit:
- Tidak ada PATCH/edit entry, tidak ada DELETE — tidak ada mekanisme koreksi mandiri sama sekali.
- Submit ulang habit yang sama pada tanggal yang sama → **409 `duplicate_habit_entry`** (bukan diblokir
  lewat konsep "lock").
- Poin dihitung sekali saat submission; tidak ada mekanisme reversal/edit poin.

**Konsekuensi reconciliation:**
- Konsep "edit-before-daily-lock" pada teks API Contract §21 **tidak berlaku** untuk business rule final
  — teks tersebut adalah requirement versi lama yang belum disinkronkan ulang ke keputusan immutability ini.
- Error code `habit_already_locked` **tidak diperlukan** — tidak pernah ada state "locked" karena tidak
  pernah ada state "editable" untuk di-lock.
- Tidak ada cutoff time yang perlu didefinisikan (tidak "dikarang") — pertanyaan itu **gugur**, bukan
  dijawab, karena premisnya (entry yang editable) sudah tidak berlaku.
- Implementasi POST/GET yang sudah ada **tidak berubah** — sudah sejak awal konsisten dengan model
  immutable ini (unique constraint `uq_student_habit_date` → 409 duplicate, tidak ada route PATCH/DELETE
  yang pernah dibuat).

**Status akhir:** STUDENTHABIT-001 dinyatakan lengkap tanpa PATCH/DELETE. GAP #1 (baris di atas) dianggap
**RESOLVED via reconciliation**, bukan gap terbuka.
