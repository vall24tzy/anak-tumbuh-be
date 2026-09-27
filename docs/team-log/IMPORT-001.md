# IMPORT-001 — Log

**Status:** Selesai (REWORK -> DONE)
**PIC:** Orang 1
**Tanggal:** 26 Sep 2026

## Ringkasan

- Route lama students/import/* (role:1,2,3, SA/HM diizinkan) diganti total jadi student-imports/* (Teacher only), sesuai API Contract v1.2 §18
- rombel_id jadi parameter REQUEST-LEVEL (bukan kolom Excel per-baris) -- divalidasi SEKALI di awal (assertOwnActiveRombel), gagal = 403 utk SELURUH request sebelum baris diproses
- Template final: Nama Lengkap, NISN, Gender saja -- Classroom/Grade Level/Email dihapus total (grade_level murni dari rombels.grade_level)
- Rule validasi 'unique:students,nisn' DIHAPUS -- NISN existing (sekolah sama) di-REUSE, bukan direjek (Dokumen v4.0 sec6.2, REMOVE 'Import selalu reject existing NISN')
- Setiap baris valid diproses lewat StudentService::store() APA ADANYA (tidak dimodifikasi) -- reuse penuh: NISN reuse/cross-school-reject, duplicate_assignment/assignment_conflict, DB transaction per-baris (rollback otomatis), revalidasi authorization penuh di commit -- semua gratis dari kode yang sudah diaudit
- ImportService.php (generic engine) TIDAK disentuh sama sekali -- teknik 'smuggling' RuntimeException(json{code,field,message}) dipakai di StudentImportService::processRow() supaya row-error tetap punya code terstruktur tanpa mengubah file generik (tetap reusable utk IMPORT-002/Teacher nanti)
- Response di-normalize ke shape final API Contract sec9/sec18: {total_rows, processed_rows, successful_rows, rejected_rows, errors:[{row,field,code,message}]}
- NOTE-09 (docs/db-gap-analysis.md): endpoint status/polling queue dipertahankan sbg technical necessity, tidak ada di API Contract v1.2, belum diformalkan jadi addendum resmi
- Trade-off sadar: StudentService::store() query TeacherScopeService per-baris (N+1-ish utk file besar) -- tidak diperbaiki krn perbaikannya butuh ubah StudentService.php (di luar file-focus task ini)

## Test Case (manual review -- tidak ada PHP/DB live di sandbox, perlu dijalankan ulang di dev env)

tests/Feature/Student/StudentImportTest.php (8 test case):

### Positive
- Template tidak punya kolom Classroom/Grade Level/Email
- Preview valid rows -> valid_count sesuai
- Commit NISN baru -> Student+User+QrToken+Assignment ke-create
- Commit NISN existing sekolah sama -> di-reuse (bukan bikin User/Student baru)

### Negative
- Commit row dgn assignment aktif sama (rombel+tahun sama) -> code duplicate_assignment
- Commit row validasi gagal (nama kosong) -> code validation_failed, field 'name'
- Commit rombel_id bukan own active rombel -> 403 SELURUH request, tidak ada row diproses
- Super Admin coba akses endpoint import -> 403 (role:teacher middleware)

## Status Orang 2 (info manual, Task Board tidak diupdate)

- SETUP-001, ARCH-001, AUTH-001, AUTH-002, AUTH-003 (+fix 403/404 reset-password), UPLOAD-001, SCHOOL-001, HEADMASTER-001, TEACHER-001, ACADEMICYEAR-001, ROMBEL-001, TEACHER-ASSIGNMENT-001, TEACHER-LIFECYCLE-001 -- selesai

## NOTE-10 (26 Sep 2026)

- Fix: `StudentImportController::status()` sekarang punya 5 field (tambah `processed_rows`),
  sebelumnya cuma 4 — tidak konsisten dengan response `commit`. Detail: `docs/db-gap-analysis.md` NOTE-10.
- Catatan: tabel `import_batches` tidak ada di 16 Final Entity List DB Design v1.5 §4, tapi tetap
  dipakai (tanpa perubahan skema) karena technical necessity untuk status/polling queue — sudah
  diinstruksikan Task Board poin 6. Bukan skema baru yang dikarang, tabel lama dipakai ulang.
