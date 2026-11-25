# Woo Marketplace vendor submission brief — v1.0.0

Draft for the vendor dashboard. This is submission material, not an approved listing. Pricing, support commitments, product ID, QIT results and Marketplace approval are owner decisions.

## Product identity

- Product name: WPEssential Checkout Impact Regression Pro for WooCommerce
- Slug and text domain: `wpessential-checkout-impact-regression-pro-for-woocommerce`
- Version: 1.0.0
- Product URI requested by owner: https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-for-woocommerce/wiki
- Required dependency: WPEssential Checkout Impact Regression for WooCommerce 1.0.0 or newer, plus WooCommerce.
- Declared minimums: WordPress 6.9, WooCommerce 10.8, PHP 8.2. Tested-up-to headers: WordPress 7.1, WooCommerce 11.3. These declarations are not proof of every version combination.

## Short description

Get notified when checkout-related settings change and export saved comparison reports for your team.

## Product description

### Know when to review your checkout

A plugin update or change to shipping and payment settings can affect what shoppers see. Pro adds notifications and portable reporting to the free manual checkout comparison workflow.

- Receive grouped email alerts after recorded checkout-related changes.
- Export saved comparison reports as CSV or JSON for review and recordkeeping.
- Keep scenario history and approved baselines in the free plugin; Pro integrates with its existing admin screen.

### How it works

Install WooCommerce and the free extension, then activate Pro. Enable alerts under WooCommerce > Checkout Impact > Alerts and enter up to five recipients. Related events are grouped into a digest. Open Reports to export saved comparisons for a scenario.

The extension does not automatically execute checkout, create orders, process payments, or send customer emails. Alert messages use the store's mail setup and do not contain setting values. No external service is required.

### Frequently asked questions

**Can I use Pro without the free extension?** No. Pro requires the free extension and WooCommerce.

**Does an alert mean checkout is broken?** No. It reports a recorded change and prompts you to run your manual comparison.

**Does Pro include automated checkout tests?** No. The current version compares manually recorded observations.

**Where are reports stored?** The free plugin stores reports on your site; Pro exports those saved reports.

## Reviewer setup and critical flows

Use an isolated synthetic test store with outbound mail captured or disabled, and no live checkout or payment activity.

1. Install supported WordPress and WooCommerce versions. Activate WooCommerce.
2. Install the paired free 1.0.0 ZIP; activate it. Install the Pro 1.0.0 ZIP; activate it. Verify Pro cannot activate if the free dependency is inactive.
3. Open WooCommerce > Checkout Impact. Create a synthetic DE/EUR scenario with an approved baseline; record a different shipping cost and save the manual report. Verify changed fields are displayed.
4. Open Reports; export CSV and JSON. Verify the exports match the saved scenario/report and do not include unrelated records.
5. Open Alerts; enable notifications with synthetic addresses in a captured-mail environment. Change a test payment/shipping option, verify the recorded event is queued, and run scheduled jobs in the isolated environment after the digest interval. Confirm only configured addresses receive the digest and no setting values appear.
6. Confirm anonymous REST requests to Pro endpoints are denied. Deactivate and uninstall Pro, verifying the free plugin retains its reports and settings.
7. For reproducible automated smoke coverage, run the paired `tests/integration/run.sh` from the Pro repository using built Free and Pro directories. The integration test intercepts mail and does not place orders.

## Marketplace acceptance gates

- Confirm higher PHP 8.2 floor is acceptable against the published PHP minimum; do not change declared support without proof.
- Paired runtime matrix for WP 7.0.9/7.1.5 and WC 11.2.2/11.3.1 passed; see submission checklist. QIT remains required.
- Run all seven required QIT categories: API, E2E, activation, security, PHPCompatibility, malware and validation. Provide paired dependency package in the review setup; no published dependency assumed.
- Confirm freemium two-submission arrangement, product name and shared wiki URI with Woo. Add the assigned Woo product header after receipt.
- Confirm price, vendor account, support policy, and documentation hosting. No pricing assumption or SLA is made here.
- Capture genuine Pro screenshots from the tested build. Use a 160×160 product icon (keep primary mark within 112×112), choose highlight color, and prepare gallery images at least 896×550. Suggested images: saved report/export, changed field comparison, alert configuration. No fabricated UI imagery or claim of completed assets.
- Keep Pro distribution private per D-025. Build with `FREE_DIR=/path/to/free ./bin/build.sh` in an authorized local checkout or use private CI artifacts. Do not publish a Pro ZIP publicly.

References: [Submission checklist](https://developer.woocommerce.com/docs/woo-marketplace/submitting-your-product/), [Product page content and assets](https://developer.woocommerce.com/docs/woo-marketplace/product-page-content-and-assets/), [Extension header guidance](https://developer.woocommerce.com/docs/extensions/getting-started-extensions/how-to-design-a-simple-extension/).
