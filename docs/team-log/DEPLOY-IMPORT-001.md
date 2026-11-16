# DEPLOY-IMPORT-001 — Log

**Status:** kode, test, addendum selesai; test lokal hijau (681 passed, 4.249 assertion); belum DONE (lihat "Belum selesai").
**PIC:** Orang 1 · **Tanggal:** 16 Nov 2026
**Dasar:** Task Board Deployment v1.10; D-36 (Opsi 1, Pemilik 16 Nov 2026); `docs/addendum/DEPLOY-IMPORT-001.md`.
**Dependency:** DEPLOY-PILOT-001 (DONE), D-36 (DIPUTUSKAN). Cabang: `deploy/vercel-import` dari `deploy/vercel-pilot` @ 28f4800.

## Yang dikerjakan
1. `RandomSecretHasher` (baru): cost = `min(4, round konfigurasi)`, terpisah dari `InitialPasswordHasher`.
2. `StudentService` (placeholder token QR) dan `QrTokenService::regenerate` (token baru) memakai helper itu.
3. `tests/Feature/Student/RandomSecretCostTest.php`: cost helper, create, import 150 dan 200 baris, regenerate + login, hash lama tetap valid.
4. `docs/addendum/DEPLOY-IMPORT-001.md` dan log ini.
5. Tidak ada perubahan endpoint, respons, kode error, `MAX_ROWS`, `max_execution_time`, atau skema DB.

## Yang diperiksa dan yang tidak
- Diperiksa dengan membaca kode: seluruh `Hash::make`/`Hash::check` di `app/` (dua `Hash::make` yang diganti; `Hash::check` di `QrTokenService::verifyAndResolveStudent` dan `AuthService` tidak berubah), `QrToken` tanpa cast `hashed`, `MAX_ROWS` memakai `>` (200 baris lolos), fixture dan pola test `InitialPasswordCostTest`/`QrTokenTest`.
- Test dijalankan Orang 1 di lokal (16 Nov 2026): `RandomSecretCostTest` 7 passed (379 assertion); suite penuh 681 passed (4.249 assertion), naik 7 dari 674 di 47a4d44.
- **Tidak diukur:** durasi import di Vercel; biaya hash per baris belum diukur terpisah di Vercel (perkiraan board 0,2 sampai 0,3 detik).

## Belum selesai (syarat DONE)
1. ~~Test lokal hijau~~ SELESAI (lihat di atas).
2. Review silang Orang 2.
3. Orang 2 deploy Preview dari cabang ini dan mengukur ulang TEST-001 tanpa `BCRYPT_ROUNDS=4` (import 150 dan 200 baris di bawah batas durasi yang Pemilik tetapkan).

## Temuan
1. Pemutusan koneksi di kisaran 30 detik pada run 3 PILOT masih belum dijelaskan; patch ini mengurangi biayanya, tidak menjelaskan penyebabnya. Bila 150 sampai 200 baris masih lambat setelah patch, D-36 opsi (b) (turunkan `MAX_ROWS`) tetap tersedia.
2. Token QR lama (round 12) tidak dimigrasi dan tetap valid; hanya baris baru yang round 4.
