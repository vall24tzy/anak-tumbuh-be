# API Contract v1.2 — Addendum: Pagination `GET /students` dan `GET /student-class-assignments` (menutup C-02 MERGE-001)

**Tanggal:** 10 Okt 2026 · **Sumber keputusan:** catatan Orang 2 untuk Orang 1, butir 1 (Opsi A: ikuti Contract §7).
**Status:** kode dan test ditulis; **belum dijalankan** (tidak ada PHP/PostgreSQL/Postman di sandbox penyusun).

## 1. Keputusan

Kedua endpoint list mengikuti konvensi API Contract v1.2 §7:

- Query `page` (integer >= 1, default 1) dan `per_page` (integer 1-100, default 20).
- `per_page` di atas 100 (atau 0, atau `page` 0) ditolak **422 `validation_failed`**, tidak dipotong diam-diam. Sama dengan `GET /habits/entries` dan ranking.
- Respons: `{ "data": [...], "meta": { "page", "per_page", "total", "total_pages" } }`.

## 2. Yang tidak berubah

- Scope per role (SA global, HM sekolah sendiri, Teacher own active rombel, Student own) tidak berubah; `total` dihitung setelah scope.
- Teacher tanpa active assignment tetap 200 dengan daftar kosong (kini `data: []` dan `meta.total: 0`).
- Tidak ada filter, `sort`, atau `order` baru: kontrak tidak mewajibkannya. Urutan tetap `id` naik (deterministik agar halaman tidak bergeser), bukan parameter client.
- `GET /students/{id}/class-assignments` (riwayat satu Student) tidak diubah, bukan daftar tak berbatas.
- Bentuk tiap elemen `data` tidak berubah (`StudentResource`, `StudentClassAssignmentResource`).

## 3. Dampak

Client yang sebelumnya membaca seluruh daftar dalam satu respons kini hanya menerima 20 baris pertama kecuali meminta `per_page` atau halaman berikutnya. Collection Postman TEST-001 dan MERGE-001 sudah diperiksa: request yang membaca daftar ini (TEST-001 "hasil import trim" dan MERGE-001 2.03/2.04) tetap lolos secara desain; TEST-001 diberi `per_page=100` pada yang pertama.

## 4. Kode

`PaginatedListRequest`, `StudentService::index()`, `StudentClassAssignmentService::index()`, `StudentController::index()`, `StudentClassAssignmentController::index()`. Test: `tests/Feature/Student/StudentListPaginationTest.php` (9 test).
