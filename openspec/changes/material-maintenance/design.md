# Material Maintenance Design

This is a reviewable design, not executed application/schema code. Preserve original course_materials retention triggers. Add an immutable material revision store and guarded current revision/state reference only after approval. Original publication remains the base revision; existing files/storage keys are reused, not copied or replaced. New revision IDs and expected revision IDs remain canonical decimal strings.

Commands must serialize through AcademicWriteTransaction, revalidate live credentials/original author/owned assignment/active parent, lock the relevant period/identity/assignment/material state, and append a revision plus atomic current-state update and audit evidence. Runtime receives only required revision inserts and controlled pointer updates. No DELETE grant or broad UPDATE on original publications. Inject revision/pointer failures and stale two-session writes during verification.

Queries must apply withdrawal and existing eligibility before safe material projection or file delivery. Current students see current metadata; historical readers select the latest revision compatible with an already authorized enrollment window, falling back to the original publication when permitted. Withdrawal is a current global restriction for student access, not a history deletion. Original/successor teacher consultation remains through existing policies. Library search must search the metadata revision that the actor may actually see, rather than match a newer private revision and disclose its existence through result selection.

The Materials panel gains explicit edit, withdraw/restore confirmation and authorized revision consultation. Server affordances are independent of client role claims. Forms clear only on confirmed writes; stale revisions cause a reconsultation prompt. Consultation and manual review recover uncertain responses without replay.

## Required evidence before handoff

- Existing publication/file bytes remain unchanged through metadata edits/state changes.
- Original-author/assignment/period authority and successor/student/administrator denials.
- Current versus historical revision selection, no post-transfer metadata/search leakage.
- Withdrawal removes student list/search and denies guessed file URLs; authorized teacher retained consultation; restore preserves original identity/eligibility.
- Stale revisions, atomic rollback, duplicate/no-op state transitions and lost-response recovery.
- Actual teacher/student browser edit/withdraw/restore journey and existing material/Library regressions.
- Record realistic fixture counts and remaining backup/LAN/load gates.
