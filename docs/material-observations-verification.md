# Private Material Observations Verification

Date: 2026-10-08. Predecessor: ab2a020. The human approved the concrete OpenSpec proposal, design and screen mapping through Continue before application implementation. This is one sequential local unit; no remote Git operation is included.

## Delivered behavior

Vice principals use Material supervision to select an original academic assignment, consult its materials and protected files, and create a private observation bound to the current retained material revision. The server derives the original teacher recipient. The original teacher responds from Materials; the vice principal explicitly reviews the response. The first workflow is Observed -> Responded -> Reviewed, with one retained response. Separate material editing remains available through the previously approved maintenance command.

The director, students and replacement teachers cannot read these private bodies or respond on behalf of the original teacher. Historical original-teacher and authorized vice-principal reading remains available after assignment replacement or period closure; private writes then freeze. Observation state and text are not included in student material projections or generic notification emission.

Migration 000016 adds immutable observations, immutable response/review events and guarded state pointers. Runtime grants permit only required inserts and pointer updates. Commands revalidate current credentials and roles, lock the original assignment/period/author under the existing write guard, and compare expected revision/event identifiers. All visible event timestamps use the existing Peru display component. Unknown outcomes require a matching successful consultation and explicit manual review; no automatic replay occurs.

## Verification

- `backend/scripts/manage-dev.ps1 verify-observations`: 94 checks against real local MySQL. Covers wrong roles, original recipient, field bounds, protected files, revision snapshots, stale writes, rollback of observation/reply/review, concurrent reply/review conflicts, 50+4 private pagination, withdrawal, teacher replacement, identity revocation, closed-period history and immutable retained evidence. Failure probes are removed in a finally block before browser verification.
- `backend/scripts/manage-dev.ps1 verify-observations -BrowserOnly`: 20 real browser checks using the owned pinned HTTPS certificate and normal authenticated teacher, vice-principal, student and director sessions. Covers publication, supervisor discovery and download, the full UI workflow, student/director exclusion, CSRF, stale state and a real committed observation whose response is deliberately lost. The subsequent consultation and explicit review produce one observation, without replay.
- Frontend: 145 tests passed, including eight observation contract/transport cases. These include rejecting a wrong-material page and preventing an older in-flight consultation from qualifying recovery. TypeScript/tooling checks and Vite build passed.
- U11: 99 tests / 2397 assertions; two existing optional skips. Material maintenance browser regression: 22 checks passed. Library browser regression: 19 checks passed.
- New PHP files and route syntax, browser helper syntax and Git whitespace checks passed.

Final inspected local snapshot: TLS and restricted runtime, 30 tables / 16 migrations, 202 accounts, 14 periods, 76 assignments, 195 enrollments, 294 materials and write ordinal 1497.

The owned application was stopped and restarted, then vice-principal and teacher windows were opened and authenticated. The aggregate database snapshot remained identical across restart. MySQL and retained files were not reset.

Private screenshots and machine receipts stay under ignored `.local/eval-dev`; passwords and database files are excluded from Git. Synthetic fixture records remain retained. An initial browser assertion ran before the refreshed control finished leaving its busy state; the helper now waits for the observable enabled control. This was a verification timing issue, not a database rollback or duplicate write.

## Remaining scope and gates

Multiple response chains, observation editing/deletion, successor routing, director intervention, observation notifications and attachment replacement are not delivered in this unit. Institution-wide collection/indexing, grade-register/export decisions, broader account/import management, durable database storage and tested backup/restore remain separate work. Physical school LAN/WLAN, real devices, load thresholds, server deployment and institutional acceptance remain open. These selected checks do not certify those gates or complete the entire product.
