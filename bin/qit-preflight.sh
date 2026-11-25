#!/usr/bin/env bash
# Maintainer-only managed QIT runner. Do not run in public CI; QIT uses a partner account.
# Usage: bin/qit-preflight.sh <authorized-qit-extension-id-or-slug> [static|runtime|all]
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=wpessential-checkout-impact-regression-pro-for-woocommerce
FREE_SLUG=wpessential-checkout-impact-regression-for-woocommerce
SUT="${1:?Pass an extension ID or slug authorized by qit extensions}"
MODE="${2:-static}"
case "$MODE" in static|runtime|all) ;; *) echo "Expected static, runtime or all" >&2; exit 2;; esac
command -v qit >/dev/null || { echo "Install QIT and authenticate: composer global require woocommerce/qit-cli; qit connect" >&2; exit 1; }
# Official QIT cloud tests accept a local SUT ZIP; runtime dependencies use approved directory slugs.
# Do not start cloud runtime tests until the Free slug is approved and available, or Woo arranges dependency installation.
if [[ "$MODE" != static && "${QIT_FREE_DIRECTORY_AVAILABLE:-}" != yes ]]; then
  echo "Runtime QIT requires the Free directory dependency. Set QIT_FREE_DIRECTORY_AVAILABLE=yes only after verifying its availability." >&2
  exit 1
fi
./bin/build.sh --pro-only
ZIP="$PWD/dist/$SLUG-1.0.0.zip"
(cd dist && sha256sum -c "$SLUG-1.0.0.zip.sha256")
OUT="$PWD/qit-results/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$OUT"
chmod 700 "$OUT"
umask 077
qit --version > "$OUT/cli-version.txt"
git rev-parse HEAD > "$OUT/source.txt"
cp "$ZIP.sha256" "$OUT/"
failed=0
run_test() {
  local label="$1" test="$2"; shift 2
  echo "Running $label (results kept locally)"
  if qit "run:$test" "$SUT" --zip="$ZIP" "$@" > "$OUT/$label.log" 2>&1; then
    printf '%s\t%s\n' "$label" "exit 0 — inspect official result" >> "$OUT/status.tsv"
  else
    printf '%s\t%s\n' "$label" "nonzero exit — inspect log" >> "$OUT/status.tsv"
    failed=1
  fi
}
if [[ "$MODE" == static || "$MODE" == all ]]; then
  for test in security phpcompatibility malware validation; do run_test "$test" "$test"; done
fi
if [[ "$MODE" == runtime || "$MODE" == all ]]; then
  for test in activation woo-api woo-e2e; do
    run_test "$test" "$test" --additional_plugins="$FREE_SLUG" --php=8.3 --wp=7.1 --woo=11.3
  done
fi
echo "Local QIT evidence: $OUT"
echo "CLI exit status is not an approval; review official report outcomes, warnings and dependency activation."
exit "$failed"
