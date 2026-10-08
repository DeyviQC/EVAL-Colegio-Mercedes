# U8 Teacher Replacement Verification

Date: 2026-10-07. Normative prerequisite: 2551051, docs/u8-human-decisions.md; U7 predecessor b2d9d9f. Scope: 5.3/5.4.

## Actual RED/GREEN

The initial four tests preceded replacement implementation. Stub RED: 4 tests / 16 assertions, four failures (missing successor, closed-parent/date/conflict admission not enforced). Initial GREEN: 4 tests / 43 assertions. Additional tests were written during verification; no historical RED sequence is manufactured for them. One test fixture initially used unsupported credential_status inactive; it was corrected to the existing approved deactivated status without changing schema or product rules.

Final command: `backend/scripts/local.ps1 -Command test -Unit U8`. Result: **27 tests / 333 assertions, zero failures/errors/warnings**. Effective PHP 8.4.26, PHPUnit 12.5.38, existing Illuminate 13.35.0. `db-smoke -Unit U8`: MySQL 8.4.9, isolated eval_u8_test, 15 tables, TLS_AES_256_GCM_SHA384. Existing migrations only; no new application dependency/schema. Owned isolated runtime was restarted for grants, never production. No secrets are recorded.

| Evidence | Verified behavior |
|---|---|
| Immediate confirmation | Prior closed/distinct active successor, today declaration, shared actual K, source identity/scope/start retained, unique lineage |
| Authority | Director/Admin and Vice Principal admitted; forged teacher/student role snapshots, revoked stored roles and credential revision denied |
| Identity/login separation | Retained deactivated teacher identities without live credentials remain academic references; actor authority is checked separately |
| Admission | Reject same teacher, non-active prior, planned/closed parent, inactive catalog, malformed/injected fields, unknown IDs, supplied past/future/null/invalid dates |
| Calendar | Today must be contained; state-active calendar-past/future parents rejected without source changes; separately approved past-date cleanup remains possible |
| Planning/actual history | Planned/future reservation and open actual destination conflict; differing entry scope allowed; closed past destination history admitted; no expiry from declared end alone |
| Same day | Replacement chain has distinct IDs/unique predecessors, nonempty ordinal intervals and unchanged original teacher |
| Canonical locks | Reversed teacher IDs lock one numeric sorted actor/teacher union; prior/destination scoped history retained |
| Insert rollback | Real trigger rejects successor INSERT after prior closure; prior/ordinal/events/count all restored, PDO transaction ended |
| Ledger rollback | Real trigger rejects second event after first event and both source mutations; entire transaction restored |
| Retention/permissions | Runtime lineage/teacher UPDATE denied (1143), DELETE denied (1142); prior maintenance DELETE blocked (1451); duplicate predecessor rejected (1062) |
| Four real races | Same prior produces one successor; competing prior assignments cannot acquire same destination authority; parent closure first denies replacement; reverse order retains active successor after parent closure |

Races use separate PHP processes and MySQL connections. First source mutation is held before commit, second command is observed waiting on academic_write_guard FOR UPDATE, then released. Atomic confirmations are returned only after commit through existing U3. Generic retry/uncertain-outcome proofs remain verified U3/U6, not a new U8 protocol or manufactured transaction failure.

## Regression receipt

| Suite | Tests | Assertions |
|---|---:|---:|
| U1 | 43 | 150 |
| U2 | 27 | 182 |
| U3 | 23 | 154 |
| Local authentication | 24 | 127 |
| U4 | 26 | 323 |
| U5 | 21 | 307 |
| U6 | 22 | 291 |
| U7 | 26 | 341 |
| U8 | 27 | 333 |
| Total | 239 | 2208 |

All suites pass. Scoped PHP lint, PowerShell parser checks and git diff whitespace checks pass. Tasks 5.3/5.4 complete at this tested command boundary; gates 1.2–1.8 remain open. Persisted activity/submission original ownership, U9 policies/public HTTP and browser/LAN/load/deployment evidence remain later work. No apply, remote Git operation, candidate integration or deferred capability is claimed.
