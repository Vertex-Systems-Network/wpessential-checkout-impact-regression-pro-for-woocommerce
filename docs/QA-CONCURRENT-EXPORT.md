# Concurrent digests and export cap

DEV-04: actual REST creation of 2,001 synthetic reports; assert both export formats return exactly the newest 2,000 unique reports in stable order. Record execution time, bytes and peak memory, no production SLA. The existing 2,000 cap is not itself a claim of unlimited export; check user-facing disclosure separately.

DEV-05: two independent WP-CLI PHP processes overlap at the captured mail call. Verify only one digest reaches the capture filter; separately enqueue a real recorded setting change while a digest is in flight and verify the new ID remains. This intentionally fails on a reproduced defect. Does not simulate every cron scheduler or queue race. Real mail globally blocked; no checkout, orders or payments.

Runtime WP 7.1.5 / WC 11.3.1 / PHP 8.3 / MariaDB. No production changes, package uploads or submitted Free changes. CI pending.
