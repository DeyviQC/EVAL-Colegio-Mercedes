# Local Authentication Prerequisite: Approved Session Mechanism

Date: 2026-10-07 (America/Lima). Verified predecessor: 82a01c334d8dc5c648ceebb289b837bbb38b4595.

## Human decision and scope

The human replied Continue to the explicit proposal of local server sessions and browser cookies. This records approval of that mechanism. It does not approve API-token authentication, an external identity provider, account-management capabilities, public registration, password recovery, new academic permissions, planned-parent work eligibility or deployment choices.

The prerequisite is separate from U4. tasks.md gate 1.6 and the Phase 3 dependency require a local authenticated actor before academic commands. U3 retained synthetic identities are not authentication. No planning checkbox is completed by this document. The six specs and design remain unchanged.

## Verified tooling gap

The existing isolated runtime has PHP 8.4.26, Illuminate Database/Events/Filesystem 13.35.0 and PHPUnit 12.5.38. Installed Illuminate directories include no auth, session, hashing, http, cookie or encryption components. Inspection of the isolated Composer cache found no authentication/session component archives. The repository has no verified full Laravel HTTP application.

The original human implementation authorization explicitly requires stopping when a remote operation is necessary. Downloading new dependencies therefore requires destination-specific permission; the session-mechanism approval does not silently authorize package downloads. No download, dependency modification or authentication implementation is performed in this readiness handoff.

## Proposed installation boundary for approval

Use the existing isolated Composer wrapper and private runtime directory, with no global installation, plugins or Composer scripts. Resolve matching Illuminate 13.35.0 components for auth, session, hashing, http, cookie, encryption and cache, retaining the existing PHP/MySQL baseline. Review the resolver plan before installation; if it requires changing existing pinned framework versions or cannot resolve, report that result rather than silently upgrading them.

The approved destinations must cover HTTPS package metadata from repo.packagist.org and distribution archives from api.github.com/codeload.github.com. No repository remote fetch, push, external account or institutional data is needed. Composer changes would be limited to backend/composer.json, backend/composer.lock and the existing isolated dependency directory. Record actual package versions and audit results; do not claim a complete Laravel application merely from component installation.

The planned dependency operation is the local wrapper's composer command with require, --dry-run, --no-scripts, --no-plugins, --prefer-dist and --no-interaction, selecting illuminate/auth:13.35.0, illuminate/session:13.35.0, illuminate/hashing:13.35.0, illuminate/http:13.35.0, illuminate/cookie:13.35.0, illuminate/encryption:13.35.0 and illuminate/cache:13.35.0. Perform the actual require only after reviewing that resolution and receiving download authorization. This is a plan, not an executed command.

## Required implementation and verification after installation

Use Laravel's existing session guard/provider contracts and local server-side session persistence. The browser carries a session cookie, not an actor ID or role as authority. Resolve each authenticated actor to the permanent retained BIGINT identity and reload current credential status and server-owned role context. Removing credentials must not delete or reuse the retained academic identity. Any future writer that changes academic authority must participate in the U3 protocol; fixture credentials are not an account-management endpoint.

Keep all runtime authentication data local. Use framework password verification, session-ID regeneration at login, session invalidation at logout, server-side revocation, CSRF checks for state-changing browser requests and secure cookie configuration. Deployment hostname/TLS and timeout settings must be explicit configuration; do not silently select IIS/Apache or an institutional timeout/password policy. Login identifier rules must not reuse academic name equivalence without separate justification.

Prove the following with synthetic credentials and isolated MySQL, using runtime credentials for execution and migration credentials only for setup:

- Valid local credentials establish a server session mapped to the correct retained identity; invalid credentials and inactive/removed identity cannot authenticate.
- Client-supplied actor IDs/roles and changed cookies cannot impersonate another identity. Authentication does not itself grant academic permissions.
- Login regenerates the session identifier; logout invalidates it; revoked credentials stop an existing session without destroying audit history.
- Protected writes reject missing/invalid CSRF evidence; cookie/session configuration and same-origin behavior are verified at the actual HTTP boundary before claiming browser readiness.
- Authentication/session persistence has only the necessary runtime grants, no DDL or retained-identity DELETE/UPDATE-ID rights; failures do not partially establish authentication.
- U1/U2/U3 regression suites still pass. Confirm installed versions and test counts, report unavailable browser/LAN evidence honestly, then create the separate verified local prerequisite handoff.

The exact HTTP host, full framework bootstrap and authentication database allowlist must be established in that bounded prerequisite before running it. No real users, institutional data, external login, frontend scaffold, public registration or deferred module is introduced here. U4 remains blocked until the prerequisite supplies real authenticated actor integration and applicable authorization evidence.

## Documentation basis

Laravel 13 documents its session guard as server-side session storage with browser cookies and distinguishes authentication from authorization: https://laravel.com/framework/docs/13.x/authentication. PHP documents strict session handling and secure cookie settings: https://www.php.net/manual/en/session.security.ini.php. These references inform implementation checks; they do not constitute installed tooling or completed tests.

## Readiness verification

Repository/vendor/cache inspection performed. Document-only git diff --check passes. No application test result is claimed for authentication, no gate/task checkbox changes, and no remote dependency operation is executed.
