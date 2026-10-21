# INTEG-O2 — Hasil run koleksi Postman (INTEG-TEST-002)

**Tanggal run:** 21 Oktober 2026 pukul 11:40 WIB · **Penyusun:** Orang 2 · **Koleksi:** `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` (folder `INTEG-O2`, 42 request)

## Ringkasan

- Folder **INTEG-O2: 42 request, 85 assertion, 0 gagal** (ME 6, DASHHM 4, SCHOOL 15, TEACHER 17).
- Dijalankan dengan newman dalam satu sesi: folder 0 dan folder 1 lebih dulu (membuat sekolah uji, token, dan data prasyarat), lalu folder INTEG-O2.
- Lingkungan: newman 6.2.3; PHP 8.2.12, PostgreSQL 17.11, database baru (migrate:fresh --seed), CACHE_STORE=database, --delay-request 13000. Isi file ini hanya nama request, kode HTTP, dan hasil assertion (tanpa token, password, atau isi respons).

## Rincian folder INTEG-O2

| No | Task | Request | HTTP | Assertion lulus |
|---|---|---|---|---|
| 1 | ME | GET /auth/me - Student A -> 200, memuat rombel + photo (Matrix #67) | 200 | 5/5 |
| 2 | ME | GET /dashboard/student - Student A -> 200, rombel + photo SAMA dengan /auth/me | 200 | 3/3 |
| 3 | ME | GET /auth/me - Teacher A -> 200, TANPA rombel/photo | 200 | 2/2 |
| 4 | ME | GET /auth/me - Headmaster -> 200, TANPA rombel/photo | 200 | 2/2 |
| 5 | ME | GET /auth/me - tanpa token -> 401 | 401 | 2/2 |
| 6 | ME | GET /dashboard/student - Teacher A -> 403 (tidak berubah) | 403 | 1/1 |
| 7 | DASHHM | GET /dashboard/headmaster - Headmaster -> 200, memuat total_siswa/total_guru/total_rombel (Matrix #68) | 200 | 4/4 |
| 8 | DASHHM | GET /dashboard/headmaster - Teacher A -> 403 | 403 | 1/1 |
| 9 | DASHHM | GET /dashboard/headmaster - Student A -> 403 | 403 | 1/1 |
| 10 | DASHHM | GET /dashboard/headmaster - tanpa token -> 401 | 401 | 2/2 |
| 11 | SCHOOL | POST /schools - Super Admin -> 201 (menyiapkan sekolah uji; nama unik per run) | 201 | 1/1 |
| 12 | SCHOOL | PATCH /schools/{id} - Super Admin -> 200, nama berubah (Matrix #65) | 200 | 2/2 |
| 13 | SCHOOL | GET /schools/{id} - Super Admin -> 200, memuat nama baru (endpoint lama tidak berubah) | 200 | 2/2 |
| 14 | SCHOOL | PATCH /schools/{id} - nama sama -> 200, nama tidak berubah (D-14: tanpa baris audit, dibuktikan PHPUnit) | 200 | 2/2 |
| 15 | SCHOOL | PATCH /schools/{id} - beda huruf besar/kecil saja -> 200, berubah (D-14) | 200 | 2/2 |
| 16 | SCHOOL | PATCH /schools/{id} - name kosong -> 422 validation_failed | 422 | 2/2 |
| 17 | SCHOOL | PATCH /schools/{id} - name 151 karakter -> 422 validation_failed | 422 | 2/2 |
| 18 | SCHOOL | PATCH /schools/{id} - name valid + address -> 422 protected_field | 422 | 3/3 |
| 19 | SCHOOL | PATCH /schools/{id} - hanya address -> 422 protected_field | 422 | 2/2 |
| 20 | SCHOOL | GET /schools/{id} - Super Admin -> 200, nama tidak berubah oleh request yang ditolak | 200 | 2/2 |
| 21 | SCHOOL | PATCH /schools/999999 - Super Admin -> 404 | 404 | 1/1 |
| 22 | SCHOOL | PATCH /schools/{id} - Headmaster -> 403 | 403 | 2/2 |
| 23 | SCHOOL | PATCH /schools/{id} - Teacher A -> 403 | 403 | 2/2 |
| 24 | SCHOOL | PATCH /schools/{id} - Student A -> 403 | 403 | 2/2 |
| 25 | SCHOOL | PATCH /schools/{id} - tanpa token -> 401 | 401 | 2/2 |
| 26 | TEACHER | POST /teachers - Headmaster -> 201 (menyiapkan guru uji; NIP unik per run) | 201 | 1/1 |
| 27 | TEACHER | PATCH /teachers/{id} - Headmaster -> 200, name + gender berubah, nip tidak (Matrix #66) | 200 | 2/2 |
| 28 | TEACHER | GET /teachers/{id} - Headmaster -> 200, memuat nilai baru (endpoint lama tidak berubah) | 200 | 2/2 |
| 29 | TEACHER | PATCH /teachers/{id} - nilai sama -> 200, tidak berubah (D-14: tanpa baris audit, dibuktikan PHPUnit) | 200 | 2/2 |
| 30 | TEACHER | PATCH /teachers/{id} - name valid + nip -> 422 protected_field | 422 | 3/3 |
| 31 | TEACHER | PATCH /teachers/{id} - status -> 422 protected_field | 422 | 2/2 |
| 32 | TEACHER | PATCH /teachers/{id} - password -> 422 protected_field | 422 | 2/2 |
| 33 | TEACHER | PATCH /teachers/{id} - gender di luar laki-laki/perempuan -> 422 validation_failed | 422 | 2/2 |
| 34 | TEACHER | PATCH /teachers/{id} - name kosong -> 422 validation_failed | 422 | 2/2 |
| 35 | TEACHER | GET /teachers/{id} - Headmaster -> 200, data tidak berubah oleh request yang ditolak | 200 | 2/2 |
| 36 | TEACHER | PATCH /teachers/999999 - Headmaster -> 404 | 404 | 1/1 |
| 37 | TEACHER | PATCH /teachers/{id} - Teacher A -> 403 | 403 | 2/2 |
| 38 | TEACHER | PATCH /teachers/{id} - Student A -> 403 | 403 | 2/2 |
| 39 | TEACHER | PATCH /teachers/{id} - Super Admin -> 403 | 403 | 2/2 |
| 40 | TEACHER | PATCH /teachers/{id} - tanpa token -> 401 | 401 | 2/2 |
| 41 | TEACHER | POST /teachers/{id}/inactive - Headmaster -> 200 (menyiapkan kasus guru inactive) | 200 | 2/2 |
| 42 | TEACHER | PATCH /teachers/{id} - guru inactive -> 409 teacher_inactive_readonly | 409 | 2/2 |
