# Academic Notifications Specification

## ADDED Requirements

### Requirement: Atomic correctly routed notices
EVAL SHALL retain a notice atomically with approved activity publication, delivery acceptance/update and initial assessment/correction for the server-derived recipients described in the proposal.

#### Scenario: Publish and fail
- GIVEN eligible current students in the exact aula
- WHEN publication commits
- THEN only those recipients receive one notice each
- AND a rolled-back publication produces neither an activity nor notices.

#### Scenario: Original delivery route
- WHEN a student delivers after teacher replacement or receives a corrected assessment
- THEN the delivery notice still targets the original teacher and the assessment notice targets that owning student.

### Requirement: Own private feed
EVAL SHALL expose only the authenticated active account's own generic notices and full unread count, without granting private academic resource access.

#### Scenario: Cross-user request
- WHEN a caller supplies another recipient ID or guesses another user's read boundary
- THEN EVAL rejects it without exposing or mutating that other inbox.

### Requirement: Bound read receipts to observed history
EVAL SHALL mark only the caller's unread notices at or below an owned reviewed latest ID, after a confirmed CSRF-protected write, retaining newly arrived unread notices.

#### Scenario: New arrival after consultation
- GIVEN a consulted latest ID and a later new notice
- WHEN the caller marks the consulted feed as read
- THEN the later notice remains unread.

### Requirement: Retained bounded consultation
EVAL SHALL return at most 50 notices newest-first with a cursor, preserving own history after academic transfer, replacement or period closure while denying inactive/revoked sessions.

#### Scenario: Uncertain acknowledgement
- WHEN a receipt write acknowledgement is lost or malformed
- THEN the client reports uncertainty and requires explicit review before another write, without automatic replay or fabricated read state.
