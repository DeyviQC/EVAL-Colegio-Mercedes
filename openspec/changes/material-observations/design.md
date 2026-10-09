# Private Material Observations Design

## Access and screen composition

Add a purpose-bound supervision workspace to the existing accepted institutional shell for vice principal. Reuse authorized assignment context labels; never reuse a student roster or expand general education-course authorization. Provide separately guarded supervision material metadata/revision/file queries. Files retain existing validation, private/no-store, MIME/disposition and safe filename behavior. Teacher observes private feedback from a nested original-material panel; server flags determine reply availability.

## Persistence and mutation

Add immutable observation records containing material, observed revision/base reference, creator, automatically derived original recipient, text and creation boundary. Add retained reply/review events and a guarded current workflow pointer. Foreign keys and immutable context constraints preserve publication/author/revision relationships. Narrow runtime insert/pointer grants; no deletes or changes to original material rows.

Serialize mutation using AcademicWriteTransaction and relevant period/assignment/identity locks, then observation-state locking. Revalidate original assignment/period, recipient, role and expected revision/state inside the guard. A teacher reply binds the material revision actually consulted so it cannot silently refer to a newer concurrent edit. Vice-principal completion references the retained reply and expected state. Store audit context without putting private texts into general public projections or notifications.

Read predicates restrict observation IDs before resolving bodies/labels. Students/director/unrelated teachers receive no private projection. Original teacher may retain read access after replacement/closure; successor consultation of material does not grant observation access.

## Client/recovery

Typed exact projections and decimal IDs, labeled plain-text forms, explicit confirmation for review completion. Display current workflow and the observed revision snapshot separately from the current material. Successful matching private consultation qualifies explicit review of an uncertain operation; failed/unrelated/earlier reads do not. No optimistic status transition, implicit material edit or replay.

## Required verification

Actual vice-principal/teacher/student/director HTTP/browser flows; original-recipient routing; unrelated/successor denial; current/base and stale revisions; material edits preserving observation context; reply/review state and duplicate rejection; revoked credentials; replacement/closed-parent historical reads with write denial; private bodies absent from student/public projections and notifications; rollback and two-connection conflicting writes; 50-row paging; protected supervisor file access; existing material maintenance/Library regressions.
