# U5 Pending Human Decisions

Date: 2026-10-07. U4 is the next verified predecessor handoff. No U5 implementation or checkbox is introduced here.

## A: Product eligibility for new enrollment under a planned parent

Design's Authorization Model and tasks.md 1.4 explicitly distinguish the approved closed-parent prohibition from the unresolved eligibility for new academic work under a requested planned period. U5 4.1/4.2 creates active enrollments and cannot select that predicate silently. Planned assignment creation remains approved regardless of this separate decision.

| Option | Advantages | Disadvantages / impact |
|---|---|---|
| Recommended for review: create active enrollments only in an active period | Clear current operational boundary; no implicit authority from planned enrollment | Does not allow preparatory enrollment through this active-enrollment command. Requires a scoped approved eligibility clarification in specs/design/tasks before implementation; never extend it into a universal active-parent rule or prohibit approved planned assignment creation |
| Allow active-enrollment creation in planned or active periods | Allows advance preparation using the existing enrollment states | Human must also define the exact authority/access boundary while the parent is planned, consistent with planned not being current. Requires approved clarification and tests; the code cannot decide operational eligibility or invent a new planned enrollment state |

This is a new product selection, not reopening approved mandatory period identity, no enrollment date containment, immediate-only transfer, active-reference checks or closed-parent denial.

## B: Technical mapping for permitted administrative closure dates

Design Effective-Date and Interval Strategy explicitly leaves permitted non-current administrative closure date/key mapping unresolved. U5 4.1/4.2 requires valid residual closure, including under a closed parent, and occupied-history comparisons. The already approved end-date order and no automatic expiry remain unchanged.

Recommended mapping for review: preserve the permitted supplied declared end date as DATE; close operational authority at the actual successful transaction key K, with no conversion of the date into midnight/ordinal and no rewriting of earlier accepted context. Rollback removes K/state/events together. This cleanly preserves actual ordering and declared evidence, but consumers must distinguish declared dates from operational authority. Requires recording the approved technical mapping in design/tasks/evidence and implementing/tests in U5; it does not itself authorize scheduling, retroactive transfer, correction or any additional closure workflow/date permission.

Alternative: defer support for non-current closure dates until separately mapped. This avoids guessing, but leaves U5 incomplete wherever its approved residual-closure scenarios require those dates. It cannot justify marking 4.1/4.2 complete or imposing a current-date-only product rule.

For an approval of the recommended mapping to unblock all affected cases, identify which non-current declared closure dates are permitted (past/future relative to execution, subject to end ≥ start). The mapping never schedules effectiveness: operational closure remains at actual successful K. Any restriction/additional date workflow is a human product decision, not an executor inference.

## Boundary

No change is proposed to the approved no-containment rule, no reopen, half-open key intervals, same-day operation order, immutable scope/identity, retained history or role matrix. U5 remains stopped until its applicable selections are recorded. Other deferred planned-assignment/replacement mappings need not be solved merely for this unit. No automatic request replay, remote Git action or integration of 2e5d053 is needed.

## Subsequent human resolution

The human explicitly approved active-parent enrollment creation, past/current valid administrative closure dates and actual successful K without retroactivity. See docs/u5-human-decisions.md for the exact grouped approval and scope. This historical pending document remains evidence of the options; it is not a parallel normative source. Remaining assignment/other planned-parent decisions are not resolved by that approval.
