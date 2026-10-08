# U5 Human Decisions and Scoped Baseline Alignment

Date: 2026-10-07. Implementation predecessor: 77826e66ae5482ffd384198cfe3b1ca11a4491cb.

The project human explicitly answered Yes, I approve that set to this exact grouped question: active enrollments only in active periods; administrative closure with a valid past/current declared date (end >= start); operational closure at actual successful commit key, without retroactivity; planned teaching assignments remain permitted according to specs.

## Approved rules

- Creation of an active enrollment, including a transfer successor, requires an active requested parent. Planned/closed parents deny that creation. This is specific to new active enrollments, not a universal active-parent policy or a change to approved planned teaching-assignment creation.
- Explicit enrollment administrative closure accepts a valid declared end date on/before server school-local today (America/Lima), not earlier than declared start. Future closure dates are rejected. Residual closure under a closed parent remains permitted to Director/Administrator.
- Preserve that permitted declared end date as DATE; set operational end to actual successful transaction key K. Do not derive K from midnight/date, backdate authority, rewrite earlier accepted context, or schedule effectiveness. No successor is created by closure.

The normative amendments precede U5 code. They change student-enrollment and cross-reference academic-periods; design/tasks distinguish these resolved enrollment dependencies from still-unresolved assignment/other planned-parent work eligibility and assignment/replacement closure mappings. Approved mandatory period identity, no enrollment date containment, restrictive retained history, role matrix, no reopen and immediate-only transfers remain unchanged.

This is a human-selected requirement amendment, not a retrospective alignment to existing code. U5 code does not yet exist at this approval record. Gate 1.2/1.4 receive scoped decisions; their broad checkboxes remain open. Only gates/tasks supported by complete evidence may be closed later. No full HTTP/LAN acceptance, U7 permission, remote Git operation, push or integration of 2e5d053 is authorized by this record.
