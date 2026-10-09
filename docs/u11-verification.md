# U11 Acceptance Reference Handoff

Date: 2026-10-07. Approval predecessor 6985d34, recorded in docs/u11-human-approval.md; U10 predecessor f9ecb66. Bounded verified scope: tasks 7.2–7.5 at the minimal immutable reference and isolated policy/HTTP boundary.

Actual initial RED before submission migration/handler: 2 tests / 15 assertions, missing-table error and closed-parent admission failure. A subsequent integration run exposed MySQL FOR UPDATE privilege requirements on immutable activity references; grant UPDATE(teaching_assignment_id) solely enables locking, while the existing immutable trigger still denies every attempted update. The handler timestamp accessor was corrected to the existing OperationalBoundary timestamp. Initial GREEN: 2 / 21. Additional tests are current verification, not reconstructed RED.

Final `backend/scripts/local.ps1 -Command test -Unit U11`: **68 tests / 1,114 assertions**, zero failures/errors/warnings. PHP 8.4.26, PHPUnit 12.5.38, existing Illuminate 13.35.0. MySQL 8.4.9, eval_u11_test, 17 tables, TLS_AES_256_GCM_SHA384. Forward 000008 migration runs only for U11; U10 reference migration is reused. No previous schema history or domain specs were rewritten.

Submission reference stores permanent generated ID, required restrictive student/activity/original assignment/accepted-under enrollment FKs, actual UTC microsecond acceptance timestamp and positive bounded operation key. Update/delete triggers retain accepted context even for maintenance; runtime INSERT allowlist excludes explicit identity. Cross-table route equality and enrollment compatibility are enforced by the guarded acceptance service, not claimed as database CHECK invariants.

AcceptSubmissionReference accepts activity_id only. No deferred payload representation is selected: content remains unavailable. Guard-first discovery reloads original activity/assignment/parent plus actor/original-teacher identity union and complete requested-period student enrollment history. Canonical locks and rediscovery precede active actor/current credential/current student role/active parent/exactly one active occupied enrollment and matching period/grade/section validation at prospective K. Closed assignment alone is not a denial. Immutable route/context are copied from authoritative records; timestamp/key derive from allocated boundary. Typed assignment evidence records submission/activity/enrollment IDs without creating a submission lifecycle.

| Evidence | Verified behavior |
|---|---|
| Route/context | Original activity assignment and current compatible enrollment copied; actual timestamp equals evidence timestamp, actual key used |
| Admission | Closed parent denied despite active children; closed assignment alone permits minimum original-route acceptance; missing/historical/mismatched enrollment rejected |
| Tampering/roles | Client student/teacher/recipient/scope/enrollment/time/content/late fields denied; invalid/missing activity, role/credential/status revocation denied |
| Retention | Runtime update/delete denied, maintenance immutable triggers reject, enrollment FK restricts deletion; activity locking privilege cannot rewrite its original route |
| Rollback | Real source INSERT and real ledger INSERT faults restore reference/key/event counts and end transaction |
| Retry | Injected retryable deadlock before source INSERT yields confirmed rollback, complete second attempt, one ordinal/reference only; not a claim of a spontaneous engine deadlock |
| Uncertain outcome | Actual commit plus injected lost acknowledgement commits once, correlation matches ledger and no replay occurs |
| Five real races | Acceptance-first/transfer-first, acceptance-first/period-closure-first, assignment closure before acceptance; retained old context versus refreshed incompatible scope is observed |
| Persisted integration | Actual transfer/replacement/period/catalog changes retain original assignment/accepted enrollment; real local session HTTP read exposes corrected synthetic labels and denies another student's accepted reference |

Races use separate PHP processes/connections, actual source mutation held before commit and observed academic_write_guard FOR UPDATE waiter. Acceptance race tests complement retained U10 period/activity races and U6 period/transfer races; no new retry/replay policy is introduced. PersistedAcademicReferences implements the internal reader; default HTTP composition remains fail closed unless an explicit server binding is supplied. No public acceptance writer route is activated; reserved U9 routes remain admission-only. Production identity label binding/full Laravel server are not inferred from test composition.

The named HistoricalAcademicReadTest, TeacherReplacementTest and AcademicPolicyHttpTest now include persisted reference scenarios. U11 database subclasses also rerun their existing coverage: original student/teacher ownership, replacement successor denial, four-role HTTP matrix after period closure, retained cleanup context and cross-user denial. An initial expanded run exposed the U8 worker database allowlist; it now explicitly permits only eval_u8_test or eval_u11_test. The corrected expanded suite passes without skips.

Regression: U1 43/150, U2 27/182, U3 23/154, Auth 24/127, U4 26/323, U5 21/307, U6 22/291, U7 26/341, U8 28/336 (one skip), U9 40/653 (two skips), U10 18/278, U11 68/1114. Total: 366 tests / 4,256 assertions; zero failures/errors. The three skips are U11-only persisted scenarios deliberately omitted on older schemas; all three execute successfully in U11. Scoped PHP/PowerShell syntax and whitespace checks pass.

Tasks 7.2–7.5 are complete at the approved reference boundary. Global gates 1.2–1.8, institutional label mapping, public acceptance integration, browser/LAN/load/UI evidence and U12 remain pending. No content, grading, processing, resubmission/versioning, complete availability/deadlines, apply, remote Git or 2e5d053 integration.