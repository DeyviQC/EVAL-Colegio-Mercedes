# U1 Exception Record

| Field      | Value                                               |
|------------|-----------------------------------------------------|
| Scope      | academic-foundation tasks 2.1, 2.2, 2.3 only        |
| Authority  | Human-approved narrow exception (see AGENTS.md)      |
| Recorded   | 2026-10-07                                           |

## What this exception authorizes

1. **Task 2.1** — Isolated local environment setup (PHP, Composer, MySQL test instance; no global installs).
2. **Task 2.2** — OperationalBoundary and EffectiveInterval value objects with unit tests (TDD, no DB).
3. **Task 2.3** — Entity classes (AcademicPeriod, Grade, Section, Subject, Area), AcademicWriteGuard, MySQL migrations, and integration tests against real `eval_u1_test` database.

## What this exception does NOT authorize

- Completing gate 1.1 or any other planning gate.
- U2 tasks (repository layer, domain services, seed/fixture utilities).
- U3 tasks (migration apply to dev DB, API endpoints, write guard retry/backoff).
- User management, identity schema beyond UUID v7 entity IDs, or auth logic.
- Lifecycle ledger, locking/retry protocols, or chain strategy.
- Commits, pushes, PRs, global installations, admin operations, production access, or remote execution.
- Scope expansion or size exceptions.

## Approved design decisions applied

| Decision | Detail |
|----------|--------|
| OperationalBoundary | Ordinal (int > 0) + UTC timestamp as evidence; order exclusively by ordinal |
| EffectiveInterval | `[start, end)` half-open; start required, end nullable |
| Declared dates | Remain separate fields; not converted to ordinals |
| Temporal mappings | Not resolved in U1 |
| Identity | Durable, UUID v7, non-reusable IDs, RESTRICT FK |
| AcademicWriteGuard | Singleton row with CHECK(id=1); INSERT=acquire, DELETE=release |
| TDD approach | Tests written before implementation; MySQL real (non-production) for integration |

## Completion criteria

Tasks are NOT auto-marked complete. Human reviews test results, code, and schema before marking progress.
