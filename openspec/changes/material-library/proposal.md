# Material Library Proposal

Date: 2026-10-08. Status: human approved these concrete rules through Continue; implemented and locally verified; institutional acceptance remains open. The human requested continuing the accepted Library/search functionality; the approved scope authorizes this bounded implementation.

## Observable result

Adapt the accepted collaborator Library screen from local commit 2e5d0532840c370aa013655ce65410a3a494e73f. It uses the same course material collection as Materials. Teacher/student choose a server-authorized course, enter a search term and consult protected material results with author, filename, publication date and existing open/download links. Preserve teacher publication through the existing course/material workflow.

## Bounded decisions for approval

- Library searches existing authorized course materials only. No new institution-wide collection or access role.
- Require a course selection. Search all eligible materials in that selected course and its already permitted predecessor lineage, rather than only browser-loaded records.
- Search title, optional description and filename with a literal substring. Trim the term; use at most 100 Unicode characters. Empty term lists eligible materials. Percent and underscore are literal text, not wildcard authority.
- Search uses an explicit case-insensitive, accent-sensitive comparison collation over the verified material fields; do not promise linguistic accent normalization or file-content extraction.
- Preserve the existing CourseMaterials eligibility rules for current students, historical publication windows, original teachers and authorized successor teachers. A search match never grants file authority.
- Results contain at most 50 rows in ascending material ID order, with a cursor tied to the selected course/term by the client. Query changes reset pagination; explicit searches and next/start controls replace the current page.
- Errors remain distinguishable from an empty result. Search performs no academic writes, backfill, upload-copying, external calls or automatic polling.

## Outside this unit

Institution-wide browse-all, new categories/tags, extracted document contents, material editing/deletion, supervision, export and indexing/load guarantees remain separately scoped. No new schema or grants are planned.
