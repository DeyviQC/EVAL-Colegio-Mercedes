# U9 HTTP Contract Approval

Date: 2026-10-07. Predecessor: 169f88a785373fa730eee02fb63b57c8c7c327d4.

The human answered "continuar" directly after the final question requesting approval of the single recommended HTTP contract. In that context this is authorization to proceed with that proposed contract, not selection between unresolved alternatives or inference from elapsed time. Record this before 6.5 implementation.

Selected contract: 401 absent/invalid session; 403 forbidden operation; 404 absent/out-of-scope read resource; 422 invalid input/date; 409 lifecycle/overlap/integrity conflict; 503 with correlation identifier for uncertain transaction outcome, with no automatic retry/replay. Preserve existing local authentication origin/CSRF/throttle 403/419/429 semantics. Never expose SQL, credentials or exception traces to clients.

Use thin server-owned policy/controller adapters over the existing verified Symfony request/response and Illuminate local SessionGuard components. No routing/framework installation, full Laravel kernel, deployed listener or browser/LAN acceptance is inferred. Commands retain guarded final authorization after preliminary policy checks. Reference-backed operations remain fail closed without U10/U11 bindings. No client-supplied actor, role, scope authority, recipient or callback is accepted.

This authorizes 6.5 and its isolated HTTP-request tests/local handoff only. It does not complete global gates 1.2–1.8, approve U10/U11 persistence, select institutional profile-name binding, enable deferred processing or authorize deployment/apply/remote Git/candidate integration.
