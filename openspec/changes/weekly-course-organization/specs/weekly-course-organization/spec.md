# Weekly Course Organization

Requirements and architecture approved by the human on 2026-10-08. Delivered local functionality accepted with "apruebo todo continua" after the operation review. Institutional calendar evidence and physical network acceptance remain separate outstanding conditions.

## ADDED Requirements

### Requirement: Weekly content discovery
EVAL SHALL provide a course view grouping authorized materials and activities by their assigned teaching week, while retaining content-type views.

#### Scenario: Student opens a week
- GIVEN the student is authorized to consult a course and its content
- WHEN the student expands a week
- THEN its authorized materials and activities appear together with clear type labels.

### Requirement: Content without a week remains discoverable
EVAL SHALL include authorized content without a week in an explicit Unassigned section.

#### Scenario: General resource
- WHEN an authorized material has no week association
- THEN the student can find it in Unassigned and in the material view.

### Requirement: Preserve academic behavior and local availability
Weekly grouping SHALL preserve existing server authorization, historical eligibility, original ownership, delivery routing, due dates and explicit activity closure. Essential consultation SHALL require no external Internet while the local server, network and power are available.

#### Scenario: Activity deadline has passed
- GIVEN an eligible student and an open activity in an active period
- WHEN the student finds the activity through a previous week
- THEN grouping SHALL NOT close it or deny delivery solely because of its week or due date.

#### Scenario: Unauthorized content
- WHEN a user opens a week in a course
- THEN unauthorized resources SHALL NOT appear in its contents or counts.
