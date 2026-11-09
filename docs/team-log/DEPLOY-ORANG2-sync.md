# DEPLOY-ORANG2-sync — Log

**Status:** sinkronisasi atas laporan hasil run dan smoke Orang 1 (7 dan 8 Nov 2026) selesai **untuk dokumen**; pertanyaan ke pemilik (butir 6) **belum dijawab**. **Pelaksana:** Orang 2. Tidak ada kode aplikasi atau test yang berubah; berkas milik Orang 1 tidak diubah selain rev3 dokumen keputusan (butir 1).

## Yang dikerjakan
1. **Rev3 dokumen keputusan** (`d09f938`, hanya `docs/addendum/SoT-Amendment-DEPLOY-D26-D33.md`): dikerjakan Orang 2 dengan `git cherry-pick d09f938` di repo sendiri (sesuai arahan Orang 1; branch `deploy/vercel-orang2` tidak di-push dari sisi Orang 1). Patch `DEPLOY-ORANG2-sync.patch` berlaku **setelah** cherry-pick itu; `DEPLOY-ORANG2-sync-full.patch` (dari `c3b97aa`) sudah memuat isi rev3 dan dipakai bila tidak di-cherry-pick. Bagian 10 tidak diisi atas nama pemilik.
2. **Hasil run dan smoke Orang 1** dicatat di `docs/team-log/DEPLOY-IMAGE-001.md` dan `DEPLOY-RUNTIME-001.md` dengan sumber "dilaporkan Orang 1". Status IMAGE: image dibangun dan smoke kontainer lokal lolos; sisanya di PILOT dan VERIFY. Gap "tag base image belum diverifikasi" ditutup dengan bukti build berhasil (`docs/ops/vercel-deploy.md`, `Dockerfile.vercel`, addendum IMAGE). Yang belum terbukti dicatat apa adanya (Vercel, Supabase, `X-Forwarded-*`, redaksi `Authorization` di log Caddy).
3. **Task Board v1.3** (berkas terpisah, tidak di repo): task `DEPLOY-SECURITY-001` (D-34) ditambahkan, status task diperbarui, sheet Keputusan Pending dan Urutan & Gate disesuaikan.
4. **`docs/ops/verify-supabase.md`**: rencana langkah DEPLOY-VERIFY-001 tanpa rahasia. VERIFY belum dimulai.

## Pertanyaan ke pemilik (butir 6)
Kunci dan password tidak boleh dikirim lewat chat atau ditulis di repo. Jawaban dicatat di sini apa adanya.

| Butir | Pertanyaan | Jawaban |
|---|---|---|
| (a) | Tanda tangan bagian 10 `SoT-Amendment-DEPLOY-D26-D33.md` (D-26 sampai D-35 tertanda di berkas, dengan tanggal) | belum dijawab |
| (b) | Siapa memegang akun Vercel Hobby dan akun Supabase (D-33)? | belum dijawab |
| (c) | Kapan repo dijadikan privat (D-35)? Per 7 Nov 2026 repo masih publik | belum dijawab |
| (d) | Tanggal rencana upgrade Laravel 11 ke versi yang diperbaiki di `DEPLOY-SECURITY-001` | belum dijawab |

## Syarat sebelum VERIFY (dari Orang 1)
Project Supabase uji ada dengan dua bucket (privat dan publik), akses S3 aktif, akun pemegang jelas, dan tanda tangan pemilik tercatat. Belum ada yang terbukti terpenuhi dari sisi Orang 2.

## Belum dikerjakan (di luar tugas ini)
VERIFY, PILOT, PRODPREP, DEPLOYMENT-001, DOC. Smoke test kontainer, konfirmasi `php -v` laptop Orang 1, dan pembuktian pooler/S3 nyata menunggu pihak yang bersangkutan.
