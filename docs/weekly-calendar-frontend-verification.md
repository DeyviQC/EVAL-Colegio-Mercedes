# Weekly Calendar Management Frontend Verification

Date: 2026-10-08. Human-approved management prototype and architecture. This unit adds the real React CalendarWorkspace, strict calendar client/decoders and the Director/Admin navigation entry. No new role or server authority is inferred from the menu.

## Implemented behavior

Consult every page of authorized academic periods and calendar drafts; select a period explicitly. Display current publication, source and retained draft status. Prepare dated teaching/management blocks and dated teaching weeks using form controls. Optional national 2026 template is visibly a reference requiring PAT review; reject blocks outside the selected parent-period dates. Save a draft before publishing; unsaved plan changes disable confirmation/publication. Closed periods and published drafts are read-only.

Write transport uses relative same-origin routes, CSRF, no-store and no redirects. Strict decoders reject invalid chronology, week identities and scope/acknowledgement mismatches. Pending writes cannot duplicate; uncertain writes block further mutations across workspace remounts because the client is owned by SessionShell. Complete consultation of the affected period plus explicit user review is required to continue. Consultation of another period cannot release uncertainty; a consultation predating a later mutation cannot certify its outcome. There is no automatic replay.

## Verification

- `npm test`: 160 tests passed, including nine new calendar contract/UI cases.
- `npm run build`: TypeScript checks and Vite build passed.
- `node tests/calendar.browser.mjs`: built React browser journey passed 20 checks with three synthetic writes and zero external requests. Covers national-reference loading, draft save, committed edit with lost response, blocked replay, explicit consultation/review, publication, closed-period/read-only behavior and overflow at 1024/768/390 pixels.
- Browser evidence: LOCALAPPDATA/Temp/eval-calendar-react-evidence. Responses and people are synthetic; this is not real session/database integration or institutional acceptance.

The browser harness serves only the built frontend on an ephemeral loopback port and replaces academic/authentication HTTP responses with memory-only fixtures. It closes the server and Chromium in finally. It does not access runtime secrets, eval_dev, institutional credentials or remote services.

## Remaining delivery

The backend additive migration and least-privilege runtime permissions have not been applied to eval_dev. The existing app may show a calendar-unavailable message until reviewed local maintenance is completed; do not claim the feature is operational in that database. A real session/MySQL browser journey, backend/frontend compatibility verification, historical-calendar detail navigation, course associations and weekly resource views remain pending. No institutional calendar is confirmed or published by this work.

No commits, pushes, remote actions or new contributor handoffs were performed. Preserve the sequential handoff rule before another contributor starts.
