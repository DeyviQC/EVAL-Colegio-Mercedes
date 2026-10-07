# EVAL

Institutional academic platform for I.E. Nuestra Señora de las Mercedes. This workspace implements the academic foundation and migrated educational modules based on commit 8811561c85f3f5382fc6a0656b62005643c194b4 and the user-authorized functional scope.

## Current implementation

- PHP 8.3+ and Laravel 13 backend; locally verified with PHP 8.4.25 and Laravel 13.35.0.
- MySQL 8.0.46; academic integrity tests run against real MySQL, not SQLite.
- React 19.2.8, TypeScript 6.0.2, Tailwind 4.3.3 and Vite 8.3.3.
- Local session authentication with CSRF protection and login throttling.
- Periods, active/inactive catalogs, period-bound enrollment, independent teaching assignments.
- Immediate non-destructive student transfer and teacher replacement, append-only lifecycle evidence, original assignment routing.
- Director management, assignment-specific vice-principal authority, teacher-owned assignments and student-owned enrollment/submission history.

Materials, private attachments, classroom library, AD/A/B/C grading, feedback, notifications and account administration now run in Laravel/MySQL/React. They are implemented separately from the academic-foundation module. The original PHP/SQLite prototype remains intact as a historical reference; old records lacking authoritative enrollment/assignment context are not silently reclassified as new acceptances.

## Repository structure

```text
backend/                Laravel application, MySQL migrations and PHPUnit tests
frontend/               React/TypeScript client and Playwright browser tests
openspec/               Source specifications and implementation status
docs/academic-foundation/ Decisions, traceability and verification evidence
scripts/                Launchers and distribution packaging
app/, public/, resources/, config/, database/, tests/
                        Preserved plain-PHP prototype
storage/                Private prototype data and local development setup
references/             Unmodified upstream checkout and prior artifacts
dist/                  Generated distributions; not source or credentials
```

Only backend/public is served for the new application. Never serve the repository root.

## Run the configured local workspace

Double-click **Abrir EVAL.cmd** or run `./scripts/start-laravel.ps1`. Open http://127.0.0.1:8081/.

The development MySQL instance is isolated under storage/mysql-development and listens only on 127.0.0.1:3307. It does not replace or change the installed MySQL80 service. Its migration and runtime credentials are local ignored files; they are never included in a ZIP. Runtime cannot UPDATE or DELETE the lifecycle ledger. The isolated development server disables binary logging; a production migration account must have the permissions required for the integrity triggers on its approved server configuration.

The retained prototype can be started with `./scripts/start.ps1` at http://127.0.0.1:8080/login.php.

## Install on another computer

Prerequisites: PHP 8.3+ with pdo_mysql, mbstring and standard Laravel extensions, Composer, Node 24 and MySQL 8.0 with enforced CHECK constraints.

1. In backend, run `composer install`.
2. Copy backend/.env.example to backend/.env, configure a dedicated MySQL database and migration credentials, then run `php artisan key:generate`.
3. Run `php artisan migrate` and, on a new demo database, `php artisan db:seed --class=AcademicDemoSeeder`.
4. Configure a distinct runtime database account followingdocs/academic-foundation/runtime-grants.sql; change backend/.env to that account after migrations. Keep migration credentials in an ignored backend/.env.migrations if needed; use `php artisan migrate --env=migrations` for future migrations.
5. In frontend, run `npm ci` and `npm run build`. Compiled local assets are placed in backend/public/client.
6. Start with Abrir EVAL.cmd, or run `php artisan serve --host=127.0.0.1 --port=8081` inside backend.

Framework dependencies and a MySQL server are not bundled into the source ZIP. A reviewed data import is required before replacing an existing installation with real academic history. IIS versus Apache and Windows Server production deployment remain undecided.

## Demo accounts

Initial password: `Mercedes2026!`. The seed creates 5 grades, 25 sections (A–E), 250 enrollments and 150 independent assignments, with six teachers teaching one area across all sections.

| Role | Email |
| --- | --- |
| Director | director@eval.test |
| Vice principal | subdirector@eval.test |
| Mathematics teacher | docente@eval.test |
| Communication teacher | comunicacion@eval.test |
| Science teacher | ciencia@eval.test |
| Social sciences teacher | sociales@eval.test |
| English teacher | ingles@eval.test |
| Arts teacher | arte@eval.test |
| Student in 3° A | estudiante@eval.test |

Other students: alumna.1a.01@eval.test, etc. Demo seed does not overwrite an existing academic period. Keep real users and credentials separate from demo data.

## Verified commands

- backend: `php artisan test` — 29 tests / 176 assertions locally, including MySQL race tests and a runtime-role permission probe.
- backend: `php vendor/bin/pint --test app/Academic app/Http/Controllers/AcademicController.php tests/Feature routes database/migrations`.
- frontend: `npm run build`, `npm run lint`, `npm run test:e2e`, `npm run format:check`.
- root: `./tests/flow.ps1` — retained prototype flow.

Tests require a separate MySQL database ending in _testing, configured in ignored backend/.env.testing and migrated before running. The concurrency worker refuses non-testing databases. Runtime permission probing additionally needs DB_RUNTIME_USERNAME and DB_RUNTIME_PASSWORD in the testing environment. Playwright uses installed Microsoft Edge; browser scenarios block outbound requests except localhost/127.0.0.1. They use the development demo and retain uniquely named test activities/submissions.

`php scripts/package.php` generatesdist/EVAL-laptop.zip, including source, locks, compiled client and the preserved prototype snapshot, while excluding environment secrets, MySQL data directories, vendor, node_modules, logs and previous deliveries.

## Product decisions and limitations

Seedocs/academic-foundation/decisions.md and acceptance.md. Transfers/replacements are immediate. Arbitrary future/retroactive changes and closed-period child operations remain unavailable pending their product rules. Names use exact stored Unicode sequences: case, accents and spaces are significant. Periods are read-only after closure; children retain history without automatic cascading changes. Transfers and replacements are immediate. Scheduled and retroactive operations are not part of this delivery.

No institutional production SLA, real tablet LAN/WLAN acceptance, remote synchronization, SIAGIE replacement or production readiness is claimed.

## Educational workflow

- Teacher publishes material directly, with text or PDF/JPG/PNG/TXT/DOCX attachments up to 10 MB. No director approval is required.
- Teacher edits own material; director can remove material. Removing a material retains its audit row and removes its physical attachment after commit.
- Student sees the classroom library and sends an answer, an attachment, or both. Ungraded work may be edited while the activity is open and the original enrollment remains active.
- Teacher receives an internal notification, sees the roster and submissions, and assigns AD/A/B/C with feedback. Original teacher ownership survives replacement; the replacement teacher cannot inherit or grade old submissions.
- Student receives a notification and sees grades/comments even after a transfer. Graded work cannot be overwritten.
- Teacher may specify a due date and explicitly close a task. Assignment closure alone does not cancel existing tasks. Both submission entry points reject explicitly closed activities.
- Director creates usable accounts and student enrollment together, updates account details, resets passwords and deactivates access without deleting academic history. Users may change their own password.
- Open visible pages refresh read data every 15 seconds. This shares the local MySQL state between accounts; it is not LAN/remote replication. Writes are never automatically retried.

Use the launcher or ./scripts/start-laravel.ps1 from the VS Code terminal. The launcher configures a writable upload temporary directory under backend/storage/app/tmp, upload_max_filesize=10M and post_max_size=12M. Default PHP settings may limit uploads to 2 MB when starting a separate server manually.

Local read-load probe: php artisan eval:load --requests=50 --concurrency=5 (backend). It performs authenticated reads only against localhost and reports measured results; it does not assert an institutional production threshold.
