# Advanced QA — post-v1.0 submission

Additional QA on the manual comparison product (D-019). The canonical v1 scope remains 30/30; QA coverage does not expand it. No live checkout, orders, payments or real mail. The owner reported Free 1.0.0 already submitted; its original package was not replaced. Later RTL/accessibility source fixes and Pro concurrency fixes were merged on GitHub; coordinate the replacement Free candidate with the directory reviewer, and rebuild Pro before marketplace use.

| ID | Verified scope and evidence | Boundary |
|---|---|---|
| DEV-01 | [Browser save/reload, report expansion, actual CSV/JSON downloads](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45773238678) | Pro flows, not exhaustive Free E2E |
| DEV-02 | [Native exact WP 6.9 / WC 10.8.0 / PHP 8.2](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45773238737); [seven Playground jobs](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45773238623) | Exact combinations only |
| DEV-03 | [Two-site MariaDB, per-site schema/scenarios/alerts/cron/export isolation](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45773346188) | No network-wide activation or new-site lifecycle claim |
| DEV-04 | [2,001 synthetic reports; newest 2,000 CSV/JSON, truncation metadata and exact cap](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45774858104) | Isolated benchmark, not an SLA |
| DEV-05 | [Duplicate digest and lost event reproduced](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45773768702); [fixed and retested](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45774088440) | No exactly-once SMTP guarantee or all races claim |
| DEV-06 | [Classic and block checkout page configuration, manual admin comparison, no order created](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45774284469) | No checkout execution/replay |
| DEV-07 | [Stripe 11.2.0, Flexible Shipping 6.13.9, Polylang 3.9.2 and Query Monitor 4.1.1 coexistence](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45775226272) | No provider transactions or live rates |
| DEV-08 | [Eight scoped axe scans passed and keyboard checks](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45774729546) | Not full WCAG certification |
| DEV-09 | [Actual WordPress Arabic/English admin direction, corrected RTL navigation and layout](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45774729546) | Not complete translations or WPML integration |
| DEV-10 | [320, 390, 768, 1024 and 1440px Alerts and Reports](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45774729546) | Two screens, Chromium |
| DEV-11 | [All four plugins together; activation and deactivation lifecycle](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45775226272) | Not arbitrary plugin combinations |
| DEV-12 | [Three failed mail attempts, queue preservation, recovery](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45773238737) | Mail intercepted |
| DEV-13 | [Reconstructed v1 schema migrates to v2 with retained records](https://github.com/Vertex-Systems-Network/wpessential-checkout-impact-regression-pro-for-woocommerce/actions/runs/45774284469) | Not historical ZIP upgrade or rollback |
| DEV-14 | Official managed QIT | Blocked: authorized Woo partner credentials/extension ID |

13 of 14 rows have passing evidence within the explicit tested slices above, not exhaustive completion or Marketplace approval. Evidence refers to the tested commits, not an assertion that every test has run against every subsequent merge. For final release, require merged source, fresh package checksums, current paired CI and official QIT where available. Pro repository remains private-distribution only per D-025; public ZIP upload forbidden.

### Minimum-version correction

Run 45773015300 detected Playground resolving 6.9 to 6.9.12; exact version assertion correctly failed. Pin 6.9.12 in Playground and use native WP 6.9 release archive for the base release; both passed.
