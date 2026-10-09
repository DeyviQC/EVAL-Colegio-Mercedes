# Lightweight Tablet Interface Verification

Date: 2026-10-08. Predecessor: 6f6b79f. The human explicitly requested a more intuitive overall frontend, tablet adaptation and lightweight animations based on Internet research, then selected a conservative design because actual tablet specifications are unknown. This is presentation refinement of the accepted EVAL screens and existing role-authorized journeys, not a new domain-policy unit.

## Delivered refinement

The institutional shell now uses consistent blue controls, clearer spacing/input borders/focus and active-section context. Desktop keeps the sidebar; tablets use a compact sidebar; narrow screens use an explicit Secciones menu that closes after selection. Existing authorized destinations remain. The initial screen explains where to begin without inventing dashboard statistics. Main buttons/action links/option summaries have 44 CSS pixel minimum height. Material/Library cards adapt to one column at tablet widths.

Teacher material maintenance is inside native Opciones del material. The existing maintenance panel mounts only when opened. On the verified course, zero maintenance requests occurred before opening an option and exactly one occurred for the selected card, instead of an automatic request for every card. Shared client uncertainty/review barriers remain; opening options never repeats a write. File actions and private observation consultation preserve existing authorization.

Finite 120 ms press feedback and a 160 ms initial entrance use transform/opacity, only with no motion-reduction preference. Reduced motion disables animations and transitions. System fonts and existing packages remain; no remote fonts/images, animation/UI framework, video, blur, continuous animation or blanket will-change were added.

## Research rationale

- [Google web.dev animation guidance](https://web.dev/articles/animations-guide): prefer transform/opacity, avoid unnecessary layout/paint effects and persistent layer promotion.
- [Android adaptive layout/navigation guidance](https://developer.android.com/design/ui/mobile/guides/layout-and-content/layout-and-nav-patterns): adapt navigation to available space and retain clear labeled destinations.
- [W3C target size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum): minimum target/spacing guidance. EVAL's selected 44 pixel main target is a project usability goal above the minimum, not universal compliance certification.
- [W3C reduced motion](https://www.w3.org/WAI/WCAG22/Techniques/css/C39.html): honor motion preference while preserving functionality.

## Executed checks

- Frontend: 151 tests passed; TypeScript/tooling and Vite build passed. No new dependency. Built JS changed from 365.28 kB / 102.60 kB gzip to 367.12 kB / 103.14 kB gzip; CSS from 16.26 kB / 4.15 kB gzip to 21.58 kB / 5.52 kB gzip. Combined compressed increase is approximately 1.91 kB, using Vite's rounded figures. This is artifact size, not physical tablet transfer speed.
- Real local Chromium: 31 read-only checks across teacher/student/director/vice principal at 768x1024, 1024x768, 600x960 and 360x800. No horizontal document overflow in the selected journeys, visible main control heights verified, section toggle/active label, deferred requests, keyboard skip-to-main and reduced-motion behavior passed. All browser requests stayed on the owned origin; external requests counted zero.
- A selected student Material course-selector navigation took 270 ms with Chromium CPU throttling set to 6x in the final run. This is one local emulated journey, including automation/local-network timing; it is not INP, a benchmark of the state-issued tablets, or a guaranteed latency.
- Existing actual browser regressions passed: material maintenance 22 checks, retained file replacement 16 checks and Library 19 checks. Helpers now explicitly open the real disclosure before using secondary controls. Existing role/API/unknown-result checks remain effective.
- Browser helper syntax and Git whitespace checks passed. No backend domain/schema changed, so the previous U11 99-test receipt is retained as previous evidence rather than presented as rerun for this styling unit.

Final development snapshot: TLS/restricted runtime, 31 tables / 17 migrations, 217 accounts, 16 periods, 84 assignments, 207 enrollments, 312 materials and ordinal 1775. Workflow verifiers published two named synthetic materials; the tablet layout verifier itself is read-only.

The owned application was stopped and restarted, then teacher/student windows opened and authenticated. The aggregate database snapshot remained identical across restart.

Private portrait/landscape/mobile screenshots and machine receipts remain under ignored .local/eval-dev. Teacher portrait and mobile account screenshots were visually inspected; adjacent secondary buttons were subsequently given horizontal spacing. All material fixtures from workflow regressions remain retained. No database reset, credential publication or remote Git operation occurred.

## Remaining acceptance

Test on an actual school tablet: browser/Android compatibility, keyboard/input behavior, large course lists/files, battery/memory and repeated navigation over the real LAN. Unknown hardware cannot be certified from desktop emulation. This unit also does not certify full accessibility, all possible viewport/content combinations, power-loss durability, institutional load or school deployment. Continue refining specific complex forms/flows using observed user difficulty; styling alone does not complete institutional functionality.

## Human blue/mint visual reference refinement

On 2026-10-08 the human supplied an educational mobile-app image and asked for a similar appearance. Applied a bounded CSS refinement to the real EVAL login, welcome, active navigation and card palette: blue/mint accents, static soft gradients and rounded surfaces. Existing authorized navigation and domain functionality stay in place; the image's external meeting/schedule features are not inferred requirements. No image asset, dependency, blur or new animation was added. Build/type checks passed; compressed CSS increased from 5.52 to 5.75 kB, with unchanged compressed JS (103.14 kB). The supported tablet verifier was rerun for this visual refinement. Actual-device acceptance remains open.
