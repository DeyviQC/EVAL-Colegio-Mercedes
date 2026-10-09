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

### Human-approved U1-only exception

The human explicitly authorizes strict test-first U1 prerequisites, minimal isolated local setup, and academic-foundation tasks 2.1–2.3 before prototype acceptance and integral architecture approval. This narrow exception does not complete gate 1.1 or any other planning gate. Setup is an independently authorized prerequisite, not domain implementation or SDD apply.

All default delivery restrictions remain in force outside this exception. It does not authorize U2 or later units, unresolved decisions, user management, U3 writers, locking/retry protocols, or the lifecycle ledger. The setup worker must not create temporal domain objects/tests, retained identity schema, or guard migrations. No chain strategy, scope expansion, or size exception is selected on the human's behalf. No commits, pushes, PRs, global installations, administrator operations, production access, or remote execution are authorized.

## Artifact and quality rules

- Write technical artifacts in English.
- Once implementation tooling exists, add unit, integration, end-to-end, and load tests where appropriate to the approved behavior.
- Run only repository-supported checks and report unavailable checks honestly.
- Keep changes focused, reviewable, and free of secrets.
- Do not perform remote operations without explicit authorization for the destination, operation, and credentials or session.

### Sequential team handoff (human instruction, 2026-10-07)

Finish and verify one bounded unit, then commit it before the next contributor starts from that exact predecessor commit. Do not develop application units concurrently. Record verification and remaining gates in each handoff. The human authorized local reconciliation; this supersedes the earlier no-commit restriction for finished reconciliation handoffs only. It does not certify prototype acceptance, resolve pending product decisions, authorize remote operations, or broaden the U1 exception into U2/U3 execution.
