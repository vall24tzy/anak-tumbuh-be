#!/usr/bin/env bash
# Pustaka bersama skrip OPS-001. Di-source oleh skrip lain; JANGAN dijalankan langsung.

OPS_REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

ops_ts()  { date -u +%Y-%m-%dT%H:%M:%SZ; }
ops_log() { printf '%s %s\n' "$(ops_ts)" "$*"; }
ops_die() { ops_log "ERROR: $1" >&2; exit "${2:-1}"; }

# Baca DB_* dan APP_ENV dari .env (atau ENV_FILE) HANYA bila variabelnya belum diset di environment.
ops_load_env() {
  local f="${ENV_FILE:-$OPS_REPO_ROOT/.env}" k v
  if [ -f "$f" ]; then
    for k in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD APP_ENV; do
      if [ -z "${!k+x}" ]; then
        v=$(grep -E "^${k}=" "$f" | tail -1 | cut -d= -f2- | sed -E "s/^\"(.*)\"\$/\1/; s/^'(.*)'\$/\1/") || true
        if [ -n "$v" ]; then export "$k=$v"; fi
      fi
    done
  fi
  export DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-5432}"
  export PGHOST="$DB_HOST" PGPORT="$DB_PORT"
  if [ -n "${DB_USERNAME:-}" ]; then export PGUSER="$DB_USERNAME"; fi
  if [ -n "${DB_PASSWORD:-}" ]; then export PGPASSWORD="$DB_PASSWORD"; fi   # password tidak pernah lewat argumen perintah
}

# 0 (benar) bila nama DB dilindungi: daftar OPS_PROTECTED_DBS, pola prod|production|live,
# atau DB aplikasi saat APP_ENV=production. Tidak ada opsi untuk menimpa perlindungan ini.
ops_is_protected_db() {
  local name="$1" p arr
  IFS=',' read -ra arr <<< "${OPS_PROTECTED_DBS:-}"
  for p in "${arr[@]:-}"; do
    if [ -n "$p" ] && [ "$p" = "$name" ]; then return 0; fi
  done
  if [[ "$name" =~ (^|[_-])(prod|production|live)([_-]|$) ]]; then return 0; fi
  if [ "${APP_ENV:-}" = "production" ] && [ "$name" = "${DB_DATABASE:-}" ]; then return 0; fi
  return 1
}

ops_need_cmd() { command -v "$1" >/dev/null 2>&1 || ops_die "perintah '$1' tidak ditemukan (pasang klien PostgreSQL)"; }

# Versi mayor dari keluaran "tool (PostgreSQL) 16.15 ..."
ops_pg_major() { "$1" --version | sed -E 's/.* ([0-9]+)(\.[0-9]+)*.*/\1/' | head -1; }
