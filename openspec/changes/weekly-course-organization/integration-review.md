# Weekly Course Integration Review

Status: proposed implementation plan for human review; no application changes. Date: 2026-10-08.

## Observed implementation

`frontend/src/features/academic-foundation/MyCourses.tsx` loads authorized courses and mounts MaterialsPanel or LibraryPanel after selecting a course. Activities and deliveries have their own ActivityWorkspace. The shared weekly view therefore requires deliberate navigation integration rather than styling alone.

`frontend/src/features/academic-foundation/materials.ts` strictly decodes material response fields and publishes title, optional description and file. It exposes no teaching-week association. Real weekly grouping requires an approved backend contract, not sorting the current response by publication time. Existing contracts and strict decoders must evolve together; a frontend-only week field would not persist or establish server authority.

## Concrete recommended behavior for approval

1. Director/Admin maintains one calendar for each institutional academic year. Use the national 2026 schedule as a clearly marked draft; management confirms or adjusts it against the school's PAT before activation. Do not equate display weeks or reporting blocks with new academic periods or automatically close existing periods.
2. Separate teaching and management blocks. Use neutral Block labels until the school's reporting organization is confirmed. Number teaching weeks continuously and show dates; management weeks do not increment that number.
3. An eligible owning teacher may optionally associate a material/activity with one existing teaching week. Unassigned remains valid. Existing content initially remains Unassigned; do not infer membership from publication or due dates.
4. Proposed association editing is limited to original owning teachers with existing current maintenance/publication authority in active context. Successors receive no new editing rights. Review material and activity maintenance differences before approving the contract; do not generalize rights from presentation controls.
5. Course navigation adds Weeks alongside existing content views. Students see only server-authorized resources and counts; historical access remains independently enforced. Direct week navigation and the content-only filter follow the verified preview.
6. Calendar changes with associated content must require explicit review of affected associations and preserve prior context. No silent content movement, deadline changes, delivery rerouting or history rewriting. Exact revision handling must be approved before implementation.

## Proposed integration boundaries

- Add bounded institutional calendar consultation/maintenance contracts and optional content-week associations after architecture approval. Exact API names, storage design and revision behavior remain design work; this document does not create schemas or migrations.
- Reuse authorized material/activity consultation and protected file actions. Do not fetch all delivery histories to populate a week page. Specify pagination and completeness of week counts before coding; counts cannot be computed from only one partial page.
- Preserve authentication expiry, stale context handling and unknown write-outcome consultation. Never automatically replay a failed calendar or association write.
- Keep calendar and resources locally available with no remote assets or Internet requests.

## Sequential delivery proposal

After approved requirements and architecture: first implement and verify bounded calendar behavior; hand off its exact verified predecessor under the authorized commit workflow before starting content associations; then integrate the course view. Do not develop these units concurrently. Obtain applicable local commit authorization for new units; the existing reconciliation-only permission is not generalized here.

## Required verification after approval

Calendar boundary and management-gap checks; invalid/cross-year association rejection; role/course and successor restrictions; preservation of due dates, routing and historical access; old content remaining Unassigned; paginated discovery; uncertain-write recovery; responsive and keyboard navigation; local operation without external Internet. Run existing repository-supported frontend/backend checks and report unavailable institutional/device checks honestly.

## Remaining gates

Human acceptance of the expanded calendar/filter prototype and recommended behavior above; institutional calendar confirmation for activation; reviewed backend/frontend contract, calendar revision semantics and architecture. Prototype acceptance already recorded for the original weekly view does not approve these new domain writes.
