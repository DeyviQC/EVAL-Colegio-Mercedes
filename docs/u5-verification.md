# U5 Verification and Local Handoff

Date: 2026-10-07. Normative predecessor: 6262d4faedb62e42ce0e554eb5f13753eddd4dc6; U4: 77826e66ae5482ffd384198cfe3b1ca11a4491cb.

## Human decision and scope

The project human explicitly approved active-parent enrollment creation and valid past/current administrative closure dates with actual successful K, recorded in docs/u5-human-decisions.md and the normative amendment commit before U5 code. Applicable readiness is in docs/u5-readiness.md. This implements only tasks 4.1/4.2. No transfer, assignment lifecycle, general historical/read API, public HTTP endpoint or deferred capability is delivered. No spec/design adaptation to pre-existing code occurs.

## Implemented behavior

EnrollmentCommands creates a new permanent BIGINT enrollment bound to retained student identity, exactly one active period and matching active grade/section. Input must include the complete scope and a valid declared start; declared end is optional and cannot precede start. Scope, state, operation keys and clocks cannot be injected as unsupported fields. Declared dates need not be contained in the period. Creation allocates its operational start at actual successful K; declared dates are stored separately, never converted to scheduling keys or midnight. Even a supplied declared end does not auto-expire the active operational row.

EnrollmentOverlapQuery scopes history by student/period and includes all states. Half-open comparisons use start < other end and other start < end, with null operational end as infinity. Creation uses prospective actual K after the complete period/catalog/shared actor/student/history set is locked under U3. It does not backdate creation to compare against declared-calendar dates. Historical key probes are read-only tests of occupied intervals, not a backdated creation endpoint. Prior closed rows remain retained and a new non-overlapping enrollment gets a distinct identity.

Administrative closure requires current Director/Administrator authority and authenticated credential revision under U3. It accepts a valid declared past/current end (America/Lima server today, end >= start) and sets operational end at actual successful K. Future/invalid dates, repeated terminal transitions, invalid scopes and unauthorized requests fail without allocation/source/events. Only state, declared end and operational end are changed. Student, period, grade, section, declared start and operational start remain immutable. Closure is allowed under a closed parent and inactive catalog even if the subject's credentials are removed; actor authority remains mandatory. Closing creates no successor, reopens nothing and never rewrites past accepted context.

Retained identities are academic references, not substituted by credentials/roles; enrollment does not create a student login or grant an authentication role. All runtime changes use the common U3 protocol; no external effects, automatic replay or client clocks are added. Runtime INSERT excludes explicit ID/operational-end fields; UPDATE permits only state/effective_until/operational_end_key, not scope/start/identity. Ledger-only references protect retained enrollment history; runtime has no DELETE or DDL.

## Environment and reproducible checks

Observed PHP 8.4.26 / Illuminate 13.35.0 / PHPUnit 12.5.38 / MySQL 8.4.9. Separate eval_u5_test contains the existing 15 academic/auth tables, TLS_AES_256_GCM_SHA384 to the owned isolated server at 127.0.0.1:3307. Existing credentials remain private outside Git. No new package or institution data is used.

Bootstrap database with start-u1.ps1 -ConfigureRuntime -Unit U5; migrate with local.ps1 -Command migrate -Role migration -Unit U5; repeat bootstrap after tables exist for table grants. --fresh-u5 is allowlisted only for the matching disposable database. Synthetic committed fixtures retain identities/history; fixture maintenance closes remaining active periods between tests, not a production lifecycle procedure.

| Repository-supported check | Result |
|---|---|
| backend/scripts/local.ps1 -Command test -Unit U5 | 21 tests / 307 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U4 | 26 tests / 323 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit Auth | 24 tests / 127 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U1 | 43 tests / 150 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U2 | 27 tests / 182 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U3 | 23 tests / 154 assertions, pass |
| backend/scripts/local.ps1 -Command db-smoke -Unit U5 | MySQL 8.4.9 / 15 tables / TLS verified |
| git diff --check | Pass |

Browser/LAN outage, full web kernel, general student visibility policies and persisted submission context remain later evidence. Accepted-context retention is limited here to unchanged existing identity/scope/start relationships and append-only evidence; no submission table/test is fabricated before U10/U11.

## Evidence and test-first limits

Four empty command stubs produced four real failures/sixteen assertions (missing creation, duplicate allowed, missing closure, future closure allowed). A null-source warning in the first test draft was corrected with an explicit not-null assertion; the subsequent RED run had four failures with no warnings. Implementation then passed four tests/thirty-three assertions. Later scenarios/races were added afterward; no historical RED is reconstructed for them.

The suite proves complete/missing/tampered scope, absent FKs, grade-section mismatch, inactive new references, planned/closed parent denial, independent periods with a residual active enrollment, declared dates outside period, open end without calendar/parent auto-expiry, past/current actual-K closure, valid cleanup after period calendar end, immutable scope/start, no reopen, role/credential revalidation and terminal retention. Closed historical occupied intervals are found at earlier keys; a touching end boundary does not overlap. Existing history and separate new IDs remain after re-enrollment.

A real MySQL ledger-failure trigger injects errors during creation and closure; source, ordinal and event counts roll back and PDO transaction ends. The trigger is removed in finally. Maintenance deletion of ledger-referenced enrollment fails 1451; runtime DELETE fails 1142, scope/start updates fail 1143. These are actual runtime-role probes, not simulated privilege claims.

Three real two-process command races use query-event/file barriers and SHOW PROCESSLIST to prove the second waits on the guard. Competing enrollments commit once with one K and one row. Period-closure-first denies waiting creation; enrollment-first then period closure commits both and preserves the active child with null operational end. A closed parent does not thereby grant new work; future protected child operations must revalidate it as designed.

## Handoff and remaining gates

Tasks 4.1/4.2 are complete. Gates 1.2–1.8 remain open globally; only their applicable enrollment decisions/evidence are resolved. U6 follows this exact committed handoff and the approved active-parent successor rule, immediate successful server-confirmed transfer and actual school-date/K contract. U7 planned/shared-day and assignment/replacement mapping/product dependencies remain pending. No push, remote Git operation, apply or integration of 2e5d053 occurred.
