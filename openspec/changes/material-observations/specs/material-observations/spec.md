# Private Material Observations

## Proposed requirements

- Vice principals SHALL review material content through purpose-bound authorization and SHALL create private observations tied to the exact current material revision in active original academic context.
- Observation recipient SHALL be the immutable original material author. Clients SHALL NOT select a recipient or supply academic authority.
- Only authorized vice principals and the original teacher SHALL read private bodies, reply/review evidence and statuses. Successor material consultation SHALL NOT grant this private access.
- Observada SHALL transition to Respondida only through the original teacher's validated plain-text reply, and Respondida to Revisada only through explicit vice-principal review. Transitions SHALL retain prior evidence and reject stale/duplicate requests atomically.
- Replacement or closed original assignment/period SHALL retain role-bounded historical reading and SHALL deny new observation/reply/review writes.
- Observations SHALL NOT appear in student materials, Library, submissions, grades or generic notifications. They SHALL NOT change publication/withdrawal behavior or imply prior publication approval.
- All writes SHALL use CSRF, current role/credential validation and guarded expected revision/state. Unconfirmed writes SHALL NOT replay automatically.

## Acceptance scenarios

1. Vice principal observes an exact current revision; original teacher reads/responds; vice principal explicitly reviews.
2. A concurrent material edit makes the observer's expected revision stale; no private record is created.
3. Another teacher, successor, student or director cannot fetch a private observation by its known ID or obtain its body in a public projection.
4. Reply/review racing from the same predecessor yields one accepted event and one stale denial, retaining consistent evidence.
5. Replacement or period closure freezes writes without changing original recipient or deleting the historical record.
6. Lost response requires consultation of that observation before a manual next action; no duplicate comment/reply is submitted automatically.
