# U12 Phase A Verification and Human Preview

Date: 2026-10-07. Predecessor: b37670dc743aa92721624fe63635c7ec481bcc7d. Human attachment explicitly authorizes the complete Director/Admin period/catalog vertical flow, minimal missing read queries, tests and a local handoff. The later request explicitly authorizes running a synthetic local preview. No numeric line cap remains after the earlier human removal; actual diff is reported before commit.

## Functional boundary

Director/Admin can list all retained periods and active/inactive catalog records, inspect period dates/state, create planned periods, request activation/closure with explicit confirmation/cancel, create subjects/areas/grades/grade-bound sections, rename catalog entries and deactivate/reactivate them. Existing commands exclusively enforce domain rules, equality and authorization; React performs wire/basic required-field validation, not independent domain authority. Labels are preserved as text. No deletion, period edit, scope reassignment, deferred feature or spec amendment was introduced.

Minimal GET /academic/periods and GET /academic/catalog/{entry|grade|section} return data.items and data.next_after. Ordered numeric-ID keyset pages contain at most 50 records, fetch 51 only to determine continuation, expose no equality keys or total count, and accept only optional canonical string after. Director/Admin credential revision/active identity/stored grant are revalidated. Other roles are denied these collections; Vice Principal purpose-bound support remains separate. No new command/schema/migration.

The session UI reuses the actual client CSRF and same-origin credentials for existing writes, PATCH for catalog updates and exact validated collection responses. It provides loading/empty/error/forbidden states, labeled required controls, native keyboard-operable buttons/selects and lifecycle confirmation. Successful writes refresh the relevant page, including newly created records beyond page one. Paginated grade selection blocks concurrent page requests. Uncertain writes are not retried and suspend further mutations in the mounted workspace. Session expiry invalidates the parent context. Query generations discard stale directory responses; no fixture supplies UI authority.

## Executed checks

- frontend npm test: 66 passed, zero failures/skips.
- frontend npm run build: strict application/tooling typecheck and Vite production build pass.
- Explicit browser U11 invocation: 79 tests / 1,529 assertions, zero failures/errors/warnings; one intentional skip for the optional human-preview method. Eight actual Chromium cases pass.
- New directory test proves real MySQL keyset boundaries, retained inactive rows, exact fields and anonymous/three-role denial, malformed cursors/authority query rejection. Existing domain/retention/race/HTTP regression remains passing.
- New real browser journey exercises period creation, existing active-period closure, cancelled activation, confirmed activation/closure, catalog create/rename/deactivation/reactivation, grade/section creation and logout. Mock-response UI case covers empty/forbidden/uncertain states without replay; it is not evidence of an actual lost commit.
- Browser tests exposed newly created records outside the first page, duplicate appends after mutation refresh, paginated grade request overlap and ambiguous selector labeling. These were corrected and the complete suite rerun. No historical RED evidence is reconstructed.

Runtime remains PHP 8.4.26 / MySQL 8.4.9 / pinned Chromium 156.0.8078.4. Use the explicit browser invocation in docs/u12-browser-verification.md after frontend build. Browser TLS uses the approved disposable SPKI exception, not institutional CA trust.

## Requested preview

Set EVAL_HUMAN_PREVIEW=1 and invoke the supported U11 test filter testExplicitTemporaryHumanPreview. It starts the owned HTTPS/PHP fixture with a disposable Director/Admin credential, prints its loopback URL/login and remains open for at most 30 minutes. The demonstration password is deliberately public test-only (EVAL-demo-local-2026), never an institutional credential. An isolated Chromium profile can use the certificate public-key pin; no OS trust changes. Do not run other U11 suites concurrently against that database during the preview. Workers and certificate files terminate/remove through normal fixture teardown; a stop file in the owned temporary fixture directory permits early shutdown. Preview-created records remain synthetic disposable database records, not production data.

No physical LAN/WLAN/outage, complete keyboard audit, load thresholds or final acceptance is claimed. Tasks 8.2-8.4 and gates 1.2-1.8 stay open. Evidence for closed-parent boundaries/equality/tooling remains in preceding handoffs; no gate is closed merely by UI. Phase B has not started; student identity selection/name-source and retained-history transport must be inspected before any enrollment UI. Prototype/odd remain separate, no remote Git/apply/candidate integration.
