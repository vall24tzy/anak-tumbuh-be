# API Contract v1.2 — Addendum: Level & Ranking

Status: Keputusan Tim (28 Sep 2026, revisi 3), BUKAN kutipan literal API Contract v1.2 §23/§24 —
dicatat di sini justru karena menyimpang/mempersempit teks §23/§24 pada beberapa poin, dijelaskan
alasannya masing-masing. Lihat juga `docs/db-gap-analysis.md` NOTE-11 untuk narasi lengkap.

## 1. `GET /api/v1/gamification/me` dibatasi Student saja

§23 mencantumkan Allowed Role "Super Admin, Headmaster, Teacher, Student" untuk endpoint ini, dengan
scope "SA/HM/Teacher: role scope. Student: OWN". Endpoint ini diimplementasikan **hanya untuk Student**
(`role:student`) — SA/HM/Teacher mendapat 403. Rekonsiliasi: hak SA/HM/Teacher untuk melihat poin/level
Student (Matrix #38) sudah dipenuhi penuh lewat `GET /api/v1/students/{id}/gamification`, yang memang
scope-nya per-role (SA global, HM own school, Teacher own active rombel). "me" secara semantik hanya
bermakna untuk role yang punya poin sendiri, yaitu Student.

## 2. Response `GET /rankings/rombel` dan `GET /rankings/angkatan`

Field per baris: `student_id` (integer), `name` (string — students.fullname), `rombel` (string — nama
rombel dari student_class_assignments aktif), `poin` (integer), `level` (integer).

**Field bernama `level`, bukan `gamification_level`** seperti istilah di §23 — keputusan tim, bukan
konsistensi literal. `level` dihitung dari mekanisme gamification yang sama persis (`LevelService`),
sekadar nama field response yang berbeda untuk endpoint ini.

Sorting: `poin` DESC, `name` ASC, lalu `id` ASC. Dua kriteria pertama business-facing (ranking yang
masuk akal bagi pengguna); `id` ASC murni tie-break teknis supaya pagination deterministik bila poin
dan nama sama persis — bukan business rule baru.

Pagination mengikuti konvensi umum API Contract §7 (identik pola `GET /habits/entries`): query `page`
(default 1), `per_page` (default 20, max 100), response membawa `meta: {page, per_page, total,
total_pages}`.

Query param yang dibaca **hanya** `rombel_id` (khusus `/rankings/rombel`) dan `academic_year_id`
(keduanya) — sesuai daftar di §24. Tidak ada `student_id`, `sort`, `order`, atau param lain; bila
dikirim, diabaikan (tidak dibaca, bukan ditolak).

Hasil kosong (caller belum/tidak punya konteks kelas yang valid) tetap mengembalikan `200` dengan
`data: []` dan `meta` yang benar (`total: 0`, dst.) — bukan error.

## 3. Ranking dibatasi Academic Year AKTIF sekolah caller

§24 mencantumkan `academic_year_id` sebagai query parameter opsional tanpa membatasi nilainya ke tahun
aktif. Keputusan tim **mempersempit** ini: `academic_year_id` yang dikirim harus sama dengan Academic
Year yang sedang aktif di sekolah caller — nilai lain (tahun lampau, tahun lain, atau tahun milik
sekolah lain) ditolak `403` untuk **semua role** (Teacher maupun Student). Dasar: Dokumen v4.0 §9,
"Ranking memakai active/current Class Assignment context" — dibaca sebagai "ranking historis bukan
fitur", konsisten dengan tidak adanya migration/skema untuk itu.

**Urutan pengecekan (wajib):** `academic_year_id` divalidasi terhadap tahun aktif sekolah caller
TERLEBIH DAHULU (berdasarkan `teachers.school_id` / `students.school_id` caller, independen dari
apakah caller sedang punya rombel aktif atau tidak) — baru setelah itu rombel caller di-resolve, baru
`rombel_id` (bila dikirim) divalidasi terhadap rombel itu. Hasil-kosong (200, `data: []`) hanya
diputuskan SETELAH kedua validasi parameter di atas lolos. Ini mencegah kasus Teacher tanpa active
assignment salah dianggap "boleh, hasilnya kosong" padahal `academic_year_id` yang dikirim sebenarnya
di luar scope-nya.

**Dua asumsi eksplisit** (tim boleh membalik keputusan ini kalau ada kebutuhan baru):
- Sekolah yang sedang tidak punya Academic Year aktif sama sekali, tapi `academic_year_id` tetap
  dikirim di request → `403` (tidak ada "tahun aktif" yang bisa jadi pembanding). Tanpa parameter sama
  sekali → `200` dengan hasil kosong.
- Caller yang belum/tidak punya konteks kelas (Teacher tanpa active Teacher Class Assignment, atau
  Student tanpa active Student Class Assignment di tahun aktif) tetap mendapat `403` bila mengirim
  `rombel_id` apa pun — karena tidak ada rombel dalam scope-nya untuk dicocokkan.

## 4. `403` (ranking) vs `404` (`/students/{id}/gamification`)

Konsisten pola yang sudah berlaku di endpoint lain proyek ini (API Contract §26): operasi tulis atau
operasi dengan scope eksplisit dari query/body → `403` untuk kejelasan penyebab (dipakai di seluruh
endpoint ranking). Operasi baca by-id dengan risiko resource-existence-leak → `404`
(`GET /students/{id}/gamification` bila Student di luar scope caller).

## 5. `is_active` (§24, teks konseptual) vs `status` (skema fisik)

§24 menuliskan query konseptual `student_class_assignments.is_active = true`. Skema fisik final
(DB Design v1.5 §5.9) memakai kolom `status` bertipe enum (`'active'`/`'ended'`) — tidak ada kolom
`is_active` pada tabel ini. Implementasi memakai `status = 'active'`. Ini rekonsiliasi penamaan
dokumen, bukan struktur/field baru.

## 6. Menampilkan `name` — konflik sadar dengan Dokumen v4.0 §9 / Matrix #9 / Role Summary Student

Dokumen v4.0 §9 menyatakan Student "tidak membutuhkan classmates list". Authorization Matrix baris #9
mencantumkan eksplisit "no classmates list" untuk Student. Role Summary Student mencantumkan
"Other students" pada kolom yang secara struktur berarti DENIED.

Menampilkan `name` pada tiap baris ranking **bertentangan secara harfiah** dengan ketiganya. Dokumen
v4.0 dan Authorization Matrix berada **di atas** API Contract pada hierarki source-of-truth proyek ini
— sehingga ini bukan sekadar "menambah field yang tidak dirinci API Contract" (seperti kasus `name`
biasa), melainkan **penyimpangan sadar terhadap SoT yang lebih tinggi**, diputuskan eksplisit oleh tim
karena ranking tanpa nama tidak bermakna fungsional bagi pengguna (angka poin dan level tanpa identitas
pemiliknya tidak menyampaikan apa pun).

**Tindak lanjut wajib (di luar scope task ini):** Authorization Matrix baris #9 dan Dokumen v4.0 §9
perlu diamandemen resmi oleh pemilik dokumen tersebut untuk mengecualikan konteks ranking secara
eksplisit ("no classmates list, KECUALI nama pada entri ranking rombel/angkatan sendiri") — supaya
tidak ada dua SoT yang saling bertentangan secara permanen. Sampai amandemen itu terjadi, perilaku
`name` di ranking berjalan sesuai keputusan tim ini, dicatat transparan di sini dan di NOTE-11.

## 7. Dependency "hingga TEST-001/TEST-002"

Klarifikasi: bukan syarat pemblokir. Dibaca sebagai keterangan jadwal pada Task Board ("dijadwalkan
terakhir hanya karena butuh data poin asli"), bukan ketergantungan teknis pada penyelesaian TEST-001
atau TEST-002 terlebih dahulu.

## 8. `gamification_level` di Dashboard Student

Belum diintegrasikan — tetap scope **MERGE-001** sesuai catatan Dependency DASHBOARD-001 di Task Board
("Field gamification_level memakai LevelService dari LEVEL-RANKING-001, diintegrasikan saat
MERGE-001"). `LevelService::levelForPoin()` sudah tersedia dan siap dipakai; `DashboardService` sengaja
tidak disentuh oleh task ini.
