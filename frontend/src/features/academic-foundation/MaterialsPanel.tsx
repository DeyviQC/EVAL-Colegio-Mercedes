import { useEffect, useRef, useState, type FormEvent } from 'react';
import { AcademicApiError } from './api.js';
import { publicationForm, materialFileUrl, type createMaterialClient, type Material } from './materials.js';
import type { Course } from './courses.js';
type View={phase:'loading'|'ready'|'error';items:Material[];cursor:string|null};
const button='rounded-lg border border-eval-teal px-4 py-2 font-semibold';
function message(error:unknown){
  if(!(error instanceof AcademicApiError))return error instanceof Error?error.message:'No se pudo completar la operación.';
  if(error.outcomeUnknown)return 'No se pudo confirmar la publicación. No la repitas: consulta los materiales y verifica el resultado.'+(error.correlationId?' Referencia: '+error.correlationId:'');
  if(error.status===401||error.status===419)return 'Tu sesión debe verificarse nuevamente.';
  if(error.status===403||error.status===404)return 'No tienes acceso a estos materiales o el curso ya no está disponible.';
  if(error.status===422)return 'Revisa el archivo y los campos. El servidor no admite ese contenido; no se permiten macros.';
  if(error.status===409)return 'El curso cambió. Vuelve a abrirlo antes de publicar.';
  return 'No se pudo completar la operación. Verifica la conexión y consulta nuevamente.';
}
export function MaterialsPanel({course,client,onExpired,onBack}:{course:Course;client:ReturnType<typeof createMaterialClient>;onExpired:()=>void;onBack:()=>void}){
  const [view,setView]=useState<View>({phase:'loading',items:[],cursor:null});const [formOpen,setFormOpen]=useState(false);
  const [busy,setBusy]=useState(false),[notice,setNotice]=useState(''),[error,setError]=useState(''),[blocked,setBlocked]=useState(()=>client.isUncertain(course.id));
  const generation=useRef(0),writing=useRef(false),alive=useRef(true),titleRef=useRef<HTMLInputElement>(null),errorRef=useRef<HTMLParagraphElement>(null);
  function fail(problem:unknown){setError(message(problem));if(problem instanceof AcademicApiError){if(problem.status===401||problem.status===419)onExpired();if(problem.outcomeUnknown||problem.status===409)setBlocked(true);}}
  async function load(after:string|null=null,focusId:string|null=null){const current=++generation.current;setView(old=>({...old,phase:'loading'}));
    try{const page=await client.list(course.id,after);if(!alive.current||current!==generation.current)return;
      if(focusId&&!page.items.some(item=>item.id===focusId)){const item=await client.detail(focusId);if(!alive.current||current!==generation.current)return;setView({phase:'ready',items:[item,...page.items],cursor:page.next_after});}
      else setView(old=>({phase:'ready',items:after?[...old.items,...page.items.filter(item=>!old.items.some(existing=>existing.id===item.id))]:page.items,cursor:page.next_after}));
    }catch(problem){if(alive.current&&current===generation.current){setView({phase:'error',items:[],cursor:null});fail(problem);}}
  }
  useEffect(()=>{alive.current=true;void load();return ()=>{alive.current=false;generation.current++;};},[course.id,client]);
  useEffect(()=>{if(formOpen)titleRef.current?.focus();},[formOpen]);useEffect(()=>{if(error)errorRef.current?.focus();},[error]);
  async function submit(event:FormEvent<HTMLFormElement>){event.preventDefault();if(writing.current||blocked)return;const form=event.currentTarget,data=new FormData(form),file=data.get('file');
    setError('');setNotice('');if(!(file instanceof File)){setError('Selecciona un archivo.');return;}
    let payload:FormData;try{payload=publicationForm(String(data.get('title')??''),String(data.get('description')??''),file);}catch(problem){fail(problem);return;}
    writing.current=true;setBusy(true);try{const result=await client.publish(course.id,payload);if(!alive.current)return;
      form.reset();setFormOpen(false);setNotice('Material publicado correctamente.');await load(null,result.id);
    }catch(problem){if(alive.current)fail(problem);}finally{writing.current=false;if(alive.current)setBusy(false);}
  }
  return <section aria-label="Materiales del curso" aria-busy={busy||view.phase==='loading'} className="mt-6">
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-eval-teal/20 pb-4"><h4 className="text-xl font-semibold">Materiales</h4>
      {course.can_publish&&<button className={button+' bg-eval-teal text-white'} disabled={busy||blocked} onClick={()=>setFormOpen(true)}>Publicar material</button>}</div>
    <div className="my-4 flex flex-wrap gap-3"><button className={button} disabled={busy||view.phase==='loading'} onClick={()=>{setError('');void load();}}>Consultar materiales</button><button className={button} disabled={busy} onClick={onBack}>Volver al curso</button></div>
    {notice&&<p role="status" className="my-4 rounded-lg bg-emerald-50 p-4 text-emerald-900">{notice}</p>}
    {error&&<p role="alert" tabIndex={-1} ref={errorRef} className="my-4 rounded-lg border border-amber-300 bg-amber-50 p-4">{error}</p>}
    {blocked&&<p>No repitas la publicación. Consulta los materiales y verifica el resultado antes de continuar.</p>}
    {formOpen&&<form onSubmit={event=>void submit(event)} aria-label="Publicar material" className="my-5 rounded-xl border bg-white p-5">
      <fieldset disabled={busy||blocked} className="space-y-4"><legend className="mb-3 text-lg font-semibold">Nuevo material</legend>
        <label className="block">Título<input ref={titleRef} name="title" required maxLength={255} className="mt-2 block w-full rounded-lg border p-3"/></label>
        <label className="block">Descripción (opcional)<textarea name="description" maxLength={10000} rows={3} className="mt-2 block w-full rounded-lg border p-3"/></label>
        <label className="block">Archivo<input name="file" type="file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png" aria-describedby="material-file-help" className="mt-2 block w-full rounded-lg border p-3"/></label>
        <p id="material-file-help" className="text-sm">PDF, Word, PowerPoint, Excel o imagen JPG/PNG. Hasta 25 MiB. No se admiten macros.</p>
        <div className="flex flex-wrap gap-3"><button type="submit" className={button+' bg-eval-teal text-white'}>{busy?'Publicando…':'Publicar'}</button><button type="button" className={button} onClick={()=>setFormOpen(false)}>Cancelar</button></div>
      </fieldset></form>}
    {view.phase==='loading'&&<p role="status">Consultando materiales…</p>}
    {view.phase==='ready'&&view.items.length===0&&view.cursor===null&&<div className="rounded-xl border border-dashed p-8 text-center"><h5 className="font-semibold">Todavía no hay materiales</h5><p className="mt-2">{course.can_publish?'Publica el primer recurso para este curso.':'Aquí encontrarás los recursos que corresponden a tu curso.'}</p></div>}
    {view.phase==='ready'&&<div className="grid gap-4 md:grid-cols-2">{view.items.map(item=><article key={item.id} aria-label={item.title} className="rounded-xl border bg-white p-5">
      <span className="text-sm text-eval-teal">{item.filename.split('.').pop()?.toUpperCase()} · {item.bytes<1024*1024?Math.max(1,Math.ceil(item.bytes/1024))+' KiB':(item.bytes/1024/1024).toFixed(2)+' MiB'}</span><h5 className="mt-2 text-lg font-semibold whitespace-pre-wrap">{item.title}</h5>
      <p className="mt-2 text-sm whitespace-pre-wrap">Publicado por {item.author_name}</p><p className="text-sm">{new Intl.DateTimeFormat('es-PE',{timeZone:'America/Lima',dateStyle:'medium'}).format(new Date(item.published_at.replace(' ','T')+'Z'))}</p>
      {item.description&&<p className="my-3 whitespace-pre-wrap">{item.description}</p>}<div className="mt-4 flex flex-wrap gap-3">
        {['application/pdf','image/jpeg','image/png'].includes(item.mime)&&<a className={button} href={materialFileUrl(item.id,'inline')} target="_blank" rel="noopener noreferrer">Abrir</a>}
        <a className={button} href={materialFileUrl(item.id,'attachment')} download>Descargar</a></div></article>)}</div>}
    {view.phase==='ready'&&view.cursor&&<button className={button+' mt-5'} disabled={busy} onClick={()=>void load(view.cursor)}>Cargar más materiales</button>}
  </section>;
}
