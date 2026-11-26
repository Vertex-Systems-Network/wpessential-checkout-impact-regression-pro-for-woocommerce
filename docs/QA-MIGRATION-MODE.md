# Native migration and checkout configuration QA

DEV-13: reconstruct known schema 1 difference (missing object_label) on a seeded MariaDB store with scenario, approved baseline, report, recorded change and Pro queue. Exercise anonymous settings hook, verify schema 2 restoration, old/new events, baseline, report and queue preservation. This is a reconstructed migration fixture, not a full historical ZIP upgrade or rollback test.

DEV-06: assign actual classic-shortcode and block-format checkout pages, verify Woo configuration and run the manual admin comparison in each configuration. Never load or submit checkout, create orders or process payments; compare order count before/after. No claim that frontend checkout rendering, automatic observation or checkout replay is supported. D-019 and Phase 6 NO-GO remain in force. No production changes.

WP 7.1.5 / WC 11.3.1 / PHP 8.3 / MariaDB; Free source pinned to submitted release. CI pending.
