# DEPLOY-RUNTIME-001 — Koneksi PostgreSQL untuk pooler Supabase dan konfigurasi proxy/runtime

**Tanggal:** 4 Nov 2026 · **PIC:** Orang 2 · **Sifat perubahan:** konfigurasi dan test; **tidak ada** perubahan endpoint, field respons, aturan bisnis, skema DB, maupun kode di `app/`.
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2 (DEPLOY-RUNTIME-001), D-27 (final sebagai rencana menurut tabel keputusan pemilik yang diterima Orang 2 pada 7 Nov 2026; teks awal usulan di `SoT-Amendment-DEPLOY-D26-D33.md`), SECURITY-001 butir 4 (HTTPS), F-23 (rate limit login), Contract v1.3 bagian 7 (UTC).
**Catatan dependency:** D-27 diputuskan pemilik sebagai rencana (tabel keputusan pemilik yang diterima Orang 2 pada 7 Nov 2026): pooler mode transaksi untuk aplikasi dengan `DB_DISABLE_PREPARES` (bukan emulate prepares), migrate dan seed lewat pooler session (5432). Bukti pooler nyata adalah syarat lulus DEPLOY-VERIFY-001; jalan mundur: pooler session dengan batas koneksi. Persetujuan tertulis di bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` diisi pemilik; tabel keputusan itu belum ada di repo.

## 1. Perubahan

| Berkas | Isi |
|---|---|
| `config/database.php` | Koneksi `pgsql`: `sslmode` dan `sslrootcert` lewat env, opsi PDO lewat dua flag yang default mati: `DB_DISABLE_PREPARES` (`PGSQL_ATTR_DISABLE_PREPARES`, disarankan) dan `DB_EMULATE_PREPARES` (`ATTR_EMULATE_PREPARES`, tidak disarankan; lihat bagian 3). Koneksi baru `pgsql_direct` untuk migrate dan seed. `DB_URL` sudah dibaca sebelumnya. |
| `.env.example` | Variabel baru (nama saja; `DB_URL` dan `DB_DIRECT_*` sebagai komentar tanpa nilai), penjelasan `CACHE_STORE=database` wajib, serta catatan sesi dan antrean tidak dipakai. |
| `tests/Concerns/SetsTemporaryEnv.php` | Pembantu test: mengubah env sementara dan memuat ulang satu berkas config. |
| `tests/Feature/Deploy/DatabaseConnectionConfigTest.php` | 14 test konfigurasi koneksi (bagian 6). |
| `tests/Feature/Deploy/TrustedProxiesEnabledTest.php`, `TrustedProxiesDisabledTest.php` | 9 test proxy tepercaya (6 + 3), EnforceHttps, dan rate limit per IP (bagian 4). |

`bootstrap/app.php` **tidak diubah**: pembacaan `TRUSTED_PROXIES` lewat env sudah ada sejak SECURITY-001. Penjelasan Task Board "bootstrap/app.php" pada artefak terpenuhi oleh test dan dokumentasi di bawah.

## 2. Variabel environment koneksi database (nama)

| Variabel | Fungsi | Nilai lokal / Supabase |
|---|---|---|
| `DB_URL` | URL koneksi aplikasi (pooler). Bila diisi, mengalahkan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | kosong / URL pooler (hanya di environment platform) |
| `DB_SSLMODE` | `sslmode` koneksi `pgsql` | `prefer` / `require` (atau `verify-full` + `DB_SSLROOTCERT`) |
| `DB_SSLROOTCERT` | berkas CA untuk `verify-full` | kosong |
| `DB_DISABLE_PREPARES` | `true` = query dan parameter dikirim sekaligus (`PDO::PGSQL_ATTR_DISABLE_PREPARES`), tanpa prepared statement bernama. Hanya koneksi `pgsql` | `false` / `true` bila lewat pooler mode transaksi (**belum dibuktikan**) |
| `DB_EMULATE_PREPARES` | `true` = PDO menyusun query di sisi klien. Hanya koneksi `pgsql`. **Merusak perbandingan boolean (bagian 3); biarkan `false`** | `false` / `false` |
| `DB_DIRECT_URL`, `DB_DIRECT_HOST`, `DB_DIRECT_PORT`, `DB_DIRECT_DATABASE`, `DB_DIRECT_USERNAME`, `DB_DIRECT_PASSWORD`, `DB_DIRECT_SSLMODE`, `DB_DIRECT_SSLROOTCERT` | koneksi `pgsql_direct` (langkah rilis) | kosong (jatuh ke `DB_*`) / diisi |

Aturan `pgsql_direct`: tiap `DB_DIRECT_*` yang kosong jatuh ke `DB_*` yang bersesuaian, **kecuali** `DB_DIRECT_URL`, yang tidak pernah mewarisi `DB_URL` (supaya migrate tidak diam-diam lewat pooler; dikunci test). Koneksi ini **tanpa** opsi emulate prepares (prepared statements bawaan PDO).

Pemakaian pada rilis:

```
php artisan migrate --database=pgsql_direct --force
php artisan db:seed --database=pgsql_direct --force
```

**Jangan dibiarkan jatuh ke `DB_*` bila aplikasi lewat pooler**: pooler dan koneksi langsung Supabase memakai host dan username yang berbeda, jadi `DB_DIRECT_URL` atau `DB_DIRECT_HOST` wajib diisi di lingkungan itu. **Usulan D-27 (mode gratis):** migrate dan seed lewat **pooler mode session (port 5432)**, bukan koneksi langsung, karena koneksi langsung Supabase hanya IPv6 (IPv4 add-on berbayar) sehingga laptop yang hanya punya IPv4 tidak dapat tersambung; jadi `DB_DIRECT_URL` diarahkan ke URL pooler session. Nama koneksi `pgsql_direct` dipertahankan dari Task Board; artinya "koneksi untuk langkah rilis", bukan harus koneksi langsung. (Fakta vendor dari `SoT-Amendment-DEPLOY-D26-D33.md` dan README Task Board; verifikasi ulang di VERIFY.)

## 3. Prepared statements di pooler mode transaksi dan keputusan D-27

### Hasil yang sudah ada (dilaporkan Orang 2; PHP 8.3.30 Laragon, PostgreSQL biasa)

| Run | Hasil |
|---|---|
| Filter `DatabaseConnectionConfigTest`, `TrustedProxies`, `ImageFilesTest` (flag mati, sebelum fix1) | 31 lulus, 1 gagal (278 assertions). Yang gagal = `TrustedProxiesEnabledTest::ip klien dibaca dari x forwarded for ...`: kesalahan test (test client Laravel menumpuk `withHeaders()` antar-request), diperbaiki fix1 |
| Suite penuh dengan `DB_EMULATE_PREPARES=true` | **611 gagal, 58 lulus (410 assertions)**, 695,76 s |
| Suite penuh baseline (kedua flag mati; fix1 dan fix2 terpasang) | **670 passed (3842 assertions)**, tidak ada baris gagal atau dilewati pada ringkasan |
| Suite penuh dengan `DB_DISABLE_PREPARES=true` | **670 passed (3842 assertions)**, sama dengan baseline |

670 = 637 (branch Orang 1) + 33 test baru task ini (23 RUNTIME + 10 IMAGE). Dua run memberi jumlah test dan assertion yang sama persis.

**Yang disimpulkan dan yang tidak.** Emulate prepares terbukti **tidak layak** (bagian di bawah). Dengan `DB_DISABLE_PREPARES=true` seluruh suite lulus pada PostgreSQL biasa, termasuk migrate dan semua query aplikasi; jadi tidak ada pola boolean yang rusak oleh opsi ini. **Flag terbukti aktif** lewat skrip pemeriksa sekali pakai: tanpa flag server menahan 2 prepared statement bernama, dengan `DB_DISABLE_PREPARES=true` 0 dan opsi `PGSQL_ATTR_DISABLE_PREPARES` = `true` di config (lihat team-log). **Belum terbukti:** perilaku terhadap pooler mode transaksi yang nyata, yang hanya dapat dibuktikan di DEPLOY-VERIFY-001. Run ini menguji PostgreSQL biasa, bukan pooler.

### Penyebab kegagalan flag emulate (terbukti dari pesan error)

Semua kegagalan yang terlihat memiliki satu pesan: `SQLSTATE[42883] operator does not exist: boolean = integer` pada `select "rombel_id", "academic_year_id", COUNT(*) AS total from "teacher_class_assignments" where "is_active" = 1 group by ...`. Query itu ada di **migration** `2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments.php` (`->where('is_active', true)`). `RefreshDatabase` menjalankan `migrate:fresh` melalui koneksi aplikasi; migration itu gagal, sehingga setiap test yang memakai database gagal (611), dan yang lolos (58) kemungkinan test tanpa database (dugaan).

Mekanismenya: Laravel mengubah boolean menjadi `1`/`0` sebelum mengikat (`Connection::prepareBindings`). Dengan prepared statement asli, parameter dikirim bertipe tak ditentukan sehingga PostgreSQL menyimpulkan `boolean` dari kolom. Dengan emulasi PDO, nilai disisipkan sebagai literal integer `1`, dan PostgreSQL menolak `boolean = integer`.

**Dampaknya bukan hanya migration.** Kode aplikasi memakai pola yang sama di sekurangnya 10 tempat (`where('is_active', true)` di `RankingService`, `StudentClassAssignmentService`, `DashboardService`, `TeacherService`, `AcademicYearService`, `TeacherAssignmentService`, `TeacherScopeService`, serta `update(['is_active' => false])` dan `where('allows_multi_select', ...)` pada `StudentHabitService`). Karena migration gagal lebih dulu, run itu **belum menunjukkan** apakah query aplikasi juga gagal; polanya sama sehingga hampir pasti gagal. Kesimpulan: `DB_EMULATE_PREPARES=true` **tidak layak** untuk aplikasi ini tanpa mengubah banyak kode.

### Mekanisme D-27 (diputuskan pemilik: `DB_DISABLE_PREPARES`, bukan emulate prepares)

Teks awal D-27 (usulan) menyebut "emulate prepares"; pemilik memutuskan `DB_DISABLE_PREPARES`. Tujuan sebenarnya adalah tidak memakai prepared statement bernama di pooler transaksi. Opsi PDO pgsql `PDO::PGSQL_ATTR_DISABLE_PREPARES` memenuhi tujuan itu tanpa menyisipkan literal: query dan parameter dikirim dalam satu panggilan, parameter tetap bertipe tak ditentukan. Karena itu ditambahkan flag `DB_DISABLE_PREPARES` (default mati, hanya koneksi `pgsql`). **Belum terbukti**: tidak ada run yang memakainya, dan perilaku terhadap pooler nyata hanya dapat dibuktikan di DEPLOY-VERIFY-001. Syarat suite penuh hijau dengan opsi itu aktif sudah terpenuhi pada PostgreSQL biasa (lihat tabel); bukti pooler nyata tetap di VERIFY.

Cara menjalankan (Cmder; catat `php -v` dari terminal yang sama; sudah dijalankan, lihat tabel di atas):

```
rem Run 1 - baseline (kedua flag mati)
php artisan test
rem Run 2 - DISABLE_PREPARES hidup
set DB_DISABLE_PREPARES=true
php artisan test
set DB_DISABLE_PREPARES=
```

PowerShell: `$env:DB_DISABLE_PREPARES = 'true'` dan `Remove-Item Env:DB_DISABLE_PREPARES`. Nilai env proses mengalahkan `.env.testing`. Catat jumlah passed/failed/assertions dan nama test yang gagal beserta pesan errornya; jangan diperbaiki diam-diam. Run ini menguji PostgreSQL biasa, **bukan** pooler mode transaksi (itu di VERIFY dan PILOT). Bila run 2 gagal, pooler mode session adalah alternatif D-27.

Bacaan statis kode (bukan hasil eksekusi):
- Tidak ada `pg_advisory_*`, `SET`/`set_config`, `LISTEN/NOTIFY`, tabel temporary, `DB::unprepared`, atau nama koneksi yang di-hardcode di `app/`; fitur berbasis sesi koneksi itulah yang rusak di pooler transaksi.
- **Perintah `SET` dari Laravel sendiri (diminta D-27 diperiksa):** `PostgresConnector::connect()` (vendor) menjalankan `set search_path to "public"` setiap koneksi dibuka karena config memuat `'search_path' => 'public'`. Isolation level, timezone, dan `synchronous_commit` hanya di-SET bila dikonfigurasi (tidak dikonfigurasi di sini). `client_encoding` dan `application_name` ada di DSN, bukan `SET`. Di pooler transaksi pengaruh `SET` sesi tidak dijamin bertahan antar-query; apakah itu berdampak bergantung pada `search_path` bawaan role di Supabase (dugaan: sudah memuat `public`; **belum diverifikasi**). Dibuktikan di VERIFY; config tidak diubah di task ini.
- Raw SQL di `app/` terbatas pada `whereRaw('1 = 0')`, `DB::raw('count(...)')`, dan `selectRaw` agregat (`DashboardService`, `HabitRecapService`).
- `lockForUpdate` dan `DB::transaction` dipakai (16 tempat); aman di pooler transaksi karena terjadi di dalam satu transaksi.
- Migration memakai `DB::statement` (CHECK constraint); dijalankan lewat `pgsql_direct`, bukan pooler, sehingga migration tidak terkena emulasi di rilis nyata. Hanya run suite lewat `RefreshDatabase` yang menjalankannya lewat koneksi aplikasi.

## 4. TRUSTED_PROXIES, EnforceHttps, dan rate limit per IP

Pembacaan env sudah ada (`bootstrap/app.php`, SECURITY-001): `TRUSTED_PROXIES` kosong = tidak ada proxy tepercaya; `*` = percayai proxy yang memanggil; daftar IP dipisah koma = hanya IP itu. Karena Vercel tidak punya IP tetap (Container Runtime tanpa Static IP), nilai yang dimaksud untuk Vercel adalah `*`. Nilai itu **harus** diisi di environment Vercel; tanpa itu dua hal rusak:

1. `EnforceHttps` melihat semua request sebagai http dan mengalihkan 308 tanpa henti.
2. `$request->ip()` (dipakai limiter `throttle:5,1` per IP dan `login-identifier` untuk request tanpa username) selalu alamat proxy, sehingga semua pengguna berbagi satu bucket dan saling terkena 429.

Test (`tests/Feature/Deploy/`): dengan `TRUSTED_PROXIES=*`, `X-Forwarded-Proto: https` tidak dialihkan dan membawa HSTS; http biasa dan `X-Forwarded-Proto: http` tetap 308; IP klien dibaca dari `X-Forwarded-For`; dua klien dengan `X-Forwarded-For` berbeda memakai bucket berbeda, tidak saling memblokir, dan dihitung independen saat bergantian. Dengan `TRUSTED_PROXIES` kosong: `X-Forwarded-Proto: https` dari klien diabaikan (tetap 308) dan `X-Forwarded-For` diabaikan (klien berbeda berbagi satu bucket). Test mengisi env sebelum aplikasi dibuat dan mem-flush status static `TrustProxies` sebelum dan sesudah, karena Laravel menyimpannya di properti static (bocor ke test berikutnya bila tidak).

**Risiko yang tidak bisa dibuktikan dari repo (untuk DEPLOY-PILOT-001):** dengan `*`, Laravel mempercayai alamat yang memanggil dan membaca klien dari `X-Forwarded-For`. Bila proxy Vercel **tidak** menimpa `X-Forwarded-For` yang dikirim klien, klien dapat memutar nilainya untuk melewati rate limit per IP. Pilot harus mengirim `X-Forwarded-For` palsu lewat URL Vercel dan memeriksa IP yang terbaca aplikasi. Perilaku header Vercel tidak diverifikasi di sini (tanpa akses jaringan).

## 5. Cache, sesi, antrean, dan timezone

- **`CACHE_STORE=database` wajib.** Blacklist JWT (logout, pencabutan token; `config/jwt.php` memakai `Providers\Storage\Illuminate`) dan limiter rate limit memakai cache. Driver `array`/`file`/`null` di produksi membuat token yang dicabut tetap berlaku dan counter limit tidak tercatat lintas request. Tabel `cache` dan `cache_locks` dibuat migration `0001_01_01_000001_create_cache_table`.
- **`SESSION_DRIVER` dan `QUEUE_CONNECTION` tidak dipakai.** Bacaan kode: tidak ada `ShouldQueue`, `dispatch`, `Queue::`, `Bus::`, atau `session(` di `app/` dan `routes/`; import xlsx sinkron. Tidak ada scheduler: `routes/console.php` hanya memuat perintah bawaan `inspire`, dan deployment tidak menjalankan `schedule:run`. Nilai bawaan Laravel di `.env.example` dibiarkan.
- **`APP_TIMEZONE` tetap `UTC`.** Contract v1.3 bagian 7 (menurut Task Board) menetapkan timestamp disimpan dan dikirim UTC; kode memakai `Asia/Jakarta` eksplisit untuk `habit_date` (`StudentHabitService`, `DashboardService`). Default `config/app.php` dan `.env.example` sudah `UTC` dan dikunci test. Saran mengubah ke `Asia/Jakarta` tidak diterapkan.

## 6. Test

| Kelas | Jumlah | Isi |
|---|---|---|
| `DatabaseConnectionConfigTest` | 14 | flag emulate dan disable default mati, flag disable hanya untuk `pgsql` dan independen dari emulate; terjemahan nilai env (`true/1/on/yes` hidup, `false/0/off/no` mati); `pgsql_direct` tanpa opsi emulate walau flag hidup; fallback ke `DB_*` dan pemakaian `DB_DIRECT_*`; `DB_URL` tidak diwarisi `pgsql_direct`; `sslmode` dan `sslrootcert`; query dan `migrate:status --database=pgsql_direct` ke koneksi nyata; `.env.example` aman, `CACHE_STORE=database`, `APP_TIMEZONE=UTC`; default timezone UTC |
| `TrustedProxiesEnabledTest` | 6 | lihat bagian 4 |
| `TrustedProxiesDisabledTest` | 3 | lihat bagian 4 |

Total 23 test baru (14 + 6 + 3, dihitung dari berkas). **Status eksekusi: belum dijalankan.** Sandbox penyusun tidak punya PHP, composer, PostgreSQL, atau akses jaringan; sintaks diperiksa hanya dengan menghitung kurung. Jalankan `php artisan test --filter="DatabaseConnectionConfigTest|TrustedProxies"` lalu suite penuh.

## 7. Temuan dan hal yang dicatat

1. **`.env.testing` lokal memakai `APP_TIMEZONE=Asia/Jakarta`** (berkas tidak di-git). Suite berjalan di timezone yang berbeda dari produksi (`UTC`). Tidak diubah di sini; pertimbangkan menyamakannya agar test mencerminkan produksi.
2. `phpunit.xml` memaksa `CACHE_STORE=array` dan `SESSION_DRIVER=array`: suite tidak menguji rate limit atau blacklist terhadap driver `database`. Pembuktian di DEPLOY-VERIFY-001 (Supabase).
3. `pgsql_direct` yang jatuh ke `DB_*` praktis di lokal tetapi berbahaya di lingkungan pooler bila `DB_DIRECT_*` lupa diisi (bagian 2). Pertimbangkan pemeriksaan eksplisit pada langkah rilis di DEPLOY-VERIFY-001.

## 8. Yang harus dilakukan di environment tim

1. `php -v` dan `where.exe php` dari terminal Laragon; pastikan PHP XAMPP 8.2 tidak terpakai (lihat DEPLOY-IMAGE-001).
2. Jalankan `php artisan test --filter="DatabaseConnectionConfigTest|TrustedProxies"` (23 test), lalu suite penuh **dua kali** dengan `DB_DISABLE_PREPARES` (bagian 3), dan laporkan keduanya.
3. Review silang Orang 1.
