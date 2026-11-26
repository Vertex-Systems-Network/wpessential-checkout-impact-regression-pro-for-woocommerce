#!/usr/bin/env bash
# Runs the free + Pro integration check in WordPress Playground (php-wasm, SQLite).
# Usage: tests/integration/run.sh <free-build-dir> <pro-build-dir> [php-version]
# Defaults to the latest WordPress and WooCommerce from WordPress.org.
# Set TEST_WP_VERSION and TEST_WC_VERSION together for exact-version checks.
# Offline: set PLAYGROUND_WP_DIR to a WordPress directory that already contains WooCommerce
# (and remove the installPlugin step with PLAYGROUND_SKIP_WOO_INSTALL=1).
set -euo pipefail
FREE=$(realpath "$1"); PRO=$(realpath "$2"); PHP_VERSION="${3:-8.2}"
HERE=$(cd "$(dirname "$0")" && pwd)
BLUEPRINT="$HERE/blueprint.json"
WP_VERSION="${TEST_WP_VERSION:-}"
WC_VERSION="${TEST_WC_VERSION:-}"
if [[ -n "$WP_VERSION" || -n "$WC_VERSION" ]]; then
	[[ "$WP_VERSION" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ && "$WC_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Set both exact WP release and WC patch versions"; exit 1; }
fi
if [[ -n "$WP_VERSION" && ( -n "${PLAYGROUND_WP_DIR:-}" || "${PLAYGROUND_SKIP_WOO_INSTALL:-0}" = 1 ) ]]; then
	echo "Exact-version matrix cannot use an offline WordPress directory or skip Woo installation"
	exit 1
fi
BLUEPRINT=$(mktemp --suffix=.json)
trap 'rm -f "$BLUEPRINT"' EXIT
python3 - "$HERE/blueprint.json" "$BLUEPRINT" "$PHP_VERSION" "$WP_VERSION" "$WC_VERSION" "${PLAYGROUND_SKIP_WOO_INSTALL:-0}" <<'PY'
import json, sys
source, target, php, wp, wc, offline = sys.argv[1:]
with open(source) as stream:
    b = json.load(stream)
if offline == '1':
    b['steps'] = [s for s in b['steps'] if s['step'] != 'installPlugin']
    b['steps'].insert(0, {'step': 'activatePlugin', 'pluginPath': 'woocommerce/woocommerce.php'})
b['preferredVersions'] = {'php': php, 'wp': wp or 'latest'}
if wp:
    for step in b['steps']:
        if step['step'] == 'installPlugin':
            step['pluginData'] = {'resource': 'url', 'url': f'https://downloads.wordpress.org/plugin/woocommerce.{wc}.zip'}
for step in b['steps']:
    if step['step'] == 'runPHP':
        step['code'] = f"<?php $GLOBALS['wpecir_expected_versions'] = array('{wp}', '{wc}', '{php}'); require '/integration/check.php';"
with open(target, 'w') as stream:
    json.dump(b, stream)
PY
ARGS=(--php="$PHP_VERSION" --blueprint="$BLUEPRINT"
	--mount="$FREE:/wordpress/wp-content/plugins/wpessential-checkout-impact-regression-for-woocommerce"
	--mount="$PRO:/wordpress/wp-content/plugins/wpessential-checkout-impact-regression-pro-for-woocommerce"
	--mount="$HERE:/integration")
if [ -n "${PLAYGROUND_WP_DIR:-}" ]; then ARGS+=(--auto-mount="$PLAYGROUND_WP_DIR"); fi
rm -f "$HERE/result.txt"
# Pinned on purpose; bump deliberately after checking the Playground changelog.
PLAYGROUND_CLI_VERSION=3.1.64
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@${PLAYGROUND_CLI_VERSION}}"
set +e
OUT=$($CLI run-blueprint "${ARGS[@]}" 2>&1); STATUS=$?
set -e
if [ -f "$HERE/result.txt" ]; then cat "$HERE/result.txt"; fi
if [ "$STATUS" -ne 0 ] || ! grep -q "INTEGRATION OK" "$HERE/result.txt" 2>/dev/null; then
	echo "$OUT" | grep -v EBADENGINE | tail -30
	echo "Integration check did not complete (playground exit $STATUS)"
	exit 1
fi
