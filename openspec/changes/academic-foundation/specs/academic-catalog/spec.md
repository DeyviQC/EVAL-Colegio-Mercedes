# Academic Catalog Specification

## Purpose

Define the basic catalog of subjects or areas, grades, and sections used by EVAL's academic relationships.

## Requirements

### Requirement: Stable catalog identities

EVAL MUST give each subject or area, grade, and section a stable identity and a human-readable name. Catalog identity MUST remain unchanged when a display name is corrected. The visible user label MUST remain separate from the normalized uniqueness key and MUST be preserved except for a future precisely defined whitespace-display policy. Normalizing a key MUST NOT rewrite the visible label or introduce historical label snapshots.

#### Scenario: Create a catalog entry

- GIVEN an authorized director/administrator provides a valid entry type and a name that satisfies active uniqueness under the approved comparison policy
- WHEN the catalog entry is created
- THEN EVAL MUST assign it a stable identity
- AND EVAL MUST retain its entry type and name
- AND EVAL MUST store the visible label separately from the comparison key

#### Scenario: Rename a catalog entry

- GIVEN a catalog entry is referenced by historical academic records
- WHEN an authorized director/administrator corrects its display name
- THEN EVAL MUST preserve the entry identity
- AND EVAL MUST preserve all existing references
- AND EVAL MUST validate any active-name collision using the approved comparison policy without substituting the key for the visible label

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

EVAL MUST prevent duplicate active instructional-entry names within the same classification, duplicate active grade names, and duplicate active section names within the same grade. The same section name MAY be used in different grades. Comparison MUST ignore case and outer whitespace and use NFC Unicode normalization, while distinguishing accents. These rules MUST apply to creation, active rename, and reactivation, without extending uniqueness to inactive entries. Exact trim/case mechanics remain technical decisions; MySQL keys and comparison MUST reproduce the product policy rather than define it.

#### Scenario: Reject a duplicate active section within a grade

- GIVEN an active section named `A` already belongs to a grade
- WHEN another active section named ` a ` is proposed for the same grade
- THEN EVAL MUST reject the duplicate

#### Scenario: Allow the same section name in another grade

- GIVEN an active section named `A` belongs to one grade
- WHEN an active section named ` a ` is created for a different grade
- THEN EVAL MUST permit the creation

#### Scenario: Compare equivalent instructional names within classification

- GIVEN an active subject named `Álgebra` exists
- WHEN another active subject named ` áLGEBRA ` is proposed, with the accent encoded as U+0061 followed by U+0301
- THEN EVAL MUST reject the same-subject collision after outer trim, case-insensitive comparison, and NFC normalization
- AND EVAL MUST permit an otherwise valid area with that equivalent name because area is a separate classification

#### Scenario: Reject equivalent active grade names

- GIVEN an active grade named `Grade 1` exists
- WHEN an active grade named ` grade 1 ` is proposed
- THEN EVAL MUST reject the duplicate

#### Scenario: Distinguish accented catalog names

- GIVEN an active subject named `Álgebra` exists
- WHEN an otherwise valid active subject named `Algebra` is proposed
- THEN EVAL MUST permit it because accents remain distinguished

#### Scenario: Reject an active rename collision

- GIVEN two active entries share a uniqueness scope
- WHEN one is renamed to a name equivalent to the other's under the approved comparison policy
- THEN EVAL MUST reject the rename without changing either identity, label, or references

#### Scenario: Reject a reactivation collision

- GIVEN an inactive entry has a name equivalent to an active entry in the same uniqueness scope
- WHEN reactivation is requested
- THEN EVAL MUST reject the reactivation without changing the retained entry or references

#### Scenario: Keep the visible label separate from name equality

- GIVEN an otherwise valid catalog entry uses a mixed-case or canonically decomposed visible label
- WHEN EVAL derives its comparison key
- THEN EVAL MUST preserve the visible label rather than replacing it with the normalized key
- AND any later whitespace-display policy MUST be defined precisely before changing whitespace presentation

### Requirement: Local catalog availability

EVAL MUST make the catalog relationships needed for essential student visibility, authorization, and routing available on the school-hosted server without external Internet while local infrastructure and power are available.

#### Scenario: Resolve academic scope without external Internet

- GIVEN the local server, LAN/WLAN, and power are available
- AND external Internet is unavailable
- WHEN EVAL resolves the catalog entries referenced by an enrollment or teaching assignment
- THEN EVAL MUST resolve them from local authoritative data
