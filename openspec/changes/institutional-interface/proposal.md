# Institutional Interface

Date: 2026-10-09. Human authorization: supplied NSM.png crest and explicit React/Tailwind institutional redesign request, superseding the blue/mint visual direction for live application presentation.

Use navy, interaction blue, subtle amber, white panels and slate backgrounds. Replace layered CSS with one shared visual system. Extract the existing session panel into a presentation component; retain transport, session and authorization controllers. Add local crest, inline SVG icons, split login, collapsible navigation, account profile, breadcrumbs and existing notification shortcut. Present stored period summaries and published calendar blocks/weeks as cards without changing dates or publication semantics.

No new dependencies, remote assets, role-switching authority, synthetic statistics or backend domain/schema changes. The local HTTPS asset allowlist must admit PNG to serve the supplied crest under the existing same-origin CSP; no arbitrary file serving is added. Existing API clients and write/uncertainty behavior remain unchanged.

Verification and remaining gates: docs/institutional-interface-verification.md. Local-only delivery; no commit, push or deployment requested.
