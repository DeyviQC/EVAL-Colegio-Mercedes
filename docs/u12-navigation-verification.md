# U12 Navigation Contract Verification

Date: 2026-10-07. Predecessor: fe04c9b6e7d094b567e2c6c40133cc2841be48ae. Approval: docs/u12-navigation-human-approval.md; contract: docs/u12-navigation-contract-review.md.

GET /academic/navigation returns exactly data.sections in canonical order, derived from persisted local_role_grants after credential revision and active retained identity checks. It accepts no query/body authority. /auth/session remains unchanged. Multiple grants union without duplicates; no grants yields an empty list. These identifiers permit section discovery only, not resource access or lifecycle admission.

The React session shell validates the exact response, renders Spanish section buttons and a truthful not-yet-connected records state. Context is cleared on session changes and requests carry generation checks so a late response cannot restore a closed session. Failures hide sections and provide explicit consultation; no automatic replay. No current-period shortcut, resource IDs, identity labels or client role picker are introduced.

## Evidence

- Frontend: 61 contract/session/rendering/navigation tests pass; strict application/tooling typecheck and production build pass. Five new cases cover exact ordering/allowlists, authority extras, delayed response invalidation, failed fetch/no retry and same-origin transport.
- Backend navigation test verifies four actual stored grants, forged actor-role resistance, role union/removal, anonymous/inactive/credential-revision denial, query/body rejection. An injected Connection QueryException verifies sanitized 503/correlation/no replay; this is a test double, not an actual database outage.
- Embedded Chromium suite: 6 passed. The new case logs in four disposable role accounts, asserts exact visible section labels, checks unrelated enrollment requests return 404 for the three non-director roles, opens the honest integration state, logs out and verifies navigation disappears. Existing actual login/logout, local assets, simulated 401 and unpinned certificate probes remain passing.
- Full U11 with explicit browser invocation: 77 tests / 1,480 assertions, zero failures/errors/skips/notices. Runtime PHP 8.4.26, isolated MySQL 8.4.9; Chromium browser.version() is asserted as 156.0.8078.4. The prior SPKI exception remains a harness-only validation exception, not institutional CA trust.

Use the invocation in docs/u12-browser-verification.md after npm run build in frontend. Browser accounts/passwords are per-run fixtures; browser diagnostics redact every fixture login/password. No migration/database secrets reach browser workers. Owned PHP/Node/Chromium processes are checked after completion.

During development a PowerShell default-encoding read damaged four existing accented fixture strings. The regression detected the mismatch; the four lines were restored with a targeted patch preserving the new test and existing behavior. Final diff does not contain those accidental fixture changes. No historical test-first evidence is invented.

Task 8.2 remains open: actual resource discovery, academic operation forms/complete accepted-prototype journeys, institutional labels and physical LAN/WLAN evidence remain missing. Resource discovery/pagination and purpose-bound Vice Principal support need a separate reviewed contract. Tasks 8.3/8.4 and gates 1.2-1.8 stay open. No spec/design amendment, public reference writer, deferred capability, prototype/odd change, apply, remote Git or integration of 2e5d053.
