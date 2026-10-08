# U9 Core Policy/Query Verification

Date: 2026-10-07. Predecessor b597dad. Verified bounded scope: 6.1–6.4, not 6.5 or complete U9.

## Actual test evidence

Policy stub RED: 3 tests / 14 assertions, three failures; initial GREEN: 3 / 20. Support tests existed before their implementation: 12-test run / 117 assertions had two expected forbidden errors at the support stub. Own-history test preceded its implementation: 18-test run / 236 assertions had one not_found error at its stub. Later verification cases are not described as historical RED/GREEN evidence.

Final: `backend/scripts/local.ps1 -Command test -Unit U9`, **23 tests / 339 assertions**, no failures/errors/warnings. PHP 8.4.26, PHPUnit 12.5.38, existing Illuminate components 13.35.0. `db-smoke -Unit U9`: MySQL 8.4.9, isolated eval_u9_test, 15 tables, TLS_AES_256_GCM_SHA384. Existing migrations/grants only, no new schema/dependencies. The file under tests/Unit deliberately exercises actual local MySQL authoritative reads; it is not a claim of a database-free unit suite.

| Evidence | Verified boundary |
|---|---|
| Actor authority | Absent actor, forged roles, revoked stored roles, removed status and invalidated credential revision deny access |
| Parent category matrix | All closed-parent enrollment/transfer/assignment/activation/replacement/activity/normal-late acceptance categories denied despite active children; valid cleanup retained |
| Planning/current distinction | Planned-parent assignment planning permitted, activation denied; unrelated current period never substitutes requested closed parent; catalog remains unaffected |
| Role/history | Director review, Vice Principal assignment-only cleanup/history, teacher owned assignment/history and student own enrollment/submission; other owner/deferred operations denied |
| Existing activity route | Synthetic activity with closed original assignment may authorize minimum acceptance only with active parent/currently compatible active enrollment; recipient injection denied |
| Purpose-bound support | Exact proposed/target duty required; minimal period/catalog/teacher-ID/enrollment-scope aggregate only; no unscoped/general browsing, unrelated student histories, writes or deferred payload |
| Inactive references | Exact closed-period history/cleanup can resolve retained inactive refs; proposed new work cannot make them eligible |
| Own history | Owner check before labels/context; original route/accepted enrollment retained; corrected current labels; no content, recipient, grading or equality key exposure |
| Actual transitions + synthetic references | Real U6 transfer and U8 replacement do not change the injected original activity/submission envelope; persisted U10/U11 integration is explicitly not proved |
| Fail-closed ports | Default reference reader exposes nothing before persistence; unknown/broken route and unavailable authoritative identity label deny projection |

No HTTP endpoint, controller/policy adapter test, complete Laravel kernel, institution-profile label binding, browser/LAN/load acceptance or persisted reference relationship is claimed. Task 6.5 awaits the human technical selection in docs/u9-pending-http-contract.md. All global gates 1.2–1.8 remain open.

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
| U8 | 27 | 333 |
| U9 core | 23 | 339 |
| Total | 262 | 2547 |

All suites pass. Scoped PHP/PowerShell syntax and git diff whitespace checks pass. No new retry/transaction protocol or source-state mutation is introduced by these queries. No apply, remote Git operation, candidate integration or deferred functionality occurred.

## Subsequent HTTP handoff

The human approved the proposed HTTP contract after this core receipt. Task 6.5 was subsequently implemented and verified; see docs/u9-http-human-approval.md and docs/u9-http-verification.md. This document preserves the earlier core boundary/counts/blocker as historical evidence; it does not describe 6.5 as still pending after that subsequent handoff.
