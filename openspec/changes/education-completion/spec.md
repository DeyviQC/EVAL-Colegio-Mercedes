# Educational completion

This additive module restores the previously user-requested features in Laravel/MySQL/React after academic-foundation migration. It does not broaden the vice-principal academic role or silently rewrite the original specification's excluded-module scope.

## Required behavior

- Teacher publishes material directly into an owned classroom assignment. No approval state or approval endpoint exists.
- Students see only their currently enrolled classroom resources; files require authenticated scope checks.
- Director may remove material, with audit retention and physical attachment removal after commit.
- Student sends text and/or a file; original assignment and accepted-under enrollment are immutable. Ungraded revisions are allowed only while that enrollment remains active and the task is open.
- Only the original teacher may grade a routed submission. Replacement does not transfer ownership. Feedback and AD/A/B/C grades remain visible to the owning student after transfer.
- Director creates usable accounts and student enrollment atomically, edits account details, resets passwords and deactivates sessions without deleting academic identities.
- Notifications are private to their recipient. Visible pages refresh local authoritative read data every fifteen seconds; writes are never automatically retried.
- Due dates are optional; task closure is explicit and blocks both acceptance paths. Assignment closure does not automatically cancel existing work.
- Closed academic periods are retained and read-only. Immediate transfers/replacements and exact name equality are used; speculative scheduling and retroactive edits are not implemented.

## Verification

See docs/academic-foundation/acceptance.md for executed commands and evidence. Runtime database role cannot modify/delete the foundational lifecycle ledger. Additional educational audit events retain actor and resource context.
