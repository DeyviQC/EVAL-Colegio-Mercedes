# Teaching Assignments Specification

## Purpose

Define stable teaching assignments, permitted concurrency, lifecycle transitions, replacement, and historical ownership in EVAL.

## Requirements

### Requirement: Stable assignment identity and scope

EVAL MUST give each teaching assignment a stable identity and MUST bind it to exactly one academic period, one teacher, one subject or area, one grade, and one section. The section MUST belong to the selected grade. Assignment creation, including planned creation, MUST be rejected in a closed period. A planned assignment in a planned period MUST be supported with valid dates and no conflicting interval. A planned assignment MUST NOT itself authorize operational work; a planned period MUST NOT be treated as the current period. Assignment activation and new operative work (replacement successors, activities and submissions) MUST require an active requested parent period. This operation-specific eligibility MUST NOT prohibit valid planned-assignment creation under planned or active parents, valid residual cleanup or role-bounded history under closed parents.

#### Scenario: Create a teaching assignment

- GIVEN the non-closed period, teacher, subject or area, grade, and section are valid
- WHEN an authorized director/administrator or vice principal creates the assignment
- THEN EVAL MUST create a stable assignment identity bound to exactly that scope

#### Scenario: Plan an assignment in a planned period

- GIVEN a planned period and valid assignment scope, contained dates, and no interval conflict
- WHEN an authorized director/administrator or vice principal creates a planned assignment
- THEN EVAL MUST permit planning without requiring an active parent
- AND the planned assignment MUST NOT itself authorize operational work
- AND the planned period MUST NOT become current through that planning operation

#### Scenario: Reject planned assignment creation in a closed period

- GIVEN an academic period is closed
- WHEN a planned teaching assignment is proposed for that period
- THEN EVAL MUST reject creation without mutations

#### Scenario: Reject incomplete assignment scope

- GIVEN a teaching-assignment request omits any required scope component
- WHEN EVAL validates the request
- THEN EVAL MUST reject the request

#### Scenario: Reject a mismatched section

- GIVEN the selected section does not belong to the selected grade
- WHEN assignment creation is requested
- THEN EVAL MUST reject the request

### Requirement: Assignment lifecycle and effective interval

Each teaching assignment MUST have an effective start date and MUST be in exactly one of `planned`, `active`, or `closed` states. Activation MUST establish an active effective interval, closure MUST record an end date not earlier than the start date, and a closed assignment MUST NOT become active again. Activation MUST require an active parent and server school-local today in America/Lima on or after the declared start. It MUST be explicit/manual at actual successful K, preserving declared dates without midnight conversion, backdating, guessed future ordinals or automatic activation. Residual active assignments under a closed period MAY be explicitly closed by director/administrator or vice principal with valid dates, including unchanged period containment; closure MUST NOT happen automatically.

#### Scenario: Activate a planned assignment

- GIVEN a planned assignment has a valid scope and start date within its active academic period, and server school-local today is on or after that declared start
- WHEN an authorized director/administrator or vice principal activates it
- THEN EVAL MUST mark it active for its effective interval

#### Scenario: Reject activation under a closed period

- GIVEN a planned assignment belongs to a closed period
- WHEN activation is requested
- THEN EVAL MUST reject the operation without changing its state or history

#### Scenario: Close an assignment

- GIVEN a teaching assignment is active, including a residual active assignment under a closed period
- WHEN an authorized director/administrator or vice principal closes it with a valid end date
- THEN EVAL MUST mark it closed
- AND EVAL MUST retain its scope and effective interval

#### Scenario: Reject dates outside the academic period

- GIVEN an assignment's proposed start or end date falls outside its academic period
- WHEN EVAL validates the assignment
- THEN EVAL MUST reject the request

### Requirement: Permitted assignment concurrency

A teacher MAY hold multiple concurrent teaching assignments. Multiple teachers MAY concurrently serve the same academic period, subject or area, grade, and section through distinct teaching assignments.

#### Scenario: Assign one teacher to multiple scopes

- GIVEN a teacher has an active teaching assignment
- WHEN another valid assignment for that teacher and a different academic scope in an active period is activated
- THEN EVAL MUST permit both assignments to remain active

#### Scenario: Assign multiple teachers to the same scope

- GIVEN one teacher has an active assignment for an academic scope
- WHEN a distinct assignment for another teacher and the same scope in an active period is activated
- THEN EVAL MUST permit both assignments
- AND EVAL MUST preserve each assignment's independent identity

### Requirement: Duplicate assignment prevention

EVAL MUST reject teaching assignments for the same teacher, period, subject or area, grade, and section when their effective intervals overlap. EVAL MUST NOT use this rule to prohibit distinct teachers from concurrently serving the same scope.

#### Scenario: Reject an overlapping duplicate

- GIVEN a teacher has an assignment for a specific academic scope and effective interval
- WHEN another assignment for the same teacher and scope would overlap that interval
- THEN EVAL MUST reject the duplicate assignment

#### Scenario: Permit a later non-overlapping assignment

- GIVEN a teacher's prior assignment for an academic scope is closed and its period is non-closed
- WHEN a new assignment for the same teacher and scope starts after the prior assignment ends
- THEN EVAL MUST permit the new assignment with a new identity

### Requirement: Non-destructive teacher replacement

Replacing a teacher MUST close the prior teaching assignment and create a new teaching assignment with a new identity. EVAL MUST NOT overwrite the teacher on, delete, or reuse the identity of the prior assignment. Replacement successor creation MUST require an active parent and MUST be rejected under a planned or closed period, without closing the prior assignment as part of the rejected replacement. This does not add scheduled replacement or resolve non-current date mapping.

#### Scenario: Replace an assigned teacher

- GIVEN a teacher has an active assignment in an active period
- WHEN an authorized director/administrator or vice principal records a replacement effective on a valid date
- THEN EVAL MUST close the prior assignment
- AND EVAL MUST create a new assignment for the replacement teacher
- AND the prior and replacement effective intervals MUST NOT overlap as a replacement pair

#### Scenario: Reject replacement under a closed period

- GIVEN a residual active assignment belongs to a closed period
- WHEN teacher replacement is requested
- THEN EVAL MUST reject successor creation and leave the prior assignment and history unchanged

#### Scenario: Preserve the prior assignment

- GIVEN a teacher has been replaced
- WHEN an authorized actor reviews assignment history
- THEN EVAL MUST retain the prior teacher, scope, identity, and effective interval
- AND EVAL MUST identify the replacement as a separate assignment

### Requirement: Assignment-bound historical ownership

Activities and submissions associated with a teaching assignment MUST retain that original assignment identity after the assignment is closed or its teacher is replaced. EVAL MUST NOT migrate historical ownership to a replacement assignment.

#### Scenario: Preserve activity ownership after replacement

- GIVEN an activity belongs to a teaching assignment
- WHEN that assignment is closed and a replacement assignment is created
- THEN EVAL MUST keep the activity bound to the original assignment

#### Scenario: Preserve submission ownership after replacement

- GIVEN a submission is linked through an activity to an original teaching assignment
- WHEN the original teacher is replaced
- THEN EVAL MUST retain the submission's route to the original assignment
- AND EVAL MUST NOT reassign it to the replacement assignment

### Requirement: Closed-assignment behavior

EVAL MUST prevent creation of new activities for a closed teaching assignment or for an active assignment whose parent period is closed. Closing an assignment MUST NOT by itself cancel, reassign, or delete activities and submissions already bound to that assignment. Under the minimum routing contract, assignment closure alone MUST NOT prevent acceptance for an existing activity when the parent period is active and the student has a currently compatible active enrollment; the route MUST remain the original assignment. Complete activity availability remains deferred. Period closure, unlike assignment closure alone, MUST deny every new submission including late submissions.

#### Scenario: Reject new activity through an active child of a closed period

- GIVEN a teacher owns an active assignment in a closed period
- WHEN creation of a new activity is requested
- THEN EVAL MUST reject creation despite the active assignment state

#### Scenario: Reject a new activity for a closed assignment

- GIVEN a teaching assignment is closed
- WHEN creation of a new activity for that assignment is requested
- THEN EVAL MUST reject the request

#### Scenario: Retain existing work after closure

- GIVEN activities or submissions belong to a teaching assignment
- WHEN the assignment is closed
- THEN EVAL MUST retain those activities and submissions under the original assignment

### Requirement: Local assignment authority

EVAL MUST resolve assignment scope, lifecycle, and historical ownership on the school-hosted server without external Internet while the local server, LAN/WLAN, and power are available.

#### Scenario: Resolve assignment ownership without external Internet

- GIVEN the local server, LAN/WLAN, and power are available
- AND external Internet is unavailable
- WHEN EVAL authorizes an assignment-bound operation
- THEN EVAL MUST resolve the teaching assignment and its lifecycle locally

### Requirement: Approved planning and operational conflict representation

For the same teacher, period, instructional entry, grade and section, EVAL MUST compare planned reservations using inclusive declared dates. The exclusive planning end MUST be the supplied end plus one day, or period end plus one day for an open end. Overlapping planned reservations MUST be rejected; disjoint reservations MUST be allowed. Distinct teachers MUST retain independent concurrency.

Planning against an active row MUST use its declared planning range. A disjoint future plan MUST NOT grant current authority or guarantee successful activation. Activation MUST revalidate actual half-open occupied operational intervals at prospective K, including relevant retained history. An open active interval MUST block duplicate activation even if declared planning ranges were disjoint. Planned reservations remain part of conflict validation and MUST NOT be excluded merely because they are not active.

Retained operational history ending on/before prospective K MUST be treated as preceding a new manual activation. EVAL MUST permit a later same-day activation when actual key intervals do not overlap, without rejecting solely because declared dates share the day. It MUST NOT derive guessed future keys from dates, backdate authority, replay the ledger as current state or impose a blanket ban on same-day operations.

### Requirement: Approved administrative assignment closure mapping

Director/Administrator or Vice Principal MAY explicitly close a residual active assignment with a valid past/current declared end: end MUST NOT precede declared start, MUST remain within the period's unchanged inclusive boundaries, and MUST NOT exceed server school-local today in America/Lima. A future declared closure date MUST be rejected without mutations. Preserve that DATE and end operational authority at actual successful K, including execution after period calendar end or parent closure. EVAL MUST NOT backdate K, schedule closure, delete references or rewrite accepted context. This grants Vice Principal no enrollment-management permission and does not resolve non-current teacher replacement mapping.