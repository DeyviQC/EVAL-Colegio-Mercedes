# EVAL backend

Laravel 13 / PHP 8.3+ / MySQL 8.0 academic foundation.

See ../README.md for installation and ../docs/academic-foundation for verified behavior and remaining decisions.

- php artisan migrate --env=migrations: execute with a separately configured migration account.
- php artisan db:seed --class=AcademicDemoSeeder: populate a new demo database without overwriting existing periods.
- php artisan serve --host=127.0.0.1 --port=8081: serve the React production client and authenticated API.
- php artisan test: run real-MySQL feature/race tests and unit tests using .env.testing.

HTTP session routes: GET /api/session, POST /api/login, POST /api/logout.
Academic projection: GET /api/academic; vice-principal support accepts a concrete section_id.
Commands: POST /api/academic/{command}; whitelist validation, CSRF and local session authentication apply.

No runtime account should receive migration or ledger UPDATE/DELETE privileges. Never commit .env files, and never expose the repository root as the public web directory.
