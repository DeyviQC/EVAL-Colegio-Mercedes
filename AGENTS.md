# Repository Instructions

## Product identity

- The official product name is **EVAL**.
- The repository is `EVAL-Colegio-Mercedes` for I.E. Nuestra Señora de las Mercedes.
- Use EVAL as the only product name in specifications, code, and documentation. Never introduce legacy or alternate names.

## Product boundaries

- EVAL is an institutional hybrid educational web platform.
- Essential student functions must work over the local LAN/WLAN without external Internet while the local server, network, and power are available.
- Preserve role boundaries for director/administrator, vice principal, teacher, and student.
- Preserve academic assignment boundaries: students access only their grade and section, teachers act only within assigned subjects or areas, grades, and sections, and submissions are routed automatically from assignments.
- EVAL does not replace SIAGIE. QR attendance is outside the initial MVP.

## Architecture status

- Planned stack: Windows Server, PHP/Laravel, MySQL, React/TypeScript, and Tailwind CSS.
- IIS versus Apache remains undecided.
- Treat the planned stack as an approved direction, not as detected implementation or installed tooling.
- Do not claim frameworks, commands, test capabilities, or runtime behavior that cannot be verified in the repository.

## Delivery workflow

1. Use OpenSpec for spec-driven development.
2. Approve requirements, architecture, and a prototype before implementing application code.
3. Keep `openspec/` artifacts consistent with approved decisions and preserve archived history.
4. Do not create application scaffolding, source code, schemas, or CI workflows before the corresponding specifications and prototype are approved.

## Artifact and quality rules

- Write technical artifacts in English.
- Once implementation tooling exists, add unit, integration, end-to-end, and load tests where appropriate to the approved behavior.
- Run only repository-supported checks and report unavailable checks honestly.
- Keep changes focused, reviewable, and free of secrets.
- Do not perform remote operations without explicit authorization for the destination, operation, and credentials or session.
