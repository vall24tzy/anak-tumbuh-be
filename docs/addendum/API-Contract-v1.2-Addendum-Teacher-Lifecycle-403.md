# API Contract v1.2 — Addendum: Teacher Lifecycle, rekonsiliasi 404 vs 403 cross-school

Status: Keputusan Tim (29 Sep 2026), merekonsiliasi kontradiksi internal pada API Contract v1.2
Bagian 15 (Teacher Lifecycle) — bukan menambah business rule baru. Lihat juga
`docs/authorization-matrix.md` Temuan #3 untuk audit lengkapnya.

## Kontradiksi yang direkonsiliasi

API Contract v1.2 Bagian 15, subsection "Idempotency & Error Cases", menulis dua baris berurutan
untuk skenario yang **sama persis** (Headmaster menonaktifkan Teacher yang ada di sekolah lain):

> - 404 — Teacher tidak ditemukan atau di luar scope actor (cross-school).
> - 403 — role selain Headmaster, atau Headmaster mencoba menonaktifkan Teacher di sekolah lain.

Baris pertama memasukkan "di luar scope actor (cross-school)" ke dalam 404. Baris kedua, pada
paragraf yang sama, eksplisit menyebut skenario cross-school sebagai 403. Keduanya tidak bisa benar
bersamaan untuk kasus yang sama.

## Keputusan final

Untuk `POST /api/v1/teachers/{id}/inactive`:

| Kondisi | Status |
|---|---|
| `id` Teacher benar-benar tidak eksis di tabel `teachers` manapun | **404** |
| `id` Teacher eksis, tapi `school_id`-nya berbeda dari sekolah Headmaster (cross-school) | **403** |
| Role selain Headmaster | **403** |

**403 yang menang** untuk skenario cross-school, bukan 404. Alasan:

1. **Konsistensi dengan endpoint sejenis.** `POST /api/v1/teachers/{id}/reset-password` — endpoint
   WRITE lain dengan scope sama persis (Headmaster, own school, target Teacher by id) — sudah
   direkonsiliasi ke 403 untuk kasus cross-school. `inactive()` punya karakteristik identik (WRITE,
   scope jelas dari path `{id}`), sehingga seharusnya berperilaku sama untuk kasus yang sama.
2. **Alasan "cegah enumerasi" (§26) tidak berlaku di sini.** Konvensi 404-untuk-scope-mismatch di
   proyek ini secara konsisten dipakai untuk mencegah resource-existence-leak pada operasi **baca**
   (`GET`-by-id) — supaya penyerang tidak bisa membedakan "tidak ada" dari "ada tapi bukan milik
   Anda" lewat enumerasi. `inactive()` adalah operasi **tulis** (`POST`) dengan scope yang sudah
   jelas dari path `{id}` itu sendiri; justifikasi "cegah enumerasi" GET tidak relevan untuk
   endpoint tulis.
3. **403 lebih jujur bagi pengguna.** Headmaster yang salah memasukkan id (Teacher-nya memang ada,
   cuma di sekolah lain) mendapat sinyal yang benar soal alasan penolakan, dibanding 404 yang
   menyamarkannya seolah Teacher itu tidak ada sama sekali.

## Implementasi

`app/Services/School/TeacherService.php::inactive()` diubah dari me-reuse `show()` (yang memang
SENGAJA tetap 404 untuk `GET /teachers/{id}`, tidak diubah oleh addendum ini) menjadi:
1. `findTeacherOrFail($id)` — fetch global by id, 404 murni kalau benar-benar tidak ada.
2. Cek eksplisit `$teacher->school_id !== $headmasterSchoolId` → 403.

Pola ini identik dengan `resetPassword()` di file yang sama.

## Cakupan

Addendum ini **hanya** berlaku untuk `POST /teachers/{id}/inactive`. Tidak mengubah:
- `GET /teachers/{id}` (`show()`) — tetap 404 untuk kedua kasus (tidak ada / di luar scope), sesuai
  konvensi GET-by-id §26.
- `POST /teachers/{id}/reset-password` — sudah 403, tidak disentuh (sudah benar, jadi acuan).
- Endpoint lifecycle Student (`inactive`/`graduate`/`bulk-graduate`) — di luar scope, tidak diaudit
  ulang oleh addendum ini.
