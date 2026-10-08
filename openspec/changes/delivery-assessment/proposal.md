# Proposal: Delivery Assessment

Date: 2026-10-08. Status: approved by the human's Continue response to the concrete proposal. This approval covers the bounded rules, additive architecture and accepted screen mapping below. The local workflow is implemented and verified; see docs/delivery-assessment-verification.md for evidence and remaining acceptance.

## Intended result

The original teacher grades a received delivery and records feedback in Deliveries. The student opens My grades and sees the activity, course, assessed evidence version, grade and teacher feedback. Follow the accepted collaborator screen organization at locally available commit 2e5d0532840c370aa013655ce65410a3a494e73f. Keep the real session, course selection, immutable routing and protected version downloads.

## Proposed bounded rules

- Allowed grades are AD, A, B and C. Require nonblank feedback, maximum 5,000 Unicode characters, matching the accepted demo form. These are delivery-level classroom assessments; no official annual grade, competence aggregation or SIAGIE replacement is implied.
- Only the delivery's original teacher may assess or correct it, while its parent period is active. Assignment replacement or activity closure alone does not remove that original authority. A successor teacher, director or vice principal cannot assess or browse another teacher's deliveries.
- Assess the exact current delivery version. Submit its version ID as an expected value, not as authority. Reject a stale version rather than assessing work the teacher has not reviewed.
- The first assessment locks student updates of that logical delivery, matching the accepted demo. No reopening or removal of assessment in this unit.
- Allow the original teacher to correct the grade or feedback during the active period. Retain every assessment revision with actor, assessed version and server time. Corrections require the expected latest assessment ID to prevent overwriting another correction silently.
- The student sees only their own assessment and prior corrections. The original teacher sees that same history. Transfer, assignment replacement and period closure preserve historical reads. An ungraded delivery is shown as pending, never assigned a default grade.
- No notification system, report export, numeric average, grading rubric, bulk import or institutional management supervision is added by this unit.

## Approval scope

Approval covers the rules above, the additive transactional design, the already accepted screen mapping, and the verification plan. Implementation followed this approval. The preceding Activities and Deliveries workflow remains available and its regression checks pass.
