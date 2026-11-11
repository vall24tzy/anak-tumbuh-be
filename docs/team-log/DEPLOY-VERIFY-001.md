# DEPLOY-VERIFY-001 — Log

**Status:** dijalankan oleh Orang 1 (10 sampai 11 Nov 2026); hasil di `docs/testing/DEPLOY-VERIFY-001-runner-result.md`. **Belum DONE**: lihat "Belum selesai".
**PIC:** Orang 1 (dijalankan bersama Orang 2 untuk data dasbor Supabase) · **Tanggal:** 10 sampai 11 Nov 2026
**Dasar:** Task Board Deployment Vercel dan Supabase; `docs/ops/verify-supabase.md`; keputusan D-26 sampai D-35.
**Dependency:** DEPLOY-STORAGE-001, DEPLOY-LIMIT-001 (Orang 1), DEPLOY-RUNTIME-001, DEPLOY-IMAGE-001 (Orang 2). **Catatan jujur:** dependency dianggap terpenuhi berdasarkan jawaban di chat (keempatnya sudah di-push dan test lokal hijau), bukan karena sudah berstatus DONE; justru VERIFY inilah yang membuktikan bagian S3 dan pooler. Persetujuan pemilik atas D-26 sampai D-35 tercatat di bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` (10 Nov 2026, dicatat Orang 2 sebagai pemilik).

## Yang dikerjakan
1. Migrate dan seed ke Supabase uji lewat `pgsql_direct` (24 migrasi `Ran`).
2. Menjalankan aplikasi lokal lewat pooler transaksi (6543) dengan `DB_DISABLE_PREPARES=true`: koneksi, `/up`, dan login Super Admin.
3. Uji S3 nyata: tulis, baca, dan hapus di bucket privat dan publik (`[true,true,true,true,true]`); URL logo publik dibuka (200).
4. Newman TEST-002 (enam run, satu valid penuh: run 6) dan TEST-001 (seluruh koleksi, 211 request).
5. Pemeriksaan manual tambahan: pembatas laju login (`401 ×5` lalu `429 ×2`), paginasi School dan Headmaster, indeks unik Headmaster.
6. Suite penuh di jendela tanpa variabel Supabase: 672 lulus (3860 assertion), PHP 8.3.30.
7. Tidak ada perubahan di `app/`, `config/`, `database/`, `routes/`, atau skema.

## Yang diperiksa dan yang tidak
- **Diperiksa dengan operasi nyata:** koneksi pooler (5432 dan 6543), tulis dan baca S3, unggah foto dan logo lewat aplikasi, import 200 baris, bucket publik.
- **Tidak dijalankan:** empat request TEST-002 yang memakai `await` (tidak jalan di newman) tidak dijalankan di Postman desktop; dicek dengan cara pengganti yang tidak setara persis. Empat endpoint daftar pada 13.10 (`/teachers`, `/academic-years`, `/rombels`, `/teacher-class-assignments`) belum diverifikasi ulang di Supabase.
- **Tidak diuji:** beban bersamaan. Semua run adalah satu klien berurutan.
- **Run 1, 2, dan 4 tidak dipakai sebagai bukti** (kesalahan penyiapan: `DB_URL` berawalan ganda, lalu `SA_PW` kosong). Run 5 tidak dipakai sebagai bukti S3 karena status variabel S3 saat itu tidak terverifikasi. Rincian di runner-result.

## Belum selesai (syarat DONE)
1. Review silang Orang 2 atas dokumen hasil dan team-log ini.
2. Orang 2 mengganti sandi database dan kunci S3 setelah semua run selesai (belum dilakukan saat berkas ini ditulis). Nilai yang dipakai di sesi VERIFY juga pernah ditempel di percakapan Orang 1 dengan asisten, jadi semuanya perlu diganti, bukan hanya yang ada di berkas.
3. Pembersihan data uji di database dan bucket Supabase uji oleh Orang 2.
4. Keputusan Orang 2 atas empat request `await` (dijalankan manual di Postman atau skrip dibetulkan) dan atas pengecekan usang 1.10 dan 7.06.

## Temuan
1. **Header `x-amz-acl`:** adapter Flysystem tetap mengirim header itu (temuan Orang 2). Unggahan ke Supabase berhasil, jadi header tidak ditolak pada project uji berpaket Free. Komentar di `config/filesystems.php` ("akses diatur lewat bucket, bukan ACL") masih bisa disalahartikan dan belum diperjelas.
2. **Latensi dari laptop ke Supabase Singapura:** import 200 baris sampai 128,8 detik, reset password siswa 40,6 detik, unduh template 24,6 detik, daftar School `per_page=100` 12,3 detik; rata-rata TEST-001 2,7 detik. Bukan angka di Vercel. Dicatat untuk PILOT (batas waktu fungsi dan wilayah Vercel belum diverifikasi). Tidak ada kode yang diubah.
3. **Pembatas laju F-23 di newman:** dua assertion TEST-001 gagal (401, bukan 429), tetapi uji manual menunjukkan pembatas bekerja. Penyebab di newman belum dipastikan (dugaan: jeda 13 detik atau cara skrip mengisi penghitung).
4. **Filter teks di Logs Supabase tidak berfungsi** untuk mencari `prepared statement` dan `08P01`, sehingga hasil kosong yang pertama kali dilaporkan dicabut; bukti pooler memakai `laravel.log`, 0 respons 5xx, dan hitungan Level (petunjuk, bukan bukti mutlak).
5. **Berkas uji TEST-002** dibuat ulang oleh `generate_test_files.py` sebelum tiap run (NIP baru); skrip mengubah `TEST-002.postman_environment.json` secara lokal dan perubahan itu tidak di-commit.
6. **`student_a_new_id`** dibuat oleh folder 4 TEST-001 (ditandai opsional), padahal folder 8 dan INTEG-O1 memakainya; karena itu seluruh koleksi dijalankan.
7. **Data TEST-001 memakai NISN dan NIP tetap**, jadi koleksi itu kemungkinan tidak bisa dijalankan dua kali pada database yang sama tanpa dibersihkan (dugaan, belum dicoba).
