# Concurrent digests and export cap

DEV-04: actual REST creation of 2,001 synthetic reports; assert both export formats return exactly the newest 2,000 unique reports in stable order. Record execution time, bytes and peak memory, no production SLA. The existing 2,000 cap is not itself a claim of unlimited export; check user-facing disclosure separately.

DEV-05: two independent WP-CLI PHP processes overlap at the captured mail call. Verify only one digest reaches the capture filter; separately enqueue a real recorded setting change while a digest is in flight and verify the new ID remains. This intentionally fails on a reproduced defect. Does not simulate every cron scheduler or queue race. Real mail globally blocked; no checkout, orders or payments.

Runtime WP 7.1.5 / WC 11.3.1 / PHP 8.3 / MariaDB. No production changes, package uploads or submitted Free changes. CI pending.

## Reproduced defects and proposed repair

Run 45773768702: export boundary passed (2001 rows in 6.420s, JSON 2000 in 0.625s, CSV 2000 in 0.670s; peak PHP allocation 154996736 bytes). Two native concurrency assertions failed: 2 captured deliveries for the same queue; new ID 3 disappeared while an in-flight sender held a stale option snapshot. This is a Pro production defect, not a Free defect.

Fix in this PR: per-site unique-option sender lease (900 seconds, owner-checked release), uncached queue reads and atomic compare-and-swap mutations so concurrent queue appends are not overwritten, fresh cron option check, uninstall removes lease. Bounded retries expose storage failure through the existing failure option and a diagnostic action. The queue still caps at 50 events. Lease expiry or process crash after SMTP acceptance cannot guarantee exactly-once delivery; no such claim. Real mail remains intercepted. Fresh native and Playground CI required. Previous Pro ZIP must be rebuilt after merge; submitted Free package untouched. Version 1.0.0 retained as pre-submission candidate.
