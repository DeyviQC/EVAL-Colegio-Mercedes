# U12 Readiness and Minimum Next Slice

Date: 2026-10-07. Verified predecessor: ea510ba408e6ff995a706f2f002dee8d8307cc5d. This is a proposal, not tooling approval or a completed gate. The human requested continuation; no unselected technical choice is treated as approved. OpenSpec remains normative.

## Inspected state

- Tasks 7.1-7.5 are complete at the documented isolated reference/command/policy boundary. Tasks 8.1-8.4 and global gates 1.2-1.8 remain open.
- backend/composer.json contains selected Illuminate components, not laravel/framework. backend/routes/academic.php is a composition closure, not an installed kernel/listener. FoundationController reserves activity/submission POST routes as admission-only; successful public creation/acceptance is not implemented.
- Persisted U11 references are available internally. Production AcademicIdentityLabels binding remains absent. Never substitute test persona names or expose raw IDs as approved institutional names.
- No frontend package.json or established frontend type/contract/browser/load commands were found. Locally detected node v24.21.0 and npm 11.19.0 are availability evidence, not approved supported versions.
- Existing prototype remains separate/read-only. Command: node --test --test-isolation=none "docs/prototipos/academic-foundation/tests/*.test.mjs": 15 passed, zero failed. Default process isolation failed with spawn EPERM before executing assertions; the repository-documented in-process command succeeded. No browser/LAN evidence follows from these tests.

## Proposed technical choice for human review

Recommended: authorize only an initial 8.1 contract slice using the existing Node runtime, local TypeScript dev dependency, TypeScript compile checks and Node's existing test runner. Implement contracts.ts, api.ts, contracts.test.ts, local package/lock/config and executable scripts. Exact TypeScript release must be verified before pinning; no version is invented here. If approved, dependency retrieval is limited to registry.npmjs.org via HTTPS, local installation only, no global installation and no lifecycle scripts. Actual resolved versions and commands will be recorded.

Advantages: verifies decimal-string BIGINT/ordinals, allowlisted DTOs and stable error handling before a UI/runtime migration; minimal new tooling and no deployment choice. Disadvantages: this does not establish React rendering, browser accessibility, full API schema tooling, coverage/lint/formatting or load testing; generated JavaScript test output must be isolated and ignored. Impact: new frontend contract/tooling files and scoped verification/readiness records; no six-spec change, no gate closure by inference. Task 8.1 closes only after every listed behavior is implemented and tested. If executable transport dependencies reveal missing approved contracts, keep affected acceptance unclaimed.

Alternative: select React/Vite/Vitest/Playwright and full HTTP integration in a larger prerequisite slice first. Advantage: earlier UI/browser integration; disadvantage: larger dependency and runtime boundary with more decisions, including local HTTPS/session deployment and an authoritative label source. Impact: frontend scaffold plus separately reviewed server integration; not recommended as the first slice.

## Approved behavior to test, without asking again

Trace tasks 8.1 and design's authenticated request/routing boundaries: string IDs/keys without Number coercion; exact historical projection allowlist; server-derived roles/scope; transfer only immediate with server-success confirmation; mandatory enrollment period without new containment validation; planned assignment planning distinct from operational authority; closed-parent new-work denial versus valid cleanup/history; visible labels distinct from equality keys; submission input has no recipient/teacher/scope/enrollment selectors. Preserve selected 401/403/404/422/409/503 and auth 419/429 mapping. Uncertain outcomes retain correlation and never automatically replay. Deferred payload/availability/grading are not selected.

## Later blockers and separate decisions

| Boundary | Required evidence/decision | Can remain pending during proposed 8.1 slice? |
|---|---|---|
| 1.6 frontend versions | Human tooling approval, verified release and lockfile, executable type/test commands | No for selected TypeScript tooling; React/Tailwind may wait until UI |
| 1.7 contracts | Approved selected TypeScript/Node contract approach and honest limitation against broader API schema gate | No for this slice; full schema/browser/load tools may wait |
| 8.2 HTTP runtime | Approved full Laravel/dev HTTPS server composition; authenticated cookies/CSRF over an actual listener; safe persisted reader/writer bindings | Yes; no browser acceptance claimed |
| 8.2 identity labels | Human-approved institutional profile source, retention/current-label mapping; synthetic labels only in declared tests | Yes; institutional acceptance remains blocked |
| 8.2 LAN/WLAN | Authorized isolated network setup and physical client evidence with Internet unavailable and server/network/power available | Yes; no LAN readiness claimed |
| 8.3 load | Institution-approved synthetic seed dimensions/provenance, concurrency/workload, lock-wait/latency/error thresholds; approved runner | Yes; do not invent institutional limits |
| 8.4 acceptance | Complete six-spec trace matrix with actual commands/results and explicitly missing layers | Final completion blocked until required layers verified |

IIS versus Apache is not required for isolated contract tests and remains undecided. No new retry/idempotency mechanism is needed for 8.1: use verified no-replay behavior. No label/account schema, React scaffold, browser/load installation, full server, public reference writer or deferred feature is authorized by this proposal.

## Recommended order

1. Human reviews the narrow tooling proposal above; preserve open gate checkboxes.
2. Record the selected decision before installation/code. Complete and verify the 8.1 slice, report exact diff and commit its bounded local handoff.
3. Review server transport/profile binding and browser tooling decisions separately before 8.2 implementation. Do not choose deployment IIS/Apache incidentally.
4. Obtain institution seed/threshold approval and load tooling before 8.3; finish acceptance traceability only with actual evidence.

No apply, remote Git, production data or integration of 2e5d053. No specifications/design/task checkbox changes in this readiness handoff.