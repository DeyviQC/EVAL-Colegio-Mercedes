# Teaching Assignments Specification

## Purpose

Define stable teaching assignments, permitted concurrency, lifecycle transitions, replacement, and historical ownership in EVAL.

## Requirements

### Requirement: Stable assignment identity and scope

EVAL MUST give each teaching assignment a stable identity and MUST bind it to exactly one academic period, one teacher, one subject or area, one grade, and one section. The section MUST belong to the selected grade.

#### Scenario: Create a teaching assignment

- GIVEN the period, teacher, subject or area, grade, and section are valid
- WHEN an authorized director/administrator or vice principal creates the assignment
- THEN EVAL MUST create a stable assignment identity bound to exactly that scope

#### Scenario: Reject incomplete assignment scope

- GIVEN a teaching-assignment request omits any required scope component
- WHEN EVAL validates the request
- THEN EVAL MUST reject the request

#### Scenario: Reject a mismatched section

- GIVEN the selected section does not belong to the selected grade
- WHEN assignment creation is requested
- THEN EVAL MUST reject the request

### Requirement: Assignment lifecycle and effective interval

Each teaching assignment MUST have an effective start date and MUST be in exactly one of `planned`, `active`, or `closed` states. Activation MUST establish an active effective interval, closure MUST record an end date not earlier than the start date, and a closed assignment MUST NOT become active again.

#### Scenario: Activate a planned assignment

- GIVEN a planned assignment has a valid scope and start date within its academic period
- WHEN an authorized director/administrator or vice principal activates it
- THEN EVAL MUST mark it active for its effective interval

#### Scenario: Close an assignment

- GIVEN a teaching assignment is active
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
- WHEN another valid assignment for that teacher and a different academic scope is activated
- THEN EVAL MUST permit both assignments to remain active

#### Scenario: Assign multiple teachers to the same scope

- GIVEN one teacher has an active assignment for an academic scope
- WHEN a distinct assignment for another teacher and the same scope is activated
- THEN EVAL MUST permit both assignments
- AND EVAL MUST preserve each assignment's independent identity

### Requirement: Duplicate assignment prevention

EVAL MUST reject teaching assignments for the same teacher, period, subject or area, grade, and section when their effective intervals overlap. EVAL MUST NOT use this rule to prohibit distinct teachers from concurrently serving the same scope.

#### Scenario: Reject an overlapping duplicate

- GIVEN a teacher has an assignment for a specific academic scope and effective interval
- WHEN another assignment for the same teacher and scope would overlap that interval
- THEN EVAL MUST reject the duplicate assignment

#### Scenario: Permit a later non-overlapping assignment

- GIVEN a teacher's prior assignment for an academic scope is closed
- WHEN a new assignment for the same teacher and scope starts after the prior assignment ends
- THEN EVAL MUST permit the new assignment with a new identity

### Requirement: Non-destructive teacher replacement

Replacing a teacher MUST close the prior teaching assignment and create a new teaching assignment with a new identity. EVAL MUST NOT overwrite the teacher on, delete, or reuse the identity of the prior assignment.

#### Scenario: Replace an assigned teacher

- GIVEN a teacher has an active assignment
- WHEN an authorized director/administrator or vice principal records a replacement effective on a valid date
- THEN EVAL MUST close the prior assignment
- AND EVAL MUST create a new assignment for the replacement teacher
- AND the prior and replacement effective intervals MUST NOT overlap as a replacement pair

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

EVAL MUST prevent creation of new activities for a closed teaching assignment. Closing an assignment MUST NOT by itself cancel, reassign, or delete activities and submissions already bound to that assignment.

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
