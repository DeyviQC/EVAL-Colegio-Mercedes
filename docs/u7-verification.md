# U7 Teaching Assignment Verification

Date: 2026-10-07. Normative predecessor: 1f448ddb919a74caeae1db88db5473c9620c2f7e; approved decisions: docs/u7-human-decisions.md. Scope: tasks 5.1/5.2 only.

## Actual evidence

The four initial tests existed before command implementation. The stub run failed all four (16 assertions): no planned source, duplicate accepted, missing period containment and zero identity. That is the recorded U7 RED evidence. Implementation initially exposed an incorrect period field mapping; it was corrected to start_on/end_on. Initial GREEN: 4 tests / 30 assertions. Later cases were added during verification; no historical RED/GREEN sequence is claimed for those cases. Fixture and expected-category mistakes were corrected without changing approved rules.

Final U7: `backend/scripts/local.ps1 -Command test -Unit U7`, 26 tests / 341 assertions, zero failures/errors/warnings. Effective PHP 8.4.26, PHPUnit 12.5.38, existing Illuminate components 13.35.0. `db-smoke -Unit U7`: MySQL 8.4.9, eval_u7_test, 15 tables, TLS_AES_256_GCM_SHA384. Existing U1–Auth migrations were applied only to this disposable isolated database; U7 adds no domain schema or dependencies. Runtime uses column-scoped grants; maintenance credentials are restricted to fixture/migration preparation.

| Evidence | Result |
|---|---|
| Planned creation under planned/active parent | Null operational keys, permanent scope/ID, no authority; closed-parent planning denied |
| Manual activation | Active requested parent, school-local today >= declared start, actual successful K; future start remains planned |
| Planning conflicts | Inclusive endpoints, null-end period horizon, disjoint reservations allowed, active declared planning range considered |
| Operational conflicts | Disjoint plans cannot both activate while actual authority is open; closed history checked by half-open K, including boundary equality |
| Lifecycle/history | Planned -> active -> closed only, no reopen/skipped transition, same-day history retained under distinct IDs/keys, no automatic calendar expiry |
| Scope/catalog | Contained valid dates, mismatched grade/section and inactive catalog rejected for new work; distinct teachers/same scope and same teacher/distinct scopes allowed |
| Cleanup | Director/Admin or Vice Principal; contained past/current end preserved, actual end K; closed-parent/inactive-catalog and post-calendar-end cleanup supported |
| Authority | Current stored roles/credentials rechecked; forged snapshots, teacher/student management and revoked roles/credentials denied |
| Atomicity | Real injected ledger INSERT failure rolls back creation, activation and closure, source/ordinal/events unchanged, PDO transaction ends |
| Retention/permissions | Maintenance DELETE blocked by ledger FK (1451); runtime DELETE denied (1142); identity/teacher rewrite denied (1143) |
| Canonical transaction | Guard first, period/catalog before retained identity and assignment history; typed assignment creation/activation ledger targets verified |
| Four real command races | Duplicate planning commits one; disjoint-plan competing activation commits one; parent closure before activation denies child mutation; reverse order retains active child after parent closure |

Concurrency runs separate PHP processes and actual MySQL connections, hold a successful source statement before commit, observe the second command waiting on academic_write_guard FOR UPDATE, then release. They do not substitute SQLite or in-memory transaction simulations.

## Regression receipt

| Suite | Tests | Assertions |
|---|---:|---:|
| U1 | 43 | 150 |
| U2 | 27 | 182 |
| U3 | 23 | 154 |
| Local authentication | 24 | 127 |
| U4 | 26 | 323 |
| U5 | 21 | 307 |
| U6 | 22 | 291 |
| U7 | 26 | 341 |
| Total | 212 | 1875 |

All suites pass. Generic retry/uncertain-commit behavior remains provided by verified U3, with U6 regression of refreshed authority; no new retry protocol is introduced in U7. No public HTTP controller, full Laravel kernel, deployed browser/LAN/load test, persisted activity/submission routing, replacement handler or deferred lifecycle is claimed. Those are later scoped units/evidence. Global gates 1.2–1.8 remain open; 5.1/5.2 are complete only at this tested command boundary. U8 awaits human date/K replacement mapping review. No apply, remote Git operation or candidate 2e5d053 integration occurred.
