# Activity and Submission Routing Specification

## Purpose

Define the minimal contracts that bind activities to teaching assignments and route student submissions deterministically in EVAL.

## Requirements

### Requirement: Activity ownership by one teaching assignment

Every activity MUST belong to exactly one teaching assignment at creation. EVAL MUST derive the activity's academic period, subject or area, grade, and section from that assignment and MUST NOT accept conflicting client-supplied academic scope.

#### Scenario: Create an assignment-bound activity

- GIVEN an authenticated teacher owns an active teaching assignment
- WHEN the teacher creates a valid activity through that assignment
- THEN EVAL MUST bind the activity to exactly that teaching assignment
- AND EVAL MUST derive the activity's academic scope from the assignment

#### Scenario: Reject an activity without an assignment

- GIVEN an activity request does not identify an authorized active teaching assignment
- WHEN EVAL validates the request
- THEN EVAL MUST reject the request

#### Scenario: Reject conflicting activity scope

- GIVEN an activity request supplies grade, section, subject or area, or period values that conflict with the selected teaching assignment
- WHEN EVAL validates the request
- THEN EVAL MUST reject the conflicting scope

### Requirement: Assignment identity remains original

An activity's teaching-assignment identity MUST remain unchanged after creation. Closing or replacing the teaching assignment MUST NOT rebind the activity to another assignment or teacher.

#### Scenario: Retain ownership after teacher replacement

- GIVEN an activity belongs to a teaching assignment
- WHEN that assignment is closed and a replacement assignment is created
- THEN EVAL MUST keep the activity bound to the original assignment
- AND EVAL MUST NOT route ownership to the replacement teacher's assignment

#### Scenario: Reject activity reassignment

- GIVEN an existing activity belongs to a teaching assignment
- WHEN a request attempts to change its assignment identity
- THEN EVAL MUST reject the request

### Requirement: Active-enrollment submission scope

EVAL MUST permit a student to create a submission only when the student's active enrollment at the time of submission matches the academic period, grade, and section of the activity's teaching assignment. A historical enrollment MUST NOT authorize a new submission, and EVAL MUST NOT use client-supplied academic scope to establish submission authority.

#### Scenario: Submit from the active enrollment scope

- GIVEN an authenticated student's active enrollment matches the academic period, grade, and section of the activity's teaching assignment
- WHEN the student submits valid work to the activity
- THEN EVAL MUST accept the submission for automatic routing

#### Scenario: Reject an out-of-scope submission

- GIVEN the student's active enrollment does not match the academic period, grade, or section of the activity's teaching assignment
- WHEN the student attempts to submit work
- THEN EVAL MUST reject the submission

#### Scenario: Reject a submission through a historical enrollment

- GIVEN a student's prior enrollment became historical after a transfer
- WHEN the student attempts a new submission using the prior enrollment's academic scope
- THEN EVAL MUST reject the submission
- AND EVAL MUST evaluate the new operation only from the student's active enrollment

### Requirement: Automatic submission routing

Every accepted submission MUST link to exactly one activity and MUST derive its destination teaching assignment from that activity. EVAL MUST NOT allow a student or client to select or override a recipient teacher or teaching assignment.

#### Scenario: Route a submission automatically

- GIVEN a student whose active enrollment matches an activity's academic scope submits valid work
- WHEN EVAL accepts the submission
- THEN EVAL MUST link it to that activity
- AND EVAL MUST route it to the activity's original teaching assignment

#### Scenario: Ignore or reject a selected recipient

- GIVEN a submission request includes a recipient teacher or alternate teaching assignment
- WHEN EVAL validates the request
- THEN EVAL MUST NOT use the supplied recipient for routing
- AND EVAL MUST reject the request when the supplied recipient conflicts with the activity's original assignment

#### Scenario: Preserve route after replacement

- GIVEN an activity's original teaching assignment has been closed or replaced
- WHEN EVAL accepts a submission for that activity from a student whose active enrollment matches its academic scope
- THEN EVAL MUST route the submission to the original teaching assignment
- AND EVAL MUST NOT route it to the replacement assignment

### Requirement: Stable submission relationships

After a submission is accepted, EVAL MUST preserve its student, activity, and original teaching-assignment relationships. Lifecycle changes to periods, enrollments, assignments, or catalog entries MUST NOT rewrite those relationships.

#### Scenario: Retain submission history

- GIVEN a submission has been accepted
- WHEN its period closes, its student's enrollment changes, or its assignment closes
- THEN EVAL MUST retain the submission's original student, activity, and assignment relationships

#### Scenario: Reject historical route mutation

- GIVEN an accepted submission is linked to an activity and teaching assignment
- WHEN a request attempts to replace its routed assignment
- THEN EVAL MUST reject the request

### Requirement: Local routing availability

EVAL MUST determine active-enrollment submission authority and routing on the school-hosted server without external Internet while the local server, LAN/WLAN, and power are available. EVAL MUST NOT depend on an external queue, catalog, authorization, or routing service for this behavior.

#### Scenario: Submit and route without external Internet

- GIVEN the local server, LAN/WLAN, and power are available
- AND external Internet is unavailable
- AND the student's active enrollment matches the activity's academic scope
- WHEN the student submits valid work
- THEN EVAL MUST validate the active enrollment and route the submission locally to the activity's original teaching assignment
