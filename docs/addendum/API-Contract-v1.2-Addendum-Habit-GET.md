# API Contract v1.2 — Addendum: Habit GET Response & Date Filter

Status: DISETUJUI (Keputusan Tim, 25 Sep 2026). Melengkapi API Contract v1.2 §21-§22 untuk
endpoint GET Habit/Bermasyarakat. Tidak mengubah nomor versi dokumen utama — dicatat sebagai
addendum terpisah karena proyek belum punya konvensi versioning addendum resmi.

## 1. Response — tambah `student_id`

Ditambahkan ke response:
- `GET /api/v1/habits/entries`
- `GET /api/v1/habits/entries/{id}`
- `GET /api/v1/bermasyarakat/entries`

Alasan: Teacher melihat entry dari banyak Student dalam own active rombel; tanpa `student_id`,
entry tidak dapat diidentifikasi milik Student mana. Kebutuhan fungsional monitoring, bukan kosmetik.

Field yang ditambahkan **hanya** `student_id`. **Tidak** ditambahkan: `student_name`, `nisn`,
nested Student object, data rombel, atau field identitas Student lainnya.

Field ini **tidak muncul** pada response POST (`POST /habits/{habit_id}/entries`,
`POST /bermasyarakat/entries`) — Student yang submit sudah pasti dirinya sendiri.

## 2. Filter `date` (opsional)

Query parameter opsional pada:
- `GET /api/v1/habits/entries`
- `GET /api/v1/bermasyarakat/entries`

```
GET /api/v1/habits/entries?date=2026-09-25
```

Format wajib `YYYY-MM-DD`; format lain → 422 (`validation_failed`). Filter berdasarkan kolom
`habit_date`. Jika tidak dikirim, behavior existing (tanpa filter) tetap sama.

**Filter ini tidak pernah mengubah atau menentukan authorization scope.** Scope (Teacher own
active rombel / Student own data) selalu dihitung terlebih dahulu dari role + active assignment;
`date` hanya mempersempit hasil di dalam scope yang sudah ditentukan itu.

Catatan: filter ini mendukung monitoring harian, **bukan** fitur "siapa yang belum mengisi" —
fitur itu butuh perbandingan dengan daftar Student dan merupakan fitur terpisah (belum ada di
scope manapun saat ini).

## 3. Query filter yang TIDAK diimplementasikan

- `?student_id=...` — **tidak ada**. `student_id` hanya field identity di response, bukan filter.
  Belum ada kebutuhan eksplisit di SoT yang mengharuskannya.
- `?rombel_id=...` — **tidak ada**. Teacher scope sudah ditentukan sepenuhnya oleh active Teacher
  assignment; parameter dari client tidak boleh menjadi penentu authorization scope.

## 4. Habit entry tetap IMMUTABLE

Ditegaskan ulang (lihat juga `docs/db-gap-analysis.md` NOTE-08): tidak ada PATCH, tidak ada
DELETE, tidak ada mekanisme Daily Lock berbasis waktu, tidak ada cutoff time. Duplicate submission
(Student + habit + tanggal sama) tetap ditolak 409 `duplicate_habit_entry`. Referensi
`habit_already_locked` / "daily lock" pada teks API Contract v1.2 §21 versi lama adalah
requirement yang sudah direkonsiliasi (dicabut), bukan celah yang perlu ditutup dengan endpoint
PATCH baru.

## 5. Tidak ada perubahan skema database

`student_id` sudah menjadi bagian data `student_habits` sejak schema awal (DB Design v1.5 §5.12).
Addendum ini murni perubahan response/filtering di layer API, tidak ada migration baru.
