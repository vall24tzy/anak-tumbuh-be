# MERGE-001 — Log

**Status:** Sesi audit, dokumen, dan Postman SELESAI disusun (sandbox penyusun tidak punya PHP/PostgreSQL/Postman). **Eksekusi dilaporkan Orang 1 pada 9 Okt 2026:** PHPUnit 443 passed / 2216 assertions; Postman Folder 1-6 dilaporkan 0 Failed. Ini laporan, BUKAN bukti tersimpan: tidak ada file hasil Runner di repo. Koreksi saat run: asersi 3.09 dan 3.12 semula mengharapkan 7 entry, diubah menjadi 6 karena `GET /habits/entries` tidak memuat Bermasyarakat (addendum Habit-GET).
**PIC:** Bersama (sesi ini: sisi Orang 1) · **Basis penyusunan:** dibuat di atas `6974b29` (8 Okt 2026) — catatan historis, HEAD berubah tiap commit; angka yang bergeser diperbarui per butir (bertanda "diperbarui 9 Okt 2026")

## Ringkasan

- **Langkah 0 (dependency):** TEST-001 (124 request; 127 setelah PAGINATION-C02) dan TEST-002 (218 request; baseline §15 menulis 217 = keadaan Stage 6, +1 dari 14.02 di §16) ada di repo; hasil Runner TEST-001 dilaporkan Orang 1 (Folder 0-8 PASS) tetapi tidak tersimpan di repo. LEVEL-RANKING-001 dan AUTHORIZATION-MATRIX terpenuhi. Audit Stage 0–6: seluruh F-01..F-53 dan F-55 FIXED — VERIFIED (diperbarui 9 Okt 2026; baseline tidak memuat F-54, penomoran melompat F-53 → F-55), label CLOSED formal belum ditulis (kecuali F-14).
- **Tugas A (endpoint) — dicek ke kode:** 69 route → 64 ada di API Contract, 3 di errata/addendum, 2 tidak ada di dokumen manapun (`GET /api/test`, `GET /teachers/import/template`; keduanya didokumentasikan 10 Okt 2026 di `API-Contract-v1.2-Addendum-Route-Tambahan.md`); 64/64 endpoint kontrak punya route; 0 pelanggaran role vs Matrix v5.2.
- **Tugas B (migrasi + seeder) — dicek ke kode:** urutan FK aman; struktur akhir cocok DB Design v1.5 + errata (termasuk `uq_tca_one_active_per_rombel_year`); seeder 2 Super Admin (password dari env), 7 habit, 28 indikator, 101 level; tidak ada jejak Grade Advancement / mass promotion / import School / import Headmaster. Selisih: tabel `import_batches` tidak ada di DB Design.
- **Tugas C (register SoT):** 15 butir; 12 tertutup oleh errata/addendum/keputusan/perbaikan teks, 2 terbuka (C-02 menunggu run test; C-07 menunggu konfirmasi pemilik), 1 informasi (C-15). Diperbarui: C-06 dan C-11 ditutup Orang 2 (9 Okt 2026); C-01 ditutup 10 Okt 2026.
- **Tugas D (Postman e2e):** collection `MERGE-001-Postman-Collection.json`, 63 request, 6 folder, tanpa secret. Dijalankan 9 Okt 2026 (lihat Status).

## File

- `docs/testing/MERGE-001-sot-register.md` — hasil Tugas A/B/C, checklist DoD, perintah run.
- `docs/testing/MERGE-001-Postman-Collection.json` — collection e2e.
- `docs/team-log/MERGE-001.md` — log ini.
- `docs/addendum/SoT-Amendment-Ranking-Name.md` — catatan amandemen nama di ranking (C-01).
- `docs/addendum/API-Contract-v1.2-Addendum-Route-Tambahan.md` — route di luar daftar endpoint (A-1, A-2, A-3).

## Temuan (tidak diperbaiki; rinci di register)

**Milik Orang 1:** C-02 `GET /students` dan `GET /student-class-assignments` tanpa pagination (cabang Super Admin tak berbatas) — keputusan Opsi A diimplementasikan di PAGINATION-C02 (10 Okt 2026), menunggu run test; teks usang `docs/team-log/TEST-001.md`, `docs/team-log/IMPORT-001.md` dan `docs/db-gap-analysis.md` NOTE-09 (import)/NOTE-10 **sudah dikoreksi 9 Okt 2026**.
**Milik Orang 2 (sudah ditutup 9 Okt 2026):** C-11 (definisi `import_batches` di errata DB), C-06 (README-TEST-002 218 request/15 folder; baseline §15), A-1/A-2/A-3 (errata API Lampiran A; duplikat dengan addendum Route-Tambahan, Lampiran A menang bila berbeda), A-5 (rate limit reset/change-password, kode SECURITY-001). Sisa untuk Orang 2: teks errata §7 "belum dipaginasi" diperbarui setelah test pagination hijau, lalu uji otorisasi lintas sekolah/rombel untuk dua endpoint itu.
**Keputusan tim 10 Okt 2026 (sudah diambil):** C-01 ditutup lewat `SoT-Amendment-Ranking-Name.md` (file baseline Dokumen v4.0/Matrix menunggu re-issue); A-3 diakui sebagai infrastruktur; `GET /api/test` dipertahankan (instruksi pemilik 9 Okt, errata API Lampiran A). **Masih menunggu keputusan/konfirmasi pemilik:** C-07 (usulan tim: xlsx tidak dipakai lagi, F-48 administratif; konfirmasi pemilik belum tercatat); penutupan formal label CLOSED (F-53, F-38, F-15/F-10, F-06) setelah Runner; kandidat F-56 (hash bcrypt penuh per Student baru untuk `token_hash` placeholder QR di `StudentService::store`, baseline §17; hanya memperlambat import, tidak memengaruhi fungsi/keamanan menurut baseline) menunggu keputusan pemilik.

## Catatan desain collection MERGE-001

- Folder 1→6 berurutan, satu kali jalan, DB `migrate:fresh --seed`. `habit_date` ditentukan server (Asia/Jakarta) dan entry immutable: run kedua di hari yang sama butuh `migrate:fresh` lagi.
- Rate limit login: tiga route login berbagi satu counter per IP (5/menit) + `login-identifier`. Login per folder: 3, 2, 0, 0, 5 (tepat batas), 2. Jalankan `php artisan cache:clear` sebelum tiap folder.
- Reset password (folder 5) dikerjakan SEBELUM bulk-graduate dan Teacher inactive (folder 6) karena kebutuhan state (scope Teacher = assignment aktif); urutan ini menyimpang dari urutan penyebutan di DoD, demi dependency.
- Akun uji memakai NIP/NISN sebagai kredensial awal (bukan secret). `sa_password` kosong bertipe secret.
- File import (`merge001-import-3-baris.xlsx`, 3 baris NISN 5000000001–5000000003) dibuat user di luar repo; tidak di-commit.

## Status eksekusi dan sisa (diperbarui 9 Okt 2026)

Sudah dijalankan Orang 1 (laporan, bukan bukti tersimpan): `php artisan test` 443 passed / 2216 assertions (baseline §17, menggantikan 434/2136), dan collection MERGE-001 Folder 1-6 0 Failed. Baseline §18 (SECURITY-001, Orang 2) melaporkan 488 passed / 2460 assertions; setelah merge dan PAGINATION-C02 (+9 test) harapan 497 passed (belum dijalankan).

Sisa:

1. Ulangi `php artisan migrate:fresh --seed`, `php artisan test`, dan collection MERGE-001 setelah merge atau perubahan kode berikutnya, untuk mendeteksi regresi.
2. Butir terbuka di register: C-02 (pagination; kode ditulis, menunggu run) dan C-07 (menunggu konfirmasi pemilik). Penutupan label CLOSED dan kandidat F-56 menunggu keputusan.
3. Sebelum deploy (bukan syarat MERGE-001): ganti password Super Admin di lingkungan yang pernah memakai string lama (string itu ada di riwayat git: `25cc67f`, `7396a1a`), isi `SEED_SUPER_ADMIN_PASSWORD`, ukur waktu import (Postman 10.14 dan 14.01) setelah F-55 di server nyata.
