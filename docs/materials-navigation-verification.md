# Materials Navigation Reconciliation

Date: 2026-10-08. This completes presentation integration of the human-accepted collaborator Materials section against the already approved course-materials capability. It introduces no new material lifecycle, library/search behavior or role authority.

Teacher and student now have a standalone Materials navigation item. Its course selector uses the existing server-owned My Courses collection and its pagination. Opening a course confirms its detail through the existing endpoint before opening the real Materials panel. Publication, historical eligibility and protected downloads continue to use the existing server services. My Courses remains available; its nested button is named Ver materiales to distinguish it from the navigation item. Separate React keys clear course selection when changing between the two workspaces.

No database schema, credential, grant or backend policy changed. No library placeholders or synthetic file listings were introduced. The verifier was adapted to enter through the new navigation, and the account verifier's existing nested-course locator was updated.

Executed: 115 frontend tests passed; application/tooling TypeScript and Vite build passed; Node syntax checks passed. The real Chromium verifier passed 14 checks across four roles, including teacher/student standalone navigation, real retained material listing, byte-identical teacher/student PDF downloads, student publication restriction, director/vice-principal absence of material navigation, student administrative denial and unpinned certificate rejection. The teacher screenshot was visually inspected. Browser fixtures reuse retained development materials, not institutional records. No new server mutation, fault-injection or load suite was needed for this presentation change.

Dedicated history presentation, library/search, material editing/deletion, supervision, durable database relocation and institutional backup/LAN/load acceptance remain pending. Changes remain local and uncommitted; no other contributor or remote Git operation was started.
