# Proposal: Classroom Reports

Date: 2026-10-08. Status: human-approved through Continue in response to the concrete director-only proposal, implemented and verified locally. Delivery assessment was verified before this unit. See docs/classroom-report-verification.md for results and remaining acceptance.

## Accepted screen and proposed result

The locally inspected collaborator frontend at commit 2e5d0532840c370aa013655ce65410a3a494e73f exposes Reports to the director. It displays a classroom roster, delivery/assessed counts and cards for AD, A, B and C. Preserve that organization using the real existing delivery and assessment data.

The director selects period, grade and section, then sees total received deliveries, received-but-ungraded deliveries, graded deliveries and the current AD/A/B/C distribution. The student table shows names, received deliveries, graded deliveries and pending grading. Students currently enrolled but without deliveries appear with zeros. Former students with retained deliveries in that exact period/classroom remain visible and are marked historical.

## Proposed bounded rules

- Reports are a new explicit director-only read permission for classroom summaries. Vice principal, teacher and student do not acquire it in this unit; their already approved screens remain available.
- Director reporting does not grant access to answers, feedback, private files, assessment editing or another role's delivery browser. Expose only the listed summary data and student display names.
- Require a named period, grade and section selection. Bind section to its grade and filter original teaching assignment and retained enrollment by that exact period/classroom. Do not combine equally named sections from other grades or periods.
- Count each logical delivery once. File versions and assessment revisions do not multiply totals. Use only the current assessment revision for the grade distribution. Corrections change the distribution without increasing graded count.
- Pending means a received delivery without a current assessment. It does not mean absent work, missed deadlines or a computed official grade. Do not average AD/A/B/C or infer missing-assignment eligibility.
- Closed periods support historical summaries. Replacements and transfers preserve original delivery attribution. Active current enrollment contributes zero-delivery roster rows only for an active period; historical roster rows come from retained deliveries.
- Student rows are paginated at 50; summary cards cover the full selected scope, not just the visible page. Refresh obtains a new consistent report snapshot.
- No PDF/Excel export, notifications, institutional grade aggregation, cross-classroom ranking or SIAGIE integration in this unit. Notifications will be specified separately after this report unit is verified.

## Approval scope

The human approved the rules above, additive director summary authority, read-only architecture and adaptation of the already accepted Reports screen before implementation. No new database tables or migrations were needed.
