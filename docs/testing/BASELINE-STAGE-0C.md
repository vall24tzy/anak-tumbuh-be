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
| Full suite setelah residual F-14 (172 + 15 test baru) | **187 passed, 873 assertions** |
| Full suite setelah test F-12 (187 + 3 test baru) | **190 passed, 895 assertions** |
| Full suite setelah fix F-46 (190 + 8 test baru) | **198 passed, 938 assertions** (baseline regresi terkini) |

Inventaris statis (jumlah method test per file), dihitung ulang dari repo pada state setelah fix F-46, berjumlah **198**, sama dengan total run (198 passed), sehingga tidak ada test yang terlewat atau ter-skip. Rinciannya: 142 (baseline Stage 0-B) + 30 (Stage 0-C) + 15 (residual F-14) + 3 (F-12) + 8 (F-46).

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
| `Feature/Auth/AuthLoginLogoutTest` | 11 | Stage 0-C (F-22) |
| `Feature/Auth/AuthMeTest` | 3 | Stage 0-C (F-27, F-26) |
| `Feature/Auth/PasswordRoleMatrixTest` | 10 | Stage 0-C (F-28) |
| `Feature/Dashboard/DashboardAccessTest` | 3 | Stage 0-C (F-42) |
| `Feature/School/ShowCrossSchoolScopeTest` | 3 | Stage 0-C (F-51) |
| `Feature/Auth/ImportJobAuthorityTest` | 8 | Residual F-14 (job import Guru/Siswa) |
| `Feature/Auth/ApiTestRouteGateTest` | 7 | Residual F-14 (`/api/test`) |
| `Feature/School/SchoolStoreStatusTest` | 3 | F-12 (`POST /schools`: 201 untuk Super Admin; 403 untuk Headmaster, Teacher, Student) |
| `Feature/Student/StudentUpdatePhotoMultipartTest` | 8 | F-46 (`PATCH /students/{id}` multipart + `_method=PATCH`) |
| **Total** | **198** | |

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

## 5. Catatan operasional

- **Deploy Stage 0-B: semua user login ulang sekali.** Setelah patch Stage 0-B, semua JWT yang terbit sebelum patch (tanpa claim `pwd`) ditolak dengan 401. Setiap user perlu login ulang satu kali saat deploy; umumkan ke tim dan FE sebelum rilis.
- **Status batch import bisa bernilai `failed`.** Job import (Guru/Siswa) yang dibatalkan karena pemilik batch tidak aktif atau hilang menandai batch `failed` (file upload dihapus). Nilai status ini belum didokumentasikan di SoT (kolom `import_batches.status` adalah string bebas) dan akan diselaraskan dengan F-37.
- **Rotasi `APP_KEY` mencabut semua sesi.** Claim `pwd` dihitung dengan HMAC yang memakai `APP_KEY` (`User::passwordFingerprint()`). Mengganti `APP_KEY` mengubah sidik jari semua user, sehingga semua JWT aktif ditolak 401 dan semua user harus login ulang. Jadwalkan rotasi seperti rilis Stage 0-B dan umumkan ke tim dan FE.
- **Menulis ulang `users.password` mencabut sesi user itu.** Sidik jari `pwd` diturunkan dari hash password, jadi setiap penulisan hash baru (change/reset password, atau rehash otomatis bila kelak diaktifkan; saat ini tidak ada rehash di kode) membuat semua token lama user tersebut ditolak 401.

## 6. Yang masih OPEN setelah Stage 0

Daftar ini bukan klaim selesai; setiap item tetap OPEN sampai kode diperbaiki dan test dijalankan hijau.

- **F-14:** scope resolver di service belum memfilter status profil (poin (c) remediation F-14). Jalur HTTP sudah ditutup middleware `AuthenticateActiveSession`. Rencana: digabung ke Stage 3 (keputusan penjadwalan; Matrix v3 §8 menempatkan F-14 di Stage 2/Grup S dan tidak menjadwalkan poin ini secara eksplisit).
- **F-14:** jendela balapan; Headmaster/Teacher yang di-inactive-kan saat job import sedang berjalan menyelesaikan job itu (gerbang hanya dicek di awal job).
- **F-37:** exception di tengah job masih menahan batch di `processing` dan file upload tidak terhapus.

## 7. Terverifikasi di luar urutan stage

- **F-12: FIXED — VERIFIED.** `POST /schools` oleh Super Admin mengembalikan 201 dengan envelope `{data:{id,name,address,logo}}`. Kode diubah di `SchoolController::store()` pada commit `7396a1a` (judul commit "test(postman): TEST-001 ..."), dan kini dikunci oleh `SchoolStoreStatusTest` (3 test; run verifikasi: full suite 190 passed, 895 assertions; baseline terkini: 198 passed, 938 assertions). Headmaster, Teacher, dan Student ditolak 403. Status belum CLOSED. Matrix v3 masih mencatat perilaku lama (200) dan F-12 masih terdaftar di bundel School Stage 3; keduanya perlu disesuaikan di Stage 3.
- **F-46: FIXED — VERIFIED.** `PATCH /students/{id}` multipart (method-spoofing POST + `_method=PATCH`) tidak lagi 422 `protected_field` karena field `_method`. Kode diubah di `UpdateStudentRequest::prepareForValidation()` (properti `$ignored`) pada commit `33d444e`, dan kini dikunci oleh `StudentUpdatePhotoMultipartTest` (8 test; full suite 198 passed, 938 assertions). Yang dikunci: foto valid 200 dan tersimpan, replace foto menghapus file lama, update `name` tanpa foto, method-spoofing lewat query string, field terlarang lain tetap 422 `protected_field` tanpa `_method` ikut dilaporkan, foto bukan gambar tetap 422 `validation_failed`, Teacher rombel lain 403, Headmaster 403, dan tanpa token 401. Status belum CLOSED. Cek manual Postman TEST-002 9.07/9.08 (bagian acceptance F-46 di Matrix) belum dilakukan. Test butuh ekstensi PHP `gd` dan terikat ke `Storage::fake('public')` serta jalur `students/{id}.jpg`; sesuaikan saat F-06 (foto Student private), jangan dianggap regresi F-46.
