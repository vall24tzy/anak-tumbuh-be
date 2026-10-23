# INTEG-DOC-001 — Log

**Status:** langkah 1 sampai 4 dikerjakan; menunggu tinjau silang Orang 2 dan keputusan pemilik atas temuan langkah 4 (bagian "Temuan langkah 4"). Belum DONE.
**PIC:** Bersama (dikerjakan Orang 1)
**Tanggal:** 22 Okt 2026
**Dasar:** Task Board Tambahan Integrasi FE v1.5 (INTEG-DOC-001); Matrix v5.3 #62-#69; D-05; SoT-Amendment-INTEG-D01-D13 §3b.
**Dependency:** semua task INTEG fitur selesai dan lulus review silang (QR, REKAP, PANDUAN, TEST-001, ME, DASHHM, SCHOOL, TEACHER, TEST-002, D-19).

## Yang sudah dikerjakan

1. **Audit konsistensi** (Matrix v5.3, API Contract v1.2 + addendum, board, kode): hasil di bawah.
2. **`docs/authorization-matrix.md`** naik ke v5.3: baris #62-#69 ditambahkan sebagai Bagian 17 (endpoint, penegak, verifikasi kode + test), ringkasan menjadi 69/69 baris, catatan cakupan diperbarui. Bagian 1-16 (audit v5.2) tidak ditulis ulang karena baris #1-#61 tidak diubah oleh Matrix v5.3 (SoT-Amendment §3b).
3. **API Contract v1.3** (langkah 4): `API_Contract_ANAKTUMBUH_v1_3_FINAL.md`, 12 sumber (11 addendum + Errata Stage 5) digabung, 850 dari 850 baris sumber disalin apa adanya (bagian "Langkah 4").
4. **Tidak ada perubahan kode aplikasi, test, Dokumen Keputusan, maupun Authorization Matrix.**

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

## Keputusan yang dipakai di langkah 4

1. **Cakupan:** semua `API-Contract-v1.2-Addendum-*` (11 file) dan `API-Contract-v1.2-Errata-Stage5.md` (instruksi Orang 2, 22 Okt 2026; usul penyusun disetujui). `SoT-Amendment-*` dan `DB-Design-*` tidak digabung ke API Contract.
2. **DB Design:** kedua errata (Habit-Guides dan Stage 5) tetap sebagai errata; tidak ada DB Design v1.6.
3. **Aturan salin (Orang 2):** isi sumber disalin apa adanya, tanpa mengubah makna dan tanpa rule, endpoint, field, atau skema baru; bagian v1.2 yang tidak dikoreksi sumber manapun identik dengan v1.2; pada §11 dan §15 hanya PATCH yang berubah (DELETE tetap tertulis tidak ada); konflik dengan Dokumen v4.0 atau Matrix dilaporkan, tidak diselesaikan di dokumen.
4. **Format:** teks Markdown mengikuti v1.2. Butir "Format" pada instruksi Orang 2 masih berisi placeholder `[ISI: ...]` yang belum diisi; mohon konfirmasi apakah perlu Word asli. Konversi tidak mengubah isi.
5. **Penempatan:** tiap sumber menjadi blok berjudul "Revisi v1.3 — … (sumber: …)" di akhir bagian yang dikoreksi; Errata Lampiran A dan Route-Tambahan masuk Lampiran A; hasil cross-check §26 dan keputusan N-1 sampai N-3 masuk Lampiran B; bagian 8 (D-19) addendum Habit-Guide masuk §14, sisanya §21.

## Langkah 4 — API Contract v1.3

**Berkas:** `API_Contract_ANAKTUMBUH_v1_3_FINAL.md`, disimpan di Project files bersama v1.2 (**di luar repo**; v1.2 tidak dihapus sampai pemilik menyetujui v1.3).

**Ringkasan angka:** 12 sumber, 25 blok, **850/850 baris sumber** ada apa adanya di v1.3 (judul sumber diturunkan satu level; judul utama sumber menjadi judul tingkat 3). v1.2 1169 baris menjadi v1.3 2407 baris. Dari 33 bagian v1.2, 19 identik dan 14 tersentuh; ditambah Lampiran A dan B (baru).

### Tabel penelusuran: sumber → bagian v1.3

Tidak ada sumber yang tidak tercatat. Kolom "Log" = nomor baris di Addendum Log v1.3 (§1.1). Kolom "Baris disalin" = baris non-kosong sumber yang ditemukan persis di v1.3.

| Log | Sumber | Baris disalin | Bagian v1.3 (baris di berkas v1.3) |
|---|---|---|---|
| v1.3.1 | Addendum Habit-GET | 45/45 | §21 (baris 1452-1517) |
| v1.3.2 | Addendum Level-Ranking | 84/84 | §23 (baris 1829-1852); §24 (baris 1878-1969) |
| v1.3.3 | Errata Stage 5 | 318/318 | §7 (baris 191-237); §9 (baris 352-416); §10 (baris 480-503); §14 (baris 785-810); §15 (baris 909-930); §16 (baris 1043-1068); §17 (baris 1121-1150); §18 (baris 1238-1298); §23 (baris 1777-1828); Lampiran A (baris 2266-2289); Lampiran A (baris 2290-2343); Lampiran B (baris 2380-2407) |
| v1.3.4 | Addendum Pagination-Students | 18/18 | §7 (baris 238-268) |
| v1.3.5 | Addendum Route-Tambahan | 25/25 | Lampiran A (baris 2344-2379) |
| v1.3.6 | Addendum INTEG-DASHHM-001 | 35/35 | §25 (baris 2003-2055) |
| v1.3.7 | Addendum INTEG-ME-001 | 49/49 | §10 (baris 504-572) |
| v1.3.8 | Addendum Habit-Recap | 63/63 | §21 (baris 1518-1604) |
| v1.3.9 | Addendum QR-Regenerated-At | 21/21 | §17 (baris 1151-1187) |
| v1.3.10 | Addendum INTEG-SCHOOL-001 | 67/67 | §11 (baris 601-688) |
| v1.3.11 | Addendum INTEG-TEACHER-001 | 65/65 | §15 (baris 931-1016) |
| v1.3.12 | Addendum Habit-Guide | 60/60 | §14 (baris 811-825); §21 (baris 1605-1682) |

Catatan penempatan: errata "§18 dan §28" dan "§23 dan §25" masing-masing satu blok (di §18 dan §23); addendum ME berlaku juga untuk §25 tetapi ditempatkan di §10; Pagination-Students berlaku untuk §17 dan §19 tetapi ditempatkan di §7; Level-Ranking dua blok (§23 dan §24); Habit-Guide dua blok (§14 untuk D-19, §21 untuk sisanya).

### Perbedaan v1.2 dan v1.3 (hanya bagian yang disentuh sumber)

- **Identik dengan v1.2 (19 bagian):** §2, 3, 4, 5, 6, 8, 12, 13, 19, 20, 22, 26, 27, 28, 29, 30, 31, 32, 33 (di §33 hanya ada satu baris kosong tambahan di ujung). Termasuk §4 dan §17 baris `GET /students`, yang sengaja tidak diubah (lihat temuan 2).
- **Hanya penambahan blok "Revisi v1.3" (14 bagian):** §1.1 (+14 baris: 12 baris Addendum Log dan catatan v1.3), §7 (+78), §9 (+65), §10 (+93), §14 (+41), §16 (+26), §17 (+67), §18 (+61), §21 (+231), §23 (+76), §24 (+92), §25 (+53); Lampiran A dan B (baru, 145 baris).
- **Baris v1.2 yang diganti (4 baris):**
  1. Judul: `API Contract ANAKTUMBUH.ID v1.2 FINAL` menjadi `... v1.3 FINAL`.
  2. §1 baris "Dokumen": hanya `v1.2` menjadi `v1.3` dan klausa di ujung baris tentang penggabungan 11 addendum dan 1 errata (22 Okt 2026).
  3. §11: `- PATCH /api/v1/schools/{id}, DELETE /api/v1/schools/{id} — **tidak ada** karena Update/Delete School tidak diberikan ...` menjadi `- DELETE /api/v1/schools/{id} — **tidak ada** karena Delete School tidak diberikan ...` (PATCH diatur blok INTEG-SCHOOL-001). Selain pencoretan PATCH, kata "Update/" pada klausa alasan ikut dihapus.
  4. §15: `- PATCH/DELETE /api/v1/teachers/{id} (edit profil/hapus) — ...` menjadi `- DELETE /api/v1/teachers/{id} (hapus) — ...` (PATCH diatur blok INTEG-TEACHER-001).
- **Baris tambahan di luar isi sumber:** 26 judul pembungkus "Revisi v1.3 — …", 12 baris Addendum Log, satu paragraf "Catatan v1.3". Tidak ada baris lain yang bukan dari v1.2 maupun sumber.

### Temuan langkah 4 (tidak diselesaikan di dokumen; butuh keputusan pemilik atau tindak lanjut)

1. **Konflik addendum vs Dokumen v4.0 (Habit-GET bagian 4).** Addendum menyatakan entri habit immutable dan "daily lock" dicabut; Dokumen v4.0 (dan amandemen) masih menyebut daily lock dan past-date rejection (Habit/StudentHabit: "Asia/Jakarta daily lock"). Dokumen v4.0 lebih tinggi daripada API Contract. Disalin apa adanya. **Pemilik:** konfirmasi mana yang berlaku.
2. **Konflik addendum vs Dokumen v4.0/Matrix (Level-Ranking bagian 6: nama pada entri ranking).** `SoT-Amendment-Ranking-Name.md` bagian 3 menyediakan teks siap tempel untuk §4 dan §17 ("kecuali entri ranking (lihat §24)"). Teks itu **tidak diterapkan** karena `SoT-Amendment-*` tidak digabung ke kontrak dan konflik tidak diselesaikan sendiri; akibatnya §4 dan §17 di v1.3 sama dengan v1.2, sedangkan blok §24 menyatakan konflik itu sudah ditutup lewat amandemen. **Pemilik/Orang 2:** putuskan apakah dua kalimat itu diterapkan di §4 dan §17 (perubahannya kecil dan sudah tertulis di amandemen).
3. **Konflik antar-sumber: Level-Ranking bagian 8 vs Errata F-40.** Level-Ranking: `gamification_level` pada dashboard Student "belum diintegrasikan, tetap scope MERGE-001". Errata F-40: sudah ditambahkan. Keduanya disalin apa adanya (Errata lebih baru, 5 Okt lawan 28 Sep, tetapi tidak menyatakan menggantikan bagian 8). **Pemilik:** tetapkan mana yang berlaku.
4. **Rujukan "§22/§25" di board (catatan terbuka).** Di v1.2, §22 adalah Bermasyarakat API dan §25 Dashboard API. Koreksi §25 ada (ME, DASHHM); tidak ada addendum yang mengoreksi §22, dan tidak ada koreksi §22 di v1.3. Tidak ditulis di kontrak. **Pemilik:** konfirmasi apakah salah ketik di board.
5. **Rujukan versi di v1.2 tidak dikoreksi addendum manapun, jadi tidak diubah:** §1, §2, §6, §33 (termasuk kalimat penutup) masih menyebut Authorization Matrix v5.2; §32 (Traceability Matrix) belum memuat baris #62 sampai #69. **Pemilik:** putuskan pembaruan rujukan ke Matrix v5.3 dan baris §32 (bukan koreksi addendum).
6. **Teks sumber yang basi di konteks v1.3 (disalin apa adanya):** Pagination-Students "kode dan test ditulis; belum dijalankan"; Errata Stage 5 (pengantar) "bukan Contract v1.3" dan "v1.2 FINAL tidak disimpan di repo"; Errata §7 menyebut `GET /students` belum dipaginasi (digantikan Pagination-Students secara kronologis); blok SCHOOL, TEACHER, QR, Habit-Guide memuat bagian "Untuk penggabungan ke API Contract v1.3 (INTEG-DOC-001)" yang kini sudah terlaksana, dan bagian "Kode"/"Test" yang merujuk berkas repo.
7. **Koreksi yang tidak punya penunjuk di bagian yang diubahnya:** isi errata §28 ada di blok §18; isi errata §25 ada di blok §23; addendum ME (juga §25) ada di §10. Pembaca §28 tidak menemukan penunjuk ke blok §18. Opsi: satu kalimat rujukan pada revisi berikutnya (tidak ditambahkan sekarang karena aturan "bagian tidak dikoreksi harus sama persis").
8. **Tumpang tindih sumber:** Pagination-Students dengan Errata §7, dan Route-Tambahan dengan Errata Lampiran A. Keduanya disalin; Route-Tambahan menyatakan "Lampiran A menang" bila ada beda. Route-Tambahan memuat catatan untuk SECURITY-001: `GET /api/test` dan halaman `/` bawaan ditinjau/dihapus sebelum production. **Tindak lanjut:** SECURITY-001 dan DEPLOYMENT-001.
9. **Definisi D-xx dirujuk beberapa blok tetapi tidak ada di dokumen**, karena `SoT-Amendment-*` tidak digabung (definisinya di `docs/addendum/SoT-Amendment-INTEG-D01-D13.md`).
10. **Format Word:** lihat butir 4 "Keputusan yang dipakai".

### Cara pemeriksaan dan batasnya

Pemeriksaan: (a) perbandingan baris v1.2 lawan v1.3 (hanya 4 baris diganti, selebihnya sisipan); (b) pencocokan tiap baris non-kosong tiap sumber terhadap v1.3 (850/850); (c) pemeriksaan bahwa baris v1.3 yang bukan dari v1.2 maupun sumber hanya pembungkus judul, baris Addendum Log, catatan v1.3, dan dua kalimat §11/§15; (d) cuplikan tata letak blok. Batas: berkas tidak dibuka di Word atau penampil Markdown (hanya diperiksa sebagai teks); isi addendum tidak dinilai ulang benar-salahnya, hanya disalin dan dicocokkan; status board tidak diubah.

## Verifikasi

Tidak ada kode aplikasi yang berubah, jadi tidak ada test baru. Tabel Bagian 17 mengandalkan test yang sudah ada; suite penuh terakhir: 623 passed (3502 assertions), PHP 8.3.6, PostgreSQL 16.15 (sandbox penyusun; bukan lingkungan tim, PostgreSQL 17.11).

## Tinjau silang (Orang 2)

Periksa: Bagian 17 `docs/authorization-matrix.md` (baris, endpoint, nama kelas), tiga temuan audit di atas, dan bahwa isi keputusan tidak diubah. Untuk langkah 4: tabel penelusuran (12 sumber, tidak ada yang tertinggal), empat baris v1.2 yang diganti, dan temuan 1 sampai 10 (khusus butir 1 sampai 4 membutuhkan keputusan pemilik).
