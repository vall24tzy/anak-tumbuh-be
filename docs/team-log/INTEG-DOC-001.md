# INTEG-DOC-001 — Log

**Status:** langkah 1 sampai 4 selesai. Keputusan pemilik D-20 sampai D-24 (23 Okt 2026) sudah diterapkan (amandemen D-20 pada Dokumen Keputusan v4.0 dan koreksi D-23 pada board tercatat selesai di Task Board Tambahan v1.6, 26 Okt 2026), dan D-25 (27 Okt 2026) dicatat. Tersisa pada pemilik: mengarsipkan API Contract v1.2, memastikan tim FE menerima v1.3 dan file Koreksi FE, membaca sekilas lima tempat di v1.3, dan menaikkan status ke DONE di board setelah pemeriksaan akhir. Belum DONE di board.
**PIC:** Bersama (dikerjakan Orang 1)
**Tanggal:** 22 Okt 2026 (pembaruan 23 Okt 2026: D-20 sampai D-24; 25 Okt 2026: dua referensi "daily lock" tambahan; 27 Okt 2026: D-25 dan Buku Panduan v1.5)
**Dasar:** Task Board Tambahan Integrasi FE v1.5 (INTEG-DOC-001); Matrix v5.3 #62-#69; D-05; SoT-Amendment-INTEG-D01-D13 §3b; D-20 sampai D-24 (pemilik, 23 Okt 2026) dan D-25 (pemilik, 27 Okt 2026), keduanya di `docs/addendum/SoT-Amendment-INTEG-DOC-001.md`.
**Dependency:** semua task INTEG fitur selesai dan lulus review silang (QR, REKAP, PANDUAN, TEST-001, ME, DASHHM, SCHOOL, TEACHER, TEST-002, D-19).

## Yang sudah dikerjakan

1. **Audit konsistensi** (Matrix v5.3, API Contract v1.2 + addendum, board, kode): hasil di bawah.
2. **`docs/authorization-matrix.md`** naik ke v5.3: baris #62-#69 ditambahkan sebagai Bagian 17 (endpoint, penegak, verifikasi kode + test), ringkasan menjadi 69/69 baris, catatan cakupan diperbarui. Bagian 1-16 (audit v5.2) tidak ditulis ulang karena baris #1-#61 tidak diubah oleh Matrix v5.3 (SoT-Amendment §3b). Pembaruan 23 Okt 2026: **Temuan #2 (daily lock) ditandai diselesaikan oleh D-20**; Matrix #35 tidak berubah.
3. **API Contract v1.3** (langkah 4): `API_Contract_ANAKTUMBUH_v1_3_FINAL.md` (sumber) dan `API_Contract_ANAKTUMBUH_v1_3_FINAL.docx` (Word, dibuat dari md final), keduanya di Project files (**di luar repo**). 12 sumber (11 addendum + Errata Stage 5) digabung; 850 dari 850 baris sumber ada apa adanya. D-21, D-22, dan D-24 diterapkan (bagian "Langkah 4").
4. **`docs/addendum/SoT-Amendment-INTEG-DOC-001.md`** (baru): memuat D-20 sampai D-24 (disetujui pemilik, 23 Okt 2026), kutipan sumber dan bukti kode D-20, serta teks siap tempel amandemen Dokumen Keputusan v4.0 (diterapkan pemilik).
5. **Tidak ada perubahan kode aplikasi, test, Dokumen Keputusan, maupun Authorization Matrix.** `SoT-Amendment-INTEG-D01-D13.md` tidak disentuh.

## Hasil audit

| Pemeriksaan | Hasil |
|---|---|
| Kolom peran Matrix #62-#69 vs middleware route (`php artisan route:list`) | 8/8 sesuai: #62 `role:teacher`; #63 `role:teacher`; #64 `role:teacher,student`; #65 `role:super_admin`; #66 `role:headmaster`; #68 `role:headmaster`; #67 dan #69 lewat field kondisional di endpoint `jwt` (`/auth/me`, `/dashboard/student`, `GET /students`) |
| Setiap baris baru punya endpoint dan test yang merujuk | 8/8 (tabel Bagian 17 di `docs/authorization-matrix.md`) |
| Kolom "Baris Matrix" di board tiap task vs nomor final | Sesuai: QR #69, REKAP #62, PANDUAN #63 dan #64, SCHOOL #65, TEACHER #66, ME #67, DASHHM #68; TEST-001 merujuk #62-#64, #69; TEST-002 merujuk #65-#68 |
| Koreksi API Contract tercatat di addendum | §10 dan §25 (ME), §25 (DASHHM), §11 (SCHOOL), §15 (TEACHER). §17 (field QR) dan §21 (rekap, panduan) tercatat di addendum QR, REKAP, PANDUAN |

## Temuan (belum diubah; butuh keputusan atau di luar repo)

1. **Rujukan "§22/§25" di board (perlu konfirmasi pemilik).** Di Contract v1.2, §22 adalah Bermasyarakat API dan §25 adalah Dashboard API. Koreksi §25 ada (ME dan DASHHM). Tidak ada addendum yang mengoreksi §22, dan menurut pembacaan tidak ada kalimat §22 yang bertentangan dengan fitur INTEG. Kemungkinan salah ketik di board; tidak ada yang dikarang untuk §22. **Status: D-23, salah ketik; pemilik memperbaiki teks board (di luar repo).**
2. **Untuk langkah 4: dua kalimat Contract v1.2 harus ditulis ulang hati-hati.** §11 ("PATCH/DELETE `/schools/{id}` tidak ada") dan §15 ("PATCH/DELETE `/teachers/{id}` tidak ditambahkan"): addendum SCHOOL dan TEACHER hanya mengganti **PATCH** (menjadi ada, #65 dan #66); **DELETE tetap tidak ada** dan harus tetap tertulis begitu pada v1.3. **Status: selesai di v1.3 (hanya PATCH yang diganti).**
3. **Info, file pemilik (di luar repo):** Role Summary di Matrix v5.3 tidak menyebut #68 (total dashboard) pada baris Headmaster, sedangkan baris Teacher menyebut View qr_regenerated_at (#69) dan baris Student menyebut #67. Hanya ringkasan; tidak ada aturan yang bertentangan.
4. **Di luar repo:** kolom Status di file board (Task Board Tambahan Integrasi FE v1.5) masih TODO atau MENUNGGU DEPENDENCY; pemilik/PIC menaikkannya ke DONE setelah langkah ini.

## Keputusan yang dipakai di langkah 4

1. **Cakupan:** semua `API-Contract-v1.2-Addendum-*` (11 file) dan `API-Contract-v1.2-Errata-Stage5.md` (instruksi Orang 2, 22 Okt 2026; usul penyusun disetujui). `SoT-Amendment-*` dan `DB-Design-*` tidak digabung ke API Contract.
2. **DB Design:** kedua errata (Habit-Guides dan Stage 5) tetap sebagai errata; tidak ada DB Design v1.6.
3. **Aturan salin (Orang 2):** isi sumber disalin apa adanya, tanpa mengubah makna dan tanpa rule, endpoint, field, atau skema baru; bagian v1.2 yang tidak dikoreksi sumber manapun identik dengan v1.2; pada §11 dan §15 hanya PATCH yang berubah (DELETE tetap tertulis tidak ada).
4. **Format:** Markdown (sumber) dan Word. Instruksi Orang 2 tanggal 23 Okt 2026 meminta `.docx` dari md final dengan `API_Contract_ANAKTUMBUH_v1_2_FINAL.docx` sebagai template; lihat temuan 12 (file itu bukan Word asli).
5. **Penempatan:** tiap sumber menjadi blok berjudul "Revisi v1.3 — … (sumber: …)" di akhir bagian yang dikoreksi; Errata Lampiran A dan Route-Tambahan masuk Lampiran A; hasil cross-check §26 dan keputusan N-1 sampai N-3 masuk Lampiran B; bagian 8 (D-19) addendum Habit-Guide masuk §14, sisanya §21.
6. **Keputusan pemilik atas temuan langkah 4 (disetujui pemilik, 23 Okt 2026; rincian di `docs/addendum/SoT-Amendment-INTEG-DOC-001.md`):**
   - **D-20:** kode dan Contract sudah benar (tanggal server Asia/Jakarta, tanpa lock harian, tanpa PATCH); yang usang adalah redaksi "daily lock / past-date rejection" di Dokumen v4.0, diselesaikan lewat amandemen dokumen saja. Matrix #35 tidak berubah.
   - **D-21:** terapkan frasa "kecuali entri ranking (lihat §24)" di §4 dan §17 (sumber: `SoT-Amendment-Ranking-Name.md` bagian 3).
   - **D-22:** Errata F-40 berlaku; pernyataan "belum diintegrasikan" di Level-Ranking bagian 8 digantikan.
   - **D-23:** rujukan "§22/§25" di board adalah salah ketik.
   - **D-24:** rujukan Authorization Matrix v5.2 di v1.3 diperbarui ke v5.3, dan §32 memuat baris #62 sampai #69.
7. **D-25 (disetujui pemilik, 27 Okt 2026; rincian di `docs/addendum/SoT-Amendment-INTEG-DOC-001.md` bagian 8):** API Contract v1.3 FINAL menjadi satu-satunya sumber kontrak API; API Contract v1.2 diarsipkan (tidak dihapus) dengan label "DIGANTIKAN oleh v1.3" sebagai baseline sebelum 11 task INTEG. Komentar kode "API Contract v1.2 §xx" tetap valid; nama berkas addendum dan Errata Stage 5 tetap; dokumen v1.3 tidak diedit. Pelaksanaan (arsip v1.2, serah terima FE, baca lima tempat di v1.3) oleh pemilik.

## Langkah 4 — API Contract v1.3

**Berkas:** `API_Contract_ANAKTUMBUH_v1_3_FINAL.md` dan `API_Contract_ANAKTUMBUH_v1_3_FINAL.docx`, disimpan di Project files bersama v1.2 (**di luar repo**; v1.2 tidak dihapus sampai pemilik menyetujui v1.3).

**Ringkasan angka:** 12 sumber, 25 blok, **850/850 baris sumber** ada apa adanya (judul sumber diturunkan satu level; judul utama sumber menjadi judul tingkat 3). v1.2 1169 baris menjadi v1.3 2383 baris. Dari 33 bagian v1.2: 14 identik penuh, 10 hanya mendapat blok revisi, 9 memuat baris v1.2 yang diganti (§1, 2, 4, 6, 11, 15, 17, 33) atau baris tabel tambahan (§32); ditambah Lampiran A dan B (baru).

### Tabel penelusuran: sumber → bagian v1.3

Tidak ada sumber yang tidak tercatat. Kolom "Log" = nomor baris di Addendum Log v1.3 (§1.1); keputusan D-20 sampai D-24 tercatat di baris v1.3.13 sampai v1.3.17 (sumber: `SoT-Amendment-INTEG-DOC-001.md`, tidak digabung ke kontrak). Kolom "Baris disalin" = baris non-kosong sumber; isi blok sama persis dengan isi sumber menurut jumlah kemunculan tiap baris (tanpa kurang, tanpa lebih).

| Log | Sumber | Baris disalin | Bagian v1.3 (baris di berkas md) |
|---|---|---|---|
| v1.3.1 | Addendum Habit-GET | 45/45 | §21 (baris 1459-1524) |
| v1.3.2 | Addendum Level-Ranking | 84/84 | §23 (baris 1818-1841); §24 (baris 1867-1960) |
| v1.3.3 | Errata Stage 5 | 318/318 | §7 (baris 198-244); §9 (baris 359-423); §10 (baris 487-510); §14 (baris 792-817); §15 (baris 916-937); §16 (baris 1050-1075); §17 (baris 1128-1157); §18 (baris 1245-1305); §23 (baris 1784-1817); Lampiran A (baris 2265-2288); Lampiran A (baris 2289-2317); Lampiran B (baris 2354-2383) |
| v1.3.4 | Addendum Pagination-Students | 18/18 | §7 (baris 245-275) |
| v1.3.5 | Addendum Route-Tambahan | 25/25 | Lampiran A (baris 2318-2353) |
| v1.3.6 | Addendum INTEG-DASHHM-001 | 35/35 | §25 (baris 1994-2046) |
| v1.3.7 | Addendum INTEG-ME-001 | 49/49 | §10 (baris 511-579) |
| v1.3.8 | Addendum Habit-Recap | 63/63 | §21 (baris 1525-1611) |
| v1.3.9 | Addendum QR-Regenerated-At | 21/21 | §17 (baris 1158-1194) |
| v1.3.10 | Addendum INTEG-SCHOOL-001 | 67/67 | §11 (baris 608-695) |
| v1.3.11 | Addendum INTEG-TEACHER-001 | 65/65 | §15 (baris 938-1023) |
| v1.3.12 | Addendum Habit-Guide | 60/60 | §14 (baris 818-832); §21 (baris 1612-1689) |

Catatan penempatan: errata "§18 dan §28" dan "§23 dan §25" masing-masing satu blok (di §18 dan §23); addendum ME berlaku juga untuk §25 tetapi ditempatkan di §10; Pagination-Students berlaku untuk §17 dan §19 tetapi ditempatkan di §7; Level-Ranking dua blok (§23 dan §24); Habit-Guide dua blok (§14 untuk D-19, §21 untuk sisanya). Letak koreksi ini dinyatakan di paragraf "Catatan pembaca v1.3" pada §1.1.

### Perbedaan v1.2 dan v1.3 (hanya bagian yang disentuh sumber atau keputusan)

- **Identik penuh dengan v1.2 (14 bagian):** §3, §5, §8, §12, §13, §19, §20, §22, §26, §27, §28, §29, §30, §31.
- **Hanya blok revisi ditambahkan (10 bagian; jumlah baris blok):** §7 (+57), §9 (+55), §10 (+69), §14 (+30), §16 (+21), §18 (+52), §21 (+163), §23 (+42), §24 (+70), §25 (+36).
- **Baris v1.2 diganti (13 baris):**

| No | Bagian | Perubahan | Dasar |
|---|---|---|---|
| 1 | Judul | `v1.2 FINAL` menjadi `v1.3 FINAL` | versi |
| 2 | §1 baris "Dokumen" | `v1.2` menjadi `v1.3`; klausa di ujung baris tentang penggabungan 11 addendum dan 1 errata (22 Okt 2026) dan keputusan D-20 sampai D-24 (23 Okt 2026). Penyebutan "Matrix v5.2" pada baris ini **tidak diubah** karena menjelaskan sinkronisasi v1.1 (20 Sep 2026), fakta historis | versi |
| 3 | §1 bullet Dokumen Keputusan v4.0 | ditambah kalimat: diamandemen 20 Okt 2026 (INTEG-DECISION-001) dan 23 Okt 2026 (D-20); nomor versi tetap 4.0 | D-24 |
| 4 | §1 bullet Authorization Matrix | `v5.2 FINAL` menjadi `v5.3 FINAL`; ditambah kalimat naik v5.2 ke v5.3 (delapan baris #62-#69) | D-24 |
| 5 | §2 baris Authorization Matrix | `v5.2 FINAL` menjadi `v5.3 FINAL`; ditambah keterangan baris #62-#69 | D-24 |
| 6 | §4 baris Student | frasa `kecuali entri ranking (lihat §24)` setelah `no classmates list` | D-21 |
| 7 | §6 Role check | Authorization Matrix v5.2 menjadi v5.3 | D-24 |
| 8 | §11 | `PATCH /schools/{id}, DELETE /schools/{id} — tidak ada karena Update/Delete School ...` menjadi `DELETE /schools/{id} — tidak ada karena Delete School ...` (PATCH diatur blok INTEG-SCHOOL-001; kata "Update/" ikut dihapus dari klausa alasan) | SCHOOL |
| 9 | §15 | `PATCH/DELETE /teachers/{id} (edit profil/hapus) ...` menjadi `DELETE /teachers/{id} (hapus) ...` (PATCH diatur blok INTEG-TEACHER-001) | TEACHER |
| 10 | §17 baris `GET /students` | frasa `kecuali entri ranking (lihat §24)` setelah `tidak ada classmates list` | D-21 |
| 11-13 | §33 (tiga kalimat, v1.2 baris 1119, 1125, 1169) | Matrix v5.2 menjadi v5.3 | D-24 |

- **Baris tambahan di luar blok:** §1.1: 17 baris Addendum Log (v1.3.1 sampai v1.3.17) dan dua paragraf ("Catatan v1.3", "Catatan pembaca v1.3"); §32: 8 baris (#62 sampai #69); §24: satu baris "Catatan v1.3 (D-22)" di dalam blok Level-Ranking; 26 judul pembungkus "Revisi v1.3 — …". Lampiran A dan B baru (baris 2263 sampai 2383). Tidak ada baris lain yang bukan dari v1.2 maupun sumber.
- **Referensi "daily lock" v1.2 yang sengaja tidak diedit (identik dengan v1.2, digantikan D-20):** §9 contoh status 422 (v1.2 baris 233), §21 dua baris (793 dan 799; dikoreksi blok Habit-GET bagian 4), dan §33 Final Audit Checklist butir HABIT "Daily lock" (1153). Untuk §9 dan §33 yang tidak punya blok koreksi, paragraf "Catatan pembaca v1.3" butir (e) menyatakan keduanya historis dan digantikan D-20; keempat tempat dikutip di tabel 2a `SoT-Amendment-INTEG-DOC-001.md`. Jumlah baris v1.2 yang diganti tidak berubah (13).
- **Penyebutan "v5.2" yang tersisa** di luar blok sumber bersifat historis (baris "Dokumen", bullet "Naik dari v5.1 → v5.2", Addendum Log v1.1 sampai v1.2.1, teks Teacher Lifecycle/Matrix #61) dan sengaja tidak diubah.

### Status temuan langkah 4 (1 sampai 10) dan temuan baru

| # | Temuan | Status |
|---|---|---|
| 1 | Habit-GET bagian 4 vs "daily lock" di Dokumen v4.0 | **Selesai di repo: D-20.** Tanpa perubahan kode/Contract/Matrix. Menunggu pemilik menerapkan teks amandemen Dokumen v4.0 (`SoT-Amendment-INTEG-DOC-001.md` bagian 2c; di luar repo). Pembaruan 25 Okt 2026: dua referensi tambahan (§9 contoh 422; §33 butir HABIT) dicakup di tabel 2a dan Catatan pembaca butir (e) |
| 2 | Level-Ranking bagian 6 vs Dokumen v4.0 §9/Matrix #9 (nama pada ranking) | **Selesai: D-21**, frasa diterapkan di §4 dan §17 v1.3 |
| 3 | Level-Ranking bagian 8 vs Errata F-40 | **Selesai: D-22**, catatan satu baris di blok Level-Ranking; teks sumber tetap disalin apa adanya |
| 4 | Rujukan "§22/§25" di board | **Tidak perlu aksi di repo: D-23** (salah ketik); pemilik memperbaiki board |
| 5 | Rujukan Matrix v5.2 dan §32 tanpa #62-#69 | **Selesai: D-24** (lihat tabel baris diganti dan §32). Lihat temuan 11 |
| 6 | Teks sumber basi di konteks v1.3 | **Tidak perlu aksi:** paragraf "Catatan pembaca v1.3" (d) menyatakan keterangan basi tidak berlaku |
| 7 | Koreksi §28/§25/ME tanpa penunjuk di bagiannya | **Selesai:** paragraf "Catatan pembaca v1.3" (b) mencatat letak blok; tidak ada kalimat penunjuk tambahan di bagian itu |
| 8 | Tumpang tindih sumber; catatan SECURITY-001 | **Tindak lanjut di SECURITY-001 dan DEPLOYMENT-001** (di luar task ini) |
| 9 | Definisi D-xx tidak ada di dokumen | **Selesai:** paragraf "Catatan pembaca v1.3" (c) menunjuk letak definisi D-01 sampai D-24 |
| 10 | Format Word | **Selesai:** `.docx` dibuat (lihat temuan 12) |
| 11 | **Baru:** Traceability Matrix §32 di v1.2 tidak memuat baris #61 (Teacher Lifecycle), padahal Matrix v5.2 sudah memuatnya | **Terbuka, tidak diubah:** D-24 hanya mencakup #62-#69. **Pemilik:** putuskan apakah #61 ditambahkan |
| 12 | **Baru:** berkas `API_Contract_ANAKTUMBUH_v1_2_FINAL.docx` di Project files adalah teks UTF-8, bukan Word asli, sehingga tidak bisa dipakai sebagai `--reference-doc` | `.docx` v1.3 memakai gaya yang dibuat sendiri (Calibri 10, judul bertingkat, tabel berborder dengan header berwarna). **Pemilik:** bila punya v1.2 Word asli, gaya itu bisa diterapkan ulang tanpa mengubah isi |
| 13 | **Koreksi atas versi sebelumnya:** md v1.3 yang dikirim 22 Okt memuat dua blok ganda (teks pengantar dan tabel Error Contract §9, serta §26 dan N-1 sampai N-3) akibat kesalahan pada langkah pengisian baris sumber | **Diperbaiki** di versi ini; pemeriksaan duplikat ditambahkan. Versi md lama harus diganti |

### Cara pemeriksaan dan batasnya

Pemeriksaan pada md final (diulang 25 Okt 2026 setelah penambahan butir (e) pada Catatan pembaca dan dua baris tabel 2a; hasil sama): (a) perbandingan baris v1.2 lawan v1.3: tepat 13 baris diganti, semuanya terdaftar di atas; (b) pencocokan 12 sumber terhadap isi blok per jumlah kemunculan tiap baris (850/850, kurang 0, lebih 0) dan urutan di dalam blok (0 pelanggaran); (c) duplikat baris: 0; (d) baris yang bukan dari v1.2 maupun sumber: hanya pembungkus judul, baris log, catatan, 8 baris §32, dan satu catatan D-22; (e) klasifikasi tiap bagian v1.2 terhadap v1.3 (14 identik, 10 hanya blok, 9 diganti/ditambah); (f) keempat baris v1.2 yang memuat "daily lock" (233, 793, 799, 1153) ada persis sama di v1.3.

Pemeriksaan pada `.docx` (dibuat dengan pandoc dari md final, ditata dengan python-docx, daftar isi disisipkan dan diperbarui lewat LibreOffice): 224 judul (Heading 1-4) dan 56 tabel sama dengan md; 6 blok kode; deret kata sama dengan md (selisih hanya 4 nomor daftar bernomor yang dibuat otomatis oleh Word); validator skill docx lolos; render PDF 52 halaman diperiksa untuk halaman judul/daftar isi, halaman judul bertingkat, dan halaman tabel §32. Daftar isi memakai baris "Table of Contents" dari v1.2 sebagai judulnya.

**Tidak diperiksa:** berkas Word belum dibuka di Microsoft Word (hanya LibreOffice, python-docx, dan validator); font Calibri tidak terpasang di sandbox sehingga render memakai pengganti; isi addendum tidak dinilai ulang benar-salahnya, hanya disalin dan dicocokkan; Dokumen Keputusan v4.0 belum diubah (pemilik); status board belum diubah; suite penuh tidak dijalankan ulang karena tidak ada perubahan kode.

## Verifikasi

Tidak ada kode aplikasi, test, Dokumen Keputusan, atau Matrix yang berubah, jadi tidak ada test baru dan suite penuh tidak dijalankan ulang di task ini. Tabel Bagian 17 mengandalkan test yang sudah ada; angka terakhir 623 passed (3502 assertions), PHP 8.3.6, PostgreSQL 16.15 (sandbox penyusun; bukan lingkungan tim, PostgreSQL 17.11). Suite penuh dan `php artisan migrate` di environment tim dijalankan oleh pemilik.

## Buku Panduan Alur Kerja Backend v1.5 (D-25)

**Berkas (di luar repo):** `Panduan_Alur_Kerja_Backend_ANAKTUMBUH_v1.5.docx` dan `.pdf`. Dasar kode: commit `1d6c300`; tanggal 27 Okt 2026.

**Yang diubah dari v1.4 (instruksi B1 sampai B3):**
1. Lampiran D, baris urutan 3: "API Contract ANAKTUMBUH.ID v1.3 FINAL (hasil penggabungan v1.2 dengan seluruh addendum dan Errata Stage 5; sumber kontrak API sejak D-25; tersedia sebagai .docx dan .md; disimpan di luar repositori)". Ditambah satu baris: v1.2 FINAL diarsipkan sejak D-25 dan hanya menjadi riwayat; rujukan "API Contract v1.2 §xx" di komentar kode tetap valid.
2. Lampiran D, baris Task Board: "Task Board Backend v23 dan Task Board Tambahan Integrasi FE (versi terbaru; saat dokumen ini disusun v1.6, REVISI 6, 26 Okt 2026)". Rujukan Task Board di bagian lain: hanya baris riwayat versi 1.4 (tidak diedit, sesuai instruksi).
3. Bagian 1.3 dan 15.4 diperiksa: tidak ada yang menyebut v1.2 sebagai sumber kontrak yang berlaku (Bagian 1.3 menyebut v1.3; 15.4 hanya menyebut v1.2 sebagai dokumen lama yang masih memuat rujukan Matrix v5.2), jadi tidak diubah.
4. Satu baris baru di riwayat versi (1.5, 27 Okt 2026, `1d6c300`); baris 1.3 dan 1.4 tidak diedit. Versi di sampul dan header menjadi 1.5.

**Verifikasi (C):**
- Perbandingan teks v1.4 lawan v1.5 (isi dokumen berurutan termasuk tabel, ditambah header dan footer): 1.376 baris lawan 1.378 baris, tepat 5 blok selisih: (1) sampul: versi dan tanggal; (2) baris riwayat 1.5 yang baru; (3) Lampiran D baris API Contract diganti dan satu baris arsip v1.2 ditambah; (4) Lampiran D baris Task Board; (5) header versi. Baris riwayat 1.3 dan 1.4 identik.
- Ekspor ulang docx dan PDF: **72 halaman** (v1.4: 72 halaman), A4. Nomor halaman Daftar Isi dihitung ulang dan stabil (selisih 0 pada iterasi kedua).
- PDF lawan docx: perbandingan jumlah token kata, yaitu urutan tiga huruf atau lebih (16.211 token di docx). Selisih sekitar 1 persen (203 lebih di docx, 133 lebih di PDF). Kata dengan selisih terbesar adalah potongan kata panjang di akhir baris (mis. `assignmen`, `yea`) dan baris judul tabel yang diulang di tiap halaman (mis. "File", "Tahap", "Peran", "Method"). Bukan perbedaan isi; pemeriksaan ini tidak membandingkan kalimat per kalimat.
- Angka pada D-25: lihat catatan verifikasi di `SoT-Amendment-INTEG-DOC-001.md` bagian 8f (hitungan ulang: 13 baris v1.2 diganti, bukan 12).
- **Tidak diperiksa:** berkas Word belum dibuka di Microsoft Word; kode tidak dijalankan (PHP tidak tersedia); isi Bab 7 sampai 10 tidak dibaca ulang kalimat per kalimat.

## Tinjau silang (Orang 2)

Periksa bagian yang berubah sejak tinjauan sebelumnya: (1) §4 dan §17 v1.3 (D-21) dan baris catatan D-22 di blok Level-Ranking; (2) rujukan Matrix v5.3 di §1, §2, §6, §33 dan baris #62-#69 di §32; (3) tabel penelusuran dan daftar 13 baris v1.2 yang diganti; (4) `docs/addendum/SoT-Amendment-INTEG-DOC-001.md` (kutipan sumber dan bukti kode D-20, teks siap tempel Dokumen v4.0); (5) Temuan #2 di `docs/authorization-matrix.md`; (6) `.docx` yang diekspor ulang dari md final (butir "Tidak diperiksa"); (7) temuan 11, 12, dan 13; (8) dua baris baru di tabel 2a `SoT-Amendment-INTEG-DOC-001.md` dan butir (e) pada Catatan pembaca v1.3.
