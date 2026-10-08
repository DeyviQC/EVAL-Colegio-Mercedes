# U4 Verification and Local Handoff

Date: 2026-10-07. Predecessor: 7dad4041e791ed90730193edb0305259db309eb8.

## Scope and authorization

The human authorized sequential approved units; scoped prerequisites are recorded in docs/u4-readiness.md. This handoff completes tasks 3.1–3.3: in-process period/catalog commands, current/bounded historical period lookup, locked active-catalog prerequisite validation and proving tests. No public academic routes, enrollment/assignment writers, deferred module, new planned-parent permission or integration of 2e5d053 is included. The six specs and design are unchanged.

Normal period planned→active→closed is explicitly approved by academic-periods; it is distinct from the unresolved question about assignments/new work under a requested planned parent. Period declared dates and names are never rewritten by lifecycle operations. Events use the already verified U3 operation date/key/UTC evidence, preserving declared boundaries as source data/creation metadata without guessing an interval mapping.

## Implemented behavior

AcademicPeriodCommands creates planned periods with valid ordered dates, server-derived keys and all-state name uniqueness; only explicit planned→active and active→closed transitions are accepted. At most one period is active. Current lookup reports absence when none is active and never infers current state from calendar dates. Bounded historical lookup uses the requested period ID. Director/Administrator permission is loaded from local server grants; other roles and forged role snapshots cannot manage periods or catalog.

AcademicCatalogCommands handles instructional entries (subject/area), grades and sections. It creates active/inactive rows, corrects labels and changes active state without changing IDs/classification/grade parents. Keys follow the approved outer-trim/NFC/full casefold/accent-distinguishing policy while visible labels remain byte-for-byte separate. Uniqueness applies to active rows within classification/grade parent; inactive duplicates, distinct accents and equal names in different parents/types remain permitted. Create, active rename and reactivation validate against the authoritative locked conflict set. No scope-rewrite endpoint is introduced, including for referenced rows; unreferenced reclassification policy is not invented.

CatalogReferenceValidation is a pure check of already locked grade/section/instructional facts. It rejects mismatched grade-section and inactive new-reference prerequisites. It creates no academic object, checks no enrollment dates and grants no actor authority. Catalog section management requires a valid grade identity without imposing an unapproved active-grade management rule; later academic writers must validate all required references independently.

All mutations use U3: guard first, complete canonical target/conflict/actor union, refreshed authority before ordinal allocation, atomic source/event changes and no external effects/replay. New generated IDs use PDO decimal-string lastInsertId to preserve unsigned BIGINT representation. Runtime has only source columns needed by these commands, ledger SELECT/INSERT and local session/counter writes; no retained identity DELETE/ID mutation, DDL, credential/role write or scope/classification UPDATE permission.

Review identified credential revocation between request authentication and command execution. AuthenticatedActor now carries a private server-derived password revision, and U4 rechecks current credentials under U3 before checking authoritative director_admin role. Password removal/change therefore denies writes before allocation even when retained identity state remains active. The revision is not a client DTO field or authorization substitute. LocalUserProvider supplies it; existing Auth tests remain unchanged and pass. Any future credential/role authority writer must participate in U3 as documented.

## Environment and checks

Observed PHP 8.4.26 / Illuminate 13.35.0 / PHPUnit 12.5.38 / MySQL 8.4.9. Separate eval_u4_test has 15 tables (existing academic/auth prerequisites) and TLS_AES_256_GCM_SHA384 at the owned isolated 127.0.0.1:3307 server. Initial bootstrap creates the database; allowlisted migration runs; a second bootstrap applies grants after tables exist. No new packages, real users or institutional data are used.

| Repository-supported check | Result |
|---|---|
| backend/scripts/local.ps1 -Command test -Unit U4 | 26 tests / 323 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit Auth | 24 tests / 127 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U1 | 43 tests / 150 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U2 | 27 tests / 182 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U3 | 23 tests / 154 assertions, pass |
| backend/scripts/local.ps1 -Command db-smoke -Unit U4 | MySQL 8.4.9 / 15 tables / verified TLS |
| git diff --check | Pass |

No full Laravel web kernel, public command HTTP endpoint, live browser/LAN outage, contract or load acceptance is claimed. In-process command/auth integration is verified; production configuration remains separate.

## Evidence and test-first limits

Four initial command stubs produced four real failures/sixteen assertions: missing planned source, omitted date validation, missing catalog source and accepted duplicate. Implementation then passed four tests/twenty-six assertions. Later negative/race tests were added afterward; no missing historical RED/GREEN is reconstructed.

Coverage includes normal/skipped/reversed/repeated period transitions, active-period conflict, invalid calendar/range/name/input, calendar-independent current absence, closed-period all-state equality, classification/grade-parent separation, equivalent active grade names, and Unicode active rename/reactivation conflicts in all three catalog kinds. Correction events retain prior/new visible labels. Inactive rows remain available and may reactivate only without active-scope collisions.

Committed synthetic enrollment/assignment fixtures prove period closure does not cascade child states, rewrite owners/scope/boundaries or affect other periods/global catalog. Catalog correction/deactivation preserves complete child rows and classification. Locked reference probes reject inactive references and grade-section mismatch. Persisted activity/submission original-route evidence remains U10/U11 and task 7.5 integration; it is not fabricated here.

A real MySQL trigger injects ledger failure during an actual period activation: source stays planned, allocation and event insert roll back. The trigger is removed in finally. An actual LocalSessionAuthentication request/login/CSRF round trip supplies the actor to a period command and its ledger. Role/credential revocation before command execution denies before allocation/events.

Four two-process races execute the actual command classes: empty-set period activation, grade creation, active rename and reactivation. Query-event/file barriers hold the first source write before commit. SHOW PROCESSLIST proves the second connection waits on the common guard. First commits, second sees fresh conflict and denies; exactly one ordinal/event is allocated. These are real concurrent MySQL command tests, not a mocked lock or a public listener.

## Remaining gates and next boundary

Only tasks 3.1–3.3 are completed. Gates 1.2–1.8 remain globally open. Period/catalog persistence and in-process authorization are verified; later endpoint policies, full deployment/local-network acceptance, temporal mappings and submission references remain their own units.

U5 depends on currently unresolved administrative-closure date/key mapping and affected planned-parent new-enrollment eligibility. Do not implement a date-to-midnight mapping, backdate authority, select active-parent-only creation or silently permit planned-parent new work. See docs/u5-pending-decisions.md for concrete human options; do not start U5 merely because U4 passes.
