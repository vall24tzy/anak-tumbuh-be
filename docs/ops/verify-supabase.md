# Rencana DEPLOY-VERIFY-001 — validasi lokal terhadap Supabase uji

**Status:** rencana langkah; **VERIFY belum dimulai**. Disusun Orang 2 dari Acceptance Criteria DEPLOY-VERIFY-001 di Task Board Deployment v1.2 dan keputusan D-26 sampai D-35 (disetujui pemilik, tertanda di bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` pada 10 Nov 2026). Dokumen ini tanpa rahasia: hanya nama variabel, tidak ada nilai, kunci, atau URL project. Fakta tentang Supabase (port pooler, perilaku Storage) berasal dari dokumen keputusan dan README Task Board, belum diverifikasi, dan harus dicek ulang saat VERIFY.

## 0. Gerbang (syarat sebelum VERIFY dimulai)
Semua harus terpenuhi dan tercatat:
1. Project Supabase uji ada, dengan **dua bucket** (privat untuk foto, publik untuk logo) dan **akses S3 aktif**.
2. Akun pemegang jelas (D-33: Orang 2, akun pribadi) dan tanda tangan pemilik di bagian 10 dokumen keputusan tercatat (10 Nov 2026).
3. Acceptance criteria butir (0): paket Supabase uji dipastikan mendukung Storage S3 dan pooler (D-32, paket Free). **Bila tidak, VERIFY tidak dimulai.**
4. DEPLOY-STORAGE-001, DEPLOY-RUNTIME-001, dan DEPLOY-LIMIT-001 lulus review silang (kode dan test).
5. Pemilik mengonfirmasi **2FA aktif** pada akun Vercel dan Supabase (syarat D-33). Per 10 Nov 2026 pemilik menyatakan 2FA aktif; bila akun pribadi dibuat baru, pastikan 2FA aktif juga di akun itu.

## 1. Persiapan lokal
1. Branch yang memuat ketiga task di atas; di terminal yang akan dipakai: `php -v` harus 8.3.x (D-26), `composer diagnose` memakai PHP yang sama (hindari XAMPP 8.2 pada PATH), `composer install`.
2. Catat versi klien `psql` dan `pg_dump` (pg_dump lama menolak mendump server yang lebih baru; relevan untuk uji dump di PRODPREP).
3. Siapkan variabel env **tanpa menulisnya ke berkas dalam repo**: set di jendela terminal (`set "NAMA=nilai"` di Cmder; tanda kutip melindungi karakter khusus seperti `&` dan `%` pada URL), atau berkas `.bat` **di luar repo** yang dihapus setelah selesai. Jangan membuat `.env.verify` di dalam repo: `.gitignore` tidak mengecualikannya.

## 2. Variabel env (nama saja)

| Variabel | Sumber | Catatan |
|---|---|---|
| `DB_URL` | Supabase uji, **pooler mode transaksi** (port 6543) | rahasia; dipakai aplikasi |
| `DB_DIRECT_URL` | Supabase uji, **pooler mode session** (port 5432, D-27) | rahasia; dipakai `pgsql_direct` untuk migrate dan seed |
| `DB_SSLMODE`, `DB_SSLROOTCERT` | ketentuan Supabase | `require` atau `verify-full` |
| `DB_DISABLE_PREPARES` | nilai tetap | `true` untuk aplikasi lewat pooler transaksi |
| `DB_EMULATE_PREPARES` | nilai tetap | `false` (terbukti merusak boolean) |
| `DB_CONNECTION`, `CACHE_STORE` | nilai tetap | `pgsql`, `database` |
| `STORAGE_PRIVATE_DRIVER`, `STORAGE_PUBLIC_DRIVER` | nilai tetap | `s3` |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | Supabase uji, kunci akses S3 | rahasia |
| `AWS_DEFAULT_REGION`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | Supabase uji | endpoint S3 Supabase; path-style `true` |
| `AWS_PRIVATE_BUCKET`, `AWS_PUBLIC_BUCKET`, `AWS_PUBLIC_URL` | Supabase uji | dua bucket dan URL dasar objek publik |
| `APP_KEY`, `JWT_SECRET`, `SEED_SUPER_ADMIN_PASSWORD` | dibuat untuk VERIFY | rahasia; berbeda dari dev; tidak dikirim lewat chat |
| `APP_URL`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE` | nilai tetap | URL lokal, `local`, `true`/`false` sesuai kebutuhan, `UTC` |
| `FORCE_HTTPS`, `TRUSTED_PROXIES` | nilai tetap | lokal: `false` dan kosong (proxy tidak ada) |

Daftar lengkap dan arti tiap variabel: `docs/ops/vercel-deploy.md` bagian 4.

## 3. Urutan run
1. **Migrate dan seed lewat `pgsql_direct`** (pooler session 5432) pada database Supabase uji yang kosong: `php artisan migrate --database=pgsql_direct --force`, lalu `php artisan db:seed --database=pgsql_direct --force`. Catat hasil dan jumlah tabel; periksa tabel `cache` ada (blacklist JWT dan rate limit memakainya). [butir 1]
2. **Aplikasi lokal lewat pooler transaksi (port 6543) dengan `DB_DISABLE_PREPARES=true`** (`php artisan serve`). Periksa endpoint infrastruktur dan login Super Admin. [butir 2]
3. **Uji S3** (bucket privat dan publik): unggah foto siswa, unduh foto lewat `GET /students/{id}/photo` dengan Authorization, ganti foto, unggah logo sekolah, dan buka URL logo publik. Pastikan tidak ada path atau URL bucket di response mana pun. **Catat apa adanya** kode dan pesan error S3 bila unggah gagal: adapter Flysystem mengirim `x-amz-acl: private` pada setiap unggah, sedangkan D-28 menyatakan Supabase tidak mendukung ACL; ini hipotesis yang harus dibuktikan di sini, bukan fakta. [butir 4]
4. **Import 200 baris** (siswa dan guru; file di bawah 4 MB, D-29) dan uji file di atas 4 MB (harus 422 dengan kode yang sudah ada). [butir 4]
5. **Postman per folder dengan newman**, dengan jeda karena rate limit login (5 per menit per IP): urutan folder 0, 1, lalu folder lain; `--delay-request 13000` seperti pola run Orang 2, atau per folder dengan jeda satu menit dan `php artisan cache:clear` di antaranya. Simpan JSON mentah **di luar repo** (memuat token dan password); hasil ringkas (nama request, kode HTTP, hasil assertion) disimpan di `docs/testing/DEPLOY-VERIFY-001-runner-result.md`. Skrip ringkasan Orang 2 saat ini hanya membaca folder `INTEG-O2` dan perlu disesuaikan untuk folder lain. [butir 3]
6. **Catat koneksi dan error pooler:** jumlah koneksi database (dasbor Supabase atau `pg_stat_activity`) dan error yang mengandung `prepared statement`, `08P01`, atau pesan pooler lain. [butir 5]
7. **Suite penuh** `php artisan test` di PHP 8.3 (database lokal, bukan Supabase); catat `php -v`. [butir 6]
8. **Tulis hasil:** `docs/testing/DEPLOY-VERIFY-001-runner-result.md` dan `docs/team-log/DEPLOY-VERIFY-001.md` yang jujur (apa yang dijalankan dan tidak). [butir 7]

## 4. Bukti yang dikumpulkan

| Butir AC | Bukti | Status |
|---|---|---|
| (0) paket mendukung S3 dan pooler | tangkapan atau catatan ketentuan paket | belum dimulai |
| (1) migrate dan seed | keluaran perintah (tanpa rahasia) | belum dimulai |
| (2) aplikasi lewat pooler | konfigurasi dan hasil login | belum dimulai |
| (3) hasil newman per folder | `DEPLOY-VERIFY-001-runner-result.md` | belum dimulai |
| (4) foto, logo, import | hasil langkah 3 dan 4 | belum dimulai |
| (5) koneksi dan error pooler | angka dan pesan error | belum dimulai |
| (6) suite penuh di PHP 8.3 | baris `Tests:` | belum dimulai |
| (7) team-log | berkas | belum dimulai |

## 5. Risiko dan hal terbuka
- `x-amz-acl`: lihat langkah 3; bila ditolak, header dibuang di sisi klien AWS SDK (perubahan di DEPLOY-STORAGE-001 atau task lanjutan).
- Paket Free: tanpa backup otomatis dan project terjeda setelah seminggu tidak aktif (D-32); uji dump manual di PRODPREP dengan `pg_dump` yang versinya sesuai.
- Batas koneksi pooler: bila terlampaui, jalan mundur D-27 adalah pooler session dengan batas koneksi.
- Vercel belum disentuh di VERIFY (hanya lokal); `vercel.json` dan port belum diuji di Vercel.
- Jangan menjalankan VERIFY terhadap database production; jangan mengirim kunci atau password lewat chat; jangan menulis rahasia ke repo.
