# Retained Material File Replacement Verification

Date: 2026-10-08. Predecessor: fd8e595. The human approved the concrete OpenSpec proposal, design and existing-screen mapping through Continue before application implementation. This is one bounded sequential local unit.

## Delivered behavior

The original teacher replaces an available material's file while its original assignment and period are active. The command preserves material identity, original publication, author, assignment, metadata and every committed attachment. A replacement adds a file version and advances the existing material revision pointer. It does not resolve or rewrite observations.

Current eligible students receive the current attachment through existing Open/Download actions. Historical students receive the latest file inside their authorized enrollment windows. Materials and Library select filename and bytes from the same visible revision as metadata. Withdrawal blocks all student file versions; restoration restores original eligibility. Students have no browse-all file history. Original and eligible successor teachers can consult protected retained versions; a successor cannot replace predecessor files or read predecessor private observations. Vice principals use purpose-bound supervision, including protected observed/replied revision links. Director obtains no new access.

Migration 000017 extends the material revision operation with file_replaced and adds immutable material_file_versions. Original migrations and records remain retained. Foreign keys/context checks bind the file to its replacement revision, and pointer triggers require that file before advancing the pointer. Runtime adds only required insert columns. Expected revision, live credentials, original authority, available state and identical-current-content checks run inside the existing write guard. File candidates are validated and finalized under an owned storage lock; finalized integrity is checked before the transaction.

Failed or uncertain candidates are retained until guarded reconciliation; possibly committed bytes are never removed based only on a lost response. LocalMaterialStorage reconciliation now recognizes both original publications and retained replacement files. It removes only unreferenced candidates while holding the existing guard and storage lock. This adds no automatic production cleanup or physical purge of committed versions.

## Executed evidence

- Real local MySQL: 138 checks. Covers role/input/invalid-extension/identical-content denials, original row and byte retention, current and historical downloads, later-filename Library privacy, explicit unauthorized-version denial, observed/replied file contexts, stale expectations, withdrawal/restoration, metadata edits retaining the current file, three rollback stages, one-winner concurrent replacements, 50+2 file pagination, successor/read-only authority, credential revocation, closed-parent denial and immutable file persistence. Includes reconciliation checks for all 52 committed file versions and three candidates from known rollback probes. The closed-parent material is an explicitly guarded synthetic fixture, not normal publication into a closed period.
- Real Chromium: 16 checks across teacher, student, vice principal and director. Covers real publication/replacement controls, retained version consultation, byte-identical protected original/current downloads, student/director history denial, an observed-file link retaining original bytes, stale private response and missing-CSRF denial. A second upload really commits before its response is lost; matching consultation and explicit review leave exactly one new file version.
- Frontend: 151 tests passed, including six file-history/multipart/revision/recovery/input/URL cases. TypeScript/tooling checks and Vite build passed.
- U11 regression: 99 tests / 2397 assertions, two existing optional skips. Its existing validator tests cover empty/oversized/unsupported/macro-bearing files. Material maintenance regression: 73 MySQL and 22 browser checks passed. Private observation browser regression: 20 checks passed. Library browser regression: 19 checks passed.
- New and modified PHP syntax, browser helper syntax and Git whitespace checks passed.

Browser verification exposed an omitted route dispatch and missing visible filename; both were corrected. Early helper expectations also waited for a notice replaced by refreshed cards and appended a second question mark to an existing query string; those checks now wait for the observable filename and use canonical version URLs. Two server fixtures and intermediate named browser publications remain retained; no reset of confirmed data was performed. No reconstructed universal test-first history is claimed.

Final inspected development snapshot: TLS and restricted runtime, 31 tables / 17 migrations, 217 accounts, 16 periods, 84 assignments, 207 enrollments, 310 materials and write ordinal 1766. These include retained synthetic verification records. The file-version card screenshot was visually inspected; timestamp/link separation was subsequently adjusted.

The owned EVAL application was stopped and restarted, then teacher and student windows were opened and authenticated. The aggregate database snapshot was identical across restart; MySQL, committed uploads and academic records were retained.

Private browser screenshots and machine receipts remain ignored under .local/eval-dev. Credentials, database, uploaded files, dependency/runtime files and builds are excluded from Git. Supported command: backend/scripts/manage-dev.ps1 verify-file-replacement; BrowserOnly skips new server fixtures.

## Remaining scope and acceptance

Version deletion/physical purge, rollback-to-version commands, bulk replacement, new notifications, multiple attachments and broader observation correction chains remain outside this unit. Durable storage, tested backup/restore, school LAN/WLAN and real devices, load thresholds, server/deployment choice and institutional acceptance remain open. Retained candidate reconciliation and local restart checks are not power-loss or production durability certification. Git publication/main integration remains a separate remote operation.
