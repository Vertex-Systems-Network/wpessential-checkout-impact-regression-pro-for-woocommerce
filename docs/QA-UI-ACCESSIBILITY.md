# Browser accessibility, RTL and responsive QA

DEV-08: automated axe-core WCAG A/AA checks on Alerts and Reports at 1440 and 390px, keyboard tab focus/selection in LTR and RTL. Automated checks are not a complete WCAG conformance audit (manual screen reader, focus visibility and zoom remain).
DEV-09: real WordPress Arabic core locale installed, separate admin with locale ar, verify actual HTML lang and computed RTL direction. Plugin translations are not assumed; this tests layout and keyboard behavior, not translation completeness.
DEV-10: both flows at 1440, 1024, 768, 390, 320px, validate app and control viewport bounds and horizontal overflow. Nested report/table scroll regions allowed. No screenshot artifacts or plugin package uploads.

Disposable MariaDB store; same seeded fixture, mail blocked. Free source pinned to submitted version. No production source changes. CI pending; findings fail the test so they cannot be mistaken for a pass.

## Findings and paired fix

Run 45773934964 failed because shared Free admin CSS used physical negative left margin (20px desktop, 10px mobile overflow in Arabic), horizontal keys ignored RTL direction, and the report scroll region lacked keyboard access. Free PR #12 (015f2cee113bb08cd9c6e4d8add2180e94336865) fixes those defects. This workflow now pins that exact candidate, rather than the submitted version. Submitted Free package is unchanged; owner must coordinate replacement with reviewer. Rerun pending. No WCAG conformance claim.
