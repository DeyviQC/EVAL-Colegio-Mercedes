# Tasks: Course Materials

Draft planning only. Every checkbox starts open; the six human product/technical selections are inputs, not proof of implementation. No source, schema or execution is authorized by this file. Sequential local handoff workflow applies after explicit implementation approval; no push/apply/candidate integration.

Subsequent human approval authorizes implementation of the bounded milestone and resolves historical pre-entry exclusion, successor teacher consultation, exact 25 MiB/macro exclusion and delegated local DB/filesystem strategy. Earlier draft restrictions/technical alternatives are superseded only within this scope. Keep implementation checkboxes evidence-based; no full milestone acceptance is implied.

## 1. Review prerequisites

- [ ] 1.1 Review proposal and both specs against the six human decisions and institutional config; approve this new capability/design. Preserve academic-foundation and its existing open gate statuses.
- [ ] 1.2 Resolve historical pre-existing-material eligibility, later arrival after assignment closure and successor-teacher consultation; record exact operational evidence mapping with examples. Do not infer publication-time membership or availability overlap.
- [ ] 1.3 Pin retained display profile provisioning/limits, course DTO/current-history discovery/pagination and missing-name semantics without full user management or roster exposure.
- [ ] 1.4 Select exact 25 MB byte convention, metadata limits, supported-content/container validation and active/macro policy, protected file dispositions and response contracts.
- [ ] 1.5 Review filesystem/DB visibility, additive guard/ledger handling, uncertainty and private-orphan reconciliation/retention. Verify compatibility with foundation no-external-effects assumptions before adapting code.
- [ ] 1.6 Confirm applicable review budget and coherent unit sizes before execution; retain full test/evidence diffs. Verify isolated MySQL/storage/browser prerequisites and preserve unavailable school-network evidence explicitly.

## 2. U1: Minimal identity and real My Courses

- [ ] 2.1 Add test-first profile retention/name resolution and four-role/credential/course-query negatives, multiple-teacher identity, transfer/replacement and reviewed historical discovery scenarios.
- [x] 2.2 Implement only restricted retained visible-name profiles, provider and minimal course collection/detail queries after 1.1-1.3/1.6. No login-as-name, SIAGIE source, public profile API or account administration.
- [x] 2.3 Connect real teacher/student course cards and detail context with session clearing, empty/loading/error states; retain UI scope as presentation only. Prove the course bridge end-to-end, run foundation regressions and record a green local handoff before U2.

## 3. U2: Consistent publication and protected consultation

- [ ] 3.1 Add approved material schema/contracts and proving tests for metadata/FKs/original authorship, file signatures/limits and publication through owned operative assignment only.
- [ ] 3.2 Implement staged upload, reviewed guard/metadata/file finalization protocol and protected material/file queries after all applicable decisions. No public file path or client academic authority.
- [ ] 3.3 Prove current and reviewed historical student access, publication after transfer exclusion, replacement author retention, assignment/period closure, and unauthorized Director/Vice Principal requests absent a separate material role.
- [ ] 3.4 Prove actual storage-finalization/metadata failures, confirmed rollback cleanup, uncertain commit retention/no replay, crash-orphan reconciliation and real concurrent closure/replacement/transfer races. Include exact byte/hash verification.
- [ ] 3.5 Run supported backend regressions and publish scoped evidence/limits with green local handoff before U3; do not claim editing/deletion/supervision.

## 4. U3: Teacher publishes; student consults

- [ ] 4.1 Implement minimal Materials panel/form through real endpoints, protected open/download, validated DTOs and readable original context. Use no operational synthetic fixtures or client authorization engine.
- [ ] 4.2 Prove browser teacher My Courses -> course -> publish -> confirmed list, student My Courses -> matching course -> list -> open/download matching bytes, plus unrelated-course/direct-file denials and expired/uncertain/empty cases.
- [ ] 4.3 Verify keyboard/error focus and tablet sizing. Office files are downloads unless separately supported; don't claim native preview or external Internet-independent browser features without tests.
- [ ] 4.4 Run backend/frontend/typecheck/build/E2E checks, report actual diff and create only the authorized local handoff when green.

## 5. U4: Bounded acceptance

- [ ] 5.1 Consolidate approvals, versions, commands, exact test results, original context/history evidence and recovery limitations in one verification receipt for this change.
- [ ] 5.2 Execute available isolated local evidence; separately obtain school LAN/WLAN/tablet tests without external Internet and protected file consultation. Do not substitute loopback for physical acceptance.
- [ ] 5.3 Obtain explicit human acceptance of the real educational journey. Keep unresolved global load/deployment/foundation acceptance items visible rather than closing them by inference.

## Review size forecast

| Unit | Observable finish | Estimated additions + deletions | Relative size |
|---|---|---:|---|
| Review artifacts | Approved rules/technical mappings, no code | 250-400 | S-M |
| U1 | Real named teacher/student courses, UI and tests | 350-550 | M |
| U2 | Consistent publish/list/file/history with failure/race evidence | 550-900 | L |
| U3 | Complete two-role browser journey | 400-650 | M-L |
| U4 | Consolidated and physical acceptance evidence | 100-200 plus corrections | S-M |

These are forecasts, not authorization or progress measures. Under a 400-line threshold U1-U3 can require an explicit coherent-size decision before code. Historical removal of numeric limits must be reconciled with the applicable next-unit instruction rather than silently choosing an exception. Do not split DB/file consistency into independently unsafe partial deliveries; if a genuine independent transport/UI split is proposed, retain each unit's full proving evidence. One bounded unit finishes/tests/commits locally before the next begins.

## Recorded deferred product backlog

Material edit, attachment replacement and withdrawal/deletion remain required future lifecycle work. Units/sessions need a separate structure decision. Vice Principal material review and private observations need their own rule set; direct publication does not imply pre-approval. Director material intervention remains undefined. Activities/submissions, evaluation, library/search, presentation and synchronization are not simultaneous implementation units in this change.

## U1 implementation receipt

Real profile/My Courses bridge: 2.2/2.3 verified for own teacher contexts and student operative scope. Additive retained_identity_profiles migration ran only on isolated eval_u11_test; runtime is read-only on profiles, provisioning uses migration identity. Missing names fail closed. No student general-assignment permission is expanded. Publication-time historical material discovery and successor material consultation remain U2; 2.1 stays open for those cases. No global foundation gate closure.

Verification: frontend 70 tests/typecheck/build pass; U11 80 tests / 1,556 assertions, zero failures/errors and one intentional optional-preview skip; embedded Chromium 9 passed including two independently authenticated teacher/student sessions opening the actual stored course. MySQL 8.4.9 / PHP 8.4.26. Run frontend npm test/build and the explicit browser U11 invocation recorded in docs/u12-browser-verification.md. Publication UI remains explicitly unavailable; milestone is not complete. U2 forecast remains 550-900 changed lines; size approval is required under the applicable 400-line review threshold before that coherent unit begins.
