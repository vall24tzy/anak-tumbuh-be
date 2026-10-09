# TEST-001 — Log

**Status:** Selesai — deliverable Postman Collection siap jalan (TODO -> DONE*)
**PIC:** Orang 1
**Tanggal:** 27 Sep 2026

*Catatan status (diperbarui 9 Okt 2026): task ini output-nya Postman Collection (bukan patch kode PHP).
Collection kini 124 request, Folder 0-8, sudah dijalankan Orang 1 pada 7-8 Okt 2026; laporan Orang 1:
PASS, 0 Failed. Itu laporan, BUKAN bukti tersimpan: tidak ada file hasil Runner di repo, jadi jangan
dianggap lulus dari keberadaan file ini saja. Riwayat: versi awal (27 Sep 2026) = 86 request, 8 folder,
belum pernah dijalankan karena tidak ada PostgreSQL/server live di sandbox pembuatnya.

## Ringkasan

- File: TEST-001-ANAKTUMBUH-Postman-Collection.json (Postman v2.1 schema, valid JSON, sudah dicek programatis)
- File: TEST-001-traceability.md -- pemetaan tiap item AC (Dokumen v4.0 sec14) ke: [Postman] request otomatis, [Code Review] diverifikasi lewat baca kode (bukan run), atau [Manual/Env] butuh setup di luar Postman (file besar, concurrency, rate-limit timing)
- Folder 0 (bootstrap data pakai endpoint Orang 2: School/Headmaster/AcademicYear/Rombel/Teacher/TeacherClassAssignment) BUKAN scope TEST-001, cuma supaya folder 1-7 bisa jalan end-to-end mandiri
- Folder 1-7 = 7 domain modul Orang 1: Student, Student Class Assignment, Student Lifecycle, Habit/Bermasyarakat, QR, Import Siswa, Dashboard Student/Teacher
- Folder 8 (21 request) = regresi lintas-modul Stage 1-5 + F-53 (F-09/F-11, F-15/F-10, F-18, F-21, F-06), di luar cluster Siswa & Kebiasaan, tanpa request login
- Setiap request pakai pm.test() cek status code + assertion field kunci (poin_awarded, error.code, protected_field, dst.) -- bukan cuma cek 200/403 doang
- 3 item AC butuh setup manual tambahan di luar kemampuan 1 request Postman: concurrency race unique-habit-constraint, file import >200 baris/>5MB, rate-limit timing (butuh Collection Runner 6x request cepat) -- semua diberi catatan eksplisit di request/dokumen, tidak diam-diam dilewati
- 6 item AC diverifikasi lewat code review langsung (bukan request HTTP) krn sifatnya pembuktian ketiadaan validasi (habit_date lampau, weekend/holiday, max 7/hari) atau mekanisme internal (transaksi atomik poin, poin_awarded computed bukan kolom DB)

## Revisi (Okt 2026)

- **Sinkron Stage 1-5 + F-53** (`d62dbb8`, 7 Okt 2026): 86 → 124 request; assertion usang diperbaiki (mis. `protected_field` kini diperiksa di `details[].reason` dan `error.code`; import >100 baris tidak lagi `pending` karena F-38) dan Folder 8 ditambahkan. Rincian: `docs/testing/TEST-001-traceability.md`.
- **Penghapusan literal password Super Admin:** request "Login Super Admin" kini memakai `{{sa_username}}` dan `{{sa_password}}` (type secret, kosong di file). Password lama tetap ada di riwayat git (collection `7396a1a`; juga default seeder `25cc67f`, dihapus dari seeder di `2e1afb1`) dan dianggap bocor; rotasi di lingkungan yang memakainya = keputusan pemilik (bagian SECURITY-001/DEPLOYMENT-001). Riwayat git tidak ditulis ulang.
- **Batas import 200 baris** (`082d5d0`, keputusan pemilik; assertion TEST-001 disesuaikan di `6974b29`): request "file > 1.000 baris" menjadi "file 201 baris (satu di atas batas 200)".

## Status Orang 2 (info manual, Task Board tidak diupdate)

- SETUP-001, ARCH-001, AUTH-001, AUTH-002, AUTH-003 (+fix 403/404 reset-password), UPLOAD-001, SCHOOL-001, HEADMASTER-001, TEACHER-001, ACADEMICYEAR-001, ROMBEL-001, TEACHER-ASSIGNMENT-001, TEACHER-LIFECYCLE-001, DASHBOARD-001, fix 403/404 Rombel/AcademicYear/TeacherAssignment -- selesai
- DASHBOARD-002 (Headmaster+SA), IMPORT-002 -- selesai (sebelum LEVEL-RANKING-001 dibuat)
- LEVEL-RANKING-001 (Orang 1) -- selesai; dependency "hingga TEST-001/TEST-002" dikonfirmasi bukan blocker (lihat addendum Level & Ranking)
