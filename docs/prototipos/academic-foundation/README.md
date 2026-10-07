# EVAL academic scope review

An independent **synthetic read-only prototype**, not an application frontend or authentication system. It helps reviewers compare current enrollment/assignment scope with original retained submission context. All people are invented; all fixtures are inspectable. Persona selection is not permissions or receiving-teacher selection.

## Open the review

Open `docs/prototipos/academic-foundation/index.html` directly from the repository in a browser, for example by double-clicking it in File Explorer. Keep its sibling CSS and three JavaScript files together. No server, build, packages, Internet connection, installation, or backend access is required by these assets. Actual disk-browser execution is still pending human verification.

On this workspace the opening path is `C:\Users\kevin\Projects\EVAL-Colegio-Mercedes\docs\prototipos\academic-foundation\index.html`. Enable local JavaScript; without it, the page displays the prototype boundary and a no-script explanation rather than sample records.

## Scenario walkthrough — human review pending

Choose options using the labeled **Sample persona** and **Sample scenario** selectors. These controls only replace the displayed sample projection; they never save data or perform academic operations.

| View | What to inspect |
|---|---|
| River Linden / After transfer and replacement (default) | Current enrollment is Grade 2 / Section B. Own earlier submission remains Section A under `enrollment-old`, original assignment `assignment-original`, teacher Morgan Vale, and Sample 2026. Current scope does not rewrite that context. |
| River / teaching references | Jules Cedar and Avery Brook share Mathematics / Grade 2 / Section B through distinct IDs `assignment-jules-b` and `assignment-peer`. No recipient selector is present. |
| Morgan Vale | Two own assignments are shown: closed Mathematics / Section A and active Creative Inquiry / Grade 3 / Section C. Original submission `submission-river` remains Morgan's retained work. |
| Jules Cedar | Replacement `assignment-new` preserves Mathematics / Section A and references `assignment-original`. Separate `assignment-jules-b` covers Section B. Neither inherits Morgan's submission history. |
| Avery Brook or Sky Rowan | The other student's internally consistent submission is visible only through its student's own projection or original teacher's projection, not River's or Morgan's view. |
| Any persona / Closed parent, retained active children | Parent text says closed and “No new academic work”; child rows retain their active/closed states. Own history remains readable. The prototype is read-only in both scenarios, regardless of future application permissions. |
| Casey Fern or Robin Elm | Explicit empty student/teacher states: no current scope or retained submissions. |

Inspect the separate “Original teaching assignment” and “Accepted-under enrollment” groups. Declared dates and synthetic operational keys are separately labeled evidence; no date-to-key conversion or domain interval engine is implemented. Display labels are not immutable historical label-at-time snapshots.

For manual accessibility review, navigate using Tab/Shift+Tab and native selector keys; inspect the skip link, focus outline, landmarks/headings, live status, and descriptive state text. Resize to a narrow viewport and zoom. These steps are a **pending checklist**, not claimed passes.

## Acceptance mapping

Auxiliary prototype criteria and task evidence live in `odd/tasks/academic-prototype.md`. OpenSpec is the sole normative source for product rules, tasks and gates.

| Criteria | Implemented evidence | Remaining human/runtime evidence |
|---|---|---|
| A1–A2 | Relative classic scripts/CSS, static no-network checks, persistent prototype banner | Open from disk in a real browser; verify no network requests |
| A3–A7 | Pure ownership/current/original projections, replacement/concurrent IDs, empty/closed states, precise routing/retention text | Confirm wording and academic comprehension with intended reviewers |
| A8 | Semantic markup, native labeled selectors, focus/media rules, aria-live status | Actual keyboard, accessibility, responsive/visual review |
| A9 | Frozen fixtures, unknown/missing-reference exclusions, non-mutation, static checks and tiny DOM component double | Not browser E2E or server authorization/security proof |
| A10 | This walkthrough and recorded checks/limitations | Explicit human prototype acceptance; gate 1.1 remains incomplete |

## Exact verification commands

Run foreground in PowerShell from repository root; Node v24.21.0 was observed locally. No package installation or test framework setup is needed.

```powershell
node --test "docs/prototipos/academic-foundation/tests/*.test.mjs"
node --check "docs/prototipos/academic-foundation/fixtures.js"
node --check "docs/prototipos/academic-foundation/projections.js"
node --check "docs/prototipos/academic-foundation/app.js"
node --check "docs/prototipos/academic-foundation/tests/projections.test.mjs"
node --check "docs/prototipos/academic-foundation/tests/artifact.test.mjs"
git diff --check
```

Recorded final automated result: **14 tests passed, 0 failed** (8 projection/fixture tests, 6 static/component tests); all five syntax checks and tracked whitespace check exited 0. Each of the eight new artifact files and the task document had no whitespace diagnostics under `git diff --no-index --check -- /dev/null "<relative-path>"` without staging (exit 1 denotes the expected new-file difference). Git emitted only LF-to-CRLF working-copy warnings, not whitespace errors; an additional trailing-whitespace search returned no matches. LF source normalization precedes final verification; no repository line-ending configuration was changed.

The component double implements only createElement, textContent, append, replaceChildren, selector values, and change callbacks. It verifies stale-history replacement, safe text rendering, closed-parent wording, empty states, and status updates; it does not simulate layout, focus, a screen reader, or loading classic scripts in an actual browser.

## Strict test-first evidence

| Unit | Observed RED | Observed GREEN | Refactor |
|---|---|---|---|
| T1 | `node --test "docs/prototipos/academic-foundation/tests/projections.test.mjs"`: 1 passed / 6 failed with nonfunctional stubs, including ownership/freeze/closed-parent assertion failures; further replacement fixture assertions: 5 passed / 2 failed | Same command: 7 passed / 0 failed | None; no refactor result invented |
| T2 | `node --test "docs/prototipos/academic-foundation/tests/artifact.test.mjs"`: 0 passed / 6 assertion failures against markup/mount stubs | Full quoted-glob suite: 13 passed / 0 failed | None |
| T2 regression | T1 command with fixture consistency assertion: 7 passed / 1 failed (other student's grade did not match original assignment) | Corrected fixture scope; full suite 14 passed / 0 failed | Correction, not refactoring |
| T3 | Documentation/verification only; no new behavior and no RED needed | Final full suite 14 passed / 0 failed | None |

Missing-file/import errors were not used as the sole RED evidence. Scoped strict TDD did not change global configuration. The initial T1 RED also contained access errors from deliberately empty stubs; genuine assertion failures were observed independently in the same run.

## Boundaries, limitations, and rollback

No materials, grades, notifications, accounts, complete activity/submission workflow, persistence, APIs, recipient override, lifecycle operations, or planned-parent eligibility/date mapping are implemented. The planned application stack remains unchanged. Parent-period closure text illustrates a rule; client-side projections are not security enforcement.

Browser/E2E automation, manual disk-browser/keyboard/visual/accessibility checks, school LAN/WLAN and Internet-loss deployment, server authentication/authorization, real database/integration/concurrency, API contracts/type checking/lint/format/coverage, and load tests are unavailable or unrun for this artifact. Disk portability is not evidence of deployed school-server availability.

All prototype files are removable together without touching existing application data or backend code. T1's fixtures/projection tests can be reviewed independently; T2's UI/test boundary and fixture regression are documented in the task plan. No commits, staging, pushes, PRs, remote operations, server launches, or tooling installations occurred. HEAD remains `ba9ed9e`. Human acceptance, full architecture approval, and future delivery strategy/chain decisions remain separate; no OpenSpec task was marked complete.

## Reconciliation verification (2026-10-07)

The sample now includes Alex Willow (Director/Admin) and Taylor Ash (Vice Principal). Director reviews foundation history; Vice Principal sees only assignment-original and its supporting scope, with no enrollment/submission listing. Inspect valid residual cleanup versus closed-parent new-work denial in the explanation; no commands execute.

New management projection test: RED 8 passed / 1 assertion failure; GREEN full suite 15 passed / 0 failed. The old safe-text test was corrected to locate student-river by ID rather than array position after adding personas. Command used: node --test --test-isolation=none "docs/prototipos/academic-foundation/tests/*.test.mjs". Default subprocess isolation hit sandbox spawn EPERM; in-process execution supplied meaningful assertion evidence. Prior 14-test results above are historical. Browser/accessibility and human acceptance remain pending.

OpenSpec is normative. See docs/reconciliation-academic-foundation.md for gate 1.1 review requirements; this artifact remains separate from backend persistence and authority.
## Explicit human acceptance (2026-10-07)

The project reviewer accepted the synthetic four-role prototype for academic-foundation validation and approved the current base architecture while preserving unresolved questions. See docs/academic-foundation-gate-1.1-approval.md for reviewed revision, authority, consistency and exact limits. Gate 1.1 is now complete; earlier pending-acceptance statements are historical. No manual browser/accessibility pass is asserted. Gates 1.2–1.8 and U1 completion remain pending; U2 remains blocked and U3 is excluded.
