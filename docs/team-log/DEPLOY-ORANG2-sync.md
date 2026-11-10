# DEPLOY-ORANG2-sync — Log

**Status:** sinkronisasi atas laporan hasil run dan smoke Orang 1 (7 dan 8 Nov 2026) selesai **untuk dokumen**; pertanyaan butir 6 **dijawab sebagian oleh Orang 2 (9 Nov 2026)**; penegasan (a) sampai (d) diminta Orang 1 dan **belum lengkap**; tanda tangan bagian 10 di berkas **belum ada**. **Pelaksana:** Orang 2. Tidak ada kode aplikasi atau test yang berubah; berkas milik Orang 1 tidak diubah selain rev3 dokumen keputusan (butir 1).

## Yang dikerjakan
1. **Rev3 dokumen keputusan** (`d09f938`, hanya `docs/addendum/SoT-Amendment-DEPLOY-D26-D33.md`): dikerjakan Orang 2 dengan `git cherry-pick d09f938` di repo sendiri (sesuai arahan Orang 1; branch `deploy/vercel-orang2` tidak di-push dari sisi Orang 1). Patch `DEPLOY-ORANG2-sync.patch` berlaku **setelah** cherry-pick itu; `DEPLOY-ORANG2-sync-full.patch` (dari `c3b97aa`) sudah memuat isi rev3 dan dipakai bila tidak di-cherry-pick. Bagian 10 tidak diisi atas nama pemilik.
2. **Hasil run dan smoke Orang 1** dicatat di `docs/team-log/DEPLOY-IMAGE-001.md` dan `DEPLOY-RUNTIME-001.md` dengan sumber "dilaporkan Orang 1". Status IMAGE: image dibangun dan smoke kontainer lokal lolos; sisanya di PILOT dan VERIFY. Gap "tag base image belum diverifikasi" ditutup dengan bukti build berhasil (`docs/ops/vercel-deploy.md`, `Dockerfile.vercel`, addendum IMAGE). Yang belum terbukti dicatat apa adanya (Vercel, Supabase, `X-Forwarded-*`, redaksi `Authorization` di log Caddy).
3. **Task Board v1.3** (berkas terpisah, tidak di repo): task `DEPLOY-SECURITY-001` (D-34) ditambahkan, status task diperbarui, sheet Keputusan Pending dan Urutan & Gate disesuaikan.
4. **`docs/ops/verify-supabase.md`**: rencana langkah DEPLOY-VERIFY-001 tanpa rahasia. VERIFY belum dimulai.

## Pertanyaan ke pemilik (butir 6)
Kunci dan password tidak boleh dikirim lewat chat atau ditulis di repo. Jawaban dicatat apa adanya; dijawab Orang 2 pada 9 Nov 2026.

| Butir | Pertanyaan | Jawaban |
|---|---|---|
| (a) | Tanda tangan bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` (D-26 sampai D-35 tertanda di berkas, dengan tanggal) | Orang 2 menyatakan menyetujui D-26 sampai D-35 (9 Nov 2026). Orang 1 meminta **Orang 2 menulisnya sendiri** di bagian 10 (nama, tanggal) dan menulis tegas "Pemilik = Orang 2" supaya jelas siapa menandatangani persetujuan risiko D-34. **Belum ada di berkas** |
| (b) | Siapa memegang akun Vercel Hobby dan akun Supabase (D-33)? | Orang 2, "dibantu Orang 1". Orang 1 meminta penegasan arti "dibantu": Hobby tidak bisa menambah anggota (D-33), sehingga Orang 2 yang menjalankan semua deploy (CLI atau commit oleh pemilik akun) dan Orang 1 tidak perlu akses akun. **Menunggu penegasan Orang 2:** arti "dibantu", siapa pemegang akun Supabase, dan 2FA sudah aktif atau belum |
| (c) | Kapan repo dijadikan privat (D-35)? Per 7 Nov 2026 repo masih publik | Jawaban Orang 2: setelah Task Board selesai dan setelah deploy di Vercel. Orang 1 menilai terlalu longgar (D-35 menjadi syarat VERIFY dan PILOT, dan DEPLOY-SECURITY-001 melarang analisis ditulis selama repo publik) dan **mengusulkan gate**: privat setelah PILOT (deploy Preview) selesai, sebelum DEPLOY-SECURITY-001 dimulai, paling lambat sebelum PRODPREP. **Menunggu ketetapan Orang 2** atau alasan bila berbeda; sampai itu repo tetap publik |
| (d) | Tanggal rencana upgrade Laravel 11 ke versi yang diperbaiki di `DEPLOY-SECURITY-001` | Jawaban Orang 2: ditunda sampai aplikasi berjalan baik. Fakta (dikutip Orang 1 dari laravel.com/docs/11.x/releases; belum diverifikasi Orang 2): Laravel 11 di luar dukungan keamanan sejak 12 Maret 2026. Perlu dicatat: patokan waktu (mis. upgrade ke Laravel 12 paling lambat N minggu setelah go-live; **N belum ditetapkan**) dan risiko yang diterima pemilik sebelum go-live (butir 4 dan 5 AC `DEPLOY-SECURITY-001`). Tahap `composer update` terbatas tetap sebelum go-live. D-34 tetap menahan `DEPLOYMENT-001` |

## Syarat sebelum VERIFY (dari Orang 1)
Project Supabase uji ada dengan dua bucket (privat dan publik), akses S3 aktif, akun pemegang jelas, dan tanda tangan pemilik tercatat. Belum ada yang terbukti terpenuhi dari sisi Orang 2.

## Belum dikerjakan (di luar tugas ini)
VERIFY, PILOT, PRODPREP, DEPLOYMENT-001, DOC. Smoke test kontainer, konfirmasi `php -v` laptop Orang 1, dan pembuktian pooler/S3 nyata menunggu pihak yang bersangkutan.
