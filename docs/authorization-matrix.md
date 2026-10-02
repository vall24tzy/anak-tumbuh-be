# Authorization Matrix v5.2 — Audit Implementasi (AUTHORIZATION-MATRIX)

Dikerjakan: 29 Sep 2026. Memetakan 61 baris `Authorization_Matrix_ANAKTUMBUH_v5_2_FINAL.xlsx` ke
endpoint API Contract v1.2 + Controller/Service yang menegakkannya, lalu diaudit langsung ke kode
(bukan cuma baca middleware role) apakah scope (own school / own active rombel / own data)
benar-benar ditegakkan di server. Metode: pembacaan kode manual (tidak ada PostgreSQL/PHP live di
sandbox ini untuk menjalankan request sungguhan) — status "Verified" berarti "logikanya secara
pembacaan kode benar", bukan "sudah dites jalan".

Dependency task ini (SCHOOL-001, HEADMASTER-001, TEACHER-001, STUDENT-001, ACADEMICYEAR-001,
ROMBEL-001, TEACHER-ASSIGNMENT-001, TEACHER-LIFECYCLE-001, STUDENT-ASSIGNMENT-001,
STUDENT-LIFECYCLE-001, AUTH-003) — semua dikonfirmasi ada di git log sebelum audit ini dimulai.

## Ringkasan

- **61/61 baris** dipetakan ke endpoint + kode yang menegakkannya.
- **58 baris**: perilaku kode sudah konsisten dengan Matrix, tidak ada temuan.
- **3 temuan** dilaporkan di Bagian "Temuan" di bawah (2 deviasi yang sudah terdokumentasi via
  Keputusan Tim sebelumnya, 1 potensi bug baru) — **tidak saya perbaiki sendiri** di task ini, sesuai
  instruksi untuk melaporkan dulu.

## 1. School (#1–2)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 1 | Create (SA only, global) | `POST /schools` | `SchoolController`/`SchoolService`, route `role:super_admin` | Role middleware saja sudah cukup (scope = global, tidak ada school_id lain untuk dicek) |
| 2 | Import — REMOVED | — | — | Tidak ada route `schools/import` di manapun |

## 2. Headmaster (#3–4)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 3 | Create (SA only) | `POST /headmasters` | `HeadmasterService::store()`, route `role:super_admin` | Suksesi (nonaktifkan HM aktif lama) di 1 transaksi; scope global sesuai Matrix |
| 4 | Import — REMOVED | — | — | Tidak ada route `headmasters/import` |

## 3. Teacher (#5–6)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 5 | Create (HM only, own school) | `POST /teachers` | `TeacherService::store()` | `(int) $data['school_id'] !== $schoolId` → 403 |
| 6 | Import (HM only, own school) | `POST /teachers/import` | `TeacherImportService::commit()` | `school_id` diambil dari akun HM yang login, TIDAK PERNAH dari input file/request — tidak ada jalur cross-school sama sekali (lebih ketat dari Student Import yang masih terima `rombel_id` dari request) |

## 4. Student (#7–12)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 7 | Create (Teacher, own active rombel) | `POST /students` | `StudentService::store()` | `TeacherScopeService::activeAssignment()` + `rombel_id` harus sama dgn assignment aktif → 403 |
| 8 | Import (Teacher, own active rombel) | `POST /student-imports/commit` | `StudentImportService::assertOwnActiveRombel()` | Divalidasi SEKALI di awal (403 utk seluruh request bila salah) |
| 9 | View (SA global; HM school; Teacher active rombel; Student OWN) | `GET /students`, `GET /students/{id}` | `StudentService::index()/show()/canView()` | 4 cabang role persis sesuai Matrix; GET-by-id scope mismatch → 404 (§26); tidak ada classmates list (Student hanya lihat diri sendiri) |
| 10 | Update field administratif (Teacher, own active rombel) | `PATCH /students/{id}` | `StudentService::update()` | `studentInTeacherActiveRombel()` via `hasActiveAssignmentInRombel()` (deterministik, GAP #5-scope) |
| 11 | Protected fields DENY semua | `PATCH /students/{id}` | `UpdateStudentRequest` | Field selain `name`/`gender`/`photo` → 422 `protected_field` (NOTE-06: field `name`, bukan `fullname`) |
| 12 | Hard Delete DENY semua | — | — | Tidak ada route `DELETE /students/{id}` |

## 5. Master Rombel (#13–16)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 13–15 | Create/Update/Delete (HM only, own school) | `POST/PATCH/DELETE /rombels` | `RombelService` | `assertRombelInScope()` 403 utk write; Delete → 409 `rombel_has_active_assignment` via FK constraint (bukan ditebak, benar2 FK RESTRICT + catch QueryException 23503) |
| 16 | View (HM own school; Teacher own school/rombel) | `GET /rombels` | `RombelService::index()` | Scope by `school_id`, Teacher pakai `$user->teacher?->school_id` |

## 6. Academic Year (#17–19)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 17–18 | Create/Update (HM only, own school) | `POST/PATCH /academic-years`, `.../activate` | `AcademicYearService` | `assertAcademicYearInScope()`; `activate()` menonaktifkan yg lama dlm 1 transaksi (partial unique index) |
| 19 | View (HM own school; Teacher own school) | `GET /academic-years` | `AcademicYearService::index()` | Scope by `school_id` |

## 7. Teacher Class Assignment (#20–22)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 20–22 | Create/Update/Deactivate (HM only, own school+year) | `POST/PATCH /teacher-class-assignments`, `.../deactivate` | `TeacherAssignmentService` | `school_id` + `teacher` harus sama sekolah; 409 `teacher_already_has_active_assignment`; rombel divalidasi sesuai sekolah+tahun (422 `rombel_mismatch`) |

## 8. Student Class Assignment (#23–25)

| # | Action | Endpoint | Ditegakkan oleh | Verifikasi |
|---|---|---|---|---|
| 23–24 | Create/Update (Teacher, own active rombel) | `POST /student-class-assignments`, `PATCH .../{id}` | `StudentClassAssignmentService` | 409 `duplicate_assignment`/`assignment_conflict`; PATCH hanya `{status:ended}` (NOTE-05, Keputusan Tim), field lain → 422 `field_not_accepted`, tidak ada auto-move |
| 25 | View (SA/HM/Teacher/Student sesuai scope) | `GET /student-class-assignments`, `GET /students/{id}/class-assignments` | `StudentClassAssignmentService::index()/historyForStudent()` | 4 cabang role; scope mismatch di GET-by-id → 404 |

## 9. Student Import (#26–31)

| # | Action | Ditegakkan oleh | Verifikasi |
|---|---|---|---|
| 26 | Validate/resolve NISN | `StudentImportService` → reuse `StudentService::store()` | NISN existing sekolah sama di-reuse, cross-school → 403 `cross_school_nisn` |
| 27 | **Resolve Rombel from Name** | — | **⚠️ Temuan #1** (lihat Bagian Temuan) — tidak diimplementasikan sesuai teks literal baris ini |
| 28 | Create/Reuse Student | `StudentImportService::processRow()` | Sama seperti #26, per baris |
| 29 | Unknown Rombel → reject | `StudentImportService::assertOwnActiveRombel()` | Karena `rombel_id` request-level (bukan per-row), ini jadi reject SELURUH request (403), bukan per-row — konsisten dgn desain #27 |
| 30 | Duplicate/Conflict Assignment | `StudentClassAssignmentService::createAssignment()` (reused) | 409 per baris, lewat teknik smuggling RuntimeException(json) ke `ImportService` generik |
| 31 | Partial Processing | `ImportService::generate()` (generik) | Baris valid tetap commit, baris invalid di `errors[]`, tidak menggagalkan seluruh request |

## 10. Habit (#32–35) & Bermasyarakat (#36–37)

| # | Action | Ditegakkan oleh | Verifikasi |
|---|---|---|---|
| 32 | Fill own habit (Student only) | `routes/v1/habit.php` `role:student` + `StudentHabitService` | POST scope OWN by JWT user, tidak ada parameter student_id yang bisa dimanipulasi |
| 33 | Monitor/View (Teacher active rombel; Student own) | `StudentHabitService::listHabitEntries()` | Teacher via `whereHas` assignment aktif; Headmaster → 403 (tidak di daftar role route) |
| 34 | Create/Edit on behalf DENY semua | — | Tidak ada route PATCH/DELETE habit entry untuk role manapun |
| 35 | **Daily lock / past-date view-only** | — | **⚠️ Temuan #2** — sudah SENGAJA dihapus oleh Keputusan Tim lanjutan (lihat Bagian Temuan), bukan celah baru |
| 36 | Submit Bermasyarakat (Student own) | `BermasyarakatController`/`StudentHabitService::storeBermasyarakatEntry()` | Native array, exclusive validation |
| 37 | Validate/Score — SYSTEM | `StudentHabitService` formula | Tidak ada endpoint manual, dihitung server saat submit |

## 11. Gamification & Ranking (#38–40)

| # | Action | Ditegakkan oleh | Verifikasi |
|---|---|---|---|
| 38 | View Level/Points (role scope; Student OWN) | `GamificationService::me()/forStudent()` | `/me` dibatasi `role:student` (Keputusan Tim NOTE-11 poin a — SA/HM/Teacher dipenuhi lewat endpoint `forStudent`, bukan `/me`); `GET /students/{id}/gamification` 4 cabang role, scope mismatch → 404 |
| 39 | View Ranking (Teacher active scope; Student cohort; HM/SA tidak ada) | `RankingService::rombel()/angkatan()` | `role:teacher,student` di route; HM/SA → 403; assignment-based (join `student_class_assignments`+`rombels`), bukan classroom string |
| 40 | Apply +10 — SYSTEM | `StudentHabitService` (`initiative='mandiri'`) | Tidak ada endpoint manual `apply-inisiatif-mandiri` di manapun |

## 12. Student Lifecycle (#41–43)

| # | Action | Ditegakkan oleh | Verifikasi |
|---|---|---|---|
| 41–43 | Inactive/Graduate/Bulk Graduate (Teacher, own active rombel) | `StudentLifecycleService` | Scope via `hasActiveAssignmentInRombel()`; cascade tutup assignment 1 transaksi; bulk-graduate 403 utk rombel bukan milik Teacher |

## 13. Account (#44–50)

| # | Action | Ditegakkan oleh | Verifikasi |
|---|---|---|---|
| 44–46 | Initial password — SYSTEM | `TeacherService`/`HeadmasterService`/`StudentService::store()` | `Hash::make($nip)`/`Hash::make($nisn)` otomatis saat create, tidak ada endpoint manual set-password |
| 47 | Change Own Password (SA only) | `POST /auth/change-password`, route `role:super_admin` | Non-SA tidak bisa reach endpoint ini sama sekali |
| 48 | Reset Headmaster Password (SA only) | `HeadmasterService::resetPassword()`, route `role:super_admin` | Scope global sesuai Matrix |
| 49 | Reset Teacher Password (HM, own school) | `TeacherService::resetPassword()` | `findTeacherOrFail()` (404 kalau benar2 tidak ada) + cek `school_id` eksplisit → 403 kalau beda sekolah (fix 403/404 sebelumnya, commit `659cdb7`, sudah saya audit ulang — benar) |
| 50 | Reset Student Password (Teacher, own active rombel) | `StudentService::resetPassword()` | Reuse `studentInTeacherActiveRombel()` |

## 14. Authentication (#51–55)

| # | Action | Ditegakkan oleh | Verifikasi |
|---|---|---|---|
| 51 | Student Login QR | `AuthService::loginStudentQr()` | Signature HMAC + hash_equals; status inactive/graduated → 403 |
| 52 | Student Login Password | `AuthService::loginStudentPassword()` | nisn+password; status gate sama |
| 53 | Logout (semua role) | `POST /auth/logout`, route `jwt` (tanpa restriksi role) | Semua role terautentikasi bisa akses |
| 54 | Refresh Token — "—" (tidak ditetapkan) | — | Tidak ada route refresh di `auth.php` — konsisten "—" (bukan ALLOW/DENY eksplisit) |
| 55 | Forgot/Self-service Reset DENY semua | — | Tidak ada route `forgot-password` di manapun |

## 15. Cross-cutting (#56–60)

| # | Action | Verifikasi |
|---|---|---|
| 56 | Cross-school isolation | Diverifikasi PER SERVICE di Bagian 1–13 di atas — seluruh Service yang di-scope sekolah (Headmaster/Teacher/Rombel/AcademicYear/TeacherAssignment/Student/dst.) menegakkan `school_id` eksplisit, bukan cuma role middleware |
| 57 | Grade Advancement DENY | Tidak ada route/kode `promote`/`kenaikan`/grade-advancement di manapun (grep bersih) |
| 58 | Recreate Student identity DENY | `StudentService::store()` selalu reuse NISN existing, tidak ada jalur "create baru" utk NISN yang sudah ada |
| 59 | Class movement via CRUD DENY | `UpdateStudentRequest` menolak semua field placement (`protected_field`); pindah kelas hanya lewat Student Class Assignment API |
| 60 | Drop `student_habits.description` — SYSTEM | Migration `create_student_habits_table` tidak punya kolom `description` sama sekali (fresh deployment, bukan ALTER/DROP menyusul) |

## 16. Teacher Lifecycle (#61)

| # | Action | Ditegakkan oleh | Verifikasi |
|---|---|---|---|
| 61 | Mark Teacher Inactive (HM, own school) | `TeacherService::inactive()` | Cascade tutup `teacher_class_assignments` aktif 1 transaksi; tidak ada reactivation (tidak ada endpoint "activate" utk Teacher) — TAPI lihat **⚠️ Temuan #3** soal kode status scope-mismatch |

---

## Temuan (dilaporkan, belum diperbaiki di task ini)

### Temuan #1 — Row #27 "Resolve Rombel from Name" tidak diimplementasikan sesuai teks

```
SOURCE A: Matrix baris #27 menulis "FE boleh kirim nama; BE resolve + validate" — menyiratkan
          ada kolom nama Rombel per-baris di file Excel yang di-resolve BE.
SOURCE B: IMPORT-001 (sudah final, direview & disetujui sebelumnya) mendesain rombel_id sebagai
          parameter REQUEST-LEVEL (satu nilai utk seluruh file), bukan kolom Excel — karena Teacher
          cuma punya 1 active rombel per Academic Year (TEACHER-ASSIGNMENT-001), jadi "resolve per
          baris dari nama" dianggap tidak diperlukan. Template hanya punya Nama Lengkap/NISN/Gender.
IMPACT: Baris #27 secara harfiah tidak match implementasi — tapi bukan berarti requirement-nya
        dilanggar: "resolve + validate rombel" tetap terjadi, hanya levelnya per-request bukan
        per-baris (row #29 "unknown rombel" tetap tertangkap, hanya jadi reject-seluruh-request,
        bukan reject-per-baris).
STATUS: Sudah didokumentasikan sebelumnya di docs/db-gap-analysis.md (bagian IMPORT-001) sejak
        task itu dikerjakan — BUKAN temuan baru, dicatat ulang di sini supaya traceability Matrix
        eksplisit menunjuknya, bukan cuma tersembunyi di gap-analysis.
REKOMENDASI: Tidak ada aksi diperlukan kecuali tim ingin secara resmi meng-update redaksi Matrix
        baris #27 supaya tidak lagi menyiratkan "per baris".
```

### Temuan #2 — Row #35 "Daily lock / past-date view-only" sudah dihapus oleh Keputusan Tim lanjutan

```
SOURCE A: Matrix baris #35 & API Contract v1.2 §21 menulis "Unique student+habit+date; Asia/Jakarta;
          past dates view-only" — menyiratkan ada mekanisme lock/edit-window.
SOURCE B: Keputusan Tim "HABIT IMMUTABLE" (poin E, task GET-filter/addendum sebelumnya): "Setelah
          Student berhasil submit habit, entry FINAL. Tidak ada PATCH/edit habit entry... Tidak ada
          Daily Lock berbasis waktu... Temuan Daily Lock sebelumnya harus diperlakukan sebagai
          requirement lama yang perlu direkonsiliasi, bukan alasan membuat endpoint PATCH."
IMPACT: Tidak ada PATCH/edit endpoint sama sekali (dikonfirmasi: grep route bersih) — "past date
        view-only" tetap BENAR sebagai fakta (karena tidak ada cara edit sama sekali, utk tanggal
        manapun), tapi bukan karena ada mekanisme "lock" aktif — lebih tepat disebut "immutable sejak
        awal", bukan "terkunci setelah suatu waktu".
STATUS: Sudah didokumentasikan di docs/addendum/API-Contract-v1.2-Addendum-Habit-GET.md (poin E)
        sejak task itu dikerjakan — BUKAN temuan baru.
REKOMENDASI: Matrix baris #35 & API Contract §21 perlu diamandemen resmi mengganti "past dates
        view-only (daily lock)" menjadi "entry immutable sejak submit (tidak ada edit-window)" —
        redaksinya sudah tidak match kode sejak Keputusan Tim itu, cuma belum ditulis ulang di
        dokumen aslinya.
```

### Temuan #3 — `TeacherService::inactive()` pakai 404, bukan 403, untuk cross-school — DIKONFIRMASI TIM & DIPERBAIKI

```
SOURCE A: API Contract v1.2 Bagian 15 (Teacher Lifecycle, Idempotency & Error Cases) menulis:
          "403 — role selain Headmaster, atau Headmaster mencoba menonaktifkan Teacher di sekolah
          lain." — eksplisit 403 untuk skenario cross-school pada endpoint INI.
SOURCE A2: TEPAT SEBELUM baris itu, di paragraf yang sama, API Contract §15 juga menulis:
          "404 — Teacher tidak ditemukan atau di luar scope actor (cross-school)."
          Dua baris ini menjelaskan skenario yang SAMA PERSIS (cross-school) dengan status code
          berbeda, di paragraf yang sama. Ini BUKAN kasus "kode menyimpang dari kontrak yang bersih"
          seperti Temuan #1/#2 — API Contract §15 ITU SENDIRI sudah kontradiktif untuk skenario ini
          sejak teks itu ditulis, independen dari kode.
SOURCE B: Kode saat ini — TeacherService::inactive() memanggil $this->show($id), dan show()
          mengembalikan 404 (bukan 403) untuk Teacher di luar scope sekolah Headmaster. Komentar di
          kode sendiri menjelaskan show() SENGAJA 404 utk GET (§26, cegah enumerasi), sedangkan
          resetPassword() (endpoint lain, kasus serupa) SUDAH diperbaiki terpisah jadi 403 lewat
          findTeacherOrFail() + cek eksplisit (commit 659cdb7) -- tapi inactive() TIDAK ikut
          diperbaiki dengan pola yang sama, padahal ini juga endpoint WRITE dengan scope jelas dari
          path (bukan GET).
IMPACT: §15 kontradiktif untuk skenario ini, jadi tidak ada satu "kebenaran tunggal" di SoT untuk
        ditaati secara pasif — harus ada keputusan tim eksplisit untuk memenangkan salah satu baris.
KEPUTUSAN TIM (dikonfirmasi, bukan saya putuskan sendiri): 403 yang dipilih untuk MEMENANGKAN
        kontradiksi ini, bukan 404. Alasan:
        a. Konsistensi dengan resetPassword() — endpoint sejenis (WRITE, scope jelas dari path
           {id}) sudah diperbaiki ke 403 di commit 659cdb7; inactive() punya karakteristik persis
           sama, sehingga sebaiknya berperilaku sama untuk kasus yang sama.
        b. show() SENGAJA dipertahankan 404 HANYA untuk GET (cegah enumerasi lintas-scope, §26) —
           inactive() adalah endpoint WRITE, bukan GET, jadi alasan "cegah enumerasi" itu tidak
           relevan di sini; tidak ada justifikasi untuk meniru pola GET pada endpoint tulis ini.
        c. 403 memberi sinyal lebih jujur ke Headmaster yang salah ID (Teacher-nya memang ada di
           database, cuma beda sekolah) dibanding 404 yang menyamarkan sebagai "tidak ada sama
           sekali".
        404 TETAP berlaku untuk kasus Teacher id benar-benar tidak eksis di database manapun (baris
        pertama Idempotency & Error Cases di §15, yang tidak kontradiktif — tidak disentuh keputusan
        ini).
STATUS: Diperbaiki — lihat app/Services/School/TeacherService.php::inactive(), test
        tests/Feature/School/TeacherLifecycleScopeTest.php, dan
        docs/addendum/API-Contract-v1.2-Addendum-Teacher-Lifecycle-403.md untuk rekonsiliasi resmi
        kontradiksi §15 di atas.
```

## Catatan cakupan

- Verifikasi bersifat **static (pembacaan kode)**, bukan runtime. Belum ada request sungguhan yang
  dijalankan di task ini.
- DASHBOARD-001/002 tidak punya baris tersendiri di Matrix v5.2 (61 baris murni operasi CRUD/lifecycle
  inti), jadi tidak dicakup tabel di atas — sudah diaudit terpisah saat DASHBOARD-001/002 selesai.
