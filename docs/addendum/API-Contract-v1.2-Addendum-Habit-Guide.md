# API Contract v1.2 — Addendum: Panduan kebiasaan per rombel (INTEG-PANDUAN-001)

**Tanggal:** 21 Okt 2026 · **Dasar keputusan:** Dokumen Keputusan v4.0 (amandemen 20 Okt 2026, butir a; D-06, D-13) · Authorization Matrix v5.3 baris **#63** (tulis) dan **#64** (lihat).
**Status:** kode, test PHPUnit, dan request Postman sudah dijalankan (lihat `docs/team-log/INTEG-PANDUAN-001.md`). DB: lihat `docs/addendum/DB-Design-v1.5-Errata-Habit-Guides.md` (tabel baru `habit_guides`, perlu `php artisan migrate`).

## 1. Endpoint

Panduan = teks polos yang ditulis Teacher untuk satu **(rombel x kebiasaan)** dan dibaca Teacher/Student rombel itu. Bukan input Student dan tidak mengubah aturan field `note` pada entri habit.

| Method dan path | Akses |
|---|---|
| `GET /api/v1/rombels/{rombel_id}/habit-guides` | Teacher (own active rombel), Student (rombel sendiri). Matrix #64. |
| `PATCH /api/v1/rombels/{rombel_id}/habit-guides/{habit_id}` | Teacher (own active rombel) saja. Matrix #63. |

`rombel_id` dan `habit_id` harus numerik (selain itu 404 dari routing). Path ditetapkan di addendum ini (di Task Board masih "usulan").

## 2. GET — daftar panduan satu rombel

Respons 200: hanya panduan yang **ada**, urut `sort_order` habit. Rombel tanpa panduan menghasilkan daftar kosong. Tanpa pagination (maksimal 7 elemen).

```json
{ "data": [ { "habit_id": 1, "content": "Bangun sebelum subuh, rapikan tempat tidur." } ] }
```

```json
{ "data": [] }
```

## 3. PATCH — tulis atau hapus panduan

Body: `{ "content": "..." }`

| `content` | Hasil |
|---|---|
| String terisi, maksimal **2.000 karakter** (multibyte dihitung per karakter) | Simpan atau timpa (upsert atomik; satu baris per rombel x habit). 200 `{ "data": { "habit_id": 1, "content": "..." } }` |
| `""`, `null`, atau hanya spasi | **Hapus** panduan (D-13). 200 `{ "data": { "habit_id": 1, "content": null } }`. Menghapus panduan yang memang tidak ada tetap 200 (idempoten). |
| Key `content` tidak dikirim | 422 `validation_failed` (mencegah salah kirim menghapus panduan). |
| Lebih dari 2.000 karakter | 422 `validation_failed`. |
| Memuat tag HTML | 422 `validation_failed` (lihat §4). |

Setelah dihapus, `GET` identik dengan "belum pernah ada panduan" (elemen hilang dari daftar). PATCH selalu 200 (tidak ada 201 untuk pembuatan pertama).

## 4. Teks polos

Panduan disimpan dan dikeluarkan sebagai teks polos. Nilai yang memuat **tag HTML** (pola `<tag ...>`, `</tag>`, atau `<!...>`) **ditolak** dengan 422, tidak dibersihkan diam-diam. Karakter `<` dan `>` biasa (misalnya `Saya <3 belajar`, `a < b`), markdown, tanda kutip, `&`, dan baris baru disimpan apa adanya; markdown **tidak** diproses.

Penolakan hanya berlaku untuk tag HTML **lengkap** (ada tanda `>` penutup). Teks seperti `<img src=x onerror=...` tanpa `>` tersimpan apa adanya. Karena itu FE **wajib** menampilkan panduan sebagai teks biasa (tanpa `innerHTML` atau render HTML apa pun).

## 5. Akses dan scope

| Pemanggil | Hasil |
|---|---|
| Tanpa token | 401 |
| Super Admin, Headmaster (GET maupun PATCH) | 403 |
| Student pada PATCH | 403 |
| Teacher, `rombel_id` = own active rombel | 200 |
| Student, `rombel_id` = rombel aktif sendiri | 200 (GET) |
| Rombel lain di **sekolah yang sama** (Teacher maupun Student) | 403 |
| Rombel milik **sekolah lain** atau **tidak ada** | 404 `not_found`, respons identik untuk keduanya (D-11, anti-enumerasi) |
| `habit_id` tidak dikenal (PATCH) | 404 `not_found` |
| Teacher inactive, Teacher tanpa assignment aktif, Student tanpa rombel aktif | 403 untuk rombel sekolahnya |

Scope Teacher hanya dari `TeacherScopeService::activeAssignment()`; rombel Student dari `StudentClassAssignmentService::activeAssignmentInCurrentAcademicYear()`. Urutan evaluasi: 401 → 403 role → 422 (hanya PATCH) → 404/403 rombel → 404 habit. Tidak ada rate limit baru (D-04) dan tidak ada `audit_logs` (bukan aksi sensitif, amandemen 20 Okt).

## 6. Keterbatasan yang perlu diketahui

- Panduan melekat pada **rombel** (yang terikat satu tahun ajaran), bukan pada guru: Teacher pengganti di rombel yang sama dapat mengubahnya. Rombel tahun ajaran baru mulai tanpa panduan (tidak ada penyalinan otomatis).
- Rombel yang masih punya panduan **tidak bisa dihapus** (FK `ON DELETE RESTRICT`). `DELETE /rombels/{id}` mengembalikan 409 dengan kode/pesan yang sudah ada (`rombel_has_active_assignment`) karena `RombelService` memetakan semua pelanggaran FK ke kode itu. Pesan tersebut kurang tepat untuk kasus ini; tidak diubah di task ini (di luar scope).
- Tidak ada riwayat versi dan tidak ada kolom penulis/waktu di respons.

## 7. Kode dan test

Baru: `HabitGuideController`, `UpdateHabitGuideRequest`, `HabitGuideService`, model `HabitGuide`, migration `2026_10_20_100000_create_habit_guides_table`, `routes/v1/habit-guide.php` (di-require dari `routes/api.php`). Tidak ada perubahan pada kode lama selain satu baris `require`.
`tests/Feature/Student/HabitGuideTest.php`: tulis/baca Teacher+Student, ketujuh kebiasaan (termasuk Bermasyarakat) berurut `sort_order`, timpa, hapus (kosong/null/spasi, idempoten, tidak menyentuh habit lain), key hilang, batas 2.000 (termasuk multibyte dan karakter 4-byte), HTML vs teks polos, 404 habit/path non-numerik, 401/403 role, lintas rombel, tidak bocor antar-rombel, 404 identik lintas sekolah, Teacher inactive/tanpa assignment/Student tanpa rombel, RESTRICT pada rombel, constraint DB (CHECK dan UNIQUE).
Postman: folder `INTEG-O1` (20 request PANDUAN setelah QR dan REKAP).

## 8. Untuk penggabungan ke API Contract v1.3 (INTEG-DOC-001)

Tambahkan kedua endpoint beserta tabel §3, §5 pada kelompok Habit/Rombel, dan tabel `habit_guides` pada DB Design (errata terpisah).
