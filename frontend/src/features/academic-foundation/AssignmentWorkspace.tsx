import {useEffect,useRef,useState,type FormEvent} from 'react';
import {AcademicApiError,type AcademicClient} from './api.js';
import {assignmentPage,type assignmentRow} from './assignments.js';
import {studentPage,type studentRow} from './enrollments.js';
import {periodPage,catalogPage,type CatalogRow} from './directory.js';
import type {period} from './contracts.js';
type Assignment=ReturnType<typeof assignmentRow>;
type Teacher=ReturnType<typeof studentRow>;
type Period=ReturnType<typeof period>;
type Options={teachers:Teacher[];periods:Period[];entries:CatalogRow[];grades:CatalogRow[];sections:CatalogRow[]};
type Kind=keyof Options;
const kinds:Kind[]=['teachers','periods','entries','grades','sections'];
const uncertainClients=new WeakSet<AcademicClient>();
const control='rounded-lg border border-eval-ink/30 px-3 py-2';

export function AssignmentWorkspace({client,onExpired}:{client:AcademicClient;onExpired:()=>void}){
  const [rows,setRows]=useState<Assignment[]>([]);
  const [cursor,setCursor]=useState<string|null>(null);
  const [options,setOptions]=useState<Options>({teachers:[],periods:[],entries:[],grades:[],sections:[]});
  const [cursors,setCursors]=useState<Record<Kind,string|null>>({teachers:null,periods:null,entries:null,grades:null,sections:null});
  const [phase,setPhase]=useState<'loading'|'ready'|'error'>('loading');
  const [busy,setBusy]=useState(false);
  const [uncertain,setUncertain]=useState(()=>uncertainClients.has(client));
  const [message,setMessage]=useState('');
  const [grade,setGrade]=useState('');
  const [section,setSection]=useState('');
  const [action,setAction]=useState<{row:Assignment;kind:'activate'|'close'|'replace'}|null>(null);
  const mounted=useRef(true),generation=useRef(0),writing=useRef(false);
  const feedback=useRef<HTMLParagraphElement>(null);
  function failed(error:unknown){
    if(error instanceof AcademicApiError){
      if(error.status===401||error.status===419){onExpired();return;}
      if(error.outcomeUnknown){uncertainClients.add(client);setUncertain(true);setMessage('No se pudo confirmar el resultado. No repitas la operación; consulta los registros.');return;}
      setMessage(error.status===403?'No tienes permiso para organizar asignaciones.':error.status===409?'La operación entra en conflicto con el período, fechas o asignaciones existentes. Consulta los registros.':error.status===422?'Revisa el docente, aula y fechas. El servidor rechazó la operación.':'No se pudo consultar o guardar la información. Vuelve a consultar.');
    }else setMessage('No se pudo validar la respuesta del servidor. Vuelve a consultar.');
  }
  async function load(after:string|null=null){
    const request=++generation.current;setPhase('loading');
    try{
      const page=await client.assignmentDirectory('assignments',assignmentPage,after);
      if(!mounted.current||request!==generation.current)return;
      setRows(old=>after?[...old,...page.items]:page.items);setCursor(page.next_after);setPhase('ready');return page;
    }catch(error){if(mounted.current&&request===generation.current){setRows([]);setPhase('error');failed(error);}}
  }
  async function loadOption(kind:Kind,after:string|null=null){
    const page=kind==='teachers'?await client.assignmentDirectory(kind,studentPage,after):
      kind==='periods'?await client.assignmentDirectory(kind,periodPage,after):
      await client.assignmentDirectory(kind,catalogPage(kind==='entries'?'entry':kind==='grades'?'grade':'section'),after);
    if(!mounted.current)return;
    setOptions(old=>({...old,[kind]:after?[...old[kind],...page.items]:page.items}));setCursors(old=>({...old,[kind]:page.next_after}));
  }
  async function refresh(){
    setBusy(true);setMessage('');
    try{await Promise.all([load(),...kinds.map(kind=>loadOption(kind))]);}
    catch(error){if(mounted.current){setPhase('error');failed(error);}}
    finally{if(mounted.current)setBusy(false);}
  }
  useEffect(()=>{mounted.current=true;void refresh();return()=>{mounted.current=false;generation.current++;};},[client]);
  useEffect(()=>{if(message)feedback.current?.focus();},[message]);
  async function mutate(operation:()=>Promise<unknown>){
    if(writing.current||uncertainClients.has(client))return;
    writing.current=true;setBusy(true);setMessage('');
    try{
      const result=await operation() as {id?:string;successor_id?:string};if(!mounted.current)return;setAction(null);
      const page=await load();if(!mounted.current)return;
      if(!page){setMessage('Operación confirmada. No se pudo actualizar el listado; vuelve a consultar.');return;}
      const changedId=result.successor_id??result.id;
      if(changedId&&!page.items.some(row=>row.id===changedId)){
        const prior=(BigInt(changedId)-1n).toString();const changed=await client.assignmentDirectory('assignments',assignmentPage,prior==='0'?null:prior);
        if(!mounted.current)return;const row=changed.items.find(item=>item.id===changedId);if(row)setRows(old=>old.some(item=>item.id===row.id)?old:[...old,row]);
      }
      setMessage('Operación confirmada. Asignaciones actualizadas.');
    }catch(error){if(mounted.current)failed(error);}
    finally{writing.current=false;if(mounted.current)setBusy(false);}
  }
  function create(event:FormEvent<HTMLFormElement>){
    event.preventDefault();const data=new FormData(event.currentTarget);
    void mutate(()=>client.planAssignment({teacher_id:String(data.get('teacher_id')),academic_period_id:String(data.get('academic_period_id')),instructional_entry_id:String(data.get('instructional_entry_id')),grade_id:grade,section_id:section,effective_from:String(data.get('effective_from')),...(data.get('effective_until')?{effective_until:String(data.get('effective_until'))}:{})}));
  }
  function confirm(event:FormEvent<HTMLFormElement>){
    event.preventDefault();if(!action)return;const selected=action;const data=new FormData(event.currentTarget);
    void mutate(()=>selected.kind==='activate'?client.activateAssignment(selected.row.id):selected.kind==='close'?client.closeAssignment(selected.row.id,String(data.get('effective_until'))):client.replaceTeacher(selected.row.id,String(data.get('teacher_id'))));
  }
  const disabled=busy||uncertain||phase!=='ready';
  return <section aria-label="Gestión de asignaciones" aria-busy={busy||phase==='loading'}>
    <h3 className="text-xl font-semibold">Organización docente</h3>
    <p className="my-3">Planifica y activa asignaciones por docente, materia o área y aula. Un reemplazo conserva la asignación y autoría anteriores.</p>
    <button className={control} disabled={busy} onClick={()=>void refresh()}>Actualizar asignaciones</button>
    {phase==='loading'&&<p role="status">Consultando asignaciones…</p>}
    {message&&<p role="status" tabIndex={-1} ref={feedback} className="my-4 rounded-lg border p-3">{message}</p>}
    {uncertain&&<p role="alert">Operaciones suspendidas para evitar duplicados. Verifica los registros antes de continuar.</p>}
    <form onSubmit={create} aria-label="Planificar asignación" className="my-5 rounded-xl border p-5">
      <fieldset disabled={disabled} className="flex flex-wrap gap-4"><legend className="mb-3 font-semibold">Nueva asignación planificada</legend>
        <label>Docente <select aria-label="Docente" name="teacher_id" required className={control}><option value="">Selecciona un docente</option>{options.teachers.map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        <label>Período <select aria-label="Período" name="academic_period_id" required className={control}><option value="">Selecciona un período</option>{options.periods.map(row=><option key={row.id} value={row.id}>{row.name} ({row.state==='active'?'activo':'planificado'})</option>)}</select></label>
        <label>Materia o área <select aria-label="Materia o área" name="instructional_entry_id" required className={control}><option value="">Selecciona una materia o área</option>{options.entries.map(row=><option key={row.id} value={row.id}>{row.name} ({row.kind==='area'?'área':'materia'})</option>)}</select></label>
        <label>Grado <select aria-label="Grado" required className={control} value={grade} onChange={event=>{setGrade(event.target.value);setSection('');}}><option value="">Selecciona un grado</option>{options.grades.map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        <label>Sección <select aria-label="Sección" required className={control} value={section} onChange={event=>setSection(event.target.value)}><option value="">Selecciona una sección</option>{options.sections.filter(row=>row.grade_id===grade).map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        <label>Inicio <input name="effective_from" type="date" required className={control}/></label><label>Fin (opcional) <input name="effective_until" type="date" className={control}/></label>
        <button type="submit" className={control}>Planificar asignación</button>
      </fieldset>
    </form>
    <div className="my-3 flex flex-wrap gap-3">{kinds.map(kind=>cursors[kind]&&<button key={kind} className={control} disabled={busy} onClick={()=>{setBusy(true);void loadOption(kind,cursors[kind]).catch(failed).finally(()=>setBusy(false));}}>Cargar más {({teachers:'docentes',periods:'períodos',entries:'materias y áreas',grades:'grados',sections:'secciones'})[kind]}</button>)}</div>
    {phase==='ready'&&rows.length===0&&<p>No hay asignaciones para mostrar.</p>}
    {phase==='ready'&&rows.map(row=><article key={row.id} aria-label={row.teacher_name+' · '+row.entry_name+' · '+row.grade_name+' '+row.section_name} className="my-4 rounded-xl border p-5">
      <h4 className="font-semibold">{row.teacher_name}</h4><p>{row.entry_name} · {row.grade_name} {row.section_name} · {row.period_name}</p>
      <p>Estado: {({planned:'Planificada',active:'Activa',closed:'Cerrada'})[row.state]}</p><p>Inicio: {row.effective_from} · Fin: {row.effective_until??'Sin fecha de fin'}</p>
      {row.replaces_assignment_id&&<p>Asignación de reemplazo; el contexto anterior se conserva en el historial.</p>}
      <div className="mt-3 flex flex-wrap gap-3">
        {row.state==='planned'&&row.period_state==='active'&&<button className={control} disabled={disabled} onClick={()=>setAction({row,kind:'activate'})}>Activar asignación</button>}
        {row.state==='active'&&<><button className={control} disabled={disabled} onClick={()=>setAction({row,kind:'close'})}>Cerrar asignación</button>{row.period_state==='active'&&<button className={control} disabled={disabled} onClick={()=>setAction({row,kind:'replace'})}>Reemplazar docente</button>}</>}
      </div>
    </article>)}
    {action&&<form onSubmit={confirm} aria-label="Confirmar cambio de asignación" className="my-5 rounded-xl border p-5">
      <fieldset disabled={disabled} className="flex flex-wrap gap-4"><legend className="mb-3 font-semibold">{({activate:'Activar asignación',close:'Cerrar asignación',replace:'Reemplazar docente'})[action.kind]}: {action.row.teacher_name}</legend>
        <p className="w-full">{action.kind==='replace'?'El reemplazo se aplica al confirmar el servidor y conserva la autoría anterior.':action.kind==='close'?'El cierre conserva la asignación y sus recursos históricos.':'El servidor comprobará el período, fechas y conflictos antes de activar.'}</p>
        {action.kind==='close'&&<label>Fecha de cierre <input name="effective_until" type="date" required className={control}/></label>}
        {action.kind==='replace'&&<label>Nuevo docente <select aria-label="Nuevo docente" name="teacher_id" required className={control}><option value="">Selecciona un docente</option>{options.teachers.filter(row=>row.id!==action.row.teacher_id).map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>}
        <button type="submit" className={control}>Confirmar {({activate:'activación',close:'cierre',replace:'reemplazo'})[action.kind]}</button><button type="button" className={control} onClick={()=>setAction(null)}>Cancelar</button>
      </fieldset>
    </form>}
    {cursor&&<button className={control} disabled={busy} onClick={()=>void load(cursor)}>Cargar más asignaciones</button>}
  </section>;
}
