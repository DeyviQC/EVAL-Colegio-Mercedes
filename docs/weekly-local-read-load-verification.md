# EVAL Weekly Local Read-Load Verification

Human acceptance: "apruebo todo continua" following the delivered functionality and operation review. This records acceptance of local behavior, not evidence of an institutional PAT, physical LAN/tablet results or production deployment.

## Bounded read-load smoke

Command: `./backend/scripts/manage-dev.ps1 -Command verify-week-reads`.

The verifier uses the existing owned HTTPS runtime, certificate pinning, encrypted local credentials through stdin and independent teacher/student sessions. It consults the course calendar and separate unassigned material/activity endpoints. Four bounded batches issue three requests per session, up to six concurrent requests; each response must return HTTP 200 and exactly match that actor's baseline projection. Each request has a 20-second timeout. No activity, material, calendar, association or academic fixture is created by this verifier; authentication necessarily establishes local sessions.

Result: 24 requests completed, zero status failures, zero changed responses and zero academic writes. Measured batch wall time was 8553 ms. Minimum response: 411 ms; median: 1448 ms; p95: 2130 ms; maximum: 2173 ms. These are observations on the current loopback development server and dataset, not an institutional performance target or a school-wide capacity certification. PHP development-server scheduling and session serialization are part of this measured environment.

Runtime snapshots before and after matched: TLS enabled, restricted runtime role, 38 tables, 19 migrations, 314 local accounts, 17 periods, 483 assignments, 297 enrollments, 332 materials and academic ordinal 2947. This supports the absence of academic mutations during the read check; it does not imply authentication/session storage is unchanged.

Node syntax and PowerShell parsing passed. Existing application tests were not unnecessarily repeated because this unit adds an operator verification helper and acceptance documentation rather than changing application behavior.

## Remaining evidence

The school's approved 2026 calendar reference or dates have been requested. No national reference template is published as the school's approved calendar merely because local functionality was accepted. Physical LAN/WLAN, external-Internet-disconnected journeys and real tablet acceptance require the institution's server address and devices. Broader capacity testing needs an approved deployment environment and expected simultaneous-user workload.

See [weekly-course-operation-guide.md](weekly-course-operation-guide.md) for role-based operation and the pending physical acceptance procedure, and [weekly-course-discovery-verification.md](weekly-course-discovery-verification.md) for functional evidence.
