# U12 Browser Harness Human Approval

Date: 2026-10-07. The human replied "continua" to the explicit choice in docs/u12-browser-trust-review.md, selecting recommended option A.

Approved: pinned Chromium and required support artifacts in an isolated development cache; named HTTPS CDN destinations with redirect inspection; disposable loopback browser profiles and a per-run SPKI exception after executable/negative verification. This exception is not institutional CA trust. No blanket certificate bypass, OS trust change, global installation or administrator operation is approved.

Scope: actual session DOM/local asset tests and negative certificate probes using the existing disposable U11 harness. No role navigation/product decision, public writer, remote Git, candidate integration or task/gate closure is implied. Unexpected download destinations or failed isolation require stopping that action.

## Redirect inspection

Installer dry run confirms Chromium 1248 / 156.0.8078.4, FFmpeg 1013 and Winldd 1007. HTTPS HEAD inspection without following redirects found that the Chromium archive redirects to https://storage.googleapis.com/chrome-for-testing-public/156.0.8078.4/win64/chrome-win64.zip. This host was not in the approved destination list; no binary was downloaded and browser execution remains pending destination approval. FFmpeg and Winldd redirect to the already named playwright.download.prss.microsoft.com host.

Requested extension: only the exact Chromium archive above, with HTTPS redirect inspection still required. This is a download destination extension, not permission for other Google services or application Internet dependencies.

The human replied "continua" to the explicit destination-extension question, authorizing that exact archive. Other unlisted destinations remain unapproved.
