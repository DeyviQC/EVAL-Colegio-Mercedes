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
import { ActivityWorkspace } from './ActivityWorkspace.js';
export function SessionPanel({view,onLogin,onLogout,onRefresh,children,navigation}:{view:SessionView;onLogin:(input:{login:string;password:string})=>Promise<void>;onLogout:()=>Promise<void>;onRefresh:()=>Promise<void>;children?:ReactNode;navigation?:ReactNode}) {
  const [login,setLogin]=useState('');const [password,setPassword]=useState('');
  const pending=view.phase==='busy'||view.phase==='checking';const authenticated=view.phase==='authenticated';
  async function submit(event:FormEvent<HTMLFormElement>) { event.preventDefault();const input={login,password};setPassword('');await onLogin(input); }
  if(authenticated) return <div className="eval-layout">
    <aside className="eval-sidebar"><div className="eval-brand"><h1>EVAL</h1><span>AULA ACADÉMICA</span><p>I.E. Nuestra Señora de las Mercedes</p></div>{navigation}<div className="eval-account"><p role="status" aria-live="polite">{view.message}</p><button onClick={()=>void onLogout()}>Cerrar sesión</button></div></aside>
    <main className="eval-workspace"><header className="eval-workspace-header"><div><p className="eval-eyebrow">COMUNIDAD EDUCATIVA</p><h2 className="text-2xl font-semibold">Tu espacio académico</h2></div><span className="eval-local-badge">Servidor local</span></header><section className="eval-workspace-content">{children??<p>No hay información para mostrar.</p>}</section></main>
  </div>;
  return <main className="eval-login mx-auto max-w-4xl px-5 py-10 sm:py-16">
    <header className="mb-10 border-b border-eval-teal/20 pb-6"><p className="text-sm font-semibold uppercase tracking-widest text-eval-teal">I.E. Nuestra Señora de las Mercedes</p><h1 className="mt-2 text-4xl font-bold">EVAL</h1><p className="mt-2 text-lg">Plataforma educativa institucional</p></header>
    <section className="rounded-2xl border border-eval-teal/15 bg-white p-6 shadow-sm sm:p-8" aria-busy={pending}>
      <h2 className="text-2xl font-semibold">{authenticated?'Tu espacio académico':'Acceso a EVAL'}</h2>
      <p className="my-4" role="status" aria-live="polite">{view.message}</p>
      {authenticated?<><button className="rounded-lg border border-eval-teal px-4 py-2" onClick={()=>void onLogout()}>Cerrar sesión</button><div className="mt-8">{children??<p>No hay información para mostrar.</p>}</div></>:
        <form onSubmit={event=>void submit(event)} className="max-w-md space-y-5">
          <div><label htmlFor="eval-login" className="mb-2 block font-semibold">Usuario</label><input id="eval-login" name="login" autoComplete="username" value={login} onChange={event=>setLogin(event.target.value)} required disabled={pending} className="w-full rounded-lg border border-eval-ink/30 px-3 py-2" /></div>
          <div><label htmlFor="eval-password" className="mb-2 block font-semibold">Contraseña</label><input id="eval-password" name="password" type="password" autoComplete="current-password" value={password} onChange={event=>setPassword(event.target.value)} required disabled={pending} className="w-full rounded-lg border border-eval-ink/30 px-3 py-2" /></div>
          <button type="submit" disabled={pending||view.phase==='expired'||view.phase==='unavailable'} className="rounded-lg bg-eval-teal px-5 py-2.5 font-semibold text-white">Ingresar</button>
        </form>}
      {(view.phase==='expired'||view.phase==='unavailable')&&<button onClick={()=>void onRefresh()} className="mt-5 rounded-lg border border-eval-teal px-4 py-2">Verificar sesión</button>}
    </section>
  </main>;
}
export function SessionShell() {
  const session=useMemo(()=>createSessionClient(),[]);
  const controller=useMemo(()=>createSessionController(session),[session]);
  const academic=useMemo(()=>createAcademicClient(fetch,()=>session.csrfToken()??''),[session]);
  const materials=useMemo(()=>createMaterialClient(fetch,()=>session.csrfToken()??''),[session]);
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
  return <SessionPanel view={view} onLogin={controller.login} onLogout={controller.logout} onRefresh={controller.refresh} navigation={context.phase==='ready'?<nav className="eval-navigation" aria-label="Secciones académicas">{context.sections.map(section=><button key={section} aria-pressed={selected===section} onClick={()=>setSelected(section)}>{sectionLabels[section]}</button>)}{context.sections.some(section=>section==='my_assignments'||section==='my_enrollments')&&<><button aria-pressed={selected==='materials'} onClick={()=>setSelected('materials')}>Materiales</button><button aria-pressed={selected==='activities'} onClick={()=>setSelected('activities')}>Actividades</button><button aria-pressed={selected==='deliveries'} onClick={()=>setSelected('deliveries')}>Entregas</button><button aria-pressed={selected==='grades'} onClick={()=>setSelected('grades')}>{context.sections.includes('my_assignments')?'Calificaciones':'Mis calificaciones'}</button></>}{own?.can_manage&&<button aria-pressed={selected==='reports'} onClick={()=>setSelected('reports')}>Reportes</button>}{own?.can_manage&&<button aria-pressed={selected==='users'} onClick={()=>setSelected('users')}>Usuarios</button>}{own&&<button aria-pressed={selected==='notifications'} onClick={()=>setSelected('notifications')}>{unread===null?'Notificaciones':'Notificaciones ('+unread+')'}</button>}{own&&<button aria-pressed={selected==='account'} onClick={()=>setSelected('account')}>Mi cuenta</button>}</nav>:undefined} >
    {context.phase==='loading'&&<p role="status">Consultando secciones…</p>}
    {context.phase==='unavailable'&&<><p role="alert">No se pudo confirmar la navegación.</p><button onClick={()=>void navigation.refresh()}>Consultar secciones</button></>}
    {context.phase==='ready'&&<>
      {selected==='materials'?<MyCourses key="materials" client={academic} materials={materials} materialsOnly onExpired={controller.invalidate}/>:selected==='notifications'?<NotificationWorkspace client={notices} onExpired={controller.invalidate} onCount={setUnread} onSection={setSelected}/>:selected==='reports'&&own?.can_manage?<ReportWorkspace client={reports} academic={academic} onExpired={controller.invalidate}/>:selected==='activities'||selected==='deliveries'||selected==='grades'?<ActivityWorkspace key={selected} client={activities} mode={selected} onExpired={controller.invalidate}/>:selected==='users'&&own?.can_manage?<UsersWorkspace client={accounts} onExpired={controller.invalidate}/>:selected==='account'&&own?<OwnAccountWorkspace client={accounts} account={own} onExpired={controller.invalidate}/>:selected==='my_assignments'||selected==='my_enrollments'?<MyCourses key="courses" client={academic} materials={materials} onExpired={controller.invalidate}/>:selected==='assignments'?<AssignmentWorkspace client={academic} onExpired={controller.invalidate}/>:selected==='enrollments'?<EnrollmentWorkspace client={academic} onExpired={controller.invalidate}/>:selected==='periods'||selected==='catalog'?<FoundationWorkspace key={selected} section={selected} client={academic} onExpired={controller.invalidate}/>:<p>{context.sections.length===0?'No hay secciones habilitadas para esta sesión.':selected?'La consulta de registros de esta sección todavía no está disponible.':'Selecciona una sección disponible.'}</p>}</>}
  </SessionPanel>;
}
