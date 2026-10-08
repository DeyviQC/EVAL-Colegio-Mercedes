# EVAL

EVAL is an institutional hybrid educational web platform for I.E. Nuestra Señora de las Mercedes. Its essential student functions are intended to remain available over the school's LAN/WLAN without external Internet, provided the local server, network, and power remain available.

## Planned technology

- Windows Server
- PHP with Laravel
- MySQL
- React with TypeScript
- Tailwind CSS
- IIS or Apache (decision pending)

The development checkout now contains PHP/Laravel, MySQL, React/TypeScript and Tailwind implementation. Windows Server deployment and IIS versus Apache remain planned and unverified.

## Repository layout

| Path | Purpose |
| --- | --- |
| `.github/workflows/` | Reserved for future automation after tooling is defined. |
| `docs/requisitos/` | Requirements documentation. |
| `docs/arquitectura/` | Architecture documentation and decisions. |
| `docs/prototipos/` | Approved prototype artifacts. |
| `docs/pruebas/` | Test strategy and evidence. |
| `frontend/` | React/TypeScript application, typed clients and UI tests. |
| `backend/` | Laravel academic services, local HTTP runtime, migrations and tests. |
| `database/` | Future database assets. |
| `openspec/` | OpenSpec specifications and change artifacts. |

## Current status

The repository has a real isolated development application with local authentication, academic foundation, Director period/catalog/enrollment UI, Director/Vice Principal assignment management, bounded teacher/student account management, course materials, and real activities/deliveries with retained versions and private evidence files. It is not a complete institutional MVP. OpenSpec approvals and remaining acceptance gates still govern new work.

Double-click `Abrir EVAL.cmd` in the repository root. Teacher, student and Vice Principal launchers open separate role sessions. The app uses persistent `eval_dev` data at `https://127.0.0.1:8443`, not a prototype or a PHPUnit preview. See [local development](docs/local-development.md) for commands, credentials access, verified behavior and storage limitations.

Original-teacher AD/A/B/C assessment, required feedback, retained corrections and locked assessed deliveries now work locally. Students consult their own results in My grades. See `docs/delivery-assessment-verification.md` for executed database/browser checks and remaining acceptance.

Director classroom reports now show complete delivery/grade totals and a bounded historical roster using real data. See docs/classroom-report-verification.md.

Academic notifications now persist activity publication, delivery/update and grading/correction events for their actual recipients. The own feed supports unread counts, explicit refresh, 50-row paging, section shortcuts and bounded read receipts. See docs/academic-notification-verification.md.

Pending work includes wider administrator/role management and institutional import, material editing/deletion and supervision, report export, library/search, synchronization, backup/restore and school-network/load acceptance. Current test evidence and remaining gates are recorded in OpenSpec tasks and `docs/local-development.md`; see `docs/activity-delivery-verification.md` for the activity/delivery journey.

## Scope boundaries

- EVAL does not replace SIAGIE.
- QR attendance is outside the initial MVP.
