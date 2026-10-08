# Student Enrollment Specification

## Purpose

Define period-bound student enrollment, enrollment-derived academic scope, transfers, and retained student history in EVAL.

## Requirements

### Requirement: Stable period-bound enrollment

EVAL MUST give each student enrollment a stable identity and MUST bind it to exactly one student, one academic period, one grade, and one section. The section MUST belong to the selected grade. The period identity is mandatory, but enrollment declared dates MUST NOT be subject to an additional containment rule against period dates. All other date, scope, lifecycle, authorization, and conflict validations MUST still apply. Creation of an active enrollment, including a transfer successor, MUST require an `active` parent period; `planned` and `closed` parents MUST reject that creation. This enrollment-specific rule MUST NOT prohibit otherwise valid planned teaching-assignment creation.

#### Scenario: Enroll a student for a period

- GIVEN the student, active academic period, grade, and section are valid
- AND the section belongs to the grade
- WHEN an authorized director/administrator creates the enrollment
- THEN EVAL MUST create a stable enrollment bound to exactly those records

#### Scenario: Do not reject solely for enrollment dates outside the period

- GIVEN an enrollment identifies exactly one active period and its declared dates fall outside that period's calendar boundaries
- AND all other validations are satisfied
- WHEN an authorized director/administrator requests enrollment
- THEN EVAL MUST NOT reject solely because the enrollment dates are not contained within the period
- AND EVAL MUST NOT infer a date-to-operational-key mapping from this absence of containment

#### Scenario: Reject incomplete enrollment scope

- GIVEN an enrollment request omits the period, grade, or section
- WHEN EVAL validates the request
- THEN EVAL MUST reject the request

#### Scenario: Reject active enrollment creation under a planned period

- GIVEN the requested academic period is planned
- WHEN creation of an active enrollment or transfer successor is requested
- THEN EVAL MUST reject creation without changing academic state, boundaries, identities or events
- AND this enrollment-specific denial MUST NOT prohibit valid planned teaching-assignment creation

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
- WHEN a valid enrollment is created for a different active academic period
- THEN EVAL MUST permit the additional enrollment

### Requirement: Enrollment lifecycle and effective dates

Each enrollment MUST have an effective start date and MUST be in exactly one of `active`, `transferred`, or `closed` states. A transferred or closed enrollment MUST have an effective end date that is not earlier than its start date and MUST NOT become active again. An open end MUST NOT expire automatically at the period end. Director/administrator MUST be permitted to close a residual active enrollment explicitly with a valid end date even when its period is closed; period closure MUST NOT close it automatically.

For explicit administrative closure, the declared end date MUST be a valid school-local date on or before the server's current date in America/Lima, and MUST NOT precede the declared start. A future declared closure date MUST be rejected without mutations. Closure MUST end operational authority at the actual successful server transaction key K, while preserving the supplied permitted declared end date. EVAL MUST NOT backdate K, convert the declared date to midnight/ordinal, rewrite earlier accepted context, or schedule closure from that date. This mapping applies only to enrollment administrative closure; assignment/replacement mappings remain separate.

#### Scenario: Close an enrollment

- GIVEN a student has an active enrollment, including a residual active enrollment under a closed period
- WHEN an authorized director/administrator closes it with a valid effective end date
- THEN EVAL MUST mark it closed
- AND EVAL MUST retain its effective interval

#### Scenario: Preserve a past declared closure date without retroactive authority changes

- GIVEN an active enrollment has a declared start no later than a permitted past closure date
- WHEN director/administrator explicitly closes it successfully at server key K
- THEN EVAL MUST preserve the supplied declared end date and set operational end to K
- AND EVAL MUST preserve previously accepted context and history without backdating the operation

#### Scenario: Reject a future administrative closure date

- GIVEN a requested closure date is later than the server's current school-local date
- WHEN explicit enrollment closure is requested
- THEN EVAL MUST reject the request without changing state, dates, boundaries or events

#### Scenario: Keep an open enrollment without automatic expiry

- GIVEN an active enrollment has no recorded effective end
- WHEN the period reaches its end date or is explicitly closed
- THEN EVAL MUST NOT automatically expire or close the enrollment
- AND a closed parent MUST NOT allow that enrollment to authorize new academic work

#### Scenario: Reject an invalid end date

- GIVEN an enrollment has an effective start date
- WHEN a transfer or closure is requested with an earlier end date
- THEN EVAL MUST reject the transition
- AND EVAL MUST keep the enrollment unchanged

### Requirement: Non-destructive transfer within a period

A grade or section transfer within the same active academic period MUST end the prior enrollment as `transferred` and create a new active enrollment with a new identity. Transfers MUST take effect immediately only upon successful server-confirmed execution. Future scheduling, retroactive transfers, and transfer corrections MUST be rejected without mutations. EVAL MUST preserve the prior enrollment as historical and MUST NOT overwrite its grade, section, or identity. From the server-effective moment of successful transfer, the prior enrollment MUST NOT authorize any new academic operation. Same-day operations MUST retain their ordered, non-overlapping history without imposing a minimum one-day interval.

#### Scenario: Transfer a student to another section

- GIVEN a student has an active enrollment in an active academic period
- AND a valid destination grade and section are available
- WHEN an authorized director/administrator requests an immediate transfer and the server successfully confirms it
- THEN EVAL MUST mark the prior enrollment as transferred
- AND EVAL MUST create a new active enrollment for the destination scope
- AND the two effective intervals MUST NOT overlap
- AND EVAL MUST use only the new enrollment to authorize academic operations created from that moment onward

#### Scenario: Reject scheduled retroactive or corrective transfers

- GIVEN a student has an active enrollment
- WHEN a future-scheduled transfer, retroactive transfer, or transfer correction is requested
- THEN EVAL MUST reject the request
- AND EVAL MUST NOT change enrollment state, intervals, identities, events, or accepted submission context

#### Scenario: Reject transfer under a closed period

- GIVEN a student has a residual active enrollment in a closed period
- WHEN transfer is requested
- THEN EVAL MUST reject successor creation
- AND EVAL MUST leave the prior enrollment and history unchanged

#### Scenario: Reject a transfer to the existing scope

- GIVEN a student has an active enrollment for a grade and section
- WHEN a transfer is requested to the same grade and section in the same period
- THEN EVAL MUST reject the request as not constituting a scope change

### Requirement: Enrollment-derived student scope

For current academic access, EVAL MUST derive a student's grade and section from the student's active enrollment in the relevant academic period. EVAL MUST NOT authorize current student access from freely supplied grade or section values. A closed relevant period MUST deny new academic work even if its enrollment remains active; this MUST NOT become a blanket active-parent-only rule for otherwise permitted planning in planned periods.

#### Scenario: Derive current student scope

- GIVEN a student has an active enrollment for a grade and section in the relevant active period
- WHEN the student requests current academic content
- THEN EVAL MUST use that enrollment's grade and section to determine the teaching assignments and activities within the student's current scope

#### Scenario: Deny new work through an active enrollment in a closed period

- GIVEN a student retains an active enrollment in a closed period
- WHEN the student requests a new activity or attempts a new submission, including a late submission
- THEN EVAL MUST deny the new operation despite the active child state
- AND EVAL MUST preserve role-authorized reads of the student's own retained history

#### Scenario: Reject client-selected scope

- GIVEN a student supplies a grade or section that differs from the active enrollment
- WHEN the student requests academic content for that supplied scope
- THEN EVAL MUST deny access to the mismatched scope

#### Scenario: Deny current scope without active enrollment

- GIVEN a student has no active enrollment in the relevant academic period
- WHEN the student requests current academic content
- THEN EVAL MUST deny the request

### Requirement: Transfer visibility boundary

After an immediate transfer is successfully confirmed by the server, EVAL MUST derive the student's scope for new activities and submissions exclusively from the new active enrollment in an active relevant period. The historical enrollment MUST NOT authorize access to a new activity or creation of a new submission. The student MUST retain read access to the student's own enrollment history and submissions made before the transfer, including the original activity, teaching assignment, and accepted-under enrollment context needed to interpret those submissions.

#### Scenario: Use the destination scope after transfer

- GIVEN a student's immediate transfer has been successfully confirmed by the server and the relevant period remains active
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
