# U1 Verification and Local Handoff

Date: 2026-10-07 (America/Lima). Parent: 48be55d.

## Human authority

The current conversation explicitly authorizes U1/2.1–2.3 implementation, isolated MySQL startup, U1 migrations/tests and one verified local handoff. The later instruction "que no tenga limite de lineas" removes the numeric size limit for this authorized work; scope remains U1. No U2/U3, remote operation, deferred capability or 2e5d053 integration is included.

Approved technical decisions: local BIGINT shared retained identity for actor/student/teacher; permanent non-reusable identities; restrictive FKs; runtime without DDL, DELETE or identity-ID changes; migration privileges separate; America/Lima declared immediate dates, UTC evidence; decimal-string operational keys; planned assignment boundaries and ambiguous mappings remain deferred. The currently approved architecture/specs are unchanged in product meaning.

## Environment and commands

Observed PHP 8.4.26, PHPUnit 12.5.38, Illuminate Database/Events/Filesystem 13.35.0 (existing lock). This is an Illuminate harness, not a full Laravel/React application. Effective MySQL: 8.4.9, owned isolated binary under LOCALAPPDATA/Temp/opencode/eval-u1; bound only to 127.0.0.1:3307. Database eval_u1_test is disposable; no institutional data/service was used. TLS verified with TLS_AES_256_GCM_SHA384.

```powershell
# Existing isolated runtime/state/TLS material only; no downloads or credentials in Git.
./backend/scripts/start-u1.ps1
./backend/scripts/local.ps1 -Command migrate -Role migration -Arguments @('--fresh-u1')
./backend/scripts/start-u1.ps1 -ConfigureRuntime
./backend/scripts/local.ps1 -Command db-smoke
./backend/scripts/local.ps1 -Command migrate -Role migration
./backend/scripts/local.ps1 -Command test
./backend/scripts/local.ps1 -Command test -Arguments @('--filter=IdentityRetentionTest')
./backend/scripts/local.ps1 -Command test -Arguments @('--filter=TemporalBoundaryTest')
./backend/scripts/local.ps1 -Command test -Arguments @('--filter=WriteGuardPreconditionTest')
```

Fresh rebuild explicitly discarded old synthetic U2 tables only in eval_u1_test. Production-style migration down refuses to destroy permanent identity. Four tables remain: migrations, retained_identities, academic_identity_references, academic_write_guard. The migration runner loads only 000001; default PHPUnit loads only Unit plus IdentityRetentionTest. Positional FoundationSchemaTest override was verified denied before DB access. An idempotent migration rerun retained the U1-only schema.

## Results and test-first evidence

| Run | Exact result |
|---|---|
| Full isolated U1 suite | 43 tests, 150 assertions, 0 failures/errors |
| IdentityRetentionTest | 16 tests, 90 assertions |
| TemporalBoundaryTest | 26 tests, 57 assertions |
| WriteGuardPreconditionTest | 1 test, 3 assertions |
| db-smoke | MySQL 8.4.9; eval_u1_test; 4 tables; verified TLS |

New pure-contract RED: 24 tests, 3 assertion failures (K-prefixed key and unsupported timing/date accepted). Integration RED against the prior disposable schema: 10 tests, 34 assertions, 3 assertion failures (CHAR identity, U2 tables, excess runtime grants) and 4 errors, including ordinal float overflow. Import/BOM/startup errors were not used as assertion RED evidence. Confirmation-contract RED: 25 temporal tests, 1 assertion failure. Timezone RED: 26 temporal tests, 1 assertion failure. Final GREEN above includes subsequent existing-behavior coverage. Historical RED/GREEN for original U1 code is not reconstructed. Earlier projection coverage was written after implementation and is recorded as such in reconciliation history.

## Criterion traceability

| Task | Implemented and verified U1 evidence |
|---|---|
| 2.1 | Positive ordinal; timestamp UTC normalization; equal declared dates; calendar/order negatives; half-open touching boundaries; null infinity; same-day nonempty history; equal/regressed clocks do not order authority; immediate timing rejects scheduled/retroactive/corrective/date override; missing confirmation has no effective boundary; persisted guard/identity remain unchanged after timing rejection |
| 2.2 | OperationalBoundary and EffectiveInterval; decimal-string keys including above JS safe integer; separate historical declared dates/keys and null end; DeclaredDateRange explicit inclusive assignment containment, invalid-date negatives and open planning horizon without closing the original range; enrollment has no implicit containment; immediate school date uses America/Lima, evidence remains UTC |
| 2.3 | Exactly one seeded guard; direct CHECK/PK probes through separate migration connection; committed allocation and rollback; final signed-64-bit ordinal and exhausted-range rejection without mutation; shared unsigned BIGINT identity; deactivation/credential removal preserves identity; unreferenced identity permanence; denied runtime ID INSERT/UPDATE/DELETE; migration-trigger protections; restrictive FK and missing-parent rejection; runtime no DDL/DELETE/EXECUTE; identity/reference rollback together |

TransferTiming is a pure prerequisite contract, not a transfer writer. Its boundary must be supplied by a future caller only after successful server commit; U6 must prove actual transfer atomicity and confirmation. U1 does not implement enrollment transfers, planned date/key mapping, replacements, retries, locksets, user management or lifecycle ledger.

## Integrity and rollback

Runtime grants are table/column-specific: SELECT, controlled credential-state INSERT/UPDATE; guard SELECT/last_ordinal UPDATE; reference SELECT/INSERT; migration-table SELECT. Runtime cannot insert explicit identity IDs, update IDs, delete identities/references/guard, or create tables. Triggers additionally reject identity delete, ID update and explicit-ID insertion even with migration DML. All three reference contexts share one identity; they confer no roles or permissions.

Tests distinguish error 1142 (table privileges), 1143 (column privileges), 1644 (retention trigger), 3819 (CHECK), 1062 (PK), 1452 (FK). Tests rollback synthetic fixtures; committed retention probes remain as synthetic permanent identities. Rollback is never production deletion. Stop/disable dependent behavior before a reviewed forward fix; destructive rebuild is explicitly limited to the disposable U1 database.

## Gates and next unit

Tasks 2.1–2.3 are complete for their U1 prerequisite scope. Gate 1.1 stays complete. Gates 1.2–1.8 remain unchecked; their applicable technical evidence is supplemented, not blanket-certified. U2 still requires a separate prerequisite/readiness review, names/key MySQL evidence, candidate schema reconciliation and its own runner/permissions. Its BIGINT mapping and neutral planned representation are human-approved; ambiguous mappings and planned-parent activation/work eligibility remain unresolved. U2/U3 have not run in this change. No push or remote operation occurred.

Final integrity review added an ordinal-regression probe: meaningful RED 1 test / 3 assertions / 1 failure (SQL regression unexpectedly allowed). The migration now enforces non-regression; explicit fresh disposable rebuild and final GREEN: 43 tests / 150 assertions, including 16 identity/guard tests / 90 assertions. Runtime grants survived disposable rebuild and were reverified. This is a guard invariant, not the U3 canonical lock/retry protocol.
