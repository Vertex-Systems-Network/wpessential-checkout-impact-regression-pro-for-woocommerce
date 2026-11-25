# WooCommerce Marketplace submission checklist – Pro add-on

**Product:** WPEssential Checkout Impact Regression Pro for WooCommerce · **Folder/slug:** `wpessential-checkout-impact-regression-pro-for-woocommerce` · **Version:** 1.0.0
**Depends on:** free plugin `wpessential-checkout-impact-regression-for-woocommerce` (WordPress.org) and WooCommerce.
**Package:** `dist/wpessential-checkout-impact-regression-pro-for-woocommerce-1.0.0.zip` (built by `./bin/build.sh` / CI artifact).

## What Pro adds

- **Change alerts:** one email digest (15-minute batching, up to 5 recipients) when a plugin update or payment/shipping setting change is recorded. Setting values are never included.
- **Report export:** CSV and JSON per scenario, with spreadsheet-formula neutralisation.
- New **Alerts** tab and export buttons inside the free plugin's screen (public hooks only; no changes to free code paths).

## Ready in the repository

| Item | State |
|---|---|
| Separate plugin with WooCommerce headers, `Requires Plugins`, HPOS + Cart/Checkout blocks declarations | Done |
| Capability checks on every REST route; translatable strings (`wpessential-checkout-impact-regression-pro-for-woocommerce`) | Done |
| Uninstall removes only Pro options and the scheduled digest | Done |
| Runtime tests on WP 7.1.4 / Woo 11.2.2 (alerts queue → digest mail, export download) | Done |
| Contract test `tests/pro-contract.php`, `tests/free-api-contract.php`, `tests/integration/` | Done |

## Owner steps (need your WooCommerce.com account)

1. Apply as a vendor: WooCommerce.com → *Sell on WooCommerce* (vendor application). Mention WPEssential and the free WordPress.org plugin.
2. After vendor approval, create the product in the Vendor Dashboard and upload the Pro ZIP.
3. Run **QIT** (Quality Insights Toolkit) tests offered in the Vendor Dashboard (activation, security, PHP compatibility, WooCommerce E2E) and fix findings.
4. Provide listing content: name, short description (use "What Pro adds" above), screenshots (Alerts tab, Reports with export), documentation URL (`https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-for-woocommerce/wiki`), support policy and pricing (open question in the plan).
5. Add the `Woo:` product header the dashboard gives you after approval to `wpessential-checkout-impact-regression-pro-for-woocommerce.php` (format `Woo: <product_id>:<hash>`) and rebuild – this lets WooCommerce.com deliver updates.
6. Confirm with the review team that requiring the free WordPress.org plugin is acceptable (open question in the plan).

## Submission identity update (2026-11-26)

Version 1.0.0 uses the current repository slug and shared [documentation wiki](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-for-woocommerce/wiki). Previous runtime results predate this rename; require fresh CI and vendor QIT. Pro artifacts must not be uploaded while this repository is public. The Marketplace product URI, Woo product header and approval of the free-plugin dependency remain vendor steps.

## Main branch verification — 2026-11-26

[CI run 45690442441](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45690442441), commit `eb2b8b437b2c4da17f32493e687d5ecff03b087b`: PHP 8.2, 8.3 and 8.4 lint and contracts, paired Playground integration on PHP 8.2 and 8.4, and ZIP build passed. Pro artifact upload was skipped because the repository is public. No QIT run or marketplace review has been verified.

See [vendor submission brief](VENDOR-SUBMISSION-BRIEF.md) for copy, reviewer steps, asset specifications and remaining decisions. The official [pre-submission checklist](https://developer.woocommerce.com/docs/woo-marketplace/submitting-your-product/) calls for the two latest major WordPress and WooCommerce releases, a PHP 7.4+ minimum (8.3+ recommended), and QIT API, E2E, activation, security, PHPCompatibility, malware and validation. This plugin declares PHP 8.2 minimum; confirm whether Woo accepts that higher floor before submitting, and do not lower the header without testing and implementation work. The free dependency must be installed from the paired ZIP until its directory listing is available.
