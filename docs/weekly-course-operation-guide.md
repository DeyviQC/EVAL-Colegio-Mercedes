# EVAL Weekly Course Operation Guide

This guide describes the implemented local interface. UI labels are quoted in Spanish. Institutional use requires the school's approved calendar and physical network acceptance; the national template is only a reference draft.

## Direction / administrator

1. Open **Calendario institucional** and select the existing academic period.
2. Prepare a draft with dated teaching and management blocks. Teaching blocks contain numbered weeks; management blocks do not contain teaching weeks.
3. For a 2026 period, **Usar referencia nacional 2026** fills an editable reference. Check it against the approved PAT and applicable DRE/UGEL adjustments before saving. The template does not establish the school's bimesters or trimesters.
4. Select **Guardar borrador**, review the saved dates, and enter the approved calendar's source/reference.
5. Confirm that the saved draft corresponds to the approved institutional calendar, then select **Publicar calendario**. Publishing the calendar does not activate or close the academic period.

For corrections, create a new draft and publish a new revision. Existing resources retain their prior week association; teachers reassociate them explicitly when appropriate. Published revisions remain retained. Closed periods allow consultation only. Vice principals do not acquire calendar publication authority through this feature.

## Teacher

1. Open **Mis cursos**, enter the assigned course and select **Semanas**.
2. Find the published resource in **Sin semana**, its assigned week or **Calendarios anteriores**.
3. Select **Organizar por semana**, choose a week from the current published calendar or **Sin semana**, then select **Guardar semana**.

Resource publication and week organization are separate operations. Publish materials and activities through their existing views; organize the confirmed resource afterward. Organization does not change files, title, original author, due date, activity state, delivery route or assessment. Only the original teacher under an active assignment and period may change the association. A successor teacher's material reading permission does not grant organization authority over predecessor resources.

If an association response is lost, use **Consultar resultado de la semana**, inspect the confirmed version and association, then select **He revisado el resultado; continuar**. Do not republish the resource to retry organization. A stale association/calendar error requires consultation of the latest context before another explicit change.

## Student

Open **Mis cursos → Entrar al curso → Semanas**. Use **Bloque** and **Ir a la semana** to navigate; dates identify each week. **Filtrar contenido** filters resources without hiding week navigation. **Sin semana** contains general and unorganized resources. **Calendarios anteriores** preserves resources assigned under earlier calendar revisions.

**Descargar material** uses the existing protected file route. **Abrir actividad** opens the existing activity and delivery interface. A past teaching week or deadline alone does not close an activity; submission eligibility remains controlled by the original activity and enrollment context. Materials and tasks have independent **Más…** controls; displayed totals come from all authorized results, not just the first page.

If the course has no published calendar, the interface displays an explicit not-configured message. Existing resources remain available through **Sin semana** and their usual views. Students cannot change calendar or resource associations.

## Acceptance on the institution's devices

These are pending physical checks, not results claimed by local browser simulation:

1. From a student tablet connected to the institutional LAN/WLAN, open the server's approved local address and authenticate.
2. With external Internet disconnected but server, power and local network available, consult a course, select a configured week, download a material and open a task.
3. Verify that an eligible submission reaches the original assignment and that another grade/section's resources remain inaccessible.
4. Check keyboard/touch navigation, readable dates, independent pagination and the missing-calendar state on actual devices.

Do not use the workstation's loopback address from another device. The institutional server address, certificate setup, IIS/Apache choice and deployment configuration require their own approved environment. No firewall, certificate-trust or production change is authorized by this guide.

Local verification evidence: [weekly-course-discovery-verification.md](weekly-course-discovery-verification.md). The synthetic period/calendar/activity fixtures recorded there are verification data, not approved school records.
