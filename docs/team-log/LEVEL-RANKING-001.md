# LEVEL-RANKING-001 — Log

**Status:** Selesai — REVISI 3 diterapkan (keputusan final tim, bukan lagi sementara)
**PIC:** Orang 1
**Tanggal:** 28 Sep 2026, direvisi 28 Sep 2026

## Ringkasan (kondisi akhir setelah revisi 3)

- Migration gamification_levels + seed 101 baris (angka persis dokumen 'Analisis & Threshold Level ANAKTUMBUH') -- TIDAK berubah di revisi 3
- LevelService::levelForPoin() -- TIDAK berubah. Ditambah levelsForPoins() batch (1 query gamification_levels per request, dipakai Ranking, bukan per baris)
- GET /gamification/me -- TIDAK ada perubahan kode (Student 200, non-Student 403); test dipecah jadi 3 method terpisah (Teacher/HM/SA)
- GET /rankings/rombel & /rankings/angkatan -- REVISI TOTAL:
  * Response: student_id, name, rombel, poin, level (field 'level' bukan 'gamification_level', keputusan tim)
  * Query rombels di-join (rombel dari assignment aktif, bukan classroom string)
  * Pagination penuh (page/per_page/meta), reuse StudentHabitService::paginationMeta()
  * Sort: poin DESC, name ASC, id ASC (tie-break teknis)
  * Ranking dibatasi Academic Year AKTIF sekolah caller saja -- academic_year_id selain itu 403 utk SEMUA role (sebelumnya Student boleh tahun lain milik sekolah sendiri -- sekarang tidak)
  * Urutan pengecekan: academic_year_id lalu rombel_id divalidasi SEBELUM hasil-kosong diputuskan (Teacher/Student tanpa assignment tapi param salah -> tetap 403, bukan 200 kosong)
  * Teacher tanpa active assignment: 403 -> 200 kosong+meta (bukan lagi error)
  * Caller tanpa konteks kelas + rombel_id apa pun -> 403 (asumsi tim)
- DashboardService, migration/schema, threshold seeder, 7 service Headmaster::where('user_id') tanpa filter status -- SEMUA TIDAK disentuh (instruksi eksplisit)
- docs/addendum/API-Contract-v1.2-Addendum-Level-Ranking.md dibuat (8 poin, termasuk poin 6: konflik sadar menampilkan name vs Dokumen v4.0 sec9/Matrix #9, Matrix & Dokumen v4.0 perlu diamandemen resmi -- di luar scope task ini)
- docs/db-gap-analysis.md NOTE-11 ditulis ulang total (revisi 3) -- gap (a)-(e), termasuk (e) gap Headmaster inactive lintas-task yang SENGAJA tidak diperbaiki di sini

## Static verification (dilakukan di sandbox ini)

- Semua file baru/ubahan: cek keseimbangan kurung {}/()/[] via script Python (pengganti php -l, tidak ada PHP di sandbox) -- semua OK
- Traced manual: query order di resolveOwnRombel() memvalidasi academic_year_id SEBELUM rombel di-resolve, utk Teacher maupun Student -- sesuai spesifikasi urutan
- Traced manual: levelsForPoins() query GamificationLevel::pluck() sekali per pemanggilan, dipanggil sekali per request (paginateWithLevel), bukan di dalam loop per baris

## Runtime verification: BELUM DIJALANKAN (tidak ada PHP/Postgres live di sandbox penulis)

Perlu dijalankan manual di dev env:
1. php artisan migrate:fresh --seed
2. php artisan test --filter=GamificationLevelDataTest
3. php artisan test --filter=GamificationLevelTest
4. php artisan test --filter=RankingTest (22 test case, termasuk: response 5-field, sorting poin+nama, tie-break id, pagination meta/422/halaman-2, 3 skenario hasil-kosong, academic_year sama/lampau/sekolah-lain/tanpa-tahun-aktif, urutan-pengecekan Teacher-tanpa-assignment, caller-tanpa-konteks+rombel_id, HM/SA 403, rombel_id milik-sendiri vs lain, cross-school, level per baris + batas threshold, query gamification_levels 1x, param diabaikan)

## Daftar asumsi & keputusan teknis (untuk direview tim)

- A.4 asumsi: sekolah tanpa Academic Year aktif + param dikirim -> 403; caller tanpa konteks kelas + rombel_id -> 403
- Tie-break id ASC adalah keputusan teknis (bukan business rule)
- Meta pada hasil kosong memakai paginator Laravel asli (whereRaw 1=0 lalu paginate), bukan ditulis manual
- Nama field 'level' (bukan 'gamification_level') adalah keputusan tim, didokumentasikan di addendum poin 2
- KONFLIK SoT poin C.6/addendum poin 6 menunggu amandemen resmi Authorization Matrix baris #9 dan Dokumen v4.0 sec9 -- belum dilakukan, di luar scope task ini

## Status Orang 2 (info manual, Task Board tidak diupdate)

- Seluruh task Orang 2 s.d. DASHBOARD-001/002 (+revisi headmaster_count active only) dan IMPORT-002 -- selesai
