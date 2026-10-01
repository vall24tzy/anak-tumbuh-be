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
| Full suite setelah fix F-46 (190 + 8 test baru) | **198 passed, 938 assertions** |
| Full suite setelah fix F-02 (198 + 5 test baru) | **203 passed, 976 assertions** |
| Full suite setelah fix F-01 (203 + 7 test baru) | **210 passed, 1013 assertions** |
| Full suite setelah fix F-05 (210 + 19 test run baru) | **229 passed, 1084 assertions** |
| Full suite setelah fix F-13 (229 + 7 test baru) | **236 passed, 1154 assertions** |
| Full suite setelah fix F-32 (236 + 13 test baru) | **249 passed, 1210 assertions** (baseline regresi terkini) |

Inventaris statis, dihitung ulang dari repo pada state setelah fix F-32, berjumlah **236 method test**. Dua file memakai dataProvider sehingga satu method menjadi beberapa test run: `StaffLoginRoleGateTest` (6 method → 7 run, +1) dan `UploadExtensionSafetyTest` (7 method → 19 run, +12). Jumlah test run = 236 + 1 + 12 = **249**, sama dengan total run (249 passed), sehingga tidak ada test yang terlewat atau ter-skip. Rincian per test run: 142 (baseline Stage 0-B) + 30 (Stage 0-C) + 15 (residual F-14) + 3 (F-12) + 8 (F-46) + 5 (F-02) + 7 (F-01) + 19 (F-05) + 7 (F-13) + 13 (F-32). `NipUsernameCollisionTest` dan `ImportStatusScopeTest` tidak memakai dataProvider (7 method = 7 run dan 13 method = 13 run); selisih 13 antara method dan test run seluruhnya berasal dari dua file di atas. Koreksi: versi sebelumnya menyebut 210 *method* untuk state setelah F-01; angka 210 itu adalah test run, sedangkan jumlah method saat itu 209 (`StaffLoginRoleGateTest` memakai dataProvider).

| File | Test run | Catatan |
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
| `Feature/School/AcademicYearActivateIdempotencyTest` | 5 | F-02 (`POST /academic-years/{id}/activate` idempoten) |
| `Feature/Auth/StaffLoginRoleGateTest` | 7 | F-01 (`POST /auth/login/staff` menolak akun Student); 6 method, 7 run karena dataProvider (`inactive`, `graduated`) |
| `Feature/Upload/UploadExtensionSafetyTest` | 19 | F-05 (ekstensi file upload dari konten, allowlist `jpg/png/webp`); 7 method, 19 run karena dataProvider (9 nama klien berbahaya + 5 kasus konten menentukan ekstensi) |
| `Feature/School/NipUsernameCollisionTest` | 7 | F-13 (NIP bentrok dengan `users.username` role lain: Create Headmaster, Create Teacher, Import Teacher); 7 method = 7 run |
| `Feature/Auth/ImportStatusScopeTest` | 13 | F-32 (status batch import hanya terbaca pemilik; `GET /teachers/import/status/{id}` dan `GET /student-imports/status/{id}`); 13 method = 13 run |
| **Total** | **249** | 236 method; 249 test run |

## 3. Register test gap (Matrix v3 §5)

| Gap | Status setelah Stage 0-C | Alasan / dependency |
|---|---|---|
| F-22 login/logout | **Sebagian ditutup** (`AuthLoginLogoutTest`) | Skenario F-01 sudah ditutup di `StaffLoginRoleGateTest`; skenario F-23 menunggu fix F-23 (finding OPEN). Gate F-14/F-24 sudah di `SessionValidityTest`. |
| F-27 `/auth/me` | **Sebagian ditutup** (`AuthMeTest`, termasuk kunci F-26 `id == users.id`) | Bentuk respons penuh menunggu F-25 (shape resmi belum didokumentasikan). |
| F-28 AUTH-003 | **Sebagian ditutup** (`PasswordRoleMatrixTest`) | Sisi positif dan skenario F-29 sudah di `SessionValidityTest`; HM→Teacher di `TeacherResetPasswordScopeTest`. |
| F-39 Import Guru | **Ditunda** | Isolasi status batch sudah ditutup di `ImportStatusScopeTest` (F-32); NIP kembar, file rusak, `.xls`, trim, dan async adalah perilaku F-33–F-38 yang masih OPEN. Test ditulis bersama fix-nya (aturan Matrix §8). |
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
- **Kandidat temuan baru (belum terdaftar di Matrix; butuh keputusan bisnis sebelum dikerjakan):** `POST /students` dengan `nisn` yang sama dengan `users.username` akun staf (NIP Headmaster/Teacher atau username Super Admin) menghasilkan 500 `internal_error`, karena `StudentService::store()` membuat `User` baru dengan `username = nisn` dan kena unique violation `users.username` (cermin F-13 di sisi Student). `CreateStudentRequest` sengaja tidak memiliki rule unik untuk `nisn` karena NISN Student yang sudah ada dipakai ulang oleh alur bisnis, sehingga perbaikannya tidak bisa menyalin rule F-13 begitu saja. Terobservasi lewat pembacaan kode (dicatat saat review patch F-13) dan probe sementara yang tidak dikomit; belum ada test di repo. Belum diperiksa: jalur Student Import dengan NISN yang sama dengan username staf.

## 7. Terverifikasi di luar urutan stage

- **F-12: FIXED — VERIFIED.** `POST /schools` oleh Super Admin mengembalikan 201 dengan envelope `{data:{id,name,address,logo}}`. Kode diubah di `SchoolController::store()` pada commit `7396a1a` (judul commit "test(postman): TEST-001 ..."), dan kini dikunci oleh `SchoolStoreStatusTest` (3 test; run verifikasi: full suite 190 passed, 895 assertions; baseline terkini: lihat §2). Headmaster, Teacher, dan Student ditolak 403. Status belum CLOSED. Matrix v3 masih mencatat perilaku lama (200) dan F-12 masih terdaftar di bundel School Stage 3; keduanya perlu disesuaikan di Stage 3.
- **F-46: FIXED — VERIFIED.** `PATCH /students/{id}` multipart (method-spoofing POST + `_method=PATCH`) tidak lagi 422 `protected_field` karena field `_method`. Kode diubah di `UpdateStudentRequest::prepareForValidation()` (properti `$ignored`) pada commit `33d444e`, dan kini dikunci oleh `StudentUpdatePhotoMultipartTest` (8 test; full suite 198 passed, 938 assertions). Yang dikunci: foto valid 200 dan tersimpan, replace foto menghapus file lama, update `name` tanpa foto, method-spoofing lewat query string, field terlarang lain tetap 422 `protected_field` tanpa `_method` ikut dilaporkan, foto bukan gambar tetap 422 `validation_failed`, Teacher rombel lain 403, Headmaster 403, dan tanpa token 401. Status belum CLOSED. Cek manual Postman TEST-002 9.07/9.08 (bagian acceptance F-46 di Matrix) belum dilakukan. Test butuh ekstensi PHP `gd` dan terikat ke `Storage::fake('public')` serta jalur `students/{id}.jpg`; sesuaikan saat F-06 (foto Student private), jangan dianggap regresi F-46.
- **F-02: FIXED — VERIFIED.** `POST /academic-years/{id}/activate` pada Academic Year yang sudah aktif tetap aktif (200 idempoten) dan tepat satu AY aktif per school setelah setiap activate. Akar masalah: bulk query builder di `AcademicYearService::activate()` menulis `is_active=false` ke baris target, lalu `$academicYear->update(['is_active' => true])` tidak menghasilkan query karena model tidak dirty, sehingga sekolah berakhir tanpa AY aktif. Fix: baris target dikecualikan dari bulk-deactivate (`whereKeyNot`). Tanpa perubahan schema, migration, atau dokumen SoT. Dikunci oleh `AcademicYearActivateIdempotencyTest` (5 test; full suite 203 passed, 976 assertions). Status belum CLOSED: cek manual Postman TEST-002 4.10 belum dilakukan. Activate konkuren (F-03) tidak tercakup dan dikerjakan terpisah.
- **F-01: FIXED — VERIFIED.** `POST /auth/login/staff` hanya menerima `super_admin`, `headmaster`, dan `teacher`. Akun Student (aktif, inactive, maupun graduated) ditolak 401 "Username atau password salah" tanpa token dan tanpa membocorkan status akun; sebelumnya Student inactive/graduated mendapat 403 "Akun tidak aktif". Fix: filter `whereIn('role', ...)` pada query `AuthService::loginStaff()`. Tanpa perubahan schema, migration, atau dokumen SoT. Dikunci oleh `StaffLoginRoleGateTest` (7 test; full suite 210 passed, 1013 assertions). Headmaster/Teacher inactive tetap 403 (gate Stage 0-B tidak berubah), SA/Headmaster/Teacher aktif tetap 200, dan login Student lewat jalur Student tetap berfungsi. Status belum CLOSED: cek manual (mis. Postman, login staff dengan NISN) belum dilakukan.
- **F-05: FIXED — VERIFIED.** Ekstensi file upload tidak lagi diambil dari nama klien. `UploadService::upload()` kini menurunkan ekstensi dari media type hasil encoder (`image/jpeg`→`jpg`, `image/png`→`png`, `image/webp`→`webp`), sehingga file tersimpan hanya dengan ekstensi allowlist hasil deteksi konten; nama file tetap `{folder}/{identifier}.{ext}` system-generated. Sebelumnya `getClientOriginalExtension()` meloloskan nama seperti `foto.pht`, `.phps`, dan `.inc` (di luar daftar blokir Laravel yang hanya `.php/.phtml/.phar`) dan menyimpannya sebagai `students/{id}.pht`. Kode diubah di `app/Services/Upload/UploadService.php` pada commit `4796b7a` (dipakai `StudentService` untuk foto Student dan `SchoolService` untuk logo; foto Teacher/Headmaster tidak lewat `UploadService`). Tanpa perubahan schema, migration, atau dokumen SoT. Dikunci oleh `UploadExtensionSafetyTest` (7 method, 19 test run; full suite 229 passed, 1084 assertions): 9 nama klien berbahaya di level service, 5 kasus konten menentukan ekstensi, format di luar allowlist ditolak fail-closed, serta jalur HTTP PATCH foto Student, POST `/students`, dan POST `/schools`. Perilaku baru yang terlihat klien: nama klien `.jpeg` disimpan `.jpg`; konten yang tidak cocok dengan nama mengikuti konten (PNG bernama `.jpg` → `.png`); format di luar allowlist di level service ditolak 422 `validation_failed` (di jalur HTTP rule `mimes` menolak lebih dulu). Urutan hapus file lama → decode → encode → simpan tidak diubah (F-08 tetap OPEN). File lama tidak dimigrasi. Status belum CLOSED (Matrix: F-05 tetap terbuka sampai ada evidence): pemindaian storage untuk file berekstensi di luar `jpg/jpeg/png/webp` (acceptance "tidak ada file executable di storage") belum dilakukan. Test butuh ekstensi PHP `gd` dengan dukungan WebP (`imagewebp`) dan terikat ke `Storage::fake('public')` serta jalur `students/{id}.jpg` dan `logos/{id}.png`; sesuaikan saat F-06, jangan dianggap regresi F-05.
- **F-13: FIXED — VERIFIED.** NIP yang bentrok dengan `users.username` akun role lain kini menghasilkan 422 `validation_failed` pada field `nip` (Create Headmaster dan Create Teacher) atau error baris `field: nip`, `code: validation_failed` (Import Teacher), bukan 500 atau `unexpected_error` generik. Akar masalah: `users.username` UNIQUE lintas role (username = NIP untuk Headmaster/Teacher, NISN untuk Student, bebas untuk Super Admin), sedangkan request hanya mengecek keunikan per tabel (`unique:headmasters,nip` / `unique:teachers,nip`); NIP yang sama dengan username role lain lolos validasi lalu gagal saat `User::create()`. Fix (commit `93943d2`, tanpa perubahan schema, migration, atau dokumen SoT): rule `nip` ditambah `unique:users,username` dengan `bail` di `CreateHeadmasterRequest`, `CreateTeacherRequest`, dan `TeacherImportService` (`bail` menjaga duplikat per-tabel tetap satu error); fallback balapan lewat trait baru `HandlesNipConflict` di `HeadmasterController::store()` dan `TeacherController::store()` yang menerjemahkan `UniqueConstraintViolationException` dari constraint NIP (`users_username_unique`, `teachers_nip_unique`, `headmasters_nip_unique`) menjadi 422 pada field `nip`, sedangkan pelanggaran constraint lain (mis. `uq_headmasters_one_active_per_school`) dilempar ulang. Fallback sengaja dipasang di controller, bukan di `TeacherService::store()`, karena service itu juga dipakai Import Teacher. Dikunci oleh `NipUsernameCollisionTest` (7 test; full suite 236 passed, 1154 assertions): Create Headmaster dengan NIP = username Super Admin, NIP Teacher, dan NISN Student (422 `nip`, tidak ada Headmaster atau user baru, Headmaster lama tetap aktif); Create Teacher dengan NIP = username Super Admin, NIP Headmaster, dan NISN Student; Import Teacher dengan baris bentrok dengan username Headmaster dan Super Admin (baris 3 dan 4 ditolak `nip`/`validation_failed`, baris valid tetap masuk); duplikat per-tabel tetap satu error; NIP baru tetap 201 dengan suksesi Headmaster berjalan; dua test balapan (disimulasikan lewat event `User::creating`) menghasilkan 422 dan transaksi suksesi di-rollback. Tidak tercakup: F-35 (NIP kembar antar-baris dalam satu file) dan F-36 (trim). Status belum CLOSED: cek manual Postman (mis. POST `/headmasters` dengan NIP yang sama dengan username Super Admin, mengharapkan 422) ditunda. Kandidat temuan sisi Student (NISN = username staf) dicatat di §6.
- **F-32: FIXED — VERIFIED.** `GET /teachers/import/status/{batchId}` dan `GET /student-imports/status/{batchId}` kini hanya mengembalikan batch kepada pemiliknya. Sebelumnya kedua controller memakai `ImportBatch::find($batchId)` tanpa filter, sehingga Headmaster/Teacher mana pun dapat membaca hasil import orang lain (NIP/NISN, nama, error baris), termasuk batch sekolah lain dan batch jenis lain. Fix: batch dicari dengan `type` sesuai endpoint (`teacher` atau `student`) dan `created_by` sama dengan user yang login; batch milik user lain, sekolah lain, jenis lain, `created_by` NULL, maupun id yang tidak ada semuanya 404 dengan respons identik (keberadaan batch tidak bocor). Bentuk respons 200 untuk pemilik tidak berubah (F-38 tidak tersentuh). Tanpa perubahan schema, migration, atau dokumen SoT. Definisi "pemilik" yang dipilih: pemilik persis, bukan "satu sekolah", karena import Siswa berscope own rombel (API Contract §26) dan SoT tidak memberi hak baca kepada non-pemilik; konsekuensinya Headmaster pengganti (suksesi) tidak dapat membaca batch pendahulunya dan Teacher lain di sekolah yang sama tidak dapat membaca batch rekannya. Dikunci oleh `ImportStatusScopeTest` (13 test; full suite 249 passed, 1210 assertions): pemilik 200 dengan bentuk respons yang sama, sekolah lain 404, user lain di sekolah yang sama 404, batch jenis lain milik sendiri 404, `created_by` NULL 404, id tidak ada 404 (masing-masing untuk kedua endpoint), dan role yang salah serta tanpa token tetap 403/401. Tidak tercakup: F-37 (job gagal menahan batch di `processing`) dan jendela balapan F-14, keduanya masih OPEN. Status belum CLOSED: cek manual Postman dengan dua akun Headmaster (minta `/teachers/import/status/{id}` milik akun lain, harapkan 404) ditunda.
