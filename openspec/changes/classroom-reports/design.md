# Design: Classroom Reports

Status: human-approved, implemented and verified locally; see docs/classroom-report-verification.md. The report menu uses the existing server-confirmed director can_manage flag; its endpoint independently enforces the dedicated report permission.

Use a dedicated read service and controller under the existing authenticated session, with live retained identity/credential checks and stored director_admin role. Keep the reporting permission separate from ActivityDelivery and DeliveryAssessment authorization; management continues to be denied individual work and file reads.

GET /reports/classroom accepts period_id, grade_id, section_id and optional after. Reject extra fields, bodies, noncanonical IDs and a section not belonging to the selected grade. Reuse existing authorized director period/catalog discovery for named selectors. Missing context does not silently choose an arbitrary classroom.

Filter teaching_assignments by the exact selected period, grade and section. Join submission_references/activity_deliveries on their original assignment and accepted enrollment; retain student and original classroom attribution. Verify those retained relationships rather than trusting a client student selector. Group only logical deliveries, and join only the current assessment pointer constrained to that delivery. Include current active-period enrollment rows with zero counts, unioned with historical delivery owners. Never expose answer, feedback, credential, storage or file metadata.

Within one read transaction/snapshot compute complete totals and grade distribution plus a student-ID-ordered page of 50 rows with a next cursor. Return scope labels, received/ungraded/graded totals, AD/A/B/C counts and student rows (display name, historical flag and counts). Do not invoke the academic write guard, mutate domain state or append lifecycle events for consultation.

The React Reports workspace is visible only to a server-confirmed director reporting capability. It preserves accepted classroom selectors, summary cards and roster table with tablet overflow containment. Counts come from the server, not from summing a partial browser page. Pending/empty states are distinct; a zero report is shown only after a confirmed response. GET uses existing no-store same-origin session transport; authentication expiry returns to session review. No external chart, cloud or export service is needed.
