# U2 Verification and Local Handoff

Date: 2026-10-07 (America/Lima). Predecessor U1: 6cb77840bc56379f6b53738e60d726a746ced63d.

## Authority and scope

The explicit human FASE 2 authorization permits U2 after verified U1 and an applicable-prerequisite review. That review is in docs/u2-readiness.md. The human removed the numeric line limit; exact diff size is still reported. This is a separate sequential handoff, not automatic acceptance of e79a0f1/ba9ed9e, not U3 and not integration of 2e5d053.

Permission review initially rejected isolated U2 preparation as U1-only. Reinspection supplied the original human FASE 2 text and verified U1 evidence; renewed review accepted the same scoped operation. No alternative path bypassed the rejection.

## Implemented schema

All six academic aggregate primary keys and all relationship IDs use local unsigned BIGINT matching retained identities. Enrollment admits active/transferred/closed only; assignment admits planned/active/closed only. Planned assignment has no operational boundary. Active/terminal shapes, date ordering and signed-64-bit operational key range are CHECK-enforced. A generated unique slot prevents multiple active periods; generated active catalog keys respect kind/grade scopes; generated active-student key prevents multiple active enrollments per student/period. Unique restrictive replacement lineage permits different teachers to share scope.

All FKs restrict DELETE and UPDATE. Schema down methods refuse destructive history deletion. No activity/submission reference tables, ledger, writers, lockset, retries or HTTP endpoints are created. Parent closure/catalog deactivation retain existing references without cascading child state.

## Equality mechanics

Server comparison applies Unicode White_Space outer trim (Z separators, TAB–CR and NEL), NFC, full Unicode case folding and NFC again. Invalid UTF-8 is rejected. The original label is never rewritten. VARBINARY(2048) stores comparison bytes and generated active keys; tuple indexes implement classification/parent scopes without linguistic collation adding equivalences. Display fields retain the candidate's 255-character capacity. Fixtures include decomposed outer-spaced algebra, NBSP/em-space/NEL, accents distinguished, Straße/STRASSE, Greek sigma variants, repeated Greek fold expansion, internal double-space distinction, rename and reactivation collisions.

Keys must be computed by the authoritative server adapter. The schema does not implement Unicode normalization inside SQL or authorize a client-provided comparison key. Future U4 commands must validate names and derive keys; no client adapter exists here.

## Environment and reproducible commands

Observed PHP 8.4.26 / Illuminate 13.35.0 / PHPUnit 12.5.38 / MySQL 8.4.9. The same owned isolated server is at 127.0.0.1:3307 with TLS_AES_256_GCM_SHA384. U1 retains eval_u1_test (4 tables); U2 uses eval_u2_test (10 tables: U1 prerequisites plus six aggregates). Existing credentials remain outside Git. U2 runtime is read-only; direct SQL fixture/constraint probes use the separate migration connection and rollback each test.

```powershell
./backend/scripts/start-u1.ps1 -ConfigureRuntime -Unit U2
./backend/scripts/local.ps1 -Command migrate -Role migration -Unit U2 -Arguments @('--fresh-u2')
./backend/scripts/local.ps1 -Command test -Unit U2
./backend/scripts/local.ps1 -Command test -Unit U2 -Arguments @('--filter=FoundationSchemaTest')
./backend/scripts/local.ps1 -Command test -Unit U1
./backend/scripts/local.ps1 -Command db-smoke -Unit U1
./backend/scripts/local.ps1 -Command db-smoke -Unit U2
```

Fresh rebuild is limited to the selected disposable unit database and matching flag; it is not a populated production upgrade. U1 and U2 test configurations and migration allowlists are separate. Default remains U1. U2 does not mutate eval_u1_test. Migration rerun is idempotent.

## Test-first and final results

Name-key RED: 4 tests / 5 assertions / 3 assertion failures against existing mechanics; GREEN: 4 tests / 10 assertions. Candidate migration additionally failed with MySQL 3780 because CHAR(36) could not reference retained BIGINT. Structural assertion RED: 2 tests / 6 assertions / 2 failures (aggregate CHAR and comparison VARCHAR); these, not missing-table/import errors, establish meaningful schema RED. Further fixtures expanded coverage; a fixture duplicate-name mistake was corrected and was not treated as business RED evidence.

Final fresh rebuild and U2 suite: **27 tests / 182 assertions**, 0 errors/failures. FoundationSchemaTest: **23 tests / 172 assertions**. U1 regression: **43 tests / 150 assertions**, unchanged. Whitespace check passed; only LF/CRLF conversion warnings occurred.

| Task 2.4 criterion | Evidence |
|---|---|
| Version-specific period constraints | Valid planned shape; invalid range/state; second active period rejected |
| Approved name equality/scopes | Binary byte storage; all-state period uniqueness; active-only entry/grade/section uniqueness; kind/parent separation; accents and labels; rename/reactivation rollback |
| Required and structural relationships | Missing fields and every missing parent rejected; composite grade/section mismatch rejected; BIGINT metadata |
| Enrollment state/date/key | No planned enrollment; closed/transferred require recorded end; independent date/key negatives; no enrollment containment/expiry; one active per student/period, distinct periods retained |
| Assignment shape and lineage | Planned parent/assignment represented with null start; no replaced state; key/date/required-scope negatives; active/closed shape; distinct teachers and unique successor; prior deletion denied |
| Retention and future ledger targets | Restrictive parents/identities/catalog and lineage; retained labels/links after lifecycle changes; non-destructive down; all six target tables exist; U3/reference tables absent |
| Assignment containment | Approved U1 DeclaredDateRange validates inclusive bounds/open planning horizon; no new temporal mapping is introduced |

## Explicit limits

Cross-table assignment containment, active-catalog eligibility, requested-parent new-work denial, immutable scope/lifecycle command transitions and historical interval conflicts remain application validations in later locked commands, as assigned in design/tasks. U2 enforces its row-local and referential constraints; direct privileged fixtures are not operational APIs or authorization grants. Planned-parent activation/new-work eligibility remains undecided. U2 does not guess midnight, backdate keys, implement overlap semantics, or change any spec.

Task 2.4 is complete for this schema unit. Gates 1.2–1.8 remain unchecked because later obligations remain. U3 is the next unit; it still needs explicit bounded retry/request replay decisions from 1.8 and a scoped prerequisite review before implementation. No push, remote operation, deferred feature or external implementation integration occurred.

Final review removed an unapproved extra restriction that required an active enrollment's declared end to be null. Meaningful RED: 1 test / 3 assertions / 1 failure; final GREEN above. Active operational end remains null, declared dates retain ordinary ordering, and a declared end does not create an operational closure or automatic expiry. This is neutral storage, not a new dated-enrollment workflow or date/key mapping.
