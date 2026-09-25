# STUDENTHABIT-001 — Log (POST + GET)

**Status:** POST dan GET selesai di kode (6-habit + Bermasyarakat). Belum diuji runtime (lihat bagian Test).
**PIC:** Orang 1
**Tanggal:** 24 Sep 2026

## Ringkasan

- Migration `student_habits` di-rewrite total (Fresh, tanpa ALTER): habit_date, classroom,
  initiative (NOT NULL CHECK IN mandiri/disuruh) ditambah; description/poin-snapshot/is_mandiri
  lama dihapus; unique (student_id,habit_id,habit_date).
- Migration baru `student_habit_indicators` (pivot Bermasyarakat).
- **Bug ditemukan & diperbaiki sendiri saat implementasi:** habit_id/indicator_id FK sempat
  ditulis pakai `foreignId()` (default bigint), padahal `habits.id` = smallIncrements dan
  `indicators.id` = increments (int biasa) — akan gagal migrate (FK type mismatch di Postgres).
  Diperbaiki jadi `unsignedSmallInteger`/`unsignedInteger` + `->foreign()` eksplisit, konsisten
  dengan pola yang sudah dipakai `indicators.habit_id` (HABIT-001).
- `StudentHabitService`: `storeHabitEntry()` (6 habit) & `storeBermasyarakatEntry()` (multi-select).
  Reuse `Student::activeClassAssignment` untuk classroom snapshot (tidak duplikasi query).
- `habit_date` dihitung `Carbon::now('Asia/Jakarta')`, eksplisit tidak bergantung `config('app.timezone')`.
- GAP #2 (Keputusan Tim 24 Sep 2026): habit_id Bermasyarakat dikirim ke endpoint 6-habit ->
  422 `wrong_habit_endpoint`.
- GAP #1: `habit_already_locked` sengaja TIDAK diimplementasikan di POST (didokumentasikan
  sebagai inkonsistensi kontrak, bukan bug).
- `daily_limit_exceeded` tetap diimplementasikan walau struktural tidak mungkin terlampaui
  (7 habit_id tetap + unique constraint) — sesuai instruksi "kejelasan kontrak" di DB Design.
- Error code dari kontrak (dipakai apa adanya): `unknown_indicator`, `mutually_exclusive_violation`,
  `daily_limit_exceeded`, `field_not_accepted` (description -> `error.code` = `field_not_accepted`, bukan
  `validation_failed`). Error code yang disetujui tim: `wrong_habit_endpoint` (GAP #2).
- Technical decision (Keputusan Tim 24 Sep 2026): `duplicate_habit_entry` (HTTP 409) = machine-readable code
  untuk duplicate yang sudah ditetapkan kontrak. `too_many_indicators` DIBATALKAN — >3 indikator positif
  memakai validation failure standar (422 `validation_failed`), tanpa error code custom.
- Duplicate `indicator_ids` ([21,21]) -> 422 `validation_failed` (rule `distinct`), TIDAK di-dedupe diam-diam.
- Review patch (24 Sep 2026): (1) `description` sebelumnya menghasilkan `error.code=validation_failed`
  -> diperbaiki jadi `field_not_accepted` via `prepareForValidation()`; (2) rule `exists:indicators,id`
  dihapus dari kedua Form Request supaya id tidak dikenal -> `unknown_indicator` (bukan validation_failed);
  (3) race check-then-insert: `UniqueConstraintViolationException` sekarang -> 409 `duplicate_habit_entry`
  (sebelumnya 500), transaksi rollback jadi `students.poin` tidak dobel; (4) perbandingan `habit_id`
  di-cast int.

## Routes

- `POST /api/v1/habits/{habit_id}/entries` — role student
- `POST /api/v1/bermasyarakat/entries` — role student (file baru `routes/v1/bermasyarakat.php`,
  di-wire ke `routes/api.php`)

## GET (audit Matrix + kontrak, Keputusan Tim 24 Sep 2026)

Audit dasar: Matrix #33 (Habit Monitor/View: Teacher ALLOW active rombel, Student ALLOW own, SA/HM DENY),
#35 (Daily lock/Historical view: tanggal lampau tetap terbaca Teacher/Student), #36/#37 (Bermasyarakat = Submit
& Validate/Score saja, tidak ada baris View khusus -> GET Bermasyarakat mengikuti pola #33 sesuai API Contract §22).
API Contract §21/§22 + Task Board: tidak ada filter query di endpoint ini; berlaku konvensi list umum §7.

- `GET /api/v1/habits/entries` dan `/{id}` — role:teacher,student (SA/HM -> 403). **Tidak memuat Bermasyarakat**
  (shape `indicator_id` vs `indicator_ids[]`); `/{id}` untuk entry Bermasyarakat / di luar scope -> 404 (§26).
- `GET /api/v1/bermasyarakat/entries` — list saja (§22 tidak mendefinisikan `/{id}`), shape `indicator_ids[]`.
- Scope Teacher = Student yang SAAT INI punya assignment aktif di own active rombel (`TeacherScopeService` +
  `student_class_assignments`), BUKAN `student_habits.classroom` (snapshot historis, bukan dasar authorization).
  Teacher tanpa active rombel -> list kosong. Student = own.
- Pagination `page` (default 1), `per_page` (default 20, max 100), meta `{page, per_page, total, total_pages}` (§7).
- Sort `sort` + `order` (§7). Default `habit_date desc`; bila sort=habit_date, tie-breaker `id` dengan arah yang sama (pagination stabil). Technical decision: allowlist sort
  = field yang ada di response (`habit_date`, `id`) — SoT tidak menyebut daftar field sortable. Nilai di luar allowlist -> 422.
- TIDAK ada filter `date`/`student_id`/`rombel_id`. Query param lain diabaikan (tidak ditolak).
- `poin_awarded` di GET dihitung ulang dari data tersimpan (indicator/initiative), tidak dipersist; formula
  scoring sekarang satu sumber (`habitPoin()` / `bermasyarakatPoin()`) dipakai POST dan GET.

## Gap / catatan (butuh keputusan bila ingin diubah)

- **GAP GET-1 RESOLVED via perubahan kontrak (Keputusan Tim 24 Sep 2026):** response GET `/habits/entries`,
  `/habits/entries/{id}`, `/bermasyarakat/entries` ditambah `student_id` SAJA (tanpa student_name/nisn/nested object).
  Ini menyimpang dari shape literal API Contract v1.2 §21/§22 -> **API Contract perlu addendum resmi** (belum ditulis
  di dokumen kontrak). Response POST TIDAK berubah. Authorization tidak berubah (TeacherScopeService + assignment
  aktif; Student own; classroom snapshot bukan boundary; `/{id}` di luar scope -> 404).
- **API-contract gap terpisah (belum diisi, tidak ada dasar SoT):** filter `date` (dan `student_id`/`rombel_id`) untuk
  GET habit entries. Sementara, melihat entry per hari hanya lewat sort + pagination (Matrix #35 tetap terpenuhi).
  Perlu keputusan/addendum kontrak sebelum diimplementasikan.

- GAP #5 RESOLVED (Keputusan Tim 24 Sep 2026): snapshot `classroom` memakai
  `StudentClassAssignmentService::activeAssignmentInCurrentAcademicYear()` = assignment status 'active' pada
  Academic Year active milik school Student (deterministik: 1 AY aktif/school + 1 assignment aktif/(student,AY)).
  Tanpa fallback ke assignment ended / AY lain; NULL bila tidak ada. Skema & constraint TIDAK diubah.
  Catatan: `StudentService`/`StudentClassAssignmentService` masih memakai `Student::activeClassAssignment`
  (hasOne tanpa konteks tahun) untuk scope Teacher — di luar scope task ini, tidak diubah.
- `too_many_indicators` praktis tidak terjangkau (hanya 3 indikator positif); guard tetap ada sebagai validation failure.
- Trigger DB (indicator_id / mutual exclusion) tidak dibuat — opsional di DB Design §12, validasi utama di Service.

## Test (manual review — tidak ada PHP/DB live di sandbox, perlu dijalankan di dev env)

### Positive
- POST 6-habit, indicator_id milik habit tsb, initiative=mandiri -> 201, poin_awarded =
  indicator.point + 10, students.poin bertambah sesuai
- POST Bermasyarakat 3 indikator positif + initiative=mandiri -> 201, poin_awarded = 60
- POST Bermasyarakat "Kurang bermasyarakat" sendirian + initiative=disuruh -> 201, poin_awarded = 10
- classroom terisi dari rombel aktif Student saat entry dibuat

### Negative
- POST ke habit_id Bermasyarakat lewat `/habits/{id}/entries` -> 422 wrong_habit_endpoint
- POST indicator_id milik habit lain -> 422 unknown_indicator
- POST 6-habit tanpa initiative -> 422 validation_failed
- POST description terisi -> 422, error.code = field_not_accepted
- POST duplikat (student,habit,tanggal sama) -> 409 duplicate_habit_entry
- POST Bermasyarakat "Kurang bermasyarakat" + indikator lain -> 422 mutually_exclusive_violation
- POST Bermasyarakat >3 indikator positif (bila terjangkau) -> 422 validation_failed (tanpa code custom)
- POST Bermasyarakat indicator_ids kosong -> 422 (Form Request, min:1)
- POST indicator_id tidak ada sama sekali -> 422 unknown_indicator
- POST Bermasyarakat indicator_ids berisi id tidak ada / milik habit lain -> 422 unknown_indicator
- 2 request POST bersamaan (student,habit,tanggal sama) -> satu 201, satu 409; students.poin hanya bertambah sekali
- Classroom snapshot: pindah rombel setelah entry -> classroom di entry lama tidak berubah
- Classroom snapshot: Student punya assignment aktif di AY lama & AY aktif -> classroom = rombel AY aktif
- Classroom snapshot: tidak ada active AY di school / tidak ada assignment aktif di AY aktif -> classroom NULL, entry tetap 201
- POST Bermasyarakat indicator_ids [21,21] atau [21,22,22] -> 422 validation_failed (bukan dedupe)
- Teacher/Headmaster coba POST -> 403 (middleware role:student)

### GET
- Student GET /habits/entries -> hanya entry milik sendiri, tanpa Bermasyarakat, poin_awarded sesuai formula (35 utk tier 25 + mandiri)
- Teacher GET /habits/entries -> hanya entry siswa di own active rombel; Teacher tanpa active rombel -> data [] total 0
- Teacher/Student GET /habits/entries/{id} di luar scope atau id entry Bermasyarakat -> 404
- Headmaster / Super Admin GET /habits/entries dan /bermasyarakat/entries -> 403
- per_page=101 / page=0 / sort=poin / order=up -> 422 validation_failed; ?date=... diabaikan (bukan filter)
- Pagination: meta.total_pages benar; urutan default habit_date desc konsisten antar halaman
- GET /bermasyarakat/entries -> indicator_ids[] + poin_awarded (60 / 25 / 20 sesuai formula)
- Tidak ada PATCH/DELETE /habits/entries/{id} (405/404)

**Belum dijalankan**: sandbox tidak punya PHP/Postgres. Hanya static/code audit + `git apply --check` + `git diff --check`. Migration (`migrate:fresh`) dan PHPUnit/Postman WAJIB dijalankan di dev environment.

## Test Plan Runtime (WAJIB di dev environment sebelum merge — belum dijalankan)

Status: **belum runtime-tested / belum fully verified.** Yang sudah: `git apply --check` PASS, `git diff --check` PASS, code audit (statis).

1. `php artisan migrate:fresh --seed` sukses (urutan migration: student_habits sebelum student_habit_indicators; FK smallint/int cocok; CHECK initiative).
2. PHPUnit suite yang ada tetap hijau (regresi Student/Assignment/Habit).
3. Postman/Feature test — authorization & scope:
   - POST: Student 201; Teacher/Headmaster/SA 403. GET: Teacher & Student 200; Headmaster/SA 403.
   - Teacher hanya melihat entry Student di own active rombel; Teacher tanpa active rombel -> data [].
   - GET `/habits/entries/{id}` di luar scope / entry Bermasyarakat -> 404.
4. Duplicate: POST habit sama hari yang sama -> 409 `duplicate_habit_entry`.
5. Race: 2 POST paralel (student, habit, tanggal sama) -> satu 201, satu 409; `students.poin` bertambah sekali.
6. Scoring: 6 habit tier 25 + mandiri = 35; Bermasyarakat 3 positif + mandiri = 60, 2 + disuruh = 25, Kurang bermasyarakat + mandiri = 20.
7. Validasi: initiative kosong/invalid 422; description 422 `field_not_accepted`; note >1000 422; `indicator_ids` duplikat / kosong 422 `validation_failed`; Kurang + lain -> 422 `mutually_exclusive_violation`; habit_id Bermasyarakat di endpoint 6-habit -> 422 `wrong_habit_endpoint`.
8. `classroom` snapshot dengan Academic Year berbeda: Student punya assignment aktif di AY lama & AY aktif -> snapshot = rombel AY aktif; tanpa AY aktif/assignment -> NULL (entry tetap 201); pindah rombel setelah entry -> classroom entry lama tidak berubah.
9. Response `student_id`: ada di ketiga GET; TIDAK ada di response POST; tidak ada field student lain.
10. Pagination/sort: default `habit_date desc`; sort=habit_date&order=asc; tie-breaker `id` searah -> tidak ada duplikat/loncat antar halaman; `per_page=101`, `page=0`, `sort=poin`, `order=up` -> 422; `?date=` / `?student_id=` diabaikan (bukan filter).

## NOTE-08 (24 Sep 2026) — Reconciliation: Habit submission IMMUTABLE

Koreksi keputusan bisnis final: habit submission **immutable**, tidak ada PATCH/DELETE, tidak ada
mekanisme "daily lock" berbasis waktu. Submit ulang habit sama di tanggal sama → 409
`duplicate_habit_entry` (sudah diimplementasikan via unique constraint sejak awal). Detail & alasan
reconciliation di `docs/db-gap-analysis.md` NOTE-08. **Tidak ada perubahan kode** — implementasi
POST/GET yang sudah ada tetap dipertahankan apa adanya, sudah konsisten dengan keputusan ini sejak awal.
