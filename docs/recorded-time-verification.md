# Recorded Event Time Presentation

Date: 2026-10-08. This is a presentation refinement of existing approved read screens; no backend timestamp, academic date, schema, authorization or write behavior changes.

UTC event timestamps now display a readable Spanish date/time explicitly labeled hora de Perú through one formatter fixed to America/Lima. It is used by materials, Library, delivery updates/version history, assessment/current/history, notifications and submission acceptance context. Time elements retain the exact original UTC timestamp, including microseconds, in their machine-readable datetime metadata. Invalid/malformed calendar timestamps display Fecha no disponible rather than crashing the workspace or exposing raw input.

Declared date-only enrollment/assignment intervals and optional activity due dates remain unchanged; they are calendar dates, not UTC events. Formatting does not change sorting, IDs, source values or persistence.

## Executed evidence

- 131 frontend tests passed. Four new cases cover a UTC midnight crossing to the previous day in Peru, exact UTC microseconds in time metadata, malformed/calendar rejection, leap dates and separation of calendar dates from event timestamps. The first test expectation was adjusted to allow the locale's abbreviation punctuation; the actual conversion was already correct.
- TypeScript/tooling checks and Vite build passed.
- 9 real browser checks passed with the device context deliberately configured to Asia/Tokyo. Existing delivery, current assessment, version history, grade history, original acceptance context, Materials, Library and Notifications were consulted through real endpoints. Displayed UTC metadata matched server values, text was labeled in Peru time, and the 768-pixel layout had no horizontal overflow. The browser verifier performs no write and leaves read/unread state untouched.
- Before/after snapshots were identical: 25 tables / 14 migrations, 182 accounts, 11 periods, 65 assignments, 180 enrollments, 220 materials, ordinal 1142. Node syntax and Git whitespace checks passed.

Run backend/scripts/manage-dev.ps1 -Command verify-recorded-time. Screenshots and receipts remain private/ignored. Full mutation regressions were not repeated for this display-only refinement. The final commit is its local handoff; no remote Git operation is included.

Server clock synchronization, institutional time configuration, physical network/load and backup/deployment acceptance remain open. This change converts existing UTC values for presentation and does not certify server clock accuracy.
