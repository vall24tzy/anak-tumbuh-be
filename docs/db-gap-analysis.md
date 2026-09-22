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
