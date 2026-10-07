# Exploration: Academic Foundation

## Current State

EVAL is in pre-implementation. The repository contains OpenSpec project context and placeholder directories, but no application code, database schema, dependencies, executable tests, or main specifications. The planned stack is Windows Server, Laravel/PHP, MySQL, React/TypeScript, and Tailwind CSS; IIS versus Apache remains undecided.

Validated project context already establishes the academic model:

- Every student enrollment and teaching assignment belongs to an academic period, and historical periods are retained.
- A student's enrollment for a period determines grade and section and therefore visible subjects or areas, activities, and resources.
- Each teaching assignment has an independent identity across period, teacher, subject or area, grade, and section.
- Teachers may hold multiple concurrent assignments, and multiple teachers may serve the same subject or area, grade, and section through distinct assignments.
- Replacing a teacher closes or deactivates the prior assignment and creates a new one without deleting or overwriting history.
- Each activity belongs to one teaching assignment; each submission is routed automatically through its activity to that original assignment. Students never select the receiving teacher.
- The server derives authorization from authenticated role, active or requested period, enrollment, and teaching assignment rather than trusting client-supplied academic scope.

## Affected Areas

- `openspec/config.yaml` — authoritative project context containing the validated academic decisions and scope boundaries.
- `openspec/specs/` — currently empty; later specification work must define the normative academic behavior and acceptance scenarios.
- `backend/` — placeholder only; a future implementation will enforce academic lifecycle, routing, and authorization rules here.
- `database/` — placeholder only; future persistence must preserve period, enrollment, assignment, activity, and submission history.
- `frontend/` — placeholder only; future clients may request operations but must not determine academic authorization or submission recipients.
- `README.md` and `AGENTS.md` — establish pre-implementation status, local availability, product identity, and durable role boundaries.

## Scope Boundaries

### First-change inclusions

- Academic periods and retained historical periods.
- A basic subject or area catalog plus grades and sections.
- Student enrollment per academic period, with grade and section determined by that enrollment.
- Independently identified teaching assignments across period, teacher, subject or area, grade, and section.
- Concurrent teaching assignments and non-destructive teacher replacement.
- Server-derived academic authorization rules for director/administrator, vice principal, teacher, and student boundaries.
- Minimal activity and submission contracts needed to guarantee assignment ownership and automatic routing.

### First-change exclusions

- Full educational-material management and vice-principal material observations.
- AD/A/B/C grading and auxiliary grade-register behavior.
- Library, educational search, advanced file storage, and final UI.
- Local/remote synchronization behavior and conflict policy.
- Replacing SIAGIE and QR attendance.
- Application scaffolding, executable schema, CI, deployment, and web-server selection.

### Local-server implications

- Essential student authorization, academic visibility, activity access, and submission routing must execute on the school-hosted server over LAN/WLAN without external Internet.
- Correctness cannot depend on an external identity, authorization, catalog, queue, or routing service during local operation.
- The local server is the authority for period, enrollment, assignment, activity, and submission relationships in this change; later synchronization must not be assumed or designed implicitly here.
- External-Internet independence does not imply operation when the local server, network, or power is unavailable.

## Approaches

1. **Temporal academic foundation with stable identities** — Represent periods, enrollments, and teaching assignments as explicit, independently identified records; model replacement as lifecycle transition plus a new assignment; bind activities and submissions to stable assignment identity.
   - Pros: Preserves history, supports concurrent assignments, makes routing deterministic, and provides trustworthy server-side authorization inputs.
   - Cons: Requires explicit lifecycle, uniqueness, overlap, and historical-access rules in later specification and design.
   - Effort: Medium

2. **Mutable current-state mapping** — Keep only the current grade/section per student and current teacher per subject or area, grade, and section, updating those values when assignments change.
   - Pros: Smaller initial conceptual model and simpler current-state queries.
   - Cons: Destroys or obscures history, cannot safely distinguish concurrent assignments, breaks ownership of prior activities and submissions, and encourages authorization from mutable client-visible fields.
   - Effort: Low initially, High when history and routing are added

3. **Defer academic identity to activity creation** — Store broad role access and select or copy recipient and classroom data when each activity or submission is created.
   - Pros: Avoids defining the complete academic foundation immediately.
   - Cons: Duplicates academic truth, allows routing drift, weakens authorization, risks student-selected recipients, and cannot reliably reconstruct teacher replacement history.
   - Effort: Medium initially, High to correct

## Recommendation

Use the temporal academic foundation with stable identities. Treat academic period, enrollment, and teaching assignment as the authoritative chain for visibility and authorization, while retaining activity ownership by the original assignment. Teacher replacement must be additive and non-destructive: close or deactivate the prior assignment, create a new assignment, and preserve all prior relationships.

The proposal should remain behavior-focused rather than selecting schema details. Subsequent specification and design must define period and assignment lifecycle states and dates, uniqueness and overlap constraints, subject-versus-area catalog semantics, role-by-operation permissions, enrollment transfer behavior within a period, and who may access historical records.

## Risks

- Undefined lifecycle states or effective dates could make current versus historical authorization ambiguous.
- Missing uniqueness and overlap rules could create duplicate or conflicting enrollments and assignments.
- Enrollment changes within a period could unintentionally alter access to previously visible activities unless historical-access behavior is explicit.
- Multiple teachers serving the same academic scope could be conflated if authorization uses only the period, subject or area, grade, and section rather than assignment identity.
- A future remote component could conflict with local authority unless synchronization ownership and conflict handling are designed separately before implementation.
- Local availability will fail if any essential authorization or routing dependency is later placed exclusively on the external Internet.

## Ready for Proposal

Yes. The validated decisions are sufficient to propose the bounded `academic-foundation` change. The proposal should carry the listed inclusions, exclusions, invariants, and unresolved specification/design questions forward without introducing implementation artifacts.
