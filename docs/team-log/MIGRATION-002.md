# MIGRATION-002 — Log

**Status:** Selesai (implementasi + tes) — menunggu review 1 orang lain di tim
**PIC:** Orang 1
**Progress:** 100%
**Tanggal:** 22 Sep 2026

## File diubah/dihapus/rename

- Rename+edit: `create_headmaster_table.php` → `create_headmasters_table.php`
- Rename+edit: `create_qr_user_table.php` → `create_qr_tokens_table.php`
- Rename+edit: `Models/QrUser.php` → `Models/QrToken.php`
- Edit: migration `users`, `schools`, `teachers`, `students`
- Edit: Model `School`, `Student`, `Teacher`, `Headmaster`, `User`, `StudentHabit`
- Edit: `UserFactory.php`, `DatabaseSeeder.php`
- Hapus: migration `password_reset_tokens`, `student_habit_backups`, `add_grade_level_to_students_table`
- Hapus: `Models/PasswordResetToken.php`, `Models/StudentHabitBackup.php`

## Hasil test

- `migrate:fresh` sukses, tanpa error FK
- Relasi Tinker dua arah lolos: `School→headmaster` (hasOne, scoped active), `School→headmasters` (hasMany), `Student→qrToken`

## Gap/catatan terbuka

- `import_batches` (tabel legacy) belum ditetapkan nasibnya di SoT — di luar scope MIGRATION-002
- Perlu review 1 orang lain di tim sebelum status final DONE
