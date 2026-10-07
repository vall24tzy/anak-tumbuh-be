# Timeout endpoint import (F-38) — konfigurasi server

Import Teacher dan Student diproses SINKRON di dalam satu request (F-38) dan tiap baris membuat akun (hash password).
Waktu respons linear terhadap jumlah baris. Ini konfigurasi **server**, bukan `.env` aplikasi (satu-satunya hal terkait di
`.env` adalah `BCRYPT_ROUNDS`, yang menentukan biaya per baris).

## Dasar angka (terukur, dev lokal Windows, `php artisan serve`)

| Kondisi | Waktu |
|---|---|
| `BCRYPT_ROUNDS` bawaan, 101 baris | 60,9 dtk (~0,60 dtk/baris) |
| `BCRYPT_ROUNDS` bawaan, 1.000 baris | 577,5 dtk |
| `BCRYPT_ROUNDS=4`, 101 baris | 6,7 dtk (~0,067 dtk/baris) |

Batas baris per file kini **200** (`App\Services\Import\ImportService::MAX_ROWS`). Pada biaya ~0,6 dtk/baris, 200 baris
sekitar **120 dtk**. Karena itu timeout endpoint ditetapkan **180 dtk** (margin 1,5x). Angka produksi bisa berbeda; ukur ulang
di server sungguhan sebelum rilis (lihat `docs/postman/TEST-002-manual-checks.md` §3). Timeout 90 atau 120 dtk TIDAK cukup
untuk 200 baris pada biaya hash di atas.

## nginx + PHP-FPM (hanya dua endpoint, bukan global)

Dua path: `POST /api/v1/teachers/import` dan `POST /api/v1/student-imports/commit`. (`/student-imports/preview` tidak membuat
akun, jadi tidak perlu.) Letakkan SEBELUM `location ~ \.php$`/front-controller umum Anda; sesuaikan `fastcgi_pass`.

```nginx
location ~ ^/api/v1/(teachers/import|student-imports/commit)$ {
    include fastcgi_params;
    fastcgi_pass            unix:/run/php/php8.3-fpm.sock;          # sesuaikan versi/socket
    fastcgi_param           SCRIPT_FILENAME $realpath_root/index.php;
    fastcgi_param           PHP_VALUE "max_execution_time=180";      # hanya untuk request ini, bukan php.ini global
    fastcgi_read_timeout    180s;
    client_max_body_size    5m;                                      # sama dengan batas file di aplikasi
}
```
Catatan:
- `PHP_VALUE` lewat `fastcgi_param` tidak berlaku bila pool memakai `php_admin_value[max_execution_time]` (terkunci); ubah di pool.
- Hanya SATU `fastcgi_param PHP_VALUE` per blok; gabungkan beberapa nilai dengan baris baru di dalam string yang sama.
- Bila nginx berperan sebagai reverse proxy ke server HTTP lain (bukan FPM), gunakan `proxy_read_timeout 180s;`.

## PHP-FPM pool

`request_terminate_timeout` adalah batas bunuh keras per pool (bawaan 0 = tidak aktif). Bila di server Anda bernilai di bawah 200s,
naikkan menjadi minimal `200s`; bila 0, biarkan. Nilai ini berlaku untuk seluruh pool, tetapi hanya menjadi plafon karena
`max_execution_time` endpoint lain tetap 30 dtk.

## Lapisan lain yang bisa memotong lebih dulu

Load balancer, CDN, atau WAF di depan nginx memiliki timeout sendiri. Contoh: Cloudflare memutus request proxied pada sekitar
100 dtk (HTTP 524), sehingga 180 dtk TIDAK akan tercapai di belakangnya. Periksa seluruh rantai; bila ada batas di bawah 180 dtk,
turunkan `MAX_ROWS` mengikuti batas itu (waktu ≈ 0,6 dtk x baris pada biaya hash bawaan).

## Perilaku bila request terpotong

Tiap baris punya transaksi sendiri: baris yang sudah selesai tetap tersimpan dan batch tertahan `processing`
(`GET .../import/status/{batchId}`). Pengguna harus mengunggah ulang hanya sisa baris (NIP/NISN yang sudah masuk akan ditolak sebagai duplikat
per baris, bukan menggagalkan seluruh file).

## Verifikasi setelah konfigurasi

1. Reload: `sudo nginx -t && sudo systemctl reload nginx` (dan `php-fpm` bila pool diubah).
2. Jalankan request Postman `14.01` (file tepat 200 baris) dan catat waktu respons di Console. Harus 200 tanpa 504/524.
3. Jalankan `14.02` (201 baris): harus 422, tidak diproses.
