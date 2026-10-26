# SoT Amendment — Keputusan atas temuan penggabungan INTEG-DOC-001 (D-20 sampai D-24)

**Tanggal:** 23 Okt 2026 · **Diputuskan oleh:** pemilik ANAKTUMBUH.ID · **Sumber temuan:** `docs/team-log/INTEG-DOC-001.md` (bagian "Temuan langkah 4")
**Status:** diputuskan dan dicatat; **disetujui pemilik, 23 Okt 2026**. Dokumen Keputusan v4.0 berada di luar repo, sehingga bagian 2c hanya menyiapkan teks amandemennya; pemilik yang menerapkannya. API Contract v1.3 (`API_Contract_ANAKTUMBUH_v1_3_FINAL.md`) sudah memuat D-21, D-22, dan D-24.
Berkas ini tidak menyentuh `SoT-Amendment-INTEG-D01-D13.md` (sudah ditandatangani) dan tidak mengubah Authorization Matrix, kode, maupun test.

## 1. Keputusan

| # | Temuan | Keputusan | Dampak | Status |
|---|---|---|---|---|
| D-20 | Addendum Habit-GET bagian 4 menyatakan "daily lock" dicabut, sedangkan Dokumen v4.0 masih menyebut "daily lock" dan "past-date rejection". | Kode dan Contract sudah benar: tanggal entri ditentukan server (Asia/Jakarta), tanpa lock harian, tanpa PATCH. Yang usang adalah redaksi "daily lock / past-date rejection" di Dokumen v4.0; diselesaikan lewat amandemen dokumen saja. Matrix #35 **tidak diubah** (kalimat "past dates view-only" tetap benar). | Tanpa perubahan kode dan Contract. Amandemen Dokumen v4.0 (bagian 2c), diterapkan pemilik. | Disetujui pemilik, 23 Okt 2026 |
| D-21 | Level-Ranking bagian 6: nama pada entri ranking bertentangan dengan kalimat "no classmates list" di Contract §4 dan §17. | Terapkan frasa siap tempel di `SoT-Amendment-Ranking-Name.md` bagian 3 ke bagian 4 dan bagian 17 Contract v1.3. Ini pengecualian sah dari aturan "bagian v1.2 harus identik". | Contract v1.3 §4 dan §17 (bagian 3). | Disetujui pemilik, 23 Okt 2026 |
| D-22 | Level-Ranking bagian 8 ("`gamification_level` pada dashboard Student belum diintegrasikan") bertentangan dengan Errata Stage 5 F-40. | Errata F-40 berlaku: `gamification_level` sudah ada di dashboard Student (terbukti di `DashboardService`). Pernyataan "belum diintegrasikan" digantikan. | Contract v1.3: satu baris catatan di blok Level-Ranking (§24); teks addendum tetap disalin apa adanya. | Disetujui pemilik, 23 Okt 2026 |
| D-23 | Board menyebut koreksi "§22/§25". | Rujukan itu salah ketik. Bagian 22 (Bermasyarakat) tidak perlu koreksi dan tidak ada yang ditulis di Contract. Teks board diperbaiki pemilik di file board. | Tidak ada perubahan di repo. | Disetujui pemilik, 23 Okt 2026 |
| D-24 | Contract v1.2 masih merujuk Authorization Matrix v5.2 dan Traceability Matrix (§32) belum memuat #62 sampai #69. | Rujukan Authorization Matrix v5.2 di v1.3 diperbarui ke v5.3, dan §32 memuat baris #62 sampai #69 (isi dari Matrix v5.3 dan Bagian 17 `docs/authorization-matrix.md`, tanpa rule, endpoint, atau field baru). | Contract v1.3 §1, §2, §6, §32, §33 (bagian 6). | Disetujui pemilik, 23 Okt 2026 |

## 2. D-20 — redaksi "daily lock"

### 2a. Teks sumber yang terdampak (kutipan asli)

| Dokumen | Lokasi | Teks asli |
|---|---|---|
| Dokumen Keputusan v4.0 | §2 (Status Perubahan Task Board), baris Habit / StudentHabit | `Student-only write; 7/day; Asia/Jakarta; daily lock; note only; description DB drop.` |
| Dokumen Keputusan v4.0 | §13 (Global Acceptance Criteria) | `Habit 7/day; unique Student+Habit+Date; Asia/Jakarta; daily lock.` |
| Dokumen Keputusan v4.0 | §14 (Testing Minimum) | `Asia/Jakarta daily lock dan past-date rejection.` |
| Authorization Matrix v5.3 | #35 (Habit, Daily lock / Historical view), kolom catatan | `Unique student+habit+date; Asia/Jakarta; past dates view-only.` (**tidak diubah**) |
| API Contract v1.2 | §21 (baris v1.2 793 dan 799) | `Tanggal sebelum hari ini: **view-only**, tidak ada backfill/edit (PATCH ditolak untuk entry ber-habit_date < hari ini server, atau setelah daily lock).` dan `422 — habit_already_locked (edit setelah daily lock atau tanggal lampau).` |
| API Contract v1.2 | §9 (Error Contract), baris status 422 (baris v1.2 233) | `... contoh: Bermasyarakat “Kurang bermasyarakat” dikirim bersama indikator lain, habit note > 1000 karakter, submit habit setelah daily lock.` |
| API Contract v1.2 | §33 (Acceptance Criteria), Final Audit Checklist, butir HABIT (baris v1.2 1153) | `... - [x] Asia/Jakarta - [x] Daily lock - [x] Note optional max 1000 ...` (butir `[x] Daily lock`) |
| Addendum Habit-GET | bagian 4 | `Referensi habit_already_locked / "daily lock" pada teks API Contract v1.2 §21 versi lama adalah requirement yang sudah direkonsiliasi (dicabut), bukan celah yang perlu ditutup dengan endpoint PATCH baru.` |

Catatan: keempat referensi "daily lock" di Contract v1.2 (§9, §21 dua baris, §33) tetap ada di Contract v1.3 karena aturan "bagian yang tidak dikoreksi harus identik dengan v1.2". Dua baris §21 dikoreksi oleh blok Habit-GET bagian 4 di §21 yang disalin apa adanya. Referensi di §9 (contoh 422) dan §33 (butir checklist HABIT) tidak punya blok koreksi; keduanya bersifat historis dan digantikan oleh D-20, dan hal itu dinyatakan di paragraf "Catatan pembaca v1.3" butir (e) pada Contract v1.3. Teks v1.2 pada keempat tempat itu tidak diedit.

### 2b. Bukti kode

- `app/Services/Student/StudentHabitService.php`, method `today()`: `return Carbon::now('Asia/Jakarta')->toDateString();`, dengan komentar bahwa tanggal ditentukan server dan tidak menerima `habit_date` dari client.
- Pencarian `habit_already_locked`, `daily lock`, dan `cutoff` di `app/` dan `routes/` tidak menemukan logika lock apa pun. Satu-satunya penyebutan di service adalah komentar Matrix #35 ("past dates view-only = tanggal lampau tetap terbaca").
- `php artisan route:list`: route entri habit hanya `GET /api/v1/habits/entries`, `GET /api/v1/habits/entries/{id}`, `POST /api/v1/habits/{habit_id}/entries`, `GET /api/v1/bermasyarakat/entries`, dan `POST /api/v1/bermasyarakat/entries`. Tidak ada route PATCH, PUT, atau DELETE untuk entri habit maupun Bermasyarakat.

### 2c. Teks siap tempel untuk Dokumen Keputusan v4.0 (nomor versi tetap 4.0)

Pola mengikuti amandemen 9 Okt 2026 dan 20 Okt 2026 (INTEG-DECISION-001). Pemilik menerapkan lima perubahan berikut; tidak ada perubahan lain pada Dokumen v4.0.

1. **§2, baris Habit / StudentHabit.** Ganti `daily lock` dengan teks di bawah, sehingga baris menjadi:
   `Student-only write; 7/day; Asia/Jakarta (tanggal ditentukan server); tanpa lock harian; entri tidak dapat diubah (tanpa PATCH/DELETE); note only; description DB drop.`
2. **§13.** Ganti kalimat menjadi:
   `Habit 7/day; unique Student+Habit+Date; Asia/Jakarta (tanggal ditentukan server); tanpa lock harian.`
3. **§14.** Ganti kalimat `Asia/Jakarta daily lock dan past-date rejection.` menjadi:
   `Tanggal entri habit ditentukan server (Asia/Jakarta); tidak ada lock harian dan tidak ada PATCH/DELETE pada entri habit.`
4. **§18, baris amandemen baru** setelah baris amandemen 20 Okt 2026:
   `Amandemen 23 Okt 2026 (D-20): redaksi "daily lock" dan "past-date rejection" pada §2 (Habit / StudentHabit), §13, dan §14 diganti: tanggal entri habit ditentukan server (Asia/Jakarta), tidak ada lock harian, tidak ada PATCH/DELETE pada entri habit. Perilaku kode dan API Contract tidak berubah; Authorization Matrix #35 tidak berubah. Nomor versi tidak berubah.`
5. Matrix #35 dan kalimat "past dates view-only" **tidak diubah**.

## 3. D-21 — hasil di API Contract v1.3

Frasa `kecuali entri ranking (lihat §24)` disisipkan persis setelah frasa classmates list, sesuai instruksi `SoT-Amendment-Ranking-Name.md` bagian 3. Tidak ada redaksi lain yang ditambahkan.

| Bagian | v1.2 | v1.3 |
|---|---|---|
| §4 (baris Student) | `... (no classmates list, Dokumen v4.0 §9).` | `... (no classmates list, kecuali entri ranking (lihat §24), Dokumen v4.0 §9).` |
| §17 (baris `GET /api/v1/students`) | `... **OWN only**, tidak ada classmates list (Matrix #9, Dokumen v4.0 §9).` | `... **OWN only**, tidak ada classmates list, kecuali entri ranking (lihat §24) (Matrix #9, Dokumen v4.0 §9).` |

## 4. D-22 — hasil di API Contract v1.3

- Dasar: Errata Stage 5 F-40 (di Contract v1.3, blok "Gamification dan Dashboard" pada §23). Bukti kode: `DashboardService` menyertakan `'gamification_level' => $this->levels->levelForPoin((int) $student->poin)` pada dashboard Student, dihitung real-time dari `students.poin`.
- Hasil: satu baris catatan di akhir bagian 8 blok Level-Ranking (§24): `Catatan v1.3 (D-22): gamification_level pada dashboard Student sudah ditambahkan oleh Errata Stage 5 F-40 dan berlaku; pernyataan 'belum diintegrasikan' di atas digantikan.` Teks bagian 8 addendum tetap disalin apa adanya.

## 5. D-23 — rujukan "§22/§25" di board

Di Contract v1.2, §22 adalah Bermasyarakat API dan §25 Dashboard API. Tidak ada addendum yang mengoreksi §22, dan v1.3 tidak memuat koreksi §22. Keputusan: salah ketik di board; tidak ada yang ditulis di Contract. Perbaikan teks dilakukan pemilik di file board (di luar repo).

## 6. D-24 — rujukan Matrix v5.3 dan Traceability Matrix

Perubahan di Contract v1.3 (selain baris tambahan §32, tidak ada perubahan lain):

- **§1:** bullet baseline Authorization Matrix menjadi `v5.3 FINAL` dengan tambahan "Naik dari v5.2 → v5.3 pada v1.3: delapan baris baru #62–#69, task INTEG"; bullet Dokumen Keputusan v4.0 ditambah "Diamandemen 20 Okt 2026 (INTEG-DECISION-001) dan 23 Okt 2026 (D-20); nomor versi tetap 4.0".
- **§2:** baris Authorization Matrix menjadi `v5.3 FINAL`, ditambah "v5.3 menambah baris #62–#69, task INTEG".
- **§6:** kalimat Role check merujuk Authorization Matrix v5.3.
- **§33:** tiga kalimat penutup (v1.2 baris 1119, 1125, 1169) merujuk Matrix v5.3.
- **§32:** delapan baris baru #62 sampai #69 pada tabel yang ada (kolom: Business Rule (Dokumen v4.0), Matrix Action, API Endpoint, Validation, Acceptance Criteria), memakai isi Matrix v5.3 dan Bagian 17 `docs/authorization-matrix.md`; kolom Business Rule merujuk butir (a) sampai (g) amandemen 20 Okt 2026.
- Penyebutan "v5.2" yang tersisa di v1.3 bersifat historis dan tidak diubah: baris "Dokumen" di §1 (menjelaskan sinkronisasi v1.1 pada 20 Sep 2026), bullet "Naik dari v5.1 → v5.2", Addendum Log v1.1 sampai v1.2.1, dan teks Teacher Lifecycle (Matrix v5.2 #61).

## 7. Dampak

Tidak ada perubahan kode, test, Authorization Matrix, atau skema DB. Dokumen Keputusan v4.0 hanya disiapkan teks amandemennya (bagian 2c). Perubahan pada Contract v1.3 tercatat di `docs/team-log/INTEG-DOC-001.md`.
