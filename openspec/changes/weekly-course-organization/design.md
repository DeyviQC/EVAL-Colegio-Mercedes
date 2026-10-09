# Weekly Navigation Design Proposal

Use the accepted EVAL blue/mint direction with labeled course tabs. The synthetic variant initially opens Weeks. Each native details/summary section shows the week label, illustrative dates, topic and counts; its contents distinguish Materials from Tasks. Keep a visible Unassigned section. Existing Materials, Activities and Deliveries views remain reachable.

The independent preview uses local assets and fictitious content only. It does not create weeks, alter real content or infer a current calendar week. Existing preview behavior remains separate in index.html.

Implementation requires approved week semantics and a reviewed data/API design. Week membership is presentation metadata, never an authorization boundary or substitute for original assignment context. Optional due dates remain informational under the approved activity capability; grouping must not close activities, modify deadlines or reroute deliveries. All content must be filtered by existing server authority before display and counts must describe only authorized content.

## Calendar-based direction for review

The independent preview now includes Go to week and Show only weeks with content controls. Direct week selection switches blocks, clears the content-only filter, expands the destination and focuses its summary. The filter retains general Unassigned resources and explains blocks without sample content. Both controls reset on course entry. These discovery refinements are proposals pending human acceptance; real data association and delivery-status shortcuts remain outside this preview.

Following national-calendar research, propose a shared institutional calendar rather than teacher-defined week dates. The school confirms dates from its PAT and applicable DRE/UGEL guidance for each academic year. Teaching blocks and management blocks must be distinct; reporting periods may be bimesters, trimesters or another approved arrangement and must not be inferred solely from the national four-block table.

Proposed student path: Course → Weeks → teaching week with date range → materials and tasks. Continuous teaching-week numbering across the year is a proposed display convention, not a MINEDU requirement. Management blocks do not increment teaching-week numbering. Unassigned resources remain visible. During management time, display that calendar context and retain manual access to previous teaching weeks; do not invent a current teaching week.

Proposed teacher path: select an existing institutional teaching week while publishing or maintaining eligible course content. A week association does not change the publication date, due date, open/closed state or submission route. Original-teacher activity authority and the separately approved material-maintenance authority remain applicable; no new successor rights are implied.

No automatic association of existing resources is proposed: publication date does not establish the pedagogical week intended by the teacher. Calendar adjustment rules, treatment of already-associated content, association cardinality and editing authority require explicit review before implementation. No calendar change may rewrite retained academic ownership or delivery history.

The human identified the school in Huamanga, Ayacucho. MINEDU Identicole confirms the matching institution at Avenida Las Mercedes 351 under UGEL Huamanga; see docs/school-calendar-research-2026.md. The approved school calendar and reporting-period structure remain unconfirmed. Use neutral Block labels for any national-reference preview until institutional bimester/trimester naming is confirmed. The current weekly preview remains illustrative.
