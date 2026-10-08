# Course Access Specification

## Purpose

Provide a minimal educational projection over approved academic relationships without exposing technical scope as user authority or implementing full account management.

### Requirement: Retained display identity

EVAL MUST provide a local visible name linked to the existing permanent retained identity. Credentials/login MUST NOT serve as a person's display name. Development/test names MUST be synthetic; institutional names MUST be provided and validated by authorized school personnel. Profiles MUST NOT enable identity reuse, erase historical references or create SIAGIE integration. A missing name MUST NOT fall back to an ID or invented name; the projection MUST explicitly report unavailable display context without exposing unapproved personal data. Historical labels are current retained labels, not invented immutable name snapshots.

#### Scenario: Resolve a course teacher

- GIVEN an authoritative assignment and retained teacher profile
- WHEN EVAL projects the authorized course
- THEN it MUST display the retained visible name and preserve assignment identity
- AND no client-supplied name MUST replace that source

#### Scenario: Missing profile

- GIVEN a required retained profile has no available visible name
- WHEN EVAL constructs the projection
- THEN it MUST fail closed for that display projection with an explicit unavailable response
- AND MUST NOT substitute a login, identity number or synthetic institutional name

### Requirement: Server-derived My Courses

EVAL MUST derive teacher courses from that teacher's authoritative assignments and student courses from authoritative enrollment relationships and matching period/grade/section. Course is a presentation of one teaching assignment; aula is grade plus section within a period. Distinct assignments MUST remain distinct even when their academic scope matches. EVAL MUST NOT accept actor/role/student/aula/teacher parameters as access evidence. The student projection MUST NOT expose a general assignment record, roster or another student's identity.

#### Scenario: Teacher ownership

- GIVEN a teacher with two own assignments and another teacher's assignment
- WHEN the teacher lists My Courses
- THEN EVAL MUST return only authorized own course contexts
- AND direct access to the unrelated course MUST be denied

#### Scenario: Student current aula

- GIVEN a student has an authoritative active enrollment
- WHEN the student lists operational courses
- THEN EVAL MUST derive the relevant period/grade/section from local records
- AND MUST NOT expand access through supplied locators or a client role selector

### Requirement: Current and historical context are separate

EVAL MUST distinguish operative access from retained historical consultation. Planned assignments MUST NOT grant publication authority. Closed assignments/periods MUST NOT grant new publication authority. A course card or client action affordance MUST NOT substitute for operation-level server checks. Historical course discovery MUST be bounded by an authorized retained relationship, including approved material eligibility, rather than permitting global browsing. Transfer/replacement MUST NOT rewrite original course identity or historical ownership.

#### Scenario: Distinct teachers in one aula

- GIVEN two assignments share a subject/area, grade, section and period
- WHEN an eligible user consults courses
- THEN the projection MUST retain each assignment and its teacher context independently

#### Scenario: Session or authority revoked

- GIVEN credentials, identity status or roles no longer authorize the request
- WHEN EVAL lists or opens a course
- THEN EVAL MUST reject using current server evidence
- AND the UI MUST clear inaccessible context rather than restore it from a late response

#### Scenario: Closed historical course

- GIVEN a retained historical relationship authorizes consultation
- WHEN the user opens its closed course
- THEN EVAL MUST present historical context without offering new publication

### Requirement: Minimal readable context

Course projections MUST include only the identifiers needed for navigation and readable subject/area, aula, period, teacher and lifecycle context. IDs MUST remain canonical strings internally and MUST NOT dominate visible labels. Collections MUST be bounded/paginated. Empty, loading, forbidden/unavailable and expired-session states MUST be explicit. No material rule is decided by merely showing a course.
