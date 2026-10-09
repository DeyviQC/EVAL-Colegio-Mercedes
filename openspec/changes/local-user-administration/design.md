# Design: Local User Administration

Status: proposed; approve together with proposal, specification and UI review before application code.

Superseding status: bounded approval recorded in proposal.md; implementation and verification recorded in docs/local-user-administration-verification.md. The earlier proposed status is preserved as history.

## Existing foundation

Reuse retained identities, local display profiles, local credentials and local role grants. Authentication already verifies current active status and password revision. The current runtime intentionally cannot create credentials or roles; changing grants is part of the implementation, not an assumption of existing capability. No global database privileges are needed.

## Consistency and authorization

Every authority mutation participates in the existing academic guard transaction protocol, with guard acquisition first and canonical retained-identity lock ordering. Re-read the director's active status, credential revision and stored role under the guard before mutation. Password self-service rechecks the current password and actor revision under the same authority protocol. Allocate identity, profile, credential and the single role atomically on creation. Preserve the established academic lock ordering; never acquire an authority lock after a conflicting academic lock.

Credential revision changes invalidate subsequent requests made with older sessions through the existing provider. Prove the behavior with real HTTP/session tests, including stale-session writes. No session plaintext or credentials enter academic lifecycle payloads. The existing lifecycle event target types do not establish an account-event format: inspect the actual ledger and define a reviewed additive account-audit schema before any schema implementation. Audit records contain actor, target identity, operation and timestamp only, and remain transactionally coupled to successful mutations.

Implementation mapping: additive local_account_events retains restrictive actor/target identity FKs, operation enum, unique guard operation_key and recorded_at timestamp. Existing academic ledger types are unchanged. Authority mutations use AcademicWriteTransaction with no academic lifecycle event; the separate account audit append occurs in its mutation callback and same transaction. Latest deactivation operation_key participates in credential revision even after reactivation, so dormant pre-deactivation sessions and actors cannot recover authority. Older prerequisite schemas without this additive table preserve their password-only revision. Runtime receives only column-specific creation/hash-update/audit-insert grants on eval_dev; no role-update, credential-login-update, delete or DDL grant.

## HTTP and frontend

Use the actual same-origin HTTPS session and CSRF boundary. Proposed routes: GET /users, POST /users, POST /users/{id}/status, POST /users/{id}/reset-password and POST /auth/change-password. IDs and role supplied by the client never confer authority. Use bounded keyset pagination; include identity, display name, username, role and status, never hashes or existing passwords. Validate uniqueness on the normalized username and surface conflicts without partial accounts.

Final route mapping: GET /account supplies the current display account and server-owned can_manage; GET/POST /users handles directory/create, POST /users/{id}/deactivate|reactivate|reset-password handles explicit operations, and POST /account/password changes the current password. Keep reserved authentication-session endpoints unchanged. Account navigation is derived from /account separately from the existing ordered academic navigation contract.

Add Users navigation only for Director/Admin and Change password for authenticated users. Generate passwords with a cryptographically secure source, outside logs and audit data; return the initial/reset secret only in its confirmed no-store response. No automatic mutation replay. A confirmed mutation followed by a failed directory refresh is reported separately from an unconfirmed mutation.

## Delivery and verification

One bounded sequential unit, no concurrent contributors. First inspect privilege fixtures and ledger extensibility; add meaningful authorization, atomicity, uniqueness and revocation tests before the corresponding implementation. Update the isolated test runtime and actual development runtime deliberately; do not rebuild populated databases or grant global privileges. Verify the browser journey using synthetic accounts in the real application, preserving existing data. Record checks and unresolved gates before any handoff commit.
