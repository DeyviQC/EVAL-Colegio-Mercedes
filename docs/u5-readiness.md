# U5 Scoped Readiness

Date: 2026-10-07. Normative predecessor: 6262d4faedb62e42ce0e554eb5f13753eddd4dc6. U4 implementation: 77826e66ae5482ffd384198cfe3b1ca11a4491cb.

The human explicitly approved docs/u5-human-decisions.md before implementation. U5 is tasks 4.1/4.2 only: enrollment creation/administrative closure, scoped occupied-interval query and tests. U6 transfer, U7 assignment lifecycle, general U9 reads/policies and public HTTP endpoints remain outside this handoff.

| Gate | Applicable readiness |
|---|---|
| 1.1 | Approved prototype/base architecture; existing approval unchanged |
| 1.2 | Separate declared DATE and actual operation K from U1/U3. Approved enrollment closure maps a valid past/current declared end to actual successful K without retroactivity; assignment/replacement mappings remain pending |
| 1.3 | Mandatory period identity; no additional declared enrollment-date containment. Date ordering and catalog/identity/conflict checks remain |
| 1.4 | New active enrollment requires active parent under the new human decision; residual valid Director/Administrator closure is allowed with a closed parent/inactive catalog. Planned assignment permissions are unchanged |
| 1.5 | Reuse U4 approved catalog identities/keys; no new comparison policy |
| 1.6 | AuthenticatedActor/credential revision from verified local authentication; permanent BIGINT IDs; Director/Administrator authority re-read under U3. Credentials/roles are not the student's retained academic identity |
| 1.7 | Separate eval_u5_test MySQL allowlist/configuration; retained fixture history, actual concurrent commands, rollback and permission evidence |
| 1.8 | Verified U3 maximum three total attempts after confirmed rollback only; no uncertain request replay; authorized sequential local handoff and removed numeric line limit |

Creation stores approved declared dates as data, allocates its operational start at actual successful creation K, and creates no scheduling field or date-to-key conversion. Declared end does not automatically expire an active row. Conflict checks use half-open operational intervals across all relevant states, not calendar-midnight inference or active-state filtering. A new candidate starts at prospective K only after complete locked validation. Historical query probes may inspect earlier keys but do not authorize backdated creation.

Closure preserves immutable student/period/grade/section/start and updates only state, declared end and operational end at K. Reject a future date, end before start, repeated closure/reopen request or invalid operational interval. Do not require active parent/catalog/subject credentials for valid residual cleanup; actor authentication/role and retained identities still govern. Accepted submission persistence is not implemented until its approved later unit.

No gate checkbox closes merely from this readiness record. Finish 4.1/4.2 only with complete scoped evidence and commit before U6. No dependency downloads, remote Git operation, production data or 2e5d053 integration are needed.
