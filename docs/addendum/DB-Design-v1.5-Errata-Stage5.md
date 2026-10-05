# DB Design v1.5 — Errata Stage 5 (penyelarasan dengan implementasi final Stage 1–4 + F-53)

Tanggal: 5 Okt 2026. Basis: `main` di atas `efbf95b` (415 passed / 2047 assertions).
Sifat dokumen: **errata**; skema dan kode TIDAK diubah oleh Stage 5. `DB_Design_ANAKTUMBUH_v1_5_FINAL.docx`
tidak disimpan di repo, jadi teksnya tidak diedit; tiap bagian memuat **teks v1.5 yang dipertahankan** dan
**blok revisi** dengan rujukan commit/finding. Bila konflik, blok revisi menang untuk bagian yang disebut.
Sumber: Matrix v3.1 (`MASTER_AUDIT_REMEDIATION_MATRIX_v3_1_STAGE0_STATUS.md`) dan keputusan pemilik.

---

## §5.2 schools — UNIQUE pada `name` (F-11; `ccfce6d`)

**Teks v1.5 (dipertahankan):** `name varchar(150) NOT NULL`, kolom Constraint `—`; "Index: tidak perlu index
tambahan (volume kecil, akses selalu by PK)."

**Revisi Stage 5.** `schools.name` kini **UNIQUE** (keputusan final Matrix F-11: unik di aplikasi **dan** di
database; `schools.id` tetap primary identifier). Dibuat oleh migration
`2026_10_04_100100_add_unique_to_schools_name` (constraint `schools_name_unique`, perbandingan
case-sensitive sama dengan `unique:schools,name` di request). Migration memeriksa duplikat lebih dulu dan
berhenti dengan `RuntimeException` berisi daftar nama kembar bila ada; tidak mengubah data otomatis.
Balapan insert nama sama → 422 pada field `name` (bukan 500). Terkait: F-09 — validasi `name` di
`POST /schools` kini `max:150` (sinkron dengan `varchar(150)`). Catatan "tidak perlu index tambahan"
berlaku untuk akses by PK; unique index ini ada demi integritas, bukan akses.

---

## §5.7 rombels — catatan bisnis `grade_level` (F-20; keputusan pemilik)

**Teks v1.5 (dipertahankan):** `grade_level integer NOT NULL CHECK (grade_level > 0)`, "FINAL — dasar
Ranking Angkatan, diisi eksplisit, tidak boleh ditebak dari name".

**Revisi Stage 5 — CATATAN BISNIS, BUKAN perubahan skema dan BUKAN validasi tambahan.**
- `grade_level` numerik **1–6 untuk SD**. **TK memakai struktur Rombel terpisah** (nama/group dinamis seperti
  "TK A", "TK B", "TK A1/A2") dan **tidak ada pemetaan angka untuk TK** (tidak ada "TK A = 1" dst).
- Catatan ini untuk masa depan (mis. bila penanganan TK dikerjakan sebagai task terpisah). Skema tetap
  `CHECK (grade_level > 0)`; API membatasi atas pada 2147483647 (batas integer 32-bit, F-20 `ccfce6d`) dan
  **tidak menegakkan** rentang 1–6.
- Terkait F-19 (`ccfce6d`): `academic_year_id` dan `grade_level` tidak dapat diubah setelah Rombel punya
  assignment (Teacher atau Student, aktif/historis). Ini aturan aplikasi (409 `rombel_field_locked`), tanpa
  perubahan skema.
- Lihat N-3 di `API-Contract-v1.2-Errata-Stage5.md` (contoh SoT lain memakai `VII-A`/`grade_level: 7`).

---

## §5.8 teacher_class_assignments — index unik per Rombel (F-16, F-18; `ccfce6d`)

**Teks v1.5 (dipertahankan):** "Constraint kunci — satu active rombel per Teacher per Academic Year:
`CREATE UNIQUE INDEX uq_tca_one_active_per_teacher_year ON teacher_class_assignments (teacher_id,
academic_year_id) WHERE is_active = true;`" dan "Index: INDEX(rombel_id), INDEX(school_id)."

**Revisi Stage 5.** Index lama **tetap berlaku** dan ditambah satu partial unique index baru (keputusan
final Matrix F-18: dalam satu Rombel pada satu Academic Year maksimal satu Teacher assignment aktif):

```sql
CREATE UNIQUE INDEX uq_tca_one_active_per_rombel_year
ON teacher_class_assignments (rombel_id, academic_year_id)
WHERE is_active = true;
```

Migration: `2026_10_04_100000_add_active_rombel_unique_to_teacher_class_assignments` (memeriksa data existing
dan berhenti dengan pesan jelas bila ada Rombel dengan >1 Teacher aktif per Academic Year; `down()` =
`DROP INDEX IF EXISTS`). Pelanggaran dilaporkan aplikasi sebagai 409 `rombel_already_has_active_teacher`
(index baru) atau 409 `teacher_already_has_active_assignment` (index lama), termasuk pada balapan
(F-16). Aturan aplikasi terkait: assignment `is_active=false` tidak dapat di-PATCH (F-17, 409
`assignment_inactive_readonly`).

---

## §5.16 audit_logs — `target_user_id` nullable (F-31; `9e1a31c`)

**Hasil verifikasi teks v1.5:** §5.16 **sudah** menyebut `target_user_id bigint, Null = Yes, Default NULL,
FK→users.id` ("Siapa yang terdampak"). **Dokumen tidak salah dan TIDAK diubah.** Yang tidak sinkron adalah
migration awal `create_audit_logs_table` (`2026_09_25_135126`), yang membuat kolom `NOT NULL`.

**Arah errata yang benar:** DB Design v1.5 adalah acuan (keputusan final Matrix F-31: "DB Design (nullable)
jadi acuan"); **kode/skema yang disinkronkan**, bukan dokumen. Migration
`2026_10_04_110000_make_audit_logs_target_user_id_nullable` melonggarkan `target_user_id` menjadi nullable
(dilewati bila tabel `audit_logs` tidak ada, karena infrastruktur ini opsional; `down()` berhenti bila sudah
ada baris tanpa target). `AuditLogService::record()` menerima target opsional. Semua pemanggil saat ini
tetap mengisi target, jadi perilaku yang ada tidak berubah. Tidak ada perubahan teks DB Design.

---

## Dampak lanjutan yang dicatat (tanpa mengubah teks DB Design)

- **§6 / §7 (ringkasan constraint dan index lintas tabel):** bila ringkasan itu dipakai sebagai rujukan,
  tambahkan `schools_name_unique` dan `uq_tca_one_active_per_rombel_year` sesuai blok §5.2/§5.8 di atas.
- **§10 (urutan migration):** migration baru Stage 3–4 (`2026_10_04_100000`, `2026_10_04_100100`,
  `2026_10_04_110000`) berada di luar daftar 001–017 di v1.5.
- **Risk/R7 dan Risk #3 (gate dan pencabutan sesi; Matrix §9 menyebut F-14/F-24/F-29):** implementasi
  memakai klaim JWT (sidik jari password) dan gate status profil di middleware/resolver, **tanpa tabel sesi
  baru dan tanpa perubahan skema**; tidak ada errata skema. (E-2 Matrix tidak terpicu.)
- **Job/queue import dihapus (F-38, `cea2bcd`):** tabel `import_batches` tetap dipakai (kini ditulis
  sinkron oleh controller). Tabel `jobs`/`failed_jobs` adalah bawaan framework dan tidak diubah.
