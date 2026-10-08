# U12 First Contract Slice Verification

Date: 2026-10-07. Approval: docs/u12-contract-human-approval.md. Predecessor a7be8cd. Task 8.1 only; U12 as a whole remains incomplete.

Registry metadata and local executable both identify TypeScript 7.0.2. Source release context: https://www.typescriptlang.org/download/ and https://github.com/microsoft/TypeScript/releases . The compiler and the Windows x64 platform package were installed locally through registry.npmjs.org with scripts disabled; optional packages for other platforms remain uninstalled. Node 24.21.0/npm 11.19.0 were already present. No global installation or remote Git operation.

Actual initial RED: `npm test` from frontend executed five tests against explicit stubs: two passed (negative rejection), three failed. Initial GREEN: five passed. Expanded coverage initially found four test-harness failures: object property ordering in three comparisons and an incorrectly synchronous assertion for an async CSRF rejection. Order-independent comparison and awaiting the rejection corrected the harness; no product rule was changed. An accidental test invocation from repository root failed because there is no root package.json; commands are supported from frontend only.

Final `npm test`: 36 passed, zero failed/skipped/cancelled. `npm run typecheck`: successful strict TypeScript check, including three `@ts-expect-error` negative compile examples (numeric ID, scheduled transfer, recipient selector). `tsc --version`: 7.0.2. Offline npm lockfile rebuild with scripts disabled succeeded using the already populated cache; type/test commands passed afterward.

| Task 8.1 criterion | Evidence |
|---|---|
| DTO/string BIGINT/ordinal compatibility | Unsigned maximum identity, signed positive maximum key, >Number.MAX_SAFE_INTEGER retention, numeric/noncanonical/overflow rejection, nullable historical boundaries |
| Historical allowlist/errors | Required original reference/enrollment/assignment graph, matching retained scope, no payload/grades/equality-key/authority fields, explicit stable HTTP failures and sanitized malformed-response rejection |
| Immediate transfer | Only prior locator plus grade/section destination; scheduling/retroactivity/correction/client actor/role/key fields rejected before fetch; success only from validated server confirmation |
| Mandatory enrollment period/no containment-only rejection | Required period ID and valid declared dates; dates are not compared to a period calendar; open end stays null, no client expiry |
| Submission without recipient/scope selectors | Activity-only reserved request, runtime excess-field rejection plus negative compile example; endpoint unavailability is propagated rather than claiming U11 writer activation |
| Labels separated from keys | Authoritative display labels retain spacing/case/accents; nested equality keys rejected, never derived/rendered as labels |
| Closed-parent cleanup/history versus new work/planning | New-work denial propagates; residual closure/history remain requestable; planning uses distinct create versus activate calls; no actor authority or state cascade inferred |
| Uncertain outcomes/no replay | Commit correlation retained, no automatic retry; network loss or malformed mutation acknowledgement marks uncertain outcome; fetch call count stays one |

Backend regression: `backend/scripts/local.ps1 -Command test -Unit U11`: 68 tests / 1,114 assertions, all passing without skips on isolated MySQL 8.4.9. No backend code changed. Prior full 366-test backend regression belongs to the U11 handoff, not a newly rerun full-suite claim.

Task 8.1 is complete at the approved typed client/contract-test boundary. Test responses are controlled fixtures inspected against existing FoundationController/AcademicScopeQuery/OwnHistoricalSubmissionQuery/command response shapes; no live cross-language API schema contract, real listener, browser, UI or LAN evidence is claimed. This does not complete gate 1.7 globally. Tasks 8.2-8.4, gates 1.2-1.8, institutional profile-name binding, public reference endpoints, full runtime/HTTPS and seed/load thresholds remain pending as listed in docs/u12-readiness.md. Prototype files stay separate and unchanged. No React/Tailwind scaffold, deferred capability, apply, deployment, remote Git or integration of 2e5d053.
