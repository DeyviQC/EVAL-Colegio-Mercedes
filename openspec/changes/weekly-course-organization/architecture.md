# Weekly Course Organization Architecture Proposal

Date: 2026-10-08. Status: human-approved architecture, confirmation "aprobado todo continua" after the architecture review. Approval includes the proposed sequential integration direction. No application implementation is performed by this document. The subsequently created calendar-management preview remains subject to the applicable prototype gate; its synthetic confirmation must never be treated as an approved school PAT.

## Existing integration constraints

The local backend uses an authenticated route composition closure in backend/routes/academic.php, Illuminate database connections and AcademicWriteTransaction for bounded writes. It must not be described as a verified full Laravel HTTP kernel. Existing academic periods have planned/active/closed transitions, with one active period enforced. Calendar publishing must not invoke those transitions.

MyCourses.tsx consults academic course records while ActivityWorkspace uses the education client; strict material/activity decoders currently have no week field. Do not assume course identifiers across these clients are interchangeable. The server must resolve the approved shared academic context and authorize each resource through its existing consultation rules.

## Proposed calendar model

- One logical institutional calendar belongs to one existing academic period. Its year is a display attribute, not an authorization or equality key. The calendar does not create a second academic-period lifecycle.
- A calendar revision contains ordered teaching/management blocks and explicitly dated teaching weeks. Each week has an opaque stable identity within that revision, a unique teaching-week number, inclusive start/end dates and a containing teaching block. Date-only values use the school calendar, without UTC conversion of midnight.
- Draft revisions are editable by Director/Admin. Validate dates against the parent period, chronological ordering, non-overlap, week containment and unique week numbers. Permit shorter institutional teaching weeks; do not assume every week has five working days or calculate instructional-day compliance merely from date spans.
- Publishing a revision requires management to record the calendar source/reference and explicit confirmation that it reflects the approved institutional calendar. The national template remains a draft until confirmed. Publishing selects the current revision atomically and does not activate or close the parent period.
- Published revisions and week dates are immutable. Corrections use a new draft revision. Previously associated content retains its original revision/week until an authorized teacher explicitly reassociates it under current authority; no bulk automatic migration occurs. Retain old revisions for authorized content/history consultation and visibly distinguish earlier-calendar associations in the course view.
- A closed academic period permits authorized historical calendar consultation, but no calendar publication or association writes. Exact lock ordering and persisted event representation require an implementation review using existing transaction conventions.

## Proposed content association

Use separate typed material-week and activity-week associations, each referencing an existing resource and a retained week identity. Each resource has zero or one current association; retain changes for review rather than deleting previous confirmed context. This is a conceptual storage contract, not a migration or finalized schema.

Association commands derive resource assignment and period on the server, verify that the week belongs to that same period's current published calendar revision, and authorize the original owning teacher under active context. Reject extra client-supplied actor/assignment/recipient fields. Existing material or activity eligibility alone does not grant association-write authority.

Set or clear an association through a dedicated command after confirmed publication. A material/activity publication initially remains Unassigned. If association fails, clearly report that the resource exists but was not organized; never replay publication to retry organization. This deliberately avoids changing established upload/acceptance atomicity in the first bounded delivery.

Use a resource association revision precondition: two competing edits cannot silently overwrite each other. Refresh authority and parent state inside the write transaction. On an unknown outcome, consult the resource association before offering an explicit retry. Do not add automatic mutation replay.

Association edits preserve title, file versions, author, publication date, deadline, activity state, original assignment and delivery route. Successor teachers gain no association editing rights through this capability.

## Proposed HTTP contracts

Exact final route placement must be reconciled with the local runtime adapter before coding. Proposed semantic operations:

| Operation | Authority / response |
|---|---|
| Consult course calendar | Existing course consultation eligibility; current revision plus referenced retained revisions |
| Create/edit calendar draft | Director/Admin; validated draft and revision token |
| Publish confirmed calendar revision | Director/Admin; revision precondition and source confirmation |
| Consult resource week association | Existing resource consultation authority; week/revision or null, association revision |
| Set/clear resource week association | Original owning teacher in active context; association precondition |
| Consult course week content | Each resource's existing authority; separate paginated material/activity lists and authorized total counts |

Preserve existing material/activity wire shapes initially; use additive dedicated association and calendar responses with strict decoders. Invalid IDs, cross-period weeks, stale revision, unpublished calendar, closed period and denied authority require explicit errors following existing categories/envelopes. Protected file routes remain unchanged.

Material and activity cursors are separate, because their identities/order need not be shared. Counts are server-derived over the full authorized result set; never infer totals from the first page. Recheck resource authority when opening content. On authorization failure or session expiry, clear displayed content using the existing session handling.

## Course interface

Add Weeks alongside existing material/activity/delivery paths, sharing retained course context without replacing current content-type entry points. An unpublished/missing calendar displays an explicit calendar-not-configured state and leaves all existing content reachable through Unassigned and existing views.

Show the school-approved reporting label when configured; otherwise Block. Retain direct week navigation and content-only filtering from the prototype, with general resources always reachable. An explicit section for content associated with an earlier calendar revision prevents it from disappearing when a new revision becomes current.

Current-week detection is deferred from the first delivery: the preview does not infer it, and school dates, weekends and management gaps require separate review. Pending-delivery shortcuts likewise remain outside this first integration. Calendar revision maintenance needs a concrete management preview before its application UI is implemented.

## Sequential implementation units after approval

1. Calendar consultation/drafts/publication and retained revision behavior, with meaningful backend/contract checks and a reviewable management prototype.
2. Optional resource associations, stale-write handling and preserved ownership, starting from the verified predecessor.
3. Shared course Weeks view, authorized paginated discovery, retained-revision visibility and existing UI integration.

Finish and verify each bounded unit before the next starts. Record verification and outstanding gates in each handoff. Local commit authorization for these new units must be established; reconciliation-only commit permission is not expanded here. No parallel application-unit work, remote actions, global installation or institutional deployment is proposed.

## Verification criteria

- Calendar order/overlap/containment and shorter-week validation; management gaps; draft/publication and retained revision immutability.
- Cross-period/role/resource denials, ownership after teacher replacement and closed-period write denial.
- Existing content Unassigned; association failures do not duplicate resource publication; competing edits and unknown-outcome recovery.
- Dates, file history, routing and deadlines unchanged; current and historical student access independently enforced.
- Separate pagination, full authorized counts and denial without hidden-resource leakage.
- Missing calendar and older-revision UI states; keyboard, reduced-motion and tablet/mobile journeys; no external Internet dependency.

Run the existing repository-supported test/build harnesses when implementation is approved; document physical LAN/tablet and institutional acceptance limitations separately. This architecture document does not certify those checks.
