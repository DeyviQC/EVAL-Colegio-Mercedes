# Proposed Student DNI Sign-In

Date: 2026-10-08. Status: discussion only; no authentication policy implemented. The human reports that the teacher maintains a student database containing DNI numbers and wants easier entry. A request for an opinion does not confirm passwordless access or a PIN policy.

Recommendation: use the student DNI as the familiar account identifier, together with a school-assigned secret and administrator recovery. Knowing an identifier must not grant access to another student's grades or permission to submit their work. Existing teacher/director/vice-principal authentication and role/enrollment boundaries remain.

Stage one can use DNI as username while retaining the approved password policy. A shorter student PIN is a separate policy decision requiring explicit approval of length, guessing/throttling behavior, recovery and shared-tablet session handling. Do not use DNI as both identifier and password, silently remove authentication, auto-create users from an arbitrary number, or persist sessions indefinitely on shared devices.

Current verified code: LocalAccountService account creation requires an alphabetic-first username; current password-change bounds are 12–72 UTF-8 bytes. Therefore even numeric-only DNI usernames require corresponding approved backend/frontend validation changes. Existing sign-in and all current credentials remain unchanged in the school sample unit.

An eventual import must use the teacher's authorized actual file, preserve DNI as a string (including leading zeros), detect duplicates and bind each account to its actual current grade/section. Do not import unverified assignments or treat the fabricated sample as institutional records. No actual DNI numbers were used or collected in the current sample.
