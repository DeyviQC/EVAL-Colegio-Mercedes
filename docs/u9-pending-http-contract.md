# U9 Pending Technical HTTP Contract

Date: 2026-10-07. This proposal is awaiting human response; no choice is inferred from elapsed time. It is not a parallel product baseline. Tasks 6.5 and gate 1.7 require an explicit final technical HTTP mapping; core policies/queries may proceed independently.

Recommended mapping presented to the human: 401 for absent/invalid local session; 403 for forbidden operation; 404 for absent/out-of-scope read resource; 422 for invalid fields/dates; 409 for state/overlap/integrity conflicts; 503 with correlation ID for uncertain commit, without automatic replay. Existing authentication origin/CSRF/throttle semantics remain 403/419/429. These are transport representations, not permission expansions or changes to command validation.

Advantages: consistent client handling, no read-resource existence leak, explicit distinction between correcting input, refreshing state and uncertain confirmation. Disadvantages: client must preserve an uncertain outcome and correlation rather than blindly retry; 404 does not distinguish an absent resource from an inaccessible one. Impact: approved technical record/design/task clarification followed by thin existing local-session adapters and real HTTP-request tests. No change to domain specs or requirement adaptation is needed.

Alternative: keep HTTP mapping pending and retain verified internal command/read ports without mounting academic routes. Advantage: no unapproved transport choice. Disadvantage: 6.5 and U9 overall stay incomplete; frontend integration waits. In either case, no framework installation is inferred. Existing Symfony request/response and local SessionGuard adapter can test an isolated transport boundary; full Laravel kernel/server/LAN acceptance requires separately established runtime evidence.

Human response to the pending question is required before implementing 6.5. It does not automatically approve U10/U11 reference persistence, complete activity/submission processing, a production identity-name mapping, deployment, global gates or remote operations.

## Subsequent contextual approval

The human replied continuar to the final single-contract approval question. docs/u9-http-human-approval.md records approval before 6.5 code. The proposal above is preserved as history; the recommended mapping is now selected. Verification/global gates and later persistence remain separate.
