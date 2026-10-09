# Academic Write Recovery Verification

Date: 2026-10-08. This refines the already approved activity/delivery lost-response requirement: the client must consult the affected result before an explicit next action and must never replay an uncertain mutation automatically. No backend, schema or permission changed.

## Delivered behavior

The shared academic client retains the uncertain request target and a local operation revision. Successful decoded consultation must start after uncertainty is known and match that target: publication uses its course activity list; submission its activity delivery list; closure its exact activity detail; grading its assessment history or a delivery page containing that delivery. Unrelated, malformed/failed and earlier in-flight consultations do not enable review. The activity detail response must match the requested ID.

He revisado el resultado is disabled until the matching consultation qualifies. Consultation never clears uncertainty automatically; the user still explicitly reviews. Reviewed state is then acknowledged on screen. A client-local pending-write barrier prevents another card's write from overwriting the recovery target. This is a UI coordination guard, not a database locking protocol or cross-browser serialization guarantee. The current implementation permits a manual next action after consultation/review; it introduces no idempotency key or final-outcome resolution service.

## Executed evidence

- 127 frontend tests passed. Five new cases cover required matching consultation, unrelated/malformed reads, reads begun before uncertainty, exact closure detail, assessment target matching and pending-write coordination. TypeScript/tooling checks and Vite build passed.
- The real Chromium verifier exercises publication, submission, grading and closure with an intentionally lost response after a real successful HTTP response from the server. The forwarder verifies TLS against the owned local certificate, allows only the fixed loopback origin, retains actual request/session headers, consumes the response and aborts delivery to the browser. It uses no blanket TLS bypass.
- 11 browser checks passed: review stays disabled immediately after loss and after failed consultation; successful matching consultation enables explicit review; exactly one publication/submission/assessment/closure request is forwarded per operation; one delivery version and one assessment revision are retained; the final activity is closed. A final repeat validates the reviewed-state message and screenshot, which was visually inspected. Named synthetic verification activities and their records are retained local development data.
- The initial harness TLS forwarding failed before reaching the application; forwarding was corrected to trust only the owned certificate. A subsequent harness assertion incorrectly required 200 for a successful 201 creation; it was corrected to the actual 200/201 contract. That synthetic publication remains retained. No backend behavior was loosened.

Run backend/scripts/manage-dev.ps1 -Command verify-write-recovery. Private screenshots and receipts remain ignored. Node syntax and Git whitespace checks passed. Full domain mutation regression suites were not repeated for this frontend-only refinement; the verifier itself exercises the four actual writes. The finished local commit is its sequential handoff; no remote Git operation is included.

## Remaining acceptance

This browser test covers deliberately dropped acknowledged responses, not every physical outage, unresolved commit timing, crash or institutional network fault. Manual retry after review, multi-tab outcome resolution, server idempotency, cross-browser concurrency and durable recovery remain separately scoped. Existing role boundaries, academic routing, retained evidence and institutional backup/LAN/load acceptance gates remain unchanged.
