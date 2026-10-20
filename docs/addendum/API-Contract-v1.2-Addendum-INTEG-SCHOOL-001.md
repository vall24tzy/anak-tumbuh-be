# API Contract v1.2 — Addendum: INTEG-SCHOOL-001 (PATCH /schools/{id}: ubah nama sekolah)

Status: implementasi INTEG-SCHOOL-001 (Orang 2), 20 Okt 2026. Dasar: keputusan D-09, D-02, D-03, D-04 (FINAL,
`SoT-Amendment-INTEG-D01-D13.md`, tidak diubah), Authorization Matrix v5.3 baris #65 (baru), Task Board INTEG
v1.5, dan keputusan tambahan D-14, D-15, D-16 (bagian 6). Sifat perubahan: **additive**; tidak ada field atau
endpoint lama yang diubah atau dihapus.

## 1. Revisi API Contract §11

§11 menyatakan tidak ada operasi PATCH untuk School. Pernyataan itu **diganti** oleh endpoint berikut:

| Method | Path | Middleware | Body | Respons |
|---|---|---|---|---|
| `PATCH` | `/api/v1/schools/{id}` | `jwt` + `role:super_admin` | `{ "name": string }` | 200 `{data: {id, name, address, logo}, message}` |

- Hanya **Super Admin**. Headmaster, Teacher, Student -> 403 `forbidden`; tanpa token -> 401 (D-09).
  Headmaster tidak mendapat GET/PATCH sekolah; nama sekolah tersedia di `GET /auth/me` dan, untuk Headmaster, di
  `GET /dashboard/headmaster` tidak (dashboard memuat angka total, bukan nama sekolah).
- Super Admin bersifat global, jadi tidak ada kasus lintas sekolah. Id tidak ada -> 404 (D-02).
- Bentuk `data` sama dengan `SchoolResource` (id, name, address, logo); `teachers_count` dan `students_count`
  tidak disertakan pada respons PATCH. `GET /schools`, `POST /schools`, dan `GET /schools/{id}` tidak berubah.

## 2. Aturan field

- Hanya `name`. Aturan mengikuti `CreateSchoolRequest`: wajib, string, maksimal 150 karakter (= `schools.name`
  varchar(150), F-09), unik di aplikasi (F-11) dengan mengabaikan id sekolah yang sedang diubah, sehingga
  mengirim nama yang sama dengan nama sekarang bukan duplikat.
- `name` kosong, null, hilang, atau lebih dari 150 karakter -> 422 `validation_failed`. Nama milik sekolah lain
  -> 422 `validation_failed` (termasuk bila duplikat terjadi pada request konkuren, fallback constraint DB
  `schools_name_unique`, pola F-11).
- Field selain `name` (`address`, `logo`, `logo_path`, `id`, `school_id`, `teachers_count`, dst.) ditolak
  **422 `protected_field`** dengan `error.details[].field` berisi nama field, walau `name` yang valid ikut
  dikirim. Seluruh request ditolak, tidak ada silent-drop (pola `UpdateStudentRequest`, Matrix #11).
  Alamat dan logo ditunda (D-09).
- Spasi di tepi `name` dipangkas oleh middleware `TrimStrings` (perilaku yang sama dengan `POST /schools`).
- Tidak ada rate limit baru (D-04): limiter `password-change` tidak diperluas.

## 3. Audit (D-03, D-14, D-15)

- Perubahan nama dicatat ke `audit_logs` lewat `AuditLogService::record()`:
  `actor_user_id` = Super Admin yang login, `action` = `school_name_update`, `target_user_id` = null
  (diperbolehkan oleh D-03 dan F-31).
- Dicatat **hanya bila nama benar-benar berubah** (D-14). "Sama" = identik dengan nilai tersimpan setelah
  normalisasi input yang sama dengan saat menyimpan (trim). Beda huruf besar/kecil dihitung **berubah**.
  Mengirim nama yang sama -> 200, nama tidak berubah, nol baris audit.
- Request yang gagal (403, 404, 422) tidak menghasilkan baris audit.
- Alasan D-14: tidak ada aksi yang terjadi, dan baris audit tidak membawa nilai lama/baru, sehingga baris untuk
  PATCH tanpa perubahan hanya noise. Bila pemilik kelak ingin semua PATCH tercatat, cukup mengubah satu blok
  (`isDirty`) di `SchoolService::update()`.
- Aturan yang sama berlaku untuk INTEG-TEACHER-001 (D-03 mencakup edit guru; action-nya `teacher_update`, D-15)
  agar perilakunya tidak berbeda antar-endpoint.

## 4. Gap yang diterima (D-16) dan sifat infrastruktur

- **Gap jejak audit.** `audit_logs` hanya memuat `actor_user_id`, `action`, `target_user_id` (-> users),
  `created_at`. Jejak ini hanya menjawab "siapa mengubah nama sekolah dan kapan", bukan "sekolah mana" dan bukan
  "dari apa ke apa". Satu-satunya petunjuk tambahan adalah `schools.updated_at`, dan hanya untuk perubahan
  terakhir. Dibiarkan tanpa perubahan skema (D-16). Jejak yang lebih lengkap (mis. kolom `details` JSON) =
  migration + errata DB Design + perubahan semua pemanggil, yaitu task terpisah yang baru dikerjakan bila pemilik
  memintanya. Untuk guru gap lebih kecil: `target_user_id` dapat menunjuk user guru; hanya nilai lama/baru
  yang hilang.
- **Sifat yang sudah ada (bukan gap baru).** `AuditLogService` sengaja tidak pernah melempar exception dan
  melewati penulisan bila tabel belum ada. "Wajib dicatat" (D-03) berarti pemanggil wajib memanggil `record()`;
  ini tidak menjamin baris tertulis saat penulisan gagal (pola yang sama dengan `password_reset` dan
  `qr_regenerate`).

## 5. Dampak ke frontend

- FE Kepala Sekolah: sembunyikan tombol edit nama sekolah (D-09); nama sekolah tetap dari `GET /auth/me`.
- FE Super Admin: belum diperiksa (di luar cakupan pengecekan kode awal).

## 6. Keputusan tambahan (pemilik, 20 Okt 2026)

| ID | Keputusan | Status |
|---|---|---|
| D-14 | Audit hanya dicatat bila nama benar-benar berubah (definisi "sama" dan alasan di bagian 3); aturan yang sama berlaku untuk INTEG-TEACHER-001 | disetujui pemilik |
| D-15 | Nama action: `school_name_update` (SCHOOL-001) dan `teacher_update` (TEACHER-001), snake_case mengikuti `qr_regenerate` dan `password_reset`; id sekolah atau nilai lama/baru tidak disisipkan ke string action; `target_user_id` null untuk sekolah | disetujui pemilik |
| D-16 | Gap jejak audit (bagian 4) diterima tanpa perubahan skema; jejak lebih lengkap = task terpisah atas permintaan pemilik | disetujui pemilik |

`docs/addendum/SoT-Amendment-INTEG-D01-D13.md` tidak diubah (sudah ditandatangani); D-14 sampai D-16 dicatat di sini.

## 7. Dampak ke dokumen lain

- API Contract v1.2 §11: penggabungan ke v1.3 (INTEG-DOC-001) mengganti "PATCH tidak ada" dengan bagian 1 di atas.
- `docs/authorization-matrix.md` di repo: baris #65 (Super Admin ubah nama sekolah) ditambahkan oleh INTEG-DOC-001.
