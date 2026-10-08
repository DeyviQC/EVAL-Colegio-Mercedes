# U10 Minimal Activity Reference Handoff

Date: 2026-10-07. Normative approval predecessor: 737f3b2; verified U9 predecessor: 00ba1b5. Scope: task 7.1 only, authorized in docs/u10-human-approval.md. Applicable 1.4 active-parent matrix and 1.8 guarded bounded transaction/no uncertain replay are implemented and tested here; global gates remain open.

Tests preceded migration/command. Actual initial RED: 2 tests / 11 assertions, one missing-table error and one closed-parent-admission failure at the stub. Initial GREEN after migration/command: 2 / 16. Additional cases are verification evidence, not manufactured historical RED. Final `backend/scripts/local.ps1 -Command test -Unit U10`: **18 tests / 278 assertions**, no failures/errors/warnings. PHP 8.4.26, PHPUnit 12.5.38, existing Illuminate 13.35.0. MySQL smoke: 8.4.9, eval_u10_test, 16 tables, TLS_AES_256_GCM_SHA384.

The forward U10-only migration creates activity_references with permanent generated BIGINT ID and required restrictive teaching_assignment_id. No content, timestamps, availability or lifecycle state is added. Update/delete triggers enforce retention/immutability even for maintenance accounts; runtime grants allow only SELECT and INSERT(teaching_assignment_id), no explicit ID, UPDATE/DELETE or DDL. Old unit migration allowlists do not execute this migration. No original U2 migration is retroactively rewritten.

CreateActivityReference accepts only assignment_id. U3 locks guard, authoritative requested period, sorted actor/original teacher identity union and assignment, rediscovers and checks active actor/current credential/current teacher role/owner, active requested parent and active assignment before allocating K. No catalog-active rule is invented for activity work on an existing assignment. Scope/route derive from that original assignment, never client data. A typed activity_reference_created evidence event targets the assignment and records the new reference ID at K; it introduces no activity lifecycle or new ledger target/schema. Default HTTP adapters remain disabled for activity writers; this handoff exposes only the approved internal command and optional persisted activity read binding.

| Evidence | Verified result |
|---|---|
| Creation/scope | Owned active assignment creates one original reference, generated ID, exact two-column source and typed assignment evidence |
| Authority | Other teacher, Director, Vice Principal, student and forged role snapshot denied; stored role/password/status revocation rechecked |
| Admission | Closed/planned assignment or parent denied; missing/invalid assignment and injected scope/recipient/content denied without reference/key/event mutation |
| Retention | Real teacher replacement, period closure and inactive catalog retain the original persisted assignment route; original teacher policy history permitted |
| MySQL integrity | NULL/missing assignment FK rejected; source assignment DELETE restricted; runtime UPDATE/DELETE denied (1142), maintenance update/delete triggers reject (1644) |
| Atomicity | Real reference INSERT failure and real ledger INSERT failure roll back reference, ordinal/events and release transaction |
| Four real races | Period/assignment closure first denies creation; creation first survives either closure with original link retained |
| Uncertain confirmation | Real commit plus injected lost acknowledgement commits exactly once, returns correlation failure and never replays |
| Read binding | PersistedActivityReferences resolves original activity locally; submission reader returns null until U11 |

Races use actual separate PHP processes/connections and a pre-commit source barrier, observe second command waiting on academic_write_guard FOR UPDATE, then release. Test fixture actor IDs are server-only worker locators, not public authority. Multiple activity references to one assignment are allowed and receive independent IDs; no duplicate-idempotency policy is inferred.

Regression: U1 43/150, U2 27/182, U3 23/154, Auth 24/127, U4 26/323, U5 21/307, U6 22/291, U7 26/341, U8 27/333, U9 38/647, U10 18/278. **Total 295 tests / 3,133 assertions, all passing.** Scoped PHP/PowerShell syntax and whitespace checks pass.

Task 7.1 complete at this reference boundary. Tasks 7.2–7.5, U11 acceptance/persisted submission history, institutional label binding, public activity endpoint, full Laravel server/browser/LAN/load evidence and gates 1.2–1.8 remain pending. Existing U9 reference fixture proofs are not converted into persisted submission claims. No content, availability, processing, deferred functionality, apply, remote Git, production data or 2e5d053 integration occurred.
