# DEPLOY-STORAGE-003 (S-1, D-38) — Log

**Status:** kode, test, addendum selesai; review silang Orang 2 lulus (a4c8335); lokal 694 passed (4.314 assertion); commit lanjutan menutup catatan review; menunggu uji kegagalan unggah di Preview (Orang 2) sebelum merge.
**PIC:** Orang 1 · **Tanggal:** 17 Nov 2026 · **Cabang:** `deploy/vercel-storage-tx` dari `deploy/vercel-storage-log` @ 75fa274; ditindaklanjuti dengan commit lanjutan setelah review silang Orang 2 (lulus, catatan 1 sampai 5).
**Dasar:** Task Board Deployment v1.11; D-38 (Pemilik, 16 Nov 2026); `docs/addendum/DEPLOY-STORAGE-003.md`.
**Dependency:** DEPLOY-IMPORT-001 (DONE, sudah di cabang), DEPLOY-STORAGE-002 (kode dan test selesai, menunggu penutupan).

## Dikerjakan
1. Create siswa dan create sekolah: foto/logo diunggah di dalam transaksi DB yang sama. S3 gagal berarti rollback penuh dan retry berhasil.
2. Re-enrollment: file lama tidak dihapus di dalam transaksi; dihapus sesudah commit bila path beda.
3. Timeout S3 eksplisit (connect 3 dtk, total 10 dtk, 1 percobaan ulang), dapat diubah lewat env.
4. 9 test baru (`CreateUploadTransactionTest`) dan 1 test timeout di `StorageDiskConfigTest`.
5. Addendum dan team-log; variabel env baru masuk `docs/ops/vercel-deploy.md`.
6. Tidak ada perubahan endpoint, bentuk respons, kode error, jalur update, atau skema.

## Diperiksa
- Sandbox: PHPUnit penuh 694 tests, 4.314 assertions OK (PHP 8.3.6, PostgreSQL 16). Baseline sebelum task: 684 passed di lokal Orang 1; 694 = 684 + 10 test baru.
- Tujuh dari sepuluh test baru gagal pada kode lama dan lulus pada kode baru.
- Folder `Upload`, `School`, `Student` (termasuk `UploadReplaceOrderTest` dan `StudentUpdatePhotoMultipartTest`) tetap hijau: jalur update tidak berubah.

## Belum selesai
1. ~~Test lokal~~ SELESAI (694 passed, 4.314 assertion, a4c8335); setelah commit lanjutan: lihat commit message/pesan.
2. ~~Review silang Orang 2~~ SELESAI (lulus, tanpa temuan pemblokir).
3. Bukti di Preview dengan S3 sungguhan (opsional, dikerjakan Orang 2): kegagalan unggah menghasilkan rollback; durasi tidak melewati timeout.
4. DEPLOY-DOC-001: perilaku create saat S3 gagal kini rollback (bukan data tersimpan); catatan S-4 dan S-5 tetap.

## Temuan
1. Test re-enrollment menangkap kesalahan penangkapan variabel (arrow function menangkap per nilai), sehingga file lama tidak terhapus sesudah commit; diperbaiki dengan objek `$photo`.
2. Saat penyuntingan, satu baris di `update()` sempat ikut berubah; suite penuh menangkapnya (7 test update gagal) dan baris itu dikembalikan. Diff akhir hanya menyentuh `store()`.
3. Koneksi DB ditahan selama decode, resize, dan unggah ke S3; dibatasi timeout. Pemisahan decode dari unggah tidak dikerjakan (tidak diminta D-38).
4. Orphan langka: rollback sesudah file di path berbeda tertulis pada re-enrollment meninggalkan file baru. Dicatat, tidak dipatch.
5. Review silang Orang 2 (a4c8335) menambah: env timeout kosong menjadi 0 (tanpa timeout), sudah ditutup di commit lanjutan (`$s3Number`, 1 test); orphan saat commit gagal dan re-enrollment path sama dicatat di addendum bagian 5; basis cabang disamakan menjadi 75fa274.
