# TEST-001 — Log

**Status:** Selesai — deliverable Postman Collection siap jalan (TODO -> DONE*)
**PIC:** Orang 1
**Tanggal:** 27 Sep 2026

*Catatan status: task ini output-nya Postman Collection (bukan patch kode PHP). Collection SUDAH
DIBUAT lengkap (86 request, 8 folder, 7 domain modul Orang 1 + 1 folder bootstrap), TAPI BELUM
PERNAH DIJALANKAN — tidak ada PostgreSQL/server live di sandbox pembuatnya. Perlu dijalankan nyata
di dev env oleh tim, hasil pass/fail sebenarnya dilaporkan terpisah, JANGAN dianggap lulus dari
keberadaan file ini saja.

## Ringkasan

- File: TEST-001-ANAKTUMBUH-Postman-Collection.json (Postman v2.1 schema, valid JSON, sudah dicek programatis)
- File: TEST-001-traceability.md -- pemetaan tiap item AC (Dokumen v4.0 sec14) ke: [Postman] request otomatis, [Code Review] diverifikasi lewat baca kode (bukan run), atau [Manual/Env] butuh setup di luar Postman (file besar, concurrency, rate-limit timing)
- Folder 0 (bootstrap data pakai endpoint Orang 2: School/Headmaster/AcademicYear/Rombel/Teacher/TeacherClassAssignment) BUKAN scope TEST-001, cuma supaya folder 1-7 bisa jalan end-to-end mandiri
- Folder 1-7 = 7 domain modul Orang 1: Student, Student Class Assignment, Student Lifecycle, Habit/Bermasyarakat, QR, Import Siswa, Dashboard Student/Teacher
- Setiap request pakai pm.test() cek status code + assertion field kunci (poin_awarded, error.code, protected_field, dst.) -- bukan cuma cek 200/403 doang
- 3 item AC butuh setup manual tambahan di luar kemampuan 1 request Postman: concurrency race unique-habit-constraint, file import >1000 baris/>5MB, rate-limit timing (butuh Collection Runner 6x request cepat) -- semua diberi catatan eksplisit di request/dokumen, tidak diam-diam dilewati
- 6 item AC diverifikasi lewat code review langsung (bukan request HTTP) krn sifatnya pembuktian ketiadaan validasi (habit_date lampau, weekend/holiday, max 7/hari) atau mekanisme internal (transaksi atomik poin, poin_awarded computed bukan kolom DB)

## Status Orang 2 (info manual, Task Board tidak diupdate)

- SETUP-001, ARCH-001, AUTH-001, AUTH-002, AUTH-003 (+fix 403/404 reset-password), UPLOAD-001, SCHOOL-001, HEADMASTER-001, TEACHER-001, ACADEMICYEAR-001, ROMBEL-001, TEACHER-ASSIGNMENT-001, TEACHER-LIFECYCLE-001, DASHBOARD-001, fix 403/404 Rombel/AcademicYear/TeacherAssignment -- selesai
- DASHBOARD-002 (Headmaster+SA), IMPORT-002 -- selesai (sebelum LEVEL-RANKING-001 dibuat)
- LEVEL-RANKING-001 (Orang 1) -- selesai; dependency "hingga TEST-001/TEST-002" dikonfirmasi bukan blocker (lihat addendum Level & Ranking)
