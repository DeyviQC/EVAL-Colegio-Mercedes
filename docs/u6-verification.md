# U6 Verification and Local Handoff

Date: 2026-10-07. Predecessor: a44bb8f8f443d01e200ca6b402d483969d48d14e. Human enrollment eligibility amendment: 6262d4faedb62e42ce0e554eb5f13753eddd4dc6.

## Scope and approved behavior

The sequential authorization and applicable prerequisites are in docs/u6-readiness.md. This handoff completes tasks 4.3/4.4: immediate, atomic, successful server-confirmed student transfer. New active successors require an active requested parent under the explicit human decision. No teacher/assignment lifecycle, public HTTP endpoint, submission-reference table, general student-visibility policy, deferred module or integration of 2e5d053 is included.

## Implemented transfer

TransferStudent accepts a prior enrollment locator and destination grade/section locators only. It rejects scheduling, retroactive/correction, clock/key, student/period or recipient/teacher selectors as unsupported input. Student/period are reloaded from the retained prior row, not substituted from request fields or a different current period.

U3 acquires the guard before discovery, then complete canonical period/old-new catalog/shared actor-student/history unions. Current credentials and Director/Administrator grant are revalidated under locks. Source must be active; parent must be active; destination must be active/matching and different; declared start cannot exceed actual server school date. The prospective candidate interval checks all other occupied history, excluding only the prior ending at the same K.

One mutation marks prior transferred with declared end on actual America/Lima operation date and operational end K, creates a distinct active successor with start on that date and operational start K, and appends two typed events with the same K/correlation. Prior identity/student/period/grade/section/declared start/operational start are retained. Previous declared-end evidence and predecessor/successor IDs are captured in ledger metadata. Different teachers/assignment owners and routes are not written by this command.

U3 commits before the service passes the actual boundary to U1's confirmedEffectiveBoundary and returns decimal-string prior/successor/key plus server date. An uncertain commit throws with correlation before confirmation; no automatic replay exists. Same-day intervals remain nonempty because ordinal K1 < K2 < K3, even if declared end/start dates are equal. Current active source after successful commit is the successor; role-bounded student access/new-work APIs remain U9 and must revalidate parent/resources.

## Runtime and checks

Observed PHP 8.4.26 / Illuminate 13.35.0 / PHPUnit 12.5.38 / MySQL 8.4.9. eval_u6_test contains the existing 15 academic/auth prerequisite tables; verified TLS_AES_256_GCM_SHA384 to owned 127.0.0.1:3307. No new packages, institution data or production credentials are used. Database bootstrap/migration/grant preparation uses the same selected-unit two-step flow as U5. Fresh rebuild is allowlisted only for the matching disposable unit database.

| Repository-supported check | Result |
|---|---|
| backend/scripts/local.ps1 -Command test -Unit U6 | 22 tests / 291 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U5 | 21 tests / 307 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U4 | 26 tests / 323 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit Auth | 24 tests / 127 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U1 | 43 tests / 150 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U2 | 27 tests / 182 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U3 | 23 tests / 154 assertions, pass |
| backend/scripts/local.ps1 -Command db-smoke -Unit U6 | MySQL 8.4.9 / 15 tables / verified TLS |
| PHP -l / git diff --check | Pass |

Total across these seven isolated suites: 186 tests / 1,534 assertions. No full Laravel HTTP application, live browser/LAN outage, contract/load acceptance or persisted original submission route is claimed. U10/U11 and task 7.5 still provide actual accepted-context persistence integration; U6 proves stable prior references and absence of route/owner mutation, not a fabricated submission test.

## Evidence and honest test-first limits

Four empty transfer stubs produced four failures/sixteen assertions (missing successor, same-scope allowed, timing fields accepted, missing same-day successor). Implementation then passed four tests/fifty-one assertions. Later negative/race/fault tests were added afterward; no historical RED is reconstructed for them.

The suite proves shared actual K/date/correlation, stable prior scope/start, same-day nonempty histories, current active successor scope, terminal-source denial, planned/closed parent denial, inactive/missing/mismatched destination denial, forbidden selectors, no end before declared start, current role/credential checks, and reversed grade/section ID lock unions with shared identities locked once. Prior inactive catalog may be left for a valid active destination; historical row remains retained. A planned-parent legacy fixture is explicitly an inconsistent maintenance fixture, not a permitted reversed period transition.

Real MySQL triggers inject successor-insert and second-event failures; prior/successor/ordinal/events roll back together and no confirmation is returned. Triggers are removed in finally. The second event fails after the first has actually been appended in the transaction. All meaningful retained interval comparisons use actual historical keys, not guessed midnight/date keys.

An injected 1213/40001 after prior update proves confirmed rollback then whole-transfer retry: original state/K return, fresh discovery validates again and only one final successor/K commits. Revoking server role between rollback and retry denies with no changes, proving cached role snapshots do not authorize retries. The test fault adapter initially mismatched installed MySqlConnection::insert's third argument; it was corrected from the installed source. A fatal test process left a synthetic active fixture; subsequent existing fixture cleanup restored the harness. Final complete runs pass.

Another test connection performs a real commit then injects lost acknowledgement. Exactly one transfer/K persists, the error correlation matches ledger evidence, and the service never confirms/replays. These are deterministic injected faults, not claimed real network outages/deadlocks.

Three actual two-process command races use query-event/file barriers and SHOW PROCESSLIST to prove guard waiting: competing transfers create one successor; period closure first denies waiting transfer without ending prior; transfer first followed by period closure preserves transferred/active child intervals. A later closed parent still denies new work and does not cascade child state/history. UTC/school-date evidence is server-derived; no external effect occurs in retryable units.

## Handoff and next prerequisite

Only 4.3/4.4 are completed. Gates 1.2–1.8 remain open globally. U7 cannot start by inferring planned-parent activation/new-work eligibility, ambiguous planned shared-day mapping or assignment administrative closure date/key mapping from enrollment rules. Those remain explicit human prerequisites in design/tasks; proposed review choices are in docs/u7-pending-decisions.md. Non-current teacher replacement remains U8-specific and is not silently selected here. No apply, push or remote Git operation occurred.
