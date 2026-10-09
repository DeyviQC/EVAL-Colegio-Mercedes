# Weekly Calendar Persistence Verification

Date: 2026-10-08. Starting HEAD: 91a50a3. Human-approved calendar requirements, architecture and management preview. No parallel contributor, commit, remote action or application-database migration was performed.

## Implemented unit

SchoolCalendarCommands creates and edits institutional drafts, publishes confirmed revisions and consults current/retained revisions. Writes use the existing academic write guard, parent-period and actor locks, refreshed Director/Admin authority, stale-draft version checks and the existing unknown-outcome transaction handling. Calendar changes do not activate or close academic periods.

Published snapshots receive stable random week identifiers and remain immutable through database triggers. The current pointer references a revision from the same period. Drafts retain their base published revision; publication from a stale base is rejected instead of replacing a newer calendar. Source reference and explicit true confirmation are required. Closed periods retain authorized management consultation but deny writes. These are confirmations supplied by a responsible user, not automatic verification of a school's PAT.

The additive migration creates school_calendar_drafts, school_calendar_revisions and school_calendar_states. It was applied only by the feature-test fixture to the existing isolated eval_u11_test database, without a fresh rebuild, database reset or changes to eval_dev. It is not added to an existing U1–U11 setup allowlist or to automatic application startup. Forward-only retention is intentional.

## HTTP surface

All routes enter through existing LocalSessionAuthentication composition:

| Method | Path under /academic | Behavior |
|---|---|---|
| GET | /periods/{id}/calendar | Current published revision, or null |
| GET | /periods/{id}/calendar-drafts?after={id} | Director-only paginated draft consultation |
| POST | /periods/{id}/calendar-drafts | Create with blocks |
| GET | /calendar-drafts/{id} | Consult draft and its version/state |
| POST | /calendar-drafts/{id}/edit | Replace validated plan with expected_version |
| POST | /calendar-drafts/{id}/publish | Publish with expected_version, source, confirmed=true |
| GET | /calendar-revisions/{id} | Consult retained publication |

Controller responses use the existing data/error envelopes and private/no-store caching. Reject malformed JSON, unexpected query parameters, uploaded files and oversized mutation bodies. Student/teacher course calendar consultation remains a separate integration unit; the management endpoints intentionally grant neither role draft access.

## Test scope and privileges

Test-first run: eight later feature cases started as five failing cases because the migration/service did not yet exist. Final targeted result: 8 tests / 116 assertions passed, including revision retention, stale edits, competing draft publication, role denial, closure, SQL immutability, malformed HTTP input and validation rollback. Domain validation remains 24 tests / 28 assertions passed. Diff whitespace checks passed.

The tests use the existing runtime connection for academic fixture commands, and the migration-role connection for calendar persistence/controller operations. This verifies real MySQL persistence and actor authorization semantics, but does not verify least-privilege runtime DML grants or production/session HTTP journeys. No grant was broadened. Runtime migration/grants and real browser acceptance must be completed through separately reviewed local maintenance before claiming the feature works in the running application.

Commands:

```powershell
./backend/scripts/local.ps1 -Command test -Unit U11 -Arguments @('--filter=SchoolCalendarPersistenceTest')
./backend/scripts/local.ps1 -Command test -Unit U11
```

The feature fixture creates the additive tables only when absent. An interrupted partial fixture migration must be diagnosed and repaired explicitly; it must not trigger a database reset.

Full U11 regression completed: 107 tests / 2513 assertions, two skips; no failures. The two existing skips do not certify their unavailable scenarios. This regression includes the eight calendar cases and preserves the existing academic behavior checks.

## Remaining delivery and calendar evidence

Real frontend management client/UI, uncertain-create consultation UX, runtime schema/grants, authorized course consultation, content associations and weekly resource discovery remain pending. No institutional calendar has been adopted. Current academic period date bounds may end before the national final management block; confirm approved institutional period/calendar dates instead of silently extending a period to fit the template. Calendar validation enforces the existing parent bounds.

The sequential handoff rule remains applicable before a new contributor starts; the earlier reconciliation-only commit permission is not generalized to this new unit.
