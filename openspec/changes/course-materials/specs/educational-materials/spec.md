# Educational Materials Specification

## Purpose

Enable direct authorized publication and protected local consultation of retained course materials. This MVP is additive to academic-foundation, not a full materials lifecycle or supervision system.

### Requirement: Direct operative publication

An authenticated teacher MUST be permitted to publish directly through an owned active teaching assignment in an active period, subject to current server authorization and input validation. A planned/closed assignment or planned/closed period MUST NOT grant publication authority. Vice Principal approval MUST NOT be required in this MVP. EVAL MUST derive author, assignment and period/grade/section/subject context from server records; conflicting or extra client authority fields MUST be rejected.

#### Scenario: Teacher publishes a material

- GIVEN the teacher owns an operative assignment in an active period
- WHEN the teacher supplies a valid title, optional description and one supported file
- THEN EVAL MUST publish that material with original author/assignment/context
- AND MUST acknowledge success only after metadata and file consistency is established

#### Scenario: Publication denied

- GIVEN an unrelated teacher, non-teacher, closed/planned assignment or non-active period
- WHEN publication is requested
- THEN EVAL MUST deny publication without creating a visible material
- AND MUST NOT authorize from the client-selected aula or author

### Requirement: Minimal material and configurable file policy

A material MUST contain a required title, optional description and exactly one required file. Unit/session association MUST NOT be mandatory. The initial supported formats MUST be PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, JPG/JPEG and PNG. The initial maximum MUST be configurable as 25 MB per file; its exact byte convention is a technical review item. Files MUST reside in local server storage and metadata in the database, not file blobs. Server validation MUST check actual supported content rather than trusting extension/client MIME alone. Unsupported, missing, multiple or oversized files MUST be rejected. Display titles MUST NOT be treated as storage paths or academic catalog equality keys.

#### Scenario: Unsupported or invalid file

- GIVEN an upload with misleading type, unsupported content, no file, multiple files or size above the configured limit
- WHEN the server validates it
- THEN it MUST reject publication without a visible material or publicly accessible attachment

### Requirement: Protected local consultation

EVAL MUST allow an authorized teacher to consult their published materials and an eligible student to consult course materials in their server-derived current scope. Opening/downloading MUST revalidate access to the material and its original course on the server; possession of an ID or storage path MUST NOT authorize access. No external Internet dependency or public storage URL MUST be required. This change MUST NOT grant Director/Admin or Vice Principal material access solely from their foundation role.

#### Scenario: Student consults a course material

- GIVEN server evidence authorizes the student's current course/material access
- WHEN the student lists and opens/downloads the material
- THEN EVAL MUST return only authorized metadata and the corresponding stored bytes
- AND MUST NOT require external Internet

#### Scenario: Direct unauthorized file request

- GIVEN a user lacks current or retained historical material eligibility
- WHEN the user requests a material/file locator directly
- THEN EVAL MUST deny without disclosing private content or server storage paths

### Requirement: Historical access and immutable context

Following a transfer, a student MUST stop receiving subsequent materials of the former aula, retain authorized historical access to materials that corresponded to that aula while they belonged to it, and gain operative access in the successor aula. Existing materials MUST preserve original author/assignment/context after teacher replacement. A successor teacher MUST NOT become historical author by replacement. Closed assignments MUST deny new publication while retaining authorized historical consultation; a closed period MUST permit materials only through authorized historical consultation. No automatic deletion or context rewrite is allowed.

Historical student eligibility MUST exclude materials published before the retained enrollment operational start and after its exclusive operational end, with matching period/grade/section. Original publication K, not wall-clock dates, MUST establish publication-time membership. Current operative access remains separately derived from active enrollment scope. The currently authorized successor teacher MUST be able to consult existing course materials through retained predecessor context without becoming their author. No files or history MUST be deleted to enforce the historical exclusion.

The configured initial maximum MUST be 26,214,400 bytes (25 MiB). DOCM/XLSM/PPTM and equivalent macro-bearing formats MUST NOT be admitted. Content validation MUST reject macros/equivalents even when disguised under an allowed extension. Earlier pending byte convention is superseded by this explicit human technical choice.

#### Scenario: Material published before transfer while student belonged

- GIVEN a material corresponded to the student's aula during their retained enrollment
- WHEN the student transfers out
- THEN EVAL MUST preserve authorized historical consultation with original context
- AND a subsequent publication in the old aula MUST NOT become accessible from that former enrollment

#### Scenario: Replacement and closure

- GIVEN a published material has an original teacher and assignment
- WHEN the teacher is replaced or the assignment/period closes
- THEN EVAL MUST retain the material's author and context
- AND new publication MUST obey the successor's own current authority or be denied after closure
- AND historical file access MUST remain separately authorized

### Requirement: Consistent visibility and no blind replay

EVAL MUST NOT expose published metadata with an absent/incomplete file or expose staged files without authorized metadata. Upload, database and finalization failures MUST have explicit cleanup/recovery behavior. An uncertain successful commit acknowledgement MUST NOT cause an automatic duplicate publication. Concurrent academic closure/transfer/replacement MUST be resolved against refreshed authoritative state using a reviewed protocol consistent with foundation integrity.

#### Scenario: Storage or commit failure

- GIVEN storage finalization, database mutation or acknowledgement fails
- WHEN publication completes or aborts
- THEN EVAL MUST preserve or recover consistency without exposing a broken material
- AND an uncertain outcome MUST return explicit consultation/reconciliation guidance, not automatic replay

### Requirement: Explicit MVP boundary

This MVP MUST NOT implement editing, file replacement, withdrawal/deletion, unit/session hierarchy, material approval workflows, supervision/observations, activities/submissions, grading, library/search or synchronization. Edit/replace/withdraw/delete remain outstanding general product requirements for later work, not permissions granted by this specification.
