# Proposal: Activities and Deliveries

Date: 2026-10-08 (America/Lima). Status: concrete behavioral baseline for human approval before application implementation. The human accepted the collaborator's professor-reviewed screens and requested continuing Activities and Deliveries. Screen acceptance is established; the previously deferred content, availability and version policies are specified here for bounded approval.

Superseding approval: the human replied Continue to the concrete approval request listing informational deadlines, text/file evidence with 10 MiB limit, and retained updates while open. This authorizes this bounded capability using the already accepted screens. No grading, notifications or broader administrative access is inferred.

## Intended result

The teacher publishes a titled activity with instructions through an owned active assignment in an active period. An eligible student opens Activities, sends a written answer and/or one evidence file, and can consult the confirmed delivery. The original teacher receives the work in Deliveries. Both screens follow the accepted frontend at DeyviQC/EVAL-Colegio-Mercedes commit 2e5d0532840c370aa013655ce65410a3a494e73f.

## Recommended rules matching the accepted demo

- Required title (maximum 200 Unicode characters) and instructions (maximum 10,000); optional due date from today onward, using America/Lima school date. Publish immediately; no approval workflow.
- A new activity starts open. Its original teacher can explicitly close it to new deliveries, with confirmation. No reopening, activity editing or deletion in this bounded delivery.
- The optional due date is informational: passing it does not automatically close the activity. The student can deliver while the activity and parent period remain open. This matches the inspected demo server rather than inferring enforcement from the label.
- A delivery requires a nonblank answer (maximum 10,000 characters), one evidence file, or both. Evidence formats match the accepted demo: PDF, JPG/JPEG, PNG, TXT and DOCX; configurable initial maximum 10 MiB (10,485,760 bytes), with server content validation. Do not silently apply the separate material limit of 25 MiB.
- One logical delivery per student/activity. The student may update it while the activity and period remain open and the active enrollment is the same retained enrollment that authorized its acceptance. Retain each confirmed version and its file; updates never rewrite original student, activity, assignment, acceptance boundary or enrollment.
- Route automatically to the activity's original assignment and teacher. Teacher replacement or assignment closure alone does not redirect or deny an otherwise eligible open-activity delivery. A closed parent period denies every new delivery/update. A transferred student's former enrollment provides historical reads, not new work authority.
- The student sees only their own deliveries. The original teacher sees deliveries belonging to their original assignment, including historical consultation. Successor teachers and management roles do not receive browse-all delivery rights in this unit.
- Assessment, grades, feedback, notifications, report export and library remain separate units. Assessment later must block edits of assessed deliveries; no assessment state is fabricated here.

## Implementation boundaries

Add content/version/file persistence alongside immutable activity_references and submission_references. Reuse and extend their authoritative checks inside one guard transaction; do not invoke current public reference commands to commit half of a content operation and then save its payload separately. Preserve the archived minimum-routing specifications and tests; the complete activity capability adds availability checks without retroactively rewriting prerequisite contracts.

## Acceptance

Actual browser teacher publication, student text and file delivery, teacher consultation/download, student update with retained versions, explicit close and rejected new/update attempts. Prove negative cross-student, cross-course, successor-teacher and management access; closed-parent and transfer denials; original-route retention; strict file checks; failed-write rollback and uncertain-response non-replay. Verify locally without external services; school LAN/outage/load acceptance remains a later environment obligation.
