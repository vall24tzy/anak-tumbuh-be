# API Contract v1.2 — Addendum: INTEG-DASHHM-001 (total sekolah di dashboard kepala sekolah)

Status: implementasi INTEG-DASHHM-001 (Orang 2), 20 Okt 2026. Dasar: Authorization Matrix v5.3 baris #68,
perluasan OD-11 / API Contract v1.2 §25, Task Board INTEG v1.5. Sifat perubahan: **additive**; field dan
endpoint lama tidak diubah atau dihapus.

## 1. Field baru

`GET /api/v1/dashboard/headmaster` (Headmaster own school) memuat tiga field baru di AKHIR objek `data`:

| Field | Tipe | Definisi |
|---|---|---|
| `total_siswa` | integer | Jumlah siswa dengan assignment aktif pada rombel tahun ajaran aktif. Sama dengan jumlah `student_count` seluruh rombel pada response yang sama. Siswa inactive/graduated (assignment ended) tidak dihitung |
| `total_guru` | integer | Jumlah Teacher berstatus `active` di sekolah Headmaster. Teacher tidak terikat tahun ajaran |
| `total_rombel` | integer | Jumlah rombel pada tahun ajaran aktif sekolah. Sama dengan panjang array `rombels` |

Urutan field: `rombels, total_siswa, total_guru, total_rombel`. Isi `rombels` (id, name, grade_level,
student_count, average_submitted_today) tidak berubah.

Tidak ada field nama sekolah atau tahun ajaran aktif: nama sekolah tetap dari `GET /auth/me`, tahun ajaran
aktif dari `GET /academic-years` (`is_active`).

## 2. Kasus tepi (konfirmasi pemilik, 20 Okt 2026)

Acceptance criteria task berbunyi "sekolah tanpa tahun ajaran aktif -> total 0 tanpa error". Tafsiran yang
dikonfirmasi pemilik:

- Tanpa tahun ajaran aktif: `rombels = []`, `total_siswa = 0`, `total_rombel = 0` (keduanya terikat tahun ajaran
  aktif), dan `total_guru` TETAP jumlah guru aktif di sekolah itu.
- Tahun ajaran aktif tanpa rombel: sama seperti di atas.
- Kedua kasus tetap HTTP 200 dan memuat ketiga field (bukan `undefined` di frontend).
- Review silang Orang 1 (reviewer, definisi siswa aktif): BELUM dilakukan saat dokumen ini ditulis. Pemilik meminta
  Orang 1 mengiyakan tafsiran ini saat review silang; hasilnya dicatat di team-log, bukan di sini.

## 3. Otorisasi dan scope

Tidak berubah: hanya Headmaster aktif; role lain 403; tanpa token 401; angka hanya untuk sekolah Headmaster
yang login. Tidak ada rate limit baru (D-04).

## 4. Implementasi

`DashboardService::forHeadmaster()`. `total_siswa` diturunkan dari query `student_count` yang sudah ada (tanpa
query siswa tambahan); `total_guru` satu query baru; `total_rombel` dari koleksi rombel yang sudah dimuat.
Jumlah query tidak naik seiring jumlah rombel (dikunci test).

## 5. Dampak ke dokumen lain

- API Contract v1.2 §25: contoh response dashboard/headmaster perlu menambah tiga field pada penggabungan
  v1.3 (INTEG-DOC-001).
- `docs/authorization-matrix.md` di repo: baris #68 ditambahkan oleh INTEG-DOC-001.
