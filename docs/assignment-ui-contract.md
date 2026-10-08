# EVAL assignment management UI

Date: 2026-10-08. Human requested the next real application slice: assignment planning, activation, closure and teacher replacement. Existing approved domain commands/policies are reused without temporal or ownership changes.

GET `/academic/assignment-workspace?kind=...` provides only assignment-duty discovery to authenticated Director/Admin and Vice Principal identities with current credential revisions and active stored grants. Kinds are assignments, teachers, periods, entries, grades and sections; only an optional canonical `after` is accepted. Pages are numeric-ID keysets of at most 50 items. Each assignment target is additionally revalidated through `assignment.read`. Retained teacher labels, original scopes, declared dates/state and replacement lineage are kept; no credentials, equality keys or student records are exposed. Missing teacher names fail closed.

Creation options are purpose-bound: planned/active periods, active catalog references and active retained identities with stored teacher grants. This does not grant Vice Principal general period/catalog/enrollment collections or any catalog/period administration. Existing individual assignment support remains unchanged. No institutional name/import source or account-management module is introduced.

The UI creates planned assignments first. Activation, closure and replacement each require explicit confirmation; closure accepts an explicit date, replacement uses the approved immediate default and supplies only the new teacher ID. Server commands enforce dates, parent states, conflicts, locks, lineage and author/history retention. Client action visibility is presentation only. Lost acknowledgements never replay and suspend that client's workspace mutations across navigation. Changed records beyond page one are fetched through the same restricted collection.

The new unit does not close OpenSpec network/load/institutional acceptance gates or implement activities, submissions, grading, supervision or user administration.

## Verification receipt

Frontend: 90 tests passed; strict application/tooling typecheck and build passed. The isolated management-directory PHPUnit case passed with 236 assertions, including Director/Vice Principal access, teacher/student/anonymous denial, missing retained names, stale credentials, query tampering and unchanged Vice Principal general-collection denials. Full isolated U11: 99 tests / 2,397 assertions, zero failures/errors and two explicit optional browser/human-preview skips.

Live `eval_dev` Chromium: 12 checks passed for Director planning/cancelled activation/activation, Vice Principal discovery/replacement/successor closure, retained original teacher context/lineage, unchanged main Mathematics assignment, teacher/student denial and 768px no-horizontal-overflow. Private fixture preparation created two named retained teacher identities without login credentials; a dedicated verification subject and its closed assignment history remain synthetic development data. The original teacher, student and course material data were not replaced or closed.

Afterward, the existing materials journey passed all 12 checks and the enrollment journey passed all seven checks. Headful EVAL opening authenticated as Vice Principal. Screenshots and receipts remain private in `.local/eval-dev/`; opening is automated evidence, not human acceptance. No commit or remote operation occurred.
