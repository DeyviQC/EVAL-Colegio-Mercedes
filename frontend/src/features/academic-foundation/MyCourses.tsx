import { useEffect, useRef, useState } from 'react';
import { AcademicApiError, type createAcademicClient } from './api.js';
import { course, coursesPage, type Course } from './courses.js';
import { MaterialsPanel } from './MaterialsPanel.js';
import type { createMaterialClient } from './materials.js';
export function MyCourses({client,materials,onExpired,materialsOnly=false}:{client:ReturnType<typeof createAcademicClient>;materials:ReturnType<typeof createMaterialClient>;onExpired:()=>void;materialsOnly?:boolean}){
  const [materialOpen,setMaterialOpen]=useState(false);
  const [rows,setRows]=useState<Course[]>([]),[selected,setSelected]=useState<Course|null>(null),[cursor,setCursor]=useState<string|null>(null);
  const [phase,setPhase]=useState<'loading'|'ready'|'error'>('loading');const [message,setMessage]=useState('');const generation=useRef(0);
  function fail(error:unknown){setRows([]);setSelected(null);setCursor(null);setPhase('error');
    if(error instanceof AcademicApiError&&(error.status===401||error.status===419)){onExpired();return;}
    setMessage(error instanceof AcademicApiError&&error.status===403?'No tienes permiso para consultar estos cursos.':'No se pudo confirmar el contexto de tus cursos. Consulta nuevamente.');}
  async function load(after:string|null=null){const current=++generation.current;setPhase('loading');setMessage('');setSelected(null);
    try{const page=await client.myCourses(coursesPage,after);if(current!==generation.current)return;setRows(old=>after?[...old,...page.items]:page.items);setCursor(page.next_after);setPhase('ready');}
    catch(error){if(current===generation.current)fail(error);}}
  async function open(id:string){const current=++generation.current;setPhase('loading');setMaterialOpen(false);
    try{const result=await client.course(id,course);if(current!==generation.current)return;setSelected(result);setMaterialOpen(materialsOnly);setPhase('ready');}
    catch(error){if(current===generation.current)fail(error);}}
  useEffect(()=>{void load();return ()=>{generation.current++;};},[client]);
  return <section aria-label={materialsOnly?'Materiales':'Mis cursos'} aria-busy={phase==='loading'}><h3 className="text-2xl font-semibold">{selected?selected.subject:materialsOnly?'Materiales':'Mis cursos'}</h3>
    {materialsOnly&&!selected&&<p className="my-4">Selecciona un curso para consultar sus materiales.</p>}
    {phase==='loading'&&<p role="status">Consultando tus cursos…</p>}{phase==='error'&&<p role="alert">{message}</p>}
    <button className="my-4 rounded-lg border px-4 py-2" disabled={phase==='loading'} onClick={()=>void load()}>{selected?'Volver a mis cursos':'Consultar cursos'}</button>
    {phase==='ready'&&selected?<><p>Aula {selected.grade} · {selected.section} — {selected.period}</p><p>Docente: {selected.teacher}</p>
      <p>{selected.can_publish?'Comparte recursos con tus estudiantes.':'Recursos y contexto de tu curso.'}</p>
      {materialOpen?<MaterialsPanel key={selected.id} course={selected} client={materials} onExpired={onExpired} onBack={()=>setMaterialOpen(false)}/>:<button className="mt-5 rounded-lg border border-eval-teal bg-eval-teal px-5 py-3 font-semibold text-white" onClick={()=>setMaterialOpen(true)}>Ver materiales</button>}</>:
      phase==='ready'&&<><div className="grid gap-4 md:grid-cols-2">{rows.map(row=><article key={row.id} className="rounded-xl border bg-white p-5" aria-label={row.subject+' · '+row.grade+' '+row.section}>
        <h4 className="text-lg font-semibold whitespace-pre-wrap">{row.subject}</h4><p>Aula {row.grade} · {row.section}</p><p className="whitespace-pre-wrap">{row.teacher}</p><p>{row.period} · {row.state==='planned'?'Planificado':row.state==='closed'||row.period_state==='closed'?'Contexto histórico':'Activo'}</p>
        <button className="mt-3 rounded-lg border px-4 py-2" onClick={()=>void open(row.id)}>{materialsOnly?'Consultar materiales del curso':'Entrar al curso'}</button></article>)}</div>
        {rows.length===0&&<p>No tienes cursos disponibles para esta consulta.</p>}{cursor&&<button onClick={()=>void load(cursor)}>Cargar más cursos</button>}</>}
  </section>;
}
