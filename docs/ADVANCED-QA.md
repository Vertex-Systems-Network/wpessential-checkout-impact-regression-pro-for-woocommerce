# Advanced QA — post-v1.0 submission

Owner requested advanced QA after Free 1.0.0 submission. This is a testing-only branch: do not modify submitted production packages or expand D-019 (manual comparison). No live checkout/orders/payments or real mail.

| ID | Acceptance criteria | Status |
|---|---|---|
| DEV-01 | Actual Chromium UI saves and reloads alert settings, expands report and downloads/validates CSV and JSON in synthetic MariaDB store | Implemented; CI pending; not full Free E2E |
| DEV-02 | Exact declared base WP 6.9 / WC 10.8.0 / PHP 8.2 runtime passes, plus existing six-version matrix | Implemented; CI pending; no claim until exact installed versions confirmed |
| DEV-12 | Forced mail failures preserve queue, schedule retries, stop after three, show status and recover on new event without real mail | Implemented; CI pending |
| DEV-03–06 | Multisite, performance, concurrency and classic/block observation boundaries | Open; separate test plans required |
| DEV-07–11 | Third-party compatibility, accessibility, RTL, responsive admin, conflicts | Open |
| DEV-13 | Migration and upgrade regression | Open; prior v1→v2 runtime evidence exists |
| DEV-14 | Official QIT | Blocked by partner access |

These rows are additional QA, not new v1 features and do not change the canonical 30/30 scope count. Browser fixture pins Free main 6d3ec072 and verifies actual saved reports, no real mail. Gallery uploads images only; package publication guard unchanged. Exact version matching accepts 6.9 and 6.9.0 as the same base release only; later patch releases are not accepted. If minimum combinations are unavailable or unsupported, record the failure rather than silently upgrading. 

### Minimum-version correction

Run 45773015300 detected WordPress Playground resolving 6.9 to 6.9.12; the exact-version assertion correctly failed before functional checks. Pin 6.9.12 explicitly in Playground, and add a separate native MariaDB job installing WP 6.9 with WP-CLI and the actual built ZIPs. Both jobs must pass; do not treat the first failed job as product failure or a base-version pass. No production files changed.
