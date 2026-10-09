# U9 Isolated HTTP Verification

Date: 2026-10-07. Normative prerequisite: 1da7376, docs/u9-http-human-approval.md. Core predecessor: 169f88a. Scope: task 6.5 over the existing local authentication/request adapter, with no full Laravel kernel/listener installation.

## Actual RED/GREEN and runtime

Two HTTP tests existed before controller implementation. Stub RED: 2 tests / 13 assertions, both fail with 404 instead of successful director read/planned assignment creation. Initial GREEN: 2 / 16. Later coverage is not presented as reconstructed historical RED. Final HTTP-only coverage: 15 tests / 308 assertions; full U9 suite: **38 tests / 647 assertions**, zero failures/errors/warnings.

Commands: `backend/scripts/local.ps1 -Command test -Unit U9` and `-Arguments @('--filter=AcademicPolicyHttpTest')`. Effective PHP 8.4.26, PHPUnit 12.5.38, existing Illuminate components 13.35.0; MySQL 8.4.9 eval_u9_test, 15 existing tables, TLS_AES_256_GCM_SHA384. No new dependencies, schema or grant expansion. All authentication passes through the verified local encrypted session/SessionGuard adapter and actual MySQL credentials/roles. Test timeout/throttle/origin and synthetic identity/profile settings remain test-only, not institution-selected values.

| Evidence | Result |
|---|---|
| Authentication | No session -> 401; actual credentials and rotated encrypted local session -> protected read; removed identity invalidates access |
| CSRF/origin | Wrong CSRF -> 419; untrusted Origin -> 403; no ordinal/ledger changes |
| Director | Foundation reads/create/lifecycle delegate to guarded commands; planned-parent planning succeeds; closed-parent new work -> 409; permitted enrollment/assignment cleanup and catalog operations retained |
| Vice Principal | Exact assignment support/cleanup includes retained inactive references; general period/enrollment reads -> 404, enrollment/catalog management -> 403, deferred processing absent |
| Teacher/student | Teacher own assignment/activity/submission history; student own enrollment/submission only; other teacher/student scope denied and no role snapshot grant |
| Input tampering | Actor/role/recipient/key injection, extra read scope, unsupported transfer timing/correction, malformed JSON/array root -> 422 without academic mutation |
| Closed-parent category matrix | Enrollment/transfer/assignment/activation/replacement commands denied; reserved activity/normal/late submission admission denied despite active children |
| Reserved reference routes | Positive fixture eligibility still returns 404 without executing a writer; default reference reader exposes no references. No U10/U11 activity/submission creation/acceptance exists |
| History transport | Synthetic original assignment and accepted-under enrollment survive closure; Director/teacher history requires authoritative role/ownership, student history remains owner-bound; no grades/payload exported |
| Alternate entry | Forged actor snapshot cannot bypass guarded catalog command after stored role change |
| Uncertain commit | Actual command commits MySQL once; injected lost acknowledgement yields 503 with ledger-matching correlation and automatic_retry=false; no SQL/trace or blind replay |

Success responses use an explicit data envelope, 201 for creation and 200 for other completed operations. Error mappings follow the approved contract. 503 also represents unavailable infrastructure/reference bindings or exhausted transaction prerequisites with no automatic replay; only actual uncertain transaction outcomes claim a ledger correlation. Read-side QueryException is sanitized without SQL/bindings. Identity-label absence is not replaced with a fabricated display name.

The route file is an explicit server-owned composition closure over LocalSessionAuthentication and FoundationController. It does not mount Laravel routes, listen on a port or accept client callbacks/actors. Command adapters preserve final fresh credential/role/parent validation under U3; preliminary policy checks never certify a write. OwnHistoricalSubmissionQuery retains its strict owner-first get method and adds an explicitly policy-authorized historical projection for Director/teacher reads, still excluding unauthorized students/Vice Principal. Existing core ownership tests remain green.

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
| U9 | 38 | 647 |
| Total | 277 | 2855 |

All suites pass. Scoped PHP lint and git diff whitespace checks pass. U9 tasks 6.1–6.5 are complete at the approved internal/synthetic-reference/isolated HTTP boundary; gates 1.2–1.8 remain open. No institutional profile-name binding, persisted references/7.5, browser/LAN/load acceptance or production deployment is claimed. U10/U11 require their explicit minimal-contract approval and prerequisites. No apply, remote Git operation or 2e5d053 integration occurred.
