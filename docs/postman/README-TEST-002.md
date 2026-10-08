# TEST-002 — Automated Testing & Bug Reporting (Modul Orang 2)

Collection Postman untuk regresi end-to-end **seluruh endpoint modul Orang 2**:
School, Headmaster, Teacher, Academic Year, Master Rombel, Teacher Class Assignment,
Teacher Lifecycle, Import Guru, Dashboard Headmaster/Super Admin, Upload (logo/foto),
Reset Password & Change Password.

**187 request**, dikelompokkan 13 folder berurutan (0-12). Dijalankan lewat **Collection
Runner** (GUI Postman) atau **newman** (CLI) — bukan diklik satu-satu, karena banyak
request saling bergantung (token, id) lewat *collection/environment variables* yang
di-set otomatis oleh script `Tests` tiap request.

---

## 0. Yang TIDAK dicakup di sini (dan kenapa)

- **Student / STUDENT-ASSIGNMENT / STUDENTHABIT / QR / IMPORT-001 (Student)** — modul
  Orang 1, di luar scope TEST-002. Satu-satunya Student yang dibuat di sini (`s1_id`,
  folder 6) murni **fixture**, dipakai untuk memicu skenario di modul Orang 2 (dashboard
  Headmaster butuh data Student, reset-password Student adalah endpoint Orang 2 walau
  targetnya Student).
- **LEVEL-RANKING-001** — endpoint gamification level dan ranking SUDAH ada (task Orang 1 selesai
  setelah suite ini pertama kali dibuat), tetapi tetap di luar scope TEST-002. Terkunci oleh PHPUnit
  (`GamificationLevelDataTest`, `GamificationLevelTest`, `RankingTest`) dan sebagian oleh TEST-001
  (`GET /gamification/me`). TEST-002 hanya menyentuh `gamification_level` lewat `dashboard/student`
  (folder 11).
- **AUDIT_LOGS isi/format** — endpoint sendiri tidak ada (`audit_logs` cuma infrastruktur
  internal AUTH-003), tidak bisa dites lewat HTTP.

## 1. Cara pakai

1. Buka Postman → **Import** → pilih `TEST-002.postman_collection.json` **dan**
   `TEST-002.postman_environment.json`.
2. Pilih environment **"TEST-002 Local"** di pojok kanan atas.
3. Set **Working Directory** supaya path file di request Upload/Import (`test-files/...`)
   bisa ditemukan: Postman → Settings (gear icon) → **General** → *Working Directory* →
   arahkan ke folder `docs/postman/` ini. (Kalau tidak diset, request folder 9 & 10 yang
   pakai file akan gagal "file not found" — request lain tetap jalan normal.)
4. Pastikan backend jalan (`php artisan serve`) dan **`.env.testing`** mengarah ke
   database khusus testing (lihat sesi sebelumnya soal `.env.testing` — JANGAN jalankan
   suite ini ke database development, isinya akan tercampur data uji).
5. **Isi `sa_password` di environment** (variabel bertipe *secret*, sengaja KOSONG di file ini
   supaya tidak ada password di disk/git): buka Environments → "TEST-002 Local" → kolom
   *Current value* `sa_password` → isi password Super Admin yang dipakai di database uji. Jangan
   menulisnya ke file ini dan jangan meng-export environment yang sudah terisi ke repo.
   Request 0.1 dan 8.18 memakainya; tanpa nilai ini login SA (dan seluruh run) gagal 401.
   **Bila run terhenti di antara 8.17 dan 8.18** (atau 8.18 gagal), password Super Admin di database uji
   TERTINGGAL sebagai `SaSementara_2026` dan run berikutnya gagal login di 0.1. Pulihkan dulu:
   `php artisan admin:reset-password <sa_username>` (atau `migrate:fresh --seed`), lalu samakan `sa_password`.
   F-29: ganti password MENCABUT token lama, itu sebabnya ada 8.17b/8.17c/8.18b (login ulang SA).
   F-38: **tidak ada lagi** kebutuhan `php artisan queue:work` — import diproses sinkron di dalam
   request (101 baris bisa memakan puluhan detik karena hash password per baris).
6. Jalankan lewat **Collection Runner** (klik collection → Run), urutan folder
   dari atas ke bawah, **jangan diacak** (banyak dependency antar-request).
   Delay antar-request disarankan **0 ms** cukup (script sudah menangani rate-limit
   login secara otomatis — lihat bagian 3).

### Lewat CLI (newman)

```bash
npm install -g newman
newman run TEST-002.postman_collection.json -e TEST-002.postman_environment.json \
  --working-dir . --delay-request 50
```

## 2. Menjalankan berulang kali (re-run)

Semua data yang dibuat collection ini (nama School, NIP, NISN) diberi **suffix unik
`run_id`** (timestamp, di-generate ulang di request `0.1`) — jadi **collection ini aman
dijalankan berkali-kali ke database yang sama** tanpa bentrok "duplicate" dari sisi HTTP.

**Kecuali file Excel** (`test-files/teacher_*.xlsx`) — NIP di dalamnya **statis** (di-bake
saat file dibuat), tidak ikut berubah otomatis tiap run Postman. Sebelum run kedua dst,
jalankan ulang:
```bash
pip install openpyxl pillow   # sekali saja
python3 generate_test_files.py
```
Ini membuat ulang semua file `test-files/*` dengan NIP baru (suffix timestamp saat ini)
dan **otomatis memperbarui** 5 variabel `imp_nip_valid_1`, `imp_nip_valid_2`,
`imp_nip_big_1`, `imp_nip_dup_1`, `imp_nip_trim_1` di `TEST-002.postman_environment.json` —
**import ulang environment itu ke Postman setelah menjalankan script ini** (Postman tidak
baca ulang file otomatis) dan isi ulang `sa_password`.

Alternatif paling bersih: `php artisan migrate:fresh --seed` sebelum tiap run penuh.

## 3. Rate limiting login (`throttle:5,1`)

`POST /auth/login/staff` dan `/auth/login/student/password` dibatasi backend **5x per
menit**. Suite ini login **>5 kali** (HM1, HM2, HMC2, T1 3x setelah reset password, dst).
**Collection-level pre-request script** (lihat tab *Pre-request Script* di root
collection) otomatis mendeteksi request bernama `[LOGIN] ...` dan menyisipkan jeda kalau
jatah 5x/menit sudah terpakai — **tidak perlu diatur manual**, tapi berarti run penuh
collection ini makan waktu **>1 menit** karena beberapa titik login memang harus
menunggu window rate-limit reset. Ini disengaja, bukan bug script.

## 4. Aturan global (dicek di SETIAP response)

Collection-level **Tests script** (root) memeriksa **setiap** response JSON secara
rekursif: tidak boleh ada key `password`, `new_password`, `generated_password`, atau
`password_hash` di manapun. Kalau ada satu saja endpoint yang bocor, test global ini
akan merah di request itu — di luar assertion spesifik per-request.

## 5. Isi folder

| # | Folder | Fokus |
|---|---|---|
| 0 | Setup | Login Super Admin awal, generate data unik run |
| 1 | School (SA only) | CRUD terbatas (tanpa Update/Delete/Import), validasi |
| 2 | Headmaster (SA only) | Create + **suksesi** (Headmaster lama → inactive) + link ke revisi `headmaster_count` |
| 3 | Teacher (Headmaster) | Create own-school, cross-school → 403 |
| 4 | Academic Year | One-active-per-school via `activate`, PATCH tidak bisa ubah `is_active`, PATCH sebagian tanggal dinilai terhadap nilai tersimpan (F-04) |
| 5 | Master Rombel | CRUD minus, `academic_year_mismatch`, batas `grade_level` (F-20), Teacher tanpa assignment = list kosong / detail 404 (F-21), delete-guard 409 |
| 6 | Teacher Class Assignment | Satu Teacher satu Rombel aktif DAN satu Rombel satu Teacher aktif per tahun (409, F-16/F-18), assignment inactive read-only (F-17), lock field Rombel (F-19), scope Teacher (F-21), mismatch (422) |
| 7 | Teacher Lifecycle | `inactive`, cascade tutup assignment, cross-school = **404** (bukan 403 — keputusan final §15) |
| 8 | Password | Reset hierarki SA→HM→Teacher, `change-password` SA-only |
| 9 | Upload | Logo School, foto Student, replacement, **[SECURITY] temuan ekstensi file** |
| 10 | Import Guru | Semua ukuran SINKRON (F-38), `.xlsx` saja (F-33), file rusak/header hilang 422 (F-34), NIP kembar dan trim (F-35/F-36), partial-failure per baris, status batch = riwayat hasil (F-32) |
| 11 | Dashboard | Per-rombel (Headmaster), global (SA) |
| 12 | Authorization Sweep | ~90 kombinasi role×endpoint lewat `pm.sendRequest`, harus semua ditolak |
| 13 | Perilaku final Stage 1-5 | F-53 (NISN vs username staf + kontrol reuse), F-06 (foto private), F-15 (`meta` di keenam endpoint list, `per_page` > 100 = 422, halaman di luar rentang) |
| 14 | [OPSIONAL] Ukur waktu | Import tepat 200 baris (batas atas, sinkron; catat waktu respons) dan 201 baris (422). Lewati bila tidak ingin 200 guru baru. |

Detail lengkap tiap request ada di **deskripsi request itu sendiri** di Postman (klik
request → tab di bawah URL bar) — bukan diulang di sini supaya tidak ada 2 sumber
kebenaran yang bisa beda.

## 6. Temuan yang PERLU DIBACA sebelum lapor "semua hijau"

Beberapa request **sengaja** tidak menguji "hasil ideal" karena hasil ideal itu sendiri
belum ditentukan tim, atau justru menguji **perilaku aktual sebagai temuan**, bukan
sebagai bug pasti. Semua ditandai `[SECURITY]` atau `[EKSPLORATIF]` di nama request, dan
dijelaskan lengkap di `TEST-002-findings.md` — **baca file itu sebelum bilang test ini
"semua lolos"**, karena "lolos" untuk request eksploratif berarti "berhasil merekam
perilaku", bukan otomatis "aman".
