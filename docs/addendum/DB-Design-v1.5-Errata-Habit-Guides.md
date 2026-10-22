# DB Design v1.5 — Errata: tabel `habit_guides` (INTEG-PANDUAN-001)

**Tanggal:** 21 Okt 2026 (pembaruan 22 Okt 2026: D-19) · **Dasar:** Dokumen Keputusan v4.0 (amandemen 20 Okt 2026, butir a; D-06, D-13; D-19 dari pemilik, 22 Okt 2026) · Task Board Tambahan Integrasi FE v1.5.
**Jenis:** tabel BARU. DB Design v1.5 belum mencakup panduan kebiasaan; tidak ada tabel atau kolom lama yang berubah, tidak ada migration lama yang diedit.

## Migration

1. `database/migrations/2026_10_20_100000_create_habit_guides_table.php` membuat tabel (FK `rombel_id` awalnya RESTRICT).
2. `database/migrations/2026_10_22_100000_habit_guides_rombel_fk_cascade.php` (D-19, 22 Okt 2026) mengganti FK `habit_guides_rombel_id_foreign` menjadi ON DELETE CASCADE; `down()` mengembalikannya ke RESTRICT. Migration pertama tidak diedit karena sudah ter-apply di database tim.

**Jalankan `php artisan migrate`** di setiap environment (dev, staging, test). Database yang sudah menjalankan migration pertama wajib menjalankannya agar FK berubah ke CASCADE.

## Tabel `habit_guides`

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint, PK | auto increment |
| `rombel_id` | bigint, NOT NULL | FK `rombels.id` (`habit_guides_rombel_id_foreign`), **ON DELETE CASCADE** sejak D-19 (migration awal RESTRICT) |
| `habit_id` | smallint unsigned, NOT NULL | FK `habits.id` (ON DELETE RESTRICT); `habits.id` bertipe smallint |
| `content` | text, NOT NULL | teks polos |
| `created_at`, `updated_at` | timestamp | diisi Eloquent |

Constraint:
- `uq_habit_guides_rombel_habit` — UNIQUE (`rombel_id`, `habit_id`): satu panduan per rombel x kebiasaan, sekaligus melayani query "semua panduan satu rombel" (`rombel_id` kolom pertama). Tidak ada index tambahan.
- `chk_habit_guides_content` — CHECK `char_length(content) BETWEEN 1 AND 2000`: panjang maksimal 2.000 karakter, dan tidak ada baris kosong (kosong = baris dihapus, D-13).

Tanpa `academic_year_id`: rombel sudah terikat satu tahun ajaran. Tanpa soft delete: hapus panduan = hapus baris.

## Dampak pada tabel lain

- `DELETE /rombels/{id}` pada rombel tanpa assignment guru atau siswa menghapus rombel beserta panduannya (CASCADE, D-19); panduan rombel lain tidak terpengaruh.
- Rombel yang masih direferensikan assignment guru atau siswa (aktif maupun riwayat) tetap ditolak FK RESTRICT tabel assignment: 409 `rombel_has_active_assignment`, dalam satu statement, sehingga panduannya tidak ikut terhapus.
- `habit_guides.habit_id` tetap RESTRICT.
- Catatan dampak: panduan hanya bisa ditulis lewat API oleh Teacher dengan assignment aktif di rombel itu, dan assignment tidak bisa dihapus lewat API, sehingga rombel yang pernah diberi panduan lewat API selalu punya riwayat assignment guru dan tetap ditolak 409. Efek CASCADE praktis terjadi pada panduan di rombel yang tidak punya assignment sama sekali.

## Catatan

`docs/db-gap-analysis.md` tidak diubah; penggabungan ke dokumen DB Design berikutnya dilakukan pada INTEG-DOC-001.
