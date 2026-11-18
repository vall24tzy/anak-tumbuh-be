# DEPLOY-SECURITY-001 — Log

**Status:** `composer update` terbatas, audit sebelum dan sesudah, addendum, dan team-log selesai; test lokal hijau (695 passed, 4.324 assertion); menunggu review silang dan tanda tangan penerimaan risiko Orang 2. Belum DONE.
**PIC:** Orang 1 (usulan) · **Tanggal:** 18 Nov 2026 · **Cabang:** `deploy/vercel-security` dari `deploy/vercel-pilot` @ 60aac12.
**Dependency:** DEPLOY-DECISION-001 (D-34, DONE), repo privat (D-35, selesai 17 Nov 2026).

## Dikerjakan
1. `composer audit` sebelum: 20 entri (19 isu) pada 8 paket.
2. `composer update` terbatas pada 7 paket (dry-run lebih dulu; 7 naik, 0 pasang, 0 hapus), lalu `composer audit` sesudah: 5 entri (4 isu), hanya `laravel/framework`.
3. `composer.lock` berubah (41 baris ditambah, 41 dihapus); `composer.json` tidak berubah.
4. `docs/addendum/DEPLOY-SECURITY-001.md`: status per advisory, patokan upgrade Laravel, tabel penerimaan risiko untuk ditandatangani Orang 2.

## Diperiksa
- `php artisan test` penuh setelah update (dijalankan Orang 1 di lokal): **695 passed (4.324 assertions)**, termasuk `FrameworkAdvisoryGuardTest` dan `MailExposureGuardTest`.
- Keterjangkauan advisory laravel: pembacaan statis kode dan tes penjaga yang sudah ada.

## Belum selesai (syarat DONE)
1. Review silang Orang 2.
2. Orang 2 menandatangani penerimaan risiko di addendum bagian 5 (AC 5) sebelum go-live.
3. Import `.xlsx` di Preview setelah phpspreadsheet 1.30.8 (usul: TEST-001, import 150 dan 200 baris, saat redeploy berikutnya).
4. Memastikan `APP_DEBUG=false` di environment Preview dan produksi (syarat isu 17).
5. DEPLOY-DOC-001: catat bahwa upgrade Laravel 11 ke 12 atau 13 paling lambat 4 minggu setelah go-live.

## Temuan
1. Terminal Git Bash memakai PHP 8.2.12 sehingga `composer update --dry-run` gagal (proyek mewajibkan PHP ^8.3); dipakai terminal dengan PHP 8.3.30 (composer 2.4.1). Tidak memakai `-W` atau `--ignore-platform-reqs`.
2. Daftar 20 entri mengandung satu advisory ganda (`GHSA-5vg9` dan `CVE-2026-48019`), jadi 19 isu berbeda.
3. Isu `Concurrency` (terbit 13 Nov) belum punya tes penjaga; pencarian statis tidak menemukan pemakaiannya.
4. Pencocokan awal saya dari basis data advisory publik (FriendsOfPHP dan GitHub Advisory Database), sebelum audit lokal Orang 1, menghasilkan 19 isu berbeda; ini sama dengan `composer audit` lokal setelah entri ganda digabung.
