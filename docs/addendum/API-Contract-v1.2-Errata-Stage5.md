# API Contract v1.2 — Errata Stage 5 (penyelarasan dengan implementasi final Stage 1–4 + F-53)

Tanggal: 5 Okt 2026. Basis: `main` di atas `efbf95b` (415 passed / 2047 assertions).
Sifat dokumen: **errata**, bukan Contract v1.3 dan bukan business rule baru. Kode, test, dan migration
TIDAK diubah oleh Stage 5. API Contract v1.2 FINAL (`API_Contract_ANAKTUMBUH_v1_2_FINAL.docx`) tidak
disimpan di repo ini, jadi teks aslinya tidak diedit; setiap bagian di bawah memuat **teks v1.2 yang
digantikan (dipertahankan sebagai histori)** lalu **blok revisi** dengan rujukan commit dan finding ID.
Pola ini sama dengan revisi F-49 di `docs/authorization-matrix.md`. Bila ada konflik antara teks v1.2
dan blok revisi di sini, **blok revisi menang** untuk bagian yang disebut.

Sumber keputusan: `MASTER_AUDIT_REMEDIATION_MATRIX_v3_1_STAGE0_STATUS.md` (disingkat Matrix) dan
keputusan pemilik project yang tidak ada di Matrix (ditandai **[Pemilik]**: F-38, F-53, status endpoint
import, F-14, catatan bisnis F-20).

Peta commit: F-01 `401eebc` · F-02 `4987936` · F-05 `4796b7a` · F-13 `93943d2` · F-14/F-24/F-29 `bea89e0`,
`4189659`, `2e1afb1` · F-23/F-30 `2e1afb1` · F-32 `5696c4b` · F-37 `205aa18` · F-40 `3f65862` ·
F-45/F-47 `79086c5` · F-46 `33d444e` · F-06/F-07/F-08 `d486910`, `ed274ac` · F-49 `2c05904` (menggantikan
`e58c6e1`) · Stage 3 (F-03/F-04/F-09/F-11/F-16/F-17/F-18/F-19/F-20/F-21) `ccfce6d` · Stage 4A
(F-31/F-33/F-34/F-35/F-36/F-41/F-50) `9e1a31c` · F-38 `cea2bcd` · F-15/F-10 `941b0a1` · F-53 `2eec896`.

---

## §7 Common API Conventions — Pagination (F-15, F-10; `941b0a1`)

**Teks v1.2 (dipertahankan):** "Query `page` (default 1), `per_page` (default 20, max 100). Response
membawa objek `meta`: { page, per_page, total, total_pages }." Konvensi ini tidak punya contoh dan belum
diterapkan di semua endpoint list.

**Revisi Stage 5.** Konvensi di atas kini **diterapkan di enam endpoint** dengan satu helper bersama
(`App\Traits\PaginatesResults` + `App\Http\Requests\Common\PaginationRequest`):

| Endpoint | Cakupan `total` (dihitung SETELAH scope, bukan jumlah global) |
|---|---|
| `GET /schools` | seluruh School (Super Admin) |
| `GET /headmasters` | seluruh Headmaster (Super Admin) |
| `GET /teachers` | Teacher di sekolah Headmaster |
| `GET /academic-years` | Academic Year di sekolah user |
| `GET /rombels` | Rombel yang terlihat (HM: sekolahnya; Teacher: lihat §14 F-21) |
| `GET /teacher-class-assignments` | HM: sekolahnya; Teacher: miliknya |

Contoh `GET /api/v1/teachers?page=2&per_page=10`:

```json
{
  "data": [ { "id": 11, "...": "..." } ],
  "meta": { "page": 2, "per_page": 10, "total": 25, "total_pages": 3 }
}
```

Perilaku yang mengikat:
- Default `page=1`, `per_page=20`. Urutan **`id` ASC** (deterministik; halaman tidak tumpang tindih).
- `per_page` > 100, `per_page` < 1, `page` < 1, atau bukan integer → **422 `validation_failed`** (bukan
  dipotong diam-diam). Catatan: Matrix hanya meminta perilaku >100 "seragam dan terdokumentasi"; memilih
  422 adalah pilihan implementasi yang sama dengan ranking dan habit, kini dicatat di sini.
- Halaman di luar rentang (mis. `page=999`): **200**, `data: []`, `meta` valid (`page` = nilai yang
  diminta, `total_pages` minimal 1). Daftar kosong: `total: 0`, `total_pages: 1`.
- Belum didukung: filter (`?academic_year_id=`, `?status=`) dan sort (`?sort=`, `?order=`) pada enam
  endpoint ini (Matrix F-15: "bila didukung"). Parameter itu diabaikan, bukan ditolak.
- Endpoint list lain (mis. `GET /students`) **tidak** berubah oleh F-15.

---

## §9 Error Contract — katalog `error.code` (F-45, F-47 + Stage 1–4 + F-53)

**Teks v1.2 (dipertahankan):** "Katalog code lengkap untuk seluruh endpoint berkembang bertahap seiring
implementasi (living document)". Katalog di bawah disusun dari grep seluruh `new ApiException(...)`,
`HttpException`, `errorResponse()` di `app/` pada `efbf95b`, bukan dari ingatan. Envelope
`{"error": {code, message, details[]}}` tidak berubah.

**A. Kode otomatis dari status HTTP** (`ApiResponseTraits::defaultErrorCode`, F-45/F-47 `79086c5`):

| HTTP | `error.code` | Kapan |
|---|---|---|
| 400 | `bad_request` | Request malformed. |
| 401 | `unauthorized` | Token tidak ada/invalid/kedaluwarsa, kredensial login salah, token lama setelah password berubah (F-29). |
| 403 | `forbidden` | Role tidak diizinkan, di luar scope, atau profil Headmaster/Teacher/Student tidak aktif (F-14). |
| 404 | `not_found` | Resource tidak ada atau di luar scope requester (§26). |
| 405 | `method_not_allowed` | Method HTTP salah (F-47). |
| 409 | `conflict` | Konflik state tanpa kode spesifik. |
| 422 | `validation_failed` | Validasi field gagal, termasuk `per_page` di luar batas, `.xls`, file >1.000 baris, format gambar tidak didukung. |
| 429 | `rate_limit_exceeded` | Rate limit login terlampaui (F-23, F-47). |
| lainnya/5xx | `internal_error` | Kesalahan tak terduga. |

**B. Kode domain** (`ApiException`):

| `error.code` | HTTP | Dipicu saat | Rujukan |
|---|---|---|---|
| `teacher_already_has_active_assignment` | 409 | Teacher sudah punya assignment aktif di Academic Year yang sama (juga pada balapan, F-16). | §16; `ccfce6d` |
| `rombel_already_has_active_teacher` | 409 | Rombel sudah punya Teacher aktif di Academic Year yang sama (POST atau pindah rombel via PATCH; juga pada balapan). | F-18; `ccfce6d` |
| `assignment_inactive_readonly` | 409 | PATCH pada Teacher Class Assignment `is_active=false` (histori). | F-17; `ccfce6d` |
| `academic_year_activation_conflict` | 409 | Aktivasi Academic Year bentrok dengan aktivasi lain yang bersamaan. | F-03; `ccfce6d` |
| `rombel_field_locked` | 409 | PATCH Rombel mengubah `academic_year_id`/`grade_level` padahal sudah ada assignment. | F-19; `ccfce6d` |
| `rombel_has_active_assignment` | 409 | DELETE Rombel yang masih direferensikan assignment (aktif atau historis). | §14 |
| `duplicate_assignment` | 409 | Student sudah punya assignment aktif pada Rombel yang sama di tahun ajaran itu. | §18/§19 |
| `assignment_conflict` | 409 | Student sudah punya assignment aktif pada Rombel lain di tahun ajaran itu. | §18/§19 |
| `duplicate_habit_entry` | 409 | Entry habit duplikat. | §21/§22 |
| `academic_year_mismatch` | 422 | `academic_year_id` bukan milik sekolah Rombel. | §14 |
| `rombel_mismatch` | 422 | Rombel tidak sesuai `school_id`/`academic_year_id` yang diminta (Teacher Assignment) atau `academic_year_id` tidak sesuai Rombel (Student Assignment). | §16/§19 |
| `nisn_conflicts_with_staff_username` | 422 | NISN Student baru sama dengan `users.username` akun Super Admin/Headmaster/Teacher. `details[0].field = "nisn"`. | F-53; `2eec896` |
| `import_file_unparseable` | 422 | File lolos `mimes:xlsx` tetapi tidak bisa dibaca. | F-34; `9e1a31c` |
| `import_header_invalid` | 422 | Kolom wajib hilang/salah ketik di header; `details[]` memuat kolom yang hilang. | F-34; `9e1a31c` |
| `protected_field` | 422 | PATCH Student menyertakan field terlarang. | F-45; `79086c5` |
| `field_not_accepted` | 422 | Body habit/Student Class Assignment memuat field yang tidak diterima. | F-45; `79086c5` |
| `daily_limit_exceeded` | 422 | Batas harian habit terlampaui. | §21 |
| `wrong_habit_endpoint` | 422 | Habit dikirim ke endpoint yang salah. | §21/§22 |
| `unknown_indicator` | 422 | Indikator tidak dikenal. | §21/§22 |
| `mutually_exclusive_violation` | 422 | Indikator Bermasyarakat yang saling meniadakan dikirim bersamaan. | §22 |
| `cross_school_nisn` | 403 | NISN sudah terdaftar di sekolah lain. | §17/§18 |

**C. Kode per baris di `errors[]` pada respons import 200** (bukan status HTTP; lihat §18):
`validation_failed`, `duplicate_in_file` (F-35, `9e1a31c`), `unexpected_error` (kegagalan tak
terstruktur), `student_create_rejected` (safety net), serta kode domain dari tabel B yang muncul per baris
(`duplicate_assignment`, `assignment_conflict`, `cross_school_nisn`, `nisn_conflicts_with_staff_username`,
`rombel_mismatch`).

**D. Ketidaksesuaian yang dicatat, bukan diputuskan:** kode `unknown_rombel` yang disebut §9 dan §18 TIDAK
diemisikan implementasi (tidak ada di `app/`). `rombel_id` yang tidak ada → 422 `validation_failed`
(rule `exists`); `rombel_id` yang bukan own active rombel → 403. Lihat N-1 di bagian akhir.

---

## §10 Authentication — penyelarasan (Matrix §9: F-25, F-26, F-01, F-14, F-24, F-29, F-23)

Ditambahkan karena Matrix §9 mewajibkannya; seluruhnya mendokumentasikan perilaku kode yang sudah ada.

- **Shape `GET /auth/me` per role (F-25):** Super Admin `{id, role, name}` (name = username, tanpa sekolah);
  Headmaster/Teacher `{id, role, name, nip, school: {id, name}|null}`; Student `{id, role, name, nisn,
  school: {id, name}|null}`. Bentuk berbeda per role adalah keputusan final F-25.
- **Semantik `id` (F-26):** `id` di `/auth/me` dan respons login = **`users.id`**, bukan id profil
  (`teachers.id`/`headmasters.id`/`students.id`). Jangan dicampur.
- **Login staff menolak Student (F-01, `401eebc`):** akun Student yang login lewat `POST /auth/login/staff`
  mendapat 401 yang tidak dapat dibedakan dari username yang tidak ada.
- **Sesi dan akun tidak aktif (F-14, F-24, F-29; `bea89e0`, `4189659`, `2e1afb1`):** Headmaster/Teacher
  `inactive` dan Student `inactive`/`graduated` ditolak 403 saat login dan pada token lama. Token juga
  ditolak (401) setelah password berubah atau di-reset, dan setelah logout. Profil Headmaster/Teacher tidak
  aktif di resolver scope → 403.
- **Rate limit login (F-23, `2e1afb1`):** dua counter independen, per IP dan per identifier (`username`
  atau `nisn`), masing-masing 5 percobaan/menit; terlampaui salah satu → 429 `rate_limit_exceeded`.
- **Password awal Super Admin (F-30, `2e1afb1`):** dari konfigurasi/secret, tidak hard-coded; lihat
  dokumen provisioning.

---

## §14 Rombel (F-19, F-20, F-21; `ccfce6d`)

**Teks v1.2 (dipertahankan):** GET Teacher: "own school/rombel yang relevan (VIEW ONLY)"; `grade_level`
"integer, wajib, FINAL"; tidak ada aturan terkunci setelah ada assignment.

**Revisi Stage 5.**
- **F-19 — field terkunci.** Setelah Rombel punya assignment — **Teacher ATAU Student, aktif maupun
  historis** — PATCH yang **mengubah** `academic_year_id` atau `grade_level` ditolak **409
  `rombel_field_locked`** (`details[]` menyebut field yang terkunci). Seluruh PATCH ditolak (tidak ada
  perubahan parsial, `name` yang dikirim bersamanya ikut tidak tersimpan). PATCH `name` saja, atau
  mengirim ulang nilai yang sama dengan yang tersimpan, tidak ditolak. Rombel tanpa assignment tetap bebas
  diubah. Alasan (Matrix): mengubahnya menggeser Ranking Angkatan secara retroaktif.
- **F-20 — batas `grade_level`.** Validasi API: integer, minimum 1, **maksimum 2147483647** (batas teknis
  kolom integer 32-bit; di atasnya 422, bukan 500). **Catatan bisnis [Pemilik], BUKAN validasi tambahan di
  API:** `grade_level` numerik 1–6 mengacu pada SD. TK memakai struktur/group Rombel terpisah (mis.
  "TK A", "TK B", "TK A1/A2") dan **tidak ada pemetaan angka untuk TK** (tidak ada "TK A = 1"). API tidak
  menolak `grade_level` > 6 dan tidak menegakkan catatan ini; lihat N-3.
- **F-21 — cakupan Teacher.** `GET /rombels` untuk Teacher hanya berisi Rombel dari **active Teacher
  Assignment miliknya** (satu Rombel; kosong bila tidak ada active assignment atau Academic Year-nya tidak
  aktif). `GET /rombels/{id}` di luar cakupan itu → **404** (sama dengan id yang tidak ada). Headmaster
  tidak berubah (seluruh Rombel sekolahnya). Paginasi: §7.

---

## §15 Teacher API — Teacher Lifecycle (F-49; `2c05904`)

**Teks v1.2 (dipertahankan, kontradiktif):** "404 — Teacher tidak ditemukan atau di luar scope actor
(cross-school). 403 — role selain Headmaster, **atau Headmaster mencoba menonaktifkan Teacher di sekolah
lain**." Dua baris itu saling bertentangan untuk kasus cross-school.

**Revisi Stage 5 — FINAL, jangan dibuka lagi:** `POST /teachers/{id}/inactive`:
- Headmaster menonaktifkan Teacher **di sekolah lain → 404** (`not_found`, pesan sama dengan id yang tidak
  ada; konvensi §26). Ini keputusan final F-49 (Matrix: `DECIDED — DOC ONLY`).
- **403 hanya untuk role selain Headmaster.** Kalimat "Headmaster mencoba menonaktifkan Teacher di sekolah
  lain → 403" **dibatalkan**.
- Sejarah: sempat ditulis 403 lewat `e58c6e1` (sesi audit lain, tanpa memperhitungkan F-49), lalu
  direkonsiliasi balik ke 404 lewat `2c05904`; lihat blok REVISI di `docs/authorization-matrix.md`
  (Temuan #3).
- **Tidak digeneralisasi:** endpoint write lain yang scope-nya jelas dari path/body (mis. PATCH Rombel,
  Academic Year, Teacher Assignment lintas sekolah) tetap **403**. Hanya endpoint ini yang 404.
- Idempotency (sudah inactive → 200) dan tidak adanya reactivation tidak berubah.

---

## §16 Teacher Class Assignment (F-16, F-17, F-18; `ccfce6d`)

**Teks v1.2 (dipertahankan):** "Satu Teacher hanya satu active rombel per Academic Year"; 409
`teacher_already_has_active_assignment`. Tidak ada aturan per Rombel dan tidak ada aturan untuk assignment
nonaktif.

**Revisi Stage 5.**
- **F-18 — satu Teacher aktif per Rombel.** Dalam satu Rombel pada satu Academic Year maksimal **satu**
  Teacher assignment aktif (Teacher itu = wali kelas sistem). Ditegakkan di DB lewat partial unique index
  baru `uq_tca_one_active_per_rombel_year` (migration
  `2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments`) di atas index lama
  `uq_tca_one_active_per_teacher_year`, dan di aplikasi (pre-check di `POST` dan di `PATCH` yang memindah
  Rombel). Pelanggaran → **409 `rombel_already_has_active_teacher`**.
- **F-16 — balapan.** Dua POST bersamaan yang menabrak salah satu index tidak lagi 500: constraint
  Teacher+tahun → 409 `teacher_already_has_active_assignment`; constraint Rombel+tahun → 409
  `rombel_already_has_active_teacher`. Kode mana yang keluar bergantung constraint yang terpicu; pre-check
  sekuensial memeriksa Teacher lebih dulu.
- **F-17 — assignment historis read-only.** `PATCH /teacher-class-assignments/{id}` pada assignment
  `is_active=false` → **409 `assignment_inactive_readonly`**; perubahan dilakukan dengan membuat assignment
  baru (POST). Cek scope (403 lintas sekolah) tetap lebih dulu.
- Migration gagal dengan pesan jelas bila data existing sudah punya >1 Teacher aktif per Rombel+tahun.

---

## §17 Student (F-06, F-07, F-53; `d486910`, `2eec896`)

**Teks v1.2 (dipertahankan):** `photo` "Diunggah lewat mekanisme upload terpisah (multipart)"; tidak ada
batas dimensi; tidak ada aturan NISN vs akun staf.

**Revisi Stage 5.**
- **F-06 — foto Student private (Matrix: Opsi A).** Field `photo` di respons Student kini berisi **URL
  endpoint terautentikasi** `GET /api/v1/students/{id}/photo` (atau `null` bila tidak ada foto), **bukan URL
  publik langsung**. Endpoint ini memakai otorisasi yang sama dengan `GET /students/{id}` (Super Admin;
  Headmaster own school; Teacher own active rombel; Student diri sendiri); di luar scope → **404**; foto
  hilang → 404. Karena butuh header `Authorization: Bearer <token>`, **FE tidak bisa memakai `<img src>`
  langsung**: ambil lewat `fetch` dengan header, lalu tampilkan sebagai blob/object URL. Respons membawa
  `Cache-Control: private, no-store` dan `X-Content-Type-Options: nosniff`. Logo School tetap URL publik.
- **F-07 — batas upload.** Gambar (foto Student, logo School): maksimum **4096×4096 px** dan **2 MB**;
  format jpg/png/webp (ekstensi ditentukan dari isi file, bukan nama klien — F-05 `4796b7a`); pelanggaran →
  422 `validation_failed`.
- **F-53 — NISN vs username staf [Pemilik].** `POST /students` dan baris Student Import dengan NISN **baru**
  yang sama dengan `users.username` akun Super Admin/Headmaster/Teacher → **422
  `nisn_conflicts_with_staff_username`** (`details[0].field = "nisn"`); Student tidak dibuat. NISN yang sama
  dengan NISN **Student lain tetap sah** (reuse/re-enrollment) dan **tidak unique global**; username
  Student tetap = NISN. Divalidasi di service sebelum insert. Batasan yang disadari: ada celah balapan
  kecil antara pengecekan dan INSERT yang secara desain tidak bisa ditutup dengan constraint (NISN sengaja
  tidak unique); constraint `users.username` yang sudah ada mencegah data ganda dan pelanggarannya
  diterjemahkan menjadi 422 bila penyebabnya akun non-Student. Race dua Student baru ber-NISN sama
  bersamaan tetap 500 (perilaku lama, di luar F-53).

---

## §18 Student Import dan §28 Import Contract (F-33 s.d. F-38; `9e1a31c`, `cea2bcd`)

**Teks v1.2 yang DIGANTIKAN (dipertahankan sebagai histori):**
1. §18 Request: "`file: <xlsx/csv>`".
2. §18: "File >100 baris diproses lewat batch/queue asinkron; ≤100 baris boleh diproses langsung sinkron
   (technical/performance note, tidak mengubah kontrak response)."
3. §28 tidak menyebut async tetapi mengacu pola yang sama untuk Teacher Import (§15).

**Revisi Stage 5 — F-38 [Pemilik, FINAL]: seluruh mekanisme asinkron dihapus.**
- **Semua ukuran file (sampai batas 1.000 baris) diproses SINKRON** di dalam request. Tidak ada
  queue/job, tidak ada 202, tidak ada `status:"pending"`, dan **tidak ada variasi respons berdasarkan
  jumlah baris**. File upload dibaca langsung dan tidak disimpan ke storage.
- **Respons commit selalu HTTP 200** dengan `total_rows`, `processed_rows`, `successful_rows`,
  `rejected_rows`, `errors[]` (`row`, `field`, `code`, `message`) secara langsung, ditambah `batch_id`
  (integer) sebagai kunci untuk endpoint status; `batch_id` bukan pengganti isi respons.
- Berlaku untuk `POST /teachers/import` (Headmaster) dan `POST /student-imports/commit` (Teacher).
  `POST /student-imports/preview` tetap hanya validasi struktural/format.
- Catatan operasional (bukan kontrak): waktu respons kini proporsional dengan jumlah baris karena tiap baris
  membuat akun; lihat `docs/testing/BASELINE-STAGE-0C.md` §5.
- Bila `commit()` melempar exception di tengah proses, batch ditandai `failed` (bukan tertahan
  `processing`) dan respons error normal dikembalikan (F-37 diteruskan ke jalur sinkron, `cea2bcd`).

**Format file (F-33).** Request `file: <xlsx>` — **`.xlsx` saja**; `.xls` dan csv ditolak **422
`validation_failed`**. Maksimum 5 MB dan 1.000 baris (>1.000 baris → 422 `validation_failed`, tidak
diproses). Berlaku untuk Teacher Import dan Student Import (preview dan commit).

**Kegagalan level file (F-34).** File yang tidak bisa dibaca → **422 `import_file_unparseable`**. Kolom
wajib hilang atau salah ketik di header → **satu** error **422 `import_header_invalid`** (`details[]` memuat
kolom yang hilang), bukan banjir error `required` per baris. Terjadi sebelum batch dibuat. File tanpa baris
data tidak punya header yang bisa diperiksa dan diproses sebagai total 0.

**Perilaku per baris.**
- **F-36:** semua field teks dari Excel (NIP, NISN, nama) di-`trim()` sebelum validasi, cek duplikat, dan
  penyimpanan.
- **F-35 (khusus Teacher Import):** NIP kembar **antar-baris dalam file yang sama** — baris pertama yang
  valid diproses; baris berikutnya gagal dengan `field: "nip"`, `code: "duplicate_in_file"`, dan pesan yang
  menyebut baris asal. Student Import tidak punya deteksi ini karena NISN yang sama justru di-reuse.
- **F-53:** baris Student dengan NISN bentrok username staf gagal per baris (`field: "nisn"`,
  `code: "nisn_conflicts_with_staff_username"`); baris lain tetap diproses (partial processing). Preview
  tidak mendeteksi ini; baru terdeteksi saat commit.
- Partial processing dan "respons 200 untuk commit yang berhasil dieksekusi" tidak berubah.

**Endpoint status — `GET /teachers/import/status/{batchId}` dan `GET /student-imports/status/{batchId}`
[Pemilik, keputusan FINAL].** Karena tidak ada lagi proses yang berjalan di background, endpoint ini
**bukan lagi untuk polling proses yang sedang berjalan**. Fungsinya kini **membaca HASIL/histori import
yang sudah selesai** berdasarkan `batch_id` yang dikembalikan di respons commit (`status` `done` atau
`failed`, plus ringkasan dan `errors[]` yang tersimpan). Alasan: hasil import tetap dapat dibaca ulang
untuk audit/riwayat tanpa mengunggah ulang file. Akses (F-32, `5696c4b`): hanya **pemilik batch** dan hanya
untuk jenis yang sesuai; batch milik orang lain, jenis lain, atau yang tidak ada → **404**. Konsekuensi
faktual: tidak ada endpoint daftar batch (id harus disimpan klien), dan status `processing` hanya tersisa
bila proses PHP terbunuh di tengah request.

---

## §23 Gamification dan §25 Dashboard (F-40, F-41, F-43, F-44; `3f65862`, `9e1a31c`)

**Teks v1.2 yang SALAH SECARA MATEMATIS (dipertahankan sebagai histori):** contoh respons §23
(`GET /students/{id}/gamification`) dan §25 (`GET /dashboard/student`) memuat `"poin": 340,
"gamification_level": 4`. Threshold asli (seeder `gamification_levels`, DB Design §5.14): Level 3 = 200
poin, **Level 4 = 400 poin**, Level 5 = 757 poin. Level adalah level tertinggi yang thresholdnya ≤ poin,
jadi 340 poin = **Level 3**, bukan 4.

**Revisi Stage 5 (F-40).** Contoh yang benar:

```json
{ "data": { "student_id": 55, "poin": 340, "gamification_level": 3 } }
```

Contoh `dashboard/student` dikoreksi dengan cara sama (`"poin": 340, "gamification_level": 3`). Field
`gamification_level` di `GET /dashboard/student` ditambahkan oleh F-40 (`3f65862`) dan dihitung real-time
dengan mekanisme yang sama dengan §23 (`LevelService`).

**F-41 — atribusi `inactive`/`graduated` di `dashboard/teacher` (Matrix, FINAL).** Hitungan Student per
status (`active`/`inactive`/`graduated`) di Rombel Teacher memakai **assignment TERAKHIR** Student pada
Academic Year itu (assignment dengan id terbesar per Student per Academic Year), bukan assignment mana pun
yang pernah ada di Rombel. Student yang pindah Rombel A → B lalu menjadi `inactive`/`graduated` di B
dihitung **di B**, tidak di A; transisi lifecycle langsung di A dihitung di A. `active` mengikuti current
active assignment. `students.classroom` tidak dipakai. Teks lama (§25: "Jumlah Student per status ... di
own active rombel") tidak menjelaskan atribusi ini.

**F-43/F-44 — definisi metrik Super Admin (Matrix, DECIDED — DOC ONLY; kode tidak berubah, `31016e3`).**
`headmaster_count` = jumlah Headmaster **`status='active'`**. `school_count` = **seluruh** School
(tidak difilter). Dua metrik berbeda; jangan disamakan.

---

## §26 Security & Isolation — hasil cross-check (tidak ada errata wajib)

Dicek terhadap seluruh fix Stage 1–4 + F-53. Hasil:
- **Konsisten:** F-21 (Rombel di luar scope Teacher → 404 pada GET), F-06 (foto di luar scope → 404),
  F-32 (batch import milik orang lain → 404), F-17 (cek scope 403 lebih dulu daripada status), F-14/F-24/F-29
  (token divalidasi tiap request).
- **Pengecualian yang sudah terdokumentasi:** aturan §26 "write pada resource dengan scope jelas → 403"
  punya satu pengecualian final, `POST /teachers/{id}/inactive` lintas sekolah → 404 (F-49, lihat §15).
- **Catatan, bukan errata:** respons F-53 (422 `nisn_conflicts_with_staff_username`) memungkinkan Teacher
  menguji apakah suatu string adalah username staf (termasuk di sekolah lain). Lihat N-2.

---

## Ketidaksesuaian yang butuh keputusan pemilik (dicatat, TIDAK diputuskan di Stage 5)

- **N-1 — `unknown_rombel`.** §9 dan §18 menyebut kode ini, tetapi implementasi tidak pernah
  mengeluarkannya (lihat §9-D). Pilihan: ubah teks SoT ke perilaku aktual (422 `validation_failed` /
  403), atau tambahkan kode ke implementasi.
- **N-2 — enumerasi username staf lewat F-53.** `cross_school_nisn` (403) sudah memiliki sifat serupa
  untuk NISN lintas sekolah. Pilihan: terima sebagai trade-off (422 jelas lebih berguna bagi Teacher),
  atau samarkan pesannya.
- **N-3 — catatan bisnis `grade_level` 1–6 (SD).** Contoh SoT lain memakai `VII-A`/`grade_level: 7`
  (§14, seeder, test). Bila platform juga melayani jenjang di atas SD, catatan "1–6" perlu dibatasi
  eksplisit sebagai konteks SD. Tidak ada dampak kode.
- **F-48 (Task Board)** adalah dokumen pemilik di luar repo ini; sinkronisasi status dilakukan pemilik.
