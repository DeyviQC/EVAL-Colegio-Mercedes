# Material Maintenance Proposal

Date: 2026-10-08. Status: these concrete rules approved by the human through Continue; implemented and locally verified; institutional acceptance remains open. The human requested continuing the project and prioritizing material maintenance and supervision. Existing specifications deliberately defer this lifecycle; the subsequent approval authorizes these bounded schema/application changes.

## First observable unit

The original teacher edits a material title/description and explicitly withdraws or restores it through the actual Materials panel. Retain immutable original publication, file, author and assignment. All edits/state changes are recorded as new revisions; no direct modification/deletion of the original course_materials row or physical file. Library and protected downloads independently respect withdrawal.

## Proposed rules for approval

1. Only the original author, with a currently active owned original assignment in an active period, may edit/withdraw/restore. Replacement teachers retain already approved consultation but cannot overwrite predecessor materials. Closed periods/assignments remain read-only.
2. First unit edits required title (255 Unicode characters maximum) and optional description (10,000 maximum). File replacement is a subsequent unit with separate revision visibility rules; no silent attachment replacement.
3. Append immutable metadata/state revisions under the existing academic write guard, recording actor, operation key, timestamp and prior revision. A stale expected revision is rejected; unknown outcomes require matching consultation before manual review, never automatic replay.
4. Withdrawal hides the material from student lists/search and denies student direct open/download, including historical student access. It retains bytes and revision evidence for authorized teachers. The original teacher can restore access while still authorized. Restore does not change publication-time eligibility or original authorship.
5. Former students must not receive title/description edits made after their enrollment ends: retain the latest revision in their authorized historical enrollment window. Current eligible students receive the current revision. Existing pre-entry and transfer eligibility restrictions remain in force; revisions do not create a new course authority.
6. Teacher confirmation clearly explains withdrawal and restoration. The selected original-author/active-assignment rule and global student withdrawal rule are product decisions proposed here, not already implied by existing specs.

## Next sequential unit: supervision

After maintenance is verified and committed, implement vice-principal material review and private material-specific observations. Proposed boundary: vice principal consults material context/files for assignment review without a student roster or grading authority; observes a specific revision; observations are visible only to authorized vice principals and the original teacher, never students. This does not introduce mandatory approval before publication. Concrete observation status, correction and replacement-teacher routing require a separate detailed specification/approval; no supervision code is authorized by this first proposal.

## Outside first unit

Physical deletion, file replacement, institutional browse-all for director, successor-teacher editing, private observations implementation, notifications for edits, moderation/publication approval, and mass maintenance remain separate. No institutional backup, deployment or global acceptance gate is closed.
