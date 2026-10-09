# Material File Replacement Design

## One revision boundary

Extend the retained material revision operation set with file replacement through a forward migration; preserve migrations 000015 and 000016 as historical artifacts. A file replacement revision keeps current title/description and available state, references its immediate predecessor, and owns one immutable file-version record. Metadata/state changes continue to use the same revision pointer. Resolve the attachment for a selected material revision using the latest file-replacement revision at or before that revision, otherwise the original publication. This avoids a second independently advancing current-file pointer and preserves observation revision bindings.

File-version evidence includes original material, owning replacement revision, validated safe file metadata, private storage reference and integrity digest. Retain foreign keys, original-author/context checks, uniqueness, immutable update/delete guards and narrow runtime grants. Do not rewrite original material rows or backfill existing publication contents.

## Storage and transaction

Reuse LocalMaterialStorage and MaterialFileValidator after inspecting their existing lifecycle. Validate and finalize a private candidate file, retain its storage lock while the database command runs, and atomically insert the material revision/version and advance the existing pointer under AcademicWriteTransaction. A known rollback may remove only its unreferenced candidate under the owned storage lock. An uncertain database outcome must retain candidate bytes for resolution; never delete a possibly referenced file. Do not remove old committed files.

Check stale expected revision, original teacher, live credentials, parent state and byte-identical current digest before committing. Replacement, metadata edit, withdrawal and observation commands share revision conflict behavior. Verify foreign-key/trigger ordering before selecting final migration SQL. The prototype-approved scope does not authorize production filesystem maintenance.

## Authorized projection and file selection

CourseMaterials must select visible metadata and file evidence from the same actor-visible revision boundary. Preserve existing original publication eligibility. Historical student selection uses retained enrollment operation windows; Library searches only the selected visible filename/title/description. Explicit file-version reads independently authorize both original material and requested version. Withdrawal blocks all student versions. Vice-principal revision/file reads stay under supervision routes; predecessor private observation bodies remain unavailable to successors.

## Verification plan

Real MySQL checks for byte retention, hashes, unchanged original publication, invalid types/size/identical content, narrow grants, stale expected revision, revoked credentials, replacement/closed parent denial, rollback at revision/version/pointer stages, one-winner concurrent replacements, historical windows and withdrawal. Real browser teacher replacement, current/historical student downloads and Library privacy, protected supervisor observed-file download, successor read-only behavior, version pagination and committed lost-response recovery. Run supported U11, frontend and material maintenance/observation/Library regressions as justified. Report untested power-loss, large-file/load and school deployment separately.
