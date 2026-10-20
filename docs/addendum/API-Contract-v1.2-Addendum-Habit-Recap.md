# API Contract v1.2 — Addendum: Rekap kebiasaan kelas `GET /habits/recap` (INTEG-REKAP-001)

**Tanggal:** 20 Okt 2026 · **Dasar keputusan:** Dokumen Keputusan v4.0 (amandemen 20 Okt 2026, butir b; D-07, D-10, D-11) · Authorization Matrix v5.3 baris **#62** · merujuk #33 (monitor habit).
**Status:** kode, test PHPUnit, dan request Postman sudah dijalankan (lihat `docs/team-log/INTEG-REKAP-001.md`).

## 1. Endpoint

`GET /api/v1/habits/recap?rombel_id={id}&from=YYYY-MM-DD&to=YYYY-MM-DD`

Endpoint baru, **hanya baca** (tidak menulis `student_habits` maupun `students.poin`). Path ini ditetapkan di addendum ini (di Task Board masih "usulan"; tidak ada di SoT sebelumnya).

| Parameter | Wajib | Aturan |
|---|---|---|
| `rombel_id` | ya | integer >= 1. Tidak ada → 422 `validation_failed`. |
| `to` | tidak | `Y-m-d`. Default: hari ini (Asia/Jakarta). |
| `from` | tidak | `Y-m-d`. Default: `to` dikurangi 6 hari (default rentang = 7 hari terakhir termasuk hari ini). |

Rentang: `from` harus ≤ `to`, dan maksimal **31 hari** inklusif (D-07). Pelanggaran → 422 `validation_failed`. Jika hanya salah satu dari `from`/`to` diberikan, yang lain memakai default di atas.

## 2. Akses dan scope

| Pemanggil | Hasil |
|---|---|
| Tanpa token | 401 |
| Super Admin, Headmaster, Student | 403 (Headmaster tidak monitor habit, Dokumen v4.0 §7) |
| Teacher, `rombel_id` = own active rombel | 200 |
| Teacher, rombel lain di **sekolah yang sama** | 403 (pola scope Teacher) |
| Teacher, rombel milik **sekolah lain** atau **tidak ada** | 404 `not_found` — respons identik untuk keduanya (D-11, anti-enumerasi) |
| Teacher inactive | 403 |
| Teacher aktif tanpa assignment aktif | 403 untuk rombel sekolahnya, 404 untuk rombel lain/tidak ada |

Scope Teacher hanya dari `TeacherScopeService::activeAssignment()`. `rombel_id` sengaja tidak divalidasi dengan `exists:rombels` (akan membedakan "tidak ada" dari "sekolah lain"); keberadaan dan scope dicek di service. Urutan evaluasi: 401 → 403 role → 422 (parameter/rentang) → 404/403 rombel.

## 3. Respons 200

```json
{
  "data": {
    "rombel": { "id": 1, "name": "VII-A" },
    "from": "2026-10-12",
    "to": "2026-10-14",
    "student_count": 3,
    "per_day": [
      { "date": "2026-10-12", "filled": 3, "percentage": 14.29 },
      { "date": "2026-10-13", "filled": 2, "percentage": 9.52 },
      { "date": "2026-10-14", "filled": 0, "percentage": 0 }
    ],
    "per_student": [
      { "student_id": 11, "fullname": "Ani", "total_poin": 120 },
      { "student_id": 12, "fullname": "Budi", "total_poin": 10 }
    ],
    "per_habit": [
      { "habit_id": 1, "code": "bangun_cepat", "name": "Bangun Cepat", "filled": 2 }
    ]
  }
}
```

(Contoh dipersingkat; `per_student` memuat semua siswa roster, `per_habit` selalu memuat ketujuh kebiasaan.)

- `per_day`: satu elemen untuk setiap tanggal dalam rentang (termasuk hari tanpa entri), urut tanggal naik. `percentage` = `filled / (student_count x 7) x 100`, angka 0–100 dibulatkan 2 desimal; `0` bila `student_count` = 0.
- `per_student`: semua siswa roster (termasuk yang belum mengisi, `total_poin` 0), urut `fullname` lalu `id`.
- `per_habit`: ketujuh kebiasaan (urut `sort_order`), `filled` = jumlah entri dalam rentang dari semua siswa roster. **Bermasyarakat dihitung satu kebiasaan** (satu entri Bermasyarakat per siswa per hari = satu terisi dari 7).
- Rincian per indikator Bermasyarakat tidak ada di v1 (ditunda, D-07).

## 4. Definisi dan keterbatasan

- **Roster / penyebut** = siswa `status = active` yang punya assignment `active` di rombel itu **saat ini**. Siswa inactive/graduated, siswa yang assignment-nya sudah berakhir, dan siswa rombel/sekolah lain tidak masuk penyebut maupun entri.
- **Keterbatasan (D-07):** rekap untuk tanggal lampau memakai roster *sekarang*, bukan roster pada tanggal itu. Siswa yang sudah pindah/lulus tidak muncul walau pernah mengisi pada tanggal tersebut.
- **Total poin** memakai formula yang sama dengan POST entry dan GET entries (`StudentHabitService::habitPoin()` dan `bermasyarakatPoin()`, dijadikan `public` agar dipakai ulang; tidak ada formula kedua). `poin_awarded` tidak dipersist; `students.poin` tidak dibaca maupun diubah. Tanggal tidak dibatasi ke masa lalu (tanggal masa depan menghasilkan 0).

## 5. Performa (D-10)

Acuan terburuk: 200 siswa x 31 hari x 7 kebiasaan = maks 43.400 entri. Agregasi di SQL (`GROUP BY`), jumlah query tetap (tidak bergantung jumlah siswa/entri; diuji), tanpa cache, tanpa index baru (`INDEX(student_id, habit_date)` dan `INDEX(habit_id, habit_date)` sudah ada). Angka ini asumsi batas atas dan belum diukur di server.

## 6. Kode dan test

`HabitRecapController`, `HabitRecapRequest`, `HabitRecapService` (baru); `routes/v1/habit.php` (route `habits/recap`, middleware `jwt` + `role:teacher`); `StudentHabitService` (hanya visibilitas `habitPoin` dan `bermasyarakatPoin` menjadi `public`).
`tests/Feature/Student/HabitRecapTest.php` (15 test): persentase/poin/per kebiasaan, kesamaan total poin dengan `poin_awarded` dari GET entries, roster (inactive, graduated, pindah, rombel lain, sekolah lain), default Asia/Jakarta, rentang 31/32 hari, validasi, 401/403, 404 identik, 403 lintas rombel, Teacher inactive/tanpa assignment, read-only, jumlah query tetap.
Postman: folder `INTEG-O1` (10 request REKAP setelah 7 request QR).

## 7. Untuk penggabungan ke API Contract v1.3 (INTEG-DOC-001)

Tambahkan endpoint ini pada kelompok Habit (§21) beserta respons §3 dan tabel error §2.
