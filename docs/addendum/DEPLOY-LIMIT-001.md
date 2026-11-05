# DEPLOY-LIMIT-001 — Batas ukuran file import diturunkan ke 4 MB

**Tanggal:** 1 Nov 2026 · **PIC:** Orang 1 · **Sifat perubahan:** satu angka pada tiga rule validasi; tidak ada endpoint, field, kode error, atau skema DB baru.
**Dasar:** Task Board Deployment Vercel dan Supabase v1.2 (DEPLOY-LIMIT-001), D-29 (usulan di `SoT-Amendment-DEPLOY-D26-D33.md`), F-38 dan Errata Stage 5 §18/§28. **Catatan dependency:** persetujuan pemilik (bagian 10 berkas D-26 sampai D-33) belum diisi; dikerjakan atas arahan Orang 1 di chat (1 Nov 2026). D-29 tetap usulan sampai pemilik menandatangani.

## 1. Keputusan yang dipakai

Opsi "turunkan ke 4 MB" dari D-29. Alasan: body request Vercel Functions dibatasi 4,5 MB (menurut dokumentasi yang dikutip di D-29; **belum diverifikasi ke halaman resmi**). File 4,5 sampai 5 MB akan ditolak platform dengan 413 dalam format yang berbeda dari Contract. Dengan 4 MB, penolakan datang dari aplikasi sebagai 422 `validation_failed`.

## 2. Perubahan

| Berkas | Perubahan |
|---|---|
| `app/Http/Requests/Student/StudentImportPreviewRequest.php` | `max:5120` menjadi `max:4096` |
| `app/Http/Requests/Student/StudentImportCommitRequest.php` | `max:5120` menjadi `max:4096` |
| `app/Http/Requests/School/TeacherImportRequest.php` | `max:5120` menjadi `max:4096` |
| `tests/Feature/Import/ImportFileSizeLimitTest.php` | 7 test (3 request x 2 kasus, plus batas foto dan logo) |

Tidak berubah: format `.xlsx` saja (F-33), batas 200 baris (`ImportService::MAX_ROWS`), kode error (`validation_failed`), batas foto dan logo 2 MB (`max:2048`).

## 3. Koreksi teks (dicatat di sini, dikerjakan di DEPLOY-DOC-001)

Angka "5 MB" untuk file import diganti "4 MB" pada:
1. API Contract v1.3: blok Errata Stage 5 §18 dan §28 (F-38: "Maksimum 5 MB dan 200 baris"). Baris OD-12 di §30 menyebut 5 MB dan 1.000 baris; itu riwayat keputusan lama yang sudah digantikan Errata Stage 5 dan dibiarkan sebagai riwayat.
2. Buku Panduan Alur Kerja v1.5 dan dokumen Koreksi FE: **belum saya periksa**; cari "5 MB" saat DEPLOY-DOC-001.
3. Postman: nama dan deskripsi di `docs/testing/TEST-001-ANAKTUMBUH-Postman-Collection.json` ("file > 5 MB -> 422") dan `docs/postman/TEST-002.postman_collection.json` (10.11 dan deskripsi folder 10) menyebut 5 MB. Request-nya **tetap lolos** karena file uji lebih besar dari 5 MB juga lebih besar dari 4 MB; hanya namanya usang. Usul tambahan (opsional): satu request file 4,2 MB untuk membuktikan batas baru.
4. `docs/addendum/API-Contract-v1.2-Errata-Stage5.md` dan `docs/postman/generate_test_files.py` (komentar "oversized > 5 MB"): riwayat dan berkas uji; dibiarkan.

## 4. Catatan untuk DEPLOY-IMAGE-001

Batas aplikasi 4 MB tidak berguna bila konfigurasi PHP di image lebih rendah. Pastikan `upload_max_filesize` dan `post_max_size` di image minimal 5M (bawaan PHP: 2M dan 8M, jadi `upload_max_filesize` perlu dinaikkan). Tanpa itu PHP menolak file 2 sampai 4 MB sebelum rule ini dijalankan. Belum diperiksa di repo ini karena belum ada image.

## 5. Bila pemilik memilih opsi lain

Bila D-29 diputuskan "tetap 5 MB, dokumentasikan batas efektif 4,5 MB": kembalikan tiga angka ke `5120`, hapus test ini, dan addendum ini diganti pernyataan batas efektif.

## 6. Status eksekusi

Test ditulis di sandbox tanpa PHP dan **belum dijalankan**: `php artisan test --filter=ImportFileSizeLimitTest`, lalu suite penuh. Pengujian dilakukan di level rule (file palsu 4096 KB lolos, 4097 KB ditolak), bukan upload `.xlsx` sungguhan lewat HTTP.
