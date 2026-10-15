# OPS-001 — Bukti pengujian (log mentah)

**Baca dulu.**
- Semua uji di bawah dijalankan di **sandbox penyusun**: PostgreSQL **16.15** (Ubuntu), PHP **8.3.6**, Laravel **11.56.1** (sesuai `composer.lock`), HEAD repo `afa658c`, database lokal dan data uji dibuat lewat API aplikasi sendiri. Ini **BUKAN staging/produksi proyek**.
- Log ditempel apa adanya (hanya dipotong lebarnya, ditandai `…`, dan sebagian baris pemanasan dibuang). Waktu memakai UTC dari jam sandbox. Durasi berasal dari data kecil (puluhan baris) dan **tidak mewakili produksi**.
- Skrip yang diuji: `scripts/backup.sh`, `scripts/restore.sh`, `scripts/test-partial-unique-concurrency.sh`, `scripts/smoke-test.sh` (+ `scripts/lib/ops-common.sh`). `shellcheck -S warning` pada kelima berkas: bersih.
- **Yang tidak ada di sini:** uji di staging/produksi, versi PostgreSQL selain 16.15, backup terjadwal (cron) di server nyata, lokasi offsite, data berukuran realistis. Lihat checklist di `docs/deployment-runbook.md` bagian 10.
- Rilis rusak pada Uji E2 adalah migration uji yang hanya ada di salinan sandbox (me-rename tabel `schools`); **tidak ada di patch**.

## Ringkasan hasil
| Uji | Acceptance criteria OPS-001 | Hasil |
|---|---|---|
| A | Migration final jalan bersih di DB kosong (Fresh) | PASS — 22 migration, seed 2/7/28/101, 5 partial unique index |
| B | Backup berjalan; restore ke environment terpisah berhasil (jumlah baris, spot check) | PASS — 23 tabel identik (jumlah baris **dan** checksum isi), aplikasi pada DB hasil restore lolos smoke test |
| C | Rollback dicoba tanpa merusak data | PASS bersyarat — alter-only aman dan data utuh; rollback migration create **menghapus data dan menghasilkan data keliru diam-diam** → keputusan: restore sebagai jalur utama |
| D | Partial unique index dites dengan concurrent insert | PASS — 15/15 pada 5 index; kontrol negatif (index dihapus) → harness FAIL sebagaimana mestinya |
| E1/E2 | Prosedur end-to-end disimulasikan minimal 1x (PASS dan FAIL→restore) | PASS di sandbox; durasi hanya indikatif |
| F | Perilaku skrip (retensi, offsite, izin, checksum, argumen) | PASS |
| G | Suite uji penuh di sandbox | 518 passed / 2814 assertions (sama dengan baseline); run pertama 516 passed / 2 failed karena `APP_URL` di `.env` sandbox |

---

######## UJI A — migrate:fresh --seed pada database KOSONG (jalur Fresh, DB Design §10.A)
NOTICE:  database "ops_fresh" does not exist, skipping
tabel sebelum: 0
  Creating migration table ....................................... DONE
  0001_01_01_000001_create_cache_table ........................... DONE
  0001_01_01_000002_create_jobs_table ............................ DONE
  2024_01_01_000000_create_users_table ........................... DONE
  2024_01_01_000002_create_schools_table ......................... DONE
  2024_01_01_000003_create_teachers_table ........................ DONE
  2024_01_01_000004_create_headmasters_table ..................... DONE
  2024_01_01_000005_create_students_table ........................ DONE
  2024_01_01_000006_create_habits_table .......................... DONE
  2024_01_01_000007_create_indicators_table ...................... DONE
  2024_01_01_000008_create_student_habits_table .................. DONE
  2024_01_01_000010_create_qr_tokens_table ....................... DONE
  2026_09_15_150154_create_import_batches_table .................. DONE
  2026_09_22_143617_create_academic_years_table .................. DONE
  2026_09_23_082239_create_rombels_table ......................... DONE
  2026_09_23_091646_create_teacher_class_assignments_table ....... DONE
  2026_09_23_120000_create_student_class_assignments_table ....... DONE
  2026_09_24_120000_create_student_habit_indicators_table ........ DONE
  2026_09_25_135126_create_audit_logs_table ...................... DONE
  2026_09_28_100000_create_gamification_levels_table ............. DONE
  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments DONE
  2026_10_04_100100_add_unique_to_schools_name ................... DONE
  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... DONE
   INFO  Seeding database.
  Database\Seeders\HabitIndicatorSeeder ........................... 48 ms DONE
  Database\Seeders\GamificationLevelSeeder ........................ 93 ms DONE
durasi migrate:fresh --seed: 3.9 dtk
tabel sesudah: 23  | migrations: 22 baris, batch 1
seed: users=2 (role: super_admin), habits=7, indicators=28, gamification_levels=101 (Level 100 = 750592)
partial unique index:
  uq_academic_years_one_active_per_school
  uq_headmasters_one_active_per_school
  uq_sca_one_active_per_student_year
  uq_tca_one_active_per_rombel_year
  uq_tca_one_active_per_teacher_year
tanpa Grade Advancement: tabel bernama %promot%|%advance%|%kenaikan% = 0

---

######## UJI B — backup → restore → verifikasi (PostgreSQL 16.15, PHP 8.3.6, Laravel 11.56.1)
### 1. Kondisi DB sumber 'anak_tumbuh_ops' (checksum isi baris per tabel)
academic_years                    1  de6e83b8582f60c79991fec53b013fe6
audit_logs                        1  c69faa29b92ae306d75e692789171c2d
cache                            10  c11c024319922950e5c79d72c8517b7d
gamification_levels             101  1f8544be65d78217c5c1201db6bb370f
habits                            7  272e62c81ac70c1b4a2530b750f0bc97
headmasters                       1  c8de77f9986b44f91b398e0cdb99b5e7
indicators                       28  1a247cb551c405f680e202322187bb80
migrations                       22  a38991bcfab56795b541339b29e325e9
qr_tokens                         6  09006fb621636289623397dd4ea5f6a5
rombels                           1  96482482d90bd70c38ab33a698513970
schools                           1  8944fd15ade154fd34bc1f4ccc278b4a
student_class_assignments         6  b5ebb4662cae5dada5f5e9416abf2db0
student_habit_indicators          3  c4e656bfc14104a6cf79ed172386ab77
student_habits                    7  1679406e13fd2ccd3b511f132a0f6097
students                          6  46a61f6a405fcf3b6c677f4b59cef15c
teacher_class_assignments         1  4e2e30ee8d11dd1d92e3a56aefe222f8
teachers                          1  98a9a2cdf3fc80d71d82e44e94c02c6b
users                            10  20a632699ef86622c3c4abff85537290

### 2. scripts/backup.sh
2026-10-12T20:14:59Z backup mulai: db=anak_tumbuh_ops host=127.0.0.1:5432 dir=/tmp/ops_backups_B
2026-10-12T20:14:59Z server_version=16.15 (Ubuntu 16.15-0ubuntu0.24.04.1) pg_dump_major=16
2026-10-12T20:14:59Z snapshot=00000003-000004CC-1
2026-10-12T20:14:59Z backup OK: /tmp/ops_backups_B/anak_tumbuh_ops_20261012T201459Z.dump (78801 byte, 23 tabel, last_migration=2026_10_04_110000_make_audit_logs_target_user_id_nullable)
2026-10-12T20:14:59Z PERINGATAN: BACKUP_OFFSITE_CMD belum diset - backup hanya ada di server ini (lokasi terpisah diputuskan di DEPLOYMENT-001)
exit=0
izin berkas:
total 96
-rw------- anak_tumbuh_ops_20261012T201459Z.counts.tsv
-rw------- anak_tumbuh_ops_20261012T201459Z.dump
-rw------- anak_tumbuh_ops_20261012T201459Z.dump.sha256
-rw------- anak_tumbuh_ops_20261012T201459Z.meta
-rw------- backup.log

### 3. scripts/restore.sh ke DB baru 'ops_restored'
2026-10-12T20:15:00Z checksum OK
2026-10-12T20:15:00Z versi: pg_dump pembuat=v16 pg_restore klien=v16
2026-10-12T20:15:00Z database 'ops_restored' dibuat
2026-10-12T20:15:00Z restore mulai: /tmp/ops_backups_B/anak_tumbuh_ops_20261012T201459Z.dump -> ops_restored
2026-10-12T20:15:00Z pg_restore selesai
2026-10-12T20:15:00Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes
TABEL (hanya yang berisi)      BARIS
academic_years                 1
audit_logs                     1
cache                          10
gamification_levels            101
habits                         7
headmasters                    1
indicators                     28
migrations                     22
qr_tokens                      6
rombels                        1
schools                        1
student_class_assignments      6
student_habit_indicators       3
student_habits                 7
students                       6
teacher_class_assignments      1
teachers                       1
users                          10
2026-10-12T20:15:00Z migrasi terakhir di hasil restore: 2026_10_04_110000_make_audit_logs_target_user_id_nullable (backup mencatat: 2026_10_04_110000_make_audit_logs_target_user_id_nullable)
RESTORE TERVERIFIKASI. Langkah susulan yang WAJIB (lihat docs/deployment-runbook.md bagian "Dampak setelah restore"):
  1. php artisan cache:clear        (cache, session, dan rate limiter memakai tabel database)
  2. Data setelah waktu backup HILANG; token user yang password-nya berubah setelah backup akan ditolak (klaim pwd, F-29).
  3. Jalankan scripts/smoke-test.sh terhadap aplikasi yang memakai database ini.
exit=0

### 4. Perbandingan isi: md5 per tabel sumber vs hasil restore
IDENTIK: 23 tabel, jumlah baris DAN checksum isi sama

### 5. Spot-check data (sumber vs restore)
anak_tumbuh_ops: 420|7|3|1|6|198001010101
ops_restored: 420|7|3|1|6|198001010101

### 6. Aplikasi (php -S :8001) yang memakai DB hasil restore 'ops_restored' + smoke test

2026-10-13T01:51:05Z PASS  GET /up = 200
2026-10-13T01:51:06Z PASS  login staff = 200 + access_token
2026-10-13T01:51:06Z PASS  GET /auth/me = 200, role super_admin
2026-10-13T01:51:06Z PASS  GET /schools = 200, meta.total=1
2026-10-13T01:51:06Z RINGKASAN smoke test: PASS=4 FAIL=0
exit=0

### 7. Data hasil restore benar-benar terbaca aplikasi (login Student + poin/level)
login student 6000000001 -> token ada
{"data":{"student_id":1,"poin":420,"gamification_level":4}}

### 8. Smoke test NEGATIF (harus FAIL): password salah
2026-10-13T01:51:06Z PASS  GET /up = 200
2026-10-13T01:51:06Z FAIL  login staff = 401 (429 = rate limit login: php artisan cache:clear)
2026-10-13T01:51:06Z FAIL  GET /auth/me dan /schools dilewati (tidak ada token)
2026-10-13T01:51:06Z RINGKASAN smoke test: PASS=1 FAIL=2
exit=1 (diharapkan 1)

---

######## UJI C — rollback migration pada SALINAN DB (hasil restore backup berisi data)
NOTICE:  database "ops_rollback" does not exist, skipping
2026-10-13T01:51:20Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes

### C0. migrate:status sebelum (22 Ran, 1 batch)
22
1|22

### C1. rollback --pretend (TANPA mengeksekusi): berapa DROP TABLE jika rollback satu batch penuh?
22
  ⇂ drop table if exists "gamification_levels"
  ⇂ drop table if exists "audit_logs"
  ⇂ drop table if exists "student_habit_indicators"
  ⇂ drop table if exists "student_class_assignments"

### C2. guard: sisipkan baris audit_logs dengan target_user_id NULL lalu rollback --step=3

      [2m+26 vendor frames [22m

  27  artisan:13
      Illuminate\Foundation\Application::handleCommand()

exit=1
migrations setelah gagal: 22 (harus tetap 22: tidak ada rollback parsial)

### C3. hapus baris NULL lalu rollback --step=3 (alter-only)
   INFO  Rolling back migrations.

  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... 5.05ms DONE
  2026_10_04_100100_add_unique_to_schools_name ................... 3.11ms DONE
  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments  1.45ms DONE

migrations: 19
index partial unique tersisa: 0 (uq_tca_one_active_per_rombel_year harus 0)
schools_name_unique ada?: 0 (harus 0)
DATA UTUH: semua tabel selain 'migrations' identik (jumlah baris DAN checksum isi) setelah rollback --step=3

### C4. migrate ulang + data tetap utuh

  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments  5.38ms DONE
  2026_10_04_100100_add_unique_to_schools_name ................... 3.67ms DONE
  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... 1.22ms DONE

BEDA:
13c13
< migrations	22	a38991bcfab56795b541339b29e325e9
---
> migrations	22	449b94327ddc3eab55274a2b7f6093f1

### C2 (ulang, pesan error guard): audit_logs berisi baris target_user_id NULL
2026-10-13T07:08:32Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes
   INFO  Rolling back migrations.
  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... 6.19ms FAIL
   RuntimeException
  F-31: tidak bisa mengembalikan NOT NULL, ada audit_logs tanpa target_user_id.
  at database/migrations/2026_10_04_110000_make_audit_logs_target_user_id_nullable.php:32
     28▕
     29▕         // Tidak menghapus log audit demi memulihkan NOT NULL: bila sudah ada baris tanpa target,
     30▕         // rollback berhenti dengan pesan jelas.
migrations setelah gagal: 22 (tetap 22 = tidak ada rollback parsial); kolom nullable?: YES

### C5. rollback --step=4: 3 migration alter-only + SATU migration create (gamification_levels, 101 baris seed)
sebelum: gamification_levels=101
  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... 3.87ms DONE
  2026_10_04_100100_add_unique_to_schools_name ................... 2.66ms DONE
  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments  1.00ms DONE
  2026_09_28_100000_create_gamification_levels_table ............. 1.32ms DONE
sesudah rollback: tabel gamification_levels ada? 0 (0 = TABEL DAN DATANYA HILANG)
  2026_09_28_100000_create_gamification_levels_table ............. 4.59ms DONE
  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments  2.04ms DONE
  2026_10_04_100100_add_unique_to_schools_name ................... 1.45ms DONE
  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... 0.86ms DONE
sesudah migrate ulang: gamification_levels=0 baris (tabel dibuat ulang KOSONG; seeder tidak ikut berjalan)

### C6. dampak ke aplikasi (php -S :8003 pada DB ops_rollback2): GET /gamification/me, dashboard student
gamification/me -> {"data":{"student_id":1,"poin":420,"gamification_level":0}} [HTTP 200]
dashboard/student -> {"data":{"wali_kelas":{"id":1,"name":"Guru OPS"},"habit_summary_today":{"submitted":7,"total":7},"poin":420,"gamification_level":0}} [HTTP 200]

### C7. migrations table setelah rollback+migrate ulang (alasan md5 'migrations' beda di C4)
19 2026_09_28_100000_create_gamification_levels_table batch=1
34 2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments batch=2
35 2026_10_04_100100_add_unique_to_schools_name batch=2
36 2026_10_04_110000_make_audit_logs_target_user_id_nullable batch=2

---

######## UJI D (ulang, pasca-perapihan shellcheck) — concurrency 5 index pada ops_fresh
== uq_academic_years_one_active_per_school (salinan: academic_years_school_id_idx1) pada tabel academic_years
PASS  uq_academic_years_one_active_per_school | A-commit: B menunggu (Lock:transactionid, 2.94s) lalu GAGAL 23505 pada "academic_years_school_id_idx1"; baris akhir=1
PASS  uq_academic_years_one_active_per_school | A-rollback: B menunggu (Lock:transactionid, 2.93s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
PASS  uq_academic_years_one_active_per_school | kontrol non-predikat: B TIDAK menunggu (0.06s) dan berhasil; baris akhir=2
== uq_headmasters_one_active_per_school (salinan: headmasters_school_id_idx1) pada tabel headmasters
PASS  uq_headmasters_one_active_per_school | A-commit: B menunggu (Lock:transactionid, 2.94s) lalu GAGAL 23505 pada "headmasters_school_id_idx1"; baris akhir=1
PASS  uq_headmasters_one_active_per_school | A-rollback: B menunggu (Lock:transactionid, 2.94s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
PASS  uq_headmasters_one_active_per_school | kontrol non-predikat: B TIDAK menunggu (0.05s) dan berhasil; baris akhir=2
== uq_tca_one_active_per_teacher_year (salinan: teacher_class_assignments_teacher_id_academic_year_id_idx) pada tabel teacher_class_assignments
PASS  uq_tca_one_active_per_teacher_year | A-commit: B menunggu (Lock:transactionid, 2.95s) lalu GAGAL 23505 pada "teacher_class_assignments_teacher_id_academic_year_id_idx"; baris akhir=1
PASS  uq_tca_one_active_per_teacher_year | A-rollback: B menunggu (Lock:transactionid, 2.94s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
PASS  uq_tca_one_active_per_teacher_year | kontrol non-predikat: B TIDAK menunggu (0.04s) dan berhasil; baris akhir=2
== uq_tca_one_active_per_rombel_year (salinan: teacher_class_assignments_rombel_id_academic_year_id_idx) pada tabel teacher_class_assignments
PASS  uq_tca_one_active_per_rombel_year | A-commit: B menunggu (Lock:transactionid, 2.94s) lalu GAGAL 23505 pada "teacher_class_assignments_rombel_id_academic_year_id_idx"; baris akhir=1
PASS  uq_tca_one_active_per_rombel_year | A-rollback: B menunggu (Lock:transactionid, 2.94s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
PASS  uq_tca_one_active_per_rombel_year | kontrol non-predikat: B TIDAK menunggu (0.04s) dan berhasil; baris akhir=2
== uq_sca_one_active_per_student_year (salinan: student_class_assignments_student_id_academic_year_id_idx) pada tabel student_class_assignments
PASS  uq_sca_one_active_per_student_year | A-commit: B menunggu (Lock:transactionid, 2.93s) lalu GAGAL 23505 pada "student_class_assignments_student_id_academic_year_id_idx"; baris akhir=1
PASS  uq_sca_one_active_per_student_year | A-rollback: B menunggu (Lock:transactionid, 2.94s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
PASS  uq_sca_one_active_per_student_year | kontrol non-predikat: B TIDAK menunggu (0.04s) dan berhasil; baris akhir=2
RINGKASAN: PASS=15 FAIL=0 (HOLD=3s, batas tunggu minimal=1.50s, db=ops_fresh)
exit=0

######## UJI D — kontrol negatif skrip concurrency (harus GAGAL pada DB yang index-nya dirusak)
2026-10-13T07:10:00Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes
mutasi: DROP INDEX uq_tca_one_active_per_rombel_year di DB ops_mutant
exit=1 (diharapkan 1)
2026-10-13T07:10:20Z FAIL  uq_tca_one_active_per_rombel_year: index parsial TIDAK ditemukan/definisinya berbeda pada salinan (periksa migrasi)
2026-10-13T07:10:27Z RINGKASAN: PASS=12 FAIL=1 (HOLD=2s, batas tunggu minimal=1.00s, db=ops_mutant)
PASS pada 4 index lain: 12
schema sementara bersih? 0

---

######## UJI E1 — ALUR DEPLOY, JALUR PASS (PostgreSQL 16.15 / PHP 8.3.6 / Laravel 11.56.1)
NOTICE:  database "ops_app" does not exist, skipping
2026-10-13T07:09:14Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes
  migrasi dikembalikan (simulasi rilis lama): 3
[0] pre-flight: migrasi tertunda = 3
[1] php artisan down
   INFO  Application is now in maintenance mode.
    saat maintenance: GET /up ->  [HTTP 200]   GET /api/v1/auth/me ->  [HTTP 503]
[2] backup.sh
2026-10-13T07:09:16Z backup OK: /tmp/ops_backups_E1/ops_app_20261013T070915Z.dump (77757 byte, 23 tabel, last_migration=2026_09_28_100000_create_gamification_levels_table)
    durasi backup: 0.4 dtk
[3] deploy kode (HEAD = kode yang sama; tidak ada git pull di simulasi) lalu migrate
  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments  2.75ms DONE
  2026_10_04_100100_add_unique_to_schools_name ................... 3.00ms DONE
  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... 1.05ms DONE
    durasi migrate: 0.2 dtk
    migrasi tertunda setelah migrate: 0
[4] php artisan up
   INFO  Application is now live.
[5] smoke test:
2026-10-13T07:09:16Z PASS  GET /up = 200
2026-10-13T07:09:17Z PASS  login staff = 200 + access_token
2026-10-13T07:09:17Z PASS  GET /auth/me = 200, role super_admin
2026-10-13T07:09:17Z PASS  GET /schools = 200, meta.total=1
2026-10-13T07:09:17Z RINGKASAN smoke test: PASS=4 FAIL=0
    smoke exit=0 -> PASS
[6] total jendela maintenance (down -> up): 1.4 dtk; total sampai smoke selesai: 1.8 dtk
[7] data aplikasi identik sebelum/sesudah migrate (selain migrations/cache)

---

######## UJI E2 — ALUR DEPLOY, JALUR FAIL → restore (rilis rusak lolos migrate tetapi gagal smoke test)
2026-10-13T07:09:40Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes
  migrasi dikembalikan (simulasi rilis lama): 3
[0] siswa 2 login (token LAMA, terbit SEBELUM backup)
    token lama: ada
    tabel sebelum deploy: student_habits=7 schools=1
[1] php artisan down
    maintenance aktif: 1
[2] backup.sh (titik pemulihan)
2026-10-13T07:09:42Z backup OK: /tmp/ops_backups_E2/ops_app_20261013T070942Z.dump (77855 byte, 23 tabel, last_migration=2026_09_28_100000_create_gamification_levels_table)
[3] RILIS RUSAK: migration uji (hanya di salinan sandbox, TIDAK ada di patch) me-rename tabel schools
  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments  3.05ms DONE
  2026_10_04_100100_add_unique_to_schools_name ................... 3.03ms DONE
  2026_10_04_110000_make_audit_logs_target_user_id_nullable ...... 0.99ms DONE
  2026_10_13_000000_ops_drill_bad_release ........................ 0.79ms DONE
[4] php artisan up
[5] smoke test (HARUS GAGAL):
2026-10-13T07:09:43Z PASS  GET /up = 200
2026-10-13T07:09:43Z PASS  login staff = 200 + access_token
2026-10-13T07:09:43Z PASS  GET /auth/me = 200, role super_admin
2026-10-13T07:09:43Z FAIL  GET /schools = 500
2026-10-13T07:09:43Z RINGKASAN smoke test: PASS=3 FAIL=1
    smoke exit=1 -> FAIL (memicu jalur FAIL runbook)
[6] lalu lintas sempat jalan sebelum keputusan rollback (aktivitas SETELAH backup):
    reset password siswa 2 oleh Teacher ->  [HTTP 500]
    siswa 2 login dengan password baru -> token baru: ada
    siswa 2 mengisi 1 habit ->  [HTTP 201]
    student_habits sekarang=8 (7 + 1 baru)
[7] KEPUTUSAN: FAIL → down, kembalikan kode rilis lama, RESTORE ke DB BARU, tukar nama
2026-10-13T07:09:44Z versi: pg_dump pembuat=v16 pg_restore klien=v16
2026-10-13T07:09:45Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes
    sesi diputus: 0
    nama DB ditukar: ops_app_restored -> ops_app (yang gagal disimpan sebagai ops_app_failed_20261013T070940Z untuk analisis)
    cache:clear dijalankan (cleared=1)
    php artisan up; durasi sejak keputusan FAIL sampai up: 1.0 dtk
[8] smoke test setelah restore:
2026-10-13T07:09:45Z PASS  GET /up = 200
2026-10-13T07:09:45Z PASS  login staff = 200 + access_token
2026-10-13T07:09:45Z PASS  GET /auth/me = 200, role super_admin
2026-10-13T07:09:45Z PASS  GET /schools = 200, meta.total=1
2026-10-13T07:09:45Z RINGKASAN smoke test: PASS=4 FAIL=0
    smoke exit=0 -> PASS
[9] BEDA dari sebelum deploy:
3c3
< cache	4	7875e4095a7273b0cf7cfbafda0d38ce
---
> cache	4	ccc3263fb7a9fea06d1ccf967f192b32
[10] DAMPAK SETELAH RESTORE (klaim pwd, F-29):
    token LAMA siswa 2 (terbit sebelum backup)    -> /auth/me  [HTTP 200]   (diharapkan 200)
    token BARU siswa 2 (password diubah setelah backup) -> /auth/me  [HTTP 401]   (diharapkan 401)
    login password LAMA (NISN)                    ->  [HTTP 200]   (diharapkan 200)
    student_habits setelah restore=7 (aktivitas pasca-backup HILANG: 8 → 7)
[11] total dari down pertama sampai smoke akhir: 4.4 dtk

---

######## UJI F — perilaku backup.sh: argumen, retensi, hook offsite
== F1. BACKUP_DIR tidak diset
scripts/backup.sh: line 24: BACKUP_DIR: BACKUP_DIR wajib diisi (lokasi di luar repo; jangan di dalam storage/ atau public/)
exit=1
== F2. retensi: berkas lama (umur 20 hari) dihapus, berkas baru dan milik DB lain dipertahankan
2026-10-13T22:35:38Z backup OK: /tmp/ops_backups_F/anak_tumbuh_ops_20261013T223537Z.dump (78813 byte, 23 tabel, last_migration=2026_10_04_110000_make_audit_logs_target_us
2026-10-13T22:35:38Z retensi hapus: /tmp/ops_backups_F/anak_tumbuh_ops_20260101T000000Z.dump.sha256
2026-10-13T22:35:38Z retensi hapus: /tmp/ops_backups_F/anak_tumbuh_ops_20260101T000000Z.counts.tsv
2026-10-13T22:35:38Z retensi hapus: /tmp/ops_backups_F/anak_tumbuh_ops_20260101T000000Z.dump
2026-10-13T22:35:38Z retensi hapus: /tmp/ops_backups_F/anak_tumbuh_ops_20260101T000000Z.meta
sisa berkas lama milik anak_tumbuh_ops: 1 (anak_tumbuh_ops_*: 0); milik DB lain: 1
== F3. hook offsite sukses
2026-10-13T22:35:38Z menjalankan BACKUP_OFFSITE_CMD
offsite: anak_tumbuh_ops_20261013T223538Z tersalin
2026-10-13T22:35:38Z offsite OK
exit=0
salinan offsite: -rw------- 78813 byte
== F4. hook offsite GAGAL -> exit 2, backup lokal tetap ada
2026-10-13T22:35:39Z menjalankan BACKUP_OFFSITE_CMD
2026-10-13T22:35:39Z ERROR: BACKUP_OFFSITE_CMD gagal (backup lokal TETAP ada)
exit=2
== F5. tanpa hook: peringatan
2026-10-13T22:35:39Z PERINGATAN: BACKUP_OFFSITE_CMD belum diset - backup hanya ada di server ini (lokasi terpisah diputuskan di DEPLOYMENT-001)
== F6. izin berkas dan folder
700 /tmp/ops_backups_F
600 /tmp/ops_backups_F/anak_tumbuh_ops_20261013T223537Z.dump
600 /tmp/ops_backups_F/anak_tumbuh_ops_20261013T223538Z.dump
== F7. checksum rusak ditolak restore
2026-10-13T22:35:39Z ERROR: checksum tidak cocok: berkas backup rusak
exit=1
database dibuat? 0

---

## Uji G — suite uji penuh di sandbox (database terpisah `ops_test`, bukan data uji)

Run pertama dengan `.env` hasil salinan `.env.example` (`APP_URL=https://api.example.com`): **516 passed, 2 failed (2811 assertions)**. Dua kegagalan ada di `tests/Feature/Security/EnforceHttpsTest` ("http dialihkan 308 …" dan "host header klien tidak dipakai …"): base URL test sudah HTTPS sehingga tidak ada redirect (respons 401, bukan 308). Penyebab terisolasi: bila dijalankan dengan `APP_URL=http://localhost`, kelas test itu lolos (5 passed) dan seluruh suite menghasilkan angka di bawah.

```
== hipotesis: APP_URL=https://... di .env membuat base URL test sudah HTTPS; uji dengan APP_URL=http://localhost
  Tests:    5 passed (11 assertions)
== SELURUH SUITE dengan APP_URL=http://localhost (DB terpisah ops_test, bukan DB data uji)
  ............................................................................
  ..............................................................

  Tests:    518 passed (2814 assertions)
  Duration: 35.09s

durasi=35s
```

Catatan: HEAD `afa658c` pada zip yang diterima berbeda dari yang disebut di pesan tugas (`85d8b27`, 515 passed); baseline §18 untuk `afa658c` tertulis 518 passed / 2814 assertions dan cocok dengan run di atas.
