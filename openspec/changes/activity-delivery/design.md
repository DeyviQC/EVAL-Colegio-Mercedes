# Design: Activities and Deliveries

Status: pending bounded behavioral approval. Existing professor-approved screens are the UI reference; no substitute preview application is required.

Superseding status: approval recorded in proposal.md. The implemented mapping and evidence are below and in docs/activity-delivery-verification.md. Global planning/deployment gates remain unchanged.

## Storage and identity

activity_references contains immutable assignment ownership; submission_references contains immutable original student/activity/assignment/accepted-under enrollment and acceptance boundary. Their triggers currently reject updates and deletion. Preserve them. Add activity content/state separately, keyed by activity identity. Add a delivery aggregate keyed by submission identity with a unique student/activity ownership constraint and a current-version pointer; add immutable version rows carrying answer, optional protected file metadata, actor and guard boundary. Foreign keys retain evidence and prevent ID reassignment.

Finalize the explicit migration columns and unique/FK mapping before implementation. Current eval_dev has 20 tables and 11 migrations; provision and schema inspection use explicit allowlists/counts and must be deliberately extended. Do not apply new grants or migrations to older prerequisite test units implicitly. Use a dedicated isolated test mapping if necessary, with reviewed local schema-only provisioning; no global installation or privileges.

Final mapping: migration 000012 adds activity_contents keyed by immutable activity_id; activity_deliveries keyed by immutable submission_id with unique student_id/activity_id and current_version_id; delivery_versions with restrictive submission FK, immutable answer/file metadata, unique storage/operation keys and recorded_at. Version rows reject UPDATE/DELETE; all three new tables reject DELETE. Delivery files reside in the distinct private deliveries directory, not the material reconciler's directory. The development runtime is now 23 tables / 12 migrations. Tests use clearly named retained synthetic records in eval_dev and leave older prerequisite schemas/grants unchanged.

## Transactions and files

Use AcademicWriteTransaction: guard first, academic parent/catalog/retained identities/enrollment/assignment/activity/submission locks in existing canonical order. Account authority and credential revision are re-read through the current deactivation-aware helper. Discover and revalidate relationships before allocating a boundary. Publication inserts the immutable reference, content and publication evidence atomically. Initial acceptance inserts its reference, delivery/version and evidence atomically. An update appends a new version and advances its current pointer without mutating the original reference.

Store files on local disk outside the public webroot and metadata in MySQL. Reuse proven protected material-storage primitives where they fit actual evidence validation. Validate size, format and content; distrust caller MIME/filename. Stage validated bytes under a generated key, make the file durable before committing a visible version, and verify bytes/hash on download. A confirmed rollback may clean only its own unreferenced staged file. An uncertain commit must retain potential evidence for reconciliation and never replay automatically. Do not delete previous confirmed evidence on updates.

Concurrent first deliveries are serialized by the guard and protected by the unique student/activity aggregate constraint. Existing activity/submission reference contracts are not alternative public content writers. Route visibility remains fail-closed until all content and retained labels resolve. Pagination uses canonical decimal IDs and bounded pages, never unrestricted class-wide payload dumps.

## HTTP and presentation

Proposed same-origin routes: assignment-scoped GET/POST activity collection, POST activity close, activity-scoped GET/POST own delivery, original-teacher GET delivery collection, and protected version-file GET. Never accept client teacher, student, enrollment, grade, section, period or alternate assignment authority. The actor comes from the existing secure cookie and CSRF boundary.

Activities and Deliveries become real role-specific sections using the accepted grade/section/course selection, cards and forms. The course selector is derived from authorized server data; selecting a course cannot authorize a request. Show available historical content, availability and optional date; show the confirmed delivery and update affordance only when authorized. On failed refresh after a successful mutation, report the confirmed operation separately. On an uncertain mutation, block repeat writes until explicit consultation/review.

The server decides close/update authority and historical visibility. Frontend-hidden controls are convenience only. All evidence files are protected by identity, original assignment and ownership policy, including old versions. No public static file URL, Internet service, email notification or external routing dependency is introduced.

Final HTTP mapping: GET /education/courses; GET/POST /education/courses/{id}/activities; GET /education/activities/{id}; POST /education/activities/{id}/close; GET/POST /education/activities/{id}/deliveries; GET /education/deliveries/{id}/versions; GET /education/versions/{id}/file. Publication/closure use JSON; deliveries use multipart. GET activity detail refreshes server-owned state and action flags alongside delivery consultation. The student replaces current answer/evidence with each update; prior versions remain downloadable.

## Uncertain outcome consultation refinement

Implement the already approved lost-response requirement by retaining the affected request target and local operation revision. An explicit review can clear uncertainty only after a decoded, successful matching read started after uncertainty became known. Publication requires its course activity list; submission its activity delivery list; closure its activity detail; assessment its revision list or a delivery page containing the exact delivery. Unrelated, failed/malformed and earlier in-flight reads do not qualify. The frontend blocks writes until explicit review and never replays them. No backend, schema or permission change.
