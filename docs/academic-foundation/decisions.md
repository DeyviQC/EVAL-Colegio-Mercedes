# Final functional delivery decisions

Source academic specs: commit 8811561c85f3f5382fc6a0656b62005643c194b4. The original planning artifact is retained unmodified; its original checkbox gates are not automatically certified by this delivery.

The user authorized completing the previously requested educational functionality after migration to Laravel/MySQL/React. Materials, assessments, notifications, account administration and activity availability are therefore implemented in a separate Education module, not silently redefined as academic-foundation requirements.

- Product name: EVAL.
- Immediate transfer/replacement with immutable prior identity, original route and same-day operational keys.
- Closed periods: retained read access; writes unavailable. Closure does not cascade to children.
- Exact Unicode-sequence name equality; no implicit trimming, accent folding or case folding of catalog/period names.
- Submission edits: allowed only before grading, while the task is open and the original accepted-under enrollment is still active. Original acceptance identity, route, time and key never change.
- Grading after teacher replacement: only the original teacher may process the original routed work in an active period. The replacement teacher gets no ownership of old deliveries.
- Published material requires no approval. Scope-authorized teacher editing and director removal are recorded. Private attachments are downloaded through authenticated, scope-checked routes.
- Explicit activity closure prevents new deliveries through both the educational and minimum-reference endpoints. Closing an assignment does not close its activities.
- Account deactivation/password resets revoke stored sessions without deleting retained identities or academic relationships.
- Local live refresh is read polling; no automatic mutation retry, no remote synchronization and no external identity service.

The local launcher configures 10 MB uploads and a workspace-local writable temporary directory. Frontend assets are compressed locally; response lengths are finalized after middleware and development browser-log injection is disabled. The final UI does not depend on an external asset CDN.

Deployment to Windows Server, IIS/Apache choice, institution-certified LAN/tablet load acceptance, remote synchronization, SIAGIE integration, QR attendance and scheduled/retroactive academic changes remain outside this functional delivery. The original prototype database is preserved; importing real historical records requires an explicit mapping when their original context is incomplete.
