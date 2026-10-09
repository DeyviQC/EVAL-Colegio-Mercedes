# U3 Verification and Local Handoff

Date: 2026-10-07 (America/Lima). Predecessor: 8a299e94fb3babdfb2919a3dbfe5decabac449c4.

## Authority and boundary

The sequential implementation authorization and the human's Continue response to the proposed three-attempt policy are recorded in docs/u3-readiness.md. The numeric line limit was removed; actual additions/deletions must still be reported before this local handoff. Only tasks 2.5–2.7 are completed here. No U4 commands, real authentication, HTTP endpoints, activity/submission tables, automatic request replay, remote operations or integration of 2e5d053 are included. The six specs and design remain unchanged.

## Implemented guarantees

The transaction adapter owns a top-level MySQL transaction. It takes the exclusive singleton guard before discovery, unions shared retained actor/student/teacher identities once, and locks complete sets by canonical table rank and numeric decimal BIGINT order. Locked context supplies current rows to server validation. Changed discovery causes rollback and complete rediscovery, never appended out-of-order locks. Active retained identity is necessary but is not sufficient application authorization; operation-specific authorization callbacks and future authenticated adapters remain mandatory.

The prospective ordinal is validated before allocation. Source changes, ordinal allocation and all events commit or roll back together. Events are inserted last. Three total attempts are permitted for rediscovery, serialization/deadlock or lock-wait timeout only after explicit confirmed whole-transaction rollback. Domain/constraint failures are not retried. Commit-stage errors return commit_outcome_unknown with correlation evidence and never replay. Failed rollback stops immediately. Callbacks are trusted internal server code, must preserve transaction ownership, must keep validation read-only, and must not perform external effects. This is not a sandbox for arbitrary callbacks.

The ledger has six typed restrictive target FKs, one retained actor FK, exactly-one/matching-discriminator checks, operational key range checks and append-only UPDATE/DELETE triggers. Current aggregate rows remain authoritative; no event replay is introduced. Declared/effective date, UTC microseconds, ordinal, actor, correlation and previous/new display labels are retained. Down migration refuses history deletion; only the explicitly selected disposable test database supports fresh rebuild.

## Environment and commands

Observed PHP 8.4.26 / Illuminate Database 13.35.0 / PHPUnit 12.5.38 / MySQL 8.4.9. This is an isolated Illuminate harness, not a verified full Laravel web application. The owned server listens on 127.0.0.1:3307; eval_u3_test has 11 tables and TLS_AES_256_GCM_SHA384. U1/U2 databases remain separate. Secrets remain in the private encrypted runtime state outside Git.

Preparation performed with the existing owned-server wrapper: start-u1.ps1 -ConfigureRuntime -Unit U3, then local.ps1 -Command migrate -Role migration -Unit U3 -Arguments @('--fresh-u3'). Fresh rebuild is disposable-test-only, not an institutional rollback procedure.

| Repository-supported verification | Result |
|---|---|
| backend/scripts/local.ps1 -Command test -Unit U1 | 43 tests / 150 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U2 | 27 tests / 182 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U3 | 23 tests / 154 assertions, pass |
| backend/scripts/local.ps1 -Command db-smoke -Unit U3 | MySQL 8.4.9, 11 tables, verified TLS |
| git diff --check | Pass |

No unsupported browser, load, full-framework or institutional LAN acceptance check is claimed.

## Race, rollback and permission evidence

- Two independent PHP processes connect to real MySQL. Deterministic file barriers hold the first transaction after validation; SHOW PROCESSLIST proves the second connection is waiting on the exclusive guard. Empty-active-set period activation and competing grade creation each commit once. The waiting writer revalidates and returns validation_denied without allocating another ordinal/event. These are protocol fixtures, not delivered U4 product commands or resolved planned-parent work eligibility.
- A separate migration connection holds a grade update. Runtime receives actual MySQL 1205 after a one-second lock wait. Confirmed rollback precedes release; the next attempt sees the committed inactive grade and denies validation without allocation.
- A deterministic 1213/40001 exception after a real source update proves full rollback, fresh validation and reuse of the uncommitted prospective key. Repeated injected faults stop after three attempts. This is injected deadlock evidence, not a claimed naturally occurring database deadlock.
- A test connection performs a real PDO commit, then injects lost acknowledgement. Source/ordinal/event persist exactly once, the correlation matches the failure, and no retry occurs. Another connection injects rollback failure; execution stops after one attempt. Test cleanup subsequently performs rollback explicitly. These faults are injected; no network outage is claimed.
- An invalid second target event rolls back the earlier event, source mutation and ordinal. Invalid discriminator, missing targets across all six types, missing actor, invalid operational key and malformed JSON fail in MySQL. Ledger-only target deletion is rejected. Actor credential removal retains its audit identity and event.
- Actual MySQL 8.4.9 FOR UPDATE initially returned 1142 with SELECT-only privileges on retained identities. Narrow UPDATE privileges on selected source columns allow locking probes; they are not role authorization. Ledger runtime has SELECT/INSERT only; runtime UPDATE/DELETE and ALTER fail with 1142. Maintenance UPDATE/DELETE fail with append-only triggers (1644). Maintenance and runtime connections remain distinct.

## Honest test-first evidence

The initial lockset stub produced three failures (ordering/union and rejected inputs), then passed three tests/seven assertions. Transaction/ledger stubs produced four focused failures/twelve assertions (missing validation/result, target checks and FK enforcement). Full implementation first exposed actual locking privilege failures and MySQL JSON object key ordering; grants and semantic JSON assertions were corrected. Additional retry, barrier and negative tests were added afterward. No historical RED/GREEN for those later tests is reconstructed. Windows pipe blocking required file barriers; final races use bounded waits and cleanup.

## Remaining prerequisites and handoff

Gates 1.2–1.8 remain open globally. U3 supplies their applicable identity, permission, transaction and replay evidence; it does not complete operation-specific temporal mappings, planned-parent activation/work eligibility, role matrix, browser/load tooling, deployment or full ambiguous-request recovery.

U4 requires a separately established local authenticated actor before commands (gate 1.6), not only before U9 HTTP. Retained synthetic identity records and active credential_status are not authentication. Authentication mechanism remains a human technical decision. Do not start U4 or claim real role authorization from these fixtures. A subsequent bounded authentication prerequisite needs that decision and verification, then an exact predecessor handoff.
