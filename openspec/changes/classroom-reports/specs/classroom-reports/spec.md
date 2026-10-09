# Classroom Reports Specification

## ADDED Requirements

### Requirement: Bounded director summary authority
EVAL SHALL permit a live authenticated director to read summaries for an explicitly selected period, grade and section, without granting access to private delivery content, evidence or assessment editing.

#### Scenario: Director consults a classroom
- WHEN the director selects a valid period/grade/section
- THEN EVAL returns named scope, summary cards and bounded student rows derived from that exact scope.

#### Scenario: Deny unrelated roles and invalid scope
- WHEN another role requests the report or a section belongs to another grade
- THEN access or scope validation fails without exposing roster or delivery data.

### Requirement: Count logical deliveries and current grades
EVAL SHALL count one received delivery per student/activity, regardless of retained evidence versions or assessment revisions, and SHALL use only its current assessment for AD/A/B/C distribution.

#### Scenario: Correct a grade
- WHEN a teacher corrects A to AD
- THEN a refreshed report moves one item from A to AD while received and graded totals remain unchanged.

#### Scenario: Pending grading
- GIVEN a received ungraded delivery
- THEN it contributes one received and one pending item, without an invented grade or missing-work classification.

### Requirement: Retain historical attribution
EVAL SHALL preserve reports by the delivery's original period/classroom after transfer, replacement or period closure, showing former delivery owners as historical rows.

#### Scenario: Transfer and closure
- GIVEN retained graded work and a subsequent student transfer or period closure
- WHEN the director selects the original scope
- THEN the delivery and current grade remain counted there without being moved to the new classroom.

### Requirement: Consistent complete totals with bounded rows
EVAL SHALL derive each response from one consistent snapshot, with totals for the complete scope and at most 50 student rows per page.

#### Scenario: More than one page
- GIVEN more than 50 eligible roster rows
- THEN the first response returns a cursor and complete summary totals; requesting later rows does not change totals merely because the page changed.
