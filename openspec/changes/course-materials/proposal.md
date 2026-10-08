# Proposal: Course Materials

## Intent

Deliver EVAL's first real educational journey: a teacher opens an authoritative course, publishes a material, and an eligible student opens the course and consults/downloads that material over the school LAN/WLAN without external Internet.

Status: draft for human review, not implementation authorization. The human explicitly approved six materials decisions in this conversation: direct publication, title/optional description/one required file, listed formats and configurable 25 MB limit, retained historical access/authorship, minimal retained-identity display profiles, and publish/consult-only MVP. This record paraphrases that approval without inventing interview provenance or message IDs.

Subsequent human approval: this baseline and implementation are explicitly approved. Historical student access excludes publication before enrollment operational start; the currently authorized successor teacher may consult predecessor materials without changing authorship. Limit is exactly 25 MiB (26,214,400 bytes); macro formats/equivalents are excluded and actual server content validation is required. Choose/prove a simple local MySQL/filesystem protocol. Earlier draft/pending wording is superseded only for these selected decisions; no additional product capability is authorized.

## Scope

### In scope

- Minimal local display-name profile linked to permanent retained identity; synthetic development profiles and institutionally validated production names.
- Server-owned teacher/student My Courses collection and detail projection; retain assignment identity rather than create a competing course domain entity.
- Teacher publication through a currently operative owned assignment, without Vice Principal pre-approval.
- Required title, optional description and exactly one required server-stored file; metadata in MySQL, not file blobs.
- PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, JPG/JPEG and PNG; configurable initial 25 MB maximum per file.
- Authorized material listing and protected open/download; original author, assignment and publication context retained across transfer/replacement/closure.
- Current versus historical consultation, as explicitly approved; technical historical eligibility mapping still requires review.
- Real same-origin teacher/student UI and negative authorization, storage consistency, concurrency and browser evidence.

### Outside this MVP

- Material edit, attachment replacement, withdrawal/deletion: deferred outstanding institutional requirements, not removed from product scope.
- Mandatory units/sessions; their structure remains unresolved.
- Vice Principal supervision/private observations; documented future responsibility, no pre-publication approval implied.
- Director/Admin material intervention or browse-all permission without a separately approved rule.
- Complete activities, submissions, grading AD/A/B/C, library/search, presentation tools, synchronization and full user/account management.
- SIAGIE integration/replacement, QR attendance, APK, remote Git or integration/code copying from 2e5d053.

## Capabilities

New: `course-access` (minimal retained display identity and My Courses projection); `educational-materials` (publication, protected consultation and retained context).

Modified: none. Reference the six academic-foundation specs; do not retrofit them. Their material exclusion limits that change, not the whole product: a separately approved materials capability is additive.

## Evidence and dependencies

Institutional scope: openspec/config.yaml records teacher upload/modify/delete/download, student consultation, local tablets/browser access, and later material-specific private Vice Principal observations. Raw interviews, a standalone ERS and the current sharing procedure were not found in inspected local evidence; no unit/session or approval-state requirement is inferred. The 25 MB limit is a human-selected configurable technical MVP setting, not interview evidence.

Foundation dependency: approved identities, periods/catalog, enrollments, assignments/replacement, operational keys, authorization and transaction protocol. Display-name provider is currently an interface without institutional binding. Existing individual assignment read is not student course-card permission; add a dedicated narrow projection instead of exposing the technical record.

Base: local commit 7ef78d5; existing live Phase A and synthetic visual preview remain separate. Existing foundation gates 1.2-1.8 and 8.2-8.4 are not closed by this draft.

## Success criteria

- Teacher and student obtain actual server-authorized courses and names, without client actor/role/scope authority.
- Teacher publishes a valid supported file; eligible student opens/downloads exactly its bytes without external Internet.
- Unauthorized cross-course access, planned/closed publication and spoofed metadata fail without publication.
- Transfer stops access to subsequent old-aula publications while retaining approved historical access; replacement preserves original author/context.
- Assignment/period closure prevents new publication and retains authorized historical reads.
- Metadata/file failures expose no broken published material; uncertain acknowledgement is never automatically replayed.
- Real browser flow plus regression evidence, and explicit distinction between loopback and unexecuted school-network evidence.

## Risks and rollback

Filesystem is not a MySQL transaction participant; atomic visibility/recovery needs a reviewed protocol. Historical eligibility must not be approximated by current enrollment alone or arbitrary date-to-key conversion. Office/image type validation and protected delivery require review. Disable publication before rollback; preserve published metadata/files/profiles and historical relations. Use forward repair for retained data, never destructive rollback by default.
