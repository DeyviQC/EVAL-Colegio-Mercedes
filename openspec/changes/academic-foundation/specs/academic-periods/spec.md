# Academic Periods Specification

## Purpose

Define the academic-period lifecycle that anchors current and historical academic relationships in EVAL.

## Requirements

### Requirement: Stable period identity

EVAL MUST give each academic period a stable identity and MUST require every academic period to have a unique name, a start date, and an end date. The start date MUST NOT be later than the end date, and every newly created period MUST start in the `planned` state.

#### Scenario: Create a valid academic period

- GIVEN an authorized director/administrator provides a unique name and valid date range
- WHEN the academic period is created
- THEN EVAL MUST create it with a stable identity
- AND EVAL MUST retain the supplied name and date boundaries
- AND EVAL MUST set its state to planned

#### Scenario: Reject an invalid date range

- GIVEN a proposed academic period has a start date later than its end date
- WHEN creation is requested
- THEN EVAL MUST reject the request
- AND EVAL MUST NOT create the academic period

#### Scenario: Reject a duplicate period name

- GIVEN an academic period already uses a name
- WHEN creation of another academic period with the same name is requested
- THEN EVAL MUST reject the request

### Requirement: Explicit period lifecycle

Each academic period MUST be in exactly one of `planned`, `active`, or `closed` states. EVAL MUST permit only the normal lifecycle transitions from `planned` to `active` and from `active` to `closed`. A period state MUST change only through an authorized explicit lifecycle operation; reaching a start date or end date MUST NOT change the state automatically. A `closed` period MUST NOT reopen within this capability.

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

Every student enrollment and teaching assignment MUST belong to exactly one academic period. EVAL MUST NOT permit those records to omit a period or to span multiple periods.

#### Scenario: Accept a period-bound record

- GIVEN an academic period exists
- WHEN a valid enrollment or teaching assignment is created for that period
- THEN EVAL MUST bind the record to exactly that academic period

#### Scenario: Reject a record without a period

- GIVEN an enrollment or teaching assignment request does not identify an academic period
- WHEN EVAL validates the request
- THEN EVAL MUST reject the request

### Requirement: Historical period retention

EVAL MUST retain closed academic periods and their identities. A period referenced by an enrollment, teaching assignment, activity, or submission MUST NOT be deleted or have its identity replaced.

#### Scenario: Close and retain a period

- GIVEN an active academic period has academic records
- WHEN an authorized director/administrator closes the period
- THEN EVAL MUST retain the period as historical
- AND EVAL MUST preserve its relationships to all academic records

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
