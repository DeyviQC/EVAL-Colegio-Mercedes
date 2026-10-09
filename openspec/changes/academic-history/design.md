# Design

GET /academic/history with kind=enrollments|assignments and optional canonical after cursor. Server checks live credentials, active identity and stored roles. Exact owner filtering precedes pagination; at most 50 ascending records with continuation. Reuse existing directory projections and decoders. Explicit consultation and next/start page controls, no mutation actions. Existing authorization and accepted screens are the baseline; closed periods remain discoverable.

## Delivery context presentation

Explicit user approval: Continue after the concrete original-context integration recommendation. Each authorized delivery card offers a read-only context panel through the existing /academic/submissions/{id} endpoint and exact historicalSubmission decoder. Show the original assignment teacher/subject, accepted enrollment period/grade/section and declared intervals. Recheck on each consultation; no authority inferred from displayed history. No schema, new discovery endpoint or payload/file permission. Errors clear prior context and support explicit retry.
