# TEST-002 — Pengecekan Manual (di luar kemampuan Postman)

Postman hanya melihat response HTTP. Empat hal di bawah tidak bisa diverifikasi lewat assertion Postman dan
dicek manual SETELAH collection selesai (kecuali disebut lain). Direvisi Stage 6 agar sesuai perilaku final:
foto Student kini di disk **`private`** (F-06), ekstensi `.php` kini ditolak 422 (F-05), import sinkron (F-38).

Jalankan dari root repo backend. Semua langkah Tinker memakai `php artisan tinker`.

## 1. File foto lama benar-benar terhapus saat replace (request 9.07 → 9.08) — F-06/F-08

9.07 mengunggah foto PNG, 9.08 menggantinya dengan JPG. Postman hanya melihat URL di response, bukan isi disk.
Foto disimpan di disk `private` (`storage/app/private/students/{id}.{ext}`), BUKAN `public`.

```php
$s = \App\Models\Student::find(<isi s1_id dari environment Postman>);
$s->photo_path;                                                        // harus "students/{id}.jpg"
Storage::disk('private')->exists("students/{$s->id}.jpg");             // true  (file baru ada)
Storage::disk('private')->exists("students/{$s->id}.png");             // false (file lama HARUS sudah terhapus)
Storage::disk('public')->exists("students/{$s->id}.jpg");              // false (tidak boleh ada salinan publik)
```
Bila `.png` masih `true`: bug di `UploadService` (urutan simpan-baru-lalu-hapus-lama, F-08). Bila salinan `public` ada: foto
masih bocor ke disk publik (F-06); jalankan `php artisan photos:migrate-to-private` dan laporkan.

## 2. Tidak ada file `.php` tersimpan dari uji upload berbahaya (request 9.06) — F-05

9.06 mengunggah logo bernama `.php`; yang benar sekarang 422 (ditolak) dan tidak ada file yang tersimpan. Postman tidak
bisa melihat disk, jadi cek:
```powershell
Get-ChildItem storage\app\public -Recurse -Include *.php,*.phtml,*.phar,*.pht
```
Hasil HARUS kosong. Di Tinker:
```php
\App\Models\School::where('logo_path', 'like', '%.php')->count();   // harus 0
\App\Models\School::whereNotNull('logo_path')->pluck('logo_path');  // hanya berekstensi png/jpg/webp
```
Bila ada hasil: temuan security; hentikan dan laporkan ke pemilik (jangan membuka/menjalankan file tersebut).

## 3. Waktu respons import sinkron (request 10.14 dan 14.01) — F-38

Sejak F-38 import diproses SINKRON di dalam request. **Sudah diukur pada run Stage 6** (lingkungan dev lokal Windows,
`php artisan serve`, `BCRYPT_ROUNDS` belum dicatat): **101 baris = 60,9 dtk; 1.000 baris = 577,5 dtk (9 mnt 37 dtk)**,
sekitar 0,58 dtk per baris dan linear terhadap jumlah baris. Artinya 101 baris SUDAH melampaui timeout bawaan
(PHP-FPM `max_execution_time` 30 dtk, nginx 60 dtk), dan 1.000 baris tidak layak sinkron di konfigurasi bawaan.
Bila terpotong di tengah, sebagian baris sudah tersimpan dan batch tertahan `processing`.

Untuk mengetahui apakah biaya terbesar ada di hash password (bisa diturunkan lewat konfigurasi) atau di query:
1. Cek nilai sekarang: `php artisan tinker` → `config('hashing.bcrypt.rounds')`.
2. Catat `max_execution_time` runtime server: `php -i | findstr max_execution_time` (CLI/FPM bisa berbeda).
3. Set `BCRYPT_ROUNDS=4` sementara di `.env`, restart server, jalankan ulang request 10.14 saja, lalu bandingkan:
   bila turun drastis (mis. dari 61 dtk menjadi di bawah 10 dtk), hash adalah biaya dominan. Kembalikan nilai semula sesudah uji.
4. Catat hasilnya di `docs/testing/BASELINE-STAGE-0C.md` §5. Keputusan (naikkan timeout dan turunkan batas baris, kurangi biaya
   per baris, atau tinjau ulang F-38) ada di pemilik project.

## 4. Rate limit login tidak membuat request lain gagal — F-23

Setelah run selesai, buka Postman Console. Baris `Login throttle: menunggu ... detik` itu normal. Yang TIDAK normal: assertion
"kredensial ditolak (bukan 429/500)" di request `[LOGIN]` mana pun merah karena 429, artinya script throttle collection meleset dari
konfigurasi backend. Laporkan bersama isi `routes/v1/auth.php` (saat ini `throttle:5,1` per IP + `throttle:login-identifier` per
username/NISN, F-23).

Cek manual opsional dua dimensi (sudah dikunci PHPUnit `LoginRateLimitTest`). Jangan jalankan saat Postman sedang berjalan, karena
mengunci IP Anda ±1 menit:
```powershell
1..6 | ForEach-Object {
  try { (Invoke-WebRequest -UseBasicParsing -Method Post -Uri http://localhost:8000/api/v1/auth/login/staff `
        -ContentType 'application/json' -Body (@{ username = "coba$_"; password = 'salah' } | ConvertTo-Json)).StatusCode }
  catch { [int]$_.Exception.Response.StatusCode }
}
```
Harapan: lima kali `401`, keenam `429` (counter per IP, identifier berbeda tiap kali). Tunggu 60 detik sebelum lanjut.

## 5. Password Super Admin pulih setelah run (8.17 → 8.18/8.18b) — F-29

8.17 mengganti password SA sementara dan 8.18 memulihkannya. Bila run terhenti di antara keduanya, password SA tertinggal
`SaSementara_2026`. Cek: login `0.1` pada run berikutnya harus 200. Bila 401, pulihkan dengan
`php artisan admin:reset-password <sa_username>` lalu samakan `sa_password` di environment.
