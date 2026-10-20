# API Contract v1.2 — Addendum: INTEG-ME-001 (rombel aktif + foto di profil dan dashboard Student)

Status: implementasi INTEG-ME-001 (Orang 2), 20 Okt 2026. Dasar: keputusan D-08 (FINAL, disetujui 20 Okt 2026
lewat `SoT-Amendment-INTEG-D01-D13.md`), Authorization Matrix v5.3 baris #67 (merujuk #9), API Contract v1.2
§10 (`GET /auth/me`) dan §25 (`GET /dashboard/student`). Sifat perubahan: **additive**; tidak ada field atau
endpoint lama yang diubah atau dihapus.

## 1. Field baru (hanya role Student)

Ditambahkan di AKHIR objek `data`, pada dua endpoint:

| Endpoint | Field baru | Tipe |
|---|---|---|
| `GET /api/v1/auth/me` (role Student) | `rombel`, `photo` | `{id:int, name:string}` atau `null`; `string` (URL) atau `null` |
| `GET /api/v1/dashboard/student` | `rombel`, `photo` | sama |

Urutan field setelah perubahan:

- `/auth/me` Student: `id, role, name, nisn, school, rombel, photo`
- `/dashboard/student`: `wali_kelas, habit_summary_today, poin, gamification_level, rombel, photo`

Role lain (Super Admin, Headmaster, Teacher): `GET /auth/me` TIDAK berubah (tanpa `rombel` dan `photo`).
Nama, nisn, dan sekolah sudah ada di `/auth/me` Student dan tidak diulang.

## 2. Definisi `rombel`

- Rombel dari assignment `student_class_assignments` berstatus `active` pada Academic Year yang sedang
  `is_active` di sekolah Student, lewat resolver yang sudah ada
  `StudentClassAssignmentService::activeAssignmentInCurrentAcademicYear()` (STUDENTHABIT-001, GAP #5).
  Tidak ada query baru.
- DB Design v1.5 §5.9 membolehkan Student punya assignment aktif di lebih dari satu tahun ajaran (unik per
  student + tahun ajaran, bukan per student). Karena itu definisi di atas wajib eksplisit: assignment di
  tahun ajaran yang tidak active TIDAK dipakai.
- `rombel` = `null` bila tidak ada assignment aktif pada tahun ajaran aktif (termasuk bila sekolah belum
  punya tahun ajaran aktif). Ini bukan error; response tetap 200.
- Isi hanya `id` dan `name`. `grade_level` dan field rombel lain tidak dikirim.

## 3. Definisi `photo` (D-08, pola F-06)

- Berisi URL absolut endpoint terproteksi `GET /api/v1/students/{id}/photo` (route `students.photo`, sama
  dengan `StudentResource`), atau `null` bila `students.photo_path` kosong.
- BUKAN path berkas dan BUKAN URL storage publik (`/storage/...`).
- Segmen `{id}` adalah `students.id`, bukan `users.id`. `data.id` pada `/auth/me` tetap `users.id` (F-26).
- Otorisasi endpoint foto tidak berubah: Student hanya dapat membuka fotonya sendiri; di luar scope = 404;
  tanpa token = 401.

### Catatan untuk frontend

URL terproteksi memerlukan header `Authorization: Bearer <token>`, sehingga **tidak bisa dipakai langsung**
sebagai `src` pada `<img>`. Frontend harus mengambil gambar dengan `fetch` + header Authorization, lalu
menampilkannya, misalnya lewat `URL.createObjectURL(blob)`. Respons foto memakai
`Cache-Control: private, no-store`.

## 4. Perubahan test yang disengaja

- `StudentPhotoPrivacyTest::auth_me_dan_dashboard_student_...`: dahulu melarang kata "photo" pada kedua
  endpoint. Sekarang yang dilarang adalah path berkas dan URL publik; keberadaan URL terproteksi justru
  diwajibkan (D-08).
- `DashboardStudentTest`: dua assertion urutan field diubah dari 4 menjadi 6 field.
- Test baru: `tests/Feature/Auth/MeStudentRombelPhotoTest.php` (14 test).

## 5. Dampak ke dokumen lain

- API Contract v1.2 §10 dan §25: contoh response perlu menambah `rombel` dan `photo` pada penggabungan v1.3
  (INTEG-DOC-001, dilakukan di akhir semua task).
- `docs/authorization-matrix.md` di repo: baris #67 ditambahkan oleh INTEG-DOC-001.
