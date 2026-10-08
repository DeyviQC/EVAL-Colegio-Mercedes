# U7 Human Review: Remaining Assignment Decisions

Date: 2026-10-07. U5/U6 are verified; no U7 code or checkbox is introduced here. The enrollment-only approval in 6262d4f does not resolve these assignment cases. This document proposes options; OpenSpec remains normative and none is selected until human approval.

## A. Product eligibility under a planned parent

Teaching-assignments Stable assignment identity and scope, design Authorization Model / Open Questions, and tasks 1.4/5.1/5.2 explicitly leave assignment activation/new operative work under planned parents undecided.

Recommended: require an active requested parent for assignment activation and new operative work (replacement successors, activities and submissions), while continuing to permit valid planned-assignment creation under planned or active parents. Keep historical reads and valid residual cleanup under closed parents, and global catalog management, unchanged.

Advantages: one clear operative boundary; no implicit authority from future/planned parent; aligns new-enrollment eligibility. Disadvantage: staff cannot activate/use an assignment for operative preparation before period activation. Alternative: allow activation/selected work under planned parents, but the human must enumerate those permitted operations; planned never becomes current merely from child activation and prior active-parent enrollment creation remains unchanged.

Impact: an approved scoped requirement clarification across affected specs/design/tasks, then U7 activation tests/commands and later affected writers/policies. This does not authorize deferred processing or convert every read/cleanup/planning operation into active-parent-only access.

## B. Planned/shared-day comparison and activation boundary

Design Effective-Date and Interval Strategy and tasks 1.2/5.1/5.2 require review of planned boundaries against actual operational history. Recommended concrete model for review:

1. Planned rows retain declared dates and null operational start/end. Compare planned reservations by inclusive declared dates (exclusive end = supplied end + one day; open end horizon = period end + one day), within the unchanged teacher/period/entry/grade/section scope. Disjoint reservations are allowed; overlapping planned reservations are rejected. Distinct teachers remain independent.
2. Planning against an active row uses its declared planning range; a disjoint future plan grants no current authority. Activation must always recheck actual occupied operational intervals, so a still-open active interval blocks duplicate activation even when declared ranges were disjoint.
3. Retained closed operational history with end <= prospective current K is wholly before any new manual activation. Do not reject a later actual same-day activation merely because declared dates share that day. Check the actual keys across history; do not filter solely by current state, map dates to midnight, backdate authority or reserve a guessed future ordinal.
4. Activation is explicit/manual at actual successful K, preserves declared dates, and requires server school-local today >= declared start. This last admission condition is an explicit proposed product/date decision, not an inferred technical rule. A future start remains planned until that date and a manual activation; no scheduled activation/auto-expiry service is added. All supplied dates remain contained in the period; this does not impose automatic calendar-driven state changes.

Advantages: retains null planned keys, permits real same-day non-overlap, keeps future plans nonoperative and revalidates actual authority at activation. Disadvantages: two comparison representations and explicit manual activation/closure; disjoint declared plans do not promise successful activation while an actual active interval remains open. Future same-day overlapping reservations cannot express sub-day scheduling through this date-only capability; no new scheduling workflow is assumed. Scope/identity/history remains retained.

Alternative: keep the ambiguous cases pending and defer U7 until a separately reviewed mapping exists. Do not hide the gap with blanket same-day denial, fake midnight keys or completed checkboxes. Approval requires the full rules/examples above, including the proposed activation date admission condition; partial approval leaves affected cases pending.

Impact: approved design/spec/task clarification where needed, planned/conflict query and activation validators, tests for disjoint plans, same-day actual closure followed by activation, duplicate plans, active-open conflicts and future-start denial. No future operation key or lifecycle ledger replay is proposed.

## C. Assignment administrative closure

Teaching-assignments Assignment lifecycle and effective interval and design temporal strategy require valid residual cleanup with unchanged period containment, including execution after the period's calendar end. Enrollment's new mapping applies only to enrollment and cannot be copied without review.

Recommended for review: Director/Administrator or Vice Principal may close a residual active assignment with a valid past/current declared end (end >= declared start, within the period, end <= America/Lima server today). Preserve that contained DATE and end operational authority at actual successful K, even if execution occurs after period calendar end or parent closure. Never backdate K, schedule closure, delete references or rewrite accepted context. Future declared closure dates are rejected. No enrollment permission is granted to Vice Principal.

Advantages: preserves the approved containment/cleanup/history rules and actual operation order. Disadvantage: no future declared closure date; supplied end may differ from actual execution date, so views must expose declared date and operational key separately. Alternative: allow future contained declared end dates while closing authority now at K; this is a distinct human date/workflow permission and must be recorded explicitly before tests/code.

Impact: scoped assignment requirement/design/task clarification and positive/negative closure/race/retention tests. No date-containment exception is needed after period end: a valid past date within the period is retained while K remains actual current execution. Teacher replacement date eligibility/mapping remains U8-specific; no scheduled replacement permission is selected by this proposal.

## Approval boundary

Approve/revise A/B/C explicitly before U7. These are concrete proposed decisions, not code accommodations. All gates retain later evidence obligations; approving the rules alone does not close 1.2–1.8 or complete tasks 5.1/5.2. Full HTTP/browser/LAN and deferred capabilities remain outside this review.

## Subsequent human approval

The human replied Continue to the concrete final question proposing approval of all three rules. See docs/u7-human-decisions.md and amended OpenSpec for the normative decision. This document retains the reviewed alternatives as historical evidence, not a parallel baseline. U8 non-current replacement mapping remains pending.
