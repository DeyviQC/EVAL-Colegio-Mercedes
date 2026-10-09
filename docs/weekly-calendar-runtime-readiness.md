# Calendar Runtime Maintenance Readiness

Date: 2026-10-08. Local runtime calendar maintenance and verification completed after explicit human authorization.

Prepared calendar-maintenance.php for the existing owned eval_dev binding and migration role. It applies only migration 000018 using the migration repository and grants calendar-specific SELECT/INSERT and bounded UPDATE columns to eval_dev_runtime. Published revisions receive no UPDATE/DELETE grant; the helper creates no accounts, resets no database and seeds no content. It must run through a reviewed integration with the existing manage-dev wrapper, not by exposing decrypted credentials.

Prepared verify-calendar.mjs for the existing pinned-certificate Chromium and stdin-only local passwords. It exercises the actual management UI and checks publication persistence and student denial on a new, synthetic planned period. It retains that test fixture and does not publish over any existing calendar. This fixture confirmation is a simulated verification action, not approval of the school's PAT. The verifier must only run once local schema/grants are approved and applied.

Automatic approval review rejected the proposed combined wrapper integration and calendar-maintenance execution: persistent shared eval_dev schema/grant changes were considered outside the U1-only AGENTS.md exception. That command did not execute; manage-dev.ps1 was not changed. No alternate maintenance route was attempted. Explicit scope clarification is required for this local non-U1 maintenance before retrying.

Static validation: both helper files are syntax-checked using the existing PHP/Node tooling. Real runtime migration, grants, browser execution and operational acceptance remain pending. Existing backend isolated tests and synthetic React browser checks remain evidence only for their previously recorded scopes.

## Authorized execution

The human explicitly authorized the requested local non-U1 migration, minimum calendar grants and synthetic-period verification with "si autorizo continua". The existing manage-dev wrapper now exposes calendar-maintenance (requiring -Maintenance) and verify-calendar. Ownership, private credentials and MySQL binding checks remain in the wrapper; credentials are never printed.

`./backend/scripts/manage-dev.ps1 -Command calendar-maintenance -Maintenance` succeeded: migration 000018 applied to eval_dev, calendar-only DML grants installed, no reset or reseed. Automatic approval review accepted this explicitly authorized execution.

`./backend/scripts/manage-dev.ps1 -Command verify-calendar` succeeded with five assertions through the actual HTTPS session, React UI and runtime database role: create synthetic planned period, save a draft, publish and retrieve 36 teaching weeks, deny calendar administration to the student, and retain the period's planned state. Fixture period ID 17 is retained with source "Synthetic verification only; not school PAT". No existing calendar was changed. The verifier tested local management behavior; it did not certify an institutional PAT, regional adjustments, production operation or course-resource weekly associations.

The earlier pending-runtime statement above records the pre-authorization checkpoint and is superseded by these execution results. Physical-device acceptance and institutional calendar evidence remain open. Course material/task association and student weekly discovery still require their own implementation and verification. No commit, push or remote operation was performed.

Subsequent integration completed association and student weekly discovery; see weekly-course-discovery-verification.md. Migration 000019 and association-only runtime grants are applied, and the runtime inspector recognizes the resulting 38-table/19-migration schema. The school PAT and physical acceptance remain open.
