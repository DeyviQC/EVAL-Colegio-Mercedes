# U12 Browser Harness Verification

Date: 2026-10-07. Predecessor: 737515495785fc334eec02fccf82ad90b20ffc79. Human approval and the exact Google archive destination extension are recorded in docs/u12-browser-human-approval.md.

## Installed boundary

Playwright 1.64.0 installed Chromium 1248 / Chrome for Testing 156.0.8078.4, FFmpeg 1013 and Winldd 1007 into LOCALAPPDATA/Temp/opencode/eval-u12-browser. No global browser or OS certificate store change. Download URLs came from the installed CLI dry run; HTTPS HEAD inspection found the approved Chromium redirect to storage.googleapis.com/chrome-for-testing-public/156.0.8078.4/win64/chrome-win64.zip (200, 207073013 bytes), and support archive redirects to playwright.download.prss.microsoft.com. Downloads completed from these approved paths.

Installed executable SHA-256 receipts (not publisher signatures or archive hashes):

- chrome.exe: 7F08161BBEB2853FFE1780A9DED4985087F52EFB9725D7D8207AABDD42F7CFB9
- ffmpeg-win64.exe: 7CCD7BBFF94976033F130C802C5AA40801F640D0E7DFB7DE12984E88133F329B

Installer-managed archives were not retained, so archive SHA-256 values are unavailable. Winldd support installation completed; no Winldd executable hash is claimed. Browser execution also asserts browser.version() equals the pinned version.

## Actual execution and correction

The PHPUnit HTTPS fixture starts owned loopback PHP/Node workers and passes only browser configuration, a disposable synthetic credential and the per-run public-key pin to Playwright; no database or migration secret reaches browser workers. It hashes the certificate's DER SubjectPublicKeyInfo and supplies only --ignore-certificate-errors-spki-list. Playwright creates disposable profiles, keeps ignoreHTTPSErrors=false, disables retries and uses one worker. Its default screenshot/video/trace capture is not enabled.

Initial execution hit sandbox spawn EPERM; authorized escalated execution enabled the existing child-process harness. Actual DOM login then exposed a 419 csrf_mismatch: the automatic favicon request could bootstrap a competing anonymous cookie/session. The UI-enabled proxy now answers GET /favicon.ico with 204/no-store before authentication. This is an auxiliary-resource correction, with no CSRF relaxation, cookie injection or product-policy change. The same DOM test failed before that correction and passed afterward; no earlier historical RED evidence is reconstructed.

Five browser cases pass: credential fields/no authority selectors; simulated expired session with explicit verification; same-origin assets while external requests are blocked (zero external attempts); actual synthetic-account login/logout with Secure/HttpOnly/SameSite=Lax cookies; actual self-signed certificate rejection both without the exception and with a different key pin. The negative expiry case mocks a 401 response and does not claim an actual timed expiry experiment. Cookie values/passwords are not logged; failure diagnostics redact the synthetic credential.

The SPKI option is a certificate-validation exception for the matching key, not CA trust. The negative probes validate unpinned rejection; they do not prove hostname/expiry/chain validation for the pinned key. Strict PHP certificate verification remains in the existing HTTPS suite.

## Reproduce and results

From the repository root, with frontend assets already built:

```powershell
$env:PLAYWRIGHT_BROWSERS_PATH=Join-Path $env:LOCALAPPDATA 'Temp/opencode/eval-u12-browser'
$env:EVAL_RUN_BROWSER='1'
./backend/scripts/local.ps1 -Command test -Unit U11
```

Full U11 with browser invocation: 76 tests / 1,428 assertions, zero failures/errors/skips; embedded Playwright suite: 5 passed. Frontend npm test: 56 passed, zero skips/failures; npm run typecheck and node --check backend/dev-runtime/https-proxy.mjs pass. Owned PHP/Node/isolated Chromium process inventory after completion: zero. Browser test opts out with an explicit skip when EVAL_RUN_BROWSER is absent; this is not silently claimed as execution.

Task 8.2 stays unchecked: live server-owned role navigation, academic operation UI, complete accepted-prototype browser journeys and physical LAN/WLAN evidence remain missing. Tasks 8.3/8.4 and gates 1.2-1.8 remain open. Existing read renderer fixtures are not institutional profiles. No public reference writer, deferred capability, spec/design amendment, apply, remote Git or candidate integration. Prototype and odd/ remain unchanged.
