# EVAL Academic Contract Slice

Approved scope: academic-foundation task 8.1, not a React application or live server.

Verified local runtime: Node 24.21.0, npm 11.19.0, TypeScript 7.0.2. Package lock pins compiler/platform package integrity; dependencies are development-only. No external runtime assets or package requests are made by the client adapter. Local TypeScript installation was approved; browser/load/UI tooling remains unselected.

From `frontend`:

```powershell
npm ci --ignore-scripts --no-audit --no-fund --registry=https://registry.npmjs.org
npm run typecheck
npm test
```

With the verified npm cache populated, `npm ci --offline --ignore-scripts --no-audit --no-fund` also works. This is tooling-cache evidence, not school LAN or application-outage acceptance. Test output is isolated in ignored `.test-build`; dependencies in ignored `node_modules`. The runner uses in-process Node isolation to avoid the observed Windows sandbox subprocess restriction.

`contracts.ts` validates allowlisted wire projections with decimal strings for unsigned BIGINT identities and positive signed-64-bit operation keys. Labels remain authoritative server text; no trimming, case folding, accent removal or equality key rendering. The client does not infer role/scope authority, enrollment containment/expiry, period cascades or full activity availability. Server temporal/permission validation remains mandatory.

`api.ts` uses relative same-origin paths, local-session credentials, explicit CSRF on mutations, no cache, redirect rejection and no automatic request replay. It exposes typed reads and selected existing command routes. Transfer success is returned only after validated server acknowledgement. Lost/invalid mutation acknowledgements retain an uncertain outcome; the adapter does not invent a correlation ID when no server response exists. Applications must display the uncertainty and reconcile through an approved server mechanism rather than resubmit blindly.

`submissionAdmission` targets the existing reserved U9 endpoint: it currently returns denial/unavailability and does not expose the internal U11 persistence service. No submission processing/content/recipient selector is implemented. Activity writes, institutional teacher-label binding and a full HTTPS listener remain separate prerequisites.

Tests use injected fetch responses and real platform Request/Response behavior. They do not exercise a network server, browser rendering, real frontend/backend end-to-end schema agreement, React, TLS or school LAN/WLAN. Existing backend U11 integration tests separately verify actual persisted routes/history/authorization. Tasks 8.2-8.4 remain open.

## Session UI checkpoint

React/Vite/Tailwind and Playwright Test were explicitly approved subsequently; see docs/u12-ui-human-approval.md and docs/u12-ui-verification.md. The historical contract-only boundaries above remain the original 8.1 record. The current build includes a session shell and reusable read-only renderers; no role navigation or academic operation forms are wired. Renderer tests use synthetic fixtures, not application data sources or institutional profiles.

From frontend: `npm test` runs 56 Node contract/session/server-rendering tests; `npm run typecheck` checks application and tooling/spec sources; `npm run build` typechecks then builds with Vite's native config loader (the bundled loader hit Windows sandbox spawn/native-binary errors). `npm run test:e2e:list` discovers 3 future browser cases without launching/downloading a browser. No browser execution or certificate bypass is authorized by these commands.

Compiled `dist` is local-only and ignored. The owned HTTPS backend harness can mount its index/JS/CSS under EVAL_UI_ENABLED=1 and preserve same-origin session paths. Build before running `backend/scripts/local.ps1 -Command test -Unit U11` from the repository root to include the actual TLS asset scenario; without dist that single test skips explicitly. The test-managed listeners stop in teardown. `npm run dev` is limited to 127.0.0.1 and supports UI development; its HTTP origin does not satisfy the approved HTTPS authentication contract and is not an authenticated integration command.

Default SessionShell shows the confirmed session/empty-workspace state. AcademicReadView components validate DTOs before rendering; live role menus/resource selection and institutional label binding remain pending. No ID locator entry, client role picker or invented profile names. Browser download/trust, E2E, live role operations, LAN/outage and load acceptance remain future evidence; task 8.2 is not completed.

## Approved isolated browser execution

The subsequent approved navigation contract connects section buttons to GET /academic/navigation, derived from actual stored role grants. Frontend tests now total 61; browser cases total 6 with four-role section visibility and resource denials. Resource discovery and operation forms remain unconnected; selecting a section states that limitation explicitly. See ../docs/u12-navigation-verification.md. Historical counts and discovery-only statements above describe earlier handoffs.

The later browser handoff executes five HTTPS DOM/certificate cases through the owned U11 PHPUnit harness. See ../docs/u12-browser-verification.md for prerequisites, invocation and exact results. Chromium 156.0.8078.4 is isolated outside source; a per-run SPKI exception is explicitly approved and is not institutional CA trust. No global certificate change or blanket ignoreHTTPSErrors is used. The harness supplies disposable credentials; do not point these tests at institutional data. Task 8.2 remains incomplete.
