# Design: Academic Foundation

## Technical Approach

EVAL will implement the academic foundation as a local, server-authoritative Laravel module backed by MySQL. Stable records for periods, catalog entries, enrollments, and teaching assignments carry current lifecycle state, while append-only lifecycle events and immutable foreign-key relationships preserve history. React clients may submit resource identifiers as request targets, but Laravel policies and application services will reconstruct the authorization context from the authenticated user and authoritative local records.

The model uses explicit effective intervals for enrollment and teaching-assignment scope. Transfers and teacher replacements are atomic, additive operations: the prior record is ended and a new record is inserted. The minimum activity/submission boundary stores the original teaching-assignment route and the enrollment under which a submission was accepted; it intentionally does not design the deferred activity or submission lifecycle.

This document describes planned implementation paths only. The repository currently has placeholder `backend/`, `frontend/`, and `database/` directories, with no Laravel or React scaffold, dependencies, schema, CI, or executable test tooling.

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

The tables, generated indexes, normalization, temporal encoding, guard, and ledger are implementation choices, not additional product requirements. The specs define uniqueness scopes but do not define whether case, whitespace, accents, or canonically equivalent Unicode spellings count as the same name. Do not silently choose trim, case folding, accent removal, or Unicode normalization. Until an equality rule is approved, only identical stored Unicode scalar sequences can safely be identified as definite duplicates; broader equivalence is an unresolved product decision. Final `name_key` generation and index collation must implement that rule exactly on the server (including catalog reactivation and rename); React is not authoritative. A binary comparison can represent exact equality but is not approval of the final name-equality rule.

### State and history representation

Current state is stored on each aggregate for efficient authorization. A lifecycle transition updates only the controlled current-state fields and appends an `academic_lifecycle_events` row in the same transaction. Identity and scope foreign keys are not changed to simulate transfers, replacements, or reclassification.

This is not event sourcing: current rows remain authoritative. The event ledger supplies transition history, actor, declared date, operational order, and correlation evidence. Referential integrity uses `RESTRICT`/`NO ACTION`, never cascading deletes, across retained academic history. Catalog display-name corrections retain stable IDs; the corresponding event captures the prior and new display value.

Use typed target foreign keys for periods, instructional entries, grades, sections, enrollments, and assignments, not an unenforced polymorphic type/ID pair. A row-local check permits exactly one target and verifies its discriminator. These restrictive FKs protect targets referenced only by the ledger as well as targets referenced by academic records. `actor_id` references a retained local identity: account deactivation or credential removal does not remove or re-identify the audit identity. Identity storage and its retention-compatible FK are future schema dependencies, not an existing user table or a new account-management capability.

The runtime database role may `SELECT` and `INSERT` ledger rows, but not `UPDATE` or `DELETE`; transitions and event inserts commit or roll back together. Migration/maintenance privileges must be separate and must not be used by runtime commands to rewrite evidence. Constraints, permission tests, and transactional handlers together enforce integrity; an application convention alone is insufficient. Actor and target identity dependencies precede ledger migration.

## Effective-Date and Interval Strategy

> **Technical representation of approved behavior, not a new product permission:** the following choices make the approved lifecycle, transfer, and replacement rules unambiguous in storage and comparisons.

- Preserve required declared effective start/end dates as school-local `DATE` values; an end date must not precede its start date and may equal it under both lifecycle specs. The configured school time zone determines the declared date of an immediate server-effective operation. A date is not enough to order enrollment, acceptance, transfer, and replacement occurring on that same day; `[D, D)` would incorrectly erase a real interval.
- Separately store server transaction-effective boundary keys: a monotonically increasing operation ordinal allocated under the write guard, with a UTC microsecond timestamp as evidence. Ordinals, not wall-clock precision, determine order. They are technical linearization keys for successful operations, not client scheduling fields. Rollback rolls back the ordinal allocation and all associated writes.
- Operational intervals are half-open `[start_key, end_key)`, with inclusive start and exclusive end. Null enrollment end means no recorded end, treated as infinity for comparisons. Activation establishes an assignment's operational start; planned rows have declared dates but no current operational authority. Closure/transfer/replacement records a boundary key as well as its required declared end date.
- At transfer or replacement key `K`, end the prior interval at `K` and start the successor at `K` in one transaction. Creation followed by two same-day transfers has keys `K1 < K2 < K3`, producing nonempty `[K1,K2)` and `[K2,K3)` histories even when every declared date is `D`. No minimum one-day duration or next-day transition is imposed.
- For immediate operations whose effective moment is the accepted transaction, this represents the specs' effective-moment boundary without adding an intra-day scheduling feature. Dates alone do not specify how arbitrary supplied dates map to operational boundaries. Future or retroactive transfers, replacements, and historical corrections are not designed or granted here; defer their semantics and any API supporting them to explicit product decisions. Do not mark a future transfer effective now, keep a transferred enrollment authoritative until a future date, or rewrite earlier submission authority.
- `effectiveFrom` and `effectiveUntil` in historical views are declared calendar dates, not exclusive instants. Expose separate `operationalStartKey` and nullable `operationalEndKeyExclusive` when interval order is needed. `accepted_at` records evidence time; the submission also retains its acceptance operation key. No client-supplied clock establishes authority.
- Period `start_on` and `end_on` are inclusive declared calendar boundaries. Teaching-assignment validation requires each supplied start/end date to lie within those boundaries, including an end equal to the period end; it does not compare a null end as infinity and reject every open assignment. For declared-date conflict planning only, an open assignment's comparison horizon is bounded by its period's inclusive end (`end_on + 1 day` as an exclusive calendar boundary). This is not an automatic closure or authorization-expiry rule.
- The enrollment and period specs require one period identity, not enrollment-date containment within its period. Do not add that constraint. Whether enrollment dates must lie within period dates is a missing product rule. Neither record may span multiple period identities.
- Period dates do not change lifecycle state automatically. Period closure changes only the period and its ledger; it does not cascade closure to enrollments/assignments. A closed period is historical and not current, so it cannot supply current-period authority. The specs do not explicitly settle every operation on a still-active child of a closed period; do not invent a blanket closed-period denial (especially for acceptance against existing activities), permission, or cascade. Defer that matrix before implementing affected commands.

For comparable operational keys, intervals overlap exactly when `left.start < right.end` and `right.start < left.end`, with an absent end treated as infinity. Compare relevant historical occupied intervals as well as current rows; current state alone does not reconstruct past conflicts. Declared-date bounds also require validation. Planned assignment conflicts must be checked, not excluded because they are not active: disjoint declared date ranges are non-overlapping, but dates alone cannot settle every shared-day planned boundary against an operational key. Mapping such planned boundaries or user-selected non-current dates is unresolved; do not guess midnight, silently allow a duplicate, or prohibit all same-day operations. These ambiguous cases need approved semantics before implementation. Exact temporal column types beyond declared `DATE` values remain implementation details.

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

**Rationale**: Date-only intervals lose same-day history; timestamps can collide or regress. A transaction ordinal records actual effective order without introducing functional scheduling. Non-current-date mappings and ambiguous planned boundaries remain deferred product decisions.

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

| Role | Approved command boundary | Approved read boundary | Explicit denial in this change |
|---|---|---|---|
| Director/Administrator | Manage periods, catalog, enrollments, and assignments subject to domain constraints | Current and historical foundation relationships | Cannot bypass lifecycle, interval, uniqueness, or routing rules. |
| Vice Principal | Create, activate, close, and replace assignments | Assignment history and purpose-bound supporting period/catalog/enrollment-scope projections | No management of periods, catalog, or enrollments; no material, observation, activity, or submission processing permission. |
| Teacher | Minimum contract permits activity creation only through the teacher's active assignment | Own active assignment and own closed-assignment activity/submission history | Other teachers' assignments; new activity through a closed assignment. |
| Student | Minimum contract permits new submission only through the active enrollment matching the activity assignment scope | Activities in active scope; own enrollment history; own submissions and approved historical context | Client-selected scope/recipient, historical scope for new work, other students' records. |

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

`AssignmentSupportView` contains only the identified period, eligible active catalog references, grade/section structure, and enrollment-scope facts needed to validate or review the target assignment operation. The policy requires the vice-principal role and a concrete assignment-management use case. Unscoped listing, unrelated student history, and writes are not exposed through this interface.

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
                                      → reread authority, current enrollment, history
                                      → reject same grade/section destination
                                      → validate dates, destination and interval conflicts
                                      → allocate effective operation key K
                                      → end prior [start_key, K) as transferred
                                      → insert new active [K, infinity), declared date D
                                      → append both lifecycle events
                                      → commit → return new enrollment ID
```

If any validation or insert fails, the transaction rolls back both the prior transition and successor creation. A concurrent transfer or acceptance waits on the same guard and student lock and then re-evaluates current state. From `K`, the prior enrollment is historical and never authorizes new work. Compare relevant formerly active history as intervals, not just rows currently labeled active; this does not add scheduling or retroactive corrections.

### Teacher replacement transaction

```text
Director/Admin or Vice Principal → ReplaceTeacher handler → begin transaction
                                                       → guard, discover complete row set
                                                       → canonical locks, both teachers before assignments
                                                       → reread authority, state, scope, dates and conflicts
                                                       → allocate key K, close prior at K
                                                       → insert distinct successor at K, declared date D
                                                          with replaces_assignment_id
                                                       → append lifecycle events
                                                       → commit
```

The successor is the active assignment effective at `K`; existing activities and submissions remain linked to the prior assignment, including when both records start/end on the same declared date. If successor creation fails, closure rolls back. Check the successor teacher's same-period/instructional-entry/grade/section intervals against all relevant planned, active, and historical assignments; exclude neither planned nor closed rows solely by state. Validate the proposed prior end at `K` and successor start at `K` as a non-overlapping pair before persisting either change. Distinct teachers sharing scope remain valid; replacement never migrates original work.

### Submission authorization and route derivation

```text
Student → activity ID → begin transaction, exclusive write guard
                      → discover complete set, canonical locks, reread activity/route
                      → resolve authenticated student's active enrollment on server
                      → revalidate role, state and effective scope under locks
                      → compare period + grade + section at acceptance key K
                      → derive assignment ID from activity (never request input)
                      → insert submission reference with enrollment + route + key K
                      → commit
```

Assignment closure or replacement does not redirect the route and, by itself, does not invalidate an already-created activity. Complete activity availability remains deferred.

Acceptance and transfer use the same exclusive guard and student parent lock. If acceptance linearizes first, its enrollment and original route are retained after transfer; if transfer linearizes first, acceptance resolves the successor enrollment and denies an old-scope mismatch. It never trusts a client enrollment, teacher, assignment, or recipient, or a pre-transaction policy result. Retry recomputes the entire decision; historical reads do not grant new-operation authority.

## Domain Integrity and Concurrency

| Invariant | Primary enforcement | Concurrency protection |
|---|---|---|
| At most one active period | Generated unique active guard plus transition service | Singleton write guard, ordered period locks, and unique-index failure handling, including creation/activation with no active row. |
| Valid grade-section pair | Composite relationship constraint plus service validation | Foreign-key enforcement. |
| Active-only catalog references for new enrollment/assignment | Command validation in the same transaction | Guard plus ordered catalog locks before insert; catalog lifecycle writes use the same protocol. |
| One active enrollment interval per student/period at any moment | Overlap query over relevant occupied intervals and lifecycle checks | Guard plus student parent-row lock serializes enrollment writes and acceptance. |
| No overlapping duplicate assignment for same teacher and scope | Interval query including relevant planned/active/history rows, with ambiguous date mappings deferred | Guard plus ordered teacher parent locks. Distinct teachers may hold concurrent domain assignments; transactions serialize. |
| Transfer is additive and non-overlapping | Single transfer handler; reject unchanged grade/section | One transaction, guard, and student lock. |
| Replacement is additive and non-overlapping | Single replacement handler and successor reference | Guard, complete ordered teacher set, then ordered assignment locks in one transaction. |
| Activity keeps original assignment | Immutable application contract and restrictive FK | Update path absent; attempted mutation rejected. |
| Submission route equals activity assignment | Acceptance handler copies route after rereading activity | Guard and student lock shared with transfers; one transaction; route never comes from client. |
| Historical relationships survive lifecycle changes | Restrictive FKs and immutable scope | No cascade delete or bulk reassignment operation. |

Every foundation create, activate, close, catalog rename/reactivation/deactivation, enrollment/transfer, assignment/replacement, and minimum activity creation/submission acceptance follows this single lock protocol:

1. Begin a transaction and take the singleton `academic_write_guard` with `FOR UPDATE` before authoritative row discovery. Every competing write must participate; no shared-to-exclusive upgrade is allowed.
2. Discover the complete row set under that guard: target and related periods, catalog references, authenticated actor and subject people (both old/new teachers for replacement), affected aggregates, and relevant conflict history. A client ID or preliminary read is only a locator.
3. Acquire exclusive row locks by fixed table rank, then ascending primary key within each table: `academic_periods`, `instructional_entries`, `grades`, `sections`, retained local identities, student identities, teacher identities, `student_enrollments`, `teaching_assignments`, `activity_references`, `submission_references`. If identities share a table, lock their union once at that table's rank; do not lock the actor early and later discover a lower-ID person. Identity table mappings must be pinned before schema implementation. Inserts of new targets need no nonexistent-row lock: the guard protects discovery and unique constraints provide the final check.
4. Re-read all authoritative relationships and conflicting intervals with current locking reads, not an earlier snapshot. Validate role, lifecycle, active catalog references, dates, period identity, history, and route using the prospective next operation key `K` held stable by the guard; allocation occurs only after validation. If the required row set or a relationship differs from discovery, roll back and rediscover in a fresh transaction; never append an out-of-order lock or upgrade a read lock. The guard prevents such changes by participating writers; restrictive FKs and immutable scope provide additional protection. Any future competing identity writer must follow this protocol when it can change academic authority or retained identities.
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
3. Create schema in dependency order: retained identity dependencies and singleton write guard, catalog/period records, enrollments/assignments, typed lifecycle-event FKs, then minimum activity/submission references when their capability implementation begins. Final identity tables and temporal/equality rules require resolution before affected migrations.
4. Apply migrations to an empty non-production database and verify constraints with integration tests.
5. Seed only institution-approved catalog and identity references through auditable commands; do not import or imply SIAGIE replacement.
6. Exercise role, concurrency, historical-read, and external-Internet-loss scenarios on the school-network staging environment.
7. Roll out in reviewable slices under the 400-line review policy. Rollback must prefer disabling/reverting application behavior or forward-fixing schema; it must never delete retained academic history or rewrite original routes.

No local/remote synchronization migration, deployment web-server selection, or production data import is included.

## Consistency Traceability

| Approved specification | Design evidence |
|---|---|
| `academic-periods` | Stable period rows, explicit lifecycle, unique active guard, retained references, local current-period resolution. |
| `academic-catalog` | Typed instructional entries, stable IDs, grade-section integrity, active-only new references, scoped active-name uniqueness, retained inactive history. |
| `student-enrollment` | Stable period-bound rows, half-open intervals, one active interval, atomic non-destructive transfer, current-scope derivation, own-history projection. |
| `teaching-assignments` | Stable assignment scope, permitted distinct-teacher concurrency, same-teacher overlap prevention, atomic replacement lineage, retained original ownership. |
| `academic-authorization` | Local authentication, policy/query architecture, operation matrix, purpose-bound vice-principal reads, teacher/student ownership checks, historical-read-only boundaries. |
| `activity-submission-routing` | Immutable activity assignment, active-enrollment match, no recipient field, explicit original route, accepted-under enrollment, retained relationships, local transaction. |

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| MySQL version does not enforce a planned check/generated-index behavior as expected | Pin and test the exact MySQL version before migration approval; retain application checks but do not rely on them alone. |
| Concurrent interval writes bypass a read-then-write check | Lock stable parent rows and run two-connection integration tests. |
| Generic endpoints accidentally broaden vice-principal access | Expose purpose-bound projection ports and policy tests; do not reuse unrestricted administration repositories as HTTP resources. |
| Historical envelope leaks another student or deferred data | Ownership policy precedes projection; DTO allowlist contains only the fields defined here. |
| Display-name corrections are mistaken for historical label snapshots | Preserve identity and audit the correction; explicitly avoid claiming immutable label-at-time semantics. |
| Later activity work silently changes closed-assignment submission behavior | Carry the immutable original-assignment contract into the separate activity/submission specification and design. |
| Future synchronization assumptions leak into local authority | Keep synchronization absent and require a separate ownership/conflict/audit/recovery design before integration. |

## Open Technical Questions

Independent technical clarifications above are usable, but the design is not fully implementation-ready. The approved specs leave these product decisions unresolved; this document does not choose them:

- Mapping non-current supplied effective dates and ambiguous same-day planned assignment boundaries to operational order. Future/retroactive transfer, replacement, and correction workflows remain unsupported by this design, not newly prohibited by a product rule.
- Whether enrollment dates must be contained within their period dates; period identity binding alone does not impose this rule.
- The operation matrix for still-active enrollments/assignments under a closed period, including new work on existing activities. Closed periods are not current; closure does not cascade; original routes remain immutable.
- Name equality beyond definite exact duplicates: case, whitespace, Unicode equivalence, and accents, within the approved uniqueness scopes.

Exact framework/database versions, school time-zone configuration, retained identity table mappings and FK/permission implementation, retry/idempotency mechanics, authentication mechanism, API schema tooling, institutional seed/import format, and IIS-versus-Apache deployment remain technical implementation dependencies. They must preserve local authority and historical retention and must not be inferred from empty implementation directories. No tasks, schema, or code are introduced by this bounded document correction.
