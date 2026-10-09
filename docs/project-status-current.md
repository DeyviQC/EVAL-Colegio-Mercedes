# EVAL Current Project Status

Date: 2026-10-08. Reviewed predecessor: fd8e595; subsequent retained material file replacement is recorded in docs/material-file-replacement-verification.md. This assessment compares openspec/config.yaml, current approved change specifications, the functional-completion roadmap and recent verification receipts. Percentages are planning estimates, not automated completeness, coverage, certification or elapsed-work metrics.

## Two different scopes

The approved local functional core is substantially implemented: authentication/roles, academic catalog/periods, enrollment, assignments/replacement, bounded accounts, protected materials with metadata edit/withdraw/restore, retained attachment replacement and private vice-principal observations, activities/deliveries, assessment/corrections, classroom summaries, notifications, course Library/search and retained history. Estimate: backend approximately 80–90% (working figure 85%), frontend approximately 75–85% (working figure 80%). Remaining work includes wider flows, usability/accessibility, institutional data/setup and broader acceptance. These figures apply only to that bounded local core.

The complete institutional product described in openspec/config.yaml is broader. A coarse equal-weight review of its 12 planned modules gives approximately 50–60% functional coverage (working figure 55%). Module coverage is not weighted by complexity or remaining effort; later synchronization is counted as planned scope, not as an immediately approved implementation. This estimate must not be calculated by averaging the two local-core percentages.

| Planned module | Current evidence | Remaining work |
|---|---|---|
| User administration | Partial: real teacher/student accounts, password maintenance, deactivate/reactivate, credential revocation | Wider administrator succession/roles/import and institutional policy |
| Subjects/areas, grades, sections | Local functional implementation | Institutional catalog validation and broader acceptance |
| Teacher assignments | Local planning/activation/closure/replacement and preserved lineage | Institutional workflows/acceptance |
| Educational materials | Partial: publish, scoped consultation, protected download, historical eligibility | Metadata edit/withdraw/restore verified; attachment replacement and private supervision verified; physical purge and institutional acceptance remain |
| Material review/private observations | Local original-teacher private workflow and purpose-bound vice-principal material review verified | One response per observation; wider correction chains, notifications and institutional acceptance remain |
| Activities/submissions | Local functional implementation with retained versions, routing and recovery | Broader institutional/network acceptance and outstanding lifecycle requirements if separately approved |
| Auxiliary grade register | Partial: per-delivery AD/A/B/C, feedback, corrections, student views and classroom summaries | Confirm consolidated register/period aggregation requirements and exports; do not infer official grades |
| Institutional library | Partial: authorized course material Library | Scope of a separate institutional collection/categories, if required |
| Local educational search | Partial: full selected-course material title/description/filename search | Stored-document content extraction/indexing and institution-wide search rules |
| Classroom screen/board presentation | Dedicated functionality pending; protected inline PDF/image open exists | Define and verify the classroom presentation journey |
| Local/remote synchronization | Pending/deferred decisions | Remote scope, reconciliation/conflict policy and offline/network evidence |
| Auditing/backups | Partial: retained lifecycle/account events | Audit consultation, durable storage and tested backup/restore |

## Verified engineering evidence

Latest frontend verification: 151 tests passed; TypeScript/tooling and Vite build passed. Latest full U11 backend regression: 99 tests / 2397 assertions, two optional skips. That suite was run during material-file-replacement integration. The responsive interface refinement is verified in docs/tablet-interface-verification.md, including deferred teacher maintenance requests, motion reduction and emulated tablet/mobile layouts. Physical school tablet acceptance remains open. Separate real runtime/browser receipts cover materials, enrollment, assignments, accounts, activities, grading, reports, notifications, history, original contexts, Library, lost-response recovery and Peru time display. These tests demonstrate selected scenarios, not exhaustive institutional acceptance or a percentage of requirements.

The latest runtime snapshot contains 31 tables / 17 migrations, 314 accounts, 16 periods, 483 assignments, 297 enrollments, 332 materials and ordinal 2942. Many records are synthetic verification fixtures. The human-authorized school sample adds fictional 1?5/A?H contexts, ten curricular areas and illustrative learning data; see docs/school-sample-verification.md. Data volume does not increase software-completion percentages. Actual locally running application and MySQL persistence are established; school deployment readiness is not accepted.

## Main risks and delivery gaps

1. The owned MySQL datadir remains under a temporary runtime directory. Move to durable storage and prove backup/restore before institutional use.
2. Physical school LAN/WLAN, Internet-loss with power/network intact, real tablets, load/concurrency thresholds and server deployment/IIS-or-Apache acceptance remain open.
3. Teammates need reproducible dependency/runtime setup; Git deliberately excludes credentials, database, uploads, dependencies and builds. A repository clone is not an installed application.
4. The current branch is feat/academic-foundation. The initial review found six local commits beyond origin/feat/academic-foundation; subsequent maintenance and private observations are also local and main integration remains pending. This is local Git evidence, not a fresh remote fetch.
5. Earlier receipts and OpenSpec foundation planning gates contain historical or stale status. Keep history, but reconcile a current requirements-to-evidence matrix rather than marking gates complete merely because screens work.

## Recommended priorities

1. Reconcile current requirements/evidence and team integration/setup, preserving this verified baseline.
2. Metadata maintenance and private vice-principal observations are delivered; attachment replacement is also delivered; specify any required wider correction workflow according to institutional priority.
3. Define and implement required grade/register reporting/export scope, then wider account/import needs.
4. Prove durable storage and restoration, school-network journeys and load/deployment acceptance.
5. Address the remaining institutional library/content-search, classroom presentation and synchronization according to their agreed priority; avoid spending all remaining work on cosmetic refinements.

This report authorizes no new product policy, production use, remote mutation or closing of planning gates.
