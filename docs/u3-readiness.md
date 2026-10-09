# U3 Scoped Readiness and Retry Decision

Date: 2026-10-07. Predecessor: 8a299e94fb3babdfb2919a3dbfe5decabac449c4.

The human replied Continue to the concrete recommended policy: maximum three total transaction attempts, retry only after confirmed rollback, no retry/replay of uncertain commits and no automatic request replay. This records that selection, not a policy invented from a pending gate. The earlier implementation authorization permits sequential approved units after their prerequisites; U1/U2 are verified and locally committed. Numeric size limit was removed by the human; actual size remains reportable.

U3 scope is tasks 2.5–2.7: guard-first ordered locks, current locked validation, rollback-safe allocation and mutations, typed append-only ledger. Shared retained BIGINT identity mapping and exact MySQL 8.4.9 harness are established. Pending planned/non-current mappings and planned-parent activation/work eligibility are not used by this generic protocol; operation-specific permissions remain future command responsibilities.

Implementation decision: own a top-level transaction; acquire exclusive singleton guard before discovery; discover full set, merge actor/old/new identities once; lock by canonical table rank and decimal numeric ID order; rediscover from locked context and restart if the set changes; revalidate actor and server-provided authorization callback before allocation; append typed events last; commit. Validation callbacks must be read-only and mutations must not perform external effects. Caller must supply actor identity from a future local authenticated adapter; this unit exposes no HTTP endpoint and does not establish real authentication or role policies.

Retry causes: rediscovery change, MySQL deadlock/serialization failure, or lock-wait timeout only after explicit whole-transaction rollback verified by transaction level and PDO state. At most three attempts. Domain/constraint failures do not retry. Any commit-stage failure is conservatively an uncertain outcome, carries correlation evidence and never retries. A rollback failure also stops, without retry. No idempotency endpoint or automatic request replay is added.

A new eval_u3_test database on the same verified isolated server uses the existing accounts, with ledger runtime SELECT/INSERT only and column-scoped source privileges for integrity tests. U1/U2 remain independent. Real two-connection barriers and lock-wait failures supplement deterministic injected commit/deadlock faults; injection is recorded honestly. No production data, remote operation, 2e5d053 or deferred feature is involved.

Gates 1.2–1.8 remain unchecked unless their complete original criteria are met. This unit supplies applicable retry, identity/permission and harness evidence only. U4 requires independently established local authenticated actor integration and must not start merely because U3 finishes.
