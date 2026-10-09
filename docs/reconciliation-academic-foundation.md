# Academic Foundation Reconciliation

## Authority and baseline

On 2026-10-07 the human approved reconciliation and required sequential team work: finish and verify a bounded unit, commit it, then the next contributor starts from that exact predecessor commit. No parallel application changes are permitted. This is a local handoff rule, not a selected PR chain strategy or remote/push authorization.

The normative product baseline is 38e90ff. Requirements are not reopened to match code. The external 2e5d053 implementation remains a future candidate and is not integrated.

## Current evidence

- PHP 8.4.26 and PHPUnit 12.5.38 were observed in the existing isolated runtime.
- WriteGuardPreconditionTest: meaningful RED, 1 failure because table access occurred outside a transaction; corrected implementation then GREEN.
- Unit directory: 12 tests, 36 assertions passed without database bootstrap/connection.
- Integration tests and migrations have not been executed during reconciliation. No database version, permission or schema acceptance is certified here.
- No historical RED/GREEN evidence is fabricated for existing U1 code.

## Pending work and acceptance

| Item | Remaining evidence or correction | Governing artifact |
|---|---|---|
| 2.1 | Declared-date validation, unsupported transfer rejection without mutations, complete clause-to-test mapping and historical execution evidence | tasks 2.1 |
| 2.2 | Historical date/key projections, assignment inclusive containment and planning horizon; primitives alone do not complete the text | tasks 2.2 and design temporal strategy |
| 2.3 | Approved identity mapping, durable non-reuse beyond duplicate existing PK, real isolated MySQL results and review | tasks 2.3, gate 1.6 |
| U2 states | Remove enrollment planned and assignment replaced; assignment prior closes on replacement | enrollment/assignment specs |
| Planned assignment | Nullable start operational key until activation; no invented date-to-key mapping | design and gate 1.2 |
| Identifiers | Align aggregate IDs with BIGINT; separately approve retained identity mapping; inspect applied-schema state before altering migrations | design, gate 1.6 |
| Names | Approve exact trim/case/key mechanics and MySQL reproduction; test accents, NFC, scope, rename/reactivation and label retention | gate 1.5 |
| Retention | Review unreferenced identity deletion/reuse, immutable references and destructive down methods; teardown belongs only to disposable fixtures | design retention |
| U2/U3 | Existing U2 remains frozen; no writers/ledger/retry implementation before applicable gates and explicit bounded handoff | tasks 1.1–1.8, 2.4–2.7 |

The transaction precondition is enforced before guard database access. This does not implement or certify the U3 protocol. All original product gates remain pending; approved decisions 1.2–1.5 need evidence and technical closure, not renewed product selection.

## Prototype acceptance checklist

Keep docs/prototipos/academic-foundation independent from the backend. Add synthetic Director/Admin and Vice Principal views: management versus necessary assignment-only supporting projections; closed-parent new-work denial versus valid residual cleanup/history; explicit denial of enrollment/catalog/period management and deferred processing for Vice Principal. No real lifecycle commands or authentication are required for this artifact.

Human review must open the artifact from disk, inspect network requests, use keyboard selectors/focus, zoom and narrow viewport, and explain current versus historical scope, original routing, replacement and cleanup across all four roles. Record reviewer, artifact version, observations resolved and explicit acceptance. Full architecture acceptance and approval references are separate gate 1.1 prerequisites.

odd/tasks/academic-prototype.md is auxiliary provenance only. Its T1–T3 checkboxes and mirror references do not complete OpenSpec tasks or gates; OpenSpec is the sole normative source. Preserve odd/ until its disposition is reviewed.

## Handoff boundaries

1. Documentation and verified U1 guard correction.
2. Complete U1 evidence and prototype review packet.
3. Human architecture/prototype acceptance and applicable technical gates.
4. Authorized U2 reconciliation/tests; do not apply existing candidate migrations blindly.
5. U3 only after accepted U2, protocol/retry decisions and explicit size/delivery decision.

No commit may assert completed functionality while its required evidence is absent. A successor starts only after the prior owner supplies the finished unit's commit and verification record. Preserve existing candidate work and history; do not integrate 2e5d053 implicitly.

## Prototype verification update

Management projections added with meaningful RED (8 pass / 1 failure) and GREEN (15/15 full Node suite). Node used --test-isolation=none because sandbox subprocess spawning was unavailable. Director/Admin and assignment-target Vice Principal samples now exist. No browser/manual acceptance is claimed.

## U1 declared-date evidence (2026-10-07)

DeclaredDateRange now validates real YYYY-MM-DD calendar dates and start/end order, permits same-day ranges, and applies containment only through the explicit assignment method. The planning horizon is the inclusive declared end plus one calendar day; open assignment dates remain open and no operational authority is inferred. EffectiveInterval historicalView returns declared dates separately from string ordinals.

Meaningful assertion RED against date stubs: 18 tests, 4 failures (invalid date, inverted range, planning horizon, outside-period assignment). GREEN including historical projection: 19 tests, 47 assertions. Projection was checked after implementation; no separate projection RED is claimed. No transfer writer, scheduling/date-key mapping, U2/U3 handler or lifecycle ledger was introduced.

Read-only db-smoke attempt failed before connection because the isolated runtime's recorded PID 2572 was not running. MySQL integration evidence remains unavailable; no other installed database service was used and no migration executed. Tasks 2.1–2.3 remain pending: unsupported transfer/no-mutation contract, identity-mapping/non-reuse acceptance and real integration evidence still require resolution. Existing candidate U2 schema is unchanged.

Reproduce pure unit checks in PowerShell (existing isolated runtime only):

```powershell
$evalRuntime = Join-Path $env:LOCALAPPDATA 'Temp\opencode\eval-u1'
$env:EVAL_VENDOR_DIR = Join-Path $evalRuntime 'vendor'
& (Join-Path $evalRuntime 'php\php.exe') -c (Join-Path $evalRuntime 'php.ini') (Join-Path $evalRuntime 'vendor\phpunit\phpunit\phpunit') --bootstrap backend/bootstrap.php --no-configuration backend/tests/Unit/Academic
```

Architecture/prototype acceptance and the pending gates remain prerequisites to full application development. General permission to continue is not recorded as an observed manual review or passing integration run.
## Explicit human acceptance (2026-10-07)

The project reviewer accepted the synthetic four-role prototype for academic-foundation validation and approved the current base architecture while preserving unresolved questions. See docs/academic-foundation-gate-1.1-approval.md for reviewed revision, authority, consistency and exact limits. Gate 1.1 is now complete; earlier pending-acceptance statements are historical. No manual browser/accessibility pass is asserted. Gates 1.2–1.8 and U1 completion remain pending; U2 remains blocked and U3 is excluded.

## Verified U1 superseding status (2026-10-07)

The human explicitly approved permanent shared BIGINT identities, retention/minimum privileges, isolated MySQL startup and U1-only implementation, then removed the line limit. U1/2.1–2.3 are now verified: 43 tests, 150 assertions, MySQL 8.4.9. See docs/u1-verification.md for exact authority, RED/GREEN limits, commands, criterion mapping and rollback/permission evidence. Earlier UUID candidates and pending-U1 statements are historical. No new gate closure, U2/U3 work or remote authority is inferred.

## Verified U2 superseding status (2026-10-07)

U2/2.4 has now been separately reconciled and verified under the human FASE 2 authorization: 27 tests / 182 assertions; U1 regression remains 43 / 150. See docs/u2-readiness.md and docs/u2-verification.md. Earlier frozen-U2 statements describe the previous boundary. No specs were altered. Gates 1.2–1.8 remain pending; U3 awaits explicit retry/replay mechanics. No external implementation or remote operation was introduced.
