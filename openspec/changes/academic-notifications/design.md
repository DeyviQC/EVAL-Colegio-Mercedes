# Design: Academic Notifications

Status: proposed, awaiting human approval.

Add retained notification rows with recipient identity FK, event type, typed activity or delivery reference FK, source operation key, server creation time and nullable read time. Constrain the target type and unique operation/recipient combination. Protect immutable event/recipient/target fields and deletion, while allowing only the nullable-to-recorded read-state transition. Runtime privileges remain column-scoped; no client recipient authority is accepted.

Emit notices from the existing complete ActivityDelivery and DeliveryAssessment mutation callbacks inside AcademicWriteTransaction. Reuse the guard and existing canonical locks. For publication derive current eligible active student recipients from stored enrollment, period and credential boundaries under the guard. Delivery/assessment recipients come from immutable original routing. A notice insertion failure rolls back the academic mutation and its events; confirmed failure follows the existing evidence cleanup protocol. Lost commit acknowledgement remains uncertain and is not replayed automatically. Do not add a post-commit best-effort sender.

GET /notifications returns a read-only consistent own-feed snapshot: unread count, latest_id, at most 50 notices and next_before. Query filters recipient_id from the authenticated actor, never a request parameter. Project event type into generic Spanish copy and a currently permitted section shortcut; exclude hidden identity, content and storage fields. Preserve active-account and credential-revision checks.

POST /notifications/read accepts only up_to_id from a previously reviewed feed. Under the common guard, recheck live authority and that the boundary identifies the caller's own retained notice; update only that recipient's unread rows with id at or below the boundary. Confirm only after commit. Newer notices remain unread. Receipt state uses the server boundary timestamp; it does not rewrite academic references or source events.

The React Notifications workspace follows the accepted newest-first cards with New/Read labels, full unread count, explicit refresh, pagination and Mark all as read. Clear count/cards only on confirmed responses; do not invent a zero inbox on error. Maintain shared client uncertain-write blocking and expired-session review. Shortcuts select existing authorized sections, whose normal read logic stays authoritative. No browser service worker, OS permission, cloud transport or external dependency is needed.
