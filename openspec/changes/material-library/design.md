# Material Library Design

## Accepted screen mapping

The collaborator EducationPanel maps both Materials and Library to the material panel. Keep that role-specific Library navigation and course cards; add an explicit labeled search form, result count for the visible page and protected open/download actions. Publication continues through the approved material workflow. Do not introduce competing material records.

## Backend composition

Add a GET-only course-scoped library adapter inside MaterialController and its existing authenticated composition. Accept only q and after query fields, no request body/files or client role/identity filters. Verify course discovery, live credentials and material eligibility through existing services. Use bound query parameters and escaped literal LIKE text under the actual verified column collation. Apply scope and search before candidate scanning; test eligibility on each candidate before projection. Fill pages using bounded batches until 51 eligible matches or exhaustion; return 50 and use the last visible ID as continuation so eligible lookahead is not skipped. Retain no-store responses. File download independently rechecks existing eligibility.

No schema migration or grant expansion. Refactor only where necessary to reuse the existing lineage, eligibility and safe material projection. Missing files/names fail without returning storage locators or fabricated rows.

## Client

Typed exact material projections; canonical decimal cursor; encode query text as a URL parameter. Course/term changes invalidate older requests and pagination. Successful search supports explicit retry and start/next pages. No mutation replay.

## Verification

Verify search across more than 50 eligible rows, literal percent/underscore/backslash, title/description/filename matches, empty term, max-length rejection, historical access and original authorship, role denial, unrelated-course/file denial, revoked sessions, and unchanged academic write ordinal. Browser coverage must confirm teacher/student Library navigation, search/reset/paging, downloads and error recovery. Synthetic fixtures are named development records, not institutional resources.

Verified runtime mapping: title/description/filename use utf8mb4_0900_as_cs. Search explicitly applies utf8mb4_0900_as_ci to the comparison only, preserving stored values and accent-sensitive comparison while ignoring case. No schema/collation migration.
