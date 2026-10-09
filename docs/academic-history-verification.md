# Academic History Verification

Date: 2026-10-08. The human explicitly requested integration of the accepted Historial / Mi historial screen. Existing academic-authorization specifications define the role boundaries; this unit implements discovery and presentation of enrollment and assignment context only.

## Delivered behavior

Director reads enrollments and assignments; vice principal reads assignments only; teacher reads own assignments; student reads own enrollments. The server checks current credential revision, active identity and stored role, and applies owner filtering before pagination. Administrative projections reuse the existing authorized directories. Client identity filters are rejected. No schema, grant, academic mutation, data backfill or file authority was added.

The screen shows retained owner/scope labels, dates, state, period status for assignments, and replacement predecessor references. Active records remain visible alongside prior records. Pages contain at most 50 ascending records and replace the visible page; explicit consultation returns to the first page. Server failure does not become an empty successful result. No mutation controls are included. Submission context detail remains available through its existing endpoint but is not integrated into this bounded screen.

## Executed evidence

- 118 frontend tests passed, including three new history transport/denial cases and existing exact enrollment/assignment projection tests. Application/tooling TypeScript and Vite build passed.
- 26 real Chromium checks passed across all four roles. They cover authenticated screen entry, real database rows, owner IDs compared with the current account, role-specific selector choices, direct opposite-role endpoint denials, rejected client identity filter and malformed cursor, 50-row pagination with disjoint pages, closed assignments/closed periods/replacement references retained, unchanged repeated reads and a 768-pixel overflow check. The student screenshot was visually inspected. The initial browser run exposed an inaccessible selector label; it was corrected and the complete final journey passed.
- Before/after development snapshots matched: 25 tables, 14 migrations, 159 accounts, 10 periods, 57 assignments, 164 enrollments, 3 materials, write ordinal 826. The verifier creates no accounts or academic records.
- Existing U11 regression passed: 99 tests / 2397 assertions, two optional skips. Git whitespace checks and relevant PHP and Node syntax checks passed. Private screenshots and receipts remain ignored.

Supported verification: backend/scripts/manage-dev.ps1 -Command verify-history. This performs the read-only browser/HTTP journey against the actual development database.

## Remaining acceptance

The unit does not claim a new revoked-session browser fixture, simultaneous-writer snapshot guarantees, full student transfer-history fixture, full submission-context screen, search/export, physical LAN/outage, load or institutional deployment acceptance. The existing credential revision guard remains applied. Database durability, backup/restore and broader MVP gates remain open. No remote Git action is included.
