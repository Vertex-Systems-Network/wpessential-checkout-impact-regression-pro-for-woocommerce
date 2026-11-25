#!/usr/bin/env bash
# Test CLI orchestration only. Never contact Woo, never publish, never send credentials.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/.." && pwd)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/bin" "$TMP/dist" "$TMP/mock"
cp "$ROOT/bin/qit-preflight.sh" "$TMP/bin/"
cat > "$TMP/bin/build.sh" <<'BUILD'
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../dist"
slug=wpessential-checkout-impact-regression-pro-for-woocommerce
printf 'synthetic fixture only' > "$slug-1.0.0.zip"
sha256sum "$slug-1.0.0.zip" > "$slug-1.0.0.zip.sha256"
BUILD
cat > "$TMP/mock/qit" <<'QIT'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$MOCK_QIT_LOG"
if [[ "$1" == "--version" ]]; then echo 'mock CLI'; exit 0; fi
if [[ "${MOCK_FAIL:-}" == "$1" ]]; then exit 3; fi
QIT
cat > "$TMP/mock/git" <<'GIT'
#!/usr/bin/env bash
printf '%040d\n' 0
GIT
chmod +x "$TMP/bin/build.sh" "$TMP/mock/"*
export PATH="$TMP/mock:$PATH" MOCK_QIT_LOG="$TMP/calls"
cd "$TMP"
if bin/qit-preflight.sh example runtime; then echo 'missing dependency gate'; exit 1; fi
test ! -e "$MOCK_QIT_LOG"
bin/qit-preflight.sh example static
test "$(grep -c '^run:' "$MOCK_QIT_LOG")" -eq 4
for name in security phpcompatibility malware validation; do
  grep -q "^run:$name example --zip=" "$MOCK_QIT_LOG"
done
: > "$MOCK_QIT_LOG"
QIT_FREE_DIRECTORY_AVAILABLE=yes QIT_WP_VERSION=stable QIT_WOO_VERSION=stable bin/qit-preflight.sh example runtime
test "$(grep -c '^run:' "$MOCK_QIT_LOG")" -eq 3
grep -q '^run:activation .* --plugin=wpessential-checkout-impact-regression-for-woocommerce --php=8.3 --wp=stable --woo=stable$' "$MOCK_QIT_LOG"
for name in woo-api woo-e2e; do
  grep -q "^run:$name .* --additional_plugins=wpessential-checkout-impact-regression-for-woocommerce --php=8.3 --wp=stable --woo=stable$" "$MOCK_QIT_LOG"
done
if MOCK_FAIL=run:security bin/qit-preflight.sh example static; then echo 'failed test was hidden'; exit 1; fi
echo 'PASS: local QIT CLI orchestration contract (no official QIT runs)'
