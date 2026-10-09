# EVAL persistent local development runtime

**Status: real persistent development app available; scoped bootstrap, owned lifecycle and local educational journey executed.** Baseline: `b813e92`, `feat/academic-foundation`. Historical blocked attempts below remain preserved. Remaining exhaustive lifecycle faults, durable datadir/backup and physical acceptance are not certified. See docs/local-development.md for current use and evidence.

## Objective and authorized boundary

Provide a stable, explicitly started/stopped loopback EVAL app instead of the 30-minute test preview. Reuse PHP/Node HTTPS, compiled React UI, existing authentication/academic services and owned isolated MySQL at `127.0.0.1:3307`.

- Human approves creating only `eval_dev`, existing migrations `000001-000010`, fresh minimal dev runtime/migration credentials and grants, and four synthetic accounts: director/admin, vice principal, teacher and student.
- Provision once: identity/credential/role/profile inserts through restricted maintenance bootstrap; academic demo data through existing commands. No user-management module, public provisioning endpoint, default public preview password or automatic reseeding.
- No production/test-database writes, MySQL restart, ambient root/SSO credentials, global trust/certificate installation, LAN listener, remote operation, install, commit or push. If privileges require a restart, STOP before restarting and report the remaining bootstrap step.
- Resolve owned credentials internally through a reviewed wrapper; never manually read private state, dump environment secrets, log passwords or pass them as command-line arguments. No grant/configuration changes during ordinary startup.
- Persistent means retained across app starts, not institutional durability: the reused MySQL datadir is under `Temp/opencode` and vulnerable to temporary-directory cleanup. No backup/retention policy is implied.

## Source roots and lifecycle

Source roots: `.gitignore`; `backend/scripts/dev.ps1`; `backend/dev-runtime/{config.php,capability.php,provision.php,open-browser.mjs,tests/}`; narrowly scoped `backend/scripts/database.php` and `backend/dev-runtime/router.php`. Only capability/config/tests/controller were added in T1; remaining paths are future work. Reuse existing migrations/services/UI; no scaffold or broad documentation.
Preserve the pre-existing uncommitted history fixes in `router.php` and `backend/tests/Feature/Academic/LocalHttpsRuntimeTest.php`; do not overwrite, revert or absorb them into launcher rollback. The latter is not a launcher edit target.
Add `/.local/` ignore before storing `.local/eval-dev/` configuration, user-protected secrets, private materials and browser profile outside public roots; enforce Windows user ACLs and reject unsafe paths. Node/browser receive no DB/migration credentials; PHP receives runtime credentials only.
Ordinary start validates exact `eval_dev`, schema, owned MySQL identity, fixed HTTPS origin and exclusive lock; no migration/reset/seed. Persist session encryption key, origin and private materials; generate a fresh proxy secret per run. Keep existing CSRF, cookie, session/throttle and no-replay behavior.
Stop only exact owned PHP/Node/browser identities (PID, start time, executable/arguments); retain DB rows, active periods, materials and keys. Never stop shared MySQL, auto-reconcile files or infer ownership from port/process name alone.
Open only installed isolated Chromium through Playwright, dedicated profile, own certificate SPKI exception and `ignoreHTTPSErrors:false`; no broad TLS bypass/global CA. Pin exception is not chain/hostname/expiry validation; explicitly validate/renew the owned certificate.

## Stable tasks, routes and rollback

Each task is a future parent-delegated bounded worker route, executed sequentially; no child launch in this preparation. Two or more nontrivial files/shared boundaries trigger preparation and delegation. Completion requires evidence, not a checkbox or commit.

- [ ] **T1 - Isolation and one-time bootstrap, test first.** Route: bootstrap/test worker; trigger: controller/config/provision/database boundary plus tests. Prove exact DB/ownership rejection, minimal privilege separation, secret isolation, partial-bootstrap recovery and repeat provisioning without reseeding. Capability check uses only internally resolved authorized credentials, no mutation; absent CREATE USER/GRANT authority stops provisioning without restart. Rollback: remove T1 additions/isolated adapter hunks only; retain populated `eval_dev`, identities, credentials and data pending explicit cleanup authority.
- [ ] **T2 - Owned start/stop and pinned Chromium, test first.** Route: lifecycle/browser worker after T1 verification; trigger: PowerShell controller, browser helper and lifecycle tests. Prove duplicate-lock denial, PID reuse/foreign-port refusal, readiness/partial-start cleanup, persistent origin/key and exact-owned shutdown; test unpinned certificate rejection. Rollback: disable/remove launcher/browser behavior and its tests only; preserve T1 data, materials, keys and baseline history fixes.
- [ ] **T3 - Persistence, login, regression and human opening.** Route: integration/evidence worker after T2 verification; trigger: multi-process/backend/browser boundary plus persistence tests. Prove four-role login/scope, teacher upload/student protected download and stop/start byte/data retention without reseed or period closure. Backend regression must not write existing test DBs; standard U11 is blocked under current authority. Establish a non-destructive dev-focused regression and report broader suites unavailable unless separately authorized. Record minimal opening/stop instructions here and actual human opening separately; no acceptance by inference. Rollback: T3 tests/instructions only; preserve all dev data and prior tasks.

## Scoped strict TDD and commands

Parent explicitly selects launcher test-first: meaningful assertion RED -> minimal GREEN -> refactor GREEN if performed. Missing-file/import errors alone are not RED. Do not flip historical `openspec/config.yaml` settings (`strict_tdd:false`, `rules.apply.tdd:false`).
Available: Node `24.21.0`, npm `11.19.0`, Windows PowerShell 5.1, owned PHP `8.4.26`; PHPUnit file, Playwright CLI and isolated Chromium executable exist. Presence is not executed compatibility evidence.
Runner: Node built-in tests orchestrating sanitized PHP and `powershell.exe -NoProfile -File` subprocesses. T1 currently covers pure grant assessment, sanitized binding rejection and command/account rejection; it does not prove bootstrap/ownership success. T2/T3 runners and non-destructive live `eval_dev` scenarios remain unimplemented; no Pester/install assumption.

| Task/check | Exact command and effects |
|---|---|
| T1 available | `node --test --test-isolation=none backend/dev-runtime/tests/bootstrap.test.mjs`; synthetic grant fixtures and invalid invocation probes, no DB writes |
| T2 planned | `node --test --test-isolation=none backend/dev-runtime/tests/lifecycle.test.mjs`; owned fake/local workers and isolated test files only |
| T3 planned | `node --test --test-isolation=none backend/dev-runtime/tests/persistence.test.mjs`; live mode requires explicit flag, writes synthetic dev data only, retains it |
| Existing syntax | `node --check backend/dev-runtime/https-proxy.mjs`; owned `C:/Users/kevin/AppData/Local/Temp/opencode/eval-u1/php/php.exe -n -l <php-file>`; no application execution |
| Existing frontend | In `frontend`: `npm run typecheck`, `npm test`, `npm run build`; latter two write `.test-build/` and `dist/`, respectively |
| Existing backend, blocked | `./backend/scripts/local.ps1 -Command test -Unit U11 -Arguments @('--filter=LocalHttpsRuntimeTest')`; writes test fixtures, starts workers and closes active test periods in teardown; never target `eval_dev` |

## Evidence and handoff

- [ ] Record each task's actual RED/GREEN/refactor command, counts, runtime proof, changed-line count and independent rollback boundary; T1 helper-only evidence below does not complete bootstrap.
- [ ] Verify only expected worktree changes before each task; stop on unexpected concurrent changes. No existing suite/preview teardown may operate on `eval_dev`.
- [ ] Read this full file and full mirror before implementation and after each evidence update: project `eval-colegio-mercedes`, topic `odd/local-dev-runtime/tasks`, locator `odd/tasks/local-dev-runtime.md`, `capture_prompt:false`; report memory ID to parent.
- [ ] Keep physical LAN/offline/load/institutional acceptance and durability unclaimed. No global gate completion or product-policy expansion.

Forecast: **500-850 authored additions/deletions** including tests and this lean tracker. **400 lines is advisory per ODD task, not a size-only stop.** Do not omit tests, compress readability or invent RED to fit. No PR chain, size exception, commit or delivery strategy is selected/created. User no-commit and T1-only restrictions override skill defaults.

Preparation checks: HEAD/branch/status and history-fix diff refreshed; only the two expected baseline modifications existed. Tool versions/path presence verified without application execution. MySQL capability check skipped: existing wrapper has no established read-only privilege-inspection command; no raw credential/state reading or temporary script is justified here.

## T1 bounded outcome

- Meaningful classifier RED: 6 tests, 3 pass/3 assertion failures against fail-closed stub; GREEN 6/6. Additional coverage reached 11/11. Partial-revocation negative then RED 11/12; fixed fail-closed handling, final GREEN 12/12. No missing import/file used as RED; no refactor claimed.
- Exact probe: `powershell.exe -NoProfile -File backend/scripts/dev.ps1 -Command capability -Role migration`; exit 2 at `state_acl`, flags `acl_protected:false`, `owner_current_user:true`, `allowed_principals_only:false`. No state contents/credentials were read/decrypted, no PHP DB child started, no SQL capability established.
- STOP: a separately authorized review/hardening of the existing encrypted-state Windows ACL is required before retry. Do not alter ACLs automatically or weaken this check. After safe retry, missing CREATE/CREATE USER/dev GRANT authority still requires an explicit maintenance step without restart or ambient credentials.
- No `eval_dev` schema/users/grants/migrations/demo accounts, `.local` secrets or runtime state created. `.gitignore`, test DB allowlists, migrations and both baseline history-fix files remain untouched. Remove only the four new helper/test files to roll back this attempt; retain this recovery record.
- Helper/test additions: 262 lines across four files. PHP `-n -l` (config/capability), Node `--check` (bootstrap tests) and PowerShell AST parse pass. Whitespace checks include untracked files; LF/CRLF warnings are not failures. T1 remains unchecked: ACL/process/SQL success, least-privilege provisioning, repeat/recovery, migrations and seeds are unverified.

## 2026-10-08 continuation: verified maintenance boundary

Human requested continued local progress, then asked to investigate how MySQL was prepared without reading or displaying secrets. No new restart, administrator-account use, test-database writes, commit or remote operation was authorized.

- Restricted only the existing encrypted `database-state.json` ACL through an approved elevated tool call. Verified pre-existing current-user ownership, exact path and non-reparse target; disabled inherited access and allowed only the current user, SYSTEM and Administrators. The command did not read file contents and retained the prior ACL in memory for rollback on verification failure.
- `./backend/scripts/dev.ps1 -Command capability -Role migration` succeeded through the reviewed wrapper: `account_binding:true`, `tls:true`, `create_database:false`, `create_user:false`, `grant_dev_permissions:false`. This is actual authenticated, read-only MySQL evidence, not an inferred configuration result. It does not prove database/schema/account provisioning.
- Launcher helper suite: `node --test --test-isolation=none backend/dev-runtime/tests/bootstrap.test.mjs`, **12 passed / 0 failed**, using approved local subprocess execution. Earlier sandbox `EPERM` execution failures did not establish product defects.
- Frontend: `npm test`, **80 passed / 0 failed**; `npm run build`, successful including application/tooling typecheck and Vite compilation. No backend database suite or preview was launched.
- Read-only source review found `start-u1.ps1 -ConfigureRuntime` uses a verified owned-server restart and MySQL `--init-file` to apply scoped SQL grants. Existing U11 SQL targets only `eval_u11_test`; neither that SQL nor that command may be reused for `eval_dev`. No reviewed administrator credential resolver was found in the repository. No encrypted state, root credentials, SQL logs or database files were manually inspected.
- No `eval_dev`, credentials, migrations, demo data or persistent application was created. Existing router/history changes remain untouched. No mirror tool is available in this session; the historical mirror #105 was not read or updated, and no new memory ID is claimed.

Next boundary: review `local-dev-maintenance-review.md` in this directory and obtain explicit maintenance authority or an operator-provided administrator session. Restart remains prohibited under the existing scope. T1 remains incomplete; T2/T3 must not start.

## Subsequent explicit human maintenance and real-app authorization

The human expressly authorized credentials/maintenance and requested work on the real application rather than a prototype or preview. This superseded the restart and T1-only boundary for this local development setup, not remote operations or unresolved institutional product decisions. A verified owned MySQL restart prepared only `eval_dev` and two fresh development accounts. No ambient administrator credentials were used. Protected temporary initialization SQL was removed after successful preparation; encrypted state and private materials remain in ignored `.local/eval-dev/`.

Bootstrap executed all 10 existing migrations, scoped runtime grants and four retained development profiles/accounts. Academic demo period/catalog/enrollment/assignment were created through existing domain services, including credential-revision checks. A real partial preparation failure retained the accounts and schema; recovery completed without deletion. Repeated successful preparation preserved the exact snapshot. T1 supplies a working local bootstrap; exhaustive fault-injection/privilege regression coverage is not claimed.

T2 now has explicit start/status/open/stop, exact PID/start/executable/owner/helper binding, exclusive operation lock, occupied-port rejection, partial-start owned cleanup, persistent origin/key and protected browser profiles. The initial missing Node executable recording race was reproduced by lifecycle assertions and corrected using the known launch executable. Combined helper/binding/certificate/lifecycle suite: 20/20. Certificate missing-file failures were environment prerequisites, not claimed assertion RED. Expired certificate renewal and reboot startup remain unexecuted recovery cases.

T3 browser evidence: 12 checks pass, four real logins, teacher publication/student consultation and byte-identical downloads, runtime TLS restrictions and administrative denial. Stop/start retained 4 accounts, one course/enrollment, two welcome files and ordinal 10; all 12 checks passed again without republishing. An early browser-verification loading race produced two retained welcome publications; the verification now waits for confirmed loading and does not create another. No lifecycle deletion was invented to remove them.

The temporary Composer vendor tree was found incomplete; 88 existing locked dependencies were restored offline from the local Composer cache without scripts/plugins or version changes. Frontend 80 tests/typecheck/build pass. No foundation test-database suite was run. Teacher headful opening was confirmed; human acceptance is pending. Root launchers and docs/local-development.md expose current functionality and limitations. No commits, pushes, global installs, LAN exposure, institutional identities, new domain modules or global acceptance gates were completed.

The historical checklist stays open where its exhaustive scope exceeds the executed evidence. The operational app is available despite those remaining tests. Source scope additionally includes manage-dev.ps1, certificate/browser/inspection helpers, root launchers and current-state documentation under this subsequent authorization. No mirror capability was available and no memory update is claimed.
