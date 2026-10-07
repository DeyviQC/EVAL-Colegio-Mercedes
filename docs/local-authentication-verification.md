# Local Session Authentication: Verified Prerequisite Handoff

Date: 2026-10-07 (America/Lima). Predecessor: 21ca23c7ef63e79a3afee3ddd62523515212122e.

## Authorization and scope

The human approved local sessions/cookies, then replied Continue to the specific request to download matching authentication dependencies from repo.packagist.org and api.github.com/codeload.github.com into the existing isolated runtime. This is a separate prerequisite to U4 under tasks.md 1.6 and Phase 3, not a new academic implementation task. No planning checkbox is closed. No six-spec requirement or design rule is changed, and no U4 period/catalog command is delivered in this handoff.

Composer dry-run and actual require each resolved 42 additions, zero updates and zero removals. Existing package versions were compared with the predecessor lock: all retained. New direct Illuminate components are pinned to 13.35.0 (auth/session/hashing/http/cookie/encryption/cache). Installation disabled plugins/scripts and global configuration; dependencies remain outside Git in the existing isolated vendor directory. Composer reported no known vulnerability advisories during installation. composer validate succeeds with warnings for deliberate exact pins; --strict returns nonzero for those warnings, not schema/lock errors. No audit beyond that observed result is claimed. Transitive console/queue/view/HTTP packages are installed dependencies, not implemented EVAL capabilities. A transitive UUID library does not change the permanent BIGINT identity mapping.

## Implemented boundary

LocalSessionAuthentication uses the installed Laravel SessionGuard and local provider contracts, an EncryptedStore and local MySQL persistence. It operates on real Symfony HTTP request/response objects without installing a web listener or full Laravel application. A protected callback receives a server-resolved permanent BIGINT identity and current role facts; client actor/role fields do not authorize anything. Role facts are snapshots for that request: an academic writer must re-read role authority and state under U3, not trust the snapshot.

GET /auth/session initializes a server session and returns CSRF evidence. POST /auth/login verifies only provisioned credentials, rotates session ID/CSRF and persists before response acknowledgement. POST /auth/logout invalidates the old session. No registration, remember-me, API token, remote identity provider, account management or password recovery endpoint exists. This adapter is not yet mounted in a production HTTP kernel.

The cookie is encrypted/authenticated, host-only, Secure, HttpOnly, SameSite=Lax and path /. HTTPS origin is mandatory and state-changing requests require that exact Origin and valid CSRF. Responses are no-store. Session data is encrypted in MySQL. The provider re-reads active retained status and credentials on each request; password revision changes, credential removal or account deactivation revoke existing sessions while leaving identities and ledger evidence intact. Server role facts cover director_admin, vice_principal, teacher and student. Authentication alone grants no academic operation or scope.

LocalSessionHandler retains revocation tombstones and refuses stale writes to revoked/expired sessions, preventing logout resurrection by a previously loaded request. Session writes use their own local transaction; no academic guard is needed for opaque session data. Runtime cannot change credentials/role grants, delete retained identities, mutate IDs or perform DDL. A narrow credential_status UPDATE grant exists only because U3 locking reads require locking-capable privileges; no credential-status writer is exposed. Any future credential/role authority writer must participate in U3.

Origin, encryption key, session minutes, attempt limit and throttle window are explicit constructor configuration with no institutional defaults. Tests use https://eval.test, random ephemeral keys, five minutes, three attempts/60 seconds and bcrypt cost four solely as synthetic fixtures. Production secrets, certificate/host provisioning, timeout/password policy, cookie persistence policy and cleanup schedule are not selected here. No deployment server is chosen. Provisioned opaque byte-exact fixture login keys are separate from academic display-name equality; this does not define a user-facing username policy. Bcrypt's 72-byte limit is enforced to prevent suffix truncation; future provisioning must choose/validate the institutional password policy and supported hash format. Runtime rehash is disabled because credential maintenance is outside this adapter.

## Isolated database and reproduction

Observed PHP 8.4.26 / Illuminate 13.35.0 / PHPUnit 12.5.38 / MySQL 8.4.9. eval_auth_test contains the U1/U2/U3 prerequisites plus four authentication tables (15 tables total), with TLS_AES_256_GCM_SHA384 to the owned server at 127.0.0.1:3307. U1/U2/U3 databases stay independent. Migration credentials create committed synthetic fixtures; runtime credentials execute authentication and protocol probes. No institutional data or credentials are used.

Prepare the Auth database with start-u1.ps1 -ConfigureRuntime -Unit Auth to bootstrap the database, local.ps1 -Command migrate -Role migration -Unit Auth, then start-u1.ps1 -ConfigureRuntime -Unit Auth again after tables exist to apply table grants. Initial table grants failed before table creation; this second phase is required for a new database. Repeated final grants are idempotent. --fresh-auth is restricted to the matching disposable database and rebuilds the explicit allowlist only. Never use it on populated institutional data.

| Repository-supported check | Result |
|---|---|
| backend/scripts/local.ps1 -Command test -Unit Auth | 24 tests / 127 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U1 | 43 tests / 150 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U2 | 27 tests / 182 assertions, pass |
| backend/scripts/local.ps1 -Command test -Unit U3 | 23 tests / 154 assertions, pass |
| backend/scripts/local.ps1 -Command db-smoke -Unit Auth | MySQL 8.4.9, 15 tables, verified TLS |
| composer validate --no-check-publish | Valid, intentional exact-version warnings |
| git diff --check | Pass |

The HTTP tests call the actual adapter with framework requests/cookies/responses and real MySQL; no live browser, socket HTTPS listener, school LAN/WLAN outage, production certificate or full HTTP kernel acceptance is claimed. Those remain later integration/deployment evidence, not simulated passes.

## Evidence details and honest RED/GREEN

After correcting Composer's working directory/autoload generation, the empty adapter produced five real failing tests/ten assertions. Full authentication then passed those tests. Additional negative, permission and integration tests were added afterward; their historical RED is not invented. Composer wrapper now always executes from backend regardless of caller directory.

The suite proves valid/invalid credentials, inactive identity denial, login ID rotation, logout and expired-cookie denial, tampered cookie/client actor denial, CSRF and HTTPS/origin rejection, server role refresh, password/removal revocation, encrypted payload/cookie attributes, configured throttling, malformed input and unavailable endpoint denial. A real MySQL test trigger injects session persistence failure; no successful login response/cookie is acknowledged, the transaction ends and the old session cannot authenticate. The trigger is removed in finally. Revoked-session stale writes are rejected against actual MySQL.

Restrictive actor FKs reject missing identities. Runtime credential/role/identity deletion and DDL probes return MySQL 1142/1143. U1 initially detected an unnecessary DELETE grant on login counters; it was removed, resets now UPDATE and U1's unchanged privilege tests pass. A repeated REVOKE initially interrupted later bootstrap grants; the final script contains only idempotent grants after that one-time correction.

Two authenticated protocol fixtures prove the same permanent identity reaches U3 and its retained ledger. A role revoked after request authentication is re-read under the guard and denied before allocation with no event. Successful fixture mutation allocates exactly once; subsequent credential removal stops access but retains the event. These are protocol-integration fixtures, not public academic endpoints or U4 commands. Fixture maintenance changes do not establish a production account-management writer.

## Next-unit boundary

The local authenticated-actor integration prerequisite is evidenced for in-process U4 command development using these established components. Gates 1.2–1.8 remain globally open for their later obligations. U4 still must record its applicable readiness, authorize operations using server role facts under U3, and prove approved equality, period/catalog lifecycle and retention. No cached request role, caller ID, active identity alone or fixture grants substitute for authorization.

Full Laravel HTTP bootstrap, deployed login UI, browser/CSRF integration, institutional credentials/configuration and LAN outage acceptance remain future prerequisite work before exposed application use. Planned-parent assignment activation/new-work eligibility, unresolved temporal mappings, IIS/Apache and deferred capabilities remain untouched. No automatic request replay, remote Git operation, push or integration of 2e5d053 occurred.
