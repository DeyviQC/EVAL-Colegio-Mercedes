# EVAL Educational Experience Preview

## Blue and mint visual proposal

Calendar management preview: Director/Admin → Administration → Review calendar opens an independent memory-only draft with the nine national-reference teaching/management blocks. Date validation rejects out-of-year, inverted and overlapping ranges. A source field and explicit synthetic review checkbox illustrate the intended confirmation step; nothing is persisted or published and course dates remain separate. Reset restores the template. This newly created management preview requires review before its application implementation.

Latest management verification: `node verify.mjs azul-menta.html` passed 80 checks, zero HTTP(S) requests; valid draft, overlap rejection, template reset and management layouts at 1024/768/390 pixels included. `node --check calendario.js` and diff whitespace checks passed. Week-level editing, real publication, revision history and parent-period validation are not implemented by this preview.

Discovery refinement: an explicit Go to week selector opens and focuses any of the 36 weeks, switching teaching block as needed. A Show only weeks with content filter keeps Unassigned resources visible and provides an empty-block explanation. Explicit week navigation clears this filter so empty weeks remain reachable. Course changes reset both controls. No delivery status is invented for the pending-task shortcut; that behavior remains a separate review decision.

Latest discovery verification: `node verify.mjs azul-menta.html` passed 73 browser checks with zero HTTP(S) requests, including direct week navigation, block switching, keyboard focus, filtering and empty states. Syntax and diff whitespace checks passed.

Calendar follow-up: the variant now provides four teaching-block choices with nine weeks each, continuous labels 1–36 and date ranges derived from the national 2026 reference. Management blocks are excluded from teaching-week numbering. The UI explicitly marks the calendar as pending school confirmation and uses neutral Block labels. Only weeks 1–2 contain synthetic task/material examples; other weeks show explicit empty states, and general resources remain in Unassigned. No current-week inference, institutional calendar configuration or application persistence is implemented.

Latest verification: `node verify.mjs azul-menta.html` passed 61 checks with zero HTTP(S) requests, checking each block's first/last week and date boundaries for both educational roles, existing interactions and mobile/tablet overflow. JavaScript syntax and diff whitespace checks passed. This extends the earlier verification receipts below; physical-device and full accessibility acceptance remain open.

The human accepted this visual direction on 2026-10-08 and requested weekly course organization, then accepted the presented weekly navigation prototype with "si me gusta". The variant now opens courses on a Weeks tab with expandable synthetic material/task groups and an Unassigned group. The original preview remains unchanged. Calendar/editing semantics and implementation architecture remain pending under openspec/changes/weekly-course-organization. No application rollout is inferred from prototype approval.

Weekly verification: `node verify.mjs azul-menta.html` passed 37 checks with zero HTTP(S) requests, including both educational roles, weekly expansion, Unassigned resources and weekly overflow at 1024/768/390 pixels. Mobile screenshot visually inspected; `node --check semanas.js` and `git diff --check` passed. Evidence remains under `LOCALAPPDATA/Temp/eval-azul-menta-preview-evidence`.

Open `azul-menta.html` for the reference-inspired alternative requested on 2026-10-08. It reuses the independent synthetic preview and adds local CSS: blue/mint accents, rounded course cards, a prominent classroom banner and labeled navigation. Original `index.html` remains available for comparison. This is a visual proposal, not final UI acceptance or a change to the running application. The existing preview's future-feature labels remain historical demonstration content, not a statement of current application implementation.

Verification: `node verify.mjs azul-menta.html` passed 24 checks with zero HTTP(S) requests, covering four demonstration roles, course tabs, disabled synthetic operations, keyboard entry and overflow at 1024, 768 and 390 pixels. Desktop student screenshot visually inspected. Evidence: `LOCALAPPDATA/Temp/eval-azul-menta-preview-evidence`. Physical device and full accessibility acceptance remain open.

Human-approved preliminary information architecture, 2026-10-07. Independent synthetic visual preview; not authentication, authorization, domain logic, persistence or final UI acceptance. No backend/productive frontend/OpenSpec modifications. No code copied from 2e5d053.

Open index.html directly in a browser. All assets are local and require no build or external Internet. Select Director/Admin, Vice Principal, Teacher or Student using the demonstration role control. Explore dashboards, classrooms/courses, students per classroom, administration, course tabs and retained context. All people and counts are invented.

Materials, complete activities/submissions, material supervision/observations are visibly marked "Prototipo / aún no implementado"; their actions are disabled. Grades AD/A/B/C, library and synchronization are not operative modules and remain future capability decisions. Existing live period/catalog implementation remains separate. Demonstration role switching does not grant actual permissions.

Verification: node verify.mjs uses the already installed Playwright and isolated Chromium cache. It opens file:// assets, checks four-role navigation/course tabs, disabled future actions, tablet/mobile overflow, basic keyboard focus and zero HTTP(S) application requests; saves synthetic screenshots under the owned temporary evidence directory printed by the runner. No application listener or credentials are used. Screenshots are inspection evidence, not full accessibility or institutional acceptance.

Executed result: 24 checks passed, zero HTTP(S) requests; viewports 1366, 1024, 768 and 390 pixels. node --check app.js passed. Eleven synthetic screenshots saved outside Git at LOCALAPPDATA/Temp/eval-visual-preview-evidence. Student dashboard visually inspected. No final visual approval or gate closure is inferred. The approved academic-foundation prototype remains unchanged.
