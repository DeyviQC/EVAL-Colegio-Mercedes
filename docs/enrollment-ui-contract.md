# EVAL Director enrollment UI contract

Date: 2026-10-08. Human requested continued work on the real application after persistent development setup. This slice connects existing enrollment create/close/immediate-transfer services; it does not implement account management, new enrollment policies or Vice Principal roster access.

GET `/academic/enrollments` returns an ID-ordered keyset page of at most 50 retained enrollment projections with authoritative student/period/grade/section names, state and declared dates. GET `/academic/enrollment-students` returns only retained IDs and visible names for active identities with a stored student grant. Both permit only canonical `after`; neither accepts client actor/role/scope selectors. Both revalidate credential revision, active identity and stored Director/Admin grant. Missing profile names fail closed. Credential identifiers, password hashes and equality keys are never exposed.

The UI consumes existing Director period/grade/section collections for named selection with explicit continuation controls. Student names come from the restricted retained profile provider; no invented institution names or raw-ID input replaces identity selection. Active-period/catalog filtering is a presentation convenience; all writes remain server-authorized and validated. Historical and closed enrollments remain visible; open declared ends do not imply automatic expiry. Enrollment dates are not artificially constrained to the period calendar.

The changed enrollment/successor is fetched through the same restricted collection if it falls beyond the first page. A confirmed write followed by a failed refresh reports that distinction instead of claiming the list was updated.

Creation uses the existing enrollment POST. Close requires explicit date and confirmation. Transfer requests only destination grade/section and is immediately effective after the server confirms; there is no scheduled transfer, recipient or client ordinal field. Uncertain writes suspend mutations across workspace navigation for that client instance and never replay. Logout/expiry unmounts record views. This does not close global OpenSpec architecture, network, load or acceptance gates. Assignment management is a subsequent bounded slice.

## Verification receipt

Frontend: 85 tests passed; strict application/tooling typecheck and Vite build passed. Isolated `eval_u11_test`: focused Director directory test passed with 28 assertions; LocalAcademicOperationTest passed with 7 tests / 281 assertions; full U11 passed with 98 tests / 2,161 assertions and two explicit optional browser/human-preview skips. This subsequent application unit used the established disposable test harness, not `eval_dev` for PHPUnit.

Live Chromium: seven enrollment checks proved creation, cancelled transfer, confirmed immediate transfer, retained predecessor, successor closure, unchanged original course student and 768px no-horizontal-overflow. It used one explicit private verification student identity without login credentials and a named destination section in `eval_dev`; these remain as synthetic retained development history. The original student's enrollment and materials were untouched. Missing exact accessible selection names were discovered by browser execution and fixed; the complete journey then passed. Screenshots/receipts are private under `.local/eval-dev/`.

No assignment UI, user management, institutional import, physical network/outage, load, full accessibility audit, new product policy, remote operation or global gate closure is claimed.
