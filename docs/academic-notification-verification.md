# Academic Notifications: Local Verification

Date: 2026-10-08. The human approved the concrete proposal through Continue. The accepted collaborator Notifications screen is the presentation baseline.

## Delivered behavior

Activity publication creates notices for currently eligible active students in the exact academic scope. Accepted/updated deliveries notify the original teacher; initial assessments and corrections notify the owning student. Events and academic writes commit together. Existing records and later enrollments are not backfilled.

Each authenticated account consults its own generic feed, full unread count and newest-first pages of at most 50 notices. Section shortcuts re-enter existing screens, which independently authorize courses and resources. The feed contains no answers, evidence filenames, feedback, student names or grade values. Refresh is explicit. Mark all as read requires CSRF and uses the latest own notice ID observed during consultation; later arrivals remain unread. An uncertain receipt cannot automatically replay.

Migration 000014 adds retained notifications with immutable event fields, recipient/operation uniqueness and one-way read timestamps. Development schema: 25 tables / 14 migrations. Runtime receives only the required insert columns and read_at update privilege; no delete or schema privileges. The isolated rollback trigger is removed in finally before browser verification.

## Executed evidence

- Frontend: 115 tests passed, including five notification contract/transport cases. TypeScript checks and Vite build passed. No initial missing-feature RED or universal test-first history is claimed.
- Actual MySQL: 52 checks passed. Dedicated synthetic identities and academic assignments verify exact recipients, inactive accounts, no late-enrollment backfill, original teacher routing after replacement, assessment/correction events, own read boundaries, later arrivals, repeat receipts, private projections, 50-row pagination, transfer retention, credential revocation and protected persistence. Injected notification failures revert publication, delivery/evidence and grading. An internal synthetic duplicate-recipient probe emits only one row; it is not a public recipient-selection API.
- Actual Chromium: 19 checks passed through real teacher/student publication, delivery, grading/correction, notice shortcuts, receipt persistence, invalid recipient selection, cross-account receipt denial and missing-CSRF denial. Existing grading authorization/history checks are included in those 19. The 768-pixel notification screen has no horizontal overflow and its screenshot was visually inspected.
- U11 regression: 99 tests / 2397 assertions, two optional skips, no failures. Existing assessment regression: 56 MySQL checks and 11 browser checks passed. Existing activity regression: 45 MySQL checks and 12 browser checks passed. Runtime helpers: 20 tests passed. PHP/Node syntax and git diff --check passed. One attempted concurrent verification was refused by the operation lock; it did not run or change data and was retried sequentially.

The supported command is backend/scripts/manage-dev.ps1 -Command verify-notifications. BrowserOnly skips creation of additional server fixtures. Private screenshots, diagnostic files and verification receipts remain ignored. Synthetic verification records are retained development data, not school enrollment.

Final runtime snapshot: TLS and restricted runtime confirmed, 25 tables, 14 migrations, 159 accounts, 10 periods, 57 assignments, 164 enrollments, 3 materials and write ordinal 826. These aggregate values remained identical across owned app stop/start. Counts include retained synthetic verification data.

## Remaining acceptance

Material notices, welcome messages, broadcasts, deadlines, automatic refresh and external messaging remain outside this unit. A simultaneous browser later-arrival race and closed-period notification fixture were not separately exercised; the server boundary test covers later arrivals, and the feed has no current-period filter. Institutional LAN/outage, load, durable database relocation and backup/restore acceptance remain open. Changes are local; no remote Git mutation or new contributor handoff occurred.
