# Academic Authorization Specification

## Purpose

Define server-derived authorization for EVAL's academic roles, current scope, and retained historical relationships.

## Requirements

### Requirement: Server-derived authorization context

For every protected academic operation, EVAL MUST derive authorization on the school-hosted server from the authenticated user, role, relevant academic period, and authoritative enrollment or teaching-assignment relationships. EVAL MUST NOT treat freely supplied client scope as authorization evidence. EVAL MUST distinguish current-period resolution from requested-resource period validation: a closed parent MUST deny new academic work despite active children, while a planned parent MUST NOT be rejected merely because it is not current for an otherwise permitted planning operation. Valid residual administrative closure and role-bounded historical reads MUST remain separate from new academic work.

#### Scenario: Authorize from authoritative relationships

- GIVEN an authenticated user requests a protected academic operation
- WHEN EVAL evaluates the request
- THEN EVAL MUST resolve the user's role and required academic relationships from authoritative local records
- AND EVAL MUST authorize the operation only when all required relationships permit it
- AND EVAL MUST validate the requested resource's parent state rather than substituting the current period or trusting an active child

#### Scenario: Reject a tampered scope parameter

- GIVEN an authenticated user supplies a period, grade, section, subject or area, assignment, or recipient identifier outside the user's authoritative scope
- WHEN EVAL evaluates the protected operation
- THEN EVAL MUST deny the operation
- AND EVAL MUST NOT broaden access based on the supplied identifier

#### Scenario: Deny an unauthenticated operation

- GIVEN no authenticated user is associated with a protected academic request
- WHEN EVAL evaluates the request
- THEN EVAL MUST deny the operation

### Requirement: Director and administrator authority

An authenticated director/administrator MUST be permitted to manage academic periods, catalog entries, student enrollments, and teaching assignments, subject to each capability's lifecycle and validation rules. This role MUST be permitted to review current and historical records in those capabilities and explicitly close residual active enrollments and assignments under a closed period with valid dates. This cleanup is not new academic work and MUST NOT bypass date validation or rewrite history. Global catalog operations and other periods MUST remain unaffected by closure of one period.

#### Scenario: Manage an academic foundation record

- GIVEN an authenticated director/administrator submits a valid academic-foundation operation
- WHEN EVAL evaluates authorization
- THEN EVAL MUST permit the operation
- AND EVAL MUST still enforce the target capability's constraints
- AND permitted residual administrative closure MUST NOT be denied as new academic work

#### Scenario: Administratively close residual active records

- GIVEN a closed period has residual active enrollments or assignments
- WHEN an authenticated director/administrator explicitly closes a residual record with valid dates
- THEN EVAL MUST permit closure and preserve its identity, scope, and retained history
- AND EVAL MUST NOT create a successor or rewrite accepted submission context

#### Scenario: Review historical academic records

- GIVEN an authenticated director/administrator requests retained period, enrollment, assignment, activity, or submission relationships
- WHEN EVAL evaluates authorization
- THEN EVAL MUST permit the historical review

### Requirement: Vice-principal assignment authority

An authenticated vice principal MUST be permitted to create, activate, close, replace, and review teaching assignments subject to their lifecycle, date, and parent-period constraints. This includes explicit closure of residual active assignments under a closed period with valid dates, but not creation, activation, or replacement successors there. EVAL MUST permit the vice principal to read only the academic-period, catalog, and enrollment information necessary for those assignment duties. A vice principal MUST NOT manage academic periods, catalog entries, or student enrollments unless another explicitly authorized role grants that authority. Within `academic-foundation`, the vice principal MUST NOT receive permission to manage or process educational materials, observations, activities, or submissions.

#### Scenario: Manage a teaching assignment

- GIVEN an authenticated vice principal submits a teaching-assignment lifecycle operation valid under the requested period's state and assignment date rules
- WHEN EVAL evaluates authorization
- THEN EVAL MUST permit the operation
- AND valid explicit closure of a residual active assignment under a closed period MUST remain permitted without granting successor creation or enrollment management

#### Scenario: Limit vice-principal residual cleanup to assignments

- GIVEN a closed period has residual active assignments and enrollments
- WHEN a vice principal with no additional authorized role requests valid explicit residual assignment closure
- THEN EVAL MUST permit assignment closure subject to valid contained dates
- AND EVAL MUST deny residual enrollment closure by that role

#### Scenario: Deny enrollment management

- GIVEN an authenticated vice principal has no additional authorized role
- WHEN the vice principal requests creation, transfer, or closure of a student enrollment
- THEN EVAL MUST deny the operation

#### Scenario: View supporting academic scope

- GIVEN an authenticated vice principal is performing academic assignment duties
- WHEN the vice principal requests the relevant period, catalog, or enrollment-scope information
- THEN EVAL MUST permit read access needed for those duties

#### Scenario: Deny unrelated foundation information

- GIVEN an authenticated vice principal requests period, catalog, or enrollment information that is not necessary for teaching-assignment duties
- WHEN EVAL evaluates authorization
- THEN EVAL MUST deny the request

#### Scenario: Deny deferred capability operations

- GIVEN an authenticated vice principal has no additional authorized role
- WHEN the vice principal requests management or processing of a material, observation, activity, or submission
- THEN EVAL MUST deny the operation within academic-foundation

### Requirement: Teacher assignment boundary

An authenticated teacher MUST be permitted to act only through teaching assignments belonging to that teacher. The teacher MUST be permitted to create activities only for the teacher's active assignments in active periods and MAY review retained historical activities and submissions belonging to the teacher's assignments, including after period closure. Active assignment state MUST NOT grant new-work authority under a closed parent.

#### Scenario: Act within an active assignment

- GIVEN an authenticated teacher owns an active teaching assignment in a active period
- WHEN the teacher requests an allowed assignment-bound operation
- THEN EVAL MUST permit the operation within that assignment's scope

#### Scenario: Deny another teacher's assignment

- GIVEN a teaching assignment belongs to a different teacher
- WHEN the authenticated teacher requests an operation through that assignment
- THEN EVAL MUST deny the operation

#### Scenario: Review own closed assignment

- GIVEN an authenticated teacher owns a closed teaching assignment with historical activities or submissions
- WHEN the teacher requests read access to that history
- THEN EVAL MUST permit read access
- AND EVAL MUST NOT permit creation of new activities for the closed assignment

### Requirement: Student enrollment boundary

An authenticated student MUST access new activities and create new submissions only through the student's active enrollment for the relevant active period. A historical enrollment or active enrollment under a closed period MUST NOT authorize either operation. EVAL MUST permit the student to read the student's own enrollment history and retained submissions with original context after transfer or period closure, but the student MUST NOT access another student's records or unrelated academic scope.

#### Scenario: Access an activity within active enrollment scope

- GIVEN an authenticated student has an active enrollment matching an activity's period, grade, and section in an active relevant period
- WHEN the student requests the activity
- THEN EVAL MUST permit access within the student's active enrollment scope

#### Scenario: Deny activity outside enrollment scope

- GIVEN an activity belongs to a grade or section outside the authenticated student's active enrollment
- WHEN the student requests the activity
- THEN EVAL MUST deny access

#### Scenario: Deny another student's submission

- GIVEN a submission belongs to another student
- WHEN the authenticated student requests that submission
- THEN EVAL MUST deny access

#### Scenario: Deny a new submission through historical enrollment

- GIVEN an authenticated student's prior enrollment is historical after a transfer
- WHEN the student attempts to create a submission using that historical scope
- THEN EVAL MUST deny the operation

#### Scenario: Read the student's own pre-transfer submission

- GIVEN an authenticated student made a submission before a transfer
- WHEN the student requests read access to that submission after the transfer
- THEN EVAL MUST permit read access with its original academic context

### Requirement: Historical authorization boundaries

A historical academic relationship MAY grant role-appropriate historical read access when applicable, but MUST NEVER by itself authorize creation of new academic work. EVAL MUST limit historical read access to director/administrator across academic history, vice principal across assignment history and necessary supporting projections, teachers for their own assignments, and students for their own enrollments and submissions. Valid explicit residual administrative closure is permitted cleanup, not new work. A closed assignment's original route MAY support minimum acceptance for an existing activity only with an active parent and a currently compatible active enrollment; the historical assignment alone is not submission authority.

#### Scenario: Prevent historical scope from granting current authority

- GIVEN a user had academic scope through a now-closed enrollment or teaching assignment
- WHEN the user requests creation of new academic work based only on that historical relationship, without the required current authority
- THEN EVAL MUST deny the operation

#### Scenario: Deny every new-work category under a closed parent

- GIVEN the requested resource's academic period is closed, regardless of any active child state
- WHEN new enrollment, transfer successor, assignment creation including planned creation, assignment activation, replacement successor, activity creation, or normal or late submission acceptance is requested
- THEN EVAL MUST deny the operation without academic mutations
- AND EVAL MUST NOT deny valid role-authorized residual closure or global catalog and other-period operations merely because this period is closed

#### Scenario: Read closed-period history within role boundaries

- GIVEN an academic period is closed
- WHEN an authenticated user requests retained history
- THEN EVAL MUST permit director/administrator academic-history reads, vice-principal assignment history and necessary supporting reads, teacher own-assignment history, and student own enrollment and submission history only within those boundaries
- AND EVAL MUST retain original identities, owners, routes, and accepted-under enrollment context without granting new-work authority

#### Scenario: Permit role-appropriate historical reading

- GIVEN an authenticated user requests historical information within the user's retained role and relationship boundary
- WHEN EVAL evaluates authorization
- THEN EVAL MUST permit read access

#### Scenario: Deny unrelated historical reading

- GIVEN an authenticated teacher or student requests historical information with no authoritative relationship to that user
- WHEN EVAL evaluates authorization
- THEN EVAL MUST deny access

### Requirement: Local authorization availability

EVAL MUST complete essential student authorization, academic visibility, activity access, and submission authorization on the school-hosted server without external Internet while the local server, LAN/WLAN, and power are available. EVAL MUST NOT require an external identity, authorization, catalog, or routing service for those decisions.

#### Scenario: Authorize locally without external Internet

- GIVEN the local server, LAN/WLAN, and power are available
- AND external Internet is unavailable
- WHEN an authenticated student performs an essential academic operation
- THEN EVAL MUST evaluate and complete the authorization decision using local authoritative data
