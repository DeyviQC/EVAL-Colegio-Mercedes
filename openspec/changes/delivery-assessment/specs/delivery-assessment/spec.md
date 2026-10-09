# Delivery Assessment Specification

## ADDED Requirements

### Requirement: Original-teacher assessment
EVAL SHALL permit only the original teacher of a delivery to record AD, A, B or C with nonblank feedback of at most 5,000 Unicode characters while the parent period is active.

#### Scenario: Assess a reviewed version
- GIVEN an ungraded delivery and its current evidence version
- WHEN its authenticated original teacher submits a valid grade and feedback with that version and a null expected assessment
- THEN one immutable assessment revision and its current pointer are committed atomically
- AND the response confirms the retained version and assessment revision.

#### Scenario: Reject unauthorized or invalid work
- WHEN a successor teacher, management actor, student or unrelated teacher attempts assessment, or an authorized teacher submits an invalid grade, blank feedback or a closed-period write
- THEN no revision, pointer or lifecycle mutation occurs.

### Requirement: Assessment protects evidence
EVAL SHALL deny student delivery updates after the first assessment, preserving every prior delivery version and file.

#### Scenario: Assessment races a delivery update
- WHEN assessment and update target the same delivery using independent connections
- THEN guard serialization either commits the update first and rejects the stale assessment version, or commits assessment first and denies the update
- AND no assessment references an unreviewed current version.

### Requirement: Corrections are retained
EVAL SHALL permit the original teacher to correct grade or feedback during an active parent period only when the supplied expected assessment equals the current revision.

#### Scenario: Concurrent correction
- WHEN two requests use the same expected assessment revision
- THEN at most one commits and the other reports a conflict
- AND earlier revisions remain immutable and readable by authorized users.

### Requirement: Private retained consultation
EVAL SHALL expose current assessments and bounded correction history only to the owning student and original teacher, preserving authorized historical reads after transfer, replacement or period closure.

#### Scenario: Student views My grades
- WHEN a student opens an authorized course
- THEN only their own delivery assessments and pending deliveries are shown with feedback and assessed version
- AND another student's assessment history or files cannot be obtained by guessed IDs.

### Requirement: Confirmed saves
EVAL SHALL display a grade as saved only after validating a committed server acknowledgement and SHALL NOT automatically replay an uncertain assessment request.

#### Scenario: Lost acknowledgement
- WHEN a write response is lost or malformed
- THEN the client requires explicit state review before another write and preserves no fabricated success state.
