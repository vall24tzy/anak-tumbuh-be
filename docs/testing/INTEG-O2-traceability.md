# INTEG-O2 — Traceability: Acceptance Criteria INTEG-TEST-002 vs Cakupan Test

Dokumen ini memetakan acceptance criteria INTEG-TEST-002 (Task Board INTEG v1.5) untuk empat task fitur Orang 2
(INTEG-ME-001, INTEG-DASHHM-001, INTEG-SCHOOL-001, INTEG-TEACHER-001) ke test PHPUnit dan request Postman folder
`INTEG-O2` di `TEST-001-ANAKTUMBUH-Postman-Collection.json`. Disusun 21 Okt 2026 (Orang 2).

**Status bukti (baca dulu).** Tabel di bawah adalah hasil pembacaan nama test dan status HTTP yang diuji, bukan hasil
eksekusi. Hasil eksekusi yang dilaporkan Orang 2: `php artisan test` **622 passed (3485 assertions)** (suite penuh,
termasuk dua test yang ditambah INTEG-TEST-002 di bagian 3, setelah pull perubahan Orang 1); Postman folder
`INTEG-O2` **85 passed, 0 failed** (Runner Postman, environment none; juga run newman 6.2.3, hasilnya tersimpan di
`docs/testing/INTEG-O2-runner-result.md`).

Legenda: **[PHPUnit]** nama method test; **[Postman]** potongan judul request di folder `INTEG-O2`; **N/A** tidak
berlaku untuk endpoint itu (alasan dicatat); **[Dibuktikan PHPUnit saja]** tidak praktis di Postman (alasan dicatat).

## 1. Verifikasi kelengkapan request Postman

Diperiksa dengan skrip terhadap file koleksi (bukan menjalankan koleksi):

| Pemeriksaan | Hasil |
|---|---|
| Request di folder `INTEG-O2` per task | INTEG-ME-001: 6, INTEG-DASHHM-001: 4, INTEG-SCHOOL-001: 15, INTEG-TEACHER-001: 17; total 42, tidak ada request tanpa label task |
| Setiap request punya minimal satu `pm.test` | Ya (0 request tanpa assertion) |
| Jumlah baris `pm.test` statis di folder | 85 = jumlah assertion pada run Runner yang dilaporkan (85 passed) |
| Variabel koleksi yang dipakai folder dideklarasikan | Ya; variabel `integ_*` dibuat oleh request di folder itu sendiri |
| Variabel dari folder lain | `sa_token`, `hm_token`, `teacher_a_token`, `student_a_token`, `school_id` dibuat Folder 0 dan 1; folder `INTEG-O2` tidak dapat dijalankan sendirian di database kosong |
| Request lama diubah | Tidak (folder `INTEG-O2` hanya ditambah; nama folder diperpanjang per task) |
| Status HTTP yang diuji | ME: 200, 401, 403; DASHHM: 200, 401, 403; SCHOOL: 200, 201, 401, 403, 404, 422; TEACHER: 200, 201, 401, 403, 404, 409, 422 |

Urutan jalan: `migrate:fresh --seed` -> Folder 0 -> `cache:clear` -> Folder 1 -> `cache:clear` -> folder `INTEG-O2`
(urutan request di dalam folder harus dijaga; `sa_password` diisi di variabel koleksi, environment "No Environment").

## 2. Matriks AC per task

### INTEG-ME-001 — `GET /auth/me` (Student) dan `GET /dashboard/student`

| AC | Cakupan |
|---|---|
| Happy path | [PHPUnit] `student_melihat_rombel_dan_photo_di_auth_me_dan_dashboard_dengan_nilai_sama`, `field_lama_auth_me_student_tidak_berubah_...`, `field_lama_dashboard_student_tidak_berubah_...` · [Postman] `GET /auth/me - Student A -> 200`, `GET /dashboard/student - Student A -> 200` |
| 401 | [PHPUnit] `AuthMeTest::me_tanpa_token_dan_token_invalid_401` (sudah ada), `dashboard_student_tetap_ditolak_untuk_role_lain_dan_tanpa_token` · [Postman] `GET /auth/me - tanpa token -> 401` |
| 403 role salah | [PHPUnit] `dashboard_student_tetap_ditolak_untuk_role_lain_dan_tanpa_token` · [Postman] `GET /dashboard/student - Teacher A -> 403`. `/auth/me` terbuka untuk semua role: N/A |
| 404 lintas sekolah (D-02) | N/A: endpoint hanya memuat data subjek token; `student_hanya_melihat_rombel_dan_photo_diri_sendiri` membuktikan tidak ada data Student lain |
| Validasi 422 | N/A: GET tanpa input |
| audit_logs | N/A: tidak ada aksi tulis |
| Dua assignment aktif | [Dibuktikan PHPUnit saja: butuh dua tahun ajaran] `dua_assignment_aktif_di_dua_tahun_ajaran_memilih_rombel_tahun_yang_active`, `rombel_mengikuti_tahun_ajaran_yang_active_bila_pergantian_tahun_terjadi` |
| Foto terproteksi (D-08) | [PHPUnit] `photo_adalah_url_terproteksi_bukan_path_berkas_atau_url_publik`, `url_photo_bisa_dibuka_siswa_pemilik_dan_ditolak_tanpa_token`, `StudentPhotoPrivacyTest::auth_me_dan_dashboard_student_memuat_photo_hanya_sebagai_url_terproteksi` · [Postman] pola URL `photo` pada `GET /auth/me - Student A` (data Postman tanpa foto: nilai null) |
| Role lain tidak berubah | [PHPUnit] `auth_me_role_lain_tidak_memuat_rombel_atau_photo` · [Postman] `GET /auth/me - Teacher A`, `- Headmaster` |

### INTEG-DASHHM-001 — `GET /dashboard/headmaster`

| AC | Cakupan |
|---|---|
| Happy path | [PHPUnit] `total_siswa_guru_dan_rombel_benar_dan_total_siswa_sama_dengan_jumlah_student_count`, `field_lama_tetap_dan_tiga_total_ditambahkan_di_akhir` · [Postman] `GET /dashboard/headmaster - Headmaster -> 200` |
| 401 dan 403 | [PHPUnit] `role_lain_ditolak_403_dan_tanpa_token_401` (Teacher, Student, Super Admin) · [Postman] Teacher A 403, Student A 403, tanpa token 401 (Super Admin 403: PHPUnit saja) |
| 404 lintas sekolah (D-02) | N/A: tanpa id di path; isolasi sekolah dibuktikan `headmaster_hanya_melihat_total_sekolah_sendiri` |
| Validasi 422 | N/A: GET tanpa input |
| audit_logs | N/A: tidak ada aksi tulis |
| Kasus tepi | [Dibuktikan PHPUnit saja] `tanpa_tahun_ajaran_aktif_...`, `tahun_ajaran_aktif_tanpa_rombel_...`, `guru_inactive_tidak_dihitung`, `siswa_inactive_dan_graduated_...`, `rombel_dan_siswa_tahun_ajaran_tidak_aktif_...`, `jumlah_query_tidak_naik_seiring_...` |

### INTEG-SCHOOL-001 — `PATCH /schools/{id}`

| AC | Cakupan |
|---|---|
| Happy path | [PHPUnit] `super_admin_mengubah_nama_sekolah_200_...`, `nama_baru_terlihat_di_get_sekolah_dan_di_auth_me_headmaster`, `name_150_karakter_diterima` · [Postman] `PATCH ... - Super Admin -> 200`, `GET /schools/{id} ... memuat nama baru` |
| 401 | [PHPUnit] `tanpa_token_401` · [Postman] `... - tanpa token -> 401` |
| 403 role salah | [PHPUnit] `role_selain_super_admin_403_...` (Headmaster, Teacher, Student) · [Postman] Headmaster, Teacher A, Student A -> 403 |
| 404 (D-02) | [PHPUnit] `id_tidak_ada_404_tanpa_audit` · [Postman] `PATCH /schools/999999 -> 404`. Lintas sekolah: N/A, Super Admin bersifat global |
| Validasi 422 | [PHPUnit] `name_kosong_null_hilang_atau_lebih_150_karakter_422_...`, `nama_milik_sekolah_lain_422_...`, `field_selain_name_ditolak_422_protected_field_...`, `name_hanya_spasi_422_dan_nama_tidak_berubah` (baru, bagian 3) · [Postman] name kosong, 151 karakter, name + address, hanya address |
| audit_logs (D-03, D-14, D-15) | [Dibuktikan PHPUnit saja: Postman tidak punya endpoint baca audit] `perubahan_nama_tercatat_di_audit_logs_...`, `dua_perubahan_berurutan_...`, `nama_sama_setelah_trim_...` (nol baris), `beda_huruf_besar_kecil_saja_...` (satu baris), serta `AuditLog::count() === 0` pada semua kasus 403/404/422 |
| Tanpa rate limit baru (D-04) | [PHPUnit] `tidak_ada_rate_limit_baru_pada_patch_sekolah` |

### INTEG-TEACHER-001 — `PATCH /teachers/{id}`

| AC | Cakupan |
|---|---|
| Happy path | [PHPUnit] `headmaster_mengubah_name_dan_gender_200_...`, `hanya_name_atau_hanya_gender_...`, `guru_tetap_bisa_memakai_token_dan_login_lama_...`, `endpoint_lama_get_show_dan_list_...` · [Postman] `PATCH ... - Headmaster -> 200`, `GET ... memuat nilai baru` |
| 401 | [PHPUnit] `tanpa_token_401` · [Postman] `... - tanpa token -> 401` |
| 403 role salah | [PHPUnit] `teacher_student_dan_super_admin_403_...`, `headmaster_inactive_403` · [Postman] Teacher A, Student A, Super Admin -> 403 |
| 404 lintas sekolah (D-02) | [PHPUnit] `guru_sekolah_lain_404_tanpa_perubahan_dan_tanpa_audit`, `id_tidak_ada_404`, `guru_inactive_di_sekolah_lain_tetap_404_bukan_409` · [Postman] `PATCH /teachers/999999 -> 404`. Lintas sekolah: [Dibuktikan PHPUnit saja] (tidak ada sekolah kedua di data Postman) |
| Validasi 422 | [PHPUnit] `field_terlarang_422_protected_field_...` (nip, username, status, school_id, password, user_id, id), `gender_tidak_valid_name_kosong_null_atau_151_karakter_422_...`, `name_hanya_spasi_422_dan_nilai_tidak_berubah` (baru, bagian 3) · [Postman] nip, status, password, gender salah, name kosong |
| Guru inactive (D-17) | [PHPUnit] `guru_inactive_ditolak_409_teacher_inactive_readonly_...` · [Postman] `POST /teachers/{id}/inactive`, lalu `PATCH ... guru inactive -> 409` |
| Body kosong (D-18) | [PHPUnit] `nilai_sama_sama_setelah_trim_atau_body_kosong_200_tanpa_baris_audit` |
| audit_logs (D-03, D-14, D-15) | [Dibuktikan PHPUnit saja] `perubahan_tercatat_satu_baris_audit_dengan_actor_action_dan_target_user_guru`, `beda_huruf_besar_kecil_saja_...`, `dua_perubahan_berurutan_...`, nol baris pada kasus 401/403/404/409/422 |
| Tanpa rate limit baru (D-04) | [PHPUnit] `tidak_ada_rate_limit_baru_pada_patch_guru` |

## 3. Celah yang ditemukan dan ditutup

Pembacaan matriks di atas menemukan satu celah, pada validasi `name` di dua endpoint tulis: tidak ada test untuk `name`
yang hanya berisi spasi (setelah `TrimStrings` dan `ConvertEmptyStringsToNull` nilainya menjadi null dan harus gagal
`required`; hal ini juga mendasari aturan "sama setelah trim" pada D-14). Ditutup dengan dua test baru, tanpa mengubah
kode:

- `SchoolUpdateTest::name_hanya_spasi_422_dan_nama_tidak_berubah`
- `TeacherUpdateTest::name_hanya_spasi_422_dan_nilai_tidak_berubah`

Keduanya lulus sebagai bagian suite penuh (622 passed). Tidak ada celah lain terhadap AC yang ditemukan pada pembacaan
ini; item bertanda N/A dan "dibuktikan PHPUnit saja" di atas adalah keterbatasan jenis endpoint atau Postman, bukan celah.

## 4. Keterbatasan

- Matriks ini tidak membuktikan bahwa test benar-benar lulus; itu dibuktikan oleh run `php artisan test` dan Runner/newman.
- Baris `audit_logs` tidak dapat dibaca lewat API, sehingga bukti audit hanya dari PHPUnit.
- File hasil run folder `INTEG-O2` (D-12) tersimpan di `docs/testing/INTEG-O2-runner-result.md` (newman, karena
  Export Results Postman berbayar); tidak ada pengecualian D-12. Run newman yang sama memuat satu assertion gagal di
  Folder 0 (bukan `INTEG-O2`; pemeriksaan variabel `sa_password` yang tidak berlaku saat password diberikan lewat
  `--env-var`), lihat `docs/team-log/INTEG-TEST-002.md`.
