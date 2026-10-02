# AUTHORIZATION-MATRIX — Log

**Status:** Selesai — audit lengkap 61 baris, 3 temuan dilaporkan (belum diperbaiki)
**PIC:** Bersama
**Tanggal:** 29 Sep 2026

## Ringkasan

- docs/authorization-matrix.md dibuat: 61/61 baris Matrix v5.2 dipetakan ke endpoint API Contract + Controller/Service yang menegakkannya
- 58 baris: kode sudah konsisten dengan Matrix, diverifikasi lewat pembacaan kode (scope school_id/rombel_id/active-assignment ditegakkan eksplisit di Service, bukan cuma role middleware)
- Cross-cutting (#56-60): REMOVE items (grade advancement, recreate identity, class-movement-via-CRUD, forgot-password, refresh-token) dikonfirmasi bersih -- tidak ada route/kode sama sekali, bukan cuma didisable
- 3 temuan dilaporkan (lihat docs/authorization-matrix.md bagian Temuan):
  1. Row #27 'resolve rombel from name' -- deviasi sudah terdokumentasi sejak IMPORT-001 (rombel_id request-level, bukan per-baris), dicatat ulang di sini demi traceability
  2. Row #35 'daily lock' -- sudah dihapus oleh Keputusan Tim 'HABIT IMMUTABLE' sebelumnya, sudah terdokumentasi di addendum Habit-GET, dicatat ulang di sini
  3. TeacherService::inactive() pakai 404 utk cross-school (via show()) -- DIKONFIRMASI TIM & SUDAH DIPERBAIKI (lihat update di bawah)

## Update 29 Sep 2026 — Temuan #3 dikonfirmasi tim, sudah diperbaiki

- API Contract sec15 ternyata kontradiktif sejak awal untuk skenario ini (menulis 404 DAN 403 utk cross-school di baris berurutan) -- bukan cuma kode yang menyimpang dari kontrak bersih, didokumentasikan sbg SOURCE A2 di docs/authorization-matrix.md Temuan #3
- Tim memutuskan 403 yang menang (konsisten dgn resetPassword() yg sudah diperbaiki, alasan "cegah enumerasi" GET §26 tidak relevan utk endpoint WRITE, 403 lebih jujur ke Headmaster)
- Kode: app/Services/School/TeacherService.php::inactive() diubah dari reuse show() (404) jadi findTeacherOrFail() + cek school_id eksplisit (403) -- pola identik resetPassword(). show() dan resetPassword() sendiri TIDAK disentuh.
- Test baru: tests/Feature/School/TeacherLifecycleScopeTest.php (4 case: same-school 200, cross-school 403, id-tidak-ada tetap 404, cascade teacher_class_assignments tetap jalan setelah fix)
- Addendum resmi: docs/addendum/API-Contract-v1.2-Addendum-Teacher-Lifecycle-403.md
- Temuan #1 dan #2: HANYA update redaksi dokumen (sesuai saran awal), TIDAK ADA kode yang diubah untuk keduanya -- dikonfirmasi tim, tidak perlu tindakan lebih lanjut di luar update redaksi Matrix/API Contract (dilakukan terpisah oleh pemilik dokumen tsb, bukan bagian task kode ini)

## Status Orang 2 (info manual, Task Board tidak diupdate)

- Seluruh task Orang 2 s.d. IMPORT-002, DASHBOARD-001/002 (+revisi headmaster_count) -- selesai
