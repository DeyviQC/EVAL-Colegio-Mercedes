import { useEffect, useMemo, useState, useSyncExternalStore, type FormEvent, type ReactNode } from 'react';
import { createSessionClient, createSessionController, type SessionView } from './session.js';
import { createNavigationController, sectionLabels } from './navigation.js';
export function SessionPanel({view,onLogin,onLogout,onRefresh,children}:{view:SessionView;onLogin:(input:{login:string;password:string})=>Promise<void>;onLogout:()=>Promise<void>;onRefresh:()=>Promise<void>;children?:ReactNode}) {
  const [login,setLogin]=useState('');const [password,setPassword]=useState('');
  const pending=view.phase==='busy'||view.phase==='checking';const authenticated=view.phase==='authenticated';
  async function submit(event:FormEvent<HTMLFormElement>) { event.preventDefault();const input={login,password};setPassword('');await onLogin(input); }
  return <main className="mx-auto max-w-4xl px-5 py-10 sm:py-16">
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
  const controller=useMemo(()=>createSessionController(createSessionClient()),[]);
  const navigation=useMemo(()=>createNavigationController(),[]);
  const context=useSyncExternalStore(navigation.subscribe,navigation.snapshot,navigation.snapshot);
  const [selected,setSelected]=useState<string|null>(null);
  const view=useSyncExternalStore(controller.subscribe,controller.snapshot,controller.snapshot);
  useEffect(()=>{void controller.refresh();return ()=>controller.invalidate();},[controller]);
  useEffect(()=>{setSelected(null);if(view.phase==='authenticated')void navigation.refresh();else navigation.clear();return ()=>navigation.clear();},[view.phase,navigation]);
  return <SessionPanel view={view} onLogin={controller.login} onLogout={controller.logout} onRefresh={controller.refresh} >
    {context.phase==='loading'&&<p role="status">Consultando secciones…</p>}
    {context.phase==='unavailable'&&<><p role="alert">No se pudo confirmar la navegación.</p><button onClick={()=>void navigation.refresh()}>Consultar secciones</button></>}
    {context.phase==='ready'&&<><nav aria-label="Secciones académicas">{context.sections.map(section=><button key={section} className="mr-3 mb-3 rounded-lg border border-eval-teal px-4 py-2" aria-pressed={selected===section} onClick={()=>setSelected(section)}>{sectionLabels[section]}</button>)}</nav>
      <p>{context.sections.length===0?'No hay secciones habilitadas para esta sesión.':selected?'La consulta de registros de esta sección todavía no está disponible.':'Selecciona una sección disponible.'}</p></>}
  </SessionPanel>;
}
