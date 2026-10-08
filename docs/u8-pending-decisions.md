# U8 Pending Human Replacement Mapping

Date: 2026-10-07. U7 is implemented and verified; U8 application code has not started. This document offers options, not additional normative rules. OpenSpec remains authoritative. Assignment closure approval does not automatically select a replacement date mapping.

Existing approved rules: replacement requires active requested parent, management authority Director/Admin or Vice Principal, immutable prior teacher/scope/identity, atomic prior closure and distinct active successor/lineage at one actual K, contained valid dates, all scoped conflicts including planned/history, and no historical work rerouting. Scheduled replacement, guessed future K, backdating operational authority and automatic state changes are not authorized.

Remaining human question (design Effective-Date and Interval Strategy/Open Questions; tasks 1.2 and 5.3/5.4): which declared DATE may immediate replacement accept, particularly when an active period is not calendar-current? Dates cannot be converted to midnight keys or silently inferred from enrollment/administrative closure rules.

## Option 1: School-local today only (recommended narrow initial scope)

Immediate replacement uses server America/Lima today as both prior declared end and successor declared start; requires that today is within the period and not before prior declared start. Old operational end and successor operational start share actual successful K. Reject any supplied different DATE without mutation. A state-active period whose calendar excludes today cannot accept replacement in this unit; standalone approved cleanup remains available.

Advantages: least ambiguous declaration, no historical-date replacement workflow, easy human explanation. Disadvantages: does not support replacement in state-active calendar-past/future periods; management must use permitted cleanup or wait for a separately approved capability. Impact: scoped human requirement/design/task clarification before U8 tests/handler, explicit mutation-free non-current denials. No gate is completed by selecting it.

## Option 2: Valid contained past/current declared date, immediate actual K

Accept a human-declared DATE with prior declared start <= DATE <= server America/Lima today, contained within the period. Preserve it as prior end and successor start, while both operational boundaries are actual successful K, not the declared day's beginning. An active calendar-past period can accept replacement with a valid contained past DATE. A future DATE remains rejected. Preserve history and accepted original ownership; past declaration never retroactively grants/revokes actual authority.

Advantages: supports immediate corrections of declared replacement date and state-active calendar-past administration without inventing a future scheduler. Disadvantages: declaration differs from real operational moment and needs explicit UI explanation/audit, more validation examples. Impact: explicit human amendment of assignment replacement requirements/design/tasks, DATE validation and actual-K pair tests before code, no migration/ledger replay or reassignment of existing work. This must be approved as replacement-specific policy, not copied from administrative closure.

Neither option authorizes U9 or deferred capabilities. Upon selection, record approval before code, implement only bounded U8, prove conflicts/lineage/rollback/races, then hand off a verified local commit. Until selection, tasks 5.3/5.4 and relevant gate questions remain pending.
