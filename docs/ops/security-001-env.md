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
`Strict-Transport-Security`; `curl -si -X OPTIONS https://<domain>/api/v1/auth/login/staff -H 'Origin: https://evil.example' -H 'Access-Control-Request-Method: POST'`
→ tanpa `Access-Control-Allow-Origin`.
