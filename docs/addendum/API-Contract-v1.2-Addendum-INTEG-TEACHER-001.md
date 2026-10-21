# API Contract v1.2 — Addendum: INTEG-TEACHER-001 (PATCH /teachers/{id}: ubah name dan gender guru)

Status: implementasi INTEG-TEACHER-001 (Orang 2), 20 Okt 2026. Dasar: keputusan D-02, D-03, D-04, D-14, D-15, D-16
(FINAL; D-14 sampai D-16 tercatat di `API-Contract-v1.2-Addendum-INTEG-SCHOOL-001.md` bagian 6, SoT-Amendment tidak
diubah), Authorization Matrix v5.3 baris #66 (baru; merujuk #61), Task Board INTEG v1.5. Sifat perubahan:
**additive**; tidak ada field atau endpoint lama yang diubah atau dihapus.

## 1. Revisi API Contract §15

§15 menyatakan PATCH Teacher tidak ditambahkan. Pernyataan itu **diganti** oleh endpoint berikut:

| Method | Path | Middleware | Body | Respons |
|---|---|---|---|---|
| `PATCH` | `/api/v1/teachers/{id}` | `jwt` + `role:headmaster` | `{ "name"?: string, "gender"?: "laki-laki"\|"perempuan" }` | 200 `{data: Teacher}` |

Bentuk `data` sama dengan `TeacherResource` yang sudah ada: `id, school_id, name, nip, gender, status`. `name`
dipetakan ke kolom `teachers.fullname`. Endpoint `GET/POST /teachers`, `GET /teachers/{id}`,
`POST /teachers/{id}/inactive`, `POST /teachers/{id}/reset-password`, dan import tidak berubah.

## 2. Aturan field

- Hanya `name` (wajib terisi bila dikirim, maksimal 150) dan `gender` (`laki-laki` atau `perempuan`). Aturan
  mengikuti `CreateTeacherRequest`. Kedua field opsional (pola PATCH Student); kirim salah satu atau keduanya.
- **NIP tidak boleh diubah di tahap ini.** `nip`, `username`, `school_id`, `status`, `password`, `user_id`, `id`,
  dan field lain apa pun ditolak **422 `protected_field`** dengan `error.details[].field` berisi nama field, walau
  `name` yang valid ikut dikirim. Seluruh request ditolak (tidak ada silent-drop; pola `UpdateStudentRequest`,
  Matrix #11). Alasan NIP: `users.username` = NIP, sehingga mengubahnya adalah keputusan terpisah (keunikan lintas
  `users.username` dan `nip`, password tidak berubah, token lama dicabut, dicatat di audit).
- `gender` di luar dua nilai, serta `name` kosong, null, atau lebih dari 150 karakter -> 422 `validation_failed`.
- Spasi di tepi `name` dipangkas oleh middleware `TrimStrings`.
- Password, token, dan `username` Teacher tidak tersentuh; token Teacher tidak dicabut.

## 3. Otorisasi, scope, dan guru inactive

| Kondisi | Hasil |
|---|---|
| Headmaster aktif, guru di sekolah sendiri, status active | 200 |
| Teacher, Student, Super Admin | 403 `forbidden` |
| Tanpa token | 401 |
| Headmaster berstatus inactive | 403 (F-14) |
| Guru di sekolah lain, atau id tidak ada | **404** (D-02; pola F-49, id guru tidak dapat dienumerasi) |
| Guru berstatus inactive (di sekolah sendiri) | **409 `teacher_inactive_readonly`** |

- Guru inactive tidak boleh diedit: pola `assignment_inactive_readonly` di `TeacherAssignmentService`. Inactive tidak
  punya reaktivasi (Matrix #61). Pengecekan dilakukan SETELAH scope sekolah, sehingga guru inactive di sekolah lain
  tetap 404.
- Tidak ada rate limit baru (D-04).

## 4. Audit (D-03, D-14, D-15, D-16)

- Perubahan dicatat ke `audit_logs` lewat `AuditLogService::record()`: `actor_user_id` = Headmaster yang login,
  `action` = `teacher_update`, `target_user_id` = `users.id` guru (bukan `teachers.id`).
- Dicatat **hanya bila ada nilai yang benar-benar berubah** (D-14): satu PATCH yang mengubah dua field = satu baris.
  Nilai sama, sama setelah trim, atau body kosong -> 200 tanpa baris audit. Beda huruf besar/kecil dihitung berubah.
  Request yang gagal (401, 403, 404, 409, 422) tidak menghasilkan baris audit. Bila pemilik kelak ingin semua PATCH
  tercatat, cukup mengubah satu blok (`isDirty`) di `TeacherService::update()`.
- Gap yang diterima (D-16): baris audit tidak membawa nilai lama/baru. Untuk guru gap lebih kecil dari sekolah,
  karena `target_user_id` menunjuk user guru; hanya nilai lama/baru yang hilang.
- Sifat yang sudah ada: `AuditLogService` tidak pernah melempar exception, jadi "wajib dicatat" berarti pemanggil
  wajib memanggil `record()`; baris tidak dijamin tertulis bila penulisan gagal (sama dengan `password_reset`).

## 5. Hal yang bersifat usulan (menunggu konfirmasi pemilik)

Kedua hal ini tidak ditetapkan eksplisit di SoT; implementasi mengambil pilihan berikut dan perlu ditegaskan saat review:

1. Status HTTP untuk guru inactive = **409**. Board hanya mengusulkan kode `teacher_inactive_readonly`; 409 mengikuti
   `assignment_inactive_readonly`.
2. Body PATCH tanpa field (`{}`) = 200 no-op tanpa baris audit (pola PATCH Student dan D-14), bukan 422.

## 6. Dampak ke frontend dan dokumen lain

- FE Kepala Sekolah: jangan kirim NIP pada edit guru; tombol edit baru diaktifkan setelah task ini selesai. Catatan:
  dokumen tugas FE (19 Okt 2026) menyebut field `nip` pada update guru; itu tidak berlaku (NIP ditolak 422).
  FE perlu menangani 409 `teacher_inactive_readonly` (sembunyikan edit untuk guru inactive).
- API Contract v1.2 §15: penggabungan ke v1.3 (INTEG-DOC-001) mengganti "PATCH Teacher tidak ditambahkan" dengan
  bagian 1 di atas.
- `docs/authorization-matrix.md` di repo: baris #66 (Headmaster ubah name + gender Teacher own school) ditambahkan
  oleh INTEG-DOC-001.
