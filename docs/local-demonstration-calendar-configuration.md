# EVAL Local Demonstration Calendar Configuration

The human repeatedly approved the 2026 weeks and prototype and reported that the live course still displayed the calendar-not-configured message. The accepted reference was therefore configured for the named local development period, explicitly as demonstration data rather than an institutional PAT assertion.

Command: `./backend/scripts/manage-dev.ps1 -Command configure-calendar-demo`.

The helper uses the existing owned HTTPS runtime, pinned certificate, authenticated director session and stdin-only credentials. It accepts only the active period named EVAL desarrollo 2026 with bounds 2026-01-01 through 2026-12-31. It consults the calendar first and creates/publishes the reference only if no current revision exists; it never replaces an existing publication or activates/closes a period. It imports the existing validated national template from the test-compiled frontend, requiring the existing npm test compilation to be present.

Execution succeeded: period 1, calendar created, no existing publication replaced, 36 teaching weeks. Publication source is explicitly: "Demostración local EVAL: referencia MINEDU 2026 aprobada por el usuario; PAT institucional pendiente". The confirmation records human acceptance for this local demonstration; it does not certify a school PAT. No material, activity or delivery was modified or automatically reassociated.

Actual student verification succeeded on course 1: calendar projection contains 36 weeks, Ir a la semana is available, Semana 1 opens, and the calendar-not-configured warning is absent. Published source is now displayed in the weekly interface. Frontend type checking/build and Node helper syntax passed; diff whitespace check passed. This verifies the actual local calendar configuration, not physical LAN/tablet acceptance.

The earlier credential dialog held the runtime operation lock while awaiting the human. Only that owned EVAL credential dialog was closed to release the lock. The accounts command now releases the lock before showing its read-only dialog, so future credential windows cannot block unrelated maintenance. Credentials were never printed into tool logs. No commit, push, remote operation, account reset or schema change occurred in this unit.

Approved institutional dates remain a separate pending evidence item. The current publication is a local demonstration reference and must not be represented as verified official school dates.
