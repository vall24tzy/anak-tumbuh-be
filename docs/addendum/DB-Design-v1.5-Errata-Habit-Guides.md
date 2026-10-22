# DB Design v1.5 — Errata: tabel `habit_guides` (INTEG-PANDUAN-001)

**Tanggal:** 21 Okt 2026 · **Dasar:** Dokumen Keputusan v4.0 (amandemen 20 Okt 2026, butir a; D-06, D-13) · Task Board Tambahan Integrasi FE v1.5.
**Jenis:** tabel BARU. DB Design v1.5 belum mencakup panduan kebiasaan; tidak ada tabel atau kolom lama yang berubah, tidak ada migration lama yang diedit.

## Migration

`database/migrations/2026_10_20_100000_create_habit_guides_table.php` — **jalankan `php artisan migrate`** di setiap environment (dev, staging, test) setelah patch diterapkan.

## Tabel `habit_guides`

| Kolom | Tipe | Aturan |
|---|---|---|
| `id` | bigint, PK | auto increment |
| `rombel_id` | bigint, NOT NULL | FK `rombels.id`, **ON DELETE RESTRICT** |
| `habit_id` | smallint unsigned, NOT NULL | FK `habits.id` (ON DELETE RESTRICT); `habits.id` bertipe smallint |
| `content` | text, NOT NULL | teks polos |
| `created_at`, `updated_at` | timestamp | diisi Eloquent |

Constraint:
- `uq_habit_guides_rombel_habit` — UNIQUE (`rombel_id`, `habit_id`): satu panduan per rombel x kebiasaan, sekaligus melayani query "semua panduan satu rombel" (`rombel_id` kolom pertama). Tidak ada index tambahan.
- `chk_habit_guides_content` — CHECK `char_length(content) BETWEEN 1 AND 2000`: panjang maksimal 2.000 karakter, dan tidak ada baris kosong (kosong = baris dihapus, D-13).

Tanpa `academic_year_id`: rombel sudah terikat satu tahun ajaran. Tanpa soft delete: hapus panduan = hapus baris.

## Dampak pada tabel lain

`DELETE /rombels/{id}` untuk rombel yang masih punya baris `habit_guides` ditolak FK RESTRICT; kode error yang dikembalikan adalah 409 `rombel_has_active_assignment` (pemetaan generik `RombelService`, pesannya kurang tepat untuk kasus ini). Untuk menghapus rombel, hapus panduannya dulu (PATCH `content` kosong).

## Catatan

`docs/db-gap-analysis.md` tidak diubah; penggabungan ke dokumen DB Design berikutnya dilakukan pada INTEG-DOC-001.
