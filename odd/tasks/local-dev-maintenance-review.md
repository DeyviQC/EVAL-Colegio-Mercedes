# EVAL local MySQL maintenance review

Date: 2026-10-08 (America/Lima). Status: proposal only; no maintenance executed.

## Confirmed problem

The owned MySQL instance accepts the reviewed migration account over verified TLS at `127.0.0.1:3307`, but the account cannot create `eval_dev`, create users or grant its permissions. Credential-state ACL hardening is complete. The capability helper's 12 tests and the frontend's 80 tests/build pass.

Existing source establishes a scoped maintenance pattern: `backend/scripts/start-u1.ps1` can restart its verified isolated MySQL process with `--init-file`. Its current implementation and SQL are test-only and must not be invoked for this proposal. No usable administrator session has been established. Do not search for or try ambient root credentials.

## Preferred route if an administrator session is available

An authorized operator creates only `eval_dev` and two fresh loopback-only accounts (`eval_dev_migration` and `eval_dev_runtime`) on the verified instance. Passwords are generated and entered through a protected local mechanism, never chat, command-line arguments or a tracked SQL file. Grant migration privileges only on `eval_dev`; runtime grants follow the existing reviewed table/column restrictions after migrations. Do not grant global CREATE USER to the application or either dev account.

## Alternative requiring explicit restart authority

If no administrator session is available, prepare and review a dedicated one-time maintenance controller before executing it. The proposed operation is:

1. Verify the exact existing executable, datadir, process start time, owner and loopback listener through the established credential wrapper. Confirm that no EVAL preview/test job is active; refuse ambiguous ownership or active jobs rather than terminate them.
2. Generate fresh development passwords and protect a temporary initialization file and persisted encrypted configuration with current-user ACLs. Keep secrets outside served roots, command arguments and logs; add `/.local/` to ignore rules before writing project-local private state. Preserve a recovery record without secret text.
3. Stop/restart only the verified owned isolated MySQL instance with a dedicated initialization file. This temporarily interrupts all clients of that instance. Do not stop or configure the installed MySQL80 service, change ports or touch another datadir.
4. Initialization creates only `eval_dev` with `utf8mb4_0900_as_cs` and the two fresh development accounts. An unexpected existing database/account must trigger an explicit recovery review; do not DROP, RESET, overwrite passwords or reseed existing objects. No writes to `eval_u*_test` or `eval_auth_test` are authorized.
5. Verify startup and authenticated account binding/TLS, then remove the secret-bearing initialization file. Ordinary future starts must not include it. On partial success, retain created data/accounts and report the exact non-secret recovery stage; never delete them automatically.
6. Apply the existing migrations 000001-000010 to `eval_dev` through its new migration identity, establish table/column runtime grants without DDL or retained-history deletion, and provision the four synthetic development roles once. This requires adapting the dev-only database/runtime allowlist with meaningful test-first isolation checks; existing test wrappers remain unchanged.

This proposal does not authorize a restart or supply an executable controller. It does not expose EVAL to the LAN, install software, modify global certificate trust, create institutional accounts, push or commit. The database remains under the existing temporary datadir, so retention across app starts is not a backup or institutional durability guarantee.

## Completion after maintenance

Finish and verify T1 before T2. T2 supplies owned start/status/open/stop, a fixed HTTPS origin and an isolated browser profile. T3 proves four-role login and teacher-upload/student-download persistence across app stop/start. Until then, no stable EVAL URL or ready-to-use persistent application is claimed.
