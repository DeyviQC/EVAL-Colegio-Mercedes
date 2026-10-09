# U12 UI and Browser Tooling Review

Date: 2026-10-07. Verified predecessor 808e8d94885163a476dd253d1a20a431b0aee7e4. Pending human technical selection; no installation or application scaffold is authorized by this document. OpenSpec remains normative.

## Evidence and remaining boundary

Task 8.1 is verified. Laravel/Node HTTPS loopback and in-process four-role journeys are verified; 8.2 remains incomplete. frontend/package.json currently contains TypeScript only. No React/Tailwind/build/E2E command is established. No Chrome/Edge executable was found at the three conventional Program Files paths inspected; this is not a complete browser inventory. No browser installation or trust store was changed.

The accepted four-role synthetic prototype remains in docs/prototipos/academic-foundation and must remain separate and unchanged. Its acceptance does not establish production UI, accounts/profile data, browser operation or permissions. Live default submission-history composition still fails closed when authoritative identity labels are unavailable.

## Recommended next bounded slice

Select local React/React DOM, their TypeScript declarations, Vite with its React plugin, Tailwind with its Vite plugin, and Playwright Test. Verify current package metadata/peer compatibility before choosing exact versions; pin the actual compatible releases and retain TypeScript 7.0.2 unless a compatibility conflict requires human review. No framework/application template generator, global install, package lifecycle scripts or CI scaffold.

Authorize package downloads only from HTTPS registry.npmjs.org. Browser binaries are NOT included in this first authorization: review exact download destinations and the isolated TLS trust mechanism separately before installing/running a browser. Do not use ignoreHTTPSErrors, global certificate trust changes or silently substitute mTLS client certificates for server-certificate verification.

Within this slice, build a minimal React/Tailwind local application shell with the existing same-origin session bootstrap/login/logout flow, typed error/loading/empty/expired-session states and reusable read-only period/enrollment/assignment/history renderers. Render validated server DTOs as text, preserving labels and original context; do not add locator-ID entry fields as a product workflow. Verify renderers with declared synthetic fixtures and contract tests; no synthetic fixture may grant access or become a production data source. Real screen navigation/list transport is a separate reviewed integration boundary: current server APIs are resource-specific and Vice Principal support requires a concrete duty/target. Do not invent general roster/support browsing.

Authentication UI uses the existing session contract (authenticated boolean/CSRF token) and credentials endpoint. Do not add registration, account/profile management, a client role/persona selector or infer roles from names/IDs. A server-derived role/navigation projection must be reviewed before live role menus; present read-only renderer fixtures as test evidence, not role authorization or institutional acceptance. Keep public activity/submission writer routes reserved. No academic writer forms in this first shell/rendering slice.

Deliver executable build/type/contract commands and compiled local JS/CSS assets with no runtime CDN/font/telemetry dependency. Install Playwright Test and establish config/test discovery only; do not claim browser E2E execution without an approved installed browser/TLS harness. Preserve existing contract runner and strict typecheck. Keep generated build/test reports and node_modules out of Git; report actual authored and generated diff sizes before the local handoff.

Advantages: follows the approved stack/prototype while exposing API/navigation gaps early, keeps dependency/runtime decisions reviewable and avoids fake institutional profiles or client authority. Disadvantages: shell/renderers alone do not deliver the complete role operation UI or browser acceptance; additional transport/navigation and certificate/browser review remains. Impact: frontend packages/lock/config, shell/styles/renderers/tests and scoped evidence records; no six-spec amendment, no global gate closure. Task 8.2 remains unchecked.

Alternative: approve only Playwright Test tooling first and postpone React/UI work. Advantage: fewer immediate dependencies. Disadvantage: no UI progression and browser executable/trust still needs separate review. Impact: test configuration only; not recommended as the main next slice.

## Traceability and next reviews

| Requirement | Next evidence / boundary |
|---|---|
| 1.6 supported frontend versions | Registry metadata, exact compatible package lock and actual build/type commands after approval |
| 1.7 browser tooling | Approved Playwright selection, config/discovery; actual executable/version and TLS trust remain separate prerequisites |
| 8.2 accepted role flows | Read renderers reflect approved original-context/projection boundaries; subsequent server-derived navigation and role operation forms need real transport tests |
| Session security | Existing same-origin cookies/CSRF, no authority selectors, logout/expiry/error states; no replay of uncertain academic writes |
| Local operation | Locally compiled assets are necessary but not sufficient; later block external requests in browser tests without disabling local access, then obtain physical LAN/WLAN evidence |
| Deferred capabilities | No content/materials/observations/AD-A-B-C/library/account management/full availability; no public reference writers |

Browser trust must be resolved with concrete verified options before actual HTTPS E2E. Institutional profile source, live navigation contracts, minimal public reference transport, LAN setup and institution seed/load thresholds remain separate decisions. No IIS/Apache deployment choice, apply, remote Git or integration of 2e5d053.

Primary tooling references inspected: https://react.dev/learn/build-a-react-app-from-scratch ; https://vite.dev/guide/ ; https://tailwindcss.com/docs/installation/using-vite ; https://playwright.dev/docs/browsers ; https://playwright.dev/docs/api/class-browser . These support available tooling approaches, not installed versions or completed repository checks.
