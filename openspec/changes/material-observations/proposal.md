# Private Material Observations Proposal

Date: 2026-10-08. Predecessor: ab2a020, completed material maintenance. Status: the human approved these concrete requirements and screen mapping through Continue; implemented and verified locally; see docs/material-observations-verification.md. Existing openspec/config.yaml assigns material review and private correction observations to the vice principal.

## Observable next unit

The vice principal opens Material supervision, selects an assignment/course, consults its material and exact revision, and records a private observation. The original teacher consults it, responds and, when appropriate, edits the material through already approved maintenance. The vice principal explicitly marks the response as reviewed. Students never receive observation content or status. Publication remains direct, with no mandatory prior approval.

## Concrete proposed rules

- Authorized vice principals may discover academic assignments for the purpose of reviewing their materials, including retained/withdrawn materials. This adds purpose-bound metadata/file access, not enrollment rosters, submissions, grade editing, general account administration or material maintenance authority. Director does not gain private-observation access implicitly.
- A new observation requires an active period and original assignment and references the exact current material revision (or original publication when no revision exists). It records the original immutable material/author/assignment plus creating vice principal. A stale observed revision is rejected.
- Recipient is derived automatically from the original material author. No client-selected teacher. A replacement teacher cannot read/reply to predecessor private observations; original teacher and authorized vice principals retain historical reading.
- Initial text and teacher reply are required nonblank plain text, at most 5,000 Unicode characters each. No attachment, external message, HTML interpretation or public comment.
- Workflow: Observada -> Respondida -> Revisada. One teacher reply in this first unit; retained correction chains/multiple replies are deferred. Revisada means explicit vice-principal review, not material publication approval or an official academic grade.
- Original teacher may respond while the original assignment and period are active. Responses can explain or identify an existing newer metadata revision; actual editing remains a separate authorized maintenance command. A reply does not automatically change the material.
- Vice-principal review completion also requires active original assignment/period. Replacement or closure makes pending observations read-only historical records; no automatic re-routing, closure, successor reply or authority transfer.
- New observation, reply and review require CSRF, current credentials/role revalidation and expected state/revision. Append retained actor/time evidence atomically under the existing write guard. Uncertain outcomes require matching consultation before explicit manual review; never automatic replay.
- Private lists use bounded pages of at most 50 records. Students, unrelated teachers and director cannot obtain observation bodies through identifiers, material lists, Library, file metadata or generic notifications. No observation notifications in this unit.

## Boundaries

File replacement, observation editing/deletion, multiple reply chains, successor routing, director intervention, automatic moderation and institution-wide approval gates remain separately scoped. This unit does not change student material eligibility or withdrawal behavior. Institutional backup/LAN/load acceptance remains open.
