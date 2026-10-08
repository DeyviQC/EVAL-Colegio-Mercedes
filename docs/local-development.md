# EVAL real local development application

Date: 2026-10-08 (America/Lima). This runs the actual React application, Laravel HTTP adapter, academic services and MySQL persistence. It does not serve the synthetic prototype or launch PHPUnit previews.

## Opening and stopping

Double-click a launcher in the repository root:

| File | Result |
| --- | --- |
| `Abrir EVAL.cmd` | Start if necessary; open an authenticated Director/Admin session. |
| `Abrir EVAL docente.cmd` | Open a separate authenticated teacher browser profile. |
| `Abrir EVAL estudiante.cmd` | Open a separate authenticated student browser profile. |
| `Abrir EVAL subdirector.cmd` | Open a separate authenticated Vice Principal browser profile. |
| `Ver accesos EVAL.cmd` | Show the local account passwords in a local Windows dialog; no passwords are printed in logs or tracked files. |
| `Detener EVAL.cmd` | Close owned browser sessions and stop the app's PHP/HTTPS workers, retaining MySQL, accounts, academic records and materials. |

The fixed app origin is `https://127.0.0.1:8443`; PHP upstream is loopback-only port `18080`. Open through the launchers: isolated Chromium uses a narrowly pinned local certificate; ordinary browsers do not automatically trust it. No global certificate trust or blanket TLS bypass was installed. The certificate lasts 30 days; expired certificates require a reviewed local renewal rather than bypassing validation.

Accounts are `director`, `subdirector`, `docente` and `estudiante`. They have independently generated passwords encrypted for the current Windows user. The launchers resolve these internally and authenticate through the real login flow; account/session grants remain server-owned. These are synthetic development accounts, not institutional identities.

Closing a browser keeps the app running. Close a role's existing window before opening the same role again. Different roles can be open simultaneously in separate profiles. Run launchers under the same Windows user that prepared the environment; no Windows administrator elevation is required for ordinary use.

## What is available

- Director/Admin: real period/catalog management and enrollment listing/creation, immediate transfers and explicit closure with retained history. See `docs/enrollment-ui-contract.md` for the bounded transport and evidence.
- Teacher/student: real Mathematics / 2.º B course, protected materials, teacher publication and student consultation/download.
- Director/Admin and Vice Principal: assignment planning, activation, dated closure and immediate teacher replacement, with retained original contexts and replacement lineage. The Vice Principal uses purpose-bound options and cannot access general period/catalog/enrollment administration. See `docs/assignment-ui-contract.md`.
- Director/Admin: Users creates actual teacher/student accounts, shows their generated initial passwords once, and supports reset, deactivation and reactivation with retained records. Mi cuenta lets each authenticated user change their own password. Creation does not implicitly enroll or assign. See `docs/local-user-administration-verification.md`.
- Teacher/student: Activities and Deliveries use actual course selection, publication, text/file delivery, retained updates, private version downloads and explicit activity closure. Evidence limit is 10 MiB; dates are informational. See `docs/activity-delivery-verification.md`.
- Report export, administrator-account maintenance, material editing/deletion, supervision, library/search and synchronization remain pending.

Academic notifications now persist activity publication, delivery/update and grading/correction events for their actual recipients. The own feed supports unread counts, explicit refresh, 50-row paging, section shortcuts and bounded read receipts. See docs/academic-notification-verification.md.

The initial course and accounts are provisioned once through existing academic commands. Starts never migrate, reseed, reset periods or delete data. Development verification has retained two small welcome PDF publications; these are test-owned persistent content, not institutional resources. Upload your own allowed PDF/image/Office file through the teacher UI to exercise the real journey.

## Data and maintenance

MySQL database: `eval_dev` on the existing owned instance at `127.0.0.1:3307`, MySQL 8.4.9. It now has 25 tables and 14 migrations, including account audit, activity/delivery content and immutable assessment revisions and retained academic notifications. `eval_dev_migration` has schema-scoped maintenance/grant privileges; `eval_dev_runtime` has reviewed table/column DML grants without CREATE, DROP, ALTER, DELETE, role updates, login updates or global account-management authority. A narrow accepted_at update privilege permits locking submission references, whose immutable trigger continues to reject actual updates. No older prerequisite schema or grant was broadened by development preparation.

Private state, user-protected encrypted credentials, persistent application key, browser profiles and material files live in ignored `/.local/eval-dev/`. Never publish, share or delete this directory as a routine cleanup operation. Do not copy its encrypted state to another Windows account.

The reused MySQL datadir is still under `LOCALAPPDATA/Temp/opencode/eval-u1/mysql/data`. Retention across app start/stop has been proven; resistance to temporary-directory cleanup, institutional backups and production deployment have not. A future bounded migration should move the database/runtime to a durable location and prove backup/restore before institutional use. The app can start the previously verified owned MySQL runtime if it is stopped; this reboot recovery path has not yet been exercised.

Supported commands from the repository root:

```powershell
./backend/scripts/manage-dev.ps1 -Command status
./backend/scripts/manage-dev.ps1 -Command snapshot
./backend/scripts/manage-dev.ps1 -Command start
./backend/scripts/manage-dev.ps1 -Command open -Role teacher
./backend/scripts/manage-dev.ps1 -Command stop
```

`prepare` is maintenance only; repeating it verifies retained accounts and existing academic records without ordinary reseeding. Never use `-Maintenance` as an ordinary start option. Initial maintenance required explicit human-authorized owned MySQL restart and a protected temporary initialization file; that file was removed after successful provisioning. No root credentials were searched or displayed. Existing databases were retained.

## Executed evidence

- Meaningful dev-binding RED: 3 cases, 2 assertion failures against the explicit deny-all stub; GREEN: 3/3. Host, database, port, unit and account/role mismatches fail closed without SQL.
- Combined local helper/binding/certificate/lifecycle suite: **20 passed, 0 failed**. Lifecycle cases verify exact owned status and duplicate-start rejection with unchanged snapshot; they do not claim exhaustive process fault-injection coverage.
- Live runtime snapshot: verified TLS, restricted account, 19 tables, 10 migrations, 4 accounts, 1 period, 1 assignment and 1 enrollment.
- Repeat preparation: snapshot unchanged, ordinal remained `8` before educational publication.
- Chromium journey: **12 checks**, including all four logins, Director period reads, role menu restriction, real teacher publication, independent student consultation, identical teacher/student downloads, student administrative denial and unpinned TLS rejection. No test database or institutional account was used.
- App stop/start: before and after snapshots had the same two materials and ordinal `10`; the complete 12-check browser journey passed again with byte-identical downloads and no extra publication.
- Teacher headful browser opening reported `EVAL opened and authenticated: teacher`. This proves automated local opening, not human acceptance.
- Frontend: **80 tests passed**, application/tooling typecheck and build passed during this continuation. No database-mutating foundation PHPUnit suite was run.
- The temporary dependency directory was found incomplete during start; Composer restored the existing 88 locked packages from local cache with network disabled and scripts/plugins disabled. No dependency version change or global installation was made.

Screenshots and the latest non-secret browser verification receipt are in the private ignored directory. Neither loopback browser evidence nor this working development app establishes school LAN/WLAN, external-Internet-loss, physical tablet, load, accessibility or institutional acceptance. OpenSpec acceptance gates remain open.

Assessment delivery: AD/A/B/C, required feedback, retained corrections and locked assessed evidence are verified locally. See docs/delivery-assessment-verification.md for 56 runtime checks, 11 browser checks and remaining institutional acceptance.

Director classroom Reports: real scope selectors, logical-delivery totals, current AD/A/B/C distribution and 50-row roster pagination are verified. See docs/classroom-report-verification.md. Use manage-dev.ps1 -Command verify-reports; -BrowserOnly reuses the existing synthetic report fixtures.

Academic notifications: publication, delivery/update and assessment/correction events, own read receipts and retained read state are verified in MySQL and Chromium. See docs/academic-notification-verification.md. Use manage-dev.ps1 -Command verify-notifications; -BrowserOnly reuses server fixtures.

Materials now has its own teacher/student menu. Select a course there to consult/publish through the existing protected service; My Courses remains available. The standalone navigation passed 14 real browser checks. See docs/materials-navigation-verification.md.
