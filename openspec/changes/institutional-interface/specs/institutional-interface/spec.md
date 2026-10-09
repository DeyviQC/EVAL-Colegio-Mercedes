# Institutional Interface Requirements

## ADDED Requirements

### Requirement: Local institutional identity
EVAL MUST display the supplied school crest using a local asset, native fonts and inline SVG icons without external Internet requests or new runtime dependencies.

#### Scenario: Open institutional access
- GIVEN a local EVAL origin with its server available
- WHEN a user opens access
- THEN the crest and split access form render with the existing credential, CSRF and session behavior.

### Requirement: Accessible responsive navigation
EVAL MUST retain every authorized destination, expose accessible names when navigation is collapsed, support narrow-width section disclosure and provide controls of at least 44 CSS pixels height. Motion MUST use CSS and respect reduced motion.

#### Scenario: Navigate as an authenticated user
- GIVEN confirmed navigation and own-account data
- WHEN a user collapses or opens navigation
- THEN destination accessibility and account logout remain available without granting another role.

### Requirement: Actual calendar presentation
EVAL MUST derive period summaries and calendar cards from existing confirmed responses, preserve read/write restrictions and expose publication and retained revision workflows.

#### Scenario: Consult a published calendar
- GIVEN an authorized management account and a selected period
- WHEN the current calendar is consulted
- THEN blocks and weeks show stored dates and source while draft/edit/publication validation remains unchanged.
