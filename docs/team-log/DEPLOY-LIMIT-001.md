# DEPLOY-LIMIT-001 — Log

**Status:** kode, test, dan addendum selesai ditulis; **belum dijalankan**, belum DONE (lihat "Belum selesai").
**PIC:** Orang 1 · **Tanggal:** 1 Nov 2026
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2; D-29 (usulan); `docs/addendum/DEPLOY-LIMIT-001.md`.
**Dependency:** DEPLOY-DECISION-001. **Catatan jujur:** persetujuan pemilik (bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md`) masih kosong; dikerjakan atas arahan Orang 1 di chat. Bila pemilik memilih opsi dokumentasi saja, perubahan ini dikembalikan (addendum bagian 5).

## Yang dikerjakan
1. Tiga rule `file` import: `max:5120` menjadi `max:4096` (Student Import preview, commit, Teacher Import).
2. `tests/Feature/Import/ImportFileSizeLimitTest.php`: 7 test (rule memakai 4096 dan bukan 5120; 4096 KB lolos dan 4097 KB ditolak untuk tiap request; foto dan logo tetap 2048).
3. `docs/addendum/DEPLOY-LIMIT-001.md`: koreksi angka 5 MB dan daftar teks untuk DEPLOY-DOC-001.
4. Tidak ada perubahan endpoint, field, kode error, batas 200 baris, atau skema DB.

## Yang diperiksa dan yang tidak
- Diperiksa dengan membaca kode dan dokumen: `grep` seluruh `app/` dan `tests/` untuk batas ukuran (hanya tiga rule import yang memakai 5120; tidak ada test yang menguji batas 5 MB), dokumen yang menyebut 5 MB.
- **Tidak dijalankan:** sandbox tanpa PHP. Sintaks dan hasil test belum terbukti.
- **Tidak diperiksa:** isi Buku Panduan v1.5 dan dokumen Koreksi FE (cari "5 MB" di DEPLOY-DOC-001); batas body Vercel 4,5 MB belum diverifikasi ke halaman resmi.
- Tidak diuji dengan upload `.xlsx` sungguhan lewat HTTP.

## Belum selesai (syarat DONE)
1. `php artisan test --filter=ImportFileSizeLimitTest` lulus, lalu suite penuh hijau.
2. Review silang Orang 2.
3. Daftar teks di addendum bagian 3 dikerjakan di DEPLOY-DOC-001.

## Temuan
1. Request Postman yang menguji ">5 MB" tetap lolos tetapi namanya usang.
2. Konfigurasi PHP di image (`upload_max_filesize`, `post_max_size`) harus mendukung 4 MB; dicatat untuk DEPLOY-IMAGE-001.
