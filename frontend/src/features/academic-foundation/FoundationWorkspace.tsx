import { useEffect, useRef, useState, type FormEvent } from 'react';
import { AcademicApiError, type createAcademicClient } from './api.js';
import { periodPage, catalogPage, type CatalogKind, type CatalogRow } from './directory.js';
import { type period } from './contracts.js';
type Client=ReturnType<typeof createAcademicClient>;
type Period=ReturnType<typeof period>;
const control='rounded-lg border border-eval-ink/30 px-3 py-2';
function errorMessage(error:unknown){
  if(!(error instanceof AcademicApiError))return 'No se pudo validar la respuesta. Consulta de nuevo.';
  if(error.status===403)return 'No tienes permiso para esta operación.';
  if(error.status===401||error.status===419)return 'La sesión debe verificarse nuevamente.';
  if(error.outcomeUnknown)return 'No se pudo confirmar el resultado. No repitas la operación; consulta los registros antes de continuar.'+(error.correlationId?' Referencia: '+error.correlationId:'');
  if(error.status===409)return 'La operación entra en conflicto con el estado actual o con un nombre existente. Consulta los registros.';
  if(error.status===422)return 'Revisa los campos y las fechas. El servidor no aceptó la operación.';
  return 'No se pudo completar la consulta. Verifica la conexión y consulta de nuevo.';
}
export function FoundationWorkspace({section,client,onExpired}:{section:'periods'|'catalog';client:Client;onExpired:()=>void}){
  const [kind,setKind]=useState<CatalogKind>('entry');const [periods,setPeriods]=useState<Period[]>([]);const [rows,setRows]=useState<CatalogRow[]>([]);
  const [grades,setGrades]=useState<CatalogRow[]>([]);const [gradeCursor,setGradeCursor]=useState<string|null>(null);
  const [gradeBusy,setGradeBusy]=useState(false);const gradeRequest=useRef(false);
  const [cursor,setCursor]=useState<string|null>(null);const [phase,setPhase]=useState<'loading'|'ready'|'error'>('loading');
  const [busy,setBusy]=useState(false);const [message,setMessage]=useState('');const [uncertain,setUncertain]=useState(false);
  const [confirm,setConfirm]=useState<{id:string;name:string;action:'activate'|'close'}|null>(null);
  const generation=useRef(0);const mounted=useRef(true);const writing=useRef(false);
  function failed(error:unknown){setMessage(errorMessage(error));if(error instanceof AcademicApiError){if(error.status===401||error.status===419)onExpired();if(error.outcomeUnknown)setUncertain(true);}}
  async function load(after:string|null=null,append=after!==null){const current=++generation.current;setPhase('loading');setMessage('');
    try{if(section==='periods'){const page=await client.directory('periods',periodPage,after);if(!mounted.current||current!==generation.current)return;setPeriods(old=>append?[...old,...page.items]:page.items);setCursor(page.next_after);}
      else{const page=await client.directory(`catalog/${kind}`,catalogPage(kind),after);if(!mounted.current||current!==generation.current)return;setRows(old=>append?[...old,...page.items]:page.items);setCursor(page.next_after);}
      setPhase('ready');
    }catch(error){if(!mounted.current||current!==generation.current)return;setPeriods([]);setRows([]);setCursor(null);setPhase('error');failed(error);}
  }
  async function loadGrades(after:string|null=null){if(gradeRequest.current)return;gradeRequest.current=true;setGradeBusy(true);
    try{const page=await client.directory('catalog/grade',catalogPage('grade'),after);if(!mounted.current)return;setGrades(old=>after?[...old,...page.items]:page.items);setGradeCursor(page.next_after);}
    catch(error){if(mounted.current){setGrades([]);failed(error);}}finally{gradeRequest.current=false;if(mounted.current)setGradeBusy(false);}}
  useEffect(()=>{mounted.current=true;lastPage.current=null;void load();if(section==='catalog'&&kind==='section')void loadGrades();return ()=>{mounted.current=false;generation.current++;};},[section,kind]);
  const lastPage=useRef<string|null>(null);
  async function mutate(operation:()=>Promise<unknown>,created=false){if(writing.current||uncertain)return;writing.current=true;setBusy(true);setMessage('');
    try{const result=await operation() as {id:string};if(!mounted.current)return;setConfirm(null);
      if(created){const prior=(BigInt(result.id)-1n).toString();lastPage.current=prior==='0'?null:prior;}
      await load(lastPage.current,false);setMessage('Operación confirmada. Registros actualizados.');}
    catch(error){if(mounted.current)failed(error);}finally{writing.current=false;if(mounted.current)setBusy(false);}
  }
  function create(event:FormEvent<HTMLFormElement>){event.preventDefault();const form=event.currentTarget;const data=new FormData(form);const name=String(data.get('name')??'');
    if(section==='periods')void mutate(()=>client.createPeriod({name,start_on:String(data.get('start_on')),end_on:String(data.get('end_on'))}),true);
    else void mutate(()=>client.createCatalog(kind,{name,...(kind==='entry'?{kind:String(data.get('kind'))}:kind==='section'?{grade_id:String(data.get('grade_id'))}:{})}),true);
  }
  return <section aria-label={section==='periods'?'Gestión de períodos':'Gestión de catálogo'} aria-busy={busy||phase==='loading'}>
    <h3 className="text-xl font-semibold">{section==='periods'?'Períodos académicos':'Catálogo académico'}</h3>
    <p>Las operaciones se validan en el servidor y conservan el historial.</p>
    {section==='catalog'&&<label>Tipo de catálogo <select className={control} value={kind} disabled={busy} onChange={event=>{setConfirm(null);setKind(event.target.value as CatalogKind);}}><option value="entry">Materias y áreas</option><option value="grade">Grados</option><option value="section">Secciones</option></select></label>}
    <button className={control} disabled={busy} onClick={()=>void load()}>Actualizar registros</button>
    {phase==='loading'&&<p role="status">Consultando registros…</p>}
    {message&&<p role={phase==='error'||uncertain?'alert':'status'}>{message}</p>}
    {uncertain&&<p role="alert">Operaciones suspendidas para evitar duplicados. Verifica el resultado con administración antes de iniciar otra sesión de trabajo.</p>}
    <form onSubmit={create} className="my-5 flex flex-wrap gap-3" aria-label={section==='periods'?'Crear período':'Crear registro de catálogo'}>
      <fieldset disabled={busy||uncertain||phase!=='ready'} className="flex flex-wrap gap-3">
        <legend>{section==='periods'?'Crear período':'Crear registro'}</legend>
        <label>Nombre <input className={control} name="name" required maxLength={255}/></label>
        {section==='periods'?<><label>Inicio <input className={control} name="start_on" type="date" required/></label><label>Fin <input className={control} name="end_on" type="date" required/></label></>:
          kind==='entry'?<label>Materia o área <select className={control} name="kind"><option value="subject">Materia</option><option value="area">Área</option></select></label>:
          kind==='section'?<><label>Grado <select className={control} name="grade_id" aria-label="Grado" required disabled={gradeBusy}><option value="">Selecciona un grado</option>{grades.map(grade=><option key={grade.id} value={grade.id}>{grade.name}{grade.is_active?'':' (inactivo)'}</option>)}</select></label>{gradeCursor&&<button type="button" disabled={gradeBusy} onClick={()=>void loadGrades(gradeCursor)}>Cargar más grados</button>}</>:null}
        <button className={control} type="submit">{section==='periods'?'Crear período':'Crear registro'}</button>
      </fieldset>
    </form>
    {phase==='ready'&&(section==='periods'?periods.length===0:rows.length===0)&&<p>No hay registros para mostrar.</p>}
    {phase==='ready'&&section==='periods'&&periods.map(row=><article key={row.id} className="my-4 rounded-lg border p-4" aria-label={row.name}>
      <h4 className="font-semibold whitespace-pre-wrap">{row.name}</h4><p>Estado: {({planned:'Planificado',active:'Activo',closed:'Cerrado'})[row.state]}</p><p>Inicio: {row.start_on} · Fin: {row.end_on}</p>
      {row.state!=='closed'&&<button className={control} disabled={busy||uncertain} onClick={()=>setConfirm({id:row.id,name:row.name,action:row.state==='planned'?'activate':'close'})}>{row.state==='planned'?'Solicitar activación':'Solicitar cierre'}</button>}
    </article>)}
    {confirm&&<section role="region" aria-label="Confirmar cambio de período" className="rounded-lg border p-4">
      <p>¿Confirmas {confirm.action==='activate'?'activar':'cerrar'} el período {confirm.name}? El servidor validará el estado. El cierre conserva los registros y bloquea nuevo trabajo en este período.</p>
      <button className={control} disabled={busy||uncertain} onClick={()=>void mutate(()=>client.transitionPeriod(confirm.id,confirm.action))}>Confirmar {confirm.action==='activate'?'activación':'cierre'}</button>
      <button className={control} disabled={busy} onClick={()=>setConfirm(null)}>Cancelar</button>
    </section>}
    {phase==='ready'&&section==='catalog'&&rows.map(row=><article key={row.id} className="my-4 rounded-lg border p-4" aria-label={row.name}>
      <h4 className="font-semibold whitespace-pre-wrap">{row.name}</h4><p>{row.kind==='subject'?'Materia':row.kind==='area'?'Área':kind==='grade'?'Grado':'Sección'} · {row.is_active?'Activo':'Inactivo'}</p>
      <form aria-label={'Editar '+row.name} onSubmit={event=>{event.preventDefault();const name=String(new FormData(event.currentTarget).get('name'));void mutate(()=>client.updateCatalog(kind,row.id,{name}));}}>
        <label>Nuevo nombre <input className={control} name="name" defaultValue={row.name} required maxLength={255} disabled={busy||uncertain}/></label><button className={control} disabled={busy||uncertain}>Guardar nombre</button>
      </form>
      <button className={control} disabled={busy||uncertain} onClick={()=>void mutate(()=>client.updateCatalog(kind,row.id,{is_active:!row.is_active}))}>{row.is_active?'Desactivar':'Reactivar'}</button>
    </article>)}
    {phase==='ready'&&cursor&&<button className={control} disabled={busy} onClick={()=>void load(cursor)}>Cargar más registros</button>}
  </section>;
}
