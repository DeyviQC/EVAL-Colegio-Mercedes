# Design: Academic Foundation

## Technical Approach

EVAL will implement the academic foundation as a local, server-authoritative Laravel module backed by MySQL. Stable records for periods, catalog entries, enrollments, and teaching assignments carry current lifecycle state, while append-only lifecycle events and immutable foreign-key relationships preserve history. React clients may submit resource identifiers as request targets, but Laravel policies and application services will reconstruct the authorization context from the authenticated user and authoritative local records.

The model uses explicit effective intervals for enrollment and teaching-assignment scope. Student transfers are immediate, successful server-confirmed operations only; scheduled, retroactive, and corrective transfers are rejected. Transfers and supported teacher replacements are atomic, additive operations: the prior record is ended and a new record is inserted. Enrollment requires a period identity without additional declared-date containment; assignment containment remains required. Closed periods prohibit new academic work but permit valid, role-authorized residual closure and historical reads without cascading child state changes. The minimum activity/submission boundary stores the original teaching-assignment route and the enrollment under which a submission was accepted; it intentionally does not design the deferred activity or submission lifecycle.

This document preserves the normative planned architecture from 38e90ff. The local branch now contains an isolated Illuminate/PHPUnit U1 harness and unaccepted U2 candidate schema; it has no approved complete Laravel/React application. Current verification and remaining gates are recorded in docs/reconciliation-academic-foundation.md. Candidate code is not evidence of architecture or prototype acceptance.

## Design Boundaries

### In scope

- Persistence and lifecycle handling for academic periods, instructional catalog entries, grades, sections, student enrollments, and teaching assignments.
- Server-derived authorization for Director/Administrator, Vice Principal, Teacher, and Student.
- Historical identity and relationship preservation.
- Atomic student transfer and teacher replacement.
- The minimum technical contract `activity -> original teaching assignment -> submission`.
- Local operation over the school LAN/WLAN without external Internet.

### Explicitly deferred

- Complete activity behavior and activity availability.
- Educational materials and vice-principal observations on materials.
- AD/A/B/C grades and auxiliary grade-register behavior.
- Library and local Encarta-style educational search.
- Local/remote synchronization, ownership, and conflict resolution.
- Submission resubmission, versioning, withdrawal, or processing.
- Advanced file storage and final UI.
- Replacement of SIAGIE, QR attendance, and selection of IIS or Apache.

The activity and submission structures below are reference contracts only. They do not define the deferred capabilities.

## Domain Model

```text
AcademicPeriod 1 ──────── * StudentEnrollment * ──────── 1 Student
       │                         │
       │                         ├── 1 Grade
       │                         └── 1 Section ────────── 1 Grade
       │
       └────────── * TeachingAssignment * ─────────────── 1 Teacher
                              │   │
                              │   ├── 1 InstructionalEntry (subject | area)
                              │   ├── 1 Grade
                              │   └── 1 Section ───────── 1 Grade
                              │
                              └── * ActivityReference ─── * SubmissionReference
                                         │                         │
                                         │                         ├── 1 Student
                                         │                         └── 1 accepted-under Enrollment
                                         └──────── original assignment route ─────┘

LifecycleEvent * ── typed restrictive foreign key to exactly one period,
                    catalog entry, enrollment, or teaching assignment
```

### Core records

| Record | Stable and immutable fields | Mutable controlled fields | Retention rule |
|---|---|---|---|
| Academic period | `id`; creation values `name`, `start_on`, and `end_on` are retained | lifecycle state | Referenced periods cannot be deleted or re-identified; date/name update behavior is not introduced by this design. |
| Instructional entry | `id`, classification after first reference | display name, active flag | Inactive entries and references remain available. |
| Grade | `id` | display name, active flag | Referenced grades remain available. |
| Section | `id`, `grade_id` after first reference | display name, active flag | Referenced sections remain attached to their grade. |
| Student enrollment | `id`, student, period, grade, section, declared `effective_from`, operational start key | lifecycle state, declared `effective_until`, operational end key set once | Transfer or closure never rewrites scope or identity. |
| Teaching assignment | `id`, period, teacher, instructional entry, grade, section, declared `effective_from`; operational start key once activated | lifecycle state, operational start key set at activation, declared `effective_until`, operational end key set once | Closure or replacement never rewrites scope or identity. |
| Activity reference | `id`, `teaching_assignment_id` | Deferred | Assignment identity cannot change. |
| Submission reference | `id`, student, activity, original assignment, accepted-under enrollment, accepted timestamp and operation key | Deferred | Route and ownership references cannot change. |

Primary keys are planned as unsigned MySQL `BIGINT` values generated locally. `BIGINT` is an implementation choice, not a product requirement. Stable identity and retention are normative; identifiers are never reused and are not authorization evidence. Introducing globally coordinated identifiers for future synchronization is explicitly deferred with synchronization design.

## Persistence Model

The table names below are planned names for later Laravel migrations, not existing schema.

| Table | Key columns and relationships | Database-enforced constraints |
|---|---|---|
| `academic_periods` | `id`, `name`, `name_key`, `start_on`, `end_on`, `state`, timestamps | Unique name key under the approved equality rule; `start_on <= end_on`; state check; generated unique active guard allows at most one `active` row. |
| `instructional_entries` | `id`, `kind`, `name`, `name_key`, `is_active` | Kind is `subject` or `area`; generated active-name key plus unique index prevents duplicate active names within kind. |
| `grades` | `id`, `name`, `name_key`, `is_active` | Generated active-name key plus unique index prevents duplicate active grade names. |
| `sections` | `id`, `grade_id`, `name`, `name_key`, `is_active` | Foreign key to grade; generated active-name key plus unique index prevents duplicate active section names within a grade. |
| `student_enrollments` | `id`, `student_id`, `academic_period_id`, `grade_id`, `section_id`, `state`, declared `effective_from`/nullable `effective_until` dates, operational boundary keys | Non-null foreign keys with restrictive deletes; state/date/order checks; `(section_id, grade_id)` references the indexed pair on `sections`. |
| `teaching_assignments` | `id`, `academic_period_id`, `teacher_id`, `instructional_entry_id`, `grade_id`, `section_id`, `state`, declared `effective_from`/nullable `effective_until` dates, operational boundary keys, nullable `replaces_assignment_id` | Non-null restrictive foreign keys; state/date/order checks; unique successor link; `(section_id, grade_id)` references the indexed pair on `sections`; self-reference preserves replacement lineage. |
| `academic_lifecycle_events` | `id`, typed nullable target FKs, event type, previous/new state, declared effective date, operational boundary key, `actor_id`, correlation ID, recorded timestamp, metadata | Exactly one target FK populated, with a matching entity type; restrictive target and actor FKs; append-only application permissions; indexed by target and operation order. Not the source of current authorization state. |
| `activity_references` | `id`, `teaching_assignment_id` | Restrictive foreign key; assignment link immutable. No activity lifecycle fields are designed here. |
| `submission_references` | `id`, `student_id`, `activity_id`, `teaching_assignment_id`, `accepted_under_enrollment_id`, `accepted_at`, acceptance operation key | Restrictive foreign keys; assignment must equal the activity's assignment, enforced by service validation and transaction. No submission processing fields are designed here. |
| `academic_write_guard` | Singleton `id`, next operation ordinal | Pre-created technical row; exclusive lock serializes foundation writes and minimum acceptance, including writes with no existing target row. Not a domain entity. |

MySQL `CHECK` constraints will express row-local invariants where supported by the selected MySQL version. Foreign keys and unique indexes are the final guard for relationship and uniqueness invariants. Cross-row interval overlap and cross-table route equality remain application-service checks executed under locks because MySQL has no native exclusion constraint.

The tables, generated indexes, temporal encoding, guard, and ledger are planned implementation choices, not existing schema. Approved name equality ignores case and outer whitespace, uses NFC normalization, and distinguishes accents. Period uniqueness covers all states; catalog uniqueness covers active entries within kind or parent only. For example, subject `Álgebra` and outer-spaced decomposed ` áLGEBRA ` (U+0061 + U+0301) collide, while area is a separate scope and `Algebra` is distinct. Grade duplicates and same-grade section duplicates use the same policy; equal section names in different grades remain permitted. Server `name_key` generation and MySQL comparison must reproduce this policy on create, active rename, and reactivation; neither MySQL collation defaults nor React define product equality. Exact whitespace trimming, case mechanics, key encoding, and version-specific index/collation behavior remain technical dependencies. Keep the visible user label separate from the comparison key and preserve it except for a future precisely defined whitespace-display policy; this is not a historical label-snapshot requirement.

### State and history representation

Current state is stored on each aggregate for efficient authorization. A lifecycle transition updates only the controlled current-state fields and appends an `academic_lifecycle_events` row in the same transaction. Identity and scope foreign keys are not changed to simulate transfers, replacements, or reclassification.

Closing a period updates only that period and its event, without automatically closing active children. Those children cease to grant new-work authority under the closed parent. Director/administrator may explicitly close residual active enrollments; director/administrator or vice principal may explicitly close residual active assignments. Cleanup still requires valid dates, including assignment containment, and retains identities, owners, routes, and accepted-under enrollment context. It creates no successor and does not rewrite history. Global catalog operations and other periods are unaffected.

This is not event sourcing: current rows remain authoritative. The event ledger supplies transition history, actor, declared date, operational order, and correlation evidence. Referential integrity uses `RESTRICT`/`NO ACTION`, never cascading deletes, across retained academic history. Catalog display-name corrections retain stable IDs; the corresponding event captures the prior and new display value.

Use typed target foreign keys for periods, instructional entries, grades, sections, enrollments, and assignments, not an unenforced polymorphic type/ID pair. A row-local check permits exactly one target and verifies its discriminator. These restrictive FKs protect targets referenced only by the ledger as well as targets referenced by academic records. `actor_id` references a retained local identity: account deactivation or credential removal does not remove or re-identify the audit identity. Identity storage and its retention-compatible FK are future schema dependencies, not an existing user table or a new account-management capability.

The runtime database role may `SELECT` and `INSERT` ledger rows, but not `UPDATE` or `DELETE`; transitions and event inserts commit or roll back together. Migration/maintenance privileges must be separate and must not be used by runtime commands to rewrite evidence. Constraints, permission tests, and transactional handlers together enforce integrity; an application convention alone is insufficient. Actor and target identity dependencies precede ledger migration.

## Effective-Date and Interval Strategy

> **Technical representation of approved behavior, not a new product permission:** the following choices make the approved lifecycle, transfer, and replacement rules unambiguous in storage and comparisons.

- Preserve required declared effective start/end dates as school-local `DATE` values; an end date must not precede its start date and may equal it under both lifecycle specs. The configured school time zone determines the declared date of an immediate server-effective operation. A date is not enough to order enrollment, acceptance, transfer, and replacement occurring on that same day; `[D, D)` would incorrectly erase a real interval.
- Separately store server transaction-effective boundary keys: a monotonically increasing operation ordinal allocated under the write guard, with a UTC microsecond timestamp as evidence. Ordinals, not wall-clock precision, determine order. They are technical linearization keys for successful operations, not client scheduling fields. Rollback rolls back the ordinal allocation and all associated writes.
- Operational intervals are half-open `[start_key, end_key)`, with inclusive start and exclusive end. Null enrollment end means no recorded end, treated as infinity for comparisons; period end does not automatically expire it. Activation establishes an assignment's operational start; planned rows have declared dates but no current operational authority. Closure/transfer/replacement records a boundary key as well as its required declared end date.
- At transfer or replacement key `K`, end the prior interval at `K` and start the successor at `K` in one transaction. Creation followed by two same-day transfers has keys `K1 < K2 < K3`, producing nonempty `[K1,K2)` and `[K2,K3)` histories even when every declared date is `D`. No minimum one-day duration or next-day transition is imposed.
- A student transfer becomes effective immediately at successful server-confirmed transaction execution. Reject future scheduling, retroactive transfers, and transfer corrections without changing state, dates, ordinals, ledger evidence, or accepted context. This approved rule does not grant scheduled replacement or settle non-current teacher replacement dates. Planned assignment boundary mapping and non-current replacement/administrative closure date-to-key mapping remain unresolved technical questions, with further approval required for any additional workflow permission. Do not guess midnight, backdate operational authority, or rewrite earlier submission authority.
- `effectiveFrom` and `effectiveUntil` in historical views are declared calendar dates, not exclusive instants. Expose separate `operationalStartKey` and nullable `operationalEndKeyExclusive` when interval order is needed. `accepted_at` records evidence time; the submission also retains its acceptance operation key. No client-supplied clock establishes authority.
- Period `start_on` and `end_on` are inclusive declared calendar boundaries. Teaching-assignment validation requires each supplied start/end date to lie within those boundaries, including an end equal to the period end; it does not compare a null end as infinity and reject every open assignment. For declared-date conflict planning only, an open assignment's comparison horizon is bounded by its period's inclusive end (`end_on + 1 day` as an exclusive calendar boundary). This is not an automatic closure or authorization-expiry rule.
- Enrollment requires exactly one period identity, without additional containment of declared enrollment dates inside period dates. All other date/order, scope, lifecycle, conflict, and authorization checks remain required; absence of containment does not resolve date/key mapping. Teaching-assignment containment remains unchanged. Neither record may span multiple period identities.
- Period dates do not change lifecycle state automatically. Period closure changes only the period and its ledger; it does not cascade closure to enrollments/assignments. A closed period prohibits new enrollments, transfer successors, assignments including planned creation, assignment activation, replacement successors, activities, and all new submissions including late acceptance. Active children cannot override this prohibition. Valid explicit residual closure and role-authorized historical reads remain permitted. Do not require an active parent universally: planned assignment creation in a planned period is permitted with valid dates and no conflict. The planned assignment itself grants no operational authority, and the planned period is not current. Neither statement decides eligibility for activation or new academic work in a requested planned period; that product question remains outside the approved closed-parent restriction and separate from temporal date/key mapping.

For comparable operational keys, intervals overlap exactly when `left.start < right.end` and `right.start < left.end`, with an absent end treated as infinity. Compare relevant historical occupied intervals as well as current rows; current state alone does not reconstruct past conflicts. Declared-date bounds also require validation as applicable: assignment period containment, not enrollment containment. Planned assignment conflicts must be checked, not excluded because they are not active: disjoint declared date ranges are non-overlapping, but dates alone cannot settle every shared-day planned boundary against an operational key. Mapping such planned boundaries and permitted non-current replacement or administrative closure dates is unresolved; do not guess midnight, silently allow a duplicate, or prohibit all same-day operations. Residual cleanup after a period's calendar end still requires a valid declared end date; no new containment exception or post-calendar-end date/key mapping is invented here. These technical mappings require review before affected implementation. Exact temporal column types beyond declared `DATE` values remain implementation details.

## Architecture Decisions

### Decision: Use a modular Laravel monolith with a dedicated academic boundary

**Choice**: Implement the future backend as one Laravel deployment with an `Academic` domain/application boundary and local MySQL persistence.

**Alternatives considered**: Separate academic microservices; controllers directly operating on Eloquent models.

**Rationale**: A single locally deployed process avoids external network dependencies and distributed consistency while still allowing domain rules to be isolated from HTTP and ORM concerns. Direct controller persistence would scatter interval, history, and authorization invariants.

### Decision: Keep authoritative academic relationships normalized

**Choice**: Store periods, catalog records, enrollments, and assignments as stable normalized rows connected by foreign keys; derive current views with queries.

**Alternatives considered**: Mutable student/teacher scope columns; duplicated academic snapshots as the primary truth.

**Rationale**: Normalized stable identities support concurrent teachers, transfer history, deterministic routing, and constraint enforcement without replacing historical rows.

### Decision: Combine current state with an append-only lifecycle ledger

**Choice**: Keep current state on aggregate rows and append every transition or relevant catalog correction to `academic_lifecycle_events` in the same transaction.

**Alternatives considered**: Current state only; full event sourcing.

**Rationale**: Current rows make authorization queries direct, while the ledger preserves non-destructive history and audit context. Full event sourcing adds projection and recovery complexity not justified by the approved scope.

### Decision: Separate declared dates from half-open operational intervals

**Choice**: Retain school-local declared dates and store half-open server-ordered operational intervals separately.

**Alternatives considered**: Date-only half-open intervals; wall-clock timestamps as the sole ordering key.

**Rationale**: Date-only intervals lose same-day history; timestamps can collide or regress. A transaction ordinal records actual effective order without introducing functional scheduling. Immediate-only transfer is approved; planned boundaries and non-current replacement/administrative closure date-to-key mappings remain technical dependencies, not permission for scheduled or corrective transfer.

### Decision: Enforce invariants in both MySQL and locked application transactions

**Choice**: Use foreign keys, unique/generated indexes, and row checks for database-expressible rules; use Laravel transaction services with `SELECT ... FOR UPDATE` for cross-row intervals and state transitions.

**Alternatives considered**: Application validation only; triggers for every rule.

**Rationale**: Database constraints protect against accidental bypass, while explicit application transactions can produce domain errors and testable behavior for rules MySQL cannot express declaratively. Trigger-heavy logic would hide domain behavior outside the Laravel application boundary.

### Decision: Serialize foundation writes through one stable guard and ordered rows

**Choice**: Acquire the singleton academic write guard first for every competing foundation mutation and minimum submission acceptance, then acquire all required rows in the canonical order below. Re-read and validate before writing.

**Alternatives considered**: Parent-only locks plus additional uniqueness-range guards; optimistic checks without locks; a separate advisory-lock service.

**Rationale**: One pre-existing local row is the minimal common serialization point for empty-set creation, active-period selection, catalog uniqueness, interval changes, and acceptance races. It avoids row-discovery and lock-upgrade hazards at the cost of coarse write throughput. Distinct teachers remain permitted to share scope even though transactions serialize. Measure this cost before considering finer-grained guards; no external lock service is needed.

### Decision: Centralize authorization in policies backed by query services

**Choice**: Laravel middleware authenticates locally; policies authorize operation categories by calling read-only academic-scope query services. Application command handlers repeat critical aggregate checks before mutation.

**Alternatives considered**: Client-side role checks; role-only middleware; authorization embedded only in controllers.

**Rationale**: Policies provide consistent resource-level decisions, while command-level checks prevent alternate entry points from bypassing invariants. Roles alone cannot enforce enrollment and assignment ownership.

### Decision: Expose vice-principal supporting data as purpose-bound projections

**Choice**: Provide read-only support queries reachable only from teaching-assignment management use cases. Each query requires a target assignment or proposed assignment scope and returns the minimum period, catalog, and enrollment-scope projection needed for that operation.

**Alternatives considered**: General vice-principal access to period/catalog/enrollment endpoints; copying support data into assignments.

**Rationale**: Purpose-bound projections implement the approved “necessary for assignment duties” boundary without granting general management or unrelated browsing permissions. Copying would create a second source of truth.

### Decision: Persist the original route and acceptance context on submissions

**Choice**: A submission reference stores its activity, original teaching assignment, student, and accepted-under enrollment. The server copies the assignment ID from the activity inside the acceptance transaction and never accepts a recipient field.

**Alternatives considered**: Resolve the assignment only by joining the current activity; route to the current teacher; accept a client recipient and validate it.

**Rationale**: Explicit immutable references make historical route preservation directly enforceable and allow the original authorization context to survive later enrollment or assignment changes. Recipient input has no valid purpose and increases tampering risk.

### Decision: Keep all essential authority on the school server

**Choice**: Authentication data required for these roles, academic data, policies, transactions, and route resolution run against the local Laravel/MySQL deployment; frontend assets are served locally.

**Alternatives considered**: Cloud-only identity or authorization; externally hosted catalog or routing; mandatory remote queue.

**Rationale**: Essential student behavior must remain available without external Internet. Local server, network, and power remain explicit dependencies.

### Decision: Use restrictive historical foreign keys and no destructive cascades

**Choice**: Historical relationships use restrictive foreign keys; application services reject identity/scope mutation and referenced-history deletion.

**Alternatives considered**: Cascading deletes; soft-deleted mutable mappings; snapshot text without foreign keys.

**Rationale**: Restrictive references preserve original meaning and expose invalid deletion attempts immediately. Soft deletion alone does not prevent relationship rewriting, and text snapshots cannot enforce identity.

## Authorization Model

Every protected request starts with the authenticated local user. Client identifiers locate a candidate resource only; they do not establish permission. The server reloads the resource, period, enrollment or assignment, and relevant lifecycle state before deciding.

Resolve the current period only when the operation requires it; otherwise validate the requested resource's authoritative period, not a substituted current period. Closed-parent checks apply to each new-work category, not to all operations indiscriminately. Planning in a planned period remains permitted; the planned assignment itself is not operational and the planned period is not current. The non-closed-parent predicates in this design and the specs state the approved closed-parent restriction, not a sufficient authorization test for a planned parent. Planned-parent activation/new-work eligibility remains an unresolved product decision: do not infer either an active-parent-only rule or automatic planned-parent permission, and do not change existing valid-operation flows to choose one. Under the minimum routing contract, assignment closure alone does not deny original-route acceptance for an otherwise eligible existing activity with a non-closed parent and currently compatible active enrollment; full activity availability is deferred.

| Role | Approved command boundary | Approved read boundary | Explicit denial in this change |
|---|---|---|---|
| Director/Administrator | Manage foundation records subject to constraints; valid explicit residual enrollment/assignment closure under a closed period | Current and historical foundation relationships, including closed-period history | No new academic work under a closed parent; cannot bypass dates, intervals, uniqueness, or routing. Global catalog and other periods remain unaffected. |
| Vice Principal | Create, activate, close, and replace assignments subject to constraints; valid residual assignment closure under a closed period | Assignment history and purpose-bound supporting period/catalog/enrollment-scope projections, including retained closed-period scope | No enrollment cleanup or other period/catalog/enrollment management; no new assignment/activation/replacement under a closed parent; no deferred processing permission. |
| Teacher | Minimum contract permits activity creation only through the teacher's active assignment in a non-closed period | Own assignments and own retained activity/submission history, including after period closure | Other teachers' assignments; new activity through a closed assignment or closed parent. |
| Student | Minimum contract permits new submission only through the active enrollment matching the activity scope in a non-closed period | Activities in permitted active scope; own enrollment history; own submissions and original accepted context after transfer/closure | Client-selected scope/recipient, historical enrollment for new work, closed-parent normal/late acceptance, other students' records. |

### Vice-principal supporting-read representation

> **Technical representation of approved behavior, not a new product permission:** supporting reads are not general catalog or enrollment access.

The application interface is purpose-bound rather than a public repository:

```php
interface TeachingAssignmentSupportQuery
{
    public function forAssignmentOperation(
        AuthenticatedActor $actor,
        AssignmentScope $targetScope
    ): AssignmentSupportView;
}
```

`AssignmentSupportView` contains only the identified period, eligible active catalog references for new assignment operations, grade/section structure, and enrollment-scope facts necessary for the exact target assignment and duty. Historical review and valid residual assignment cleanup may resolve retained references, including inactive catalog references and closed-period scope, only when necessary for that exact target and duty; this does not make inactive references eligible for new assignment operations. Supporting reads must not require an active parent or hide the historical references needed for those duties. The policy requires the vice-principal role and a concrete assignment-management use case. Unscoped listing, general catalog or enrollment browsing, unrelated student history, enrollment cleanup, and writes are not exposed through this interface.

### Student historical-submission envelope

> **Technical representation of approved behavior, not a new product permission:** this envelope defines the minimum context returned only after the server verifies that the authenticated student owns the submission.

```ts
type OwnHistoricalSubmissionView = {
  submission: {
    id: string;
    acceptedAt: string;
  };
  activityReference: {
    id: string;
  };
  acceptedUnderEnrollment: {
    id: string;
    academicPeriod: Reference;
    grade: Reference;
    section: Reference;
    effectiveFrom: string;
    effectiveUntil: string | null;
    operationalStartKey: string;
    operationalEndKeyExclusive: string | null;
  };
  originalTeachingAssignment: {
    id: string;
    academicPeriod: Reference;
    teacher: Reference;
    instructionalEntry: Reference & { kind: "subject" | "area" };
    grade: Reference;
    section: Reference;
    effectiveFrom: string;
    effectiveUntil: string | null;
    operationalStartKey: string;
    operationalEndKeyExclusive: string | null;
  };
};

type Reference = { id: string; displayName: string };
```

The envelope excludes other students, recipient selection, activity availability, submission content/version state, grading, processing, and material data. Display names are resolved through retained identities; this contract does not claim they are immutable historical label snapshots.

## Application Interfaces and Contracts

Planned application ports keep HTTP and Eloquent details outside domain operations:

```php
interface AcademicPeriodCommands
{
    public function create(CreateAcademicPeriod $command): AcademicPeriodId;
    public function activate(ActivateAcademicPeriod $command): void;
    public function close(CloseAcademicPeriod $command): void;
}

interface EnrollmentCommands
{
    public function enroll(EnrollStudent $command): EnrollmentId;
    public function transfer(TransferStudent $command): EnrollmentId;
    public function close(CloseEnrollment $command): void;
}

interface TeachingAssignmentCommands
{
    public function create(CreateTeachingAssignment $command): TeachingAssignmentId;
    public function activate(ActivateTeachingAssignment $command): void;
    public function replace(ReplaceTeacher $command): TeachingAssignmentId;
    public function close(CloseTeachingAssignment $command): void;
}

interface AcademicAuthorization
{
    public function decide(AuthenticatedActor $actor, AcademicOperation $operation): Decision;
}

interface SubmissionRouteContract
{
    public function accept(
        AuthenticatedStudent $student,
        ActivityId $activity,
        SubmissionPayload $deferredPayload
    ): SubmissionReferenceId;
}
```

The submission request contract has no period, grade, section, teacher, recipient, or alternate assignment field. If compatibility input later includes any such field, conflicting values are rejected and never used as authority.

`TransferStudent` accepts an immediate scope change only, not scheduling, retroactive, or correction input. Successful confirmation returns the successor identity after commit; failure leaves prior state, ordinal, and events unchanged. Enrollment input requires period ID and ordinary declared-date validation without enrollment containment; assignment input retains containment. Handlers must distinguish closed-parent new-work denial from valid role-authorized residual closure and historical reads. Name adapters preserve visible labels separately from equality keys. None of these planned ports implements a schema or grants apply approval.

Domain failures map to stable application error categories such as `unauthenticated`, `forbidden`, `invalid_transition`, `invalid_interval`, `inactive_catalog_reference`, `scope_mismatch`, `overlapping_enrollment`, `overlapping_assignment`, and `historical_reference_conflict`. HTTP status and final UI copy are deferred to implementation design conventions.

## Data Flow

### Protected academic request

```text
React client
    │ authenticated request + target resource ID
    ▼
Laravel authentication middleware (local)
    ▼
Policy / AcademicAuthorization
    │ reload role + period + enrollment/assignment from MySQL
    ├── deny: no authoritative relationship
    ▼
Application command/query
    │ revalidate aggregate invariants for writes
    ▼
MySQL transaction or read projection
```

### Student transfer transaction

```text
Director/Admin → TransferStudent handler → begin transaction
                                      → guard, discover set, canonical exclusive locks
                                      → reread authority, parent state, current enrollment, history
                                      → reject closed parent or scheduled/retroactive/corrective transfer
                                      → reject same grade/section destination
                                      → validate dates, destination and interval conflicts
                                      → allocate effective operation key K
                                      → end prior [start_key, K) as transferred
                                      → insert new active [K, infinity), declared date D
                                      → append both lifecycle events
                                      → commit → confirm immediate transfer and return new enrollment ID
```

If any validation or insert fails, the transaction rolls back both the prior transition and successor creation, including ordinal and events. A concurrent transfer, period closure, or acceptance waits on the same guard and canonical locks and then re-evaluates parent and child state. From successful execution at `K`, the prior enrollment is historical and never authorizes new work; destination scope is visible only after successful server confirmation. Compare relevant formerly active history as intervals, not just rows currently labeled active. Preserve same-day ordinal history; scheduling, retroactive transfer, and transfer corrections are rejected, not unresolved product options.

### Teacher replacement transaction

```text
Director/Admin or Vice Principal → ReplaceTeacher handler → begin transaction
                                                       → guard, discover complete row set
                                                       → canonical locks, both teachers before assignments
                                                       → reread authority, parent state, scope, dates and conflicts
                                                       → reject closed parent before any prior closure/successor
                                                       → allocate key K, close prior at K
                                                       → insert distinct successor at K, declared date D
                                                          with replaces_assignment_id
                                                       → append lifecycle events
                                                       → commit
```

The successor is the active assignment effective at `K`; existing activities and submissions remain linked to the prior assignment, including when both records start/end on the same declared date. If successor creation fails, closure rolls back. Check the successor teacher's same-period/instructional-entry/grade/section intervals against all relevant planned, active, and historical assignments; exclude neither planned nor closed rows solely by state. Validate the proposed prior end at `K` and successor start at `K` as a non-overlapping pair before persisting either change. Distinct teachers sharing scope remain valid; replacement never migrates original work.

This flow describes supported immediate replacement, not scheduled replacement or an invented non-current date mapping. Under a closed parent, valid standalone residual assignment closure by director/administrator or vice principal remains possible, but replacement must not use that cleanup permission to create a successor.

### Submission authorization and route derivation

```text
Student → activity ID → begin transaction, exclusive write guard
                      → discover complete set, canonical locks, reread activity/route
                      → resolve authenticated student's active enrollment on server
                      → revalidate role, non-closed parent and effective enrollment scope under locks
                      → compare period + grade + section at acceptance key K
                      → derive assignment ID from activity (never request input)
                      → insert submission reference with enrollment + route + key K
                      → commit
```

Assignment closure or replacement does not redirect the route and, by itself, does not deny minimum acceptance for an existing activity with a non-closed parent and currently compatible active enrollment. A closed parent denies all new acceptance, including late submissions, even if a child remains active. Complete activity availability, content, and deadline lifecycle remain deferred. Existing accepted references and role-authorized historical reads retain original owners, route, and enrollment context after period closure.

Acceptance and transfer use the same exclusive guard and student parent lock. If acceptance linearizes first, its enrollment and original route are retained after transfer; if transfer linearizes first, acceptance resolves the successor enrollment and denies an old-scope mismatch. It never trusts a client enrollment, teacher, assignment, or recipient, or a pre-transaction policy result. Retry recomputes the entire decision; historical reads do not grant new-operation authority.

## Domain Integrity and Concurrency

| Invariant | Primary enforcement | Concurrency protection |
|---|---|---|
| At most one active period | Generated unique active guard plus transition service | Singleton write guard, ordered period locks, and unique-index failure handling, including creation/activation with no active row. |
| Closed period admits no new academic work; valid residual cleanup remains permitted | Operation-category check against the resource's parent, not child state or current-period substitution | Guard and ordered period locks; revalidate parent state before creation, activation, successors, activity creation, or normal/late acceptance. Cleanup requires valid dates and role; no cascade. |
| Approved name equality and visible-label separation | Server comparison key: outer trim, case-insensitive, NFC, accent-distinguishing; all-state period versus active scoped catalog indexes | Guard and ordered catalog/period locks for create, rename, and reactivation; selected MySQL behavior must reproduce the policy. |
| Valid grade-section pair | Composite relationship constraint plus service validation | Foreign-key enforcement. |
| Active-only catalog references for new enrollment/assignment | Command validation in the same transaction | Guard plus ordered catalog locks before insert; catalog lifecycle writes use the same protocol. |
| One active enrollment interval per student/period at any moment | Overlap query over relevant occupied intervals and lifecycle checks | Guard plus student parent-row lock serializes enrollment writes and acceptance. |
| No overlapping duplicate assignment for same teacher and scope | Interval query including relevant planned/active/history rows, with ambiguous date mappings deferred | Guard plus ordered teacher parent locks. Distinct teachers may hold concurrent domain assignments; transactions serialize. |
| Transfer is immediate, additive and non-overlapping | Single transfer handler; reject unchanged scope and scheduled/retroactive/corrective input | One transaction, guard, period and student locks; successful confirmation only; rejection leaves no mutations. |
| Replacement is additive and non-overlapping | Single replacement handler and successor reference | Guard, complete ordered teacher set, then ordered assignment locks in one transaction. |
| Activity keeps original assignment | Immutable application contract and restrictive FK | Update path absent; attempted mutation rejected. |
| Submission route equals activity assignment | Acceptance handler copies route after rereading activity | Guard and student lock shared with transfers; one transaction; route never comes from client. |
| Historical relationships survive lifecycle changes | Restrictive FKs and immutable scope | No cascade delete or bulk reassignment operation. |

Every foundation create, activate, close, catalog rename/reactivation/deactivation, enrollment/transfer, assignment/replacement, and minimum activity creation/submission acceptance follows this single lock protocol:

1. Begin a transaction and take the singleton `academic_write_guard` with `FOR UPDATE` before authoritative row discovery. Every competing write must participate; no shared-to-exclusive upgrade is allowed.
2. Discover the complete row set under that guard: target and related periods, catalog references, authenticated actor and subject people (both old/new teachers for replacement), affected aggregates, and relevant conflict history. A client ID or preliminary read is only a locator.
3. Acquire exclusive row locks by fixed table rank, then ascending primary key within each table: `academic_periods`, `instructional_entries`, `grades`, `sections`, retained local identities, student identities, teacher identities, `student_enrollments`, `teaching_assignments`, `activity_references`, `submission_references`. If identities share a table, lock their union once at that table's rank; do not lock the actor early and later discover a lower-ID person. Identity table mappings must be pinned before schema implementation. Inserts of new targets need no nonexistent-row lock: the guard protects discovery and unique constraints provide the final check.
4. Re-read all authoritative relationships and conflicting intervals with current locking reads, not an earlier snapshot. Validate role, lifecycle, requested parent-period state for the operation category, active catalog references where required, dates, period identity, history, and route using the prospective next operation key `K` held stable by the guard; allocation occurs only after validation. Deny closed-parent new work even with active children, but allow valid role-authorized residual cleanup and do not reject planned-parent planning automatically. If the required row set or a relationship differs from discovery, roll back and rediscover in a fresh transaction; never append an out-of-order lock or upgrade a read lock. The guard prevents such changes by participating writers; restrictive FKs and immutable scope provide additional protection. Any future competing identity writer must follow this protocol when it can change academic authority or retained identities.
5. Allocate operation key `K`, mutate controlled fields/insert successors or references, append ledger evidence, and commit. No external effects occur inside this retryable unit. New ledger rows are inserted last and are not discovered mutable aggregates.

Safe bounded retries restart the whole transaction after deadlock, discovery change, or serialization failure and re-run authorization and conflict checks; domain conflicts are returned, not blindly retried. Retry only after confirmed rollback, never automatically replay an uncertain commit or duplicate a submission. Request idempotency/ambiguous-commit recovery is an implementation dependency before automatic request replay. Integrity violations become domain failures. This coarse protocol is intentionally one common local serialization mechanism, not competing period/person versus assignment-first schemes.

## Local Operation

- Laravel, MySQL, authentication data needed for scoped users, built frontend assets, and all academic reference data reside on the school server.
- No protected read, authorization decision, transfer, replacement, or submission-route decision calls an external identity provider, queue, catalog, or routing service.
- External Internet loss is an operational test condition. Loss of local server, LAN/WLAN, or power is not claimed to be supported.
- Synchronization agents, remote replicas, and conflict policies are absent from this design. They require a separate approved design before any integration.
- Web-server-specific configuration is not designed; the application boundary must remain deployable behind the later approved IIS or Apache choice.

## Planned Future File and Module Layout

These paths are proposals for later implementation after architecture and prototype approval. None is claimed to exist now, and this design phase creates none of them.

| Planned path | Action in a future implementation | Responsibility |
|---|---|---|
| `backend/app/Domain/Academic/` | Create | Entities, value objects, interval logic, and lifecycle rules without HTTP/ORM dependencies. |
| `backend/app/Application/Academic/Commands/` | Create | Transactional period, enrollment, assignment, transfer, and replacement handlers. |
| `backend/app/Application/Academic/Queries/` | Create | Role-bounded current/history projections and vice-principal support query. |
| `backend/app/Application/Academic/Authorization/` | Create | Operation model and server-derived decisions. |
| `backend/app/Infrastructure/Persistence/Academic/` | Create | Eloquent repositories and MySQL locking/overlap queries. |
| `backend/app/Http/Controllers/Academic/` | Create | Thin request adapters. |
| `backend/app/Policies/Academic/` | Create | Laravel policy adapters over academic authorization. |
| `backend/database/migrations/` | Create during Laravel scaffolding | Academic tables, keys, generated columns, checks, and indexes. |
| `backend/tests/Unit/Academic/` | Create | Pure lifecycle, interval, and authorization decision tests. |
| `backend/tests/Feature/Academic/` | Create | Database, transaction, HTTP, policy, and local-dependency tests. |
| `frontend/src/features/academic-foundation/` | Create after prototype approval | Typed API adapters and role-appropriate screens; no authorization authority. |
| `frontend/src/features/academic-foundation/contracts.ts` | Create | TypeScript response/request shapes generated from or checked against backend contracts. |

The repository-level `database/` directory remains untouched by this phase. Whether deployment SQL assets later belong there or only in Laravel migrations should follow the approved scaffolding convention.

## Testing Strategy

No executable test command or framework is currently present. The following is the required future strategy, not a claim that tests can run now.

| Layer | What to test | Planned approach |
|---|---|---|
| Unit | State machines, declared dates versus half-open operational keys, same-day transfer chains/replacement, period boundary validation, overlap/history checks, authorization decisions, route derivation | PHPUnit tests against domain and application services with deterministic school date/time and operation ordering. |
| Integration | Foreign keys, generated unique guards, active-name uniqueness, grade-section integrity, append-only events, lock behavior, rollback, concurrent transfer/replacement, route equality | Laravel database tests against the approved MySQL version, including two-connection race tests rather than SQLite substitutions. |
| Feature/API | Authentication, policy boundaries for all four roles, tampered identifiers, vice-principal projection scoping, student historical envelope, no recipient input | Laravel feature tests through HTTP adapters and real policies. |
| Contract | PHP response DTOs and TypeScript contract compatibility | Schema-backed contract tests once API tooling is selected. |
| E2E | Director period/enrollment flow, vice-principal assignment flow, transfer boundary, teacher replacement history, student scoped access and own prior submission | Browser tests over a local deployment after the prototype and UI are approved. |
| Load/concurrency | Roster reads, assignment reads, simultaneous submission acceptance, conflicting transfers/replacements | Local-network load tests with seeded school-scale data and explicit database-lock/error thresholds. |
| Resilience | Essential authorization and routing with external Internet blocked | Run the deployed local stack with outbound Internet denied while LAN/WLAN remains available. |

Key negative cases include skipped/reversed lifecycle transitions, duplicate active period, inactive catalog references, mismatched grade-section pairs, overlapping same-student enrollments, overlapping same-teacher/scope assignments, another teacher's assignment, another student's submission, historical scope used for new work, conflicting client scope, selected recipient, deletion of referenced history, and transaction failure between prior closure and successor insert.

Approved alignment cases must accompany the affected behavior: immediate successful transfer, rejected scheduled/retroactive/corrective transfers without mutations, same-day ordered history, enrollment required period without containment-only rejection, open end without automatic expiry, unchanged assignment containment, and planned assignment creation in a planned parent without authority. Exercise every closed-parent new-work category, including planned creation and normal/late acceptance, with active children; prove valid director/administrator enrollment cleanup and director/administrator or vice-principal assignment cleanup, vice-principal enrollment denial, role-bounded history, and unaffected catalog/other periods. Prove minimum original-route acceptance after assignment closure alone with a non-closed parent and matching active enrollment. Name tests cover case/outer trim/NFC equivalence, accent distinction, subject/area separation, grade and parent-scoped section duplicates, active rename/reactivation collision, all-state period versus active-only catalog uniqueness, and visible-label/key separation. Real MySQL tests must reproduce product equality and period closure/write races; no deferred activity/deadline lifecycle is added.

## Threat Matrix

The skill's process-integration matrix is not applicable. “Submission routing” in this change is an in-database domain relationship, not URL routing, shell execution, subprocess integration, executable-file classification, Git automation, or PR automation.

| Boundary | Applicability | Reason |
|---|---|---|
| Documentation-like paths | N/A | No executable-file classification or execution boundary is introduced. |
| Git repository selection | N/A | No Git command or repository-selection behavior is introduced. |
| Commit state | N/A | No commit/index automation is introduced. |
| Push state | N/A | No push or ref-resolution behavior is introduced. |
| PR commands | N/A | No PR command composition or automation is introduced. |

No threat-matrix RED tests are required for N/A rows. Academic authorization and tampering threats are covered by the unit, integration, and feature tests above.

## Migration and Rollout

This is a greenfield, pre-implementation repository, so there is no existing application data migration in this phase.

1. Approve this design and a role-appropriate prototype before scaffolding.
2. Confirm the supported PHP, Laravel, and MySQL versions during implementation planning; generated columns and enforced checks must be validated against that exact MySQL version.
3. Create schema in dependency order: retained identity dependencies and singleton write guard, catalog/period records, enrollments/assignments, typed lifecycle-event FKs, then minimum activity/submission references when their capability implementation begins. Final identity tables, remaining temporal mappings, and technical name-key/MySQL reproduction require resolution before affected migrations. Approved immediate transfer, no enrollment containment, closed-parent operation rules, and name equality are not outstanding product choices; document consistency and human gate evidence still require review.
4. Apply migrations to an empty non-production database and verify constraints with integration tests.
5. Seed only institution-approved catalog and identity references through auditable commands; do not import or imply SIAGIE replacement.
6. Exercise role, concurrency, historical-read, and external-Internet-loss scenarios on the school-network staging environment.
7. Roll out in reviewable slices under the 400-line review policy. Rollback must prefer disabling/reverting application behavior or forward-fixing schema; it must never delete retained academic history or rewrite original routes.

No local/remote synchronization migration, deployment web-server selection, or production data import is included.

## Consistency Traceability

| Approved specification | Design evidence |
|---|---|
| `academic-periods` | Stable period rows, approved all-state name equality, explicit lifecycle/current resolution, no closed-parent new work, valid residual cleanup without cascade, retained references. |
| `academic-catalog` | Typed entries, stable visible labels separate from equality keys, accent-distinguishing NFC/case/outer-trim policy, active scoped create/rename/reactivation uniqueness, retained inactive history. |
| `student-enrollment` | Required period without enrollment containment, open end without automatic expiry, half-open same-day history, immediate successful non-destructive transfer only, closed-parent denial and valid administrator cleanup, own-history projection. |
| `teaching-assignments` | Unchanged contained dates, planned-parent planning without authority, distinct-teacher concurrency, overlap prevention, closed-parent creation/activation/replacement denial, valid residual closure, original ownership. |
| `academic-authorization` | Local server-derived requested-resource context, operation matrix distinguishing new work/cleanup/history, purpose-bound vice-principal reads and assignment-only cleanup, bounded ownership/history. |
| `activity-submission-routing` | Immutable activity assignment, non-closed parent and active-enrollment match, closed-assignment-only minimum acceptance, no recipient field, original route/accepted context retention, local locked transaction. |

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| MySQL version does not enforce a planned check/generated-index behavior as expected | Pin and test the exact MySQL version before migration approval; retain application checks but do not rely on them alone. |
| Concurrent interval writes bypass a read-then-write check | Lock stable parent rows and run two-connection integration tests. |
| Generic endpoints accidentally broaden vice-principal access | Expose purpose-bound projection ports and policy tests; do not reuse unrestricted administration repositories as HTTP resources. |
| Historical envelope leaks another student or deferred data | Ownership policy precedes projection; DTO allowlist contains only the fields defined here. |
| Display-name corrections are mistaken for historical label snapshots | Preserve identity and audit the correction; explicitly avoid claiming immutable label-at-time semantics. |
| Later activity work conflates assignment closure with period closure | Preserve minimum original-route acceptance after assignment closure alone with non-closed parent/matching active enrollment; closed-parent normal/late acceptance is denied. Full availability needs separate design. |
| Active child or blanket parent check bypasses the approved operation matrix | Revalidate requested parent under canonical locks; test each new-work denial, valid role/date-bounded cleanup, planned-parent planning, and role-bounded historical reads. |
| Default database collation or normalization changes product equality or visible labels | Validate technical keys against case/outer trim/NFC/accent fixtures on real MySQL; keep labels separate and uniqueness scopes unchanged. |
| Future synchronization assumptions leak into local authority | Keep synchronization absent and require a separate ownership/conflict/audit/recovery design before integration. |

## Open Technical Questions

The six capability requirements already have user approval; record that existing approval and review these scoped approved amendments rather than require blanket requirements reapproval. Approved decisions 1.2–1.5 settle immediate-only successful student transfer, mandatory enrollment period without additional containment, closed-parent new-work denial with valid residual cleanup/history, and case-insensitive outer-trim NFC name equality with accents distinguished. They do not imply full technical architecture, prototype, or implementation approval.

One product eligibility question remains outside the approved closed-parent policy: whether assignment activation or new academic work is permitted under a planned parent. Planned assignment creation is approved, a planned assignment itself is not operational, and only an active period is current; none of these facts resolves requested-period eligibility. Non-closed is not sufficient planned-parent authorization, and no active-parent-only rule is introduced. Resolve that question before implementing affected planned-parent cases without changing existing valid-operation flows here.

Remaining technical questions are:

- Mapping ambiguous same-day planned assignment boundaries and permitted non-current teacher replacement/administrative closure dates to operational order, including residual cleanup after the period's calendar end while retaining valid contained assignment dates. No mapping or new scheduled replacement permission is invented. Student scheduled, retroactive, and corrective transfers are rejected, not pending choices.
- Exact outer-whitespace trimming and case-comparison mechanics, comparison-key encoding, and MySQL version/index/collation reproduction of approved equality; precise whitespace-display policy remains to be defined before changing visible whitespace. Accents remain distinguished, and period all-state versus catalog active-only scopes remain unchanged.

Exact framework/database versions, school time-zone configuration, retained identity table mappings and FK/permission implementation, retry/idempotency mechanics, authentication mechanism, API schema tooling, institutional seed/import format, and IIS-versus-Apache deployment remain technical implementation dependencies. They must preserve local authority and historical retention and must not be inferred from empty implementation directories. References to existing requirements approval and review of scoped amendments, missing full technical architecture approval, a role-appropriate accepted prototype, and separate execution authorization remain necessary. This bounded alignment changes planned documents only, not executable schema or code, and completes no implementation task.

## Scoped enrollment eligibility and closure decision (2026-10-07)

Explicit human approval is recorded in docs/u5-human-decisions.md. New active enrollment creation, including transfer successors, requires an active requested parent period. This enrollment-specific predicate does not prohibit approved planned teaching-assignment creation and does not settle assignment activation or other new-work eligibility in planned parents. Earlier planned-parent unresolved statements remain applicable to those other operations, not this resolved enrollment category.

Administrative enrollment closure accepts a permitted valid past/current school-local end date, end >= declared start and end <= server today in America/Lima. Preserve that DATE and end operational authority at the actual successful transaction key K. Future closure dates are rejected; no midnight conversion, backdating, scheduling or accepted-context rewrite is allowed. This resolves the earlier administrative date/key question only for enrollment closure, including valid residual closure under a closed parent; assignment closure/replacement mappings remain unresolved. No enrollment-period date containment is added.

## Approved U7 assignment eligibility and temporal model (2026-10-07)

The human approved all three proposals in docs/u7-pending-decisions.md; authority is recorded in docs/u7-human-decisions.md. Assignment activation and new operative work (replacement successors, activities/submissions) require an active requested parent. Valid planned-assignment creation under planned/active parents, residual cleanup and history under closed parents remain permitted. Earlier unresolved planned-parent eligibility statements are superseded for these categories; a different current period never substitutes for a requested parent.

Planned rows retain null operational keys and inclusive declared reservations, open planning horizon = period end + one day. Planning against active rows uses their declared planning ranges; disjoint plans grant no authority. Activation revalidates actual occupied intervals at prospective K and other planned reservations; open active intervals block duplicates even when declared ranges were disjoint. Closed history with end <= K precedes future manual activation; do not blanket-deny actual same-day non-overlap. Manual activation uses actual successful K, preserves declared dates and requires America/Lima today >= declared start; no guessed future key, midnight/backdating, auto-activation or ledger replay.

Assignment administrative closure by Director/Administrator or Vice Principal preserves a valid contained past/current end date and ends operational authority at actual successful K, including residual closure after parent/calendar end. End >= declared start, end <= school-local today and unchanged period containment remain mandatory; future closure dates are rejected. No enrollment permission is granted to Vice Principal. Earlier assignment closure/planned shared-day mapping questions are resolved by this model; non-current teacher replacement mapping remains pending for U8.

## Approved U8 immediate replacement mapping (2026-10-07)

Human-delegated selection is recorded in docs/u8-human-decisions.md. Option 1 uses server America/Lima today only, contained in the active parent and not before prior start. Reject different supplied dates and calendar-past/future execution without mutations. Prior end and successor start preserve that DATE; actual operational boundaries share successful K. Distinct teacher/identity and unique immutable replaces_assignment_id retain prior scope/history. Revalidate all destination planned/active/history conflicts and active catalog under canonical period/catalog/union-of-teachers/union-of-assignment-history locks. No schedule, backdating, midnight keys, rerouting or cascade. This resolves earlier U8 non-current mapping questions for the supported immediate-only scope; administrative cleanup retains its separately approved past/current rule. Gates and persisted reference/UI/network evidence are not closed by this choice.

## Approved U9 isolated HTTP contract (2026-10-07)

Human contextual approval is recorded in docs/u9-http-human-approval.md. Map absent/invalid session to 401, forbidden operation to 403, absent/out-of-scope reads to 404, invalid inputs/dates to 422, lifecycle/overlap/integrity conflicts to 409 and uncertain transaction outcomes to 503 with correlation and no automatic replay. Keep authentication origin/CSRF/throttle semantics and sanitize responses. Test thin local SessionGuard/request/response policy/controller adapters against actual isolated MySQL commands. No full Laravel kernel/listener/LAN acceptance or new reference persistence is claimed; writer checks remain authoritative under locks and reference defaults fail closed.
