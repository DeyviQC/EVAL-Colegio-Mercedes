# Activity Delivery Capability

## ADDED Requirements

### Requirement: Assignment-owned activity publication
EVAL SHALL let the authenticated owning teacher publish titled instructions only through an active assignment in an active period. Content and the immutable activity reference SHALL commit atomically. Client-supplied academic scope or recipient SHALL be rejected.

#### Scenario: Publish a real activity
- WHEN the owning teacher submits valid title, instructions and an optional due date
- THEN authorized students can consult the confirmed activity in that assignment
- AND no publication is acknowledged before content and ownership commit.

### Requirement: Explicit availability and informational date
EVAL SHALL publish activities open, permit explicit closure by the original teacher and deny new deliveries/updates after closure. An optional due date SHALL not automatically close an activity. Closure SHALL retain historical content and deliveries.

#### Scenario: Date passed but activity remains open
- GIVEN the parent period remains active and the student's enrollment matches
- WHEN the student submits after the indicated date to an open activity
- THEN acceptance SHALL not be denied solely by that date.

### Requirement: Real retained delivery content
EVAL SHALL require nonblank answer text, one valid evidence file, or both. A student's first accepted delivery SHALL retain its original routing and enrollment context. Every confirmed update SHALL append an immutable version to the same logical delivery.

#### Scenario: Update with retained evidence
- GIVEN an open activity, active period and the same currently active accepted-under enrollment
- WHEN the student updates their work
- THEN the logical delivery identity and original route remain unchanged
- AND the previous answer/file and acceptance context remain retained.

### Requirement: Current work authority and original routing
EVAL SHALL evaluate new delivery authority from current active enrollment matching the activity scope and an active parent period. Assignment closure/replacement alone SHALL not redirect or deny an eligible open-activity delivery. A transferred enrollment SHALL not authorize updating its historical delivery.

#### Scenario: Replacement does not redirect work
- GIVEN an open activity of an original assignment in an active period and compatible current enrollment
- WHEN the student delivers after teacher replacement
- THEN work remains routed to the original assignment
- AND the successor teacher gains no permission to inspect the original delivery.

### Requirement: Private delivery consultation
EVAL SHALL allow the student to consult only their own retained deliveries and their original teacher to consult assignment-owned deliveries. Historical consultation SHALL preserve these ownership boundaries after closure or transfer. Management roles SHALL not receive delivery access in this capability.

#### Scenario: Cross-student or cross-teacher access
- WHEN a user directly requests another student's delivery or a non-owned original assignment's file
- THEN the server SHALL deny access independently of navigation and identifier knowledge.

### Requirement: Protected evidence and uncertain outcomes
EVAL SHALL enforce the approved evidence allowlist and 10 MiB initial limit against actual content and store bytes outside the public webroot. It SHALL protect all version downloads, retain prior confirmed evidence and avoid automatic mutation replay after unconfirmed outcomes.

#### Scenario: Lost acceptance response
- WHEN acceptance may have committed but its response cannot be confirmed
- THEN the client SHALL consult its own delivery before any explicit next action
- AND it SHALL not automatically submit a second version.
