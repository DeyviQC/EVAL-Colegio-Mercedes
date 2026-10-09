# Delivery Assessment: Local Verification

Date: 2026-10-08. Human approval: Continue after the concrete proposal for AD/A/B/C, required feedback, locked assessed deliveries and retained corrections.

## Real application behavior

The original teacher grades a received delivery in Deliveries using AD, A, B or C and required feedback. The student consults their own grade, feedback and assessed version in My grades. The original teacher can correct the grade or feedback during the active period; each immutable revision remains accessible to that teacher and owning student. First assessment blocks student updates. Assignment replacement and activity closure preserve original teacher authority.

Migration 000013 adds immutable assessment revisions, constrained delivery/version relationships, a constrained current assessment pointer, feedback constraint and restrictive triggers. The existing guard transaction serializes assessment/correction with evidence updates, checks expected version/revision and commits the revision, pointer and resource-ID-only lifecycle event atomically. Public projections and action flags enforce server authority. The client blocks automatic replay after lost/malformed acknowledgements.

## Executed evidence

- Initial contract/compile RED exposed the absent decoder, fields and operation. No historical browser/database RED or universal test-first claim is made.
- Frontend: 104 passing tests; application/tooling TypeScript checks and final Vite build pass.
- Actual MySQL assessment verifier: 56 passing checks. Invalid grades/feedback/authority inputs, unauthorized roles, stale version/revision, assessed-update denial, immutable corrections, private history, unrelated-student denial, replacement/closure retaining original teacher authority and student history after transfer are covered.
- A narrowly conditioned insert-failure trigger proves rollback leaves revision count, current pointer, versions, lifecycle ledger and ordinal unchanged; finally cleanup removes the trigger before browser checks.
- Two independent PHP/MySQL correction connections produce one commit and one stale-revision conflict. Assessment versus evidence-update connections produce a valid serialized outcome: either assessment of the reviewed version with update denial, or an update with stale-version assessment denial. No contention-duration or load claim is made.
- Actual Chromium assessment journey: 11 passing checks for publication/delivery, teacher grading, student consultation, correction, retained prior feedback, absent update form, direct assessed-update denial, student assessment denial, management history denial and tablet-width overflow. Receipt and screenshot are private and ignored.
- Activities/Deliveries regression: 45 runtime and 12 browser checks pass, including original evidence bytes and updates before assessment.
- Existing U11: 99 tests / 2397 assertions, two optional skips, no failures. Runtime helpers: 20 passing tests after browser verification released its operation lock.
- PHP/Node syntax checks and git diff --check pass.

Earlier browser failures exposed a load wait, incorrectly encoded selector label and prefilled feedback label; these were corrected with explicit accessible labels. An update-history remount race was corrected by showing delivery confirmation after the refreshed list. Earlier helper attempts were denied by sandbox process spawning or the operation lock; only the final supported passing runs are counted.

## Runtime and remaining acceptance

The first migration attempt was not executed because automatic approval review reached its usage limit. The next human Continue triggered a retry through that same review, which succeeded. No bypass was used.

Verified schema: 24 tables / 13 migrations, TLS and restricted runtime grants. Before the follow-up closed-period fixtures, an owned stop/start preserved identical snapshots: 41 accounts, 7 periods, 42 assignments, 44 enrollments, 3 materials and ordinal 411. This snapshot does not count assessment/activity/version rows; dedicated server checks verify them separately. Teacher and student browsers were opened and authenticated after restart.

Synthetic confirmed verification data remains retained; interrupted browser runs can leave valid activities or deliveries. No reset, institutional data deletion or remote mutation occurred. Predecessor-schema reads remain supported for staged upgrades, returning null assessment and false can_assess before migration.

Follow-up verification constructs a named, relationally complete historical closed-period fixture under the write guard, leaving the institution's active period unchanged. Correction is denied without mutation, can_assess is false and retained feedback remains readable. This is synthetic fixture construction, not a normal admission/period-transition journey. Lost-commit server fault injection, school LAN/outage, load and backup/restore acceptance are not claimed. Reports, notifications and wider modules remain separately scoped. Changes are local and uncommitted.
