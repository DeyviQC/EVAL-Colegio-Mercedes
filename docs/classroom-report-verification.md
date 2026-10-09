# Classroom Reports: Local Verification

Date: 2026-10-08. Human approval: Continue after the concrete director-only classroom summary proposal. The accepted collaborator Reports screen at locally available commit 2e5d0532840c370aa013655ce65410a3a494e73f is the UI baseline.

## Delivered behavior

The director selects period, grade and section and consults complete classroom received/pending/graded totals and current AD/A/B/C distribution. The roster displays names and counts, including zero-delivery active-period enrollments and marked historical delivery owners. Each page contains at most 50 students, with next-page and return-to-start controls; full totals do not derive from a partial browser page.

The dedicated report query checks live credentials, active identity and stored director_admin role. Its explicit read-only repeatable-read transaction produces one response snapshot for scope labels, totals and rows. Original assignment and accepted enrollment must match the exact requested period/grade/section and student. Versions and assessment revisions never multiply logical delivery counts; only the current assessment contributes a grade. Reporting grants no private answer, feedback, evidence download or assessment-edit authority. The existing server-confirmed can_manage flag shows the director menu because its current policy is the same stored director role; the report endpoint independently enforces its own authority.

No database migration, runtime privilege expansion, installation, external service or remote Git action was needed. The schema remains 24 tables / 13 migrations.

## Executed evidence

- Actual initial frontend contract/compile RED: absent report module. Additional cases were added during implementation; no universal historical test-first or initial browser RED is claimed.
- Frontend: **110 tests passed**, including six report contract/transport cases covering exact projections, safe decimal IDs/counts, grade/count invariants, page/cursor bounds, no private fields, read-only transport, denial, wrong scope and repeated cursor page. Application/tooling TypeScript checks and final Vite build pass.
- Actual MySQL verifier: **40 checks passed** against a dedicated 52-student synthetic aula. It verifies zero-delivery rows, 50+2 pagination without duplicate students, complete unchanged totals across pages, role denials, wrong-grade section rejection, version-independent counts, current-grade correction from A to AD, absence of private fields, original attribution after transfer/replacement, and retained closed-period counts. Invalid HTTP field/method forms are rejected. Snapshot comparisons confirm report consultations do not change the write ordinal, lifecycle ledger, delivery or assessment counts.
- Actual Chromium: **12 checks passed** for the real director menu, named selectors, exact server/card totals, main roster, no evidence links, teacher/student/vice-principal menu and direct-route denial, extra-field and scope rejection, actual 50+2 pagination with complete totals, historical row marking, return to first page and 768-pixel overflow check. The final build's browser-only run also passed all 12 using existing fixtures. The viewport screenshot was visually inspected; private receipt/screenshot paths are ignored.
- Existing U11 regression: **99 tests / 2397 assertions**, two optional skips, no failures. Runtime helpers: **20 tests passed**, including owned status and duplicate-start preservation. PHP/Node syntax checks and git diff --check pass.

The verifier supports paginated catalog discovery. manage-dev.ps1 -Command verify-reports runs server fixtures then the browser; adding -BrowserOnly reuses existing report fixtures and does not create more accounts. It is accepted only for that command. Synthetic setup uses the real existing account/enrollment/assignment/activity/assessment commands. Two full verifier runs retained two named 52-student aulas; the school's main student and aula were not replaced or reset.

Final inspected development snapshot: TLS, restricted runtime grants, 24 tables, 13 migrations, 149 accounts, 8 periods, 49 assignments, 153 enrollments, 3 materials and ordinal 682. These are development/verification records, not a claim of institutional enrollment. The director application was opened and authenticated after verification. Teacher/student sessions remain available.

## Remaining acceptance

Reports are classroom summaries, not official aggregate grades or missing-work eligibility calculations. PDF/Excel export, notifications and wider management supervision remain separately scoped. Simultaneous-writer snapshot fault probes, physical school LAN/outage, load, durable storage and backup/restore acceptance are not claimed. Changes remain local and uncommitted. No new contributor started; a verified committed handoff is still required before one does.
