# U12 Server-Owned Navigation Contract Review

Date: 2026-10-07. Verified predecessor: d00b39fc351502b3fccfdaa438bd1d4d374bb8b9.
Status: proposed technical contract; no endpoint or UI implementation selected by this record.

## Observed gap and normative boundary

The session response is exactly authenticated/CSRF; changing its shape would break the current exact decoder. FoundationController accepts individual resource locators and purpose-bound assignment support, but exposes no navigation or collection endpoint. AcademicAuthorization refreshes persisted roles, active identity and credential revision. Role menus cannot be inferred from a synthetic persona or client-supplied role.

The academic-authorization spec requires server-derived context, Director/Administrator management and historical review, Vice Principal assignment duties with minimum purpose-bound support, teacher assignment ownership and student enrollment scope. Menus are discoverability only: an enabled section never grants resource authority or predicts valid lifecycle actions. Planned/closed periods and retained relationships must not be hidden by an implicit current-period-only rule.

## Recommended option A: bounded context first

Select GET /academic/navigation, behind existing local session authentication, with no query/body fields. Return the existing data envelope with exactly sections, an ordered unique array of technical section identifiers. No actor ID, display name, role picker, profile, school roster, resource IDs or current-period assertion in this first response.

Proposed identifiers and sources:

| Identifier | Authoritative persisted role granting section visibility |
|---|---|
| periods | director_admin |
| catalog | director_admin |
| enrollments | director_admin |
| assignments | director_admin or vice_principal |
| my_assignments | teacher |
| my_enrollments | student |

Use the fixed order above and the union of actual persisted grants for multiple roles; an authenticated identity without these grants receives an empty array. Revalidate active retained identity and credential revision at the query boundary, consistent with existing policy checks. Never accept client section/role as authority. Keep /auth/session unchanged. Existing 401/403/422/503 mappings and no automatic retry remain applicable.

The client validates the exact envelope/allowlisted identifiers and shows Spanish section labels only after confirmed authentication. Fetch context once on confirmed session, invalidate it on logout, expiry or unavailable authorization, and discard late responses from earlier sessions. No automatic replay after errors. Sections may show a truthful empty integration state until separately reviewed resource discovery exists; do not fabricate records or render locator inputs as the school workflow.

Advantages: resolves the immediate server-owned menu gap without broadening data access or inventing labels; easy to prove real role changes and session isolation. Disadvantages: does not yet provide resource discovery or academic operation forms; no complete accepted-prototype journey is claimed. Impact: one query/endpoint, exact typed client contract, session UI integration and scoped tests/evidence. No six-spec/design amendment, account management, writer or checkbox closure.

## Alternative option B

Leave navigation unavailable and review collection/discovery transport together with menus in a larger slice. Advantage: avoids an intermediate empty section state. Disadvantage: larger privacy/pagination/purpose-bound review and delayed role visibility. Impact: no code until those contracts are selected. Not recommended for the next bounded handoff.

## Required proving evidence after approval

- Actual persisted four-role and multiple-grant projections; empty/unknown grants do not grant sections; supplied actor roles never override persisted grants.
- Rejected anonymous, revoked/changed-credential/inactive contexts and malformed query/body; sanitized database failure.
- Exact decoder rejects unknown/duplicate/out-of-order identifiers, extra identity fields and authority-shaped input.
- Logout/expiry clears navigation; a delayed response cannot reattach prior-session navigation; failures require explicit refresh, not automatic retries.
- Real HTTPS browser sessions for four disposable roles show only their allowed section labels; direct forbidden resource requests still fail even if a client changes its menu.
- Existing U11 and frontend regression checks, exact diff reporting and local sequential handoff.

## Later decisions remain separate

Resource discovery needs a bounded transport/pagination contract and tests for retained records and each role scope. Vice Principal period/catalog/enrollment support remains duty/target-bound; this menu grants no general list. Institutional identity-name sources remain unresolved; IDs are not substitute names. Activity/submission writer routes stay reserved, materials/observations/AD-A-B-C/library remain deferred. No current-parent-only historical filter or client recipient selection is introduced.

Task 8.2 requires real accepted-prototype role flows; gates 1.6/1.7 require applicable technical contract/harness selection. This review adds the missing selection, not new product permissions or evidence of completion. Tasks 8.2-8.4 and gates 1.2-1.8 remain open. Prototype, odd/, specs/design and checkboxes remain unchanged. No apply, remote Git or candidate integration.
