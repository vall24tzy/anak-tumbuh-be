# TEST-002 — Daftar Temuan (butuh keputusan/tindak lanjut manusia)

Ini bukan hasil eksekusi (lihat catatan status eksekusi di `README-TEST-002.md` dan di
pesan pengantar) — ini daftar hal yang **ditemukan lewat pembacaan kode saat menyusun
test**, supaya tidak hilang begitu saja di antara ratusan assertion. Setelah kamu
menjalankan collection-nya, cocokkan hasil aktual dengan prediksi di sini.

---

## 🟡 [CODE SMELL, direvisi — sebelumnya ditulis sebagai SECURITY/bug] Ekstensi file upload ikut nama file client, bukan konten

**File:** `app/Services/Upload/UploadService.php`, baris `$extension = strtolower($file->getClientOriginalExtension());`

**Revisi status (setelah run aktual):** entri ini awalnya ditulis sebelum collection
dijalankan, dengan ekspektasi request `9.06` lolos `201` (dan MEMBUKTIKAN file `.php`
tersimpan apa adanya di storage publik). Setelah dijalankan, hasil aktualnya **422**
(`validation_failed`) — Laravel punya proteksi bawaan `shouldBlockPhpUpload()`
(`Illuminate\Foundation\Validation\ValidatesAttributes`) yang menolak ekstensi
`.php`/`.phtml`/`.phar` pada rule `image`/`mimes` **secara unconditional**, walau
konten file itu benar-benar gambar asli — proteksi ini jalan di level `FormRequest`
(`CreateSchoolRequest`), **sebelum** request sampai ke `UploadService::upload()`.
Assertion request `9.06` sudah diperbarui mengikuti temuan ini (expect `422`, bukan `201`).

**Kesimpulan:** kode smell di baris `getClientOriginalExtension()` **tetap ada** (ekstensi
disk seharusnya ditentukan dari deteksi konten, bukan nama file client), tapi **saat ini
tidak ada jalur endpoint manapun yang bisa mengeksploitasinya** — semua endpoint upload
(`CreateSchoolRequest`, `CreateStudentRequest`, `UpdateStudentRequest`) memakai rule
`mimes:jpg,jpeg,png,webp` yang otomatis kena proteksi `shouldBlockPhpUpload()` yang sama.

**Rekomendasi (bukan keputusan final, perlu didiskusikan tim):** tetap ganti
`$file->getClientOriginalExtension()` dengan `$file->extension()` (berbasis
`guessExtension()` dari MIME type asli) sebagai **defense-in-depth** — bukan untuk
menutup celah yang sudah tertutup proteksi Laravel, tapi supaya keamanan tidak
bergantung 100% pada satu lapis proteksi framework yang bisa berubah di versi mendatang.

---

## 🔴 [BUG BARU] Request 1.01–1.03 & 9.01–9.02 — `POST /schools` balikin HTTP 200, seharusnya 201

**File:** `app/Http/Controllers/V1/School/SchoolController.php`, method `store()`

```php
public function store(CreateSchoolRequest $request)
{
    $school = $this->service->store($request->validated());
    return $this->successResponse(new SchoolResource($school), 'Sekolah berhasil dibuat');
    // successResponse($data, $message, int $code = 200, ...) -> $code tidak diisi -> default 200
}
```

**Perbandingan dengan endpoint create lain yang sudah benar** (`RombelController::store()`,
`HeadmasterController::store()`, `TeacherController::store()`,
`TeacherAssignmentController::store()`, `StudentController::store()`) — semuanya
eksplisit memanggil `successResponse(..., code: 201)`. Hanya `SchoolController::store()`
yang lupa mengisi parameter `code`, jadi jatuh ke default `200` milik
`ApiResponseTraits::successResponse()`.

**Dampak:** melanggar API Contract v1.2 §8 (konvensi umum: resource baru dibuat → `201
Created`) secara konsisten di 1 endpoint saja (`POST /schools`) — inkonsisten dengan
seluruh endpoint create lain di modul yang sama.

**Bukti:** 5 kegagalan — `1.01`, `1.02`, `1.03` (folder School), `9.01`, `9.02` (folder
Upload, sama-sama create School dengan logo).

**Rekomendasi (bukan keputusan final):**
```php
return $this->successResponse(new SchoolResource($school), 'Sekolah berhasil dibuat', code: 201);
```

---

## 🔴 [BUG BARU] Request 9.07 & 9.08 — Replace foto Student (`PATCH /students/{id}` multipart) selalu 422 `protected_field`

**File:** `app/Http/Requests/Student/UpdateStudentRequest.php`, method `withValidator()`

```php
public function withValidator($validator)
{
    $validator->after(function ($validator) {
        foreach (array_keys($this->all()) as $field) {
            if (!in_array($field, $this->allowed, true)) {
                $validator->errors()->add($field, 'protected_field');
            }
        }
    });
}
```

**Akar masalah:** endpoint ini di-PATCH lewat multipart method-spoofing — `POST` +
field `_method=PATCH` (wajib, dijelaskan sendiri di deskripsi request: *"PATCH
multipart harus dikirim sebagai POST + _method=PATCH (PHP tidak mem-parse multipart
untuk PATCH)"*). Laravel **membaca** `_method` untuk menentukan verb aktual, tapi
**tidak menghapusnya** dari `$request->all()` — jadi `_method` tetap ikut ke
`withValidator()`. Karena `_method` tidak ada di whitelist `$allowed = ['name',
'gender', 'photo']`, guard "tolak field asing" ini menganggap `_method` sebagai
protected field dan menambahkan error `protected_field`, membuat validasi gagal
(`422`) — **padahal request-nya sah**.

**Dampak:** **setiap** `PATCH /students/{id}` multipart (upload/replace foto Student)
**selalu gagal 422**, tidak peduli isi file-nya valid atau tidak — ini genuine bug
produksi, bukan artefak test. Request tanpa file (PATCH biasa `application/json`, tanpa
`_method`) tidak kena bug ini karena tidak butuh method-spoofing.

**Bukti:** `9.07` (replace foto PNG) dan `9.08` (replace foto JPG) — keduanya 422,
seharusnya 200.

**Rekomendasi (bukan keputusan final):** kecualikan `_method` (dan idealnya field
reserved Laravel lain seperti `_token` kalau suatu saat dipakai) dari pengecekan:
```php
foreach (array_keys($this->except(['_method'])) as $field) {
    if (!in_array($field, $this->allowed, true)) {
        $validator->errors()->add($field, 'protected_field');
    }
}
```

---

## 🔴 [BUG TERKONFIRMASI] Request 4.10 — `activate()` pada AY yang sudah aktif malah jadi `false`

**Status:** dikonfirmasi lewat run asli (bukan dugaan) — response `4.10` di Postman
menunjukkan `is_active: false`, dan `AcademicYear::find(<id>)` di Tinker mengonfirmasi
DB memang tersimpan `false`.

**File:** `app/Services/School/AcademicYearService.php`, method `activate()`

```php
return DB::transaction(function () use ($academicYear) {
    AcademicYear::where('school_id', $academicYear->school_id)
        ->where('is_active', true)
        ->update(['is_active' => false]);   // <- termasuk $academicYear sendiri kalau sudah aktif

    $academicYear->update(['is_active' => true]);   // <- Eloquent skip UPDATE ini

    return $academicYear->fresh();
});
```

**Akar masalah:** `$academicYear` adalah objek Eloquent yang di-load **sebelum**
mass-update di atasnya dijalankan. Kalau AY ini **sudah** aktif (kasus idempotent),
nilai `is_active` di memori objek itu sudah `true`. Saat `$academicYear->update(['is_active'
=> true])` dipanggil, Eloquent membandingkan nilai baru (`true`) dengan nilai yang **dia
ingat di memori** (`true` juga) — dianggap "tidak berubah" (not dirty), sehingga Eloquent
**tidak mengirim query UPDATE sama sekali**. Padahal baris sebelumnya (query builder
langsung, di luar sepengetahuan objek `$academicYear`) barusan mengubah baris itu jadi
`false` di database. Hasil akhir: DB tersimpan `false`, dan `->fresh()` di baris terakhir
jujur melaporkan kondisi itu.

**Kenapa cuma muncul di kasus idempotent:** di `4.08` (aktifkan AY yang *sebelumnya
tidak aktif*), nilai lama-di-memori `false` vs baru `true` — beda, Eloquent tetap kirim
UPDATE, hasilnya benar. Bug ini spesifik ke kasus "aktifkan AY yang sudah aktif".

**Dampak:** endpoint `POST /academic-years/{id}/activate` **tidak benar-benar
idempotent** — memanggilnya 2x pada AY yang sama justru **menonaktifkan** AY itu (tidak
ada AY aktif sama sekali untuk sekolah itu setelahnya), bukan "tidak berubah apa-apa"
seperti yang diharapkan API Contract §13. Ini bisa berdampak nyata: Frontend yang
tidak sengaja mengirim request activate dobel (misal user klik tombol 2x) akan membuat
sekolah itu **kehilangan Academic Year aktif** tanpa sadar.

**Rekomendasi (bukan keputusan final):**
```php
AcademicYear::where('school_id', $academicYear->school_id)
    ->where('is_active', true)
    ->where('id', '!=', $academicYear->id)   // <- tambahan ini
    ->update(['is_active' => false]);

$academicYear->update(['is_active' => true]);
```
Dengan mengecualikan `id` target dari mass-deactivate, kalau dia memang sudah aktif dia
tidak pernah disentuh sama sekali (tetap `true`), menghindari seluruh masalah
dirty-tracking di atas. Alternatif lain: pakai query builder langsung
(`AcademicYear::where('id', $academicYear->id)->update(['is_active' => true])`) untuk
baris reaktivasi, supaya tidak lewat pengecekan dirty Eloquent sama sekali.

---

## 🟡 [EKSPLORATIF] Request 10.07 — Struktur header Excel salah, tidak divalidasi eksplisit

**File:** `app/Services/Import/ImportService.php`

Tidak ada pengecekan "apakah header file cocok dengan `columnMap` yang diharapkan"
sebelum baris diproses. File dengan header `Foo/Bar/Baz` (bukan `Nama Lengkap/NIP/Gender`)
akan diproses tetap — tiap baris otomatis gagal validasi `required` (karena
`mapRow()` tidak nemu key yang diharapkan, jadi semua field jadi `null`), hasilnya
`200` dengan `rejected_rows` = jumlah baris, bukan `422` di level file.

**Assertion 10.07** mengizinkan kedua kemungkinan (`200` atau `422`) dan cuma memastikan
**bukan** `500` — jadi request ini akan tetap hijau apapun hasilnya, **tapi hasilnya perlu
dicatat manual** dari response body: kalau `200`, apakah pesan errornya cukup jelas buat
Headmaster paham "file kamu strukturnya salah, bukan datanya kosong"? Ini pertimbangan UX,
bukan bug — perlu keputusan tim apakah worth ditambah validasi header eksplisit.

## 🟡 [EKSPLORATIF] Request 10.08 — File tanpa baris data sama sekali

Sama seperti di atas — belum jelas apakah file 0-baris seharusnya `200` (total_rows: 0,
tidak ada yang diproses) atau `422` ("file kosong, tidak ada data"). Assertion cuma
memastikan bukan `500`. Catat hasil aktualnya.

## 🟡 [EKSPLORATIF] Request 10.18 — Isolasi data status import antar-Headmaster

**File:** `app/Http/Controllers/V1/School/TeacherImportController.php`, method `status()`

```php
$batch = ImportBatch::find($batchId);
```

Tidak ada filter `created_by` atau `school_id` — secara pembacaan kode, Headmaster
manapun yang tahu/menebak `batch_id` bisa `GET /teachers/import/status/{id}` milik
Headmaster lain dan **melihat isi errors-nya** (yang berisi nama & NIP calon Guru sekolah
lain). Request ini **tidak mengasumsikan** ini pasti bug — assertion cuma mencatat status
code aktual (`200`/`403`/`404`) ke Postman Console, karena `GET /teachers/import/status/{id}`
sendiri **tidak ada di API Contract v1.2** (NOTE-05, technical necessity untuk polling
queue) — jadi belum ada kontrak otoritatif soal scope-nya. Kalau hasil aktualnya `200`,
ini kemungkinan gap yang sama persis dengan pola cross-school lain yang sudah kita
perbaiki sebelumnya (403/404), tapi karena endpoint ini di luar API Contract, perlu
diputuskan dulu apakah masuk scope perbaikan atau menunggu endpoint ini masuk kontrak
resmi.

---

## ✅ Bukan bug, sudah dikonfirmasi by design (dicantumkan biar tidak dilaporkan ulang)

- **7.10** Cross-school Teacher Lifecycle → **404** (bukan 403): ini keputusan final tim
  yang sudah dibuat sebelumnya (lihat catatan §15 di riwayat kerja) — request ini
  memvalidasi keputusan itu, bukan melaporkan sebagai temuan baru.
- **2.18** `headmaster_count` tidak naik setelah suksesi Headmaster: ini hasil revisi
  yang sudah di-push ke `main` sebelum TEST-002 disusun — request ini regression-test
  untuk revisi tersebut.
- **6.02 / 10.13** kode error `teacher_already_has_active_assignment` (409) dan pesan
  "1.000 baris" — dikutip dari komentar/pesan yang sudah ada di kode saat ini, bukan
  ditebak. Kalau ternyata beda persis (typo dsb), assertion akan merah dan itu murni
  soal ketelitian teks, bukan gap desain.
- **10.15** (polling status batch import) sempat gagal "belum `done` setelah 20
  polling" walau `queue:work` sudah jalan — bukan bug backend. Akar masalahnya di
  prerequest script: `setTimeout(function () {}, 2000);` biasa **tidak benar-benar
  menunda eksekusi** di sandbox Postman (fire-and-forget), jadi 20x "polling dengan
  jeda 2 detik" sebenarnya cuma request beruntun tanpa jeda nyata — worker (default
  `--sleep=3`) belum sempat kebagian waktu. Dikonfirmasi manual: request status batch
  yang sama, di-Send ulang setelah nunggu ±15 detik nyata, hasilnya `done` dengan semua
  baris berhasil. Script sudah diperbaiki jadi `await new Promise(function (resolve) {
  setTimeout(resolve, 2000); });` (Postman v10+ mendukung top-level `await` di
  pre-request script) supaya jeda-nya beneran terjadi.

---

## Sinkronisasi Stage 6 (Okt 2026): collection disesuaikan dengan perilaku final Stage 1-5 + F-53

Baseline run asli (sebelum Stage 1-5): **627 test, 617 passed, 10 gagal**. Sepuluh kegagalan itu berasal dari
tiga bug produksi dan sudah diperbaiki; assertion-nya **tidak diturunkan**, sudah menuntut perilaku yang benar
(201, 200 idempoten, 422, 200 pada replace foto). Collection ini BELUM dijalankan ulang terhadap backend final
(tidak ada environment di sisi penyusun); tabel di bawah mendaftar PER REQUEST apa yang berubah.

| Kegagalan asli | Penyebab | Sekarang |
|---|---|---|
| 1.01-1.03, 9.01-9.02 | `POST /schools` 200 (F-12) | Assertion sudah 201; kode memberi 201 (`7396a1a`) |
| 4.10 | `activate` AY aktif menjadi `false` (F-02) | Assertion 200 + tetap `true`; diperbaiki `4987936` |
| 9.06 | Ekstensi `.php` tersimpan (F-05) | Assertion 422 `validation_failed`; diperbaiki `4796b7a` |
| 9.07-9.08 | PATCH multipart 422 `protected_field` (F-46) | Assertion 200; diperbaiki `33d444e`. Regex `photo` diubah ke URL endpoint (F-06) |

Daftar lengkap perubahan collection (kolom 2: BARU / UBAH / HAPUS):

| Request | Perubahan | Alasan / rujukan |
|---|---|---|
| 3.09 List Teacher (HM1) - hanya school sendiri | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 4.07 Hanya AY2 yang aktif | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 4.09 Hanya AY1 yang aktif | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 4.13 Teacher VIEW: list AY (T1) -> 200 | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 5.08 List Rombel (HM1) - hanya school sendiri | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 6.11 List assignment (HM1) - hanya school sendiri | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 7.07 History retained: Teacher 2 tetap muncul di list dengan status inactive | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 10.03 Guru hasil import masuk school Headmaster, gender ternormalisasi | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 10.04 Isolasi school: HM2 tidak melihat guru hasil import HM1 | UBAH URL | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat |
| 1.06 List School (SA) | UBAH URL+SCRIPT | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat (data SA akumulasi lintas run -> halaman terakhir) |
| 2.09 List Headmaster (SA) | UBAH URL+SCRIPT | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat (data SA akumulasi lintas run -> halaman terakhir) |
| 2.17 Hanya 1 Headmaster active per school (uq_headmasters_one_active_per_school) | UBAH URL+SCRIPT | F-15/F-10: list kini {data, meta} dan default 20 item/halaman; ?per_page=100 supaya semua data run ini terlihat (data SA akumulasi lintas run -> halaman terakhir) |
| 1.05b [F-09] Create School nama 151 karakter -> 422 (kolom varchar(150)) | BARU | F-09 (ccfce6d): max:150 |
| 4.11b [F-04] PATCH AY2 hanya start_date melewati end_date tersimpan -> 422 | BARU | F-04 (ccfce6d): validasi tanggal efektif |
| 4.11c [F-04] PATCH AY2 hanya end_date sebelum start_date tersimpan -> 422 | BARU | F-04 (ccfce6d): validasi tanggal efektif |
| 5.05b [F-20] Create Rombel grade_level 2147483648 (> batas integer 32-bit) -> 422 | BARU | F-20 (ccfce6d) |
| 5.13 [F-21] Teacher tanpa active assignment: list Rombel (T1) -> 200 kosong | UBAH | F-21: Teacher tanpa active assignment tidak menerima Rombel apa pun (sebelum assignment dibuat di folder 6) |
| 5.14 [F-21] Teacher tanpa active assignment: detail Rombel (T1) -> 404 | UBAH | F-21: detail Rombel di luar scope Teacher = 404 (sebelumnya 200) |
| 6.08b [FIXTURE F-18] Create Rombel 4 VII-D (untuk Teacher 2) | BARU | F-18 (ccfce6d): fixture rombel tambahan |
| 6.08c [FIXTURE F-18] Create Rombel 5 VII-E (tujuan pindah assignment 3) | BARU | F-18 (ccfce6d): fixture rombel tambahan |
| 6.09 Assign Teacher 2 -> Rombel 4 (untuk tes lifecycle) | UBAH BODY | F-18: Rombel 1 sudah dipegang Teacher 1 (409 rombel_already_has_active_teacher), T2 pindah ke Rombel 4 |
| 6.15 PATCH assignment 3: pindah ke Rombel 5 | UBAH BODY+ASSERT | F-18: pindah ke Rombel 1 yang berisi Teacher 1 kini 409; tujuan diganti Rombel 5 yang kosong |
| 6.01b [F-19] PATCH Rombel 1 grade_level setelah punya assignment -> 409 rombel_field_locked | BARU | F-19 (ccfce6d) |
| 6.01c [F-19] PATCH Rombel 1 name + nilai grade_level yang SAMA -> 200 (bukan perubahan) | BARU | F-19 (ccfce6d) |
| 6.12b [F-21] Teacher 1 list Rombel -> hanya Rombel 1, meta.total 1 | BARU | F-21 (ccfce6d) |
| 6.12c [F-21] Teacher 1 detail Rombel 2 (di luar scope) -> 404 | BARU | F-21 (ccfce6d) |
| 6.21b [F-17] PATCH assignment 3 yang sudah inactive (histori) -> 409 assignment_inactive_readonly | BARU | F-17/F-18 (ccfce6d) |
| 6.21c [F-18] Assign Teacher 3 (bebas) -> Rombel 1 yang sudah punya Teacher aktif -> 409 rombel_already_has_active_teacher | BARU | F-17/F-18 (ccfce6d) |
| 9.07 REPLACEMENT (1/2): Teacher 1 upload foto Student S1 (PNG) | UBAH | F-06: field photo kini URL endpoint terautentikasi, bukan URL publik students/{id}.png |
| 9.08 REPLACEMENT (2/2): upload foto JPG menggantikan PNG | UBAH | F-06: sama dengan 9.07; ekstensi file tidak lagi muncul di URL |
| 10.07 [F-34] Import struktur salah (header Foo/Bar/Baz) -> 422 import_header_invalid | UBAH | F-34: header kolom wajib hilang -> 422 import_header_invalid (sebelumnya 200 dengan error required per baris) |
| 10.08 Import file tanpa data (hanya header) | UBAH | Dipertegas: file tanpa baris data tidak punya header yang bisa diperiksa dan diproses sebagai total 0 (F-34, batas cakupan yang dicatat) |
| 10.09 Import file bukan xlsx (bytes acak, nama .xlsx) -> 422 validation_failed | UBAH | Penjelasan assertion diperjelas: file acak ditolak rule mimes:xlsx; file xlsx rusak sungguhan ada di 10.09b |
| 10.09b [F-34] Import xlsx RUSAK (zip valid, sheet korup) -> 422 import_file_unparseable | BARU | F-34 (9e1a31c) |
| 10.10b [F-33] Import format .xls (Excel lama) -> 422 | BARU | F-33 (9e1a31c) |
| 10.13b [F-35] Import NIP kembar dalam satu file -> baris ke-2 gagal duplicate_in_file | BARU | F-35 (9e1a31c) |
| 10.13c [F-36] NIP bertepi spasi tersimpan ter-trim; NIP kembar hanya tersimpan sekali | BARU | F-36 (9e1a31c) |
| 10.14 [F-38] Import 101 baris -> diproses SINKRON, respons langsung lengkap | UBAH | F-38: tidak ada lagi 202/pending/queue; respons lengkap langsung (sebelumnya: status pending + batch_id) |
| 10.15 [F-38] Baca HASIL batch 101 baris lewat batch_id (riwayat, bukan polling) | UBAH | F-38: polling loop + pre-request tunggu 2 detik + butuh queue:work DIHAPUS; kini satu GET atas hasil batch selesai |
| 10.16 Guru dari import 101 baris ada di list | UBAH | F-38 + F-15: bukan lagi "asinkron"; per_page=100 dan cek meta.total |
| 10.18 [F-32] HM2 membaca status batch milik HM1 -> 404 | UBAH | F-32 (5696c4b): keputusan final 404; sebelumnya request eksploratif yang hanya mencatat 200/403/404 |
| 13.01 [F-53] Teacher membuat Student dengan NISN = NIP Headmaster -> 422 nisn_conflicts_with_staff_username | BARU | F-53 (2eec896) |
| 13.02 [F-53 kontrol] NISN Student yang SUDAH ada (re-enroll ke Rombel yang sama) -> 409 duplicate_assignment, BUKAN 422 staf | BARU | F-53 negatif (2eec896) |
| 13.03 [F-06] Detail Student S1 -> field photo = URL endpoint .../students/{id}/photo | BARU | F-06 (d486910) |
| 13.04 [F-06] GET foto Student S1 TANPA token -> 401 | BARU | F-06 (d486910) |
| 13.05 [F-06] GET foto Student S1 dengan token Teacher pemilik -> 200 image/* | BARU | F-06 (d486910) |
| 13.06 [F-06] GET foto Student S1 oleh Headmaster sekolah lain -> 404 | BARU | F-06 (d486910) |
| 13.07 [F-15] GET /teachers?per_page=2 -> data maks 2, meta konsisten | BARU | F-15 (941b0a1) |
| 13.08 [F-15] GET /teachers?per_page=101 -> 422 validation_failed | BARU | F-15 (941b0a1) |
| 13.09 [F-15] GET /teachers?page=99999 (di luar rentang) -> 200, data kosong, meta valid | BARU | F-15 (941b0a1) |
| 13.10 [F-15] Keenam endpoint list mengembalikan {data, meta} | BARU | F-15/F-10 (941b0a1) |
| 14.01 [UKUR F-38] Import tepat 200 baris (batas atas MAX_ROWS) -> sinkron, catat waktu | BARU | F-38: pengukuran waktu (opsional) |
| 4.10b [RECOVERY - bukan test coverage] Pulihkan AY1 setelah bug idempotent 4.10 | HAPUS | F-02 (4987936): request pemulihan state karena bug idempotent 4.10; bug sudah diperbaiki sehingga request ini tidak lagi diperlukan |

Catatan: request lain (folder 0-3, 7, 8, 11, 12) tidak berubah; `[LOGIN]` rate limit (F-23) tidak ditambah test dua dimensi
(opsional, ditunda karena berbagi login Super Admin dengan seluruh run).

### Revisi setelah run manual pertama terhadap backend final (699 test, 600 lulus, 99 gagal)

Kegagalan itu bukan bug backend; keduanya cacat pada collection dan sudah dikoreksi:

1. **NISN S1 bentrok dengan NIP Teacher 3 (data uji).** `nip_*` dibuat `'9' + (1..8) + run_id` dan `nisn_s1 = '97' + run_id`,
   padahal `nip_t3` = `'97' + run_id`. Request 6.25 (Teacher 1 membuat Student S1) ditolak 422
   `nisn_conflicts_with_staff_username` oleh F-53 (benar secara perilaku), sehingga `s1_id`/`student_token` kosong dan semua yang
   bergantung pada S1 ikut gagal (6.26, 8.13, 8.14, 8.22, 9.07, 9.08, 11.01, 13.02-13.06, dan 33 kasus Student di sweep folder 12).
   Perbaikan: `nisn_s1 = '99' + run_id` (prefix 99 tidak dipakai NIP).
2. **Token Super Admin dicabut oleh F-29 setelah 8.17.** Ganti password MENCABUT token lama, tetapi collection memakai `sa_token`
   yang sama untuk 8.18 dan seluruh folder 9-13. 8.18 gagal 401, password SA TERTINGGAL `SaSementara_2026`, dan semua request SA
   sesudahnya gagal. Perbaikan: 8.17c (token lama harus 401), 8.17b dan 8.18b (login ulang SA).

Dua penyebab itu menjelaskan 95 dari 99 assertion gagal; sisa kecil (dugaan: 11.01 `student_count`) perlu diperiksa pada run ulang.

3. **10.06 masih mengharapkan perilaku lama (run kedua: 706 test, 705 lulus, 1 gagal).** Baris 7 file `teacher_partial.xlsx` berisi NIP kembar dengan baris 2.
   Sebelum F-35 backend memprosesnya dan gagal saat insert (`unexpected_error`); sekarang kembaran antar-baris dideteksi sebelum proses dan
   dilaporkan `field: nip`, `code: duplicate_in_file`, pesan menyebut "baris 2". Backend benar, assertion usang: sudah dikoreksi
   (nama request kini memuat `[F-35]`). Jumlah test tetap 706; hasil yang diharapkan pada run berikutnya **706 lulus, 0 gagal**.

4. **Batas baris import turun dari 1.000 ke 200 (keputusan pemilik setelah pengukuran waktu F-38).** Dampak pada collection: 10.13 (pesan 422 kini menyebut 200,
   bukan 1.000), folder 14 (14.01 sekarang tepat 200 baris, 14.02 baru: 201 baris -> 422), generator (`teacher_200_rows.xlsx`, `teacher_201_rows.xlsx`
   menggantikan `teacher_1000_rows.xlsx`). Variabel environment `_import_1000_ms` diganti `_import_200_ms`. Jumlah test yang diharapkan pada run berikutnya: **711**
   (709 + 2 assertion dari 14.02 baru); bila angka run berbeda, catat angka sebenarnya.

