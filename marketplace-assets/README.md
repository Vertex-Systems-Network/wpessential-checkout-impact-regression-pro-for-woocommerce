# Woo Marketplace listing assets — 1.0.0

- `icon.png`: 160 × 160, optimized indexed PNG for upload.
- `icon.svg`: editable source, derived from the free extension icon. Suggested highlight #1E3A8A.
- Gallery: pending real screenshots, not fabricated. Capture at 1440 × 900 (above 896 × 550 minimum), browser zoom 100%, English admin locale, consistent viewport. Crop only if necessary; do not reconstruct or modify UI.

## Capture procedure

Use a disposable WordPress 7.1.5 / WooCommerce 11.3.1 / PHP 8.3 store with Free + Pro 1.0.0 installed, no real customers or orders, and outbound mail blocked/captured. Use synthetic products and checkout observations only. No actual checkout submissions.

| Filename | Screen | Capture state | Suggested caption |
|---|---|---|---|
| gallery-01-alerts.png | /wp-admin/admin.php?page=wpessential-checkout-impact#alerts | Alerts enabled; synthetic ops@example.test and qa@example.test recipients; settings saved | Receive grouped email alerts when checkout-related settings change. |
| gallery-02-export.png | /wp-admin/admin.php?page=wpessential-checkout-impact#reports | Select a scenario with a saved report; Export CSV and Export JSON visible/enabled | Export saved comparison reports in CSV or JSON. |
| gallery-03-comparison.png | WooCommerce > Checkout Impact > comparison tab | Approved baseline, changed shipping cost (EUR 4.90 to EUR 12.50), result visible | Review manually recorded checkout differences against an approved baseline. |

For the second and third captures use the same synthetic scenario, e.g. Germany / EUR. Do not expose real email addresses, hostnames, API keys, order data, credentials or license keys. Verify files are readable, at least 896 × 550, and show the actual active Pro build. Record the source commit and runtime versions with the final gallery. No image from the Free-only listing is evidence of Pro UI.

## Release state

Both header-update PR CI runs passed (Free 45707151928, Pro 45707442343); Pro main run 45708406277 also completed successfully. Free PR #10 still awaits owner merge at the time of this handoff. QIT and vendor account steps are not complete. The package build allowlist does not include this directory. No Pro distribution artifact may be uploaded publicly.
