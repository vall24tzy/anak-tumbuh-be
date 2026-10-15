#!/usr/bin/env bash
# OPS-001 — restore backup (format custom) ke database TARGET yang KOSONG, lalu verifikasi jumlah baris tiap tabel
# terhadap manifes backup.
#
# Pemakaian:  scripts/restore.sh --confirm --file /path/DB_UTC.dump --target-db NAMA_DB_BARU [--create-db]
#
# Skrip ini DESTRUKTIF terhadap database target, karena itu:
#   * menolak berjalan tanpa --confirm;
#   * menolak nama DB produksi (OPS_PROTECTED_DBS, pola prod|production|live, atau DB aplikasi saat APP_ENV=production);
#   * menolak target yang sudah berisi tabel (target HARUS kosong);
#   * menolak bila versi klien pg_restore lebih lama dari pg_dump yang membuat backup.
# Untuk memulihkan produksi: restore ke nama BARU, lalu tukar nama lewat psql (lihat docs/deployment-runbook.md).
# Exit code: 0 sukses terverifikasi | 1 gagal/ditolak | 3 restore selesai tetapi verifikasi tidak cocok
set -euo pipefail
umask 077
source "$(dirname "${BASH_SOURCE[0]}")/lib/ops-common.sh"

CONFIRM=0; FILE=""; TARGET=""; CREATE=0
while [ $# -gt 0 ]; do
  case "$1" in
    --confirm) CONFIRM=1; shift ;;
    --file) FILE="${2:?--file butuh nilai}"; shift 2 ;;
    --target-db) TARGET="${2:?--target-db butuh nilai}"; shift 2 ;;
    --create-db) CREATE=1; shift ;;
    -h|--help) sed -n '2,15p' "$0"; exit 0 ;;
    *) ops_die "argumen tidak dikenal: $1" ;;
  esac
done

ops_load_env
ops_need_cmd psql; ops_need_cmd pg_restore; ops_need_cmd sha256sum
[ "$CONFIRM" -eq 1 ] || ops_die "restore bersifat destruktif: tambahkan --confirm setelah memeriksa target"
[ -n "$FILE" ] && [ -f "$FILE" ] || ops_die "--file wajib dan harus ada"
[ -n "$TARGET" ] || ops_die "--target-db wajib"
[[ "$TARGET" =~ ^[A-Za-z0-9_]+$ ]] || ops_die "nama database hanya boleh huruf, angka, underscore"
if ops_is_protected_db "$TARGET"; then
  ops_die "DITOLAK: '$TARGET' adalah nama database produksi/terlindungi. Restore ke nama baru lalu tukar nama (runbook)."
fi

BASE="$(basename "$FILE" .dump)"; DIR="$(cd "$(dirname "$FILE")" && pwd)"
COUNTS="$DIR/$BASE.counts.tsv"; META="$DIR/$BASE.meta"

# 1. Integritas berkas
if [ -f "$DIR/$BASE.dump.sha256" ]; then
  ( cd "$DIR" && sha256sum -c "$BASE.dump.sha256" ) >/dev/null || ops_die "checksum tidak cocok: berkas backup rusak"
  ops_log "checksum OK"
else
  ops_log "PERINGATAN: $BASE.dump.sha256 tidak ada, checksum dilewati"
fi
pg_restore --list "$FILE" > /tmp/ops_restore_toc.$$ || ops_die "pg_restore --list gagal: berkas bukan backup yang valid"
trap 'rm -f /tmp/ops_restore_toc.$$' EXIT

# 2. Aturan versi: klien pg_restore >= pg_dump pembuat backup (klien mengikuti server produksi)
DUMP_VER="$(sed -nE 's/^;[[:space:]]+Dumped by pg_dump version: ([0-9]+).*/\1/p' /tmp/ops_restore_toc.$$ | head -1)"
CLIENT_MAJOR="$(ops_pg_major pg_restore)"
if [ -n "$DUMP_VER" ] && [ "$CLIENT_MAJOR" -lt "$DUMP_VER" ]; then
  ops_die "pg_restore v$CLIENT_MAJOR lebih lama dari pg_dump v$DUMP_VER: pasang klien yang sama/lebih baru (ikuti versi server produksi)"
fi
ops_log "versi: pg_dump pembuat=v${DUMP_VER:-?} pg_restore klien=v$CLIENT_MAJOR"

# 3. Target harus ada dan KOSONG
EXISTS="$(psql -qAtX -d postgres -c "SELECT 1 FROM pg_database WHERE datname = '$TARGET'")"
if [ -z "$EXISTS" ]; then
  [ "$CREATE" -eq 1 ] || ops_die "database '$TARGET' tidak ada (buat dulu, atau pakai --create-db)"
  psql -qAtX -d postgres -v ON_ERROR_STOP=1 -c "CREATE DATABASE \"$TARGET\"" >/dev/null
  ops_log "database '$TARGET' dibuat"
fi
NTABLES="$(psql -qAtX -d "$TARGET" -c "SELECT count(*) FROM pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema')")"
[ "$NTABLES" = "0" ] || ops_die "DITOLAK: database '$TARGET' sudah berisi $NTABLES tabel; target harus KOSONG"

# 4. Restore: satu transaksi, berhenti pada error pertama (semua-atau-tidak-sama-sekali)
ops_log "restore mulai: $FILE -> $TARGET"
pg_restore --single-transaction --exit-on-error --no-owner --no-privileges --dbname="$TARGET" "$FILE" \
  || ops_die "pg_restore gagal; database '$TARGET' tidak berubah (transaksi dibatalkan)"
ops_log "pg_restore selesai"

# 5. Verifikasi: jumlah baris SEMUA tabel public vs manifes (snapshot yang sama dengan dump)
if [ ! -f "$COUNTS" ]; then
  ops_log "PERINGATAN: manifes $BASE.counts.tsv tidak ada; verifikasi jumlah baris dilewati"; exit 0
fi
SQL="$(psql -qAtX -d "$TARGET" -c "SELECT string_agg(format('SELECT %L AS t, count(*) AS n FROM %I.%I', tablename, schemaname, tablename), ' UNION ALL ' ORDER BY tablename) FROM pg_tables WHERE schemaname='public'")"
psql -qAtX -d "$TARGET" -c "SELECT t || E'\t' || n FROM ($SQL) q ORDER BY t" > /tmp/ops_restore_counts.$$
if diff -u "$COUNTS" /tmp/ops_restore_counts.$$ > /tmp/ops_restore_diff.$$; then
  ops_log "verifikasi jumlah baris OK: $(wc -l < "$COUNTS" | tr -d ' ') tabel identik dengan manifes"
  VERIFIED=1
else
  ops_log "ERROR: jumlah baris TIDAK cocok dengan manifes:" >&2; cat /tmp/ops_restore_diff.$$ >&2; VERIFIED=0
fi
printf '%-30s %s\n' "TABEL (hanya yang berisi)" "BARIS"; awk -F'\t' '$2>0 {printf "%-30s %s\n",$1,$2}' /tmp/ops_restore_counts.$$
LAST_MIG="$(psql -qAtX -d "$TARGET" -c 'SELECT migration FROM migrations ORDER BY id DESC LIMIT 1' 2>/dev/null || true)"
EXP_MIG="$(sed -nE 's/^last_migration=(.*)/\1/p' "$META" 2>/dev/null || true)"
ops_log "migrasi terakhir di hasil restore: ${LAST_MIG:-?} (backup mencatat: ${EXP_MIG:-?})"
[ -z "$EXP_MIG" ] || [ "$LAST_MIG" = "$EXP_MIG" ] || { ops_log "ERROR: migrasi terakhir berbeda dari meta backup" >&2; VERIFIED=0; }
rm -f /tmp/ops_restore_counts.$$ /tmp/ops_restore_diff.$$
[ "${VERIFIED:-0}" -eq 1 ] || exit 3
cat <<MSG
RESTORE TERVERIFIKASI. Langkah susulan yang WAJIB (lihat docs/deployment-runbook.md bagian "Dampak setelah restore"):
  1. php artisan cache:clear        (cache, session, dan rate limiter memakai tabel database)
  2. Data setelah waktu backup HILANG; token user yang password-nya berubah setelah backup akan ditolak (klaim pwd, F-29).
  3. Jalankan scripts/smoke-test.sh terhadap aplikasi yang memakai database ini.
MSG
