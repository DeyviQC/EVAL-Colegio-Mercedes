# U12 Browser and Certificate Review

Date: 2026-10-07. Predecessor: 737515495785fc334eec02fccf82ad90b20ffc79.
Status: proposed technical selection; no browser download or certificate exception approved by this record.

## Verified starting point

The session UI/read renderer slice is committed. Its evidence is 56 frontend tests, successful type checking/build, and 75 U11 tests / 1,409 assertions with built assets. Three Playwright cases are discovered, not executed. Task 8.2 and gates 1.2-1.8 remain open. No new test run is claimed here.

Installed Playwright Test is 1.64.0. Its browsers.json pins Chromium revision 1248, version 156.0.8078.4. Its installed coreBundle.js selects the Chromium Chrome-for-Testing path builds/cft/<version>/<platform archive> at https://cdn.playwright.dev. General download mirrors also include https://playwright.download.prss.microsoft.com/dbazure/download/playwright. Redirect destinations must be inspected before following them; an unapproved host requires review. Do not run the default multi-browser installer or system dependency installer.

The approved UI tooling scope explicitly excluded browser binaries and required a separate trust review. Current Playwright configuration sets ignoreHTTPSErrors to false. The existing PHP HTTPS tests validate the disposable certificate against an explicit CA file, but that does not establish browser trust. Client certificate configuration is not server CA trust.

## Recommended bounded option A

Approve downloading only the pinned Chromium executable and its required installer support artifacts over HTTPS from the named CDN hosts, into an isolated development cache outside tracked source. Inspect the installer dry run and redirects before transfer, record actual destinations/version and artifact hashes, and stop for an unlisted destination. No global browser installation, administrator operation or OS trust-store change.

Approve investigating and implementing a per-run Chromium public-key (SPKI) certificate exception for the disposable loopback harness only. Generate the harness key locally, compute the SHA-256 SPKI pin, use a disposable browser profile, and keep ignoreHTTPSErrors false. Verify support in the actual executable before claiming success. Do not use a blanket ignore-certificate-errors flag. Restrict requests to the owned loopback origin; block external application requests.

This is a narrow certificate-validation exception, NOT CA trust: a matching key may bypass certificate validation failures. It does not prove browser certificate-chain, hostname or expiry validation for that key and must never be reported as institutional TLS acceptance. PHP/Node strict certificate verification remains independently tested. Browser negative probes must reject an unpinned key and reject the self-signed endpoint without the exception. If isolation, executable support or negative probes fail, stop rather than broaden the exception.

Advantages: permits actual DOM/session/local-asset tests without modifying machine trust; the harness exception is disposable and auditable. Disadvantages: does not validate institutional browser TLS deployment and requires explicit approval of the exception. Impact: browser harness/config/E2E tests and evidence only; no spec/design rule, academic authorization, production deployment or checkbox closure.

## Alternative option B

Keep browser execution pending until a proper development/institutional CA trust mechanism is selected and approved. Do not install a root certificate by inference. Advantages: avoids a certificate exception and can produce full browser TLS validation evidence. Disadvantages: blocks immediate browser HTTPS journeys and requires a separate trust deployment decision. Impact: review/evidence only until that mechanism is approved.

## Proving boundary after selection

1. Execute the existing three browser cases against owned HTTPS processes and local assets; report actual results, not discovery counts.
2. Add real DOM login/logout/expiry and unpinned-certificate negative cases using disposable accounts/database only. Record cleanup of owned processes/profiles.
3. Record external request blocking and local asset loading without confusing loopback evidence with physical LAN/WLAN or a network outage experiment.
4. Keep 8.2 unchecked: live role navigation/academic flows and physical LAN evidence remain missing. Load runner/seeds/thresholds remain separate 8.3 prerequisites.

The current session response exposes authenticated status and CSRF, not roles or menu context. Existing reads need resource locators; Vice Principal support is duty/target-bound. A subsequent server-owned navigation/context contract must be reviewed before menus or general lists. No client role selector, unrestricted roster, invented institutional labels or public reference writer follows from browser approval.

## Normative traceability

Tasks 1.6 and 1.7 require applicable supported tooling and proving harness selection; task 8.2 requires browser role/local-operation evidence. docs/u12-ui-tooling-review.md requires browser downloads/trust to be reviewed separately. This proposal adds prerequisite evidence and selects a test mechanism; it does not change approved product behavior. Prototype, odd/, six specs, design and task checkboxes remain unchanged. No apply, remote Git operation or integration of 2e5d053.

Primary tooling reference: https://playwright.dev/docs/browsers (version-specific binaries, scoped browser installation and local cache options). Chromium SPKI behavior must be verified against the installed executable; no current executable support has been tested in this review.
