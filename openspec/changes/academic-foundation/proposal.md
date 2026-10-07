# Proposal: Academic Foundation

## Intent

Establish the normative academic foundation that EVAL needs before application design or implementation. The change will make academic periods, student enrollments, and independently identified teaching assignments the authoritative chain for academic visibility, authorization, activity ownership, and submission routing while retaining historical relationships.

## Scope

### In Scope

- Academic periods with retained historical periods.
- A basic academic catalog covering subjects or areas, grades, and sections.
- Student enrollment per academic period, with each enrollment determining the student's grade and section.
- Stable, independently identified teaching assignments across academic period, teacher, subject or area, grade, and section.
- Multiple concurrent teaching assignments, including multiple teachers serving the same academic scope through distinct assignments.
- Non-destructive teacher replacement by closing or deactivating the prior assignment and creating a new assignment without deleting or overwriting history.
- Server-derived academic authorization for director/administrator, vice principal, teacher, and student boundaries.
- Minimal future-facing contracts that bind each activity to one teaching assignment and route each submission through its activity to the original assignment without student-selected recipients.
- Essential academic authorization, visibility, and routing behavior on the school-hosted server over LAN/WLAN without external Internet while local infrastructure and power are available.

### Out of Scope

- Educational materials and vice-principal material observations.
- AD/A/B/C grading and auxiliary grade-register behavior.
- Library, educational search, advanced file storage, and final UI.
- Local/remote synchronization behavior, ownership, and conflict policy.
- Replacing SIAGIE and QR attendance.
- Application scaffolding, executable database schema, source implementation, tests, CI, deployment, and web-server selection between IIS and Apache.

## Capabilities

### New Capabilities

- `academic-periods`: Defines period identity, lifecycle boundaries, current-versus-historical interpretation, and history retention.
- `academic-catalog`: Defines the basic subjects-or-areas, grades, and sections used by enrollments and teaching assignments.
- `student-enrollment`: Defines period-bound enrollment, enrollment-derived grade and section, and the resulting student academic scope.
- `teaching-assignments`: Defines stable assignment identity, concurrent assignments, lifecycle transitions, and non-destructive teacher replacement.
- `academic-authorization`: Defines server-derived role and academic-scope authorization without trusting freely supplied client scope.
- `activity-submission-routing`: Defines the minimal assignment ownership and automatic routing contracts for future activities and submissions.

### Modified Capabilities

None. `openspec/specs/` contains no existing capability specifications.

## Approach

Use the exploration-recommended temporal academic foundation with stable identities. Academic periods, enrollments, and teaching assignments will be explicit authoritative records; lifecycle changes will preserve prior records rather than mutate historical identity. Activities will retain the identity of their originating teaching assignment, and submissions will derive their destination from the activity instead of client-selected recipients. Authorization decisions will be made by the local server from the authenticated role, relevant period, enrollment, and teaching assignment.

The specification and design phases must resolve lifecycle states and effective dates, uniqueness and overlap constraints, subject-versus-area catalog semantics, enrollment transfer behavior within a period, role-by-operation permissions, cardinality rules, and historical-access rules. These details refine the approved foundation and are not blockers to this proposal.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `openspec/changes/academic-foundation/proposal.md` | New | Records the bounded intent, capability contract, approach, risks, and acceptance criteria. |
| `openspec/changes/academic-foundation/specs/` | Future new | Will contain normative specifications for the six proposed capabilities. |
| `openspec/changes/academic-foundation/design.md` | Future new | Will define the approved technical model after requirements are specified. |
| `database/` | Future modified | Persistence design must preserve periods, enrollments, assignments, and original activity/submission relationships. |
| `backend/` | Future modified | Server behavior must enforce lifecycle, authorization, visibility, and deterministic routing rules. |
| `frontend/` | Future modified | Clients may request operations but must not determine academic scope or submission recipients. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Ambiguous lifecycle states or effective dates produce inconsistent current and historical authorization. | Medium | Define explicit lifecycle transitions, temporal boundaries, and acceptance scenarios during specification and design. |
| Missing uniqueness, overlap, or cardinality rules permit conflicting enrollments or assignments. | Medium | Specify allowed concurrency separately from prohibited duplication and validate each rule server-side. |
| Enrollment changes unintentionally alter access to earlier activities. | Medium | Specify transfer and historical-access behavior before persistence or authorization design is approved. |
| Academic scope is conflated when multiple teachers serve the same grade and section. | High | Require stable teaching-assignment identity for ownership, authorization, and routing rather than relying only on catalog fields. |
| Client-supplied identifiers are treated as authorization evidence. | High | Require the server to derive and verify academic scope from authenticated role and authoritative relationships on every protected operation. |
| A future remote component undermines local authority or external-Internet independence. | Medium | Keep synchronization excluded and require a separate approved design for ownership, conflicts, auditing, and recovery. |

## Rollback Plan

This phase creates documentation only. Roll back by reverting `openspec/changes/academic-foundation/proposal.md`; no runtime, schema, or user data requires restoration. For later implementation, each delivery slice must remain independently reversible, and rollback must disable or revert new behavior without deleting historical academic records or severing original assignment relationships.

## Dependencies

- Approved EVAL project context and academic decisions in `openspec/config.yaml`.
- The validated exploration in `openspec/changes/academic-foundation/exploration.md`.
- Approval of the six capability specifications and their acceptance scenarios before technical design.
- Approval of technical architecture and a prototype before application implementation, schema creation, or scaffolding.
- Local server, LAN/WLAN, and power for essential local operation; external Internet is not a dependency for the scoped behavior.

## Success Criteria

- [ ] Six capability specifications are produced with no unplanned modified capability and cover every in-scope item.
- [ ] Every enrollment and teaching assignment is specified as belonging to exactly one academic period, with historical periods retained.
- [ ] Teaching assignments have stable independent identities and explicitly support concurrent assignments and non-destructive teacher replacement.
- [ ] Role and academic boundaries are specified so authorization is derived server-side from authoritative period, enrollment, assignment, and role data.
- [ ] Activity ownership and submission routing are specified so each submission reaches the activity's original teaching assignment and students cannot select a recipient teacher.
- [ ] Acceptance scenarios demonstrate essential scoped behavior without external Internet when the local server, LAN/WLAN, and power are available.
- [ ] Lifecycle, cardinality, catalog, permission, enrollment-transfer, and historical-access questions are resolved in specification or design before implementation begins.
- [ ] No excluded module, application scaffold, executable schema, deployment choice, synchronization behavior, or IIS-versus-Apache decision is introduced by this change.
