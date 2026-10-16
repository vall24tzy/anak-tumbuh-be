# OPS-001 — Bukti verifikasi lokal (Windows, PostgreSQL 17.11)

**Pelaksana:** pemilik project (Orang 2), di komputernya sendiri. **Penyusun dokumen:** hanya memandu langkah dan menyalin keluaran asli yang ditempelkan pemilik; penyusun tidak menjalankan perintah apa pun.
**Basis:** HEAD `91231bd` (working tree bersih sebelum uji). **Waktu:** 15 Okt 2026 (stempel waktu pada log memakai UTC).
**Status:** hasil di bawah adalah **laporan pemilik** (keluaran terminal yang ditempelkan), bukan berkas log yang tersimpan di repo. Pelengkap dokumen `docs/ops/ops-001-bukti.md` (bukti sandbox Orang 1, PostgreSQL 16.15); berkas itu tidak diubah.
Tidak ada password, `.env`, atau isi dump yang dimasukkan ke dokumen ini. Password Super Admin uji diketik lewat `read -rs` dan tidak pernah dicetak.

## 1. Environment

| Item | Nilai |
|---|---|
| OS | Windows 11 Home, versi 10.0.26200 (`Win32_OperatingSystem`) |
| Shell | Git Bash (MINGW64): `uname -srm` = `MINGW64_NT-10.0-26200 3.5.4-cbe555e0.x86_64 x86_64`; GNU bash 5.2.37(1)-release (x86_64-pc-msys); `sha256sum` GNU coreutils 8.32; Git 2.47.0.windows.2 |
| PHP | 8.3.30 CLI (ZTS, Visual C++ 2019 x64), Laragon (`C:\laragon\bin\php\php-8.3.30`) |
| Laravel | 11.56.1 (`php artisan --version`) |
| Server PostgreSQL yang diuji | **17.11** (`PostgreSQL 17.11 on x86_64-windows, compiled by msvc-19.44.35229, 64-bit`), dari arsip zip binaries (tanpa installer/service), port **5434**, data `C:/pg17-data-uji`, dibuat dengan `initdb -U postgres -A trust -E UTF8 --locale=C` (tanpa password, pengaturan bawaan; tidak diverifikasi ulang bahwa hanya mendengar di localhost) |
| Klien | `psql`, `pg_dump`, `pg_restore` **17.11** dari zip yang sama (`C:\pg17\pgsql\bin`), didahulukan di PATH hanya pada sesi Git Bash yang dipakai uji |
| Server lain di komputer yang sama (TIDAK dipakai uji) | **14.5** (Laragon, port 5432) dan **14.24** (service Windows `postgresql-x64-14`, port 5433). Klien lama: 14.0 di `C:\Program Files\PostgreSQL\14` |
| `APP_ENV` | `local` |
| Database uji | `anak_tumbuh_ops_a`, `anak_tumbuh_ops_b`, `anak_tumbuh_ops_b_restored`, `anak_tumbuh_ops_mutant` (semuanya di server 17.11). Database aplikasi pemilik tidak ditargetkan perintah apa pun |
| `BACKUP_DIR` | `/d/ops-backup-uji` (di luar repo) |
| Alat bantu uji (di luar repo, tidak di-commit) | pembungkus `psql` (bagian 4), skrip data uji `ops-seed-b.php`, skrip banding tabel `compare-tables.sh` |

## 2. Ringkasan hasil

| Uji | Hasil | Catatan |
|---|---|---|
| A. `migrate:fresh --seed` pada DB kosong | **PASS** | Semua angka sama dengan uji A di sandbox 16.15 (bagian 5) |
| B. backup → restore → banding isi → smoke test | **PASS, dengan pembungkus `psql`** | Tanpa pembungkus, `backup.sh` **GAGAL** di Windows (BUG-1, bagian 6.1). Smoke test positif lulus, negatif gagal sebagaimana mestinya |
| D. concurrency 5 index × 3 skenario | **PASS 15/15**, kontrol negatif **FAIL** sebagaimana mestinya | Dijalankan dengan pembungkus `psql` aktif |
| E1/E2. alur deploy PASS dan FAIL → restore | **BELUM DIJALANKAN** | Ditunda ke tahap terpisah (lihat bagian 8) |

Perbedaan perilaku PostgreSQL 16.15 (bukti Orang 1) vs 17.11 (di sini): **tidak ada perbedaan perilaku PostgreSQL yang terlihat** pada hasil migrasi, seed, backup, restore, smoke test, dan concurrency. Satu-satunya perbedaan nyata berasal dari **Windows/Git Bash**, bukan dari versi PostgreSQL (BUG-1). Selisih waktu (bagian 7) bersifat indikatif saja.

## 3. Persiapan lingkungan dan riwayat kegagalan

1. **Kegagalan pertama yang dilaporkan (tugas): `pg_dump (v14) lebih lama dari server (v17)`.** Itu berasal dari percobaan Orang 1 dan **tidak terjadi di komputer pemilik**: di sini tidak ada server 17 (hanya 14.5 dan 14.24, lihat bagian 1). Pengaman versi di skrip **tidak diubah dan tidak dilonggarkan**, dan tidak terpicu dalam uji ini (klien 17.11 dengan server 17.11). Untuk menguji skenario server 17.11, server 17.11 sementara dijalankan dari zip binaries di port 5434 dengan folder data sendiri; Laragon dan kedua server 14 tidak disentuh.
2. **PATH awal di Git Bash:** `psql`/`pg_dump`/`pg_restore` = 14.x (`which -a pg_dump` menunjukkan klien 14 setelah klien 17 didahulukan lewat `export PATH=...` pada sesi). Klien 17.11 terverifikasi: `pg_dump --version`, `pg_restore --version`, `psql --version` = 17.11.
3. **PHP:** `php -v` di Git Bash semula melaporkan 8.2.12 (kemungkinan dari XAMPP: `C:\xampp\php\php.exe` ada di PATH Windows setelah Laragon; asalnya tidak diverifikasi). `composer.lock` memuat paket yang mensyaratkan PHP 8.3 (`intervention/image`, `intervention/gif`), dan `vendor/composer/platform_check.php` memeriksa `PHP_VERSION_ID >= 80300`. **Tidak dijalankan dengan 8.2**; ini disimpulkan dari berkas, bukan dari uji. Uji memakai PHP 8.3.30 Laragon yang didahulukan di PATH sesi.
4. **Alias Git Bash:** `psql` dan `php` ter-alias ke `winpty ...` (hanya untuk terminal interaktif). Alias dihapus pada sesi uji (`unalias psql php`).
5. **Tampilan terminal (mintty):** keluaran `psql.exe` kadang tertimpa/terpotong di layar. Mitigasi: `| cat` atau `| tee file`. Tidak memengaruhi isi data.
6. **Salah ketik yang tercatat (agar tidak disalahartikan):** pemeriksaan `\r` pertama memakai `-x` (bukan `-X`) dan tidak sah sebagai bukti; kueri index uji A pertama memakai `indexdep` (seharusnya `indexdef`) dan diulang dengan benar. Hasil keduanya yang sah ada di bagian 5 dan 6.
7. **`cmd.exe /c ver`** dari Git Bash menggantung/berantakan; versi Windows diambil dari PowerShell.

## 4. Alat bantu uji (di luar repo)

Pembungkus `psql` (disimpan sebagai `D:\ops-uji-tools\shim\psql`, didahulukan di PATH sesi): membuang `\r` pada keluaran `psql.exe`. Argumen, stdin, stderr, dan kode keluar tidak diubah; `sed -u` dipakai agar keluaran tidak tertahan pada `coproc`.

```bash
#!/usr/bin/env bash
real=/c/pg17/pgsql/bin/psql.exe
"$real" "$@" | sed -u 's/\r$//'
exit "${PIPESTATUS[0]}"
```

Dua skrip lain dipakai untuk uji B dan bukan bagian repo: `ops-seed-b.php` (mengisi 2 sekolah, 12 user, 6 siswa, dan data terkait ke `anak_tumbuh_ops_b`; menetapkan password `superadmin1` dari variabel sesi tanpa mencetaknya) dan `compare-tables.sh` (membandingkan jumlah baris dan md5 teks seluruh baris, diurutkan, per tabel schema `public`).

## 5. Uji A — `migrate:fresh --seed` (DB `anak_tumbuh_ops_a`, server 17.11)

Kondisi sebelum: 0 tabel (`SELECT count(*) FROM pg_tables ...` = `0`).

```
$ time SEED_SUPER_ADMIN_PASSWORD= DB_DATABASE=anak_tumbuh_ops_a php artisan migrate:fresh --seed --no-ansi 2>&1 | tee /tmp/ujiA-migrate.log

   INFO  Preparing database.

  Creating migration table .......................................... 44.25ms DONE

   INFO  Running migrations.

  0001_01_01_000001_create_cache_table .............................. 19.15ms DONE
  0001_01_01_000002_create_jobs_table ............................... 28.86ms DONE
  2024_01_01_000000_create_users_table .............................. 12.76ms DONE
  2024_01_01_000002_create_schools_table ............................. 8.84ms DONE
  2024_01_01_000003_create_teachers_table ........................... 32.34ms DONE
  2024_01_01_000004_create_headmasters_table ........................ 21.05ms DONE
  2024_01_01_000005_create_students_table ........................... 20.25ms DONE
  2024_01_01_000006_create_habits_table ............................. 12.11ms DONE
  2024_01_01_000007_create_indicators_table ......................... 13.33ms DONE
  2024_01_01_000008_create_student_habits_table ..................... 29.95ms DONE
  2024_01_01_000010_create_qr_tokens_table .......................... 11.66ms DONE
  2026_09_15_150154_create_import_batches_table ..................... 10.17ms DONE
  2026_09_22_143617_create_academic_years_table ..................... 16.81ms DONE
  2026_09_23_082239_create_rombels_table ............................ 17.41ms DONE
  2026_09_23_091646_create_teacher_class_assignments_table .......... 25.61ms DONE
  2026_09_23_120000_create_student_class_assignments_table .......... 29.42ms DONE
  2026_09_24_120000_create_student_habit_indicators_table ........... 20.94ms DONE
  2026_09_25_135126_create_audit_logs_table ......................... 18.18ms DONE
  2026_09_28_100000_create_gamification_levels_table ................ 10.30ms DONE
  2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments  9.26ms DONE
  2026_10_04_100100_add_unique_to_schools_name ....................... 4.25ms DONE
  2026_10_04_110000_make_audit_logs_target_user_id_nullable .......... 1.94ms DONE


   INFO  Seeding database.

  Database\Seeders\HabitIndicatorSeeder .................................. RUNNING
  Database\Seeders\HabitIndicatorSeeder ............................... 82 ms DONE

  Database\Seeders\GamificationLevelSeeder ............................... RUNNING
  Database\Seeders\GamificationLevelSeeder ............................ 91 ms DONE

SEED_SUPER_ADMIN_PASSWORD tidak diset: password acak dibuat untuk superadmin1. Gunakan `php artisan admin:reset-password` untuk menetapkannya.
SEED_SUPER_ADMIN_PASSWORD tidak diset: password acak dibuat untuk superadmin2. Gunakan `php artisan admin:reset-password` untuk menetapkannya.

real    0m2.844s
user    0m0.061s
sys     0m0.030s
```

Kondisi sesudah (nilai dari keluaran pemilik; pada layar asli satu baris tercampur dengan pesan error dari kueri index yang salah ketik, lihat bagian 3 butir 6):

```
23                                  <- tabel
22 baris, batch terakhir 1           <- migrations
users=2 role=super_admin
habits=7
indicators=28
gamification_levels=101
100|750592                           <- level teratas
tabel grade advancement=0
```

Lima partial unique index (kueri diulang dengan benar):

```
$ psql -d anak_tumbuh_ops_a -Atc "SELECT indexname FROM pg_indexes WHERE schemaname='public' AND indexdef LIKE 'CREATE UNIQUE INDEX%WHERE%' ORDER BY 1" | cat
uq_academic_years_one_active_per_school
uq_headmasters_one_active_per_school
uq_sca_one_active_per_student_year
uq_tca_one_active_per_rombel_year
uq_tca_one_active_per_teacher_year
```

**Perbandingan dengan sandbox 16.15 (`ops-001-bukti.md`, uji A):** 23 tabel, 22 migration batch 1, `users=2`, `habits=7`, `indicators=28`, `gamification_levels=101`, level 100 = 750592, lima index, tanpa tabel Grade Advancement: **sama**. Durasi 2,84 dtk di sini vs 3,9 dtk di sandbox (mesin berbeda; tidak sebanding).

## 6. Uji B — backup, restore, banding, smoke test

Data uji: `anak_tumbuh_ops_b` diisi `migrate:fresh --seed` lalu `ops-seed-b.php` (`SELESAI: schools=2 users=12 teachers=2 headmasters=2 students=6 student_class_assignments=6`; `schools=2 users=12 students=6 rombels=2`). `DB_PASSWORD` diset kosong agar skrip tidak membaca `.env` aplikasi.

### 6.1 `backup.sh` tanpa pembungkus: GAGAL (BUG-1)

```
$ DB_DATABASE=anak_tumbuh_ops_b bash scripts/backup.sh 2>&1 | tee /tmp/ujiB-backup.log; echo "exit=${PIPESTATUS[0]}"
2026-10-15T10:20:05Z backup mulai: db=anak_tumbuh_ops_b host=127.0.0.1:5434 dir=/d/ops-backup-uji
2026-10-15T10:20:06Z server_version=17.11 pg_dump_major=17
2026-10-15T10:20:06Z snapshot=00000014-00000002-1
pg_dump: error: query failed: ERROR:  invalid snapshot identifier: "00000014-0000000"-1
'g_dump: detail: Query was: SET TRANSACTION SNAPSHOT '00000014-00000002-1
2026-10-15T10:20:06Z ERROR: pg_dump gagal
exit=1
```

Tulisan yang tertimpa (`"00000014-0000000"-1` dan `'g_dump`) adalah ciri karakter `\r` di akhir nilai snapshot. Bukti langsung (kali ini dengan `-X` yang benar, melalui pipa):

```
$ psql -qAtX -d anak_tumbuh_ops_b -c "SELECT 'x'" | od -c
0000000   x  \r  \n
0000003
```

Dengan pembungkus (bagian 4) di PATH:

```
$ psql -qAtX -d anak_tumbuh_ops_b -c "SELECT 'x'" | od -c
0000000   x  \n
0000002
```

### 6.2 `backup.sh` dengan pembungkus: PASS

```
$ DB_DATABASE=anak_tumbuh_ops_b bash scripts/backup.sh 2>&1 | tee /tmp/ujiB-backup2.log; echo "exit=${PIPESTATUS[0]}"
2026-10-15T10:34:01Z backup mulai: db=anak_tumbuh_ops_b host=127.0.0.1:5434 dir=/d/ops-backup-uji
2026-10-15T10:34:01Z server_version=17.11 pg_dump_major=17
2026-10-15T10:34:01Z snapshot=00000019-00000002-1
2026-10-15T10:34:04Z backup OK: /d/ops-backup-uji/anak_tumbuh_ops_b_20261015T103401Z.dump (79348 byte, 23 tabel, last_migration=2026_10_04_110000_make_audit_logs_target_user_id_nullable)
2026-10-15T10:34:04Z PERINGATAN: BACKUP_OFFSITE_CMD belum diset - backup hanya ada di server ini (lokasi terpisah diputuskan di DEPLOYMENT-001)
exit=0
```

Berkas di `BACKUP_DIR`: `anak_tumbuh_ops_b_20261015T103401Z.counts.tsv`, `.dump`, `.dump.sha256`, `.meta`, dan `backup.log`. Isi `.sha256`: `1edf652652a1fe1a977ddb54f6321bb6b8842836683879f1920888ccd79875ae *anak_tumbuh_ops_b_20261015T103401Z.dump`. Isi `.meta`:

```
timestamp_utc=20261015T103401Z
database=anak_tumbuh_ops_b
server_version=17.11
pg_dump_version=pg_dump (PostgreSQL) 17.11
last_migration=2026_10_04_110000_make_audit_logs_target_user_id_nullable
app_git_sha=91231bd
tables=23
dump_bytes=79348
```

**Izin berkas:** `chmod 600` dan `umask 077` **tidak dapat dibuktikan di NTFS**; kolom izin dari `ls -l` di Git Bash tidak dipakai sebagai bukti. Izin berkas harus diverifikasi di server Linux (DEPLOYMENT-001).

### 6.3 `restore.sh` ke database baru: PASS (dengan pembungkus)

```
$ bash scripts/restore.sh --confirm --file /d/ops-backup-uji/anak_tumbuh_ops_b_20261015T103401Z.dump --target-db anak_tumbuh_ops_b_restored --create-db 2>&1 | tee /tmp/ujiB-restore.log; echo "exit=${PIPESTATUS[0]}"
2026-10-15T22:27:34Z checksum OK
2026-10-15T22:27:37Z versi: pg_dump pembuat=v17 pg_restore klien=v17
2026-10-15T22:27:39Z database 'anak_tumbuh_ops_b_restored' dibuat
2026-10-15T22:27:39Z restore mulai: /d/ops-backup-uji/anak_tumbuh_ops_b_20261015T103401Z.dump -> anak_tumbuh_ops_b_restored
2026-10-15T22:27:40Z pg_restore selesai
2026-10-15T22:27:41Z verifikasi jumlah baris OK: 23 tabel identik dengan manifes
TABEL (hanya yang berisi)      BARIS
academic_years                 2
gamification_levels            101
habits                         7
headmasters                    2
indicators                     28
migrations                     22
rombels                        2
schools                        2
student_class_assignments      6
students                       6
teacher_class_assignments      2
teachers                       2
users                          12
2026-10-15T22:27:41Z migrasi terakhir di hasil restore: 2026_10_04_110000_make_audit_logs_target_user_id_nullable (backup mencatat: 2026_10_04_110000_make_audit_logs_target_user_id_nullable)
RESTORE TERVERIFIKASI. Langkah susulan yang WAJIB (lihat docs/deployment-runbook.md bagian "Dampak setelah restore"):
  1. php artisan cache:clear        (cache, session, dan rate limiter memakai tabel database)
  2. Data setelah waktu backup HILANG; token user yang password-nya berubah setelah backup akan ditolak (klaim pwd, F-29).
  3. Jalankan scripts/smoke-test.sh terhadap aplikasi yang memakai database ini.
exit=0
```

### 6.4 Banding isi per tabel (sumber vs hasil restore): PASS

```
$ bash /d/ops-uji-tools/compare-tables.sh anak_tumbuh_ops_b anak_tumbuh_ops_b_restored 2>&1 | tee /tmp/ujiB-compare.log; echo "exit=${PIPESTATUS[0]}"
TABEL                              SUMBER  RESTORE  STATUS
academic_years                          2        2  IDENTIK
audit_logs                              0        0  IDENTIK
cache                                   0        0  IDENTIK
cache_locks                             0        0  IDENTIK
failed_jobs                             0        0  IDENTIK
gamification_levels                   101      101  IDENTIK
habits                                  7        7  IDENTIK
headmasters                             2        2  IDENTIK
import_batches                          0        0  IDENTIK
indicators                             28       28  IDENTIK
job_batches                             0        0  IDENTIK
jobs                                    0        0  IDENTIK
migrations                             22       22  IDENTIK
qr_tokens                               0        0  IDENTIK
rombels                                 2        2  IDENTIK
schools                                 2        2  IDENTIK
student_class_assignments               6        6  IDENTIK
student_habit_indicators                0        0  IDENTIK
student_habits                          0        0  IDENTIK
students                                6        6  IDENTIK
teacher_class_assignments               2        2  IDENTIK
teachers                                2        2  IDENTIK
users                                  12       12  IDENTIK
IDENTIK: 23 tabel, jumlah baris DAN checksum isi sama
exit=0
```

Sampel: `sumber: students=6 poin=120` dan `restore: students=6 poin=120`.

### 6.5 Smoke test pada database hasil restore

`cache:clear` dijalankan pada DB hasil restore (`Application cache cleared successfully.`). Aplikasi sementara: `php -S 127.0.0.1:8001 -t public` dengan `DB_DATABASE=anak_tumbuh_ops_b_restored` (sebelumnya port 8001 bebas: `000`; `/up` = 200 setelah dijalankan).

Positif (harus lulus):

```
$ SMOKE_BASE_URL=http://127.0.0.1:8001 SMOKE_USERNAME=superadmin1 SMOKE_PASSWORD="$OPS_SA_PASSWORD" SMOKE_MIN_SCHOOLS=2 bash scripts/smoke-test.sh 2>&1 | tee /tmp/ujiB-smoke-pos.log; echo "exit=${PIPESTATUS[0]}"
2026-10-15T22:50:23Z PASS  GET /up = 200
2026-10-15T22:50:27Z PASS  login staff = 200 + access_token
2026-10-15T22:50:28Z PASS  GET /auth/me = 200, role super_admin
2026-10-15T22:50:30Z PASS  GET /schools = 200, meta.total=2
2026-10-15T22:50:30Z RINGKASAN smoke test: PASS=4 FAIL=0
exit=0
```

Negatif (password sengaja salah; harus gagal):

```
$ SMOKE_BASE_URL=http://127.0.0.1:8001 SMOKE_USERNAME=superadmin1 SMOKE_PASSWORD="salah-sengaja-123" SMOKE_MIN_SCHOOLS=2 bash scripts/smoke-test.sh 2>&1 | tee /tmp/ujiB-smoke-neg.log; echo "exit=${PIPESTATUS[0]}"
2026-10-15T22:55:24Z PASS  GET /up = 200
2026-10-15T22:55:26Z FAIL  login staff = 401 (429 = rate limit login: php artisan cache:clear)
2026-10-15T22:55:26Z FAIL  GET /auth/me dan /schools dilewati (tidak ada token)
2026-10-15T22:55:26Z RINGKASAN smoke test: PASS=1 FAIL=2
exit=1
```

Aplikasi sementara dimatikan (`kill %1`; `/up` kembali `000`). Smoke test hanya memakai `curl`, jadi tidak terpengaruh BUG-1.

## 7. Uji D — concurrency 5 partial unique index

Pada DB fresh `anak_tumbuh_ops_a` (pembungkus `psql` aktif):

```
$ bash scripts/test-partial-unique-concurrency.sh --confirm --db anak_tumbuh_ops_a 2>&1 | tee /tmp/ujiD.log; echo "exit=${PIPESTATUS[0]}"
2026-10-15T23:04:46Z == uq_academic_years_one_active_per_school (salinan: academic_years_school_id_idx1) pada tabel academic_years
2026-10-15T23:04:50Z PASS  uq_academic_years_one_active_per_school | A-commit: B menunggu (Lock:transactionid, 2.91s) lalu GAGAL 23505 pada "academic_years_school_id_idx1"; baris akhir=1
2026-10-15T23:04:54Z PASS  uq_academic_years_one_active_per_school | A-rollback: B menunggu (Lock:transactionid, 2.79s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:04:58Z PASS  uq_academic_years_one_active_per_school | kontrol non-predikat: B TIDAK menunggu (0.23s) dan berhasil; baris akhir=2
2026-10-15T23:04:59Z == uq_headmasters_one_active_per_school (salinan: headmasters_school_id_idx1) pada tabel headmasters
2026-10-15T23:05:03Z PASS  uq_headmasters_one_active_per_school | A-commit: B menunggu (Lock:transactionid, 2.79s) lalu GAGAL 23505 pada "headmasters_school_id_idx1"; baris akhir=1
2026-10-15T23:05:07Z PASS  uq_headmasters_one_active_per_school | A-rollback: B menunggu (Lock:transactionid, 2.95s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:05:12Z PASS  uq_headmasters_one_active_per_school | kontrol non-predikat: B TIDAK menunggu (0.54s) dan berhasil; baris akhir=2
2026-10-15T23:05:12Z == uq_tca_one_active_per_teacher_year (salinan: teacher_class_assignments_teacher_id_academic_year_id_idx) pada tabel teacher_class_assignments
2026-10-15T23:05:16Z PASS  uq_tca_one_active_per_teacher_year | A-commit: B menunggu (Lock:transactionid, 2.80s) lalu GAGAL 23505 pada "teacher_class_assignments_teacher_id_academic_year_id_idx"; baris akhir=1
2026-10-15T23:05:20Z PASS  uq_tca_one_active_per_teacher_year | A-rollback: B menunggu (Lock:transactionid, 2.82s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:05:24Z PASS  uq_tca_one_active_per_teacher_year | kontrol non-predikat: B TIDAK menunggu (0.23s) dan berhasil; baris akhir=2
2026-10-15T23:05:25Z == uq_tca_one_active_per_rombel_year (salinan: teacher_class_assignments_rombel_id_academic_year_id_idx) pada tabel teacher_class_assignments
2026-10-15T23:05:29Z PASS  uq_tca_one_active_per_rombel_year | A-commit: B menunggu (Lock:transactionid, 2.78s) lalu GAGAL 23505 pada "teacher_class_assignments_rombel_id_academic_year_id_idx"; baris akhir=1
2026-10-15T23:05:33Z PASS  uq_tca_one_active_per_rombel_year | A-rollback: B menunggu (Lock:transactionid, 2.82s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:05:37Z PASS  uq_tca_one_active_per_rombel_year | kontrol non-predikat: B TIDAK menunggu (0.50s) dan berhasil; baris akhir=2
2026-10-15T23:05:37Z == uq_sca_one_active_per_student_year (salinan: student_class_assignments_student_id_academic_year_id_idx) pada tabel student_class_assignments
2026-10-15T23:05:41Z PASS  uq_sca_one_active_per_student_year | A-commit: B menunggu (Lock:transactionid, 2.80s) lalu GAGAL 23505 pada "student_class_assignments_student_id_academic_year_id_idx"; baris akhir=1
2026-10-15T23:05:46Z PASS  uq_sca_one_active_per_student_year | A-rollback: B menunggu (Lock:transactionid, 2.81s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:05:50Z PASS  uq_sca_one_active_per_student_year | kontrol non-predikat: B TIDAK menunggu (0.22s) dan berhasil; baris akhir=2
2026-10-15T23:05:50Z RINGKASAN: PASS=15 FAIL=0 (HOLD=3s, batas tunggu minimal=1.50s, db=anak_tumbuh_ops_a)
exit=0
```

Schema sementara sesudahnya: `SELECT count(*) FROM pg_namespace WHERE nspname='ops_concurrency_test'` = `0`.

Kontrol negatif: backup yang sama di-restore ke `anak_tumbuh_ops_mutant` (`RESTORE TERVERIFIKASI`), lalu `DROP INDEX uq_tca_one_active_per_rombel_year` (tidak ada keluaran karena `-q`), lalu skrip dijalankan; **harus gagal**:

```
$ bash scripts/test-partial-unique-concurrency.sh --confirm --db anak_tumbuh_ops_mutant 2>&1 | tee /tmp/ujiD-negatif.log; echo "exit=${PIPESTATUS[0]}"
2026-10-15T23:16:15Z == uq_academic_years_one_active_per_school (salinan: academic_years_school_id_idx1) pada tabel academic_years
2026-10-15T23:16:19Z PASS  uq_academic_years_one_active_per_school | A-commit: B menunggu (Lock:transactionid, 2.78s) lalu GAGAL 23505 pada "academic_years_school_id_idx1"; baris akhir=1
2026-10-15T23:16:23Z PASS  uq_academic_years_one_active_per_school | A-rollback: B menunggu (Lock:transactionid, 2.77s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:16:27Z PASS  uq_academic_years_one_active_per_school | kontrol non-predikat: B TIDAK menunggu (0.24s) dan berhasil; baris akhir=2
2026-10-15T23:16:28Z == uq_headmasters_one_active_per_school (salinan: headmasters_school_id_idx1) pada tabel headmasters
2026-10-15T23:16:32Z PASS  uq_headmasters_one_active_per_school | A-commit: B menunggu (Lock:transactionid, 2.97s) lalu GAGAL 23505 pada "headmasters_school_id_idx1"; baris akhir=1
2026-10-15T23:16:36Z PASS  uq_headmasters_one_active_per_school | A-rollback: B menunggu (Lock:transactionid, 2.81s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:16:40Z PASS  uq_headmasters_one_active_per_school | kontrol non-predikat: B TIDAK menunggu (0.23s) dan berhasil; baris akhir=2
2026-10-15T23:16:41Z == uq_tca_one_active_per_teacher_year (salinan: teacher_class_assignments_teacher_id_academic_year_id_idx) pada tabel teacher_class_assignments
2026-10-15T23:16:45Z PASS  uq_tca_one_active_per_teacher_year | A-commit: B menunggu (Lock:transactionid, 2.80s) lalu GAGAL 23505 pada "teacher_class_assignments_teacher_id_academic_year_id_idx"; baris akhir=1
2026-10-15T23:16:49Z PASS  uq_tca_one_active_per_teacher_year | A-rollback: B menunggu (Lock:transactionid, 2.81s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:16:53Z PASS  uq_tca_one_active_per_teacher_year | kontrol non-predikat: B TIDAK menunggu (0.23s) dan berhasil; baris akhir=2
2026-10-15T23:16:53Z FAIL  uq_tca_one_active_per_rombel_year: index parsial TIDAK ditemukan/definisinya berbeda pada salinan (periksa migrasi)
2026-10-15T23:16:54Z == uq_sca_one_active_per_student_year (salinan: student_class_assignments_student_id_academic_year_id_idx) pada tabel student_class_assignments
2026-10-15T23:16:58Z PASS  uq_sca_one_active_per_student_year | A-commit: B menunggu (Lock:transactionid, 2.82s) lalu GAGAL 23505 pada "student_class_assignments_student_id_academic_year_id_idx"; baris akhir=1
2026-10-15T23:17:02Z PASS  uq_sca_one_active_per_student_year | A-rollback: B menunggu (Lock:transactionid, 2.83s) lalu BERHASIL; baris akhir=1 (id 2 = milik B)
2026-10-15T23:17:06Z PASS  uq_sca_one_active_per_student_year | kontrol non-predikat: B TIDAK menunggu (0.27s) dan berhasil; baris akhir=2
2026-10-15T23:17:06Z RINGKASAN: PASS=12 FAIL=1 (HOLD=3s, batas tunggu minimal=1.50s, db=anak_tumbuh_ops_mutant)
exit=1
```

Schema sementara di DB tiruan sesudahnya: `0`.

**Perbandingan dengan 16.15 (`ops-001-bukti.md`, uji D):** 15/15 dan kontrol negatif (12 PASS, 1 FAIL, `exit=1`) **sama**. Waktu tunggu B pada A-commit/A-rollback 2,77–2,97 dtk (16.15: 2,93–2,95 dtk). Kontrol non-predikat 0,22–0,54 dtk (16.15: 0,04–0,06 dtk); kemungkinan karena pembuatan proses lebih lambat di Windows (perkiraan, tidak terbukti); keduanya jauh di bawah batas 1,50 dtk.

Waktu indikatif lain: backup 3 dtk (79.348 byte); restore sekitar 7 dtk dari mulai sampai selesai verifikasi; keduanya data kecil dan bukan pengukuran yang valid untuk produksi.

## 8. Uji E (alur deploy PASS dan FAIL → restore): belum dijalankan

Tidak dijalankan pada sesi ini; ditunda ke tahap terpisah dan akan ditambahkan di dokumen ini setelah dijalankan. Alasan: langkahnya panjang (maintenance `down`/`up`, menggulung migration di DB tiruan, simulasi rilis rusak, tukar nama database) dan `php artisan down` menulis berkas di `storage/framework/` repo pemilik, sehingga perlu dikerjakan hati-hati dan terpisah. Akibatnya butir "simulasi deploy penuh" pada runbook bagian 10 tetap kosong.

## 9. Temuan

| Kelas | Temuan | Bukti / status |
|---|---|---|
| **BUG** (khusus Windows) | **BUG-1:** `scripts/backup.sh` gagal di Git Bash dengan `psql.exe` karena `psql.exe` menulis `\r\n` ke pipa; nilai snapshot dari `coproc` membawa `\r` dan ditolak `pg_dump --snapshot` (`invalid snapshot identifier`). Tidak terlihat di sandbox Linux Orang 1 | Bagian 6.1 (log gagal + `od -c`). `restore.sh` (baris 62, 68, 81, 90) juga membaca keluaran `psql` ke variabel lewat `$(...)`, dan `test-partial-unique-concurrency.sh` memanggil `psql`; apakah keduanya gagal tanpa pembungkus **tidak diuji** |
| **NEEDS DECISION** | Apakah Windows native perlu didukung (patch minimal terpisah, mis. membuang `\r` dari keluaran `psql` di `scripts/lib/ops-common.sh`), atau skrip OPS-001 dinyatakan hanya untuk Linux/WSL (produksi memang Linux). Patch minimal belum dibuat dan tidak ada skrip yang diubah | Menunggu keputusan pemilik |
| **NEEDS DECISION** | Versi PostgreSQL yang dianggap "lokal tim" pada runbook bagian 1: 17.11 (server uji), 14.5 (Laragon pemilik), atau 14.24 (service Windows). Ketiganya tercatat apa adanya di runbook | Menunggu keputusan pemilik |
| **DOKUMENTASI** | Runbook bagian 1 menulis "PHP ≥ 8.2", tetapi `composer.lock` memuat paket yang mensyaratkan 8.3 (`intervention/image`, `intervention/gif`) dan `vendor/composer/platform_check.php` memeriksa `PHP_VERSION_ID >= 80300`; PHP minimum efektif tampaknya 8.3. Dari pembacaan berkas, tidak dijalankan dengan PHP 8.2 | Usulan: perbaiki baris itu (tidak diterapkan di patch ini) |
| **TECHNICAL NOTE** | Kegagalan pertama dari tugas (`pg_dump v14` vs server v17) berasal dari lingkungan Orang 1 dan tidak direproduksi di komputer pemilik, yang tidak punya server 17. Pengaman versi skrip tidak diubah dan tidak terpicu | Bagian 3 butir 1 |
| **TECHNICAL NOTE** | Backup, restore, banding tabel, dan uji concurrency dijalankan **dengan pembungkus `psql`**; hasilnya tidak membuktikan skrip berjalan apa adanya di Windows | Bagian 4 |
| **TECHNICAL NOTE** | Git Bash (mintty): alias `winpty` untuk `psql`/`php`, tampilan terminal tertimpa pada keluaran `psql.exe`, dan `cmd.exe /c ver` menggantung; hanya memengaruhi tampilan/kenyamanan, bukan skrip | Bagian 3 butir 4, 5, 7 |
| **TECHNICAL NOTE** | Tidak ada perbedaan perilaku PostgreSQL 16.15 vs 17.11 yang terlihat; selisih waktu bersifat indikatif | Bagian 2 dan 7 |

## 10. Belum terverifikasi

- Uji E1/E2 (alur deploy PASS dan FAIL → restore, tukar nama database).
- `chmod 600`/`umask 077` pada berkas backup: tidak dapat dibuktikan di NTFS; wajib diverifikasi di server Linux.
- Skrip OPS-001 apa adanya (tanpa pembungkus) di Windows; restore dan concurrency tanpa pembungkus.
- Linux/WSL dengan PostgreSQL 17.x; PostgreSQL 16 di Windows.
- Staging dan produksi; backup terjadwal (cron), retensi pada server nyata, lokasi offsite (`BACKUP_OFFSITE_CMD`), enkripsi backup, data berukuran realistis, durasi nyata.
- `APP_ENV=production` melindungi DB aplikasi (uji dijalankan dengan `APP_ENV=local`).
- Pengaman versi klien/server pada skrip (tidak terpicu dalam uji ini).
- Hanya mendengar di localhost untuk server uji (pengaturan bawaan, tidak diperiksa ulang).

## 11. Usulan opsional (belum diterapkan, perlu persetujuan)

Tambahkan ke `.gitignore` pola keluaran backup sebagai pengaman tambahan agar tidak tercommit tanpa sengaja: `*.dump`, `*.dump.sha256`, `*.counts.tsv`, dan `backup.log`. Saat ini `.gitignore` hanya melindungi `.env`; folder backup uji sudah berada di luar repo. **NEEDS DECISION.**
