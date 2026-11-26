# WPEssential Checkout Impact Regression Pro for WooCommerce

By [WPEssential](https://wpessential.org) · Plugin page: <https://wpessential.org/plugins/wpessential-checkout-impact-regression-pro-for-woocommerce>

Paid add-on (WooCommerce Marketplace) for the free **[WPEssential Checkout Impact Regression for WooCommerce](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-for-woocommerce)** plugin (WordPress.org).

- **Change alerts:** one email digest when a plugin update or a payment/shipping setting change is recorded (15-minute batching, up to 5 recipients, retries, no setting values in mail).
- **Report export:** CSV and JSON for every saved comparison report of a scenario.

## Free + Pro: how they fit together

| | Free | Pro |
|---|---|---|
| Repository | `wpessential-checkout-impact-regression-for-woocommerce` | `wpessential-checkout-impact-regression-pro-for-woocommerce` (this repo) |
| Distribution | WordPress.org | WooCommerce Marketplace |
| Works alone | Yes – fully useful | **No** – needs the free plugin |
| Contains the other's code | No | No |

- `Requires Plugins: woocommerce, wpessential-checkout-impact-regression-for-woocommerce` – WordPress will not activate Pro without the free plugin, and will not let the free plugin be deactivated while Pro is active.
- Pro also checks `WPECIR_PRO_MIN_FREE_VERSION` against the free plugin's `WPECIR_VERSION` and stays idle with an admin notice when the free plugin is too old.
- Pro uses only the free plugin's public extension points: `wpecir_loaded`, `wpecir_change_event_recorded`, `wpecir_pro_active`, the `Repository` API, and the JS filters `wpecir.adminTabs` / `wpecir.reportActions`. `tests/free-api-contract.php` lists every one of them.

## Keeping the two in sync

Both repositories run the same checks against each other on every push, pull request and every night:

1. `tests/free-api-contract.php` – fails if the free plugin no longer provides something Pro uses (or Pro starts using something new).
2. `tests/integration/run.sh` – installs the latest WordPress + WooCommerce in WordPress Playground, activates free + Pro and checks REST routes, export, alerts, permissions and that Pro cannot run without free.
3. This (private) repository's CI builds **both** ZIPs into one **plugin-packages** artifact that expires after 7 days. GitHub lets anyone with read access to a repository download its artifacts, so **give access to this repository only to the owner/admins** – no read-only collaborators, outside collaborators or broad team grants. The public free repository never builds or uploads Pro files; it tests Pro inside the job using the read-only `PRO_REPO_TOKEN` secret.

If both repositories have a branch with the same name, CI tests those branches together – use that for changes that touch both plugins. **Keep this repository private.** While it is public, CI still builds and checks the Pro ZIP but does not upload it.

**Runner minutes:** while this repository is private, its CI does not start automatically (private repos use paid minutes). Use **Actions → Run workflow** when you need a run, or build locally with `FREE_DIR=../wpessential-checkout-impact-regression-for-woocommerce ./bin/build.sh`. On a private repository the automatic runs show as **skipped** – that is not a pass. Verify a Pro pull request for free in the public free repository: push a same-named branch there and open a PR, or use **Actions → CI → Run workflow** there with `pro_ref` set to your Pro branch. That run checks the compatibility contract, Pro's tests and the Playground integration. The free repository also tests Pro `main` every night.

Optional: a `CROSS_REPO_TOKEN` secret here (fine-grained, *Contents: read & write* on the free repo) makes a Pro run on `main` (a push while public, or a manual run) re-run the free repository's CI.

## Development

```bash
git clone https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-for-woocommerce ../wpessential-checkout-impact-regression-for-woocommerce
php tests/pro-contract.php
php tests/free-api-contract.php                 # FREE_DIR defaults to ../wpessential-checkout-impact-regression-for-woocommerce
./bin/build.sh                                   # dist/: Pro ZIP + free ZIP
tests/integration/run.sh <free-build-dir> <pro-build-dir> 8.2
```

Edit `wordpress-org/readme.md`, then run `php tools/readme-to-txt.php` (same readme system as WPEssential). Release steps: [docs/WOO-MARKETPLACE-SUBMISSION.md](docs/WOO-MARKETPLACE-SUBMISSION.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Submission identity update (2026-11-26)

Version 1.0.0 uses the current repository slug and shared [documentation wiki](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-for-woocommerce/wiki). Previous runtime results predate this rename; require fresh CI and vendor QIT. Pro artifacts must not be uploaded while this repository is public. The Marketplace product URI, Woo product header and approval of the free-plugin dependency remain vendor steps.

Paired runtime compatibility through WooCommerce 11.3.1 was verified in [CI run 45705094936](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45705094936); see submission documentation for exact combinations and scope.

## Pre-submission QIT

See [Pro preflight](docs/PRO-PREFLIGHT.md) for the managed test runner, latest gallery evidence, private package distribution and owner decisions. Free 1.0.0 has been submitted to WordPress.org according to the owner; approval is unconfirmed.

## Advanced QA

See [advanced QA backlog and acceptance criteria](docs/ADVANCED-QA.md). Additional testing is separate from the submitted v1 scope; no production code changes.
