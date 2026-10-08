# U6 Scoped Readiness

Date: 2026-10-07. Predecessor: a44bb8f8f443d01e200ca6b402d483969d48d14e. Human eligibility amendment: 6262d4faedb62e42ce0e554eb5f13753eddd4dc6.

The sequential implementation authorization permits U6 after committed U5. Scope is tasks 4.3/4.4 only: immediate atomic server-confirmed transfer, tests and handoff. Transfer timing is already approved, not a new human question. The new enrollment-specific active-parent rule applies to successor creation. No U7 assignment workflow, general U9 API or U10/U11 persisted submission reference is created.

Applicable gates: 1.1 approved; 1.2 immediate school-date/actual K mapping is established by U1/U3 and design, not the pending teacher/assignment mapping; 1.3 no enrollment date containment and correct destination scope are preserved; 1.4 requested active parent is re-read under locks and valid residual cleanup remains U5; 1.5 reuses approved U4 catalogs; 1.6 uses retained BIGINT/local authenticated actor with credential revision and current Director/Administrator grant; 1.7 uses separate eval_u6_test, actual MySQL races and rollback probes; 1.8 uses verified whole-transaction bounded retry and never replays uncertain commits. Global gate checkboxes remain open.

The API accepts only prior enrollment locator and destination grade/section locators. Student/period come from the retained prior record; there is no timing, recipient, alternate student/period, correction or operation-key selector. Re-read complete period, old/new catalog, shared actor/student and all relevant enrollment history under U3. Validate active source/parent, actual server date >= prior declared start, changed active destination/matching section and no competing occupied interval at prospective K, excluding only the prior ending at K.

One successful mutation marks prior transferred with declared end on actual school date and operational end K, creates a distinct active successor with declared start on that date and operational start K, and appends both events with the same K/correlation. Prior scope/start are never rewritten. Return confirmation only after U3 commits; no automatic replay. Same-day history remains nonempty because ordinal intervals, not dates, establish ordering.

Persisted submission original-route/context integration remains later tasks 7.4/7.5. U6 proves retained prior identity/scope and no other aggregate writes without fabricating unavailable reference tables or claiming full student-facing visibility policy. No external effect, production data, remote Git operation or 2e5d053 integration is needed.
