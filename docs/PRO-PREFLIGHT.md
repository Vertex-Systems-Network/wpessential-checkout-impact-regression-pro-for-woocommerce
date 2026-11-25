# Pro pre-submission QIT and distribution handoff

## Status

The owner reports the Free 1.0.0 plugin has been submitted to WordPress.org. Approval, assigned directory slug and SVN access have not yet been confirmed. Do not modify the submitted Free package or resubmit it automatically.

All six paired Playground runtime combinations and prior gallery CI passed. The actual gallery is available from [run 45717484812](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45717484812/artifacts/15070851497); artifact expires on 2026-12-02. Use expanded comparison as the primary screenshot. The icon is in marketplace-assets.

## Managed QIT

Official documentation: https://qit.woo.com/docs/getting-started/ and https://qit.woo.com/docs/support/test-options/

Managed tests need an authenticated Woo partner account and an authorized extension identifier. Do not publish ZIPs or credentials as public artifacts. QIT is not installed or authenticated in this environment. On an authorized workstation, install and connect:

    composer global require woocommerce/qit-cli
    qit connect
    qit extensions

Then run from a private checkout:

    bin/qit-preflight.sh <authorized-extension-id> static

This builds Pro locally and attempts security, phpcompatibility, malware and validation. For activation, Woo API and Woo E2E, the Free dependency must be available through the directory slug or Woo must provide an approved way to include it. When the directory slug is live and verified:

    QIT_FREE_DIRECTORY_AVAILABLE=yes bin/qit-preflight.sh <authorized-extension-id> runtime

The script passes the actual ZIP, records local logs and checksums, does not publish a marketplace submission, and does not treat exit code alone as QIT approval. Record each official result, review warnings and fix any failures. If the partner account cannot run tests for an unlisted product, ask Woo/QIT support for a pre-submission testing arrangement.

## Corrected marketplace requirements

- Woo's validation docs state the Woo product header is added during deployment; do NOT manually insert one: https://qit.woo.com/docs/managed-tests/validation/
- PHP 8.2 is the actual minimum for both plugins. The published Woo checklist asks for PHP 7.4+ minimum, so obtain explicit acceptance of the higher floor. Lowering Pro alone would not help because the submitted Free dependency requires 8.2. Do not falsely claim support or change the Free package pending review.
- Woo requires two submissions for freemium products; the WordPress.org submission is not a Woo Marketplace submission. Confirm the dependency arrangement and shared wiki URI with Woo.
- Private distribution remains an owner action: repository Settings > General > Danger Zone > Change repository visibility > Make private. Verify access permissions, and configure a least-privilege PRO_REPO_TOKEN in Free if needed for compatibility CI. Once private, manually run the existing Pro CI and download plugin-packages. Do not publish Pro ZIPs to a public release. GitHub connector has no repository visibility change or manual workflow dispatch action.
- Draft commercial proposal (owner approval required): USD 49/year for one store, using Woo's standard annual subscription/renewal model; no SaaS billing, lifetime or multi-site promise. Confirm price parity and support capacity before listing.
- Draft support scope: installation and activation, alert/export functionality, reproducible defects and supported versions; exclude bespoke customization, live checkout debugging outside plugin scope, unrelated third-party configurations. Route support through Woo Marketplace support after listing. No response-time or refund promises beyond the applicable Woo policy; confirm owner can meet Woo's required support commitments.

The final decision, vendor account, managed QIT execution, dependency acceptance and publication are not marked complete.
