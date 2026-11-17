# DEPLOY-STORAGE-003 — Unggah foto dan logo di dalam transaksi DB untuk create (S-1, D-38)

**Tanggal:** 17 Nov 2026 · **PIC:** Orang 1 · **Cabang:** `deploy/vercel-storage-tx` (dari `deploy/vercel-storage-log` @ bd3b165)
**Dasar:** Task Board Deployment v1.11 (DEPLOY-STORAGE-003), keputusan D-38 (Pemilik, 16 Nov 2026), review silang Orang 2 temuan S-1.
**Sifat perubahan:** perilaku create saat S3 gagal dan konfigurasi timeout S3. Endpoint, bentuk respons, kode error sukses, dan skema DB tidak berubah.

## 1. Masalah (S-1)
`StudentService::store` dan `SchoolService::store` menyimpan ke DB dahulu, baru mengunggah foto atau logo. Bila S3 gagal, klien menerima 500 tetapi data sudah tersimpan; retry kena 409 (NISN, assignment) atau 422 (nama sekolah). Logo sekolah tidak bisa diunggah ulang karena `PATCH /schools` hanya `name` (Contract v1.3, D-09).

## 2. Perubahan

| Berkas | Perubahan |
|---|---|
| `app/Services/School/SchoolService.php` | `store()`: `School::create` dan unggah logo di dalam satu `DB::transaction`. Gagal unggah: baris sekolah rollback. Bila `logo_path` gagal diperbarui setelah file tertulis, file baru dihapus (kompensasi, best-effort). Penanganan nama duplikat (422) tidak berubah. |
| `app/Services/Student/StudentService.php` | `store()`: unggah foto menjadi langkah TERAKHIR di dalam transaksi create (sesudah assignment lolos). Gagal unggah: User, Student, QrToken, dan assignment ikut rollback. Student baru: file baru dihapus bila langkah sesudah unggah gagal. **Re-enrollment** (NISN sudah ada): file lama tidak dihapus di dalam transaksi; dihapus sesudah commit bila path-nya beda dari yang baru. |
| `config/filesystems.php` | Disk S3: `http.connect_timeout` 3 dtk, `http.timeout` 10 dtk, `retries` 1 (bawaan SDK 3). Dapat diubah lewat `STORAGE_S3_CONNECT_TIMEOUT`, `STORAGE_S3_TIMEOUT`, `STORAGE_S3_RETRIES`. |
| `.env.example` | Tiga variabel timeout di atas (dikomentari). |
| `docs/ops/vercel-deploy.md` | Tiga variabel timeout masuk tabel variabel (dipaksa oleh `ImageFilesTest`: setiap variabel `.env.example` harus disebut di dokumen ini). |
| `tests/Feature/Upload/CreateUploadTransactionTest.php` | **Baru**, 9 test. |
| `tests/Feature/Upload/StorageDiskConfigTest.php` | 1 test baru (timeout dan retry). |

## 3. Syarat D-38 dan pemenuhannya
1. **Hanya create siswa dan sekolah; jalur update tidak diubah:** `update()` di `StudentService` dan `UploadService::upload` tidak disentuh; `UploadReplaceOrderTest` dan `StudentUpdatePhotoMultipartTest` tetap hijau.
2. **Unggah di dalam transaksi, rollback dan retry berhasil:** test siswa dan sekolah (gagal 500, tidak ada baris, retry 201 dengan data yang sama).
3. **Timeout S3 eksplisit:** lihat tabel di atas. Terburuk sekitar 2 x 10 detik per unggah (1 percobaan ulang). Nilai awal; ubah lewat env bila perlu.
4. **Test rollback, retry, dan re-enrollment:** lihat bagian 4.
5. **Tidak ada perubahan endpoint atau bentuk respons:** terpenuhi.
6. **Addendum dan team-log:** berkas ini dan `docs/team-log/DEPLOY-STORAGE-003.md`.

## 4. Test baru (`CreateUploadTransactionTest`)
Kegagalan S3 disimulasikan dengan mengganti `UploadService` di container (versi yang bisa dinyalakan dan dimatikan; controller di-cache Route selama satu test).
1. Create siswa dengan foto, unggah gagal: 500, tidak ada User, Student, QrToken, assignment; retry 201 dengan foto tersimpan.
2. Create siswa, unggah sukses lalu pembaruan `photo_path` gagal: tidak ada baris, tidak ada file yatim di disk privat.
3. Create siswa tanpa foto: tidak berubah.
4. Re-enrollment, unggah gagal: assignment tidak tercipta, `photo_path` dan file lama utuh.
5. Re-enrollment, gagal sesudah file tertulis: foto lama tetap ada (tidak dikompensasi).
6. Re-enrollment berhasil dengan ekstensi beda: file lama dihapus sesudah commit, `photo_path` baru.
7. Create sekolah dengan logo, unggah gagal: tidak ada baris; retry dengan nama yang sama 201.
8. Create sekolah, gagal sesudah file tertulis: tidak ada file yatim di disk publik.
9. Create sekolah tanpa logo: tidak berubah.

Tujuh dari sepuluh test terkait (9 baru ditambah 1 test timeout) gagal pada kode lama, sisanya lulus pada keduanya.

## 5. Keputusan desain dan batasnya
- **Penangkapan variabel:** `store()` memakai objek `$photo` untuk path lama dan baru, karena arrow function menangkap variabel biasa per nilai (ditemukan oleh test re-enrollment).
- **Kompensasi file** hanya untuk Student baru dan sekolah baru. Pada re-enrollment file baru bisa saja sudah menimpa foto lama di path yang sama; orphan dianggap lebih aman daripada kehilangan foto.
- **Koneksi DB ditahan selama unggah** (decode, resize, kirim ke S3). Dibatasi timeout di atas. Decode gambar besar di dalam transaksi tidak dipisah dari unggah (tidak diminta D-38); bila terbukti lambat di Preview, kandidat perbaikan berikutnya.
- **Orphan tersisa:** bila rollback terjadi setelah file di path berbeda tertulis pada re-enrollment (kasus langka), file baru tertinggal sebagai yatim. Dicatat, tidak dipatch.
- Respons 500 saat S3 gagal tidak berubah; yang berubah hanya keadaan data (kini tidak tersimpan).

## 6. Tidak berubah
Endpoint, bentuk respons, kode error, `update()` siswa dan sekolah, `UploadService`, `MAX_ROWS`, skema DB.

## 7. Status eksekusi
Dijalankan Orang 1 di sandbox (PHP 8.3.6, PostgreSQL 16): `php artisan test` setara PHPUnit penuh **694 tests, 4.314 assertions, OK**. Folder `Upload`, `School`, `Student`: 389 tests OK. Hasil di lokal Orang 1 dicatat di team-log. Belum dibuktikan: perilaku dengan S3 Supabase sungguhan di Preview (timeout, rollback saat S3 benar-benar menolak).
