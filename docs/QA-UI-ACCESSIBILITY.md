# Browser accessibility, RTL and responsive QA

DEV-08: automated axe-core WCAG A/AA checks on Alerts and Reports at 1440 and 390px, keyboard tab focus/selection in LTR and RTL. Automated checks are not a complete WCAG conformance audit (manual screen reader, focus visibility and zoom remain).
DEV-09: real WordPress Arabic core locale installed, separate admin with locale ar, verify actual HTML lang and computed RTL direction. Plugin translations are not assumed; this tests layout and keyboard behavior, not translation completeness.
DEV-10: both flows at 1440, 1024, 768, 390, 320px, validate app and control viewport bounds and horizontal overflow. Nested report/table scroll regions allowed. No screenshot artifacts or plugin package uploads.

Disposable MariaDB store; same seeded fixture, mail blocked. Free source pinned to submitted version. No production source changes. CI pending; findings fail the test so they cannot be mistaken for a pass.
