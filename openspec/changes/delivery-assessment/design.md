# Design: Delivery Assessment

Status: human-approved and implemented locally. Migration 000013 and the real teacher/student workflow are verified in docs/delivery-assessment-verification.md; remaining acceptance is stated explicitly there.

## Persistence and concurrency

Add an immutable assessment revision table beside activity_deliveries and delivery_versions. Each revision retains delivery ID, version ID, original teacher ID, grade, feedback, operation key and recorded time. A composite relationship or equivalent database constraint must establish that the assessed version belongs to that delivery. Add a nullable current assessment revision pointer to the delivery aggregate, constrained to its own revisions. Preserve existing submission references and immutable version/file records. Reject assessment revision UPDATE/DELETE; grant only the runtime columns needed to insert revisions and update the pointer.

Use AcademicWriteTransaction and its existing global guard/canonical lock order. Under the guard, rediscover and lock the period, identities, original assignment, activity and submission/delivery records. Recheck live credentials, original ownership, active parent period, expected current version and expected assessment revision. Insert the immutable revision, update the current pointer and append the resource-ID-only lifecycle event in one transaction. Feedback and grade values do not belong in lifecycle event metadata; they remain in the access-controlled revision table.

Extend the complete delivery writer under the same guard to deny updates when the assessment pointer exists. Thus a racing delivery update either commits first and makes the teacher's version expectation stale, or assessment commits first and blocks the update. Two concurrent assessments with the same expectation yield exactly one accepted revision; a stale request returns a conflict. No partial assessment or acknowledgement before commit is allowed.

## Proposed HTTP mapping

- GET /education/deliveries/{id}/assessments: original teacher or owning student, bounded cursor history of 50 rows.
- POST /education/deliveries/{id}/assessments: original teacher only; JSON grade, feedback, expected_version_id and expected_assessment_id (null for initial assessment). Server derives actor, route and period. Reject extra authority fields.
- Extend authorized delivery/version projections with nullable current assessment and server-derived can_assess/can_submit flags. A delivery with assessment has can_submit=false. Do not expose file storage keys or hashes in public payloads.

Use existing authentication, CSRF, strict decimal identifiers, no-store responses and error mappings. Malformed acknowledgement or lost response requires explicit state review before another write; never automatically replay a grade save.

## UI mapping

Deliveries preserves the accepted course selector and delivery cards. The original teacher chooses a grade explicitly (empty placeholder initially), writes required feedback and saves against the displayed version. Show confirmed grade only after server success. Corrections load the confirmed latest values and preserve the expected revision ID. Show stale/conflict messages with explicit refresh.

My grades uses server-authorized course and activity discovery, showing only the student's own deliveries and pending/graded state. Show feedback, assessed version and correction history with existing private downloads. Remove the student update form after a refreshed assessment; server enforcement remains mandatory even if an old page still contains the form. Keep the existing tablet layout and labels from the accepted demo.
