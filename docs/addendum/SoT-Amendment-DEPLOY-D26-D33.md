# SoT Amendment — Keputusan deploy Vercel dan Supabase, mode gratis (D-26 sampai D-33)

**Disusun:** 1 Nov 2026 · **Penyusun:** Orang 1 (task DEPLOY-DECISION-001) · **Yang memutuskan:** pemilik ANAKTUMBUH.ID
**Status (revisi 3, 7 Nov 2026): keputusan D-26 sampai D-35 SUDAH DIPUTUSKAN pemilik, tetapi hanya sebagai laporan.** Teks keputusan datang dari tabel pemilik yang diterima Orang 2 lalu diteruskan ke Orang 1; tabel aslinya **belum dilihat langsung** dan **belum ada tanda tangan di berkas ini**. Bagian 1 sampai 9 di bawah adalah **usulan awal (riwayat)**; bila berbeda dengan **bagian 11**, bagian 11 yang berlaku. **Revisi 2 (review Orang 1):** menambah pemeriksaan perintah `SET` sesi di D-27 dan syarat batas durasi fungsi di D-31; tidak ada keputusan yang diubah. Berkas ini tidak mengedit `SoT-Amendment-INTEG-D01-D13.md` maupun `SoT-Amendment-INTEG-DOC-001.md`, dan tidak mengubah kode, test, atau dokumen lain.

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
| D-26 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-27 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-28 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-29 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-30 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-31 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-32 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-33 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-34 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |
| D-35 | Lihat bagian 11 | Faisal Nisrina (Pemilik = Orang 2) | 10 November 2026 |

**Persetujuan D-26 sampai D-35. Pemilik = Orang 2. Disetujui oleh: Faisal Nisrina. Tanggal: 10 November 2026.**

Catatan pencatatan: teks persetujuan di atas dirumuskan oleh pemilik (Orang 2) pada 10 November 2026 dan dimasukkan ke berkas ini lewat patch `DEPLOY-ORANG2-sync-3.patch` atas permintaannya; pemilik yang meng-commit dan mem-push. Persetujuan mencakup isi bagian 11 beserta syarat tiap butir dan penegasan pemilik di bagian 12. Khusus D-34: yang diterima pemilik adalah risiko advisory yang menunggu upgrade Laravel 12 (batas waktu di bagian 12 butir (d)); daftar advisory per item dan statusnya dicatat oleh `DEPLOY-SECURITY-001` sebelum go-live.

## 11. Keputusan final D-26 sampai D-35 (sebagaimana dilaporkan; berlaku bila berbeda dari bagian 1 sampai 9)

**Sumber:** tabel pemilik yang diterima Orang 2 dan diteruskan ke Orang 1 (7 Nov 2026), disalin apa adanya di bawah. Bukan dokumen yang dilihat langsung oleh penyusun. Persetujuan pemilik atas butir-butir ini tercatat di bagian 10 (10 November 2026).

| ID | Keputusan | Syarat atau catatan |
|---|---|---|
| D-26 | PHP 8.3 saja | `composer.json` `^8.3`, image di minor 8.3, suite lulus di 8.3.30 |
| D-27 | Pooler mode transaksi untuk aplikasi dengan `DB_DISABLE_PREPARES` (bukan emulate prepares); migrate dan seed lewat pooler session (5432) | Final sebagai rencana. Bukti pooler nyata adalah syarat lulus DEPLOY-VERIFY-001. Jalan mundur: pooler session dengan batas koneksi |
| D-28 | Supabase Storage lewat S3, foto privat, logo publik | Tanpa ACL (`x-amz-acl` tidak didukung Supabase). Wajib terbukti di VERIFY, termasuk di paket Free. Bila gagal: naikkan paket uji atau pakai penyedia S3 lain |
| D-29 | Batas upload import 4 MB | Batas Vercel 4,5 MB sudah terkonfirmasi. Contract, Panduan, dan Koreksi FE diperbarui lewat DEPLOY-LIMIT-001 dan DEPLOY-DOC-001 |
| D-30 | Dua project Supabase (uji dan production), satu project Vercel (Preview dan Production) | Region berdekatan dengan pengguna; periksa ketersediaan dan batas project paket Free; pertimbangkan perlindungan data pribadi |
| D-31 | Kriteria lulus pilot: Postman lulus, upload/unduh dan import berhasil, tanpa error koneksi, latensi dan cold start dicatat | Angka batas ditetapkan setelah pengukuran. NO-GO: host Laravel konvensional |
| D-32 | Paket Free untuk Supabase | Risiko diterima: tanpa backup otomatis, project terjeda setelah seminggu, hanya data uji; dump manual diuji di PRODPREP |
| D-33 | Vercel Hobby, proyek non-komersial | Berlaku selama tidak ada yang mendapat uang dari proyek ini; pemilik memegang akun, 2FA aktif, kredensial tidak masuk repo; verifikasi pemicu deployment dua orang saat pilot |
| D-34 | Task keamanan terpisah untuk 20 advisory `composer audit` (terutama phpspreadsheet) | Menahan go-live, bukan pilot. Advisory yang tidak diperbaiki dicatat sebagai risiko yang diterima pemilik |
| D-35 | Repo dijadikan privat tanpa perubahan lain | Ganti password lokal yang pernah terbuka, pindai riwayat untuk rahasia lain, tambahkan collaborator, izinkan Vercel membaca repo privat |

**Catatan Orang 2 atas D-35 (dilaporkan):** password lokal yang pernah terbuka di commit awal sudah berbeda dari password yang dipakai sekarang, jadi tidak ada yang perlu dirotasi; pemindaian riwayat tidak menemukan rahasia lain. Orang 1 belum memeriksa ini sendiri. **Status repo saat bagian ini ditulis: masih publik** (dapat diambil dari GitHub tanpa autentikasi pada 7 Nov 2026).

### Perbedaan terhadap usulan awal (bagian 1 sampai 9)

| ID | Usulan awal | Keputusan final |
|---|---|---|
| D-27 | Pooler transaction dengan **emulate prepares** | `DB_DISABLE_PREPARES`. Run suite dengan emulate prepares gagal total (611 test gagal, `boolean = integer`), dilaporkan Orang 2 di `docs/addendum/DEPLOY-RUNTIME-001.md` |
| D-28 | Akses ACL tidak dibahas | **Tanpa ACL.** Lihat catatan di bawah |
| D-29 | Batas 4,5 MB belum diverifikasi | Terkonfirmasi (menurut tabel) |
| D-30 | Satu project Supabase untuk pilot, production ditunda | **Dua** project Supabase (uji dan production) |
| D-33 | Pemegang akun Vercel Hobby, lokasi repo ditanyakan | Hobby, proyek non-komersial, syarat 2FA dan kredensial di luar repo |
| D-34, D-35 | Belum ada | Baru |

### Hal yang perlu dicatat, bukan keputusan baru

1. **D-28 "tanpa ACL" belum dipenuhi kode.** Adapter `league/flysystem-aws-s3-v3` tetap mengirim `x-amz-acl: private` pada tiap unggah walau `visibility` tidak diset (ditemukan Orang 2 di `vendor/.../AwsS3V3Adapter.php`). Komentar di `config/filesystems.php` ("akses diatur lewat bucket, bukan ACL") menggambarkan maksud, bukan perilaku header. Dibuktikan di VERIFY; bila Supabase menolak header itu, task kode terpisah (mis. membuang header di klien AWS SDK) dan bukan tebakan sekarang.
2. **D-30 dan D-32 tampak tidak sejalan:** dua project Supabase, sedangkan paket Free tidak boleh menyimpan data siswa nyata dan paket production baru diputuskan di PRODPREP. Project "production" pada paket Free hanya boleh berisi data uji sampai PRODPREP memutuskan paketnya. Diselesaikan di PRODPREP, tidak diubah di sini.
3. **D-34 belum punya baris di Task Board Deployment v1.2.** Perlu task keamanan terpisah (menahan go-live, bukan pilot); penambahan ke board dilakukan oleh pemegang board.
4. **D-31:** batas durasi fungsi Vercel Hobby dan angka cold start tetap dicatat saat PILOT.

## 12. Penegasan pemilik atas butir (a) sampai (d) (10 November 2026)

**Sumber:** jawaban pemilik (Orang 2) pada 10 November 2026 atas pertanyaan Orang 1 yang dicatat di `docs/team-log/DEPLOY-ORANG2-sync.md`. Bagian ini menegaskan atau melengkapi bagian 11; bila berbeda, bagian ini berlaku.

| Butir | Penegasan pemilik | Mengubah |
|---|---|---|
| (a) | Persetujuan D-26 sampai D-35 ditulis di bagian 10 dengan "Pemilik = Orang 2", nama Faisal Nisrina, tanggal 10 November 2026. | Bagian 10 |
| (b) | **D-33, akun dan akses.** Orang 2 menjalankan semua deploy (Hobby tidak dapat menambah anggota); Orang 1 tidak memerlukan akses akun. Pemegang akun **Vercel dan Supabase = Orang 2**. Kedua akun dibuat dengan **email perusahaan**, bukan email pribadi. Syarat D-33 "2FA aktif" tetap berlaku; **status 2FA Vercel dan Supabase belum dijawab pemilik** dan dikonfirmasi sebelum DEPLOY-VERIFY-001 dimulai. | D-33 (melengkapi) |
| (c) | **D-35, kapan repo menjadi privat.** Setelah DEPLOY-PILOT-001 (deploy Preview) selesai, **sebelum** DEPLOY-SECURITY-001 dimulai, dan **paling lambat sebelum** DEPLOY-PRODPREP-001. Ini menggantikan jawaban pemilik sebelumnya ("setelah Task Board selesai dan setelah deploy"). Sampai gate tercapai, repo tetap publik dan tidak ada analisis keterjangkauan advisory yang ditulis di repo. Saat repo menjadi privat, Vercel perlu diberi izin membaca repo itu (langkah D-35). | D-35 (mengganti waktu pelaksanaan) |
| (d) | **D-34, batas upgrade Laravel.** Upgrade ke Laravel 12 **paling lambat 4 minggu setelah go-live**. Pemilik **menerima risiko** advisory yang menunggu upgrade itu, dan penerimaan ini tertulis sebelum go-live (bagian 10 dan 12 ini). Tahap `composer update` terbatas tetap dilakukan **sebelum** go-live. D-34 tetap menahan DEPLOYMENT-001. | D-34 (melengkapi) |

**Fakta dukungan versi Laravel (diverifikasi penyusun patch pada 10 November 2026 dari halaman rilis resmi Laravel, `laravel.com/framework/docs/12.x/releases`):** Laravel 11 keamanan berakhir 12 Maret 2026. Laravel 12: perbaikan bug berakhir 13 Agustus 2026, perbaikan keamanan berakhir **24 Februari 2027**. Laravel 13 (PHP 8.3 sampai 8.5) dirilis 17 Maret 2026. Akibatnya jendela keamanan Laravel 12 pendek: bila go-live jatuh akhir 2026 atau awal 2027, batas 4 minggu jatuh dekat atau melewati 24 Februari 2027. Target versi (12 atau 13) ditinjau lagi di `DEPLOY-SECURITY-001` dan **tidak diubah di sini**; keputusan 12 oleh pemilik tetap berlaku sampai pemilik mengubahnya.
