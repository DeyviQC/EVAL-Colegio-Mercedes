# EVAL

EVAL is an institutional hybrid educational web platform for I.E. Nuestra Señora de las Mercedes. Its essential student functions are intended to remain available over the school's LAN/WLAN without external Internet, provided the local server, network, and power remain available.

## Planned technology

- Windows Server
- PHP with Laravel
- MySQL
- React with TypeScript
- Tailwind CSS
- IIS or Apache (decision pending)

This stack is planned architecture, not detected implementation.

## Repository layout

| Path | Purpose |
| --- | --- |
| `.github/workflows/` | Reserved for future automation after tooling is defined. |
| `docs/requisitos/` | Requirements documentation. |
| `docs/arquitectura/` | Architecture documentation and decisions. |
| `docs/prototipos/` | Approved prototype artifacts. |
| `docs/pruebas/` | Test strategy and evidence. |
| `frontend/` | Future React and TypeScript application. |
| `backend/` | Future Laravel application. |
| `database/` | Future database assets. |
| `openspec/` | OpenSpec specifications and change artifacts. |

## Current status

The repository is in pre-implementation. Requirements, architecture, and prototypes must be approved through the OpenSpec workflow before application scaffolding or source code is introduced.

## Scope boundaries

- EVAL does not replace SIAGIE.
- QR attendance is outside the initial MVP.
