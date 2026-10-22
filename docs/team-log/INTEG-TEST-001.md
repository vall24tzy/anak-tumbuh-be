# INTEG-TEST-001 — Log

**Status:** Selesai (TODO -> DONE); menunggu tinjau silang Orang 2.
**PIC:** Orang 1
**Tanggal:** 21 Okt 2026
**Dasar:** Task Board Tambahan Integrasi FE v1.5 (INTEG-TEST-001); Matrix v5.3 #62, #63, #64, #69; D-02, D-11, D-12.
**Dependency:** INTEG-QR-001, INTEG-REKAP-001, INTEG-PANDUAN-001 (DONE); TEST-001 v23 (DONE). Dibuat di atas kode yang sudah memuat ketiga task tersebut.

## Ringkasan

- **Kelengkapan Postman:** folder `INTEG-O1` memuat 37 request: QR 7 (#1-#7), REKAP 10 (#8-#17), PANDUAN 20 (#18-#37). Tidak ada request yang kurang; koleksi tidak diubah di task ini (request lama tidak diubah).
- **Celah PHPUnit ditutup:** 5 test baru (lihat bagian bawah). Tidak ada perubahan kode aplikasi.
- **Run koleksi:** hasil disimpan di `docs/testing/INTEG-O1-runner-result.md` (INTEG-O1: 37 request, 53 assertion, 0 gagal; run laptop tim 22 Okt 2026 menggantikan hasil run sandbox 21 Okt).
- **Suite:** 601 passed (3317 assertions) = 596/3286 (kode dengan PANDUAN) + 5 test baru (31 assertion).

## Matriks DoD per endpoint

"N/A" = item DoD tidak berlaku untuk endpoint itu (alasan di kolom). Nama test ada di `tests/Feature/Student/`; angka Postman = nomor request di folder INTEG-O1.

### INTEG-QR-001 — field `qr_regenerated_at` pada `GET /students` (`StudentListQrRegeneratedAtTest`)

| DoD | PHPUnit | Postman |
|---|---|---|
| Happy path | `teacher_list_memuat_qr_regenerated_at_null_bila_belum_pernah_regenerate`, `teacher_list_terisi_iso8601_utc_setelah_regenerate_dan_hanya_siswa_itu`, `siswa_tanpa_baris_qr_token_tetap_muncul_dengan_qr_regenerated_at_null` (baru) | #1, #2 |
| 401 tanpa token | `list_tanpa_token_ditolak_401` | #7 |
| 403 role salah | N/A (endpoint lama, bukan 403). Role lain 200 **tanpa** field: `headmaster_super_admin_dan_student_tidak_melihat_field` | #5, #6 |
| 404 lintas sekolah | N/A (list tanpa id). Isolasi lintas sekolah: `teacher_lintas_rombel_dan_lintas_sekolah_tidak_melihat_siswa_lain` | - (koleksi tanpa sekolah kedua) |
| 403 lintas rombel | List ter-scope dan regenerate rombel lain 403: `teacher_lintas_rombel_dan_lintas_sekolah_tidak_melihat_siswa_lain` | #4 |
| 422 | N/A (tanpa input) | - |
| Lain-lain | `teacher_show_siswa_tidak_memuat_qr_regenerated_at`, `field_lama_pada_list_teacher_tidak_berubah`, `list_teacher_tanpa_n_plus_1_untuk_qr_token` | #3 |

### INTEG-REKAP-001 — `GET /habits/recap` (`HabitRecapTest`)

| DoD | PHPUnit | Postman |
|---|---|---|
| Happy path | `rekap_menghitung_persentase_per_hari_total_poin_dan_terisi_per_kebiasaan`, `total_poin_sama_dengan_poin_awarded_dari_scoring_get_entries`, `siswa_non_roster_dan_entri_di_luar_rentang_tidak_dihitung`, `rombel_tanpa_siswa_aktif_mengembalikan_nol_tanpa_error` | #8 |
| 401 | `tanpa_token_401_dan_role_selain_teacher_403` | #17 |
| 403 role salah | `tanpa_token_401_dan_role_selain_teacher_403` (Headmaster, Super Admin, Student) | #15, #16 |
| 404 lintas sekolah | `rombel_sekolah_lain_dan_rombel_tidak_ada_menghasilkan_404_yang_sama` | #14 (`rombel_id=999999`) |
| 403 lintas rombel | `rombel_lain_di_sekolah_yang_sama_ditolak_403`, `teacher_tanpa_assignment_aktif_ditolak_403_...`, `teacher_inactive_tidak_punya_scope_403` | #12, #13 |
| 422 | `rentang_31_hari_diterima_dan_32_hari_ditolak_422`, `from_setelah_to_dan_format_tanggal_salah_ditolak_422`, `rombel_id_wajib_dan_harus_integer_422`, `hanya_from_diberikan_to_default_hari_ini_dan_batas_31_hari_tetap_berlaku` (baru) | #9, #10, #11 |
| Lain-lain | `default_rentang_7_hari_terakhir_memakai_tanggal_asia_jakarta`, `hanya_to_diberikan_from_otomatis_to_dikurangi_6_hari` (baru), `rekap_hanya_membaca_...`, `jumlah_query_tetap_...` | - |

### INTEG-PANDUAN-001 — `GET|PATCH /rombels/{id}/habit-guides` (`HabitGuideTest`)

| DoD | PHPUnit | Postman |
|---|---|---|
| Happy path | `teacher_menulis_panduan_dan_teacher_serta_student_rombel_membacanya`, `patch_kedua_menimpa_isi_tanpa_membuat_baris_baru`, `ketujuh_kebiasaan_termasuk_bermasyarakat_bisa_diberi_panduan_dan_diurut_sort_order` (baru) | #18-#21 |
| 401 | `tanpa_token_401_dan_role_tidak_berhak_403` | #37 |
| 403 role salah | `tanpa_token_401_dan_role_tidak_berhak_403` (Headmaster, Super Admin: GET dan PATCH; Student: PATCH) | #22, #34, #35, #36 |
| 404 lintas sekolah | `rombel_sekolah_lain_dan_rombel_tidak_ada_menghasilkan_404_identik_untuk_teacher_dan_student` (baca dan tulis), `habit_id_tidak_dikenal_404_...` | #33 (`rombel_id=999999`), #26 |
| 403 lintas rombel | `teacher_lintas_rombel_di_sekolah_sama_403_untuk_baca_dan_tulis`, `student_hanya_membaca_rombel_sendiri_lintas_rombel_403`, `teacher_inactive_teacher_tanpa_assignment_dan_student_tanpa_rombel_aktif_403` | #30, #31, #32 |
| 422 | `content_tanpa_key_ditolak_422_dan_tidak_menghapus_panduan`, `batas_2000_karakter_...`, `tag_html_ditolak_422_...` | #23, #24, #25 |
| Lain-lain | hapus D-13 (`content_kosong_null_atau_spasi_...`, `menghapus_...`), `karakter_4_byte_tersimpan_utuh_dan_dihitung_per_karakter` (baru), RESTRICT rombel, constraint DB | #27, #28, #29 |

## Celah yang ditutup (5 test baru, kode aplikasi tidak diubah)

1. REKAP: hanya `to` diberikan -> `from` = `to` - 6 hari.
2. REKAP: hanya `from` diberikan -> `to` = hari ini (Asia/Jakarta); batas 31 hari tetap berlaku (31 hari 200, 32 hari 422).
3. PANDUAN: ketujuh kebiasaan (termasuk Bermasyarakat) bisa diberi panduan dan daftar mengikuti `sort_order`.
4. PANDUAN: karakter 4-byte (emoji) tersimpan utuh dan dihitung per karakter (2.000 diterima, 2.001 ditolak).
5. QR: siswa tanpa baris `qr_tokens` tetap muncul di list dengan `qr_regenerated_at` null (tidak error).

Uji mutasi (kode dipulihkan setelahnya): default `from` = `to` - 7 -> 2 test gagal; `qrToken` tanpa null-safe -> 1 test gagal; daftar panduan tanpa urut `sort_order` -> 2 test gagal.

## Test dan run (dijalankan)

Lingkungan penyusun: PHP 8.3.6, PostgreSQL 16.15 (bukan lingkungan tim: baseline tim memakai PostgreSQL 17.11).

- `php artisan test`: **601 passed (3317 assertions)**.
- Run laptop tim (22 Okt 2026, selesai 09:23 WIB; newman 6.2.3, PHP 8.2.12, PostgreSQL 17.11, `CACHE_STORE=database`, `--delay-request 13000`): folder 0-4 lalu INTEG-O1 dalam satu sesi, **108 request, 167 assertion, 0 gagal**; INTEG-O1 **37 request, 53 assertion, 0 gagal**. Ini yang tersimpan di `docs/testing/INTEG-O1-runner-result.md`.
- Run sandbox penyusun sebelumnya (21 Okt, hanya riwayat; sudah digantikan): newman 6.2.3, cache array, folder 0-8 lalu INTEG-O1 dalam satu sesi; INTEG-O1 **37 request, 53 assertion, 0 gagal**.

## Batas yang perlu diketahui

- "Per folder" dijalankan sebagai satu sesi berurutan karena newman tidak membawa variabel koleksi antar-run terpisah; INTEG-O1 hanya bergantung pada folder 0-4.
- File hasil Runner kini berasal dari run laptop tim: hanya folder 0-4 dan INTEG-O1 (folder 5-8 sengaja tidak dijalankan karena INTEG-O1 hanya bergantung pada folder 0-4), tanpa kegagalan. Kegagalan folder 5, 6, dan 8 pada run sandbox 21 Okt adalah keterbatasan sandbox, bukan dari task INTEG.
- Skenario lintas sekolah (404 dengan sekolah kedua sungguhan) hanya di PHPUnit; di Postman diuji dengan `rombel_id=999999` (respons identik, D-11) karena koleksi INTEG-O1 tidak punya sekolah kedua.
- D-12: hasil Runner disimpan di `docs/testing/` dan sudah diganti dengan hasil run environment tim (22 Okt 2026).

## Tinjau silang (Orang 2)

Periksa: matriks DoD di atas lengkap dan tidak ada kode aplikasi yang berubah (hanya tiga file test dan dua file docs).
