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
