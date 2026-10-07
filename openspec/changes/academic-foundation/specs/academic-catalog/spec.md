# Academic Catalog Specification

## Purpose

Define the basic catalog of subjects or areas, grades, and sections used by EVAL's academic relationships.

## Requirements

### Requirement: Stable catalog identities

EVAL MUST give each subject or area, grade, and section a stable identity and a human-readable name. Catalog identity MUST remain unchanged when a display name is corrected.

#### Scenario: Create a catalog entry

- GIVEN an authorized director/administrator provides a valid entry type and name
- WHEN the catalog entry is created
- THEN EVAL MUST assign it a stable identity
- AND EVAL MUST retain its entry type and name

#### Scenario: Rename a catalog entry

- GIVEN a catalog entry is referenced by historical academic records
- WHEN an authorized director/administrator corrects its display name
- THEN EVAL MUST preserve the entry identity
- AND EVAL MUST preserve all existing references

### Requirement: Subject and area semantics

Each instructional catalog entry MUST be classified as exactly one of `subject` or `area`. EVAL MUST NOT treat the classification as interchangeable after the entry is referenced by an enrollment-visible scope or teaching assignment.

#### Scenario: Classify an instructional entry

- GIVEN an authorized director/administrator creates an instructional catalog entry
- WHEN the entry is saved as a subject or an area
- THEN EVAL MUST retain exactly the selected classification

#### Scenario: Reject ambiguous classification

- GIVEN an instructional catalog entry is classified as both a subject and an area or as neither
- WHEN creation is requested
- THEN EVAL MUST reject the request

#### Scenario: Preserve referenced classification

- GIVEN an instructional entry is referenced by a teaching assignment
- WHEN a request attempts to change its classification
- THEN EVAL MUST reject the change
- AND EVAL MUST preserve the historical meaning of the assignment

### Requirement: Grade and section structure

Each section MUST belong to exactly one grade. EVAL MUST require enrollments and teaching assignments to use a section that belongs to their selected grade.

#### Scenario: Use a section in its grade

- GIVEN a section belongs to a grade
- WHEN an enrollment or teaching assignment uses that grade and section
- THEN EVAL MUST accept the grade-section relationship as structurally valid

#### Scenario: Reject a mismatched grade and section

- GIVEN a section belongs to a different grade than the one requested
- WHEN an enrollment or teaching assignment is validated
- THEN EVAL MUST reject the mismatched academic scope

### Requirement: Catalog activation lifecycle

Catalog entries MUST be either active or inactive. EVAL MUST permit new enrollments and teaching assignments to reference only active entries, while inactive entries MUST remain available for historical interpretation.

#### Scenario: Use active catalog entries

- GIVEN a subject or area, grade, and section are active
- WHEN a valid enrollment or teaching assignment references them
- THEN EVAL MUST permit those references

#### Scenario: Reject a new reference to an inactive entry

- GIVEN a required catalog entry is inactive
- WHEN creation of an enrollment or teaching assignment references it
- THEN EVAL MUST reject the request

#### Scenario: Retain an inactive entry in history

- GIVEN a catalog entry is referenced by a historical enrollment or teaching assignment
- WHEN the entry is made inactive
- THEN EVAL MUST preserve the entry and its historical references

### Requirement: Catalog uniqueness within type and parent

EVAL MUST prevent duplicate active instructional-entry names within the same classification, duplicate active grade names, and duplicate active section names within the same grade. The same section name MAY be used in different grades.

#### Scenario: Reject a duplicate active section within a grade

- GIVEN an active section named `A` already belongs to a grade
- WHEN another active section named `A` is proposed for the same grade
- THEN EVAL MUST reject the duplicate

#### Scenario: Allow the same section name in another grade

- GIVEN an active section named `A` belongs to one grade
- WHEN an active section named `A` is created for a different grade
- THEN EVAL MUST permit the creation

### Requirement: Local catalog availability

EVAL MUST make the catalog relationships needed for essential student visibility, authorization, and routing available on the school-hosted server without external Internet while local infrastructure and power are available.

#### Scenario: Resolve academic scope without external Internet

- GIVEN the local server, LAN/WLAN, and power are available
- AND external Internet is unavailable
- WHEN EVAL resolves the catalog entries referenced by an enrollment or teaching assignment
- THEN EVAL MUST resolve them from local authoritative data
