# Runbook Deployment — Backup, Restore, Migration, Rollback (OPS-001)

**Status dokumen.** Prosedur dan skrip di sini dibuat dan diuji pada **sandbox penyusun** (PostgreSQL 16.15, PHP 8.3.6, Laravel 11.56.1 sesuai `composer.lock`, HEAD `afa658c`). Bukti mentah ada di `docs/ops/ops-001-bukti.md`. **Belum diverifikasi di staging/produksi**: lihat bagian 10 (checklist yang harus diulang tim).

**Di luar OPS-001 (checklist DEPLOYMENT-001, tidak dikerjakan di sini):** config timeout import 180 detik, seed Super Admin produksi, `photos:migrate-to-private`, pengukuran Postman 10.14 dan 14.01, verifikasi nilai produksi, lokasi backup di luar server, enkripsi file backup, backup folder `storage/`.

---

## 1. Prasyarat dan versi

| Item | Ketentuan |
|---|---|
| PostgreSQL klien (`psql`, `pg_dump`, `pg_restore`) | **Versi klien mengikuti server produksi.** `pg_dump` harus sama atau lebih baru dari server; `pg_restore` harus **sama atau lebih baru** dari `pg_dump` yang membuat backup. Skrip menolak bila aturan ini dilanggar. |
| Versi PostgreSQL produksi | **Belum ditentukan** (keputusan DEPLOYMENT-001). |
| Versi PostgreSQL lokal tim | **Belum diputuskan (NEEDS DECISION, pemilik).** Nilai nyata dari verifikasi lokal 15 Okt 2026 (`docs/ops/ops-001-bukti-lokal.md`): server uji **17.11** (`SELECT version();` = `PostgreSQL 17.11 on x86_64-windows, compiled by msvc-19.44.35229, 64-bit`; zip binaries, Windows 11, port 5434) dengan klien `psql`/`pg_dump`/`pg_restore` **17.11**. Server lain di komputer pemilik yang sama, tidak dipakai uji: **14.5** (Laragon, port 5432) dan **14.24** (service Windows, port 5433). Penyusun OPS-001 (Orang 1) memakai 16.15 di sandbox Linux; itu bukan versi tim. |
| PHP / Laravel | PHP ≥ 8.2; Laravel **tetap 11.56.1** (risiko 6 advisory diterima pemilik secara tertulis; lihat `docs/ops/security-001-env.md`). Jangan upgrade saat deploy. |
| Akun database | Backup: hak `SELECT` pada semua tabel. Restore: hak `CREATEDB` (jika memakai `--create-db`) atau database kosong yang sudah disiapkan DBA. |
| Variabel koneksi | Skrip membaca `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` dari environment, atau dari `.env` bila variabelnya belum diset. Password dikirim lewat `PGPASSWORD`, tidak pernah lewat argumen perintah. |

**`.env` (termasuk `APP_KEY` dan `JWT_SECRET`) TIDAK ikut `pg_dump`.** Simpan terpisah di tempat rahasia (secret manager atau brankas tim), bukan di dalam folder backup. Hilangnya `JWT_SECRET` membuat semua token ditolak; `APP_KEY` yang berbeda membuat semua data yang dienkripsi Laravel tidak terbaca. Jangan pernah commit `.env`.

## 2. Backup

```bash
BACKUP_DIR=/var/backups/anak-tumbuh BACKUP_RETENTION_DAYS=14 scripts/backup.sh
```

- Format `pg_dump --format=custom` (`--no-owner --no-privileges`), diambil dalam **satu snapshot** bersama hitungan baris tiap tabel, sehingga manifes cocok dengan isi dump walau aplikasi sedang menulis.
- Keluaran per backup: `<DB>_<UTC>.dump`, `.dump.sha256`, `.counts.tsv` (manifes jumlah baris), `.meta` (versi PostgreSQL/pg_dump, migrasi terakhir, SHA kode). Folder `chmod 700`, berkas `chmod 600`.
- Verifikasi bawaan: `pg_restore --list` harus lolos dan manifes tidak kosong; bila gagal, backup dianggap rusak (exit 1) dan tidak disimpan.
- Retensi: `BACKUP_RETENTION_DAYS` (default **14**, bisa diubah; `0` = tidak menghapus). Hanya berkas milik database ini yang dihapus.
- **Lokasi di luar server:** diputuskan di DEPLOYMENT-001. Tersedia hook `BACKUP_OFFSITE_CMD` yang dijalankan setelah backup lokal sukses dengan variabel `BACKUP_FILE`, `BACKUP_BASE`, `BACKUP_DIR`. Contoh (hanya ilustrasi, bukan keputusan): `BACKUP_OFFSITE_CMD='rsync -a "$BACKUP_DIR/$BACKUP_BASE".* backup-host:/data/anak-tumbuh/'`. Tanpa hook, skrip mencetak peringatan.
- **Enkripsi file backup: tidak diimplementasikan** (keputusan tim: dicatat untuk DEPLOYMENT-001). Untuk sekarang hanya izin berkas 600. Backup berisi data pribadi siswa dan hash password: jangan taruh di dalam `public/` atau `storage/`.
- Exit code: `0` sukses · `1` gagal · `2` backup lokal sukses tetapi hook offsite gagal.
- **Jadwal harian** (contoh cron, jam dan akun disesuaikan server): `15 1 * * * cd /var/www/anak-tumbuh && BACKUP_DIR=/var/backups/anak-tumbuh scripts/backup.sh >> /var/log/anak-tumbuh-backup.log 2>&1`. Pantau dua hal: exit code bukan 0, dan tidak ada berkas `.dump` baru dalam 26 jam (`find /var/backups/anak-tumbuh -name '*.dump' -mmin -1560 | grep -q .`).

## 3. Restore

```bash
scripts/restore.sh --confirm --file /var/backups/anak-tumbuh/<DB>_<UTC>.dump --target-db <NAMA_BARU> [--create-db]
```

Skrip ini **destruktif terhadap target**, karena itu: wajib `--confirm`; menolak nama database produksi (daftar `OPS_PROTECTED_DBS`, pola `prod|production|live`, atau DB aplikasi saat `APP_ENV=production`); menolak target yang sudah berisi tabel; menolak `pg_restore` yang lebih lama dari `pg_dump` pembuat; memeriksa checksum. Restore berjalan dalam satu transaksi (`--single-transaction --exit-on-error`): gagal berarti target tidak berubah.

Setelah restore skrip **membandingkan jumlah baris semua tabel** dengan manifes dan memeriksa migrasi terakhir. Exit code: `0` terverifikasi · `1` gagal/ditolak · `3` restore selesai tetapi tidak cocok (**jangan dipakai**).

**Memulihkan produksi (tanpa menyentuh nama produksi).** Restore ke nama baru, hentikan aplikasi, lalu tukar nama:

```sql
-- psql -d postgres ; aplikasi sudah di mode maintenance
SELECT pg_terminate_backend(pid) FROM pg_stat_activity
 WHERE datname IN ('<DB_PROD>','<DB_BARU>') AND pid <> pg_backend_pid();
ALTER DATABASE "<DB_PROD>" RENAME TO "<DB_PROD>_failed_<UTC>";   -- simpan untuk analisis
ALTER DATABASE "<DB_BARU>" RENAME TO "<DB_PROD>";
```

### Dampak setelah restore (wajib dipahami dan dikomunikasikan)

1. **Data setelah waktu backup HILANG** (pendaftaran, habit, perubahan password, dst.). Nilai sequence ikut kembali ke waktu backup, jadi ID yang sempat dilihat klien setelah waktu itu dapat dipakai ulang oleh data baru.
2. **Token yang password-nya berubah setelah backup ditolak (401)** karena klaim `pwd` (F-29) tidak cocok dengan hash hasil restore. Token yang terbit sebelum backup tetap valid selama password sama. Password yang diubah setelah backup kembali ke nilai lama.
3. **Jalankan `php artisan cache:clear`.** Cache, session, dan rate limiter memakai tabel database; isi tabel `cache` hasil restore berasal dari waktu backup.
4. File di `storage/` (foto, logo) tidak ikut backup database (DEPLOYMENT-001).

## 4. Migration

- **Deploy pertama / database kosong:** `php artisan migrate:fresh --seed --force` (jalur Fresh, DB Design §10.A). Seeder menghasilkan 2 Super Admin (password dari `SEED_SUPER_ADMIN_PASSWORD`), 7 habit, 28 indikator, 101 level.
- **`migrate:fresh` menghapus SEMUA tabel. Jangan pernah dijalankan pada database yang berisi data.**
- **Deploy berikutnya:** `php artisan migrate --force`. Seeder **tidak** ikut berjalan. Lihat dulu `php artisan migrate:status --pending` dan SQL-nya dengan `php artisan migrate --pretend`.
- Tidak ada backfill atau migrasi data di migration (sesuai DB Design).

## 5. Kebijakan rollback (keputusan tim)

**Restore dari backup adalah jalur rollback utama.** `php artisan migrate:rollback --step=N` hanya boleh dipakai bila **semua** migration pada N langkah terakhir bersifat alter-only.

| Jenis | Migration | Perilaku `down()` |
|---|---|---|
| Alter-only (boleh `--step`) | `2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments`, `2026_10_04_100100_add_unique_to_schools_name`, `2026_10_04_110000_make_audit_logs_target_user_id_nullable` | Menghapus index/unique; yang ketiga **berhenti dengan pesan jelas** (F-31) bila ada `audit_logs` tanpa `target_user_id` |
| Create-table (**JANGAN** di-rollback) | 19 migration lainnya | `dropIfExists`: **tabel beserta datanya hilang** |

- `migrate:rollback` **tanpa `--step`** setelah deploy pertama menjalankan seluruh batch 1 = **22 `DROP TABLE`**.
- Bahaya yang tidak terlihat: setelah rollback sebuah migration create lalu `migrate` ulang, tabel dibuat **kosong** tanpa seeder. Pada uji, `gamification_levels` kosong membuat `GET /gamification/me` dan dashboard student tetap **HTTP 200 tetapi `gamification_level` menjadi 0** (seharusnya 4). Tidak ada error yang memberi tahu. Karena itu rollback create-table tidak dipakai untuk pemulihan.
- Sebelum rollback apa pun: `php artisan migrate:rollback --step=N --pretend`, periksa isinya, dan pastikan backup baru ada.

## 6. Alur deploy end-to-end

| # | Langkah | Perintah / catatan |
|---|---|---|
| 0 | Pre-flight | Catat SHA rilis; `php artisan migrate:status --pending`; `migrate --pretend`; pastikan backup terakhir < 24 jam; ruang disk cukup; versi klien PostgreSQL sesuai bagian 1; `.env` produksi ada (tidak di-commit). |
| 1 | Maintenance | `php artisan down --retry=60`. **Perhatian:** `GET /up` tetap **200** saat maintenance, sedangkan API menjawab **503**; jadi `/up` bukan penanda maintenance sudah selesai. |
| 2 | Backup | `BACKUP_DIR=... scripts/backup.sh` — wajib exit `0` (exit `2` hanya bila offsite memang tidak wajib; diputuskan DEPLOYMENT-001). |
| 3 | Deploy kode | Tarik rilis dan `composer install --no-dev --optimize-autoloader` (spesifik hosting: DEPLOYMENT-001). |
| 4 | Migrate | `php artisan migrate --force` |
| 5 | Cache | `php artisan cache:clear` |
| 6 | Online | `php artisan up` |
| 7 | Smoke test | `SMOKE_BASE_URL=... SMOKE_USERNAME=... SMOKE_PASSWORD=... SMOKE_MIN_SCHOOLS=1 scripts/smoke-test.sh` (4 cek: `/up`, login staff, `/auth/me`, `/schools`). |
| 8 | Keputusan | **PASS** → selesai, catat durasi dan SHA. **FAIL** → jalur FAIL di bawah. |

**Jalur FAIL:** (1) `php artisan down`; (2) kembalikan kode rilis lama; (3) `scripts/restore.sh --confirm --create-db --file <backup langkah 2> --target-db <NAMA_BARU>` sampai terverifikasi; (4) tukar nama database (bagian 3); (5) `php artisan cache:clear`; (6) `php artisan up`; (7) smoke test harus PASS; (8) komunikasikan dampak setelah restore (bagian 3) dan simpan database yang gagal untuk analisis.

Catatan smoke test: login memakai rate limit 5/menit per IP. Bila FAIL dengan 429, jalankan `php artisan cache:clear` lalu ulangi. Smoke test ini minimal; smoke test produksi penuh (semua role, CRUD, upload, QR) adalah DEPLOYMENT-001. Perilaku saat `migrate` gagal di tengah jalan **tidak diuji** di OPS-001; jalur FAIL tetap restore.

## 7. Uji concurrent insert untuk partial unique index

```bash
scripts/test-partial-unique-concurrency.sh --confirm [--db NAMA_DB]
```

Menguji 5 index (`academic_years`, `headmasters`, `teacher_class_assignments` ×2, `student_class_assignments`) dengan dua sesi `psql` paralel: sesi B terbukti **menunggu** (`wait_event Lock`) lalu **gagal `23505`** pada index yang benar setelah sesi A commit; bila sesi A rollback, sesi B **berhasil**; baris di luar predikat parsial tidak menunggu. Pengujian memakai schema sementara berisi salinan struktur (`LIKE ... INCLUDING INDEXES`) sehingga baris dan sequence asli tidak tersentuh; definisi index salinan dicocokkan dengan database terlebih dulu. Menolak jalan tanpa `--confirm` dan menolak database produksi. Jalankan pada salinan hasil restore atau staging, terutama setelah migration yang menyentuh index.

## 8. Ringkasan perintah

| Tujuan | Perintah |
|---|---|
| Backup | `BACKUP_DIR=... scripts/backup.sh` |
| Restore | `scripts/restore.sh --confirm --file ... --target-db ... [--create-db]` |
| Migrasi bertahap | `php artisan migrate --force` |
| Mode maintenance | `php artisan down --retry=60` / `php artisan up` |
| Smoke test | `scripts/smoke-test.sh` (env `SMOKE_*`) |
| Uji concurrency | `scripts/test-partial-unique-concurrency.sh --confirm` |
| Setelah restore | `php artisan cache:clear` |

## 9. Batas dan hal yang perlu diketahui

- **Menjalankan `php artisan test`:** bila `.env` memakai nilai `.env.example` (`APP_URL=https://...`), 2 test `EnforceHttpsTest` gagal karena base URL test sudah HTTPS. Jalankan dengan `APP_URL=http://localhost` dan database terpisah (test memakai `RefreshDatabase` dan **menghapus isi database yang dipakai**). Hasil pada sandbox: 518 passed / 2814 assertions (sama dengan baseline).
- Durasi backup/restore/migrate yang tercatat di bukti berasal dari data kecil (puluhan baris). Waktu di produksi bergantung ukuran data dan harus diukur di staging.
- Tidak ada pengujian pada versi PostgreSQL selain 16.15.

## 10. Checklist verifikasi di staging (harus diulang tim)

- [ ] Isi versi PostgreSQL lokal tim dan versi produksi (bagian 1); pastikan versi klien mengikuti server. — lokal PG17: server uji 17.11 dan klien 17.11 tercatat; versi "lokal tim" dan produksi: NEEDS DECISION; staging: DEPLOYMENT-001.
- [ ] Jalankan `scripts/backup.sh` terjadwal di server nyata; pastikan berkas, izin, retensi, dan peringatan jika backup berhenti. — lokal PG17: backup manual lulus (di Windows hanya dengan pembungkus `psql`; lihat BUG-1 di `docs/ops/ops-001-bukti-lokal.md`); `chmod 600` tidak bisa dibuktikan di NTFS; jadwal, izin, dan retensi: staging: DEPLOYMENT-001.
- [ ] Putuskan dan uji lokasi backup di luar server (`BACKUP_OFFSITE_CMD`) — DEPLOYMENT-001.
- [ ] Restore drill ke database terpisah di staging dengan data realistis; ukur durasi dump dan restore. — lokal PG17: restore ke database terpisah lulus (data kecil, 79.348 byte; backup 3 dtk, restore sekitar 7 dtk, indikatif); data realistis: staging: DEPLOYMENT-001.
- [ ] Simulasi deploy penuh jalur PASS dan jalur FAIL (bagian 6) di staging, termasuk tukar nama database dan `cache:clear`. — lokal PG17: belum dijalankan (uji E tertunda); staging: DEPLOYMENT-001.
- [ ] Jalankan uji concurrency pada salinan staging. — lokal PG17: lulus 15/15 pada DB fresh dan kontrol negatif gagal sebagaimana mestinya (dengan pembungkus `psql` di Windows); salinan staging: DEPLOYMENT-001.
- [ ] Pastikan `APP_ENV=production` di produksi sehingga skrip destruktif menolak DB aplikasi.
- [ ] Putuskan enkripsi file backup dan backup `storage/` (DEPLOYMENT-001).
