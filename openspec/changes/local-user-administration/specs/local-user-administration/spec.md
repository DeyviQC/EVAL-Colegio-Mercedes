# Local User Administration

## ADDED Requirements

### Requirement: Director-managed teacher and student creation
EVAL SHALL allow only an active authenticated Director/Admin to atomically create a retained identity, display profile, unique normalized username, generated password hash and exactly one teacher or student role.

#### Scenario: Creation followed by academic setup
- WHEN the director creates a teacher or student
- THEN the account persists and can authenticate with the confirmed initial password
- AND no assignment, enrollment or course access is implicitly created.

#### Scenario: Duplicate or unauthorized creation
- WHEN a normalized username already exists, or the actor lacks current director authority
- THEN creation fails without a partial identity, credential or role.

### Requirement: Bounded directory and maintenance scope
EVAL SHALL expose a paginated teacher/student directory only to Director/Admin. It SHALL reject attempts to maintain director or vice-principal accounts through these operations, and SHALL not expose existing passwords or hashes.

#### Scenario: Privilege boundary
- WHEN a vice principal, teacher or student directly requests account maintenance
- THEN the server denies access independently of frontend visibility.

### Requirement: Retained deactivation and reactivation
EVAL SHALL allow the director to deactivate or reactivate teacher/student accounts without deleting identities or changing academic relationships.

#### Scenario: Deactivated teacher with retained materials
- WHEN the director deactivates a teacher
- THEN subsequent authenticated teacher requests fail
- AND assignment history and material authorship remain retained.

### Requirement: Password maintenance and revocation
EVAL SHALL allow the director to reset a teacher/student password and an authenticated user to change their own password after proving the current password. Passwords SHALL meet the approved byte limits; mutations SHALL recheck current authority under the guard and revoke old credential revisions.

#### Scenario: Old session after password change
- WHEN a password change or reset commits
- THEN a session authenticated with the prior revision cannot make a subsequent protected request
- AND the new password can authenticate.

### Requirement: Secret handling and uncertain outcomes
EVAL SHALL return generated initial/reset passwords only after confirmed commit, over the existing protected HTTPS session with no-store responses. It SHALL not persist plaintext passwords in logs or audit records or automatically replay an uncertain mutation.

#### Scenario: Response lost after creation
- WHEN the client cannot confirm a creation outcome
- THEN it reports uncertainty and consults the directory before any explicit next action
- AND it does not silently create another account or repeat the password reset.
