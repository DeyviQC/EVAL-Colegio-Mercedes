# Student Enrollment Specification

## Purpose

Define period-bound student enrollment, enrollment-derived academic scope, transfers, and retained student history in EVAL.

## Requirements

### Requirement: Stable period-bound enrollment

EVAL MUST give each student enrollment a stable identity and MUST bind it to exactly one student, one academic period, one grade, and one section. The section MUST belong to the selected grade.

#### Scenario: Enroll a student for a period

- GIVEN the student, academic period, grade, and section are valid
- AND the section belongs to the grade
- WHEN an authorized director/administrator creates the enrollment
- THEN EVAL MUST create a stable enrollment bound to exactly those records

#### Scenario: Reject incomplete enrollment scope

- GIVEN an enrollment request omits the period, grade, or section
- WHEN EVAL validates the request
- THEN EVAL MUST reject the request

### Requirement: One active enrollment per student and period

A student MUST have no more than one active enrollment in an academic period at any moment. EVAL MUST reject overlapping active enrollment intervals for the same student and period.

#### Scenario: Maintain one active enrollment

- GIVEN a student has no active enrollment in an academic period
- WHEN a valid enrollment becomes effective
- THEN EVAL MUST treat it as the student's sole active enrollment for that period

#### Scenario: Reject overlapping enrollments

- GIVEN a student already has an active enrollment in an academic period
- WHEN another enrollment for that student would be active during an overlapping interval in the same period
- THEN EVAL MUST reject the conflicting enrollment

#### Scenario: Allow enrollments in different periods

- GIVEN a student has an enrollment in one academic period
- WHEN a valid enrollment is created for a different academic period
- THEN EVAL MUST permit the additional enrollment

### Requirement: Enrollment lifecycle and effective dates

Each enrollment MUST have an effective start date and MUST be in exactly one of `active`, `transferred`, or `closed` states. A transferred or closed enrollment MUST have an effective end date that is not earlier than its start date and MUST NOT become active again.

#### Scenario: Close an enrollment

- GIVEN a student has an active enrollment
- WHEN an authorized director/administrator closes it with a valid effective end date
- THEN EVAL MUST mark it closed
- AND EVAL MUST retain its effective interval

#### Scenario: Reject an invalid end date

- GIVEN an enrollment has an effective start date
- WHEN a transfer or closure is requested with an earlier end date
- THEN EVAL MUST reject the transition
- AND EVAL MUST keep the enrollment unchanged

### Requirement: Non-destructive transfer within a period

A grade or section transfer within the same academic period MUST end the prior enrollment as `transferred` and create a new active enrollment with a new identity. EVAL MUST preserve the prior enrollment as historical and MUST NOT overwrite its grade, section, or identity. From the effective moment of transfer, the prior enrollment MUST NOT authorize any new academic operation.

#### Scenario: Transfer a student to another section

- GIVEN a student has an active enrollment in an academic period
- AND a valid destination grade and section are available
- WHEN an authorized director/administrator records an effective transfer
- THEN EVAL MUST mark the prior enrollment as transferred
- AND EVAL MUST create a new active enrollment for the destination scope
- AND the two effective intervals MUST NOT overlap
- AND EVAL MUST use only the new enrollment to authorize academic operations created from that moment onward

#### Scenario: Reject a transfer to the existing scope

- GIVEN a student has an active enrollment for a grade and section
- WHEN a transfer is requested to the same grade and section in the same period
- THEN EVAL MUST reject the request as not constituting a scope change

### Requirement: Enrollment-derived student scope

For current academic access, EVAL MUST derive a student's grade and section from the student's active enrollment in the relevant academic period. EVAL MUST NOT authorize current student access from freely supplied grade or section values.

#### Scenario: Derive current student scope

- GIVEN a student has an active enrollment for a grade and section in the relevant period
- WHEN the student requests current academic content
- THEN EVAL MUST use that enrollment's grade and section to determine the teaching assignments and activities within the student's current scope

#### Scenario: Reject client-selected scope

- GIVEN a student supplies a grade or section that differs from the active enrollment
- WHEN the student requests academic content for that supplied scope
- THEN EVAL MUST deny access to the mismatched scope

#### Scenario: Deny current scope without active enrollment

- GIVEN a student has no active enrollment in the relevant academic period
- WHEN the student requests current academic content
- THEN EVAL MUST deny the request

### Requirement: Transfer visibility boundary

After a transfer becomes effective, EVAL MUST derive the student's scope for new activities and submissions exclusively from the new active enrollment. The historical enrollment MUST NOT authorize access to a new activity or creation of a new submission. The student MUST retain read access to the student's own enrollment history and submissions made before the transfer, including the original activity and teaching-assignment context needed to interpret those submissions.

#### Scenario: Use the destination scope after transfer

- GIVEN a student's transfer is effective
- WHEN the student requests a new activity or creates a new submission
- THEN EVAL MUST derive the student's academic scope exclusively from the new active enrollment

#### Scenario: Deny a new operation through the historical enrollment

- GIVEN a student's transfer is effective
- AND the student's prior enrollment is historical
- WHEN the student requests a new activity or attempts a new submission using the prior enrollment's scope
- THEN EVAL MUST deny the operation

#### Scenario: Retain access to the student's prior submission

- GIVEN the student submitted work before an effective transfer
- WHEN the student requests that submission after the transfer
- THEN EVAL MUST permit read access to the student's own submission
- AND EVAL MUST preserve its original activity and teaching-assignment context

#### Scenario: Retain read access to enrollment history

- GIVEN a student's prior enrollment became historical after a transfer
- WHEN the student requests the student's own enrollment history
- THEN EVAL MUST permit read access to that historical enrollment
- AND EVAL MUST NOT treat the read access as authority for a new academic operation

### Requirement: Enrollment history retention

EVAL MUST retain transferred and closed enrollments and MUST preserve the relationships needed to interpret the student's own historical submissions. Historical enrollment records MUST NOT be deleted or rewritten to represent the student's current scope, and MUST NOT authorize new academic operations.

#### Scenario: Review historical enrollment scope

- GIVEN a student has a transferred or closed enrollment
- WHEN an authorized actor reviews the student's history
- THEN EVAL MUST present the grade, section, period, and effective interval recorded for that enrollment

#### Scenario: Reject deletion of referenced enrollment history

- GIVEN an enrollment supports enrollment history or submission evidence
- WHEN deletion of the enrollment is requested
- THEN EVAL MUST reject the deletion

### Requirement: Local enrollment authority

EVAL MUST evaluate essential enrollment-derived student visibility and access on the school-hosted server without external Internet while the local server, LAN/WLAN, and power are available.

#### Scenario: Resolve enrollment scope without external Internet

- GIVEN the local server, LAN/WLAN, and power are available
- AND external Internet is unavailable
- WHEN a student requests current activities within the active enrollment scope
- THEN EVAL MUST resolve the student's enrollment and academic scope locally
