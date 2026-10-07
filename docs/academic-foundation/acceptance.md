# Final local verification

## Result

The functional delivery includes the academic foundation plus the user-authorized Education module: direct material publication, protected attachments, classroom library, submissions and revisions, AD/A/B/C assessment with feedback, account administration, notifications and local read refresh.

| Check | Result |
| --- | --- |
| backend: php artisan test | 29 tests passed, 176 assertions, real isolated MySQL |
| frontend: npm run build | TypeScript and production build passed |
| frontend: npm run lint | Passed |
| frontend: npm run test:e2e | 8 scenarios passed, including 12 isolated login bootstraps |
| backend: php artisan eval:load --requests=50 --concurrency=5 | 50 authenticated classroom reads, 0 failures; final measured median 233.63 ms, p95 431.54 ms, 12.52 requests/s |

Browser flows cover all four roles, assignment navigation, original-teacher submission/assessment/student feedback, uploaded material available without approval, private download and a new usable account followed by deactivation. The tests retain synthetic activities/materials as visible demonstration data and preserve test account identities.

MySQL tests verify transfer/acceptance races in both orders, duplicate writes at the guard barrier, rollback, large string ordinal transport, historical route immutability, role-bounded queries, runtime ledger permissions, file isolation, account/password behavior and rejection of closed-task submissions through alternate endpoints.

## Resolved runtime issues

- Assets are served compressed through a public, filename-constrained route without session cookies.
- Response lengths are finalized after middleware; developer script injection is disabled.
- Login renews the CSRF token after session regeneration.
- Director navigation scrolls while the account/logout controls remain reachable.
- File uploads use a writable workspace-local temporary folder and a 10 MB limit in the launcher.
- Request interruption has a visible error and never automatically replays a write.

## Limits of the evidence

The local load probe is a development measurement, not an institution-approved production SLA. Real tablet/LAN/WLAN acceptance, Windows Server deployment and IIS/Apache choice remain separate deployment work. Browser tests block outbound browser requests; server egress is not certified by those tests.

The upstream planning tasks are retained as historical planning artifacts and are not blanket-certified as complete. Immediate transfer/replacement, exact name equality and read-only closed periods are the documented implementation policies. Scheduled/retroactive changes, remote replication, SIAGIE replacement and QR attendance are outside this delivery.

Legacy records with missing original enrollment/assignment context remain preserved in the separate prototype database, rather than being assigned fabricated historical acceptance context.

