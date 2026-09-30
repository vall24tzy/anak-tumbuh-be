# BASELINE TEST — Stage 0-C

Sumber: `MASTER_AUDIT_REMEDIATION_MATRIX_v3_FINAL_DECISIONS.md` §8 (Stage 0-C — Baseline test) dan §5 (Test Gaps).
Dokumen ini mencatat hasil eksekusi nyata sebagai baseline regresi. Tidak ada klaim "lulus" yang tidak berasal dari run.

## 1. Environment

| Item | Nilai (dari repo) |
|---|---|
| PHP / PHPUnit | `^8.2` / `^10.5` (`composer.json`) |
| Database test | PostgreSQL wajib: skema memakai raw CHECK constraint dan partial unique index. `DB_CONNECTION=sqlite` di `phpunit.xml` sengaja di-comment. |
| Konfigurasi DB | `.env.testing` (gitignored): `DB_CONNECTION=pgsql`, `DB_DATABASE=anak_tumbuh_be_testing` |
| Override `phpunit.xml` | `APP_ENV=testing`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`, `BCRYPT_ROUNDS=4` |

**Bahaya environment (cek sebelum run pertama di mesin baru).** `.env.testing` masuk `.gitignore`. Pada clone baru tanpa file itu, Laravel jatuh ke `.env`, dan `RefreshDatabase` menjalankan `migrate:fresh` pada database di `.env` (development). Pastikan `DB_DATABASE` di `.env.testing` berbeda dari `.env` sebelum menjalankan test.

**Catatan penulisan test.** Ketiga route login memakai `throttle:5,1` dengan kunci yang sama per IP, jadi batasi maksimal 5 request login per test (lebih dari itu menjadi 429). Sequence Postgres tidak di-reset oleh `RefreshDatabase`, sehingga test tidak boleh bergantung pada nilai `id` absolut.

## 2. Hasil baseline (eksekusi nyata, dilaporkan Orang 2)

| Run | Hasil |
|---|---|
| Full suite (verifikasi Stage 0-B) | **142 passed, 531 assertions** |
| `SessionValidityTest` | **22 passed, 90 assertions** |
| Full suite setelah patch Stage 0-C (142 + 30 test baru) | **172 passed, 780 assertions** (baseline regresi berlaku untuk stage berikutnya) |
| Full suite setelah residual F-14 (172 + 15 test baru) | **187 passed, 873 assertions** (baseline regresi terkini) |

Inventaris statis (jumlah method test per file) berjumlah **142**, sama dengan total run, sehingga tidak ada test yang terlewat atau ter-skip.

| File | Test | Catatan |
|---|---:|---|
| `Unit/GamificationLevelDataTest` | 4 | LEVEL-RANKING-001 |
| `Feature/Gamification/GamificationLevelTest` | 10 | LEVEL-RANKING-001 |
| `Feature/Gamification/RankingTest` | 22 | LEVEL-RANKING-001 |
| `Feature/School/TeacherResetPasswordScopeTest` | 3 | |
| `Feature/School/TeacherImportTest` | 8 | Matrix v3 menulis "11 test method"; angka itu adalah jumlah `public function` termasuk 3 helper anonymous class. Test sebenarnya 8. |
| `Feature/Dashboard/DashboardSuperAdminTest` | 7 | |
| `Feature/Auth/SessionValidityTest` | 22 | Stage 0-B (F-14, F-24, F-29) |
| `Feature/Api/ApiErrorCodeTest` | 8 | Stage 0-A (F-45, F-47) |
| `Feature/School/AcademicYearWriteScopeTest` | 6 | |
| `Feature/School/RombelWriteScopeTest` | 6 | |
| `Feature/School/TeacherAssignmentWriteScopeTest` | 6 | |
| `Feature/Student/QrTokenTest` | 8 | |
| `Feature/Student/StudentHabitGetFilterTest` | 10 | |
| `Feature/Student/StudentImportTest` | 8 | |
| `Feature/Student/StudentLifecycleTest` | 10 | |
| `Feature/Student/StudentUpdateNameFieldTest` | 2 | |
| `Feature/ExampleTest`, `Unit/ExampleTest` | 1 + 1 | bawaan Laravel |
| **Total** | **142** | |

## 3. Register test gap (Matrix v3 §5)

| Gap | Status setelah Stage 0-C | Alasan / dependency |
|---|---|---|
| F-22 login/logout | **Sebagian ditutup** (`AuthLoginLogoutTest`) | Skenario F-01 dan F-23 menunggu fix masing-masing (finding OPEN). Gate F-14/F-24 sudah di `SessionValidityTest`. |
| F-27 `/auth/me` | **Sebagian ditutup** (`AuthMeTest`, termasuk kunci F-26 `id == users.id`) | Bentuk respons penuh menunggu F-25 (shape resmi belum didokumentasikan). |
| F-28 AUTH-003 | **Sebagian ditutup** (`PasswordRoleMatrixTest`) | Sisi positif dan skenario F-29 sudah di `SessionValidityTest`; HM→Teacher di `TeacherResetPasswordScopeTest`. |
| F-39 Import Guru | **Ditunda** | NIP kembar, file rusak, `.xls`, trim, async, dan isolasi status adalah perilaku F-32–F-38 yang masih OPEN. Test ditulis bersama fix-nya (aturan Matrix §8). |
| F-42 dashboard | **Sebagian ditutup** (`DashboardAccessTest`: akses/401 + isolasi lintas sekolah `headmaster`) | `dashboard/student` (F-40) dan `dashboard/teacher` (F-41) ditunda ke fix masing-masing. |
| F-51 `show()` lintas sekolah | **Ditutup** (`ShowCrossSchoolScopeTest`) | Rombel, AcademicYear, TeacherAssignment; HM dan Teacher. |
| SCHOOL-001, HEADMASTER-001, TEACHER-001, ACADEMICYEAR-001, ROMBEL-001, TEACHER-ASSIGNMENT-001, TEACHER-LIFECYCLE-001 | **OPEN** | Tercantum di §5 tanpa F-ID. Dari inventaris file, hanya sisi write-scope (403/404) yang punya test; skenario create/list/validasi/single-active/409/cascade belum. Isi test belum diaudit rinci; masuk backlog test terpisah. |

## 4. Aturan menjaga baseline

1. Setiap stage: jalankan full suite dan catat jumlah passed/assertions di sini atau di log stage.
2. Test baru bersifat additive; jangan mengubah assertion test lama untuk membuat suite hijau.
3. Test untuk perilaku finding yang masih OPEN ditulis bersamaan dengan fix-nya, bukan lebih awal (test merah menodai baseline).
4. Test yang gagal setelah patch dianggap bukti (bug test atau temuan produksi); jangan "diperbaiki" dengan melonggarkan assertion.
