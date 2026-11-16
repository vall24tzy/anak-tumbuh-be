# DEPLOY-IMPORT-001 — Biaya hash nilai acak di jalur import dan regenerate QR diturunkan

**Tanggal:** 16 Nov 2026 · **PIC:** Orang 1 · **Sifat perubahan:** satu helper baru dan dua pemanggilan `Hash::make` diganti; tidak ada endpoint, field, kode error, bentuk respons, atau skema DB baru.
**Dasar:** Task Board Deployment Vercel dan Supabase v1.10 (DEPLOY-IMPORT-001), D-36 (Opsi 1, diputuskan Pemilik 16 Nov 2026), temuan T-06 di `docs/testing/DEPLOY-PILOT-001-result.md`.

## 1. Masalah (bukti dari PILOT, T-06)

Import siswa 150 baris gagal di Vercel dengan bcrypt bawaan (round 12): 500 di batas 30 detik (`BcryptHasher.php:49`), run berikutnya koneksi terputus walau `max_execution_time` 120. Dengan `BCRYPT_ROUNDS=4` import selesai 5,95 detik. Password awal sudah round 4 (`InitialPasswordHasher`, F-55); sisa biaya ada di hash placeholder token QR per siswa (`StudentService.php`, `Hash::make((string) Str::uuid())`). Jalur regenerate (`QrTokenService.php`) memakai pola sama.

## 2. Keputusan yang dipakai

D-36 Opsi 1: nilai acak berentropi tinggi di-hash dengan round rendah `min(4, config('hashing.bcrypt.rounds'))` lewat **helper terpisah** (bukan `InitialPasswordHasher`, yang docblock-nya khusus password awal). Opsi (b) menurunkan `MAX_ROWS` tidak dipakai; (c) menaikkan `max_execution_time` bukan solusi tunggal.

## 3. Perubahan

| Berkas | Perubahan |
|---|---|
| `app/Services/Student/RandomSecretHasher.php` | **Baru.** `ROUNDS = 4`; `make()` memanggil `Hash::make` dengan `rounds = min(4, round konfigurasi)`. |
| `app/Services/Student/StudentService.php` | Placeholder token QR saat create/import memakai `RandomSecretHasher::make(...)`; `use ...\Hash` yang tidak terpakai dihapus. |
| `app/Services/Student/QrTokenService.php` | Token hasil regenerate memakai `RandomSecretHasher::make(...)`. `Hash::check` di verifikasi login tidak berubah. |
| `tests/Feature/Student/RandomSecretCostTest.php` | **Baru.** 7 test (lihat bagian 5). |

## 4. Mengapa aman

- Nilai yang di-hash bukan password yang bisa ditebak: UUID v4 (122 bit acak) dan `Str::random(64)`. Bcrypt round penuh tidak menambah keamanan nyata untuk nilai seperti ini.
- Login QR tetap dilindungi HMAC-SHA256 dengan `APP_KEY` sebelum hash dicek; payload yang dipalsukan ditolak sebelum menyentuh `Hash::check`.
- **Token lama tetap valid tanpa migrasi:** `Hash::check` membaca cost dari string hash itu sendiri, dan `QrToken` tidak memakai cast `hashed`, jadi tidak ada verifikasi cost terhadap round konfigurasi. Baris round 12 yang sudah ada tetap lolos; baris baru round 4.
- `min()` menjamin cost tidak melebihi round konfigurasi (mis. `phpunit.xml`: 4).
- Round global tidak diubah. Password (awal maupun pilihan actor) tidak tersentuh: `grep Hash::make app/` hanya menyisakan `InitialPasswordHasher` dan `RandomSecretHasher`.

## 5. Test

`RandomSecretCostTest`, 7 test (setUp menaikkan round konfigurasi ke 10 supaya cost `$2y$04$` vs `$2y$10$` bisa dibedakan, pola `InitialPasswordCostTest`):

1. Helper memakai cost 4 walau round konfigurasi 10; hash valid.
2. Helper tidak melebihi round konfigurasi (konfigurasi 4 menghasilkan cost 4).
3. Create Student menyimpan placeholder token cost 4.
4. Import 150 baris: semua berhasil, 150 token cost 4.
5. Import 200 baris (`MAX_ROWS`): semua berhasil, 200 token cost 4.
6. Regenerate menyimpan token cost 4 dan login QR dengan payload baru 200.
7. Login QR dengan hash lama (cost 10) tetap 200; token salah 401.

## 6. Tidak berubah

Endpoint, bentuk respons, kode error, `MAX_ROWS` (200), `max_execution_time`, round global, `InitialPasswordHasher`, skema DB.

## 7. Status eksekusi

Dijalankan Orang 1 di lokal (16 Nov 2026, cabang `deploy/vercel-import` dari `deploy/vercel-pilot` @ 28f4800):

- `php artisan test --filter=RandomSecretCostTest`: **7 passed (379 assertions)**, 13,89 detik.
- `php artisan test` penuh: **681 passed (4.249 assertions)**, 161,29 detik. Naik 7 dari acuan 674 passed di 47a4d44; tidak ada kegagalan dan tidak ada yang di-skip.

Belum dibuktikan (syarat penutupan D-36): TEST-001 hijau di Preview **tanpa** `BCRYPT_ROUNDS=4`, import 150 dan 200 baris diukur ulang di Vercel (Orang 2). Durasi nyata di Vercel tidak bisa dibuktikan oleh test lokal.
