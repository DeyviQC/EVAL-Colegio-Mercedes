# Material Library

## Requirements

Library MUST search existing eligible materials in a selected authorized course, including its approved predecessor lineage. It MUST NOT expose another academic scope or create a new browse-all role.

Search MUST use a trimmed literal substring of at most 100 Unicode characters across title, description and filename. Empty terms MUST list eligible materials. Search MUST cover server-side eligible records rather than only a partial browser page.

Each result MUST preserve original material identity/authorship and use independently protected file endpoints. GET queries MUST accept only the declared search/cursor fields and MUST perform no academic mutation.

Pages MUST contain at most 50 eligible material rows, ascending canonical decimal IDs. Changing course or search term MUST reset the cursor and invalidate stale responses. Failure MUST NOT appear as a confirmed empty result.

### Acceptance scenarios

- An eligible student finds an existing material by title, description or filename and downloads identical bytes through the protected endpoint.
- An unrelated student or administrative role cannot use Library to obtain course materials or file authority.
- A literal percent or underscore matches that text rather than all rows.
- A match after the first 50 records is discoverable without prior browser loading; pages do not skip eligible matches.
- Transfer/replacement does not rewrite original authorship or loosen existing historical eligibility.
- A failed consultation clears stale results, and explicit retry can recover without any write or replay.
