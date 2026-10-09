# Fictional Secondary School Data Verification

Date: 2026-10-08. Predecessor: df5b6f1. The human explicitly authorized invented data in the real local EVAL application, requested Internet research and specified sections A–H. This is an additive local data unit using existing approved commands, with no domain schema/frontend/authentication policy changes.

## Curricular reference and limits

The [MINEDU secondary program](https://www.minedu.gob.pe/curriculo/pdf/programa-curricular-educacion-secundaria.pdf) lists the curricular areas used as national reference. This sample includes Matemática, Comunicación, Ciencia y Tecnología, Ciencias Sociales, Desarrollo Personal/Ciudadanía/Cívica, Inglés como Lengua Extranjera, Arte y Cultura, Educación Física, Educación para el Trabajo and Educación Religiosa. Castellano como Segunda Lengua depends on institutional context and was not automatically assigned; Tutoría was not silently created as a graded subject. No verified Mercedes timetable or actual roster was provided. The A–H range comes from the human, and generated staffing/enrollment/course distributions are fictional.

## Added dataset

- Grades 1–5, eight sections each: 40 classrooms in the existing active local period.
- 90 fictional student accounts/enrollments and seven fictional teacher accounts. Display names explicitly end in (ficticio). The existing docente teaches the illustrative Math/Communication/Science scopes; other subjects use fictitious staff. No real names/DNI were imported and no existing profile, password or enrollment was replaced.
- 400 total sample course assignments across these classrooms: one existing assignment reused, 399 created. Existing original teacher/area/grade/section boundaries remain enforced by normal commands.
- Twenty named [Ejemplo] materials and twenty activities for 2.B and 2.H. PDFs are original one-page readable guides with a fictional-data marker, embedded text, standard font and valid cross-reference structure. No copyrighted school texts were copied.
- Fifty sample deliveries, forty A/AD assessments with explicit fictional feedback, and visible pending/ungraded states. The existing estudiante sees its own ten-area 2.B context and example grades; it cannot gain another section from this seed.

The script uses the current director and live actor credentials with existing catalog/account/assignment/enrollment/publication/submission/assessment commands and write guard. It is bound to the owned loopback eval_dev runtime. Generated secrets are neither printed nor committed. Existing administrator account maintenance can reset fictional account passwords if later required; the review opens the existing known teacher/student sessions. Data, uploads and machine receipts remain ignored.

## Executed checks

`manage-dev.ps1 seed-school-sample` completed with 97 new accounts, 399 assignments, 90 enrollments, 20 materials, 20 activities, 50 deliveries and 40 assessments. A second run produced zero new records in every category. Existing data were preserved; no period switch, institutional reset or deletion occurred.

`manage-dev.ps1 verify-school-sample` passed 19 actual browser checks using normal authenticated student/director sessions and the owned pinned certificate. Covers all ten student course labels in 2.B, downloadable readable sample PDF, delivery/grade consultation, every grade's A–H sections, fictional student report rows and selected tablet viewport overflow. The verifier is read-only. An initial helper evaluated material absence while the first request was still loading; it now waits for result readiness before paging.

PHP/Node syntax and Git whitespace checks passed. Existing application tests were not falsely presented as rerun for a data-only seed. No new dependencies or remote Git mutation. Authentication stays unchanged; DNI/PIN discussion is recorded in docs/student-dni-access-proposal.md and requires a separate approved policy.

Supported local commands: seed-school-sample; verify-school-sample; open -Role teacher -Sample; open -Role student -Sample. Existing owned role windows must be closed through the managed stop/start workflow before opening another window for the same role. The sample startup opens the real Materials course and scrolls to its example card; it does not use a mock role switch or fake API.

Final local snapshot: 31 tables / 17 migrations, 314 accounts, 16 periods, 483 assignments, 297 enrollments, 332 materials and write ordinal 2942, with TLS and restricted runtime. EVAL was restarted and existing student/teacher sessions opened directly at the sample material. No schema/authentication change or remote publication occurred.
