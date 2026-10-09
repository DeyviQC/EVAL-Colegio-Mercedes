import { useEffect, useMemo, useState, useSyncExternalStore, type FormEvent, type ReactNode } from 'react';
import { createSessionClient, createSessionController, type SessionView } from './session.js';
import { createNavigationController, sectionLabels } from './navigation.js';
import { createAcademicClient } from './api.js';
import { FoundationWorkspace } from './FoundationWorkspace.js';
import { MyCourses } from './MyCourses.js';
import { EnrollmentWorkspace } from './EnrollmentWorkspace.js';
import { AssignmentWorkspace } from './AssignmentWorkspace.js';
import { createMaterialClient } from './materials.js';
import { createAccountClient,type OwnAccount } from './accounts.js';
import { UsersWorkspace,OwnAccountWorkspace } from './AccountWorkspace.js';
import { createActivityClient } from './activities.js';
import { NotificationWorkspace } from './NotificationWorkspace.js';
import { createNotificationClient } from './notifications.js';
import { ReportWorkspace } from './ReportWorkspace.js';
import { createReportClient } from './reports.js';
import { SupervisionWorkspace } from './SupervisionWorkspace.js';
import { HistoryWorkspace } from './HistoryWorkspace.js';
import { ActivityWorkspace } from './ActivityWorkspace.js';
import { CalendarWorkspace } from './CalendarWorkspace.js';
import { createCalendarClient } from './calendars.js';
import { createWeeksClient } from './weeks.js';
import {SessionPanel} from './InstitutionalShell.js';
import {NavButton} from './InstitutionalUI.js';
export {SessionPanel} from './InstitutionalShell.js';
export function SessionShell() {
  const session=useMemo(()=>createSessionClient(),[]);
  const controller=useMemo(()=>createSessionController(session),[session]);
  const academic=useMemo(()=>createAcademicClient(fetch,()=>session.csrfToken()??''),[session]);
  const materials=useMemo(()=>createMaterialClient(fetch,()=>session.csrfToken()??''),[session]);
  const weeks=useMemo(()=>createWeeksClient(()=>session.csrfToken()??''),[session]);
  const calendars=useMemo(()=>createCalendarClient(()=>session.csrfToken()??''),[session]);
  const accounts=useMemo(()=>createAccountClient(()=>session.csrfToken()??''),[session]);
  const notices=useMemo(()=>createNotificationClient(()=>session.csrfToken()??''),[session]);
  const [unread,setUnread]=useState<number|null>(null);
  const reports=useMemo(()=>createReportClient(),[]);
  const activities=useMemo(()=>createActivityClient(()=>session.csrfToken()??''),[session]);
  const [own,setOwn]=useState<OwnAccount|null>(null);
  const navigation=useMemo(()=>createNavigationController(),[]);
  const context=useSyncExternalStore(navigation.subscribe,navigation.snapshot,navigation.snapshot);
  const [selected,setSelected]=useState<string|null>(null);
  const view=useSyncExternalStore(controller.subscribe,controller.snapshot,controller.snapshot);
  useEffect(()=>{void controller.refresh();return ()=>controller.invalidate();},[controller]);
  useEffect(()=>{setUnread(null);setSelected(null);if(view.phase==='authenticated')void navigation.refresh();else navigation.clear();return ()=>navigation.clear();},[view.phase,navigation]);
  useEffect(()=>{let active=true;setOwn(null);if(view.phase==='authenticated')void accounts.own().then(account=>{if(active)setOwn(account);}).catch(()=>{});return ()=>{active=false;};},[view.phase,accounts]);
  const activeLabel=selected&&selected in sectionLabels?sectionLabels[selected as keyof typeof sectionLabels]:selected==='history'?(context.sections.some(s=>s==='my_assignments'||s==='my_enrollments')?'Mi historial':'Historial'):selected==='grades'?(context.sections.includes('my_assignments')?'Calificaciones':'Mis calificaciones'):({weeks:'Calendario y semanas',calendar:'Calendario institucional',materials:'Materiales',library:'Biblioteca',activities:'Actividades',deliveries:'Entregas',supervision:'Supervisión de materiales',reports:'Reportes',users:'Usuarios',history:'Historial',notifications:'Notificaciones',account:'Mi cuenta'} as Record<string,string>)[selected??'']??'Inicio';
  return <SessionPanel account={own} profileLabel={own?.can_manage?'Director / Administrador':context.sections.includes('my_assignments')?'Docente':context.sections.includes('my_enrollments')?'Estudiante':context.sections.includes('assignments')?'Subdirector':undefined} onNotifications={own?()=>setSelected('notifications'):undefined} unread={unread} activeLabel={activeLabel} view={view} onLogin={controller.login} onLogout={controller.logout} onRefresh={controller.refresh} navigation={context.phase==='ready'?<nav className="eval-navigation" aria-label="Secciones académicas"><NavButton aria-pressed={selected===null} onClick={()=>setSelected(null)}>Inicio</NavButton>{context.sections.map(section=><NavButton key={section} aria-pressed={selected===section} onClick={()=>setSelected(section)}>{sectionLabels[section]}</NavButton>)}{context.sections.some(section=>section==='my_assignments'||section==='my_enrollments')&&<><NavButton aria-pressed={selected==='weeks'} onClick={()=>setSelected('weeks')}>Calendario y semanas</NavButton><NavButton aria-pressed={selected==='materials'} onClick={()=>setSelected('materials')}>Materiales</NavButton><NavButton aria-pressed={selected==='library'} onClick={()=>setSelected('library')}>Biblioteca</NavButton><NavButton aria-pressed={selected==='activities'} onClick={()=>setSelected('activities')}>Actividades</NavButton><NavButton aria-pressed={selected==='deliveries'} onClick={()=>setSelected('deliveries')}>Entregas</NavButton><NavButton aria-pressed={selected==='grades'} onClick={()=>setSelected('grades')}>{context.sections.includes('my_assignments')?'Calificaciones':'Mis calificaciones'}</NavButton></>}{own&&context.sections.includes('assignments')&&!own.can_manage&&<NavButton aria-pressed={selected==='supervision'} onClick={()=>setSelected('supervision')}>Supervisión de materiales</NavButton>}{own?.can_manage&&<NavButton aria-pressed={selected==='reports'} onClick={()=>setSelected('reports')}>Reportes</NavButton>}{own?.can_manage&&<NavButton aria-pressed={selected==='calendar'} onClick={()=>setSelected('calendar')}>Calendario institucional</NavButton>}{own?.can_manage&&<NavButton aria-pressed={selected==='users'} onClick={()=>setSelected('users')}>Usuarios</NavButton>}{context.sections.length>0&&<NavButton aria-pressed={selected==='history'} onClick={()=>setSelected('history')}>{context.sections.some(s=>s==='my_assignments'||s==='my_enrollments')?'Mi historial':'Historial'}</NavButton>}{own&&<NavButton aria-pressed={selected==='notifications'} onClick={()=>setSelected('notifications')}>{unread===null?'Notificaciones':'Notificaciones ('+unread+')'}</NavButton>}{own&&<NavButton aria-pressed={selected==='account'} onClick={()=>setSelected('account')}>Mi cuenta</NavButton>}</nav>:undefined} >
    {context.phase==='loading'&&<p role="status">Consultando secciones…</p>}
    {context.phase==='unavailable'&&<><p role="alert">No se pudo confirmar la navegación.</p><button onClick={()=>void navigation.refresh()}>Consultar secciones</button></>}
    {context.phase==='ready'&&<>
      {selected==='calendar'&&own?.can_manage?<CalendarWorkspace academic={academic} client={calendars} onExpired={controller.invalidate}/>:selected==='supervision'?<SupervisionWorkspace client={materials.observations} onExpired={controller.invalidate}/>:selected==='library'?<MyCourses key="library" client={academic} materials={materials} teacherMode={context.sections.includes('my_assignments')} libraryOnly onExpired={controller.invalidate}/>:selected==='history'?<HistoryWorkspace client={academic} kinds={context.sections.includes('enrollments')?['enrollments','assignments']:context.sections.includes('my_enrollments')?['enrollments']:['assignments']} onExpired={controller.invalidate}/>:selected==='materials'?<MyCourses key="materials" client={academic} materials={materials} teacherMode={context.sections.includes('my_assignments')} materialsOnly onExpired={controller.invalidate}/>:selected==='notifications'?<NotificationWorkspace client={notices} onExpired={controller.invalidate} onCount={setUnread} onSection={setSelected}/>:selected==='reports'&&own?.can_manage?<ReportWorkspace client={reports} academic={academic} onExpired={controller.invalidate}/>:selected==='activities'||selected==='deliveries'||selected==='grades'?<ActivityWorkspace key={selected} client={activities} mode={selected} onExpired={controller.invalidate}/>:selected==='users'&&own?.can_manage?<UsersWorkspace client={accounts} onExpired={controller.invalidate}/>:selected==='account'&&own?<OwnAccountWorkspace client={accounts} account={own} onExpired={controller.invalidate}/>:selected==='weeks'||selected==='my_assignments'||selected==='my_enrollments'?<MyCourses key="courses" client={academic} materials={materials} weeks={weeks} activities={activities} teacherMode={context.sections.includes('my_assignments')} onExpired={controller.invalidate}/>:selected==='assignments'?<AssignmentWorkspace client={academic} onExpired={controller.invalidate}/>:selected==='enrollments'?<EnrollmentWorkspace client={academic} onExpired={controller.invalidate}/>:selected==='periods'||selected==='catalog'?<FoundationWorkspace key={selected} section={selected} client={academic} onExpired={controller.invalidate}/>:<div className="eval-home"><div className="eval-welcome"><p className="eval-eyebrow">TU PUNTO DE PARTIDA</p><h3>Bienvenido a EVAL</h3><p>{context.sections.length===0?'No hay secciones habilitadas para esta sesión.':selected?'La consulta de registros de esta sección todavía no está disponible.':'Elige una sección del menú para consultar tus cursos, recursos o registros.'}</p><p className="eval-welcome-note">Tus opciones corresponden a tu cuenta y a tu contexto académico.</p></div>{!selected&&context.sections.length>0&&<section className="eval-home-shortcuts" aria-label="Accesos directos"><h3>Accesos directos</h3><div className="eval-shortcut-grid">{context.sections.map(section=><button key={section} onClick={()=>setSelected(section)}><strong>{sectionLabels[section]}</strong><span>Abrir esta sección</span><span aria-hidden="true">→</span></button>)}</div></section>}</div>}</>}
  </SessionPanel>;
}
