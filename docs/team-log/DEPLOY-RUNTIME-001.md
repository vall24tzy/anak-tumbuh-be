# DEPLOY-RUNTIME-001 — Log

**Status:** kode (config), test, dan addendum selesai; suite penuh **lulus (670 passed) baik baseline maupun dengan `DB_DISABLE_PREPARES=true`** pada PostgreSQL biasa (dilaporkan Orang 2). **Belum bisa DONE:** perilaku terhadap pooler nyata belum terbukti, aktifnya flag **terbukti** lewat skrip pemeriksa, dan bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` diisi pemilik (lihat "Belum selesai").
**PIC:** Orang 2 · **Tanggal:** 4 Nov 2026
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2; D-27 (final sebagai rencana, tabel keputusan pemilik yang diterima Orang 2 pada 7 Nov 2026); `docs/addendum/DEPLOY-RUNTIME-001.md`.
**Dependency:** DEPLOY-DECISION-001. **Catatan jujur:** D-26 sampai D-35 diputuskan pemilik menurut tabel yang diterima Orang 2 (7 Nov 2026); tabel itu belum ada di repo dan bagian 10 dokumen keputusan diisi pemilik. Bila D-27 berubah, config dan addendum perlu disesuaikan.

## Yang dikerjakan
1. `config/database.php`: koneksi `pgsql` membaca `DB_SSLMODE`, `DB_SSLROOTCERT`, dan dua flag opsi PDO yang default mati (`DB_DISABLE_PREPARES` disarankan, `DB_EMULATE_PREPARES` tidak disarankan); koneksi baru `pgsql_direct` (`DB_DIRECT_*`) untuk migrate dan seed.
2. `.env.example`: variabel baru tanpa rahasia, `CACHE_STORE=database` wajib, catatan sesi dan antrean tidak dipakai.
3. Test: `DatabaseConnectionConfigTest` (14), `TrustedProxiesEnabledTest` (6), `TrustedProxiesDisabledTest` (3), dan pembantu `tests/Concerns/SetsTemporaryEnv.php`.
4. `docs/addendum/DEPLOY-RUNTIME-001.md`.
5. Tidak ada perubahan di `app/`, `bootstrap/app.php`, endpoint, field, aturan bisnis, atau skema DB. `APP_TIMEZONE` tetap UTC.

## Hasil run yang dilaporkan Orang 2 (5 Nov 2026)
- Filter 32 test (`DatabaseConnectionConfigTest`, `TrustedProxies`, `ImageFilesTest`): 31 lulus, 1 gagal. Yang gagal adalah kesalahan test (header test client menumpuk), diperbaiki oleh `DEPLOY-RUNTIME-001-fix1-test-header.patch`; belum dijalankan ulang.
- Suite penuh dengan `DB_EMULATE_PREPARES=true`: **611 gagal, 58 lulus (410 assertions)**, 695,76 s. Semua kegagalan yang terlihat memuat `SQLSTATE[42883] operator does not exist: boolean = integer` dari migration `..._add_active_rombel_unique_to_teacher_class_assignments` (`where is_active = 1`). Penjelasan dan dampaknya ada di addendum bagian 3: emulate prepares merusak perbandingan boolean (kode aplikasi memakai pola yang sama di sekurangnya 10 tempat), jadi **flag itu tidak layak**. Usulan: mekanisme D-27 diganti menjadi `DB_DISABLE_PREPARES` (`PDO::PGSQL_ATTR_DISABLE_PREPARES`); **belum dijalankan**.
- Suite penuh baseline (kedua flag mati, fix1 dan fix2 terpasang): **670 passed (3842 assertions)**.
- Suite penuh dengan `DB_DISABLE_PREPARES=true`: **670 passed (3842 assertions)**, identik dengan baseline. 670 = 637 (branch Orang 1) + 33 test baru (23 RUNTIME + 10 IMAGE).
- Pemeriksaan flag (`check-prepares.php`, skrip sekali pakai di luar repo, dijalankan Orang 2 di Cmder, PHP 8.3.30): **tanpa flag** opsi `PGSQL_ATTR_DISABLE_PREPARES` tidak ada di config dan server menahan **2** prepared statement bernama; **dengan `DB_DISABLE_PREPARES=true`** opsi itu bernilai `true` di config dan server menahan **0**. Jadi flag aktif dan benar-benar menghilangkan prepared statement bernama pada koneksi. (Perkiraan awal "1 tanpa flag" salah: hitungan ikut menghitung query pemeriksa itu sendiri.)
- Lingkungan: PHP 8.3.30 (Laragon; `composer diagnose` di Cmder menunjukkan PHP yang sama), Cmder, PostgreSQL biasa (bukan pooler). Tidak ada baris dilewati pada ringkasan `Tests:`, sehingga `MailExposureGuardTest`, `FrameworkAdvisoryGuardTest`, dan `StorageDiskConfigTest` ikut lulus tanpa dilewati (diturunkan dari ringkasan, bukan dari daftar per test).

## Yang diperiksa dan yang tidak
- Diperiksa dengan membaca kode dan vendor: `bootstrap/app.php` dan `EnforceHttps` (SECURITY-001), limiter login (`AppServiceProvider`), `Connector::getOptions()` (opsi `options` PDO digabung dengan bawaan), `TrustProxies` (daftar proxy di properti static; ada `flushState()`), `PostgresConnector` (membaca `sslmode` dan `sslrootcert`), `config/jwt.php` (blacklist memakai cache), pemakaian queue/session/scheduler di `app/` dan `routes/`, dan fitur SQL yang rusak di pooler transaksi (tidak ditemukan; lihat addendum bagian 3).
- **Tidak dijalankan:** sandbox tanpa PHP, composer, PostgreSQL, dan jaringan. Tidak ada test, `php -l`, atau suite yang dijalankan; sintaks hanya diperiksa dengan menghitung kurung. Sintaks dan perilaku 22 test baru belum terbukti.
- **Tidak dikerjakan:** pembuktian terhadap pooler mode transaksi nyata (emulate prepares terbukti gagal; `DB_DISABLE_PREPARES` lulus suite pada PostgreSQL biasa, tetapi pooler belum diuji); tidak ada koneksi ke Supabase atau pooler.

## Belum selesai (syarat DONE)
1. Pembuktian terhadap pooler mode transaksi dan Vercel nyata di DEPLOY-VERIFY-001 dan DEPLOY-PILOT-001 (termasuk `X-Forwarded-For` palsu lewat Vercel).
2. Bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` diisi pemilik (D-27 sudah diputuskan memakai `DB_DISABLE_PREPARES`).
3. Review silang Orang 1.

## Temuan
1. `.env.testing` lokal memakai `APP_TIMEZONE=Asia/Jakarta` (tidak di-git), berbeda dari produksi (UTC).
2. `phpunit.xml` memaksa cache dan sesi `array`; suite tidak menguji limiter dan blacklist terhadap cache `database`.
3. `pgsql_direct` jatuh ke `DB_*` bila `DB_DIRECT_*` kosong: praktis di lokal, berbahaya di lingkungan pooler bila lupa diisi.
4. Dengan `TRUSTED_PROXIES=*`, perlindungan rate limit per IP bergantung pada Vercel menimpa `X-Forwarded-For` klien; tidak dapat dibuktikan dari repo.
5. `PostgresConnector` menjalankan `set search_path to "public"` tiap koneksi dibuka (dari config `search_path`); efeknya di pooler transaksi belum terbukti (D-27 meminta pemeriksaan ini; lihat addendum bagian 3).
6. D-27 menetapkan migrate dan seed lewat pooler **session** (5432), bukan koneksi langsung (IPv6 saja); `DB_DIRECT_URL` diarahkan ke pooler session.
