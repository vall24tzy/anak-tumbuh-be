# SoT Amendment — Keputusan deploy Vercel dan Supabase, mode gratis (D-26 sampai D-33)

**Disusun:** 1 Nov 2026 · **Penyusun:** Orang 1 (task DEPLOY-DECISION-001) · **Yang memutuskan:** pemilik ANAKTUMBUH.ID
**Status:** **USULAN, BELUM FINAL.** Baris persetujuan dan tanggal di bagian 10 sengaja dikosongkan untuk diisi pemilik. **Revisi 2 (review Orang 1):** menambah pemeriksaan perintah `SET` sesi di D-27 dan syarat batas durasi fungsi di D-31; tidak ada keputusan yang diubah. Berkas ini tidak mengedit `SoT-Amendment-INTEG-D01-D13.md` maupun `SoT-Amendment-INTEG-DOC-001.md`, dan tidak mengubah kode, test, atau dokumen lain.

**Batasan fase ini (arahan Orang 1):** hanya layanan **gratis** (Vercel Hobby, Supabase Free), tanpa biaya. Tujuannya membuktikan bahwa aplikasi bisa berjalan di Vercel dan Supabase; keterbatasan paket gratis diterima. Keputusan ini ditinjau ulang sebelum production (DEPLOY-PRODPREP-001). Semua usulan di bawah memakai batasan itu, dan hal yang berubah dibandingkan usulan awal di board ditandai.

## 1. Ringkasan keputusan

| # | Yang diputuskan | Usulan (mode gratis) | Syarat uji | Menahan task |
|---|---|---|---|---|
| D-26 | Versi PHP image | PHP 8.3 saja | `php vendor/composer/platform_check.php` lulus dan suite penuh hijau di PHP 8.3 | IMAGE, RUNTIME |
| D-27 | Koneksi database | Aplikasi lewat pooler **transaction** (6543) dengan emulate prepares; migrate/seed lewat pooler **session** (5432), bukan koneksi langsung | Suite penuh hijau dengan emulate prepares aktif; jika gagal, aplikasi pindah ke pooler session | RUNTIME |
| D-28 | Penyimpanan file | Supabase Storage lewat S3: foto bucket privat (lewat endpoint terproteksi), logo bucket publik | Akses S3 terbukti di project Free (belum terkonfirmasi); foto dan logo lolos uji unggah/unduh | STORAGE |
| D-29 | Batas upload import | Turunkan dari 5 MB ke 4 MB | Test batas 4096 KB; dokumen terdampak diperbarui | LIMIT, DOC |
| D-30 | Environment dan region | Satu project Supabase Free (uji/pilot) dan satu project Vercel; project production ditunda | Region dicatat saat project dibuat | IMAGE, VERIFY |
| D-31 | Kriteria lulus pilot dan jalan mundur | Kriteria terukur plus syarat khusus paket gratis; jalan mundur ke host Laravel konvensional | Hasil terukur di PILOT | PILOT |
| D-32 | Paket dan backup | **Gratis untuk pilot** (data uji saja); paket production dan backup ditunda ke PRODPREP | Tidak ada data siswa nyata di paket Free | PRODPREP |
| D-33 | Akun dan akses | Satu pemegang akun Vercel Hobby yang menjalankan semua deploy; pemilik menetapkan lokasi repo | Satu deploy percobaan dari repo yang sama | VERIFY, PILOT |

## 2. D-26 — versi PHP: PHP 8.3 saja

- **Bukti repo:** `composer.json` menulis `"php": "^8.2"`, tetapi `intervention/image 4.3.2` dan `intervention/gif 5.0.1` di `composer.lock` mensyaratkan `^8.3`, dan `vendor/composer/platform_check.php` menolak PHP di bawah 8.3.0 (`PHP_VERSION_ID >= 80300`). Pemeriksaan itu lulus di PHP 8.3.6 (sandbox penyusun); suite penuh 623 test lulus di PHP 8.3.6 pada PostgreSQL 16.15.
- **Ketidakselarasan yang ditemukan:** syarat PHP di `composer.json` (8.2) tidak sama dengan syarat dependensi (8.3). Usul: dirapikan menjadi `^8.3` di task kode (RUNTIME atau IMAGE), bukan di berkas ini.
- **Dukungan keamanan:** PHP 8.2 berakhir 31 Des 2026, PHP 8.3 sampai 31 Des 2027 (sumber sekunder: herodevs.com dan endoflife.ai; satu sumber menulis tanggal berbeda untuk 8.3, jadi **verifikasi di php.net/supported-versions.php**). PHP 8.4 tidak dipilih karena suite belum pernah dijalankan di sana; naik ke 8.4 menjadi pekerjaan terpisah.
- **Syarat uji:** platform check dan suite penuh hijau pada PHP 8.3 di terminal yang sama.

## 3. D-27 — koneksi database

- **Fakta Supabase (dokumentasi resmi):** koneksi langsung ke database hanya IPv6 di semua paket (IPv4 adalah add-on berbayar). Shared pooler berjalan di IPv4 di semua paket: mode session di port 5432 dan mode transaction di port 6543. Mode transaction tidak mendukung prepared statements.
- **Akibat untuk rencana awal ("migrate lewat koneksi langsung"):** pada paket gratis, `migrate` dari laptop yang hanya punya IPv4 tidak akan tersambung lewat koneksi langsung. **Usulan diubah:** migrate dan seed lewat pooler **session** (5432); aplikasi di Vercel lewat pooler **transaction** (6543).
- **Bukti repo:** `config/database.php` bagian `pgsql` belum punya opsi PDO apa pun (`sslmode=prefer`, tanpa `PDO::ATTR_EMULATE_PREPARES`). Task RUNTIME perlu menambah opsi emulate prepares dan menetapkan `sslmode`. Cache, session, dan queue di `.env.example` memakai driver `database`; rate limiter login bergantung pada cache sehingga ikut lewat koneksi yang sama.
- **Perlu diperiksa di RUNTIME:** saat koneksi dibuka, Laravel dapat menjalankan perintah `SET` tingkat sesi (mis. `search_path`); pada pooler mode transaction pengaruhnya tidak dijamin bertahan antar-query. Periksa saat suite dijalankan dengan emulate prepares; bukan klaim bahwa itu bermasalah.
- **Syarat uji (keputusan bersyarat):** suite penuh harus hijau dengan emulate prepares aktif. Jika gagal, aplikasi pindah ke pooler session dengan jumlah koneksi yang dijaga. Batas koneksi paket gratis dicatat saat pilot, tidak ditebak di sini.

## 4. D-28 — penyimpanan file

- **Bukti repo:** foto siswa memakai disk lokal `private` (dilayani lewat `Storage::...->response()` di endpoint terproteksi, F-06); logo sekolah memakai disk lokal `public` dengan URL dari `Storage::disk('public')->url()`. Disk `s3` sudah ada di `config/filesystems.php`, tetapi paket `league/flysystem-aws-s3-v3` belum ada di `composer.json`. Di Vercel, disk lokal tidak bisa dipakai untuk menyimpan file permanen, sehingga keduanya harus pindah ke S3.
- **Fakta Supabase:** Storage menyediakan endpoint S3 (`https://<project-ref>.supabase.co/storage/v1/s3`, path-style, access key dari dashboard). Batas paket Free: 1 GB penyimpanan, 50 MB per file. File kita kecil: foto dan logo maksimal 2 MB (`max:2048`). **Belum terkonfirmasi** di sumber yang dibaca bahwa protokol S3 tersedia di paket Free; dibuktikan saat DEPLOY-VERIFY-001 dengan membuat access key di project uji.
- **Usulan:** foto di bucket privat, tetap lewat endpoint terproteksi (data pribadi anak); logo di bucket publik karena bukan data pribadi (alternatif: logo juga lewat endpoint). Keputusan ini harus menetapkan cara URL logo dibentuk (konfigurasi URL disk, bukan `url()` disk lokal), diuji di STORAGE.
- **Risiko:** jika S3 tidak tersedia di Free, STORAGE butuh jalan lain (driver Storage berbasis REST) dan itu pekerjaan kode tambahan; kabari pemilik sebelum lanjut.

## 5. D-29 — batas upload import: 4 MB

- **Fakta Vercel:** body request Vercel Functions dibatasi 4,5 MB (lebih besar menghasilkan 413 `FUNCTION_PAYLOAD_TOO_LARGE`); dicek lewat salinan halaman batas fungsi di examples.vercel.com, **buka halaman resmi vercel.com/docs/functions/limitations untuk memastikan**.
- **Bukti repo:** tiga request membatasi file xlsx 5 MB (`max:5120`): `StudentImportPreviewRequest`, `StudentImportCommitRequest`, `TeacherImportRequest`. Foto dan logo 2 MB, aman.
- **Usulan:** turunkan ke 4 MB (`max:4096`) supaya jawaban 422 dari aplikasi muncul lebih dulu, bukan 413 dari platform dengan format berbeda. File 200 baris jauh lebih kecil, jadi tidak ada dampak praktis. Alternatif: tetap 5 MB dan mendokumentasikan batas efektif 4,5 MB.
- **Dokumen terdampak:** API Contract v1.3 (F-38 di blok Errata §18/§28), Panduan Alur Kerja, Koreksi FE. Dikerjakan di LIMIT dan DOC. **Syarat uji:** test untuk batas 4096 KB.

## 6. D-30 — environment dan region

- **Mode gratis:** Supabase Free membatasi 2 project aktif per organisasi. Usulan: **satu project untuk pilot** dan menyisakan satu slot; project production **tidak dibuat sekarang** (lihat D-32). Satu project Vercel; pilot memakai lingkungan Preview.
- **Region:** dipilih saat project dibuat dan tidak dapat diubah kemudian. Usulan awal Singapura untuk Supabase dan fungsi Vercel. Ketersediaan region di paket gratis Vercel Hobby **belum terverifikasi**; dicatat saat pembuatan project di VERIFY.
- **Data anak:** lokasi data di luar negeri bisa berdampak pada ketentuan perlindungan data pribadi. Berkas ini tidak menilai kepatuhannya; konsultasikan ke pihak yang berwenang sebelum data nyata dipakai. Karena itu pilot memakai **data uji saja**.

## 7. D-31 — kriteria lulus pilot dan jalan mundur

Kriteria GO: (1) semua folder Postman lulus tanpa gagal; (2) unggah/unduh foto, unggah logo, dan import 200 baris berhasil dan selesai dalam batas durasi fungsi platform (import terukur sekitar 0,6 detik per baris, jadi 200 baris sekitar 120 detik, Errata Stage 5 F-38; batas durasi Vercel Hobby **dicatat dari dokumentasi resmi saat pilot**, belum diverifikasi di sini); (3) tidak ada error koneksi database selama run; (4) cold start dan latensi dicatat dan diterima pemilik (angka batas ditetapkan setelah pilot mengukurnya, bukan ditebak sekarang). **Tambahan khusus paket gratis:** (5) project Supabase tidak terjeda selama jendela pilot (ada aktivitas sebelum 7 hari); (6) pemakaian penyimpanan dan bandwidth tetap di bawah kuota Free; (7) tidak ada batas Hobby yang terlampaui (menurut halaman Hobby, melampaui batas mengunci fitur 30 hari).
NO-GO: pindah ke host Laravel konvensional dengan mempertahankan Supabase dan hasil task kode; pilihan host ditetapkan saat NO-GO, diutamakan yang gratis atau terjangkau.
Catatan: Vercel Container Runtime masih **Beta** (dokumentasi resmi, tersedia di semua paket termasuk Hobby, halaman diperbarui 7 Juli 2026), sehingga hasil pilot menentukan kecocokannya.

## 8. D-32 dan D-33 — paket, backup, akun, dan akses

**D-32, paket dan backup (mode gratis).**
- Pilot memakai Supabase Free dan Vercel Hobby, biaya nol. Paket production dan strategi backup **ditunda ke DEPLOY-PRODPREP-001**; keputusan itu dibuat sebelum data siswa nyata disimpan.
- **Batasan paket Free yang diterima (pilot saja):** tanpa backup otomatis dan tanpa PITR; project dijeda setelah sekitar satu minggu tanpa aktivitas; 500 MB database, 1 GB penyimpanan, 5 GB bandwidth, 2 project aktif (sumber: halaman harga Supabase; **periksa halaman resmi karena angka bisa berubah**).
- **Aturan:** paket Free **tidak boleh** menyimpan data siswa nyata. Opsi backup gratis yang perlu diuji (bukan klaim): `pg_dump` terjadwal oleh tim lewat pooler session; skrip OPS-001 dirancang untuk database lokal sehingga belum tentu bisa dipakai apa adanya.

**D-33, akun dan akses (temuan baru, mengubah usulan awal).**
- **Fakta Vercel (dokumentasi resmi):** pada tim Hobby, commit yang di-deploy harus ditulis oleh pemilik tim Hobby; kolaborasi lewat keanggotaan tim memerlukan paket Pro, dan repo privat milik organisasi GitHub tidak dapat di-deploy dari Hobby. Anggota tim (Orang 1 dan Orang 2) **tidak dapat ditambahkan** pada paket gratis.
- **Usulan mode gratis:** satu orang memegang akun Vercel Hobby dan menjalankan semua deploy; commit dari anggota lain harus di-merge atau di-push oleh pemegang akun, atau deploy dijalankan dari komputer pemegang akun lewat CLI (cara kerja yang tepat **diuji dengan satu deploy percobaan** di VERIFY). Pemegang akun Supabase juga satu orang; kredensial tidak ditulis di repo atau dokumen, kunci produksi hanya di environment Vercel, dan 2FA aktif.
- **Pertanyaan untuk pemilik:** repo ada di mana (akun pribadi atau organisasi GitHub; privat atau publik)? Jawabannya menentukan apakah deploy dari Hobby bisa berjalan.
- **Ketentuan penggunaan Hobby:** paket Hobby hanya untuk penggunaan pribadi non-komersial; menurut dokumentasi resmi, penggunaan komersial mencakup deployment yang dipakai demi keuntungan finansial siapa pun yang terlibat. Pilot uji kemungkinan memenuhi syarat, tetapi pemilik perlu memastikan; berkas ini tidak menilainya.

## 9. Hal yang belum ada di board dan urutan memutuskan

1. **Siapa yang menanggung biaya** (Supabase berbayar, Vercel Pro, domain) belum ada di board. Pada mode gratis nilainya nol sekarang; diputuskan ulang di PRODPREP.
2. **Dampak pada board v1_2:** D-32 berubah (tidak ada paket berbayar sekarang), D-33 berubah (tidak ada anggota tim di Vercel), D-27 berubah (migrate lewat pooler session), dan task PRODPREP perlu memuat keputusan paket production.
3. **Urutan:** pertama D-26, D-27, D-28, D-29 (membuka task kode); sebelum VERIFY: D-30, D-32, D-33; sebelum PILOT: D-31.

## 10. Persetujuan pemilik (diisi pemilik)

| # | Keputusan final (isi atau coret usulan) | Disetujui pemilik | Tanggal |
|---|---|---|---|
| D-26 | | | |
| D-27 | | | |
| D-28 | | | |
| D-29 | | | |
| D-30 | | | |
| D-31 | | | |
| D-32 | | | |
| D-33 | | | |
