# Material Library Verification

Date: 2026-10-08. The human approved the concrete course-scoped Library/search proposal through Continue. The accepted collaborator Library screen at locally available commit 2e5d0532840c370aa013655ce65410a3a494e73f reuses the course material collection.

## Delivered behavior

Teacher/student select an authorized course in Biblioteca and search existing eligible materials by title, description or filename. Empty search lists materials. Trimmed terms allow at most 100 Unicode characters; percent, underscore and the escape character are literal. Search runs against the full server-side course/predecessor material collection, not only browser-loaded rows. Candidate batches are filtered through the existing eligibility policy before projection; pages contain at most 50 eligible rows, with eligible lookahead and the last visible ID as cursor. Changed queries reset pagination. Errors clear stale results and do not appear as a confirmed empty result.

The runtime fields use utf8mb4_0900_as_cs. Search explicitly applies utf8mb4_0900_as_ci to comparison only: case-insensitive, accent-sensitive, without rewriting stored values or altering schema. Parameters are bound and LIKE patterns are escaped. Results use the existing exact material projection; author, original material identity and protected file endpoints remain unchanged. The teacher can enter the existing publication panel and return to Library. No schema migration, new grants, browse-all permission or external service was introduced.

## Executed evidence

- Frontend: 122 tests passed, including three new query encoding, invalid term/cursor and Unicode-limit/denial cases. Application/tooling TypeScript and final Vite build passed.
- Actual MySQL: 43 checks passed using a dedicated 54-material synthetic course. Tests cover 50+4 disjoint pagination, matches beyond the first page, title/description/filename, literal percent/underscore/escape/backslash, trimmed terms, empty/no-match search, SQL-like text, role/scope/inactive denial, over-limit term and malformed cursor, no private storage locator, byte-identical protected file retrieval, retained original author after replacement, retained access after transfer, later-publication exclusion, revoked identity denial, HTTP field validation and actual column collations. Read-only and denied searches preserve material/ledger/ordinal snapshots. Fixtures publish through actual approved commands and use one repeated synthetic PDF payload.
- Chromium: 19 checks passed across four roles, including real Library navigation, 50-row paging, case-insensitive search beyond the first page, literal/filename/no-match search, student publication restriction, protected byte-identical download, HTTP failure and recovery, teacher entry into existing publication UI and 768-pixel overflow check. The student screenshot was visually inspected. The harness initially needed an explicit return to the course selector when reopening the same Library menu; it was corrected without changing authorization.
- Existing U11 regression: 99 tests / 2397 assertions, two optional skips, no failures; this suite includes course-material persistence/policy tests. Existing Material navigation/download browser regression: 14 checks passed. PHP/Node syntax and Git whitespace checks passed.

The initial MySQL search test exposed case-sensitive field collations and the query was corrected to the approved case-insensitive comparison. Three unsuccessful server-fixture attempts and one successful run retained four named synthetic scopes (217 added material records); these are development test records, not institutional resources. BrowserOnly reruns reuse those scopes without creating more accounts/materials.

Final inspected development snapshot: TLS/restricted runtime, 25 tables / 14 migrations, 182 accounts, 11 periods, 65 assignments, 180 enrollments, 220 materials, ordinal 1133. No institutional enrollment or backup durability is implied. Private receipts, uploaded evidence and screenshots remain ignored.

Supported command: backend/scripts/manage-dev.ps1 -Command verify-library. Add -BrowserOnly to reuse existing server fixtures. No initial universal RED or load guarantee is claimed. Changes are recorded as a finished local handoff; no remote Git operation is included.

## Remaining acceptance

The selected course is required; institution-wide search/browse-all, extracted document contents, categories/tags, editing/deletion, supervision and search indexing remain separate capabilities. A dedicated closed-period Library fixture, more than 200 mixed eligible/ineligible candidates, accent comparison browser fixture and simultaneous search/transfer race were not separately exercised. Existing period/history policies remain unchanged. Durable storage, backup/restore, school LAN/outage/load and institutional MVP acceptance remain open.
