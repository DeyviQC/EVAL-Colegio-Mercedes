# Design: Course Materials

## Status and authority

Draft for human review. Six scoped human decisions are accepted as recorded in proposal.md; this draft does not authorize code/migrations/runtime changes. Academic-foundation remains normative and unchanged. No contradiction was found: foundation excludes materials from its own change, while this new change defines additive permissions. No candidate 2e5d053 code, account model, limits or lifecycle is adopted.

Subsequent implementation approval supersedes the draft status. The human selected historical publication-key membership (no historical pre-entry material), successor teacher consultation without authorship change, exactly 25 MiB and no macro-bearing formats/equivalents. Historical alternatives and byte-choice discussions below are preserved as review history, not unresolved blockers or competing rules. Normal technical details/protocol are delegated for implementation and proving tests, without distributed storage/synchronization.

## Minimal bridge

Proposed `retained_identity_profiles`: one restricted visible-name record per retained identity, restrictive identity FK, no authentication credentials or automatic identity deletion. Implement existing AcademicIdentityLabels with local server-owned data. Only synthetic provisioned profiles in development; school-authorized personnel validate institutional names. Provisioning mechanics and display-name length limits require technical review; no runtime account-management UI or public profile endpoint.

Proposed GET /academic/my-courses and GET /academic/my-courses/{assignmentId}: dedicated educational DTO, not student access to general assignment records. Use current authenticated identity and persisted grants; teacher membership comes from own assignment, student operative membership from active enrollment in matching active parent scope. Expose string ID, subject/area label and kind, grade/section labels, period label/state, teacher visible name and assignment state. No password/login/roster/equality key/operational key. Authoritative historical eligibility is separate. An optional bounded current/history view discriminator and keyset cursor must be pinned before code; neither supplies scope authority. Preserve independent assignment IDs for concurrent teachers and replacements.

Operational course listing may include an existing closed assignment where material consultation is actually authorized, but MUST NOT imply publication authority. Planned assignment presentation is read-only for its owner; student planned-course visibility and exact current/history grouping remain review items. A card never computes permissions in React. Missing required names produce explicit unavailable context, not ID-as-name. Avoid one missing profile silently broadening/partially corrupting the projection; select and test the exact error mapping.

## Material model and protected transport

Proposed immutable `course_materials` metadata: ID, original assignment FK, original author retained-identity FK, title, nullable description, original display filename, server-generated storage key, validated format/MIME, byte size, integrity hash, publication timestamp and successful publication operational key. Scope derives from retained assignment; do not accept copied client scope. No editing/version/review/deletion fields masquerading as implemented workflows. Any duplicated accepted scope must be justified as retained context and consistency-tested before schema approval.

Suggested endpoints: GET /academic/my-courses/{id}/materials, POST /academic/my-courses/{id}/materials (multipart), GET /academic/materials/{id}, GET /academic/materials/{id}/file. Reuse local session, origin, CSRF for writes, no-store where appropriate, sanitized transport errors and canonical string locators. Confirm response/error contracts before implementation. Open PDF/images only through protected delivery; download Office files rather than assume browser rendering. Delivery disposition, filename encoding, MIME/nosniff and range behavior are technical review items. Storage is outside the public root; URLs never reveal physical paths.

Multipart accepts title, optional description and one file; no author/teacher/aula/period override. Enforce request and actual stream limits, filename safety, extension/content consistency and supported binary/container signatures. DOC/XLS/PPT legacy compound formats and zipped OOXML need explicit safe validators: do not equate any ZIP with DOCX or claim malware scanning without a selected tool. No extraction/conversion/remote antivirus dependency is inferred. Macro-bearing Office containers and active document content require an explicit acceptance/security policy. Bound metadata lengths before code.

Initial configurable limit: human approved 25 MB. Recommended byte representation is 25 * 1024 * 1024 with the UI labeled 25 MiB; alternative decimal 25,000,000 bytes retains MB. This technical convention is pending, not silently selected. PHP/proxy/upload caps must agree with the approved choice and multipart overhead. Supported file types do not imply native Office preview on tablets.

## Publication transaction and recovery proposal

Stage the validated upload outside public storage before acquiring academic locks; never hold the global guard while receiving network bytes. Compute size/hash there. In the guarded transaction, refresh credentials, owned active assignment and active period, allocate the approved operational key and retain original metadata. The filesystem cannot join MySQL's atomic commit.

Recommended protocol for review: finalize the validated file under a unique server-owned immutable key before committing metadata; only committed metadata plus a verified final file allows visibility. On confirmed rollback remove the owned unreferenced file; on uncertain commit retain it for reconciliation rather than deleting a potentially committed resource. Failure before finalization yields no publication. Crash between file finalization and metadata commit can leave a private orphan; a bounded reconciliation mechanism verifies DB references before cleanup. No external side effect or retry is assumed safe merely because SQL rolled back.

The existing foundation transaction adapter/ledger accepts specified resource kinds; inspect extension points before selecting additive material lock/event handling. Do not insert material events into an incompatible typed ledger or weaken canonical guard ordering. Storage finalization is a new reviewed side-effect boundary; code must not silently violate foundation's existing no-external-effects transaction assumptions. Final protocol, acknowledgement uncertainty correlation, orphan retention and reconciliation command are blocking technical review items. Automatic request replay remains forbidden; no general idempotency framework is inferred.

## Historical eligibility: blocking clarification

Current matching enrollment is sufficient only for the approved operative material policy, not historical proof. Existing stable half-open operational keys provide ordering; dates/timestamps do not grant membership. A closed parent switches material access to the historical path even if an enrollment child remains active.

Unresolved case: material published before the student joined, but available during their enrollment. Publication-key-in-enrollment alone would exclude it; overlap of availability and membership could include it. Another case is a student joining after the original assignment closed but before period closure. The human's "corresponded while belonging" rule must be mapped explicitly rather than inferred.

Recommended option for review: derive retained eligibility from overlap of enrollment authority with a reviewed material-course consultation interval, plus same period/grade/section; publication begins availability, assignment/period closures bound operative access without deleting prior eligibility. Advantage: represents material encountered during membership, including pre-existing resources. Disadvantage: requires explicit interval rules and closure events; successor teacher's consultation of predecessor materials still needs a rule. Alternative: publication-time membership snapshot. Advantage: simple evidence. Disadvantage: can exclude pre-existing resources a student legitimately used. Do not implement either until the human confirms the intended edge cases and the technical mapping.

Explicitly approved cases remain fixed: material corresponding during former membership remains historically readable; subsequent old-aula publications do not; replacement preserves original authorship; closure prevents publication and preserves authorized history. Historical course discovery must return only contexts with a proved eligibility relationship. Do not grant successor authorship or unrestricted old-aula browsing.

## Local UI and verification

Use the preliminarily accepted educational structure: teacher/student My Courses cards, course header/context and Materials panel. Actual data only; no synthetic fixture becomes an authority source. Teacher: title/description/file form, busy/error/uncertain states and confirmed refresh. Student: protected list/open/download. Teacher publication controls appear only as server-derived affordances; server still validates every request. No supervision tab presented as functional. Accessibility includes labeled upload, keyboard navigation, focus/error feedback and tablet layouts.

Prove MySQL/profile retention and authority, course isolation, supported/invalid/oversized files, traversal/forged headers, byte/hash consistency, filesystem/DB/commit failures, concurrent closure/replacement/transfer, historical edge cases after selection, and real two-role browser flow. Add isolated local-storage harness and measure publication bursts only after choosing material-relevant limits; foundation load thresholds are still pending. Existing regressions remain required; optional school LAN, tablet and Office download tests need physical evidence. Harness SPKI exception is not institutional TLS acceptance.

## Remaining decisions before implementation

1. Historical pre-existing-file eligibility, closed-assignment consultation for later arrivals, and successor teacher access to predecessor material (not authorship).
2. Exact identity/course DTO provisioning, pagination and missing-name behavior; title/description/name lengths.
3. MB/MiB convention; content validators and macro/active-content policy; response dispositions/ranges.
4. Guard/ledger additive integration, file-finalization/reconciliation protocol, orphan retention and uncertain acknowledgement mapping.
5. Unit estimates and applicable review-budget authorization. No deployment/IIS/Apache or remote integration decision is selected here.
