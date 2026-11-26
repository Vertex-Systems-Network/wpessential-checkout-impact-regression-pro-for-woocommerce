#!/usr/bin/env bash
set -euo pipefail
HERE=$(cd "$(dirname "$0")" && pwd)
: "${QA_WP:?Disposable WP path required}" "${QA_SYNC:?Coordination directory required}"
run() { QA_MODE="$1" wp --path="$QA_WP" --user=qa-admin eval-file "$HERE/worker.php"; }
start_hold() {
  run send-hold > "$QA_SYNC/hold.log" 2>&1 &
  HOLD=$!
  for i in {1..200}; do
    [[ -f "$QA_SYNC/entered" ]] && return
    kill -0 "$HOLD" 2>/dev/null || { cat "$QA_SYNC/hold.log"; return 1; }
    sleep .1
  done
  echo "Hold barrier not reached"; return 1
}
finish_hold() { touch "$QA_SYNC/release"; wait "$HOLD"; cat "$QA_SYNC/hold.log"; }
cleanup() { touch "$QA_SYNC/release"; if [[ -n "${HOLD:-}" ]]; then wait "$HOLD" 2>/dev/null || true; fi; }
trap cleanup EXIT
reset() { rm -f "$QA_SYNC"/{entered,release,deliveries.log,original.json,new.json}; run seed; }
fail=0
reset
start_hold
run send
finish_hold
run verify-duplicate || fail=1
reset
start_hold
run queue-new
finish_hold
run verify-new || fail=1
exit "$fail"
