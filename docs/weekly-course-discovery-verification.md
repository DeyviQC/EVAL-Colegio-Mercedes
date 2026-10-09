# Weekly Course Discovery Verification

Date: 2026-10-08. Weekly association and course discovery are implemented in the local EVAL adapter and React interface. The same contributor continued sequentially after the verified association backend; no parallel contributor, commit, push or remote operation occurred.

## Delivered behavior

Mis cursos → select a course → Semanas provides direct dated week navigation, teaching-block filtering and content-only filtering. Sin semana retains general and existing resources. Calendarios anteriores and retained revision week controls preserve discovery after calendar correction. Existing material/library/activity/delivery views remain available. Weekly activity opening uses the existing ActivityCard and activity client, preserving submission routing and explicit closure behavior; material downloads use the existing protected file route.

CourseWeeksQuery resolves the retained academic assignment through MyCoursesQuery, then reuses CourseMaterials and ActivityDelivery consultation eligibility for each candidate before including it in contents, retained calendars or counts. Material lineage remains separate from the original activity assignment; the client does not equate IDs from different course APIs. Material and activity cursors and full authorized totals are independent. Publication or delivery commands are never replayed to retry week organization. Original teacher organization controls appear only for resources in the active current assignment; the backend independently enforces original ownership and active context.

Dedicated association contracts validate resource, assignment, period, kind, association version and calendar/week identities. Unknown writes block further association mutations until the exact affected resource is consulted and the user explicitly reviews the result. Unrelated or pre-mutation consultation cannot authorize review. Stale version/calendar failures require consultation without automatic replay. Denial clears weekly content; session expiry invokes existing session invalidation.

## Verification

- Focused MySQL tests: 13 tests, 234 assertions in eval_u11_test, covering associations plus discovery, independent pagination/full totals, earlier-calendar visibility and historical student counts excluding publications after transfer.
- Full U11 regression: 120 tests, 2747 assertions, two existing skips. Test fixtures use migration credentials for new calendar/association tables and education content; these results do not certify runtime grants by themselves.
- Frontend: 170 contract/UI tests passed; type checking and production build passed.
- Built React synthetic Chromium journey: 15 checks passed, two association writes, zero external requests. Covers navigation, week selection and focus, organization, activity detail, lost acknowledgement consultation/manual review, earlier-calendar content, filtering and clearing on denied access. Widths 1024/768/390 have no horizontal page overflow; mobile screenshot visually inspected. Evidence is in the local temporary eval-weeks-react-evidence directory.
- Actual eval_dev maintenance: migration 000019 and only association SELECT/INSERT and pointer UPDATE(change_id) grants applied through the owned manage-dev wrapper after approved weekly architecture, explicit local non-U1 maintenance authorization and the human's continuation request. No reset/reseed or calendar replacement occurred. Published association histories have no UPDATE/DELETE runtime grants.
- Actual HTTPS/React/runtime-role journey: ten checks passed. Teacher created synthetic activity 152 in existing development assignment 1; teacher and student found it through Sin semana. A dedicated clear command created retained association version 1; student consultation succeeded, student mutation returned 403, history contained one record, and course calendar projection was unchanged. This retained fixture is explicitly synthetic and is not student work.
- Runtime snapshot passed: eval_dev, TLS enabled, restricted runtime grants, 38 tables and 19 migrations. The startup inspector recognizes calendar and association migration counts while retaining its forbidden privilege check. PowerShell parsing, PHP maintenance syntax, Node verifier syntax and diff whitespace checks passed.

## Operational boundaries

Real assignment to a non-null week is verified in isolated MySQL and synthetic browser journeys. The real-session verifier deliberately leaves existing calendars unchanged: it does not publish a synthetic calendar over a real development course merely to exercise this path. A course without a published calendar therefore shows its explicit not-configured message and Sin semana. Direction must publish the institutionally approved calendar before real teaching-week organization is used.

The school's approved PAT and DRE/UGEL adjustments remain unverified; the national 2026 template is only a draft reference. Physical LAN/tablet acceptance, load testing and production deployment are not certified. Current-week detection and pending-delivery shortcuts remain deferred as approved. Weekly discovery reuses existing MyCourses admission; it does not broaden course admission for historical cases that that directory does not already expose.

## Local operation

The existing prepared runtime supports these commands from the repository root:

```powershell
./backend/scripts/manage-dev.ps1 -Command calendar-maintenance -Maintenance
./backend/scripts/manage-dev.ps1 -Command resource-week-maintenance -Maintenance
./backend/scripts/manage-dev.ps1 -Command snapshot
./backend/scripts/manage-dev.ps1 -Command verify-weeks
```

Maintenance helpers use the existing migration repository and minimum runtime grants; they do not prepare accounts or seed the school. verify-weeks creates and retains a synthetic activity, so repeated execution intentionally creates another verification fixture. It never changes the existing calendar. Existing runtime start/stop commands continue to use ownership checks and the updated inspector. These commands describe local development operation, not institutional deployment authorization.

## Final operation review

The subsequent operation review removed PHP integer narrowing from the weekly cursor comparison and reused already resolved retained revisions within one calendar consultation. Cursor ordering now uses canonical decimal-string length and lexical comparison, matching the existing lock-set ID ordering. The focused suite passed again: 13 tests and 237 assertions, including an unsigned BIGINT maximum cursor returning no items while retaining the full authorized total. No schema or shared-data change was needed for this correction.

[weekly-course-operation-guide.md](weekly-course-operation-guide.md) records the actual Direction/teacher/student paths and a separate pending physical LAN/tablet acceptance procedure. The guide does not claim institutional PAT confirmation, remote access configuration or production deployment.

The human subsequently accepted the delivered local functionality with "apruebo todo continua". A bounded, read-only local workload passed with 24 requests and matching academic snapshots; see weekly-local-read-load-verification.md. This adds local read-load evidence without certifying school-wide capacity or physical network acceptance.
