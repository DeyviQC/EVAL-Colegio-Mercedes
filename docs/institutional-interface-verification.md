# Institutional Interface Verification

Date: 2026-10-09. The explicit human request replaces the live blue/mint direction with institutional navy/blue and the supplied school crest. The earlier prototype remains intact.

Delivered: reusable local crest/inline SVGs; split access with existing loading/session controls; extracted session presentation; collapsible sidebar, accessible destination labels, capability-derived profile and logout; breadcrumbs and existing notifications shortcut; actual period summary and published block/week cards; shared typography, controls, panels, report tables and responsive/reduced-motion styles. No new dependency, API/schema or domain command.

The HTTPS development proxy now admits bounded /assets/*.png paths under its existing containment and CSP. This is required for the same-origin crest, not broader filesystem serving. Browser fixture servers identify PNG correctly.

Executed verification:
- Frontend build and strict types passed; 170 existing frontend tests passed.
- Synthetic calendar browser: 20 checks; three fixture writes; zero external requests.
- Synthetic weekly browser: 19 checks; two fixture writes; zero external requests.
- Focused LocalHttpsRuntimeTest: eight discovered, two existing optional skips; 522 assertions, no failures.
- New read-only real-session institutional browser: 59 checks, all four profiles, logo decoding, collapsed accessible navigation, 1366/1024/768/390 overflow and 44px controls, actual published calendar blocks/weeks, logout; zero external requests and zero page errors.
- Existing real-session tablet regression: 31 checks, zero external requests, deferred maintenance and reduced motion verified; selected 6x CPU-throttled course selection observed at 376ms, not a hardware performance guarantee.
- Login, director home and mobile calendar screenshots visually inspected. Evidence remains ignored under .local/eval-dev/institutional-evidence.

Build: JS 402.92 kB / 112.67 kB gzip, CSS 26.36 kB / 6.24 kB gzip. Prior build: JS 398.26 kB / 111.15 kB gzip, CSS 27.04 kB / 6.66 kB gzip. Supplied crest: 24,881 bytes separately served. Native fonts; no external resources.

The loopback application was restarted through owned scripts to load PNG routing; database and files were retained. Profile text reflects confirmed capabilities because the own-account DTO contains no explicit role. Session status is not a heartbeat monitor. Unread count is not invented before the existing feed is consulted.

Physical tablets, school LAN/outage/load, full accessibility certification, institutional calendar dates, durable backup/restore and deployment remain open. This work is local/uncommitted, with preceding local changes preserved.

## Committed local handoff

The human explicitly requested a single local Git commit on 2026-10-09. Predecessor: aec01f8d723567e92f931759a5c7ff8dd94b6fc5. This handoff consolidates the verified institutional interface and preceding local prototype-alignment changes. Verification is recorded above; physical LAN/tablet, institutional calendar and operational acceptance gates remain open. Private runtime data, credentials, browser profiles and generated builds are excluded. No remote operation is authorized or performed.
