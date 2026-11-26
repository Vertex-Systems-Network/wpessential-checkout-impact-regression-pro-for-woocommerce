# Multisite and export pagination QA

DEV-03: two actual WordPress sites on a subdirectory network, per-site plugin activation, separate WP-CLI bootstraps, site-scoped schema, scenarios, options and cron isolation. Network-wide activation/uninstall, newly provisioned sites after network activation, and subdomain networks are outside this initial test.

DEV-04: 45 nonexpired reports (three export pages) on parent, one on child, one expired fixture per site, exact report ID ordering/deduplication, CSV/JSON counts, and cross-site exclusion. Measure elapsed time and PHP memory as evidence, not an SLA. 2,000 report cap and larger stress tests remain open. DEV-05 concurrent writes/cron overlap remain open: no sequential test is claimed as concurrency proof.

Both tests use MariaDB 10.11, WordPress 7.1.5, WooCommerce 11.3.1 and PHP 8.3; Free is pinned to the submitted source. No real mail, checkout, payment or order execution. No production changes or public package uploads. Status: implemented, CI pending.
