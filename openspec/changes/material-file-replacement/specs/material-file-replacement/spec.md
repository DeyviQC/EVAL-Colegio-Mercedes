# Material File Replacement Specification

## Original authority and retention

The system SHALL allow only the original teacher, with live credentials and active original assignment/period, to replace the file of an available material. It SHALL retain material identity, original publication and every committed attachment. Replacement SHALL advance the same guarded revision lineage used by maintenance and observations.

Scenario: two uploads reference the same current revision. Exactly one commits; the other receives a stale revision outcome without advancing a pointer or retaining a falsely committed version.

## Revision-consistent visibility

The system SHALL resolve material metadata, filename and downloaded file from the same authorized revision boundary. Historical students SHALL NOT gain files or filenames from replacements outside their authorized enrollment windows. Student version requests SHALL be independently authorized, and current withdrawal SHALL deny all student versions.

Scenario: a student transfers before a replacement. The original eligible material remains historically readable, while the later replacement filename, content and Library match remain unavailable.

## Private observation preservation

The system SHALL preserve the file associated with an observed or replied-to material revision. Later replacement SHALL NOT rewrite private evidence or automatically close an observation. Existing private-role and original-recipient boundaries SHALL remain effective.

Scenario: a vice principal observes file A and the teacher later replaces it with file B. The observation's protected file link still serves A; the current material serves B to currently eligible actors. An earlier expected revision cannot silently create a new observation or reply against B.

## Recovery and bounded consultation

The system SHALL reject invalid, empty, oversized and byte-identical current attachments, retain old committed files, and use bounded retained-version pages. An unconfirmed write SHALL require matching consultation and explicit review before another attempt, with no automatic replay. Known rollback SHALL NOT leave referenced bytes missing; uncertain outcomes SHALL NOT trigger candidate deletion.
