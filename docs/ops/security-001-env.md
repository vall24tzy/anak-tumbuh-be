# SECURITY-001 (tahap awal) — konfigurasi lingkungan produksi

Berlaku untuk `.env` produksi. Semua nilai diisi saat deploy; **jangan commit**. Pemeriksaan otomatis: `tests/Feature/Security/`.

| Variabel | Nilai produksi | Catatan |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Pesan exception mentah hanya keluar bila `true` (respons 500 → "Terjadi kesalahan pada server"). |
| `APP_KEY` | dibuat baru: `php artisan key:generate --show` | **Beda dari dev.** Dipakai juga untuk HMAC payload QR dan sidik jari password: mengganti APP_KEY mencabut semua token JWT aktif dan QR yang sudah dicetak. |
| `JWT_SECRET` | dibuat baru: `php artisan jwt:secret --show` | **Beda dari dev.** Mengganti nilainya mencabut semua token aktif. |
| `CORS_ALLOWED_ORIGINS` | domain FE persis, koma-pisah, `https://...` | Kosong di production = tidak ada origin lintas-domain. Wildcard/`http://`/localhost dibuang. |
| `FORCE_HTTPS` | `true` | http → https (308) + HSTS 180 hari. `/up` dikecualikan. |
| `TRUSTED_PROXIES` | IP proxy/CDN, koma-pisah (atau `*`) | **Wajib bila di belakang reverse proxy/CDN**, bila tidak `FORCE_HTTPS=true` mengalihkan tanpa henti. Proxy harus mengirim `X-Forwarded-Proto`. |
| `APP_URL` | `https://<domain-api>` | Host di URL redirect HTTPS diambil dari sini, bukan dari header Host klien. |
| `DB_PASSWORD` | kuat, unik | Bukan `postgres`. |
| `SEED_SUPER_ADMIN_PASSWORD` | lihat dokumen provisioning (F-30) | Di luar patch ini. |

Cek cepat setelah deploy: `curl -sI http://<domain>/api/v1/auth/me` → `308` ke `https://`; `curl -sI https://<domain>/api/v1/auth/me` →
`Strict-Transport-Security`.

Cek CORS setelah deploy (ganti `<domain>` dengan domain API dan `<domain-fe>` dengan domain FE; `-i` menampilkan header respons):

1. Origin tidak terdaftar (negatif):
   `curl -si -X OPTIONS https://<domain>/api/v1/auth/login/staff -H 'Origin: https://evil.example' -H 'Access-Control-Request-Method: POST'`
   - **Lulus** bila `Access-Control-Allow-Origin` tidak ada sama sekali, **atau** nilainya hanya salah satu origin di `CORS_ALLOWED_ORIGINS`.
   - **Gagal** bila nilainya `https://evil.example` atau `*`.
2. Origin FE yang benar (positif):
   `curl -si -X OPTIONS https://<domain>/api/v1/auth/login/staff -H 'Origin: https://<domain-fe>' -H 'Access-Control-Request-Method: POST'`
   - **Lulus** bila `Access-Control-Allow-Origin` berisi persis `https://<domain-fe>`. Bila tidak, browser FE akan ditolak.

Kenapa header bisa tetap muncul pada cek 1: dengan **satu** origin terdaftar, library CORS mengirim origin terdaftar itu apa adanya, bukan origin pemanggil; browser menolak karena nilai header tidak cocok dengan origin pemanggil, jadi keamanannya tetap terjaga. Dengan **dua atau lebih** origin terdaftar, header baru dikirim hanya bila origin pemanggil cocok (lihat komentar di `tests/Feature/Security/CorsPolicyTest.php`).

## Logging dan monitoring dasar

Keputusan pemilik (11 Okt 2026): rotasi harian, retensi 14 hari, level `warning`; monitoring dasar = uptime check eksternal ke `/up`.

- **Konfigurasi (`.env`):** `LOG_CHANNEL=stack`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning`. `config/logging.php` (channel `daily`)
  membaca `LOG_DAILY_DAYS` (default 14) dan `LOG_LEVEL`; tidak ada perubahan struktur config.
- **Lokasi:** `storage/logs/` (satu berkas per hari, bernama `laravel-YYYY-MM-DD.log`). Direktori harus dapat ditulis oleh user web server.
- **Isi pada level `warning`:** warning ke atas, antara lain exception tak tertangani yang dilaporkan Laravel, `audit_logs write failed (non-blocking)`
  dan `import file unparseable`. Pesan level `info` tidak tertulis.
- **Yang dicatat kode (hasil pemindaian `app/`):** hanya dua panggilan `Log::warning` (`AuditLogService`, `ImportService`); keduanya mencatat nama/pesan
  exception, bukan password, `new_password`, token, atau isi request. Catatan sisa risiko: pesan exception dari pembaca Excel dapat memuat potongan
  isi berkas unggahan; pesan kegagalan `audit_logs` dapat memuat id pelaku/target dan nama aksi.
- **Perlu dicek di server (belum diverifikasi):** `zend.exception_ignore_args=On` di `php.ini` produksi. Bila `Off`, jejak exception pada pemanggilan
  yang menerima password sebagai argumen dapat memuat potongan argumen itu di log.
- **Aksi sensitif yang berhasil** (reset/ganti password, regenerate QR) dicatat di tabel `audit_logs`, bukan di berkas log pada level `warning`.
  Keputusan pemilik (11 Okt 2026): **memadai**, karena Acceptance Criteria SECURITY-001 menyebut `audit_logs` sebagai tempat pencatatan `password_reset`
  dan `qr_regenerate`; tidak perlu log file tambahan. Catatan: kolom "Prompt Siap Pakai" butir 9 pada Task Board v23 juga menyebut log file standar untuk
  aksi sensitif; keputusan ini menafsirkan Acceptance Criteria sebagai yang berlaku.
- **Monitoring dasar:** pasang uptime check eksternal ke `GET https://<domain>/up`; sehat = HTTP 200. `/up` hanya menandakan aplikasi dapat dimuat,
  bukan bahwa database sehat. `/up` dikecualikan dari paksa-HTTPS. Cek manual: `curl -si https://<domain>/up`.
  Layanan, interval, dan tujuan notifikasi dipilih saat DEPLOYMENT-001 (butuh domain produksi); rekomendasi pemilik: layanan gratis apa pun, interval 5 menit,
  notifikasi ke email pemilik. Ini butir checklist DEPLOYMENT-001, bukan penghalang penutupan SECURITY-001.

## Status advisory CVE-2026-48019 (handoff)

- **Advisory:** CVE-2026-48019 / GHSA-5vg9-5847-vvmq, "CRLF injection in default email rule" (severity High, CVSS 8.9). CRLF injection pada aturan validasi
  `email` Laravel yang, bersama Symfony Mailer/Mime, dapat memengaruhi email keluar pada aplikasi yang mengirim email ke alamat dari pengguna.
  Diperbaiki di Laravel **12.60.0** dan **13.10.0**; versi terdampak tertulis `< 12.60.0` dan `<= 13.9.0`.
- **Versi terkunci proyek:** `laravel/framework` **11.56.1** (dibaca dari `composer.lock`). Tidak ada rilis 11.x yang menambalnya.
- **Analisis paparan (keputusan pemilik: dicatat tidak terdampak):** tidak ada pengiriman email dan tidak ada aturan validasi `email`. Dasarnya:
  pemindaian `app/` dan `routes/` tidak menemukan `Mail::`, `Mailable`, `Notification`, `->notify(`, maupun aturan `email`; `MAIL_MAILER=log`;
  `User` hanya memakai trait `Notifiable` (tidak mengirim apa pun tanpa `->notify(`). Pemindaian di `config/`, `database/`, dan `resources/` tidak dicakup.
- **Penjaga:** `tests/Feature/Security/MailExposureGuardTest.php` gagal bila kode di `app/` atau `routes/` mulai memakai email, dengan pesan
  "Evaluasi ulang CVE-2026-48019 sebelum memakai email".
- **Dukungan Laravel 11:** menurut tabel dukungan resmi di laravel.com/docs/releases (diverifikasi pemilik, 11 Okt 2026), perbaikan keamanan
  Laravel 11 berakhir pada 12 Maret 2026, sehingga perbaikan 11.x tidak akan terbit.
- **Rekomendasi:** jadwalkan upgrade ke Laravel 12 sebelum DEPLOYMENT-001. Keputusan pemilik menyusul. Versi minimum yang memperbaiki seluruh entri
  `laravel/framework` pada audit di bawah adalah **12.69.0** (berdasarkan rentang versi pada keluaran audit).
- **Hasil `composer audit` (dijalankan pemilik, 11 Okt 2026):** `Found 6 security vulnerability advisories affecting 2 packages`.

  | # | Paket | Advisory | Judul | Versi terdampak (menurut audit) | Dilaporkan |
  |---|---|---|---|---|---|
  | 1 | laravel/framework | CVE-2026-102279 / GHSA-jh5r-qr3c-85q8 | XSS in Debug Page Information | `<12.69.0`, `>=13.0.0,<13.30.0` | 2026-09-29 |
  | 2 | laravel/framework | GHSA-crmm-hgp2-wgrp (tanpa CVE) | Temporary Signed URL Path Confusion | `<12.61.1`, `>=13.0.0,<13.12.0` | 2026-06-17 |
  | 3 | laravel/framework | GHSA-5vg9-5847-vvmq (tanpa CVE) | CRLF injection in default email rule | `<12.60.0`, `>=13.0.0,<=13.9.0` | 2026-06-17 |
  | 4 | laravel/framework | CVE-2026-48019 / GHSA-5vg9-5847-vvmq | CRLF injection in default email rule | `>=9.0.0,<10.0.0`, `>=10.0.0,<11.0.0`, `>=11.0.0,<12.0.0`, `>=12.0.0,<12.60.0`, `>=13.0.0,<13.10.0` | 2026-05-19 |
  | 5 | league/commonmark | GHSA-97jj-33gv-5xf9 (tanpa CVE) | DisallowedRawHtml bypassed when a disallowed tag name ends the raw-HTML literal | `>=1.3.0,<=2.10.1` | 2026-09-30 |
  | 6 | league/commonmark | GHSA-3q6v-r5mr-hxv8 (tanpa CVE) | Quadratic-time denial of service in the GitHub Flavored Markdown Table extension block-start scan | `>=2.0.0,<=2.10.1` | 2026-09-30 |

  Baris 3 dan 4 adalah advisory yang sama (GHSA-5vg9-5847-vvmq) yang tercatat dua kali, jadi enam entri = lima isu berbeda. Baris 4 menandai
  cabang 11.x (`>=11.0.0,<12.0.0`) sebagai terdampak. Versi terkunci: `laravel/framework` 11.56.1 dan `league/commonmark` 2.10.0 (paket transitif),
  dibaca dari `composer.lock`.
- **Analisis paparan (pencarian statis di `app/`, `routes/`, `bootstrap/`, `config/`; belum dijalankan; isi advisory #2, #5, dan #6 tidak dibaca penyusun, hanya judulnya):**
  - **#1 CVE-2026-102279:** menurut deskripsi CVE di sumber yang dibaca penyusun (Debian security tracker, OpenCVE), kerentanan terjadi pada halaman debug saat
    `APP_DEBUG=true`; mitigasi sementara yang disebut sumber adalah `APP_DEBUG=false`. Dengan `APP_DEBUG=false` di produksi (butir checklist di bawah), halaman itu tidak tampil.
    Lingkungan dev dengan debug aktif tetap terpapar. Halaman advisory GitHub tidak dapat dibuka penyusun (404), jadi rincian severity belum diverifikasi.
  - **#2 Temporary Signed URL:** tidak ditemukan `temporarySignedRoute`, `signedRoute`, `hasValidSignature`, atau middleware `signed`.
  - **#3 dan #4 CRLF email:** lihat bagian di atas (tidak ada pengiriman email atau aturan `email`; dijaga `MailExposureGuardTest`).
  - **#5 dan #6 league/commonmark:** tidak ditemukan pemakaian `Str::markdown`, `CommonMark`, atau markdown di `app/`, `routes/`, `bootstrap/` (satu-satunya
    kemunculan kata markdown adalah daftar service provider framework di `bootstrap/cache/services.php`).
  - Kesimpulan penyusun: tidak ditemukan jalur kode aplikasi yang memakai fitur terdampak (kecuali #1 yang bergantung pada `APP_DEBUG`). Ini hasil pembacaan statis, bukan jaminan.
- **Keputusan pemilik (12 Okt 2026), Opsi B:** `laravel/framework` tetap **11.56.1**, tanpa upgrade sebelum go-live. Risiko advisory pada tabel di atas
  (6 entri = 5 isu) **diterima secara tertulis oleh pemilik**, dengan syarat di bagian "Penerimaan risiko Opsi B" di bawah. Upgrade ke 12.69.0+ tetap
  direkomendasikan pasca go-live. Untuk #5 dan #6, versi perbaikan tidak tertulis di keluaran audit (cek halaman advisory); memperbarui paket transitif
  `league/commonmark` adalah opsi terpisah dari upgrade framework dan belum diverifikasi.

## Penerimaan risiko Opsi B (12 Okt 2026)

Keputusan pemilik: tetap di Laravel 11.56.1 sampai go-live; risiko diterima secara tertulis dengan **lima syarat**:

1. **`APP_DEBUG=false` di produksi, WAJIB dan tidak boleh ditawar.** Ini menutup paparan #1 (CVE-2026-102279), yang hanya terjadi pada halaman debug.
2. **Penjaga test diperluas:** `tests/Feature/Security/MailExposureGuardTest.php` (email; advisory #3 dan #4) dan
   `tests/Feature/Security/FrameworkAdvisoryGuardTest.php` (signed URL; advisory #2, dan markdown; advisory #5 dan #6). Test gagal bila kode di `app/` atau `routes/`
   mulai memakai fitur terkait, dengan pesan "Evaluasi ulang penerimaan risiko Laravel 11 sebelum memakai fitur ini" beserta ID advisory yang dibaca dari tabel di atas.
3. **`composer audit` dijalankan saat deploy** dan hasilnya dicatat (advisory yang diketahui dan diterima).
4. **Titik evaluasi ulang risiko Laravel 11 ditetapkan (pengganti tanggal upgrade, keputusan pemilik 13 Okt 2026)** (baris di bawah).
5. **Keputusan ini dicatat sebagai penerimaan risiko pemilik** (bagian ini).

**Pemicu evaluasi ulang penerimaan risiko:**

- advisory baru (hasil `composer audit`) yang menyentuh fitur yang dipakai aplikasi;
- fitur baru yang memakai email, signed URL, atau markdown (test penjaga gagal);
- perubahan hosting atau topologi (misalnya pindah server, proxy/CDN).

**Batas penerimaan risiko:** advisory tidak hilang karena diterima; Laravel 11 tidak akan menerima perbaikan (lihat "Dukungan Laravel 11" di atas). Penjaga test hanya
memindai `app/` dan `routes/` dan hanya mengenali pola yang terdaftar; ia bukan pengganti review kode.

**Tanggal upgrade pasca go-live: belum ditentukan (keputusan pemilik 13 Okt 2026); pemilik belum memutuskan akan upgrade selama Laravel 11 masih berfungsi.
Penerimaan risiko ini dievaluasi ulang, dan keputusan upgrade diambil saat salah satu pemicu terjadi:**
- `php artisan test` gagal di `FrameworkAdvisoryGuardTest` atau `MailExposureGuardTest` (artinya fitur terpapar mulai dipakai).
- `composer audit` pada deploy berikutnya menunjukkan advisory baru yang menyentuh fitur yang dipakai aplikasi.
- Versi PHP di server tidak lagi mendapat dukungan keamanan (PHP 8.2: 31 Des 2026) atau hosting mensyaratkan versi lebih baru.
- Aplikasi akan menambah fitur email, signed URL, atau markdown.
- Evaluasi berkala: setiap 3 bulan sekali, jalankan `composer audit` dan catat hasilnya.

## Checklist verifikasi produksi (dilakukan di DEPLOYMENT-001)

SECURITY-001 ditutup sebagai selesai di kode; nilai produksi diverifikasi di DEPLOYMENT-001. Kotak di bawah sengaja kosong; centang hanya setelah dicek di server.

- [ ] **WAJIB:** `APP_DEBUG=false` (respons error tidak menampilkan stack trace/SQL/path); syarat penerimaan risiko Laravel 11, tidak boleh ditawar
- [ ] `composer audit` dijalankan, hasil dicatat (advisory yang diketahui dan diterima)
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` dan `JWT_SECRET` berbeda dari dev (dibuat baru, bukan disalin)
- [ ] `SEED_SUPER_ADMIN_PASSWORD` diisi (dan password Super Admin lama dirotasi di setiap lingkungan yang pernah di-seed)
- [ ] `CORS_ALLOWED_ORIGINS` berisi domain FE yang benar (cek negatif dan positif di bagian "Cek cepat setelah deploy")
- [ ] `FORCE_HTTPS=true` dan `TRUSTED_PROXIES` sesuai topologi proxy/CDN (http dialihkan 308 tanpa loop; header HSTS ada)
- [ ] Throttle aktif: enam login gagal beruntun ke `/api/v1/auth/login/staff` menghasilkan 429 `rate_limit_exceeded` pada permintaan ke-6; reset-password dan change-password dibatasi 10/menit per user
- [ ] Log harian aktif: berkas `storage/logs/laravel-YYYY-MM-DD.log` terbentuk, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning`; `zend.exception_ignore_args=On`
- [ ] Uptime check eksternal ke `/up` terpasang (rekomendasi: interval 5 menit, notifikasi ke email pemilik) dan melaporkan 200
