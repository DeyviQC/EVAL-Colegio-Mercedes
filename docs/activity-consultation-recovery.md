# Activity Consultation Recovery

Date: 2026-10-08. Bounded refinement of the existing approved activity consultation capability.

A failed activity-list query previously cleared rows and also satisfied the screen's empty-list condition. The screen now displays an empty-course message only after a confirmed successful response. HTTP/network failures clear rows and obsolete pagination, preserve the error, and allow an explicit read retry. A new successful consultation clears the prior error. Publication success remains visible only when the subsequent consultation succeeds; otherwise the consultation error remains visible. No mutation is automatically replayed.

The dedicated Chromium verifier injects HTTP 503 and transport failures at the activity-list request, checks absence of a false empty state and obsolete pagination, and then verifies recovery against actual server rows. A successful injected empty response verifies that a confirmed empty result still displays the empty state. These injections test presentation, not MySQL fault handling or academic-write rollback.

Frontend tests (118) and application/tooling TypeScript/Vite build passed. Runtime evidence is recorded by verify-activity-recovery in ignored private receipts. The change adds no schema, grant or role behavior. Larger functional gaps and remaining institutional gates are recorded in docs/functional-completion-roadmap.md.

Final evidence: 6 recovery/empty-state browser checks passed; the existing activity regression passed 45 MySQL checks and 12 browser checks for publication, submission/update, retained private evidence and closure. Node syntax and Git whitespace checks passed. This finished refinement is a local handoff; no remote Git operation was performed.
