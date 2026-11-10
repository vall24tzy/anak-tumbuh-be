# DEPLOY-ORANG2-sync — Log

**Status:** sinkronisasi atas laporan hasil run dan smoke Orang 1 (7 dan 8 Nov 2026) selesai **untuk dokumen**; penegasan (a) sampai (d) **dijawab pemilik (Orang 2) pada 10 Nov 2026** dan dicatat final di bagian 10 dan 12 `SoT-Amendment-DEPLOY-D26-D33.md` (berlaku setelah patch `DEPLOY-ORANG2-sync-3.patch` ter-commit); **satu hal masih terbuka: status 2FA Vercel dan Supabase belum dijawab**. **Pelaksana:** Orang 2. Tidak ada kode aplikasi atau test yang berubah; berkas milik Orang 1 tidak diubah selain rev3 dokumen keputusan (butir 1).

## Yang dikerjakan
1. **Rev3 dokumen keputusan** (`d09f938`, hanya `docs/addendum/SoT-Amendment-DEPLOY-D26-D33.md`): dikerjakan Orang 2 dengan `git cherry-pick d09f938` di repo sendiri (sesuai arahan Orang 1; branch `deploy/vercel-orang2` tidak di-push dari sisi Orang 1). Patch `DEPLOY-ORANG2-sync.patch` berlaku **setelah** cherry-pick itu; `DEPLOY-ORANG2-sync-full.patch` (dari `c3b97aa`) sudah memuat isi rev3 dan dipakai bila tidak di-cherry-pick. Bagian 10 tidak diisi atas nama pemilik.
2. **Hasil run dan smoke Orang 1** dicatat di `docs/team-log/DEPLOY-IMAGE-001.md` dan `DEPLOY-RUNTIME-001.md` dengan sumber "dilaporkan Orang 1". Status IMAGE: image dibangun dan smoke kontainer lokal lolos; sisanya di PILOT dan VERIFY. Gap "tag base image belum diverifikasi" ditutup dengan bukti build berhasil (`docs/ops/vercel-deploy.md`, `Dockerfile.vercel`, addendum IMAGE). Yang belum terbukti dicatat apa adanya (Vercel, Supabase, `X-Forwarded-*`, redaksi `Authorization` di log Caddy).
3. **Task Board v1.3** (berkas terpisah, tidak di repo): task `DEPLOY-SECURITY-001` (D-34) ditambahkan, status task diperbarui, sheet Keputusan Pending dan Urutan & Gate disesuaikan.
4. **`docs/ops/verify-supabase.md`**: rencana langkah DEPLOY-VERIFY-001 tanpa rahasia. VERIFY belum dimulai.

## Pertanyaan ke pemilik (butir 6)
Kunci dan password tidak boleh dikirim lewat chat atau ditulis di repo. Jawaban dicatat apa adanya; dijawab Orang 2 pada 9 Nov 2026.

| Butir | Pertanyaan | Jawaban |
|---|---|---|
| (a) | Tanda tangan bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` (D-26 sampai D-35 tertanda di berkas, dengan tanggal) | **Final (10 Nov 2026).** Teks dari pemilik: "Persetujuan D-26 sampai D-35. Pemilik = Orang 2. Disetujui oleh: Faisal Nisrina. Tanggal: 10 November 2026." Ditulis di bagian 10 lewat patch sync-3; pemilik yang meng-commit |
| (b) | Siapa memegang akun Vercel Hobby dan akun Supabase (D-33)? | **Final (10 Nov 2026).** Orang 2 menjalankan semua deploy; Orang 1 tidak perlu akses akun. Pemegang akun Vercel dan Supabase = Orang 2, memakai email perusahaan. **2FA Vercel dan Supabase: belum dijawab**; dikonfirmasi pemilik sebelum VERIFY (syarat D-33) |
| (c) | Kapan repo dijadikan privat (D-35)? | **Final (10 Nov 2026).** Usulan gate Orang 1 diterima: privat setelah PILOT (deploy Preview) selesai, sebelum DEPLOY-SECURITY-001 dimulai, paling lambat sebelum PRODPREP. Menggantikan jawaban 9 Nov ("setelah Task Board selesai dan setelah deploy"). Repo tetap publik sampai gate tercapai |
| (d) | Batas waktu upgrade Laravel 11 ke versi yang diperbaiki (`DEPLOY-SECURITY-001`) | **Final (10 Nov 2026).** N = 4: upgrade ke Laravel 12 paling lambat 4 minggu setelah go-live; pemilik menerima risikonya. `composer update` terbatas tetap sebelum go-live; D-34 tetap menahan `DEPLOYMENT-001`. Fakta dukungan versi diverifikasi 10 Nov 2026 (lihat bagian 12 dokumen keputusan); catatan: perbaikan keamanan Laravel 12 berakhir 24 Feb 2027, target 12 atau 13 ditinjau di `DEPLOY-SECURITY-001`

## Syarat sebelum VERIFY (dari Orang 1)
Project Supabase uji ada dengan dua bucket (privat dan publik), akses S3 aktif, konfirmasi paket Free mendukung S3 dan pooler, 2FA aktif pada akun Vercel dan Supabase (D-33). Dua syarat lama sudah terpenuhi per 10 Nov 2026: akun pemegang jelas (Orang 2) dan tanda tangan pemilik tercatat (bagian 10). Sisanya belum terbukti.

## Belum dikerjakan (di luar tugas ini)
VERIFY, PILOT, PRODPREP, DEPLOYMENT-001, DOC. Smoke test kontainer, konfirmasi `php -v` laptop Orang 1, dan pembuktian pooler/S3 nyata menunggu pihak yang bersangkutan.
