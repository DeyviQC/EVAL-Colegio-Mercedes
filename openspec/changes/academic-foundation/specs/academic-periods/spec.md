# Academic Periods Specification

## Purpose

Define the academic-period lifecycle that anchors current and historical academic relationships in EVAL.

## Requirements

### Requirement: Stable period identity

EVAL MUST give each academic period a stable identity and MUST require every academic period to have a unique name across all states, a start date, and an end date. Name comparison MUST ignore case and outer whitespace and MUST use NFC Unicode normalization, while distinguishing accents. The comparison key MUST be separate from the visible user label; EVAL MUST preserve that label except for a future precisely defined whitespace-display policy. Exact trim/case mechanics and database representation remain technical decisions that MUST reproduce this policy, not define it. The start date MUST NOT be later than the end date, and every newly created period MUST start in the `planned` state.

#### Scenario: Create a valid academic period

- GIVEN an authorized director/administrator provides a name unique under the approved comparison policy and a valid date range
- WHEN the academic period is created
- THEN EVAL MUST create it with a stable identity
- AND EVAL MUST retain the supplied visible name separately from its comparison key and retain the date boundaries
- AND EVAL MUST set its state to planned

#### Scenario: Reject an invalid date range

- GIVEN a proposed academic period has a start date later than its end date
- WHEN creation is requested
- THEN EVAL MUST reject the request
- AND EVAL MUST NOT create the academic period

#### Scenario: Reject a duplicate period name

- GIVEN an academic period in any state already uses a name
- WHEN creation of another academic period with a name equivalent after outer trimming, case-insensitive comparison, and NFC normalization is requested
- THEN EVAL MUST reject the request

#### Scenario: Distinguish accents in period names

- GIVEN an academic period named `Álgebra` exists
- WHEN a valid period named `Algebra` is proposed
- THEN EVAL MUST NOT reject it as a duplicate of `Álgebra`

### Requirement: Explicit period lifecycle

Each academic period MUST be in exactly one of `planned`, `active`, or `closed` states. EVAL MUST permit only the normal lifecycle transitions from `planned` to `active` and from `active` to `closed`. A period state MUST change only through an authorized explicit lifecycle operation; reaching a start date or end date MUST NOT change the state automatically. A `closed` period MUST NOT reopen within this capability. Closure MUST prohibit new academic work in that period: new enrollments, transfer successors, teaching assignments (including planned assignments), assignment activation, replacement successors, activities, and submissions, including late submissions. An active child MUST NOT override its closed parent. Explicit administrative closure of residual active enrollments by director/administrator and residual active assignments by director/administrator or vice principal MUST remain permitted subject to valid dates. Closure MUST NOT automatically change child state, cascade, delete history, or rewrite retained relationships; global catalog management and other periods MUST remain unaffected. A planned parent MUST NOT be rejected merely for being non-active when an otherwise valid planning operation is requested.

#### Scenario: Activate a planned period

- GIVEN a planned academic period has valid date boundaries
- WHEN an authorized director/administrator activates it
- THEN EVAL MUST mark the period as active
- AND EVAL MUST treat its activation as the beginning of its current operational use

#### Scenario: Close an active period

- GIVEN an academic period is active
- WHEN an authorized director/administrator closes it
- THEN EVAL MUST mark the period as closed
- AND EVAL MUST retain it as historical
- AND EVAL MUST prohibit new academic work without automatically closing its children

#### Scenario: Close a period with active children

- GIVEN an active period has active enrollments and teaching assignments
- WHEN an authorized director/administrator closes the period
- THEN EVAL MUST close only the period and retain the children's states and history
- AND EVAL MUST permit explicit residual enrollment closure by director/administrator and assignment closure by director/administrator or vice principal with valid dates
- AND EVAL MUST NOT treat the active children as authority for new academic work

#### Scenario: Keep lifecycle independent from dates

- GIVEN a planned period reaches its start date or an active period reaches its end date
- WHEN no authorized lifecycle operation is performed
- THEN EVAL MUST keep the period in its existing state

#### Scenario: Reject a skipped lifecycle transition

- GIVEN an academic period is planned
- WHEN a request attempts to change it directly to closed
- THEN EVAL MUST reject the transition
- AND EVAL MUST keep the period planned

#### Scenario: Reject an invalid lifecycle reversal

- GIVEN an academic period is closed
- WHEN a request attempts to change it to planned or active
- THEN EVAL MUST reject the transition
- AND EVAL MUST keep the period closed

### Requirement: Single current period

EVAL MUST have no more than one active academic period at a time. The active academic period MUST be treated as the current period; planned and closed periods MUST NOT be treated as current.

#### Scenario: Identify the current period

- GIVEN exactly one academic period is active
- WHEN EVAL resolves the current academic period
- THEN EVAL MUST return that active period

#### Scenario: Prevent concurrent active periods

- GIVEN one academic period is active
- WHEN activation of another planned period is requested
- THEN EVAL MUST reject the activation
- AND EVAL MUST preserve the existing active period

#### Scenario: Operate without a current period

- GIVEN no academic period is active
- WHEN EVAL resolves the current academic period
- THEN EVAL MUST report that no current period exists
- AND EVAL MUST NOT infer a current period from dates alone

### Requirement: Period-bound academic records

Every student enrollment and teaching assignment MUST belong to exactly one academic period. EVAL MUST NOT permit those records to omit a period or to span multiple periods. Enrollment period binding MUST NOT impose an additional declared-date containment rule; teaching-assignment date containment MUST remain required. New records MUST NOT be created in a closed period, including transfer or replacement successors and planned assignments.

#### Scenario: Accept a period-bound record

- GIVEN a non-closed academic period exists
- AND active enrollment creation (including a transfer successor) targets an active period; planned teaching-assignment creation retains its separately approved eligibility
- WHEN a valid enrollment or teaching assignment is created for that period
- THEN EVAL MUST bind the record to exactly that academic period
- AND EVAL MUST apply enrollment and assignment date validation independently without adding enrollment-date containment

#### Scenario: Reject new records in a closed period

- GIVEN an academic period is closed
- WHEN creation of an enrollment, transfer successor, teaching assignment including a planned assignment, or replacement successor is requested in that period
- THEN EVAL MUST reject the request without creating or changing academic records

#### Scenario: Reject a record without a period

- GIVEN an enrollment or teaching assignment request does not identify an academic period
- WHEN EVAL validates the request
- THEN EVAL MUST reject the request

### Requirement: Historical period retention

Active enrollment creation, including transfer successors, requires an active parent period under the approved enrollment-specific eligibility rule. Planned-parent teaching-assignment planning remains permitted; this rule does not settle assignment activation or other new-work eligibility under planned parents. Enrollment administrative closure retains its separately approved past/current declared-date and actual-K mapping, including residual closure under a closed period.

EVAL MUST retain closed academic periods and their identities. A period referenced by an enrollment, teaching assignment, activity, or submission MUST NOT be deleted or have its identity replaced. Role-authorized historical reads MUST remain available with original identities, owners, routes, and accepted-under enrollment context; closure MUST NOT rewrite them.

#### Scenario: Close and retain a period

- GIVEN an active academic period has academic records
- WHEN an authorized director/administrator closes the period
- THEN EVAL MUST retain the period as historical
- AND EVAL MUST preserve its relationships to all academic records
- AND EVAL MUST preserve original owners, routes, and accepted-under enrollment context for role-authorized historical reads

#### Scenario: Reject deletion of referenced history

- GIVEN a closed academic period is referenced by an academic record
- WHEN deletion of that period is requested
- THEN EVAL MUST reject the deletion
- AND EVAL MUST leave the historical relationships unchanged

### Requirement: Local period authority

EVAL MUST perform essential current-period resolution and period validation on the school-hosted server without requiring external Internet while the local server, LAN/WLAN, and power are available.

#### Scenario: Resolve the current period without external Internet

- GIVEN the local server, LAN/WLAN, and power are available
- AND external Internet is unavailable
- WHEN a local client requests an operation that requires the current academic period
- THEN EVAL MUST resolve and validate the period locally
