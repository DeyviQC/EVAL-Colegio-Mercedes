# Proposal: Academic Notifications

Date: 2026-10-08. Status: approved through the human's Continue response to the concrete proposal; implemented and locally verified; institutional acceptance remains open. Classroom Reports is implemented and verified; no notification domain code or schema is created by this proposal.

## Intended result and accepted screen

Adapt the accepted collaborator Notifications screen at locally available commit 2e5d0532840c370aa013655ce65410a3a494e73f: newest-first notices, New/Read state, unread count and Mark all as read. Notifications are inside EVAL and available on the local network; no email, external push or third-party service is involved.

## Proposed bounded rules

- Publishing an activity notifies currently eligible active students in its exact period/grade/section. Students enrolled later do not receive retrospective notices.
- Accepting or updating a delivery notifies its original teacher. Initial grading or a correction notifies the delivery's owning student. Teacher replacement does not redirect the original route.
- Notices are generated atomically with the corresponding academic write, without partial success or duplicate recipients for the same operation. Rejected/rolled-back academic writes generate no notice. Existing records are not backfilled.
- Each account reads only its own notices. Use generic messages such as New activity available, New delivery received, Delivery updated and Grade available/updated; do not embed private answers, filenames, feedback, student names or grade values in the feed.
- A notice offers a shortcut to the relevant existing section when the current role permits it. The underlying section/resource independently rechecks academic access. A notice never grants work or file access.
- Keep retained notice history after transfer, replacement or period closure. Account deactivation/session revocation still denies feed access. Read state is one-way and retained; no delete, unread toggle or retention purge in this unit.
- Mark all as read applies only to that user's notices present when the feed was consulted, bounded by its server-provided latest notice ID. Later arrivals remain unread. Require CSRF and confirmed success; never automatically replay an uncertain receipt write.
- Feed pages contain at most 50 notices in descending ID order. Unread count covers the entire own feed, not only the visible page. Refresh is explicit; no polling or real-time availability promise is added.
- Material publication/editing, account welcome alerts, institutional broadcasts, report alerts and delivery-deadline reminders remain separately scoped.

## Approval scope

Approve these events, recipient/visibility rules, atomic additive persistence and adaptation of the already accepted Notifications screen. No new global credentials, installation, remote service or message to another person is authorized by this proposal.
