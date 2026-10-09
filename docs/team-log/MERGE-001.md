# MERGE-001 — Log

**Status:** Sesi audit, dokumen, dan Postman SELESAI disusun (sandbox penyusun tidak punya PHP/PostgreSQL/Postman). **Eksekusi dilaporkan Orang 1 pada 9 Okt 2026:** PHPUnit 443 passed / 2216 assertions; Postman Folder 1-6 dilaporkan 0 Failed. Ini laporan, BUKAN bukti tersimpan: tidak ada file hasil Runner di repo. Koreksi saat run: asersi 3.09 dan 3.12 semula mengharapkan 7 entry, diubah menjadi 6 karena `GET /habits/entries` tidak memuat Bermasyarakat (addendum Habit-GET).
**PIC:** Bersama (sesi ini: sisi Orang 1) · **Basis:** HEAD `6974b29`

## Ringkasan

- **Langkah 0 (dependency):** TEST-001 (124 request) dan TEST-002 (218 request; baseline menulis 217) ada di repo; hasil Runner TEST-001 tidak tercatat di repo. LEVEL-RANKING-001 dan AUTHORIZATION-MATRIX terpenuhi. Audit Stage 0–6: seluruh F-01..F-53 FIXED — VERIFIED, label CLOSED formal belum ditulis (kecuali F-14).
- **Tugas A (endpoint) — dicek ke kode:** 69 route → 64 ada di API Contract, 3 di errata/addendum, 2 tidak ada di dokumen manapun (`GET /api/test`, `GET /teachers/import/template`); 64/64 endpoint kontrak punya route; 0 pelanggaran role vs Matrix v5.2.
- **Tugas B (migrasi + seeder) — dicek ke kode:** urutan FK aman; struktur akhir cocok DB Design v1.5 + errata (termasuk `uq_tca_one_active_per_rombel_year`); seeder 2 Super Admin (password dari env), 7 habit, 28 indikator, 101 level; tidak ada jejak Grade Advancement / mass promotion / import School / import Headmaster. Selisih: tabel `import_batches` tidak ada di DB Design.
- **Tugas C (register SoT):** 15 butir; 9 tertutup oleh errata/addendum/keputusan, 5 terbuka (C-01, C-02, C-06, C-07, C-11), 1 informasi (C-15).
- **Tugas D (Postman e2e):** collection `MERGE-001-Postman-Collection.json`, 63 request, 6 folder, tanpa secret. Dijalankan 9 Okt 2026 (lihat Status).

## File

- `docs/testing/MERGE-001-sot-register.md` — hasil Tugas A/B/C, checklist DoD, perintah run.
- `docs/testing/MERGE-001-Postman-Collection.json` — collection e2e.
- `docs/team-log/MERGE-001.md` — log ini.

## Temuan (tidak diperbaiki; rinci di register)

**Milik Orang 1:** C-02 `GET /students` dan `GET /student-class-assignments` tanpa pagination (cabang Super Admin tak berbatas) — menunggu keputusan pemilik; teks usang `docs/team-log/TEST-001.md` (86 request), `docs/team-log/IMPORT-001.md` dan `docs/db-gap-analysis.md` NOTE-09 (queue polling).
**Milik Orang 2:** A-1 `GET /api/test`; A-2 `GET /api/v1/teachers/import/template`; C-06 teks usang `README-TEST-002.md` baris 22 dan baseline §15 (217 vs 218 request, TEST-001 password, 709 vs 712); C-11 definisi `import_batches`.
**Keputusan pemilik:** C-01 amandemen Dokumen v4.0 §9 / Matrix #9 / Role Summary untuk `name` di ranking; C-07 sinkronisasi Task Board xlsx (F-48); A-3 pengakuan route infrastruktur (`/up`, `/`, `/login`); penutupan formal label CLOSED (F-53, F-38, F-15/F-10, F-06) setelah Runner.

## Catatan desain collection MERGE-001

- Folder 1→6 berurutan, satu kali jalan, DB `migrate:fresh --seed`. `habit_date` ditentukan server (Asia/Jakarta) dan entry immutable: run kedua di hari yang sama butuh `migrate:fresh` lagi.
- Rate limit login: tiga route login berbagi satu counter per IP (5/menit) + `login-identifier`. Login per folder: 3, 2, 0, 0, 5 (tepat batas), 2. Jalankan `php artisan cache:clear` sebelum tiap folder.
- Reset password (folder 5) dikerjakan SEBELUM bulk-graduate dan Teacher inactive (folder 6) karena kebutuhan state (scope Teacher = assignment aktif); urutan ini menyimpang dari urutan penyebutan di DoD, demi dependency.
- Akun uji memakai NIP/NISN sebagai kredensial awal (bukan secret). `sa_password` kosong bertipe secret.
- File import (`merge001-import-3-baris.xlsx`, 3 baris NISN 5000000001–5000000003) dibuat user di luar repo; tidak di-commit.

## Sisa yang perlu dijalankan user

1. `php artisan migrate:fresh --seed` lalu `php artisan test` (bandingkan dengan baseline 434 passed / 2136 assertions).
2. Collection MERGE-001 per folder (isi `sa_password`; `cache:clear` antar folder).
3. Bila hijau: pemilik memutuskan penutupan label CLOSED dan butir terbuka di register.
