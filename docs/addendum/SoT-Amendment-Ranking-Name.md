# SoT Amendment — Nama Student pada Entri Ranking (menutup C-01 MERGE-001)

**Tanggal:** 10 Okt 2026 · **Diputuskan oleh:** tim backend ANAKTUMBUH.ID (Orang 1 dan Orang 2)
**Status:** diputuskan dan dicatat. Salinan pembaruan Dokumen v4.0 (docx) dan Authorization Matrix v5.2 (xlsx)
yang diunggah 9 Okt 2026 sudah memuat pengecualian (redaksi aktual di bagian 3b), tetapi belum menggantikan salinan
lama di project/repo; API Contract §4 dan §17 belum diubah (teks siap tempel di bagian 3 masih berlaku untuk Contract).

## 1. Keputusan

Entri ranking pada `GET /api/v1/rankings/rombel` dan `GET /api/v1/rankings/angkatan` menampilkan
`student_id`, `name`, `rombel`, `poin`, `level`. Nama Student adalah bagian dari representasi ranking,
bukan "classmates list". Keputusan ini sudah berlaku di kode sejak LEVEL-RANKING-001
(`API-Contract-v1.2-Addendum-Level-Ranking.md` §2 dan §6, `db-gap-analysis.md` NOTE-11).

## 2. Teks sumber yang terdampak (kutipan asli)

| Dokumen | Lokasi | Teks asli |
|---|---|---|
| Dokumen Keputusan v4.0 | §9 | "Student dapat melihat Teacher/Wali Kelas; tidak membutuhkan classmates list." |
| Authorization Matrix v5.2 | #9 (Student — View), kolom catatan | "Student hanya data sendiri; no classmates list." |
| Authorization Matrix v5.2 | Role Summary, Student, kolom tidak diizinkan | "Other students; previous habit; placement; own password change; own password reset (self-service)" |
| API Contract v1.2 | §4 (Roles & Scope, Student) | "Tidak ada akses ke data Student lain (no classmates list, Dokumen v4.0 §9)." |
| API Contract v1.2 | §17 (`GET /students`, scope Student) | "Student: OWN only, tidak ada classmates list (Matrix #9, Dokumen v4.0 §9)" |

Teks pembanding yang sudah ada di SoT dan menunjukkan arah yang sama:

- Dokumen v4.0 §9: "Ranking rombel dan angkatan tersedia untuk Student dan Teacher."
- Matrix #39 (Gamification — View Ranking Rombel / Angkatan): Student ALLOW, scope "Teacher active scope; Student cohort".
- Matrix Role Summary, Student, kolom diizinkan: memuat "ranking".
- API Contract §24: ranking untuk Teacher dan Student dalam konteks rombel dan angkatan.

Artinya SoT sendiri sudah memberi Student akses ke ranking atas cohort-nya. Ranking tanpa identitas
tidak bermakna bagi pengguna, sehingga pengecualian ini menyelaraskan dokumen, bukan menambah hak baru.

## 3. Teks pengganti (siap tempel saat baseline di-re-issue)

- **Dokumen v4.0 §9:** "Student dapat melihat Teacher/Wali Kelas; tidak membutuhkan classmates list di luar fitur
  ranking. Entri ranking rombel dan angkatan menampilkan nama Student sebagai bagian dari representasi ranking."
- **Matrix #9, catatan:** "Student hanya data sendiri; no classmates list (pengecualian: entri ranking, lihat #39)."
- **Matrix #39, catatan:** "Ranking available Student/Teacher; HM no special ranking. Entri memuat student_id, name, rombel, poin, level."
- **Matrix Role Summary, Student, kolom tidak diizinkan:** "Other students (kecuali entri ranking rombel/angkatan)".
- **API Contract §4 dan §17:** tambahkan "kecuali entri ranking (lihat §24)" pada kalimat "no classmates list".

## 3b. Redaksi yang sudah ada di salinan pembaruan (9 Okt 2026)

- **Dokumen v4.0 §9** (paragraf baru setelah "tidak membutuhkan classmates list"): "Pengecualian: entri ranking rombel dan angkatan menampilkan name Student lain (field: student_id, name, rombel, poin, level); ini bukan classmates list dan tidak membuka data Student lain di luar entri ranking."
- **Dokumen v4.0 §18** (baris penutup): "Amandemen 9 Okt 2026: pengecualian name pada entri ranking (C-01). Nomor versi tidak berubah."
- **Matrix #9, catatan:** "Student hanya data sendiri; no classmates list. Pengecualian: entri ranking (rombel/angkatan, baris #39) menampilkan name Student lain."
- **Matrix Role Summary, Student, kolom tidak diizinkan:** "Other students (kecuali name pada entri ranking); …".
- **Tidak berubah di salinan pembaruan:** catatan Matrix #39 dan API Contract §4/§17.
- Redaksi bagian 3 (usulan) dan 3b (aktual) tidak identik; yang dipakai sebagai SoT adalah 3b setelah salinan itu menggantikan file lama. Nomor versi tidak naik dan Matrix tidak memiliki catatan revisi: pemilik memutuskan perlu-tidaknya changelog.

## 4. Batas pengecualian (tetap berlaku)

- Hanya dua endpoint ranking di atas. Field yang boleh tampil: `student_id`, `name`, `rombel`, `poin`, `level`.
  Tidak ada `nisn`, tidak ada objek Student bersarang, tidak ada field identitas lain.
- Student tetap tidak punya daftar teman di tempat lain: `GET /students` dan `GET /student-class-assignments`
  untuk Student hanya data sendiri; `GET /dashboard/student` hanya memuat wali kelas; gamification hanya milik sendiri.
- Ranking hanya untuk Academic Year aktif (`Addendum-Level-Ranking` §3). Headmaster dan Super Admin tetap 403 pada ranking.

## 5. Dampak

Tidak ada perubahan kode. Butir C-01 pada `docs/testing/MERGE-001-sot-register.md` ditutup. Re-issue
file baseline (docx/xlsx) adalah pekerjaan administratif terpisah dan tidak menghalangi merge.
