# Material Maintenance

## Proposed requirements

- EVAL SHALL allow only the original material author with an active original assignment in an active period to edit metadata, withdraw or restore. Teacher replacement SHALL NOT transfer authorship or write authority for predecessor materials.
- Original publication/file/context SHALL remain immutable. Maintenance SHALL append retained actor/time/revision evidence and reject stale expected revisions without mutations.
- Withdrawal SHALL deny all student listing/search/open/download of that material, including historical access, while retaining authorized teacher consultation and stored evidence. Explicit authorized restoration SHALL preserve original eligibility.
- Metadata changes after a historical student's enrollment ends SHALL NOT appear in that student's consultation or search. Current students SHALL receive currently eligible metadata.
- Edit/withdraw/restore SHALL require CSRF, server reauthorization and confirmed acknowledgement; uncertain writes SHALL NOT replay automatically.

## Acceptance scenarios

1. An authorized original teacher edits title/description; an eligible current student sees the revision and downloads identical original bytes.
2. A transferred student retains only previously authorized metadata and cannot discover a post-transfer title through search.
3. A withdrawal removes the material from the student list and Library and denies direct file access. The teacher still sees retained material/revisions.
4. Restoration makes the material available only through its original authorization, without new author/publication context.
5. An unrelated teacher, successor, student, director or vice principal cannot mutate this first-unit material lifecycle.
6. Closed parent/assignment and stale expected revision reject mutations; injected persistence failure leaves no partial revision/pointer/audit state.

Supervision is a subsequent separately approved capability; it does not require pre-publication approval by implication.
