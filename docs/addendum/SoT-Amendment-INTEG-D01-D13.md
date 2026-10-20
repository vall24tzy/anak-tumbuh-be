# SoT Amendment — Persetujuan Keputusan D-01 s/d D-13 (INTEG-DECISION-001)

**Tanggal:** 20 Okt 2026 · **Disetujui oleh:** nisrina (pemilik project) · **Pemilik produk:** klipaa
**Status:** disetujui dan dicatat. Persetujuan dinyatakan langsung oleh pemilik project pada 20 Okt 2026: semua keputusan D-01 s/d D-13
disetujui tanpa perubahan. Berkas ini mencatat persetujuan itu; berkas ini tidak membuat keputusan baru dan tidak mengubah makna keputusan.
Nomor versi Dokumen Keputusan tetap v4.0 (pola amandemen C-01); Authorization Matrix naik ke v5.3 (baris #1-#61 tidak diubah).

> Catatan: persetujuan langsung yang tercatat berasal dari pemilik project (nisrina). Persetujuan klipaa (pemilik produk) atas panduan (D-06, D-13), edit sekolah (D-09), dan edit guru belum tercatat di berkas ini; tambahkan bila sudah diberikan.

## 1. Keputusan

Isi kolom "Keputusan final" disalin apa adanya dari sheet "Keputusan Pending" pada `Task_Board_ANAKTUMBUH_BE_Tambahan_Integrasi_FE_v1_3.xlsx`.
Kalimat "satu-satunya item yang masih terbuka" pada D-01 menggambarkan keadaan sebelum persetujuan ini.

| ID | Keputusan final | Task terdampak | Status |
|---|---|---|---|
| D-01 | Pemilik project menandatangani SATU file keputusan di repo (tanggal, tiap D-01..D-13, penyetuju) yang mengesahkan seluruh keputusan di sheet ini; bila ada pemilik produk terpisah, minta persetujuannya untuk panduan, edit sekolah, dan edit guru. Ini satu-satunya item yang masih terbuka | INTEG-DECISION-001, semua task fitur | DISETUJUI |
| D-02 | FINAL: 404 untuk endpoint tulis baru yang diakses lintas sekolah (id tidak dapat dienumerasi); 403 tetap untuk role tidak berhak | INTEG-TEACHER-001 (dan konsistensi INTEG-SCHOOL-001) | DISETUJUI |
| D-03 | FINAL: audit_logs WAJIB lewat AuditLogService untuk edit nama sekolah dan edit data guru; ada test yang membuktikan baris audit tercatat | INTEG-SCHOOL-001, INTEG-TEACHER-001 | DISETUJUI |
| D-04 | FINAL: TIDAK menambah rate limit baru untuk endpoint tulis INTEG. Limiter 'password-change' (10 request/menit per user, 429 rate_limit_exceeded, F-47) dibuat khusus operasi kredensial dan tidak diperluas. PATCH sekolah, PATCH guru, dan tulis panduan bukan operasi kredensial dan sudah dibatasi autentikasi JWT + role. Tinjau ulang hanya bila ada penyalahgunaan nyata | INTEG-SCHOOL-001, INTEG-TEACHER-001 | DISETUJUI |
| D-05 | FINAL: Matrix berhenti di #61, baris baru #62 Rekap, #63 Panduan (tulis), #64 Panduan (lihat), #65 Edit nama sekolah, #66 Edit nama/gender guru, #67 Profil siswa (rombel+foto), #68 Dashboard kepala sekolah, #69 QR status siswa. Versi Matrix naik ke v5.3 (baris #1-#61 tidak diubah). Pemilik memasukkan baris ke file Matrix (di luar repo); Task Board hanya merujuk | INTEG-DECISION-001, INTEG-DOC-001 | DISETUJUI |
| D-06 | FINAL: panduan kebiasaan BOLEH dikerjakan. Teks polos, maks 2.000 karakter, per (rombel x habit), guru dengan assignment aktif menulis, guru dan siswa rombel itu membaca. Amandemen Dokumen v4.0 §7 tetap diperlukan | INTEG-PANDUAN-001 | DISETUJUI |
| D-07 | FINAL: persentase terisi per hari (entri / siswa x 7), total poin per siswa, terisi per kebiasaan; Bermasyarakat satu kebiasaan; maksimal 31 hari, default 7; roster = assignment aktif sekarang | INTEG-REKAP-001 | DISETUJUI |
| D-08 | FINAL (keputusan #7): rombel {id,name} + foto via URL endpoint terproteksi (pola F-06); StudentPhotoPrivacyTest disesuaikan eksplisit (yang dilarang: path file/URL publik, bukan URL terproteksi) | INTEG-ME-001 | DISETUJUI |
| D-09 | FINAL: edit nama sekolah hanya Super Admin, field name saja; FE kepala sekolah menyembunyikan tombol edit | INTEG-SCHOOL-001 | DISETUJUI |
| D-10 | FINAL: acuan ukuran terburuk = 200 siswa per rombel (batas satu import, F-38) x 31 hari x 7 kebiasaan = maksimal 43.400 entri per permintaan. Tidak perlu cache dan tidak perlu index baru: DB Design menyatakan INDEX(student_id, habit_date) dan INDEX(habit_id, habit_date) sudah cukup untuk monitoring per rombel. Agregasi dilakukan di SQL (GROUP BY), bukan memuat semua entri ke memori. Angka ini asumsi batas atas, belum diukur di server | INTEG-REKAP-001 | DISETUJUI |
| D-11 | FINAL: respons sama untuk keduanya, 404 (anti-enumerasi, selaras D-02); rombel lain di sekolah yang sama = 403 (pola scope Teacher) | INTEG-REKAP-001, INTEG-TEST-001 | DISETUJUI |
| D-12 | FINAL: pelaksana tiap task fitur (DoD (e)). INTEG-TEST-001/002 hanya memverifikasi kelengkapan, menjalankan koleksi, dan menyimpan file hasil Runner di repo | Semua task fitur, INTEG-TEST-001/002 | DISETUJUI |
| D-13 | FINAL: content kosong atau null menghapus panduan (rombel x habit) tersebut dan hasilnya sama dengan belum ada panduan (FE kembali ke teks statis); rinci respons ditetapkan di addendum | INTEG-PANDUAN-001 | DISETUJUI |

## 2. Teks sumber yang terdampak (kutipan asli)

| Dokumen | Lokasi | Teks asli |
|---|---|---|
| Dokumen Keputusan v4.0 | §7 | "Teacher hanya monitor/view own active rombel." |
| Dokumen Keputusan v4.0 | §7 | "Headmaster tidak fill dan tidak monitor." |
| Dokumen Keputusan v4.0 | §7 | "Student hanya punya satu free-text input: note; optional; max 1000; tanpa minimum length." |
| Dokumen Keputusan v4.0 | §7 | "FE description adalah teks statis penjelasan habit, bukan input Student." |
| Dokumen Keputusan v4.0 | §9 | "Dashboard scope/current placement mengikuti assignment." |
| Authorization Matrix v5.2 | #33 (Habit, Monitor / View) | "Teacher monitor only; Headmaster tidak monitor." (Super Admin DENY, Headmaster DENY, Teacher ALLOW, Student ALLOW) |
| Authorization Matrix v5.2 | #9 (Student, View) | "Student hanya data sendiri; no classmates list. Pengecualian: entri ranking (rombel/angkatan, baris #39) menampilkan name Student lain." |
| Authorization Matrix v5.2 | #61 (Teacher Lifecycle, Mark Teacher Inactive) | "Headmaster menonaktifkan Teacher dalam sekolahnya sendiri; teachers.status active->inactive; seluruh teacher_class_assignments aktif milik Teacher ditutup (is_active=false) dalam transaksi yang sama, assignment lama tetap tersimpan sebagai histori; tidak ada reactivation." |
| Authorization Matrix v5.2 | Role Summary, Super Admin, kolom Explicitly Allowed | "Create School; Create Headmaster; Change Own Password; Reset Headmaster Password" |
| Authorization Matrix v5.2 | Role Summary, Headmaster, kolom Explicitly Allowed | "Create/import Teacher; master Rombel; Academic Year; Teacher Class Assignment; Reset Teacher Password" |
| Authorization Matrix v5.2 | Role Summary, Teacher, kolom Explicitly Allowed | "Student CRUD/import; Student Class Assignment own scope; monitor habit; ranking; inactive/graduated/bulk graduate; View Academic Year & Master Rombel (own scope); Reset Student Password" |
| Authorization Matrix v5.2 | Role Summary, Student, kolom Explicitly Allowed | "Own data; own habit; ranking; Teacher/Wali Kelas; QR or username/password login" |

Temuan pencarian: Dokumen v4.0 tidak memuat pernyataan eksplisit bahwa tidak ada Update School atau Update Teacher (teks hanya mencatat Create School,
Create Headmaster, Create/Import Teacher, dan Mark Teacher Inactive). Karena itu tidak ada kalimat lama yang diubah; perubahan dicatat pada blok amandemen
(bagian 3a). Matrix #1-#61 tidak memiliki baris Update School atau Update Teacher (tidak ada, bukan DENY eksplisit); #65 dan #66 menambahkannya.

## 3. Teks pengganti

### 3a. Dokumen Keputusan v4.0 (poin dan blok ditambahkan; tidak ada teks lama yang dihapus)

Berkas: `Dokumen_Keputusan_Perubahan_Task_Board_ANAKTUMBUH_v4_0_FINAL_AMANDEMEN-D01.docx` (tracked changes, sisipan saja).

- **§7 Habit & StudentHabit, poin baru 1:** "Teacher dengan assignment aktif di rombel boleh menulis panduan pengisian per (rombel x habit): teks polos, maksimal 2.000 karakter, tanpa HTML. Teacher dan Student rombel tersebut dapat membaca; Headmaster tidak. Teks kosong menghapus panduan. Panduan bukan input Student dan tidak mengubah aturan note; bila belum ada panduan, FE menampilkan teks statis penjelasan habit."
- **§7 Habit & StudentHabit, poin baru 2:** "Teacher dapat melihat rekap kelas own active rombel (monitor/view saja): persentase terisi per hari, total poin per siswa, dan jumlah terisi per kebiasaan; Bermasyarakat dihitung satu kebiasaan; maksimal 31 hari. Headmaster tetap tidak monitor."
- **Blok akhir dokumen (setelah "Amandemen 9 Okt 2026 ... (C-01)"):** "Amandemen 20 Okt 2026 (INTEG-DECISION-001): (a) panduan kebiasaan per rombel x habit, teks polos maks 2.000 karakter (§7); (b) rekap kelas Teacher (§7); (c) edit nama sekolah hanya Super Admin, field name saja; (d) edit data guru oleh Headmaster own school, hanya name dan gender, NIP tidak boleh diubah, guru inactive tidak boleh diedit; (e) field rombel aktif dan foto terproteksi pada profil/dashboard Student; (f) field total_siswa, total_guru, total_rombel pada dashboard Headmaster; (g) field qr_regenerated_at pada daftar Student, hanya Teacher. Aksi sensitif (edit sekolah, edit guru) wajib dicatat di audit_logs; endpoint tulis lintas sekolah dijawab 404. Nomor versi tidak berubah."

### 3b. Authorization Matrix v5.3 (`Authorization_Matrix_ANAKTUMBUH_v5_3_FINAL.xlsx`)

Sheet "Authorization Matrix": baris #62-#69 ditambahkan di bawah #61 (status FINAL semua). Baris #1-#61 tidak diubah.

| No | Resource / Fitur | Action | Super Admin | Headmaster | Teacher | Student | Scope / Ownership | Status | Catatan / Rule |
|---|---|---|---|---|---|---|---|---|---|
| 62 | Habit Recap (Class) | View Class Recap | DENY | DENY | ALLOW | DENY | Teacher own active rombel | FINAL | Rekap v1: persentase terisi per hari, total poin per siswa, terisi per kebiasaan; Bermasyarakat = 1 kebiasaan; maks 31 hari, default 7; roster = assignment aktif saat ini. Rombel sekolah lain/tidak ada = 404; rombel lain di sekolah yang sama = 403. |
| 63 | Habit Guide | Write Guide (rombel x habit) | DENY | DENY | ALLOW | DENY | Teacher own active rombel | FINAL | Teks polos maks 2.000 karakter, tanpa HTML; teks kosong menghapus panduan. |
| 64 | Habit Guide | View Guide | DENY | DENY | ALLOW | ALLOW | Teacher own active rombel; Student rombel sendiri | FINAL | Baca saja; Headmaster DENY. |
| 65 | School | Update School Name | ALLOW | DENY | DENY | DENY | Global (Super Admin) | FINAL | Hanya field name; Headmaster tidak mendapat GET/PATCH sekolah; wajib audit_logs. |
| 66 | Teacher | Update Teacher (name, gender) | DENY | ALLOW | DENY | DENY | Own school | FINAL | Hanya name dan gender; NIP/username/status/school_id/password tidak boleh diubah; guru inactive tidak boleh diedit (lihat #61); wajib audit_logs; guru sekolah lain = 404. |
| 67 | Student Profile | View Active Rombel + Photo | DENY | DENY | DENY | ALLOW | Student own | FINAL | rombel {id,name} dari assignment aktif pada Academic Year aktif; foto lewat URL endpoint terproteksi, bukan path/URL publik. |
| 68 | Dashboard | Headmaster School Totals | DENY | ALLOW | DENY | DENY | Own school | FINAL | total_siswa (assignment aktif di rombel TA aktif), total_guru (status active), total_rombel (TA aktif). |
| 69 | Student | View QR Regenerated At | DENY | DENY | ALLOW | DENY | Teacher own active rombel | FINAL | Field qr_regenerated_at pada GET /students (list), hanya Teacher. |

Sheet "Role Summary", kolom Explicitly Allowed (kolom Explicitly Not Allowed tidak diubah):

| Role | Teks baru |
|---|---|
| Super Admin | Create School; Create Headmaster; Change Own Password; Reset Headmaster Password; Update School Name (field name saja) |
| Headmaster | Create/import Teacher; master Rombel; Academic Year; Teacher Class Assignment; Reset Teacher Password; Update Teacher name/gender (own school) |
| Teacher | Student CRUD/import; Student Class Assignment own scope; monitor habit; ranking; inactive/graduated/bulk graduate; View Academic Year & Master Rombel (own scope); Reset Student Password; View Class Recap; Write/View Habit Guide (rombel x habit); View qr_regenerated_at |
| Student | Own data; own habit; ranking; Teacher/Wali Kelas; QR or username/password login; View Habit Guide (rombel sendiri); View active rombel + photo (own) |

Sheet "Business Rules": baris No 23-26 ditambahkan (panduan teks polos maks 2.000 karakter; NIP guru tidak dapat diubah; aksi sensitif dicatat di audit_logs;
endpoint tulis baru lintas sekolah = 404, 403 tetap untuk role tidak berhak).

Label versi: tidak ditemukan penyebutan "v5.2" di isi sheet manapun, sehingga tidak ada label yang diganti; versi v5.3 tercantum pada nama file.

## 4. Dampak

- Task fitur INTEG berubah dari NEEDS DECISION ke TODO: INTEG-QR-001, INTEG-REKAP-001, INTEG-PANDUAN-001, INTEG-ME-001, INTEG-DASHHM-001, INTEG-SCHOOL-001, INTEG-TEACHER-001.
- INTEG-TEST-001, INTEG-TEST-002, dan INTEG-DOC-001 menjadi TODO saat task fitur terkait DONE (aturan sheet "Urutan & Gate"; TEST-001 v23 juga harus DONE sebelum INTEG-TEST-001/002 dimulai).
- Perubahan status di file Task Board INTEG dilakukan pemilik (sheet "Urutan & Gate", langkah 2); berkas ini tidak mengubah file itu.
- Tidak ada perubahan kode. Addendum API Contract, errata DB Design, dan test untuk kemampuan baru belum dibuat (dikerjakan oleh task fitur dan INTEG-DOC-001).
