# INTEG-DOC-001 — Log

**Status:** SEBAGIAN — langkah 1 sampai 3 selesai; penggabungan docx API Contract v1.3 (langkah 4) menunggu dua keputusan (lihat bagian bawah).
**PIC:** Bersama (dikerjakan Orang 1)
**Tanggal:** 22 Okt 2026
**Dasar:** Task Board Tambahan Integrasi FE v1.5 (INTEG-DOC-001); Matrix v5.3 #62-#69; D-05; SoT-Amendment-INTEG-D01-D13 §3b.
**Dependency:** semua task INTEG fitur selesai dan lulus review silang (QR, REKAP, PANDUAN, TEST-001, ME, DASHHM, SCHOOL, TEACHER, TEST-002, D-19).

## Yang sudah dikerjakan

1. **Audit konsistensi** (Matrix v5.3, API Contract v1.2 + addendum, board, kode): hasil di bawah.
2. **`docs/authorization-matrix.md`** naik ke v5.3: baris #62-#69 ditambahkan sebagai Bagian 17 (endpoint, penegak, verifikasi kode + test), ringkasan menjadi 69/69 baris, catatan cakupan diperbarui. Bagian 1-16 (audit v5.2) tidak ditulis ulang karena baris #1-#61 tidak diubah oleh Matrix v5.3 (SoT-Amendment §3b).
3. **Tidak ada perubahan kode aplikasi dan tidak ada perubahan isi keputusan.**

## Hasil audit

| Pemeriksaan | Hasil |
|---|---|
| Kolom peran Matrix #62-#69 vs middleware route (`php artisan route:list`) | 8/8 sesuai: #62 `role:teacher`; #63 `role:teacher`; #64 `role:teacher,student`; #65 `role:super_admin`; #66 `role:headmaster`; #68 `role:headmaster`; #67 dan #69 lewat field kondisional di endpoint `jwt` (`/auth/me`, `/dashboard/student`, `GET /students`) |
| Setiap baris baru punya endpoint dan test yang merujuk | 8/8 (tabel Bagian 17 di `docs/authorization-matrix.md`) |
| Kolom "Baris Matrix" di board tiap task vs nomor final | Sesuai: QR #69, REKAP #62, PANDUAN #63 dan #64, SCHOOL #65, TEACHER #66, ME #67, DASHHM #68; TEST-001 merujuk #62-#64, #69; TEST-002 merujuk #65-#68 |
| Koreksi API Contract tercatat di addendum | §10 dan §25 (ME), §25 (DASHHM), §11 (SCHOOL), §15 (TEACHER). §17 (field QR) dan §21 (rekap, panduan) tercatat di addendum QR, REKAP, PANDUAN |

## Temuan (belum diubah; butuh keputusan atau di luar repo)

1. **Rujukan "§22/§25" di board (perlu konfirmasi pemilik).** Di Contract v1.2, §22 adalah Bermasyarakat API dan §25 adalah Dashboard API. Koreksi §25 ada (ME dan DASHHM). Tidak ada addendum yang mengoreksi §22, dan menurut pembacaan tidak ada kalimat §22 yang bertentangan dengan fitur INTEG. Kemungkinan salah ketik di board; tidak ada yang dikarang untuk §22.
2. **Untuk langkah 4: dua kalimat Contract v1.2 harus ditulis ulang hati-hati.** §11 ("PATCH/DELETE `/schools/{id}` tidak ada") dan §15 ("PATCH/DELETE `/teachers/{id}` tidak ditambahkan"): addendum SCHOOL dan TEACHER hanya mengganti **PATCH** (menjadi ada, #65 dan #66); **DELETE tetap tidak ada** dan harus tetap tertulis begitu pada v1.3.
3. **Info, file pemilik (di luar repo):** Role Summary di Matrix v5.3 tidak menyebut #68 (total dashboard) pada baris Headmaster, sedangkan baris Teacher menyebut View qr_regenerated_at (#69) dan baris Student menyebut #67. Hanya ringkasan; tidak ada aturan yang bertentangan.
4. **Di luar repo:** kolom Status di file board (Task Board Tambahan Integrasi FE v1.5) masih TODO atau MENUNGGU DEPENDENCY; pemilik/PIC menaikkannya ke DONE setelah langkah ini.

## Menunggu keputusan sebelum langkah 4 (docx API Contract v1.3)

1. **Cakupan penggabungan.** Selain tujuh addendum INTEG dan dua errata Stage 5, `docs/addendum/` memuat addendum lama (`Habit-GET`, `Level-Ranking`, `Pagination-Students`, `Route-Tambahan`, dan lainnya). Usul: gabungkan semuanya agar v1.3 menjadi satu sumber lengkap; alternatif: hanya yang terkait INTEG dan Stage 5 (board mewajibkan keduanya, termasuk §18/§28 import sinkron 200 baris, F-38).
2. **Dokumen DB Design.** Ada dua errata DB (Habit-Guides dan Stage 5). Usul: tetap sebagai errata (board hanya menyebut API Contract); alternatif: DB Design v1.6.

## Verifikasi

Tidak ada kode aplikasi yang berubah, jadi tidak ada test baru. Tabel Bagian 17 mengandalkan test yang sudah ada; suite penuh terakhir: 623 passed (3502 assertions), PHP 8.3.6, PostgreSQL 16.15 (sandbox penyusun; bukan lingkungan tim, PostgreSQL 17.11).

## Tinjau silang (Orang 2)

Periksa: Bagian 17 `docs/authorization-matrix.md` (baris, endpoint, nama kelas), tiga temuan di atas, dan bahwa isi keputusan tidak diubah.
