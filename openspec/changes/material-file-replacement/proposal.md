# Retained Material File Replacement

Date: 2026-10-08. Predecessor: fd8e595, verified private material observations. Status: the human approved these concrete rules, design and screen mapping through Continue before implementation. Implemented and verified locally; see docs/material-file-replacement-verification.md.

## Proposed bounded behavior

The original teacher can replace the attachment of an available material while its original assignment and period are active. The material keeps its ID, original publication, author and assignment. A successful replacement appends a retained file version and a new material revision. It does not delete or overwrite original bytes, change the title/description, republish into a different course or change an observation automatically.

- Use existing supported file types, MIME/content validation and the existing 25 MiB limit. Reject empty, invalid and byte-identical current files. No ZIP, external URL, arbitrary storage path or client-selected author.
- Require current session, CSRF and expected material revision. Revalidate original authority and active context inside the shared write guard. Reject stale uploads without advancing any retained pointer.
- Current eligible students receive the latest authorized file through the existing material links. Historical students receive the latest file version within their authorized enrollment windows, falling back to the original eligible publication. Later replacements and their filenames must not leak through material cards, Library matching or direct version URLs.
- Withdrawal continues to deny all student material/file access, including historical versions. Restoration restores the original access rules, not global access. Already downloaded copies are not revoked.
- Authorized course teachers can consult retained file versions. A successor can consult through existing course lineage but cannot replace predecessor files or read predecessor private observations. Authorized vice principals can review retained versions through purpose-bound supervision. Director receives no new material access.
- Observations and replies retain their exact consulted revision. A protected observed/replied-file link resolves the file at that revision. Replacement makes earlier expected-revision observation creation/reply stale. It neither erases nor automatically resolves existing observations.
- Retained version lists use at most 50 entries per page. Expose safe filename/type/size/time and protected links; never storage paths or internal hashes.
- Unknown replacement outcomes block another replacement until matching material status consultation and explicit manual review. Never automatically resubmit an upload.

## Deferred scope

Physical purge, version deletion, student browse-all version history, rollback-to-old-version commands, bulk replacement, new notification types, multiple attachments per material and changes to submitted work remain outside this unit. Institutional durable storage, backup/restore, school LAN/load and deployment gates remain open.
