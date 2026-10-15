#!/usr/bin/env bash
# OPS-001 — uji concurrent insert untuk 5 partial unique index (academic_years, headmasters,
# teacher_class_assignments x2, student_class_assignments) dengan DUA sesi psql paralel sungguhan.
#
# Pemakaian:  scripts/test-partial-unique-concurrency.sh --confirm [--db NAMA_DB]
# Env:        OPS_HOLD_SECONDS (default 3) = lama sesi A menahan transaksi terbuka
#
# Cara kerja: tabel salinan di schema sementara `ops_concurrency_test` dibuat dengan
# CREATE TABLE ... (LIKE public.X INCLUDING INDEXES INCLUDING CONSTRAINTS), sehingga definisi index parsial persis sama
# dengan DB hasil migrasi (dicocokkan ulang di awal) tanpa menyentuh baris, sequence, atau tabel asli. Schema dihapus di akhir.
# Untuk tiap index:
#   1) A commit    : A INSERT lalu menahan transaksi; B INSERT konflik -> B HARUS MENUNGGU (wait_event Lock),
#                    lalu GAGAL dengan SQLSTATE 23505 pada index yang benar setelah A commit.
#   2) A rollback  : B harus menunggu lalu BERHASIL setelah A rollback.
#   3) kontrol     : B memasukkan baris yang tidak masuk predikat parsial (is_active=false/inactive/ended) -> TIDAK menunggu, berhasil.
# Skrip menolak berjalan tanpa --confirm dan menolak nama DB produksi/terlindungi (lihat lib/ops-common.sh).
# Exit code: 0 semua PASS | 1 ada FAIL atau prasyarat tidak terpenuhi
set -uo pipefail
umask 077
source "$(dirname "${BASH_SOURCE[0]}")/lib/ops-common.sh"

CONFIRM=0; DB=""
while [ $# -gt 0 ]; do
  case "$1" in
    --confirm) CONFIRM=1; shift ;;
    --db) DB="${2:?--db butuh nilai}"; shift 2 ;;
    -h|--help) sed -n '2,19p' "$0"; exit 0 ;;
    *) ops_die "argumen tidak dikenal: $1" ;;
  esac
done
ops_load_env
ops_need_cmd psql
DB="${DB:-${DB_DATABASE:-}}"
[ -n "$DB" ] || ops_die "nama database tidak ditemukan (--db, env, atau .env)"
[ "$CONFIRM" -eq 1 ] || ops_die "uji ini menulis data uji ke schema sementara: tambahkan --confirm setelah memeriksa target ($DB)"
if ops_is_protected_db "$DB"; then ops_die "DITOLAK: '$DB' adalah nama database produksi/terlindungi. Jalankan pada salinan hasil restore atau staging."; fi

S=ops_concurrency_test
HOLD="${OPS_HOLD_SECONDS:-3}"
MIN_WAIT="$(awk -v h="$HOLD" 'BEGIN{printf "%.2f", h*0.5}')"
WORK="$(mktemp -d /tmp/ops_conc.XXXXXX)"
PSQL=(psql -qAtX -d "$DB" -v ON_ERROR_STOP=1 -v VERBOSITY=verbose)
Q() { psql -qAtX -d "$DB" -v ON_ERROR_STOP=1 -c "$1"; }
PASS=0; FAIL=0
ok()  { PASS=$((PASS+1)); ops_log "PASS  $*"; }
bad() { FAIL=$((FAIL+1)); ops_log "FAIL  $*" >&2; }

[ "$(Q "SELECT count(*) FROM pg_namespace WHERE nspname='$S'")" = "0" ] || ops_die "schema $S sudah ada; hapus manual setelah dipastikan bukan milik proses lain"
cleanup() { Q "DROP SCHEMA IF EXISTS $S CASCADE" >/dev/null 2>&1; rm -rf "$WORK"; }
trap cleanup EXIT

Q "CREATE SCHEMA $S" >/dev/null || ops_die "tidak bisa membuat schema sementara"
for t in academic_years headmasters teacher_class_assignments student_class_assignments; do
  Q "CREATE TABLE $S.$t (LIKE public.$t INCLUDING INDEXES INCLUDING CONSTRAINTS)" >/dev/null || ops_die "gagal menyalin tabel $t (apakah migrasi sudah dijalankan?)"
done

# Kecocokan definisi index parsial: public vs salinan. Mengembalikan nama index SALINAN (untuk dicocokkan ke pesan error).
scratch_index() {   # $1 = nama index di public
  Q "SELECT s.indexname FROM pg_indexes p JOIN pg_indexes s
       ON s.schemaname='$S' AND s.tablename=p.tablename
      AND regexp_replace(s.indexdef,'^CREATE UNIQUE INDEX \S+ ON \S+ ','') = regexp_replace(p.indexdef,'^CREATE UNIQUE INDEX \S+ ON \S+ ','')
      WHERE p.schemaname='public' AND p.indexname='$1' AND p.indexdef LIKE 'CREATE UNIQUE INDEX%' AND p.indexdef LIKE '% WHERE %' LIMIT 1"
}

# nama_index_public | tabel | SQL A | SQL B-konflik | SQL B-kontrol(tidak masuk predikat)
CASES=(
"uq_academic_years_one_active_per_school|academic_years|INSERT INTO $S.academic_years (id,school_id,name,start_date,end_date,is_active) VALUES (1,10,'AY-A','2026-07-01','2027-06-30',true)|INSERT INTO $S.academic_years (id,school_id,name,start_date,end_date,is_active) VALUES (2,10,'AY-B','2027-07-01','2028-06-30',true)|INSERT INTO $S.academic_years (id,school_id,name,start_date,end_date,is_active) VALUES (3,10,'AY-C','2028-07-01','2029-06-30',false)"
"uq_headmasters_one_active_per_school|headmasters|INSERT INTO $S.headmasters (id,user_id,school_id,fullname,nip,gender,status) VALUES (1,101,10,'HM A','NIP-A','laki-laki','active')|INSERT INTO $S.headmasters (id,user_id,school_id,fullname,nip,gender,status) VALUES (2,102,10,'HM B','NIP-B','laki-laki','active')|INSERT INTO $S.headmasters (id,user_id,school_id,fullname,nip,gender,status) VALUES (3,103,10,'HM C','NIP-C','laki-laki','inactive')"
"uq_tca_one_active_per_teacher_year|teacher_class_assignments|INSERT INTO $S.teacher_class_assignments (id,teacher_id,school_id,academic_year_id,rombel_id,is_active) VALUES (1,201,10,20,301,true)|INSERT INTO $S.teacher_class_assignments (id,teacher_id,school_id,academic_year_id,rombel_id,is_active) VALUES (2,201,10,20,302,true)|INSERT INTO $S.teacher_class_assignments (id,teacher_id,school_id,academic_year_id,rombel_id,is_active) VALUES (3,201,10,20,303,false)"
"uq_tca_one_active_per_rombel_year|teacher_class_assignments|INSERT INTO $S.teacher_class_assignments (id,teacher_id,school_id,academic_year_id,rombel_id,is_active) VALUES (1,201,10,20,301,true)|INSERT INTO $S.teacher_class_assignments (id,teacher_id,school_id,academic_year_id,rombel_id,is_active) VALUES (2,202,10,20,301,true)|INSERT INTO $S.teacher_class_assignments (id,teacher_id,school_id,academic_year_id,rombel_id,is_active) VALUES (3,203,10,20,301,false)"
"uq_sca_one_active_per_student_year|student_class_assignments|INSERT INTO $S.student_class_assignments (id,student_id,school_id,rombel_id,academic_year_id,status) VALUES (1,401,10,301,20,'active')|INSERT INTO $S.student_class_assignments (id,student_id,school_id,rombel_id,academic_year_id,status) VALUES (2,401,10,302,20,'active')|INSERT INTO $S.student_class_assignments (id,student_id,school_id,rombel_id,academic_year_id,status) VALUES (3,401,10,303,20,'ended')"
)

now() { date +%s.%N; }
# run_case <label> <tabel> <sqlA> <sqlB> <akhirA: COMMIT|ROLLBACK> -> menyetel B_RC, B_ERR, B_SECS, B_WAIT
run_case() {
  local label="$1" table="$2" sqlA="$3" sqlB="$4" endA="$5" t0 t1
  Q "TRUNCATE $S.$table" >/dev/null
  : > "$WORK/a.out"; rm -f "$WORK/b.res" "$WORK/b.err"
  ( PGAPPNAME=ops_A "${PSQL[@]}" > "$WORK/a.out" 2> "$WORK/a.err" <<SQL
BEGIN;
$sqlA;
\echo A_INSERTED
SELECT pg_sleep($HOLD);
$endA;
SQL
  ) &
  local pidA=$!
  for _ in $(seq 1 100); do grep -q A_INSERTED "$WORK/a.out" 2>/dev/null && break; sleep 0.1; done
  grep -q A_INSERTED "$WORK/a.out" || { bad "$label: sesi A gagal menyisipkan baris (lihat $WORK/a.err)"; kill $pidA 2>/dev/null; return 1; }
  ( t0=$(now); PGAPPNAME=ops_B "${PSQL[@]}" -c "$sqlB" > /dev/null 2> "$WORK/b.err"; rc=$?; t1=$(now); echo "$rc $t0 $t1" > "$WORK/b.res" ) &
  local pidB=$!
  sleep 1
  B_WAIT="$(Q "SELECT coalesce(wait_event_type||':'||wait_event,'tidak-menunggu') FROM pg_stat_activity WHERE application_name='ops_B' ORDER BY backend_start DESC LIMIT 1")"
  [ -n "$B_WAIT" ] || B_WAIT="sesi-B-sudah-selesai"
  wait $pidA; wait $pidB
  read -r B_RC T0 T1 < "$WORK/b.res"
  B_SECS="$(awk -v a="$T0" -v b="$T1" 'BEGIN{printf "%.2f", b-a}')"
  B_ERR="$(tr '\n' ' ' < "$WORK/b.err")"
}

for c in "${CASES[@]}"; do
  IFS='|' read -r IDX TABLE SQLA SQLB SQLC <<< "$c"
  SIDX="$(scratch_index "$IDX")"
  if [ -z "$SIDX" ]; then bad "$IDX: index parsial TIDAK ditemukan/definisinya berbeda pada salinan (periksa migrasi)"; continue; fi
  ops_log "== $IDX (salinan: $SIDX) pada tabel $TABLE"

  run_case "$IDX/A-commit" "$TABLE" "$SQLA" "$SQLB" COMMIT
  ROWS="$(Q "SELECT count(*) FROM $S.$TABLE")"
  if [ "$B_RC" -ne 0 ] && grep -q '23505' <<< "$B_ERR" && grep -q "\"$SIDX\"" <<< "$B_ERR" \
     && awk -v s="$B_SECS" -v m="$MIN_WAIT" 'BEGIN{exit !(s>=m)}' && [[ "$B_WAIT" == Lock:* ]] && [ "$ROWS" = "1" ]; then
    ok "$IDX | A-commit: B menunggu (${B_WAIT}, ${B_SECS}s) lalu GAGAL 23505 pada \"$SIDX\"; baris akhir=$ROWS"
  else
    bad "$IDX | A-commit: rc=$B_RC wait=$B_WAIT secs=$B_SECS rows=$ROWS err=[$B_ERR]"
  fi

  run_case "$IDX/A-rollback" "$TABLE" "$SQLA" "$SQLB" ROLLBACK
  ROWS="$(Q "SELECT count(*) FROM $S.$TABLE")"; SURV="$(Q "SELECT id FROM $S.$TABLE ORDER BY id LIMIT 1")"
  if [ "$B_RC" -eq 0 ] && awk -v s="$B_SECS" -v m="$MIN_WAIT" 'BEGIN{exit !(s>=m)}' && [[ "$B_WAIT" == Lock:* ]] && [ "$ROWS" = "1" ] && [ "$SURV" = "2" ]; then
    ok "$IDX | A-rollback: B menunggu (${B_WAIT}, ${B_SECS}s) lalu BERHASIL; baris akhir=$ROWS (id $SURV = milik B)"
  else
    bad "$IDX | A-rollback: rc=$B_RC wait=$B_WAIT secs=$B_SECS rows=$ROWS surviving_id=$SURV err=[$B_ERR]"
  fi

  run_case "$IDX/kontrol" "$TABLE" "$SQLA" "$SQLC" COMMIT
  ROWS="$(Q "SELECT count(*) FROM $S.$TABLE")"
  if [ "$B_RC" -eq 0 ] && awk -v s="$B_SECS" 'BEGIN{exit !(s<1.5)}' && [[ "$B_WAIT" != Lock:* ]] && [ "$ROWS" = "2" ]; then
    ok "$IDX | kontrol non-predikat: B TIDAK menunggu (${B_SECS}s) dan berhasil; baris akhir=$ROWS"
  else
    bad "$IDX | kontrol non-predikat: rc=$B_RC wait=$B_WAIT secs=$B_SECS rows=$ROWS err=[$B_ERR]"
  fi
done

ops_log "RINGKASAN: PASS=$PASS FAIL=$FAIL (HOLD=${HOLD}s, batas tunggu minimal=${MIN_WAIT}s, db=$DB)"
[ "$FAIL" -eq 0 ] && [ "$PASS" -gt 0 ]
