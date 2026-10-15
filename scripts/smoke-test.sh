#!/usr/bin/env bash
# OPS-001 — smoke test MINIMAL untuk langkah "Smoke test" di docs/deployment-runbook.md.
# Bukan smoke test produksi penuh (signin semua role, CRUD, upload, QR = DEPLOYMENT-001).
#
# Pemakaian:  SMOKE_BASE_URL=https://api.contoh.id SMOKE_USERNAME=... SMOKE_PASSWORD=... scripts/smoke-test.sh
# Env:        SMOKE_BASE_URL (wajib, tanpa slash akhir)  SMOKE_USERNAME / SMOKE_PASSWORD (akun Super Admin; password tidak lewat argumen)
#             SMOKE_MIN_SCHOOLS (opsional) = jumlah School minimal yang harus terbaca (mis. dari manifes backup)
# Cek:        1) GET /up = 200   2) POST /api/v1/auth/login/staff = 200 + access_token
#             3) GET /api/v1/auth/me = 200 + role super_admin   4) GET /api/v1/schools = 200 + objek meta
# Exit code:  0 semua PASS | 1 ada FAIL
set -uo pipefail
ts() { date -u +%Y-%m-%dT%H:%M:%SZ; }
PASS=0; FAIL=0
ok()  { PASS=$((PASS+1)); echo "$(ts) PASS  $*"; }
bad() { FAIL=$((FAIL+1)); echo "$(ts) FAIL  $*" >&2; }
command -v curl >/dev/null || { echo "curl tidak ditemukan" >&2; exit 1; }
: "${SMOKE_BASE_URL:?SMOKE_BASE_URL wajib}"; : "${SMOKE_USERNAME:?SMOKE_USERNAME wajib}"; : "${SMOKE_PASSWORD:?SMOKE_PASSWORD wajib}"
BASE="${SMOKE_BASE_URL%/}"
case "$SMOKE_USERNAME$SMOKE_PASSWORD" in *'"'*|*'\'*) echo "username/password tidak boleh memuat tanda kutip atau backslash (JSON sederhana)" >&2; exit 1 ;; esac
TMP="$(mktemp)"; trap 'rm -f "$TMP"' EXIT
req() {  # req METODE URL [header-auth]  -> kode HTTP; badan di $TMP
  local m="$1" u="$2" a="${3:-}"
  if [ "$m" = "POST" ]; then
    printf '{"username":"%s","password":"%s"}' "$SMOKE_USERNAME" "$SMOKE_PASSWORD" | \
      curl -sS --max-time 20 -o "$TMP" -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' --data @- "$u"
  else
    curl -sS --max-time 20 -o "$TMP" -w '%{http_code}' -H 'Accept: application/json' ${a:+-H "Authorization: Bearer $a"} "$u"
  fi
}
note503() { [ "$1" = "503" ] && echo " (503 = mode maintenance masih aktif? jalankan: php artisan up)"; }

C="$(req GET "$BASE/up")" || C=000
[ "$C" = "200" ] && ok "GET /up = 200" || bad "GET /up = $C$(note503 "$C")"

C="$(req POST "$BASE/api/v1/auth/login/staff")" || C=000
TOKEN="$(sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p' "$TMP")"
if [ "$C" = "200" ] && [ -n "$TOKEN" ]; then ok "login staff = 200 + access_token"; else bad "login staff = $C$(note503 "$C") (429 = rate limit login: php artisan cache:clear)"; fi

if [ -n "$TOKEN" ]; then
  C="$(req GET "$BASE/api/v1/auth/me" "$TOKEN")" || C=000
  if [ "$C" = "200" ] && grep -q '"role":"super_admin"' "$TMP"; then ok "GET /auth/me = 200, role super_admin"; else bad "GET /auth/me = $C (role super_admin tidak terbaca)"; fi
  C="$(req GET "$BASE/api/v1/schools?per_page=100" "$TOKEN")" || C=000
  if [ "$C" = "200" ] && grep -q '"meta"' "$TMP"; then
    TOTAL="$(sed -n 's/.*"total":\([0-9]*\).*/\1/p' "$TMP" | head -1)"
    ok "GET /schools = 200, meta.total=${TOTAL:-?}"
    if [ -n "${SMOKE_MIN_SCHOOLS:-}" ] && [ "${TOTAL:-0}" -lt "$SMOKE_MIN_SCHOOLS" ]; then bad "jumlah School ${TOTAL:-0} < SMOKE_MIN_SCHOOLS=$SMOKE_MIN_SCHOOLS"; fi
  else bad "GET /schools = $C"; fi
else
  bad "GET /auth/me dan /schools dilewati (tidak ada token)"
fi
echo "$(ts) RINGKASAN smoke test: PASS=$PASS FAIL=$FAIL"
[ "$FAIL" -eq 0 ]
