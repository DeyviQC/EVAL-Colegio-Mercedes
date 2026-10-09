# Weekly Calendar Validation Verification

Date: 2026-10-08. Reviewed starting HEAD: 91a50a3. Human approvals: architecture "aprobado todo continua" and calendar-management prototype "si aprobadocontinua". Existing prototype/specification work was already present in the workspace and is retained.

## Completed bounded unit

SchoolCalendarPlan validates an ordered calendar against an existing DeclaredDateRange. Teaching weeks have consecutive integer numbers; management blocks contain no teaching weeks. Inclusive intervals cannot overlap, weeks must fit their block, blocks must fit the parent period, and malformed/unexpected fields are rejected. Short institutional weeks and years other than 2026 are permitted. The validated plan is a readonly snapshot. No assumed national 36-week invariant or instructional-day compliance certification is encoded.

The new suite uses the existing PHP/PHPUnit runtime through backend/scripts/local.ps1 in PHP mode, with backend/phpunit.calendar.xml and the existing bootstrap. No database credentials, migrations, running application sessions or external network are needed by this pure domain suite. Do not use the database unit wrapper with an unsupported Calendar unit name.

Command from repository root:

```powershell
./backend/scripts/local.ps1 -Command php -Arguments @("$env:LOCALAPPDATA/Temp/opencode/eval-u1/vendor/phpunit/phpunit/phpunit", '--configuration', 'backend/phpunit.calendar.xml')
```

Test-first evidence: the initial 20-case run failed because SchoolCalendarPlan did not yet exist; after implementation those cases passed. Additional containment/list/management-only and immutable-snapshot cases were added for final verification. See final result below.

Final result: 24 tests / 28 assertions passed on PHP 8.4.26 and PHPUnit 12.5.38. `git diff --check` passed. The suite does not exercise database writes, authorization or browser integration; those require the remaining application units.

## Remaining delivery

This unit is validation infrastructure, not an operative calendar feature. Persistence, publication source/confirmation, retained revisions, authorization, stale-write/unknown-outcome handling, HTTP contracts, frontend integration and content associations remain unimplemented. No school calendar has been confirmed or published. No database or existing academic behavior was changed.

The sequential handoff requirement remains applicable before another contributor starts an application unit. No new contributor was started and no commit/remote action is performed by this receipt. New-unit local commit authorization is not inferred from the earlier reconciliation-only permission.
