# EVAL academic prototype prerequisite

**Status: T1–T3 complete; frozen candidate ready for parent checking.** The parent read the full preparation document and mirror #89 and reconciled them before this handoff. Only the static artifact under `docs/prototipos/academic-foundation/` and this plan/mirror changed. Human prototype acceptance remains pending; gate 1.1 is not completed.

## Objective and boundaries

Make enrollment-derived current scope and retained original history understandable to student/teacher reviewers. Transfers and teacher replacement must not visually overwrite earlier enrollment, assignment, owner, or routing context. Use invented English sample people and opaque string IDs, not institutional data.

This is an independent synthetic read-only review artifact, not application code, SDD apply, U2+, U12, authentication, security proof, persistence, or domain authorization/interval implementation. Planned Windows Server/Laravel/MySQL/React/TypeScript/Tailwind direction remains unchanged. Do not edit backend, frontend, database, AGENTS, OpenSpec, configuration, or runtime files. No materials, grades, notifications, accounts, live APIs, lifecycle writers, storage, migrations, unresolved eligibility/date mapping, network assets, downloads, installations, server launch, commits, staging, pushes, PRs, or child dispatch. The artifact needs no normative policy waiver; actual conflict must be reported, not waived.

The preparation baseline was clean `feat/academic-foundation` at `ba9ed9e`; `docs/prototipos/` contained only `.gitkeep`. At execution start HEAD remains `ba9ed9e`, with only the preparation `odd/` untracked. No root package.json exists; artifact classic scripts can expose guarded CommonJS exports to `.mjs` tests without a server, eval, dynamic Function, VM, or application dependency.

## Read-only normative references

All paths below are under `openspec/changes/academic-foundation/`: `proposal.md`, `design.md` (Authorization Model and Student historical-submission envelope), `tasks.md` (gate 1.1 and remaining decisions), and `specs/academic-authorization/spec.md`, `specs/student-enrollment/spec.md`, `specs/teaching-assignments/spec.md`, `specs/activity-submission-routing/spec.md`. Existing requirements approval is preserved. Historical tooling statements in those documents are not current runtime evidence. External monolithic/API-coupled frontend findings supplied by the parent are not newly verified here; only independent card/responsive ideas are used, never copied repository/build output.

## Acceptance criteria

| ID | Review contract |
|---|---|
| A1 | `index.html` opens from disk using relative CSS/classic scripts only; no server, build, packages, fetch, polling, storage, POST, external fonts/CDN, or HTTP-only imports. |
| A2 | Permanent EVAL synthetic read-only banner; persona selection is not authentication/permissions and all fixtures are inspectable. |
| A3 | Student current period/grade/section comes from selected student's active enrollment; own enrollment and submission history remain separate from current scope; other students excluded from projection, not secured against inspection. |
| A4 | Teacher sees own assignments and original-owned work only; multiple scopes and same-course distinct-teacher IDs retained; replacement teacher never inherits original history. |
| A5 | Empty student/teacher personas and closed-parent scenario with active children retained; no new-work permission from active children and no actual writes in any scenario. |
| A6 | Own sample history retains activity, original assignment/teacher/period/subject-or-area/grade/section, accepted-under enrollment and acceptance evidence. Declared dates and synthetic operational keys have separate labels; no date conversion or immutable label-at-time claim. |
| A7 | No receiving-teacher selector or send/create/transfer/replace/close operation; persona/scenario switches only. Assignment closure alone versus parent closure explained without acceptance engine or complete activity/deadline/payload lifecycle. |
| A8 | Semantic landmarks/headings, labeled native selectors, visible keyboard focus, color-independent state text, responsive cards, aria-live status; actual browser/accessibility review pending. |
| A9 | Frozen fixtures and pure tests cover ownership, unknown persona empty fallback, missing reference safe omission, current/original separation, concurrent identity, replacement non-inheritance, empty/closed views, and non-mutation. Static/component checks are not browser E2E or permissions proof. |
| A10 | README gives exact opening path, scenario walkthrough, mapped acceptance, actual checks/TDD evidence and limitations; human acceptance pending and no OpenSpec checkbox changed. |

## Stable tasks, routing, and rollback

Recorded delegated routes explain the parent's handoff; this sole writer executes serially and launches no children. All paths here are relative to `docs/prototipos/academic-foundation/`.

- [x] **T1 — Frozen synthetic fixtures and pure projections.** `tests/projections.test.mjs` first, nonfunctional stubs only as needed for assertion RED, then `fixtures.js` and `projections.js`; GREEN. Trigger: two or more nontrivial files/shared projection contract. Rollback: these three artifact files only. Actual: 239 added lines; 7/7 tests, three syntax checks and three untracked whitespace checks passed. Replacement fixture corrected to preserve original Section A; independent Section B assignment remains distinct.
- [x] **T2 — Accessible static read-only UI.** `tests/artifact.test.mjs` first for local markup/content and minimal DOM component checks, assertion RED, then `index.html`, `styles.css`, `app.js`; GREEN. Trigger: four-file mapping/multiple nontrivial files. Rollback: these four files and T2 fixture-consistency correction/assertion; T1 remains independently testable. Actual: 345 new UI/test lines plus 13 regression-test additions and one fixture-line replacement (2 changed lines), 360 authored changes total. All 14 tests passed; applicable syntax and untracked whitespace checks passed. Browser keyboard/visual checks pending.
- [x] **T3 — Verification and human review packet.** Complete checks after final source normalization; `README.md` with results/walkthrough. Trigger: preparation/review boundary spanning all files. Rollback: README only; no T3 behavior correction/refactor. Actual: 76 README additions, final 14/14 tests, five syntax checks and tracked whitespace check exit 0; all nine untracked-file checks have no whitespace diagnostics (exit 1 is expected new-file difference), only LF/CRLF warnings; trailing-whitespace search has no matches. Final artifact snapshot: 673 lines across eight files; plan 72 lines separately. HEAD unchanged, no tracked or staged changes. Browser/manual acceptance pending.

## Effective strict TDD and evidence

Explicit scoped session requirement: tests → meaningful assertion RED → minimal GREEN → refactor GREEN when performed. Missing import/file errors alone are not RED proof. No global configuration flip; historical `strict_tdd: false`/`apply.tdd: false` remains untouched. Node v24.21.0 is available; preparation's read-only built-in test probe passed 1/1. DOM initialization stays separate from pure projections; textContent render helpers avoid raw HTML data interpolation.

| Task | RED command/results | GREEN command/results | Refactor |
|---|---|---|---|
| T1 | `node --test "docs/prototipos/academic-foundation/tests/projections.test.mjs"`: initial 1 pass/6 failures including real ownership/freeze/parent assertions; follow-up 5 passes/2 assertion failures for replacement/independent identity | Same command: 7 passes/0 failures | None; correction preceded GREEN, not a refactor claim |
| T2 | `node --test "docs/prototipos/academic-foundation/tests/artifact.test.mjs"`: 0 passes/6 assertion failures. Fixture regression via T1 command: 7 passes/1 assertion failure (other student's grade mismatch) | Full quoted-glob command below: initially 13/13; after fixture correction 14/14 | None |
| T3 | Documentation/verification only; no new behavior, RED N/A | Final full quoted-glob command: 14 passes/0 failures; five syntax checks exit 0 | None |

Run in repository root, foreground PowerShell:

```powershell
node --test "docs/prototipos/academic-foundation/tests/*.test.mjs"
node --check "docs/prototipos/academic-foundation/fixtures.js"
node --check "docs/prototipos/academic-foundation/projections.js"
node --check "docs/prototipos/academic-foundation/app.js"
node --check "docs/prototipos/academic-foundation/tests/projections.test.mjs"
node --check "docs/prototipos/academic-foundation/tests/artifact.test.mjs"
git diff --check
```

Also run `git diff --no-index --check -- /dev/null <path>` for EACH new untracked artifact and this plan without staging. Capture actual results/counts. Browser/E2E, accessibility/visual/responsive, school LAN/WLAN/Internet-loss, server security/authentication, database/concurrency, contract/type/lint/format/coverage, and load checks are unavailable or unrun for this artifact. Do not install tooling or claim manual passes. Offline disk delivery is not LAN deployment evidence.

## Workload and delivery control

Initial authored-line forecasts (including tests/docs): T1 220–340; T2 320–460; T3 80–160; future total 620–960. Measure honest additions/deletions; no code-golf, omitted tests, or artificial splits. Parent has reviewed the forecast: **400 lines is advisory only per ODD task, not an acceptance criterion or automatic size-only stop.** This corrects the preparation plan's fabricated mandatory T2 size decision; it is not architecture/policy scope expansion. Delivery strategy remains ask-on-risk; chain and size exception remain unset for future human delivery. Native broader risk consent/review is owned by the parent after checks, not this worker's size judgment. No commit/PR authority is implied.

## Handoff and mirror

After EACH completed task, update its checkbox only after applicable actual checks; save this FULL current document with relative locator `odd/tasks/academic-prototype.md`, project `eval-colegio-mercedes`, topic `odd/academic-prototype/tasks`, capture_prompt false; read back file and memory and report IDs. Parent owns final candidate review; no review dispatch/lens selection/consent request here.

Skill paths injected: `C:\Users\kevin\.config\opencode\skills\cognitive-doc-design\SKILL.md`; `C:\Users\kevin\.config\opencode\skills\work-unit-commits\SKILL.md`. Apply readable docs and cohesive behavior/tests/docs boundaries; user no-commit restriction overrides skill defaults.

## Authority clarification

This file is auxiliary prototype evidence only. OpenSpec academic-foundation is the sole normative product/gate/task source. T1–T3 completion and mirror references do not certify gate 1.1. Human prototype acceptance remains pending.

## Current reconciliation result

The prototype now includes all four role perspectives. In-process Node verification passed 15 tests after a meaningful management-projection RED. The existing 14-test handoff is historical; manual browser review and gate 1.1 acceptance remain pending. The sequential team rule replaces parallel handoff assumptions for subsequent development.
