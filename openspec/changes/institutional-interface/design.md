# Design

InstitutionalUI provides SchoolBrand, inline Icon, NavButton and Spinner. InstitutionalShell owns presentation state and existing credential form behavior. SessionShell retains account/session/navigation controllers and supplies the profile and notification callback. No authentication policy is moved into presentation components.

The account DTO does not expose a role. The profile caption is derived from confirmed capabilities: can_manage, own assignments, own enrollments or assignment management; it is not authority or a role editor. The green status caption identifies a confirmed local session, not an active health-monitoring guarantee. Unknown unread count remains unknown until the existing notification feed is consulted.

CalendarWorkspace reuses existing state and clients. Display-only cards render current.blocks and weeks; the period selector remains authoritative. Existing revision and editing controls stay in place. The supplied 140x200 PNG is displayed without upscaling, preserving its proportions. PNG serving extends the development proxy's filename/type allowlist and existing realpath containment, retaining same-origin CSP.

Shared styles cover all existing academic panels, forms, controls, details, tables, state messages and course cards. Layout uses 248px desktop sidebar, 208px medium sidebar, optional 84px collapse and mobile disclosure under 700px. Existing touch/reduced-motion requirements and API behavior are preserved.
