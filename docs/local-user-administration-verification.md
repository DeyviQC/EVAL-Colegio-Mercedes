# Local User Administration: Verification

Date: 2026-10-08 (America/Lima). Real persistent eval_dev application, not the synthetic preview.

## Delivered behavior

Director/Admin sees Users and can create teacher/student accounts with a display name, normalized unique username and server-generated 20-character initial password. The password is shown only in its confirmed result and disappears when dismissed or navigating away; directory responses never expose hashes or passwords. Directory supports exact username search and keyset pages of 50, including inactive accounts. Account creation is independent of enrollment and assignment.

Director/Admin can reset passwords, deactivate or reactivate a single-role teacher/student account using explicit confirmation. Existing director/vice-principal accounts cannot be maintained through these endpoints. Each authenticated role has Mi cuenta and can change its own password by supplying the current password and matching new-password confirmation. Password limits are 12–72 UTF-8 bytes; hashing uses bcrypt cost 12. Prior password sessions and pre-deactivation sessions are rejected, including dormant sessions first used after reactivation. Reactivation preserves the current password and historical records.

Account writers participate in the existing guard and retained identity lock order and re-read active director/credential authority before mutation. Creation and its separate audit event commit together. local_account_events stores only actor, target, operation, ordinal and timestamp. Existing academic lifecycle schemas remain unchanged. No runtime role/login update, deletion or DDL privilege is added. The additive migration and column grants are prepared only in eval_dev; older isolated prerequisite databases retain their previous schema and grant boundaries.

Client mutations are never automatically replayed. An uncertain mutation blocks further account writes across views until explicit result review. Lost initial-password acknowledgement requires directory inspection and an explicit reset; it cannot recover a previously generated secret. A confirmed operation whose subsequent directory refresh fails is reported as confirmed. Generated passwords do not enter verification output or screenshots.

## Executed checks

- Browser test initially failed because Users did not exist; prior launcher configuration failure is not counted as functional RED. Two accessible-label selector issues were corrected while implementing the screen.
- Server verification: **26 checks**, actual runtime account, all three non-director creation denials, invalid username/admin-role rejection, protected director denial, duplicate normalization, password hashing, no implicit course, invalid-current-password denial, stale actor rejection after deactivation/reactivation, denied audit deletion/login update/role update and secret-free audit columns.
- Browser verification: **15 checks**, actual creation/sign-in/password change/reset/status maintenance, negative student access, dormant session denial after reactivation, tablet viewport without overflow, plus newly created teacher assignment, student enrollment, teacher publication and byte-identical student PDF download.
- Frontend: **94 tests passed**, strict application/tooling TypeScript checks and Vite build passed. New client tests cover credential leakage rejection, CSRF, confirmations and uncertainty blocking across reads.
- Existing U11 backend: **99 tests / 2397 assertions**, two optional preview/browser tests skipped, no failures. Existing Auth suite: **24 tests / 127 assertions**, no failures.
- Existing persistent browser journeys after account integration: materials **12**, enrollments **7**, assignments **12**, all pass; main math course and original student remain retained.
- Snapshot confirms restricted TLS runtime, **20 tables / 11 migrations**. Verification accounts, course fixtures and files are retained as clearly named development data, not institutional accounts. Failed intermediate browser runs can leave confirmed fixture records; tests do not delete retained history.
- Owned app stop/start preserves identical snapshots: 13 accounts, 9 assignments, 13 enrollments, 3 materials and ordinal 80. EVAL reopened and authenticated as Director/Admin after the restart.

Teacher/student accounts can now be created without SQL or seed scripts. The professor-approved demo remains the screen baseline; this unit does not implement wider administrator maintenance, activity content/submissions, assessment, reports, library or notifications. Global deployment, durable database-location migration, backup/restore, school LAN/outage and load gates remain open. Exhaustive concurrent fault injection for account writers has not been run; the existing transaction protocol regression suite passes. Changes are local and uncommitted; no remote Git mutation was performed.
