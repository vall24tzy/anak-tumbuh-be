#!/usr/bin/env bash
# OPS-001 — backup database PostgreSQL (pg_dump format custom), konsisten dalam satu snapshot.
#
# Pemakaian:  BACKUP_DIR=/var/backups/anak-tumbuh scripts/backup.sh
# Env:        BACKUP_DIR (WAJIB, di luar repo)        BACKUP_RETENTION_DAYS (default 14; 0 = jangan hapus)
#             BACKUP_OFFSITE_CMD (opsional; lokasi di luar server diputuskan di DEPLOYMENT-001)
#             DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD (atau dari .env)
# Keluaran:   <DB>_<UTC>.dump  .sha256  .counts.tsv  .meta  (semua chmod 600)
# Exit code:  0 sukses | 1 gagal | 2 backup lokal sukses tetapi hook offsite gagal
set -euo pipefail
umask 077
source "$(dirname "${BASH_SOURCE[0]}")/lib/ops-common.sh"

while [ $# -gt 0 ]; do
  case "$1" in
    --dir) BACKUP_DIR="${2:?--dir butuh nilai}"; shift 2 ;;
    -h|--help) sed -n '2,10p' "$0"; exit 0 ;;
    *) ops_die "argumen tidak dikenal: $1" ;;
  esac
done

ops_load_env
ops_need_cmd psql; ops_need_cmd pg_dump; ops_need_cmd pg_restore; ops_need_cmd sha256sum
: "${BACKUP_DIR:?BACKUP_DIR wajib diisi (lokasi di luar repo; jangan di dalam storage/ atau public/)}"
: "${DB_DATABASE:?DB_DATABASE tidak ditemukan (env atau .env)}"
RETENTION="${BACKUP_RETENTION_DAYS:-14}"
[[ "$RETENTION" =~ ^[0-9]+$ ]] || ops_die "BACKUP_RETENTION_DAYS harus bilangan bulat"

mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BASE="${DB_DATABASE}_${STAMP}"
DUMP="$BACKUP_DIR/$BASE.dump"
COUNTS="$BACKUP_DIR/$BASE.counts.tsv"
META="$BACKUP_DIR/$BASE.meta"
LOGF="$BACKUP_DIR/backup.log"
exec > >(tee -a "$LOGF") 2>&1
trap 'rm -f "$DUMP.partial" "$COUNTS.partial"' EXIT

ops_log "backup mulai: db=$DB_DATABASE host=$DB_HOST:$DB_PORT dir=$BACKUP_DIR"
SERVER_VER="$(psql -qAtX -d "$DB_DATABASE" -c 'SHOW server_version')" || ops_die "tidak bisa terhubung ke database"
DUMP_MAJOR="$(ops_pg_major pg_dump)"; SERVER_MAJOR="${SERVER_VER%%.*}"
ops_log "server_version=$SERVER_VER pg_dump_major=$DUMP_MAJOR"
if [ "$DUMP_MAJOR" -lt "$SERVER_MAJOR" ]; then
  ops_die "pg_dump (v$DUMP_MAJOR) lebih lama dari server (v$SERVER_MAJOR): pasang klien PostgreSQL yang mengikuti versi server"
fi

# Satu snapshot untuk dump DAN hitungan baris, supaya manifes cocok dengan isi dump walau aplikasi sedang menulis.
coproc PSQL { psql -qAtX -v ON_ERROR_STOP=1 -d "$DB_DATABASE"; }
send() { printf '%s\n' "$1" >&"${PSQL[1]}"; }
send "BEGIN ISOLATION LEVEL REPEATABLE READ;"
send "SELECT pg_export_snapshot();"
IFS= read -r SNAP <&"${PSQL[0]}" || ops_die "gagal mengambil snapshot"
ops_log "snapshot=$SNAP"

pg_dump --format=custom --no-owner --no-privileges --snapshot="$SNAP" --file="$DUMP.partial" "$DB_DATABASE" \
  || ops_die "pg_dump gagal"

send "SELECT 'x' WHERE false;"   # sinkronisasi kecil
send "SELECT string_agg(format('SELECT %L AS t, count(*) AS n FROM %I.%I', tablename, schemaname, tablename), ' UNION ALL ' ORDER BY tablename) FROM pg_tables WHERE schemaname = 'public';"
IFS= read -r COUNT_SQL <&"${PSQL[0]}" || ops_die "gagal menyusun query hitungan"
send "SELECT t || E'\t' || n FROM ($COUNT_SQL) q ORDER BY t;"
send "SELECT '__END__';"
: > "$COUNTS.partial"
while IFS= read -r line <&"${PSQL[0]}"; do
  [ "$line" = "__END__" ] && break
  printf '%s\n' "$line" >> "$COUNTS.partial"
done
send "COMMIT;"
send '\q'
wait "$PSQL_PID" 2>/dev/null || true

# Verifikasi berkas dump terbaca dan manifes tidak kosong.
pg_restore --list "$DUMP.partial" > /dev/null || ops_die "verifikasi pg_restore --list gagal; backup dianggap RUSAK"
[ -s "$COUNTS.partial" ] || ops_die "manifes hitungan baris kosong"
mv "$DUMP.partial" "$DUMP"; mv "$COUNTS.partial" "$COUNTS"
( cd "$BACKUP_DIR" && sha256sum "$BASE.dump" > "$BASE.dump.sha256" )

LAST_MIG="$(psql -qAtX -d "$DB_DATABASE" -c 'SELECT migration FROM migrations ORDER BY id DESC LIMIT 1' 2>/dev/null || true)"
GIT_SHA="$(git -C "$OPS_REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo tidak-diketahui)"
{
  echo "timestamp_utc=$STAMP"
  echo "database=$DB_DATABASE"
  echo "server_version=$SERVER_VER"
  echo "pg_dump_version=$(pg_dump --version)"
  echo "last_migration=$LAST_MIG"
  echo "app_git_sha=$GIT_SHA"
  echo "tables=$(wc -l < "$COUNTS" | tr -d ' ')"
  echo "dump_bytes=$(wc -c < "$DUMP" | tr -d ' ')"
} > "$META"
chmod 600 "$DUMP" "$COUNTS" "$META" "$DUMP.sha256" "$LOGF"
ops_log "backup OK: $DUMP ($(wc -c < "$DUMP" | tr -d ' ') byte, $(wc -l < "$COUNTS" | tr -d ' ') tabel, last_migration=$LAST_MIG)"

# Retensi: hanya berkas milik DB ini.
if [ "$RETENTION" -gt 0 ]; then
  find "$BACKUP_DIR" -maxdepth 1 -type f -name "${DB_DATABASE}_*" -mtime "+$RETENTION" -print -delete \
    | sed "s|^|$(ops_ts) retensi hapus: |" || true
fi

# Hook offsite (opsional). Dijalankan dengan BACKUP_FILE/BACKUP_BASE/BACKUP_DIR di environment.
if [ -n "${BACKUP_OFFSITE_CMD:-}" ]; then
  ops_log "menjalankan BACKUP_OFFSITE_CMD"
  if BACKUP_FILE="$DUMP" BACKUP_BASE="$BASE" BACKUP_DIR="$BACKUP_DIR" bash -c "$BACKUP_OFFSITE_CMD"; then
    ops_log "offsite OK"
  else
    ops_log "ERROR: BACKUP_OFFSITE_CMD gagal (backup lokal TETAP ada)" >&2
    exit 2
  fi
else
  ops_log "PERINGATAN: BACKUP_OFFSITE_CMD belum diset - backup hanya ada di server ini (lokasi terpisah diputuskan di DEPLOYMENT-001)"
fi
