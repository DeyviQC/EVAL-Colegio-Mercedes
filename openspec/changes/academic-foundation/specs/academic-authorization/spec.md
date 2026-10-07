# Academic Authorization Specification

## Purpose

Define server-derived authorization for EVAL's academic roles, current scope, and retained historical relationships.

## Requirements

### Requirement: Server-derived authorization context

For every protected academic operation, EVAL MUST derive authorization on the school-hosted server from the authenticated user, role, relevant academic period, and authoritative enrollment or teaching-assignment relationships. EVAL MUST NOT treat freely supplied client scope as authorization evidence.

#### Scenario: Authorize from authoritative relationships

- GIVEN an authenticated user requests a protected academic operation
- WHEN EVAL evaluates the request
- THEN EVAL MUST resolve the user's role and required academic relationships from authoritative local records
- AND EVAL MUST authorize the operation only when all required relationships permit it

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

An authenticated director/administrator MUST be permitted to manage academic periods, catalog entries, student enrollments, and teaching assignments, subject to each capability's lifecycle and validation rules. This role MUST be permitted to review current and historical records in those capabilities.

#### Scenario: Manage an academic foundation record

- GIVEN an authenticated director/administrator submits a valid academic-foundation operation
- WHEN EVAL evaluates authorization
- THEN EVAL MUST permit the operation
- AND EVAL MUST still enforce the target capability's constraints

#### Scenario: Review historical academic records

- GIVEN an authenticated director/administrator requests retained period, enrollment, assignment, activity, or submission relationships
- WHEN EVAL evaluates authorization
- THEN EVAL MUST permit the historical review

### Requirement: Vice-principal assignment authority

An authenticated vice principal MUST be permitted to create, activate, close, replace, and review teaching assignments. EVAL MUST permit the vice principal to read only the academic-period, catalog, and enrollment information necessary for those assignment duties. A vice principal MUST NOT manage academic periods, catalog entries, or student enrollments unless another explicitly authorized role grants that authority. Within `academic-foundation`, the vice principal MUST NOT receive permission to manage or process educational materials, observations, activities, or submissions.

#### Scenario: Manage a teaching assignment

- GIVEN an authenticated vice principal submits a valid teaching-assignment lifecycle operation
- WHEN EVAL evaluates authorization
- THEN EVAL MUST permit the operation

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

An authenticated teacher MUST be permitted to act only through teaching assignments belonging to that teacher. The teacher MUST be permitted to create activities only for the teacher's active assignments and MAY review historical activities and submissions belonging to the teacher's closed assignments.

#### Scenario: Act within an active assignment

- GIVEN an authenticated teacher owns an active teaching assignment
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

An authenticated student MUST access new activities and create new submissions only through the student's active enrollment for the relevant period. A historical enrollment MUST NOT authorize either operation. EVAL MUST permit the student to read the student's own enrollment history and submissions made before a transfer, but the student MUST NOT access another student's records or unrelated academic scope.

#### Scenario: Access an activity within active enrollment scope

- GIVEN an authenticated student has an active enrollment matching an activity's grade and section in the relevant period
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

A historical academic relationship MAY grant role-appropriate historical read access when applicable, but MUST NEVER authorize creation of a new academic operation. EVAL MUST limit historical read access to director/administrator across academic history, vice principal across assignment history, teachers for their own assignments, and students for their own enrollments and submissions.

#### Scenario: Prevent historical scope from granting current authority

- GIVEN a user had academic scope through a now-closed enrollment or teaching assignment
- WHEN the user requests creation of a new academic operation based only on that historical relationship
- THEN EVAL MUST deny the operation

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
