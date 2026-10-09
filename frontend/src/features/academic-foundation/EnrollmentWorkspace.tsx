import {useEffect,useRef,useState,type FormEvent} from 'react';
import {AcademicApiError,type AcademicClient} from './api.js';
import {catalogPage,periodPage,type CatalogRow} from './directory.js';
import {enrollmentPage,studentPage,type enrollmentRow,type studentRow} from './enrollments.js';
import type {period} from './contracts.js';

type Enrollment=ReturnType<typeof enrollmentRow>;
type Student=ReturnType<typeof studentRow>;
type Period=ReturnType<typeof period>;
type Options={students:Student[];periods:Period[];grades:CatalogRow[];sections:CatalogRow[]};
type OptionKind=keyof Options;
const uncertainClients=new WeakSet<AcademicClient>();
const control='rounded-lg border border-eval-ink/30 px-3 py-2';

export function EnrollmentWorkspace({client,onExpired}:{client:AcademicClient;onExpired:()=>void}){
  const [rows,setRows]=useState<Enrollment[]>([]);
  const [cursor,setCursor]=useState<string|null>(null);
  const [options,setOptions]=useState<Options>({students:[],periods:[],grades:[],sections:[]});
  const [optionCursors,setOptionCursors]=useState<Record<OptionKind,string|null>>({students:null,periods:null,grades:null,sections:null});
  const [phase,setPhase]=useState<'loading'|'ready'|'error'>('loading');
  const [busy,setBusy]=useState(false);
  const [message,setMessage]=useState('');
  const [uncertain,setUncertain]=useState(()=>uncertainClients.has(client));
  const [grade,setGrade]=useState('');
  const [section,setSection]=useState('');
  const [action,setAction]=useState<{row:Enrollment;kind:'close'|'transfer'}|null>(null);
  const [destinationGrade,setDestinationGrade]=useState('');
  const [destinationSection,setDestinationSection]=useState('');
  const mounted=useRef(true);
  const generation=useRef(0);
  const writing=useRef(false);
  const feedback=useRef<HTMLParagraphElement>(null);

  function failed(error:unknown){
    if(error instanceof AcademicApiError){
      if(error.status===401||error.status===419){onExpired();return;}
      if(error.outcomeUnknown){uncertainClients.add(client);setUncertain(true);setMessage('No se pudo confirmar el resultado. No repitas la operación; verifica los registros con administración.');return;}
      setMessage(error.status===403?'No tienes permiso para administrar matrículas.':error.status===409?'La matrícula entra en conflicto con el estado actual. Consulta los registros.':error.status===422?'Revisa el estudiante, aula y fechas. El servidor rechazó la operación.':'No se pudo consultar o guardar la información. Vuelve a consultar.');
    }else setMessage('No se pudo validar la información recibida. Vuelve a consultar.');
  }
  async function load(after:string|null=null){
    const request=++generation.current;setPhase('loading');
    try{
      const result=await client.enrollmentDirectory('enrollments',enrollmentPage,after);
      if(!mounted.current||request!==generation.current)return;
      setRows(old=>after?[...old,...result.items]:result.items);setCursor(result.next_after);setPhase('ready');return result;
    }catch(error){if(mounted.current&&request===generation.current){setRows([]);setPhase('error');failed(error);}}
  }
  async function loadOption(kind:OptionKind,after:string|null=null){
    const result=kind==='students'?await client.enrollmentDirectory('enrollment-students',studentPage,after):
      kind==='periods'?await client.directory('periods',periodPage,after):
      await client.directory(kind==='grades'?'catalog/grade':'catalog/section',catalogPage(kind==='grades'?'grade':'section'),after);
    if(!mounted.current)return;
    setOptions(old=>({...old,[kind]:after?[...old[kind],...result.items]:result.items}));
    setOptionCursors(old=>({...old,[kind]:result.next_after}));
  }
  async function refresh(){
    setBusy(true);setMessage('');
    try{await Promise.all([load(),...(['students','periods','grades','sections'] as const).map(kind=>loadOption(kind))]);}
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
      if(!page){setMessage('Operación confirmada. No se pudo actualizar el listado; vuelve a consultar los registros.');return;}
      const changedId=result.successor_id??result.id;
      if(changedId&&!page.items.some(row=>row.id===changedId)){
        const prior=(BigInt(changedId)-1n).toString();
        const changed=await client.enrollmentDirectory('enrollments',enrollmentPage,prior==='0'?null:prior);
        if(!mounted.current)return;
        const row=changed.items.find(item=>item.id===changedId);
        if(row)setRows(old=>old.some(item=>item.id===row.id)?old:[...old,row]);
      }
      setMessage('Operación confirmada. Matrículas actualizadas.');
    }
    catch(error){if(mounted.current)failed(error);}
    finally{writing.current=false;if(mounted.current)setBusy(false);}
  }
  function create(event:FormEvent<HTMLFormElement>){
    event.preventDefault();const data=new FormData(event.currentTarget);
    void mutate(()=>client.createEnrollment({student_id:String(data.get('student_id')),academic_period_id:String(data.get('academic_period_id')),grade_id:grade,section_id:section,effective_from:String(data.get('effective_from')),...(data.get('effective_until')?{effective_until:String(data.get('effective_until'))}:{})}));
  }
  function confirm(event:FormEvent<HTMLFormElement>){
    event.preventDefault();if(!action)return;
    const selected=action;const data=new FormData(event.currentTarget);
    void mutate(()=>selected.kind==='close'?client.closeEnrollment(selected.row.id,String(data.get('effective_until'))):client.transfer(selected.row.id,{grade_id:destinationGrade,section_id:destinationSection}));
  }
  const disabled=busy||uncertain||phase!=='ready';
  return <section aria-label="Gestión de matrículas" aria-busy={busy||phase==='loading'}>
    <h3 className="text-xl font-semibold">Estudiantes por aula</h3>
    <p className="my-3">Administra matrículas y conserva el historial de cada estudiante. Los traslados se aplican inmediatamente después de la confirmación del servidor.</p>
    <button className={control} disabled={busy} onClick={()=>void refresh()}>Actualizar matrículas</button>
    {phase==='loading'&&<p role="status">Consultando matrículas…</p>}
    {message&&<p role="status" tabIndex={-1} ref={feedback} className="my-4 rounded-lg border p-3">{message}</p>}
    {uncertain&&<p role="alert">Operaciones suspendidas para evitar duplicados. Consulta los registros antes de continuar.</p>}
    <form onSubmit={create} aria-label="Crear matrícula" className="my-5 rounded-xl border p-5">
      <fieldset disabled={disabled} className="flex flex-wrap gap-4">
        <legend className="mb-3 font-semibold">Nueva matrícula</legend>
        <label>Estudiante<select aria-label="Estudiante" name="student_id" required className={control}><option value="">Selecciona un estudiante</option>{options.students.map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        <label>Período<select aria-label="Período" name="academic_period_id" required className={control}><option value="">Selecciona un período activo</option>{options.periods.filter(row=>row.state==='active').map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        <label>Grado<select aria-label="Grado" required className={control} value={grade} onChange={event=>{setGrade(event.target.value);setSection('');}}><option value="">Selecciona un grado</option>{options.grades.filter(row=>row.is_active).map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        <label>Sección<select aria-label="Sección" required className={control} value={section} onChange={event=>setSection(event.target.value)}><option value="">Selecciona una sección</option>{options.sections.filter(row=>row.is_active&&row.grade_id===grade).map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        <label>Inicio<input name="effective_from" type="date" required className={control}/></label>
        <label>Fin (opcional)<input name="effective_until" type="date" className={control}/></label>
        <button type="submit" className={control}>Crear matrícula</button>
      </fieldset>
    </form>
    <div className="my-3 flex flex-wrap gap-3">{(['students','periods','grades','sections'] as const).map(kind=>optionCursors[kind]&&<button key={kind} className={control} disabled={busy} onClick={()=>{setBusy(true);void loadOption(kind,optionCursors[kind]).catch(failed).finally(()=>setBusy(false));}}>Cargar más {({students:'estudiantes',periods:'períodos',grades:'grados',sections:'secciones'})[kind]}</button>)}</div>
    {phase==='ready'&&rows.length===0&&<p>No hay matrículas para mostrar.</p>}
    {phase==='ready'&&rows.map(row=><article key={row.id} aria-label={row.student_name+' · '+row.grade_name+' '+row.section_name} className="my-4 rounded-xl border p-5">
      <h4 className="font-semibold">{row.student_name}</h4><p>{row.period_name} · {row.grade_name} {row.section_name}</p>
      <p>Estado: {({active:'Activa',transferred:'Trasladada',closed:'Cerrada'})[row.state]}</p><p>Inicio: {row.effective_from} · Fin: {row.effective_until??'Sin fecha de fin'}</p>
      {row.state==='active'&&<div className="mt-3 flex gap-3"><button className={control} disabled={disabled} onClick={()=>{setDestinationGrade(row.grade_id);setDestinationSection('');setAction({row,kind:'transfer'});}}>Trasladar</button><button className={control} disabled={disabled} onClick={()=>setAction({row,kind:'close'})}>Cerrar matrícula</button></div>}
    </article>)}
    {action&&<form onSubmit={confirm} aria-label="Confirmar cambio de matrícula" className="my-5 rounded-xl border p-5">
      <fieldset disabled={disabled} className="flex flex-wrap gap-4"><legend className="mb-3 font-semibold">{action.kind==='close'?'Cerrar matrícula':'Traslado inmediato'}: {action.row.student_name}</legend>
        <p className="w-full">{action.kind==='close'?'El cierre conserva esta matrícula en el historial.':'El traslado cierra el contexto actual y crea la matrícula de destino; no cambia las entregas anteriores.'}</p>
        {action.kind==='close'?<label>Fecha de cierre<input name="effective_until" type="date" required className={control}/></label>:<>
          <label>Grado de destino<select aria-label="Grado de destino" required className={control} value={destinationGrade} onChange={event=>{setDestinationGrade(event.target.value);setDestinationSection('');}}><option value="">Selecciona un grado</option>{options.grades.filter(row=>row.is_active).map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
          <label>Sección de destino<select aria-label="Sección de destino" required className={control} value={destinationSection} onChange={event=>setDestinationSection(event.target.value)}><option value="">Selecciona una sección</option>{options.sections.filter(row=>row.is_active&&row.grade_id===destinationGrade).map(row=><option key={row.id} value={row.id}>{row.name}</option>)}</select></label>
        </>}
        <button type="submit" className={control}>Confirmar {action.kind==='close'?'cierre':'traslado'}</button><button type="button" className={control} onClick={()=>setAction(null)}>Cancelar</button>
      </fieldset>
    </form>}
    {cursor&&<button className={control} disabled={busy} onClick={()=>void load(cursor)}>Cargar más matrículas</button>}
  </section>;
}
