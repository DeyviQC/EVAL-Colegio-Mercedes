# U12 Backend Journey Evidence

Date: 2026-10-07. Predecessor ac273bd. Scope: docs/u12-local-operation-scope.md; backend portion of 8.2, not task completion.

Added LocalAcademicOperationTest to the existing U11 runner/schema. Initial harness loading failed due to a private helper colliding with an inherited protected method; renamed it. Actual RED after loading correction: three tests / 24 assertions, three assertion failures against an explicit 404 composition stub. No historical product RED is invented: these are new integration-journey tests of previously implemented services.

After wiring actual local authentication/controller composition, the next run exposed a missing POST argument and an incorrect expectation of 403 rather than the existing approved 409 state-conflict representation on assignment creation. Corrected those tests, not product rules. GREEN: three tests / 134 assertions. Additional catalog case/trim/accent assertions were then added.

Final full U11 command: `backend/scripts/local.ps1 -Command test -Unit U11`: **71 tests / 1,254 assertions**, zero failures/errors/skips. New journey portion contributes **3 tests / 140 assertions**. Isolated MySQL 8.4.9 eval_u11_test; no new migration/permission or dependency. Frontend `npm test`: 36 passed; `npm run typecheck`: successful. Scoped PHP syntax and whitespace checks pass.

| Journey | Actual evidence |
|---|---|
| Director/Admin | Case-insensitive outer-trim period/catalog equality, accents distinguished; planning in planned parent without operational authority; active enrollment with mandatory period and date outside its calendar; no open-end expiry; successful immediate transfer exposes successor only after confirmation, original section retained and shared operational boundary |
| Unsupported transfer | Scheduling/effective date/correction/client key/student selectors rejected without ordinal/event mutation or open transaction |
| Closed parent/four roles | Closing parent leaves active assignment child unchanged; Vice Principal enrollment closure denied, valid assignment closure permitted; Director enrollment cleanup permitted; Director/original teacher/student read retained original assignment/accepted enrollment; Vice Principal submission read denied |
| New work/history | Closed-parent activity/normal/late submission/assignment creation denied without mutation; history remains readable after valid residual cleanup |
| Closed assignment only | Existing active-parent/current-compatible enrollment permits original-route acceptance through internal U11 service; authenticated student reads accepted history; reserved public POST still returns 404 with no mutation; another student cannot read it |

Each role uses an actual local credential/session cookie/CSRF request sequence. Fixture password writes are maintenance-only test setup; synthetic labels are explicitly test-bound, not institutional profile implementation. Responses are processed in-process; HTTPS scheme is declared in Request construction, not verified over a TLS socket. No browser, network server, Internet-loss, physical LAN/WLAN, UI or load evidence is claimed.

8.2 remains unchecked, as do 8.3/8.4 and gates 1.2-1.8. No production code change, public writer activation, prototype edit, deferred capability, apply, remote Git or integration of 2e5d053. See docs/u12-runtime-review.md for the next concrete human technical choice.
