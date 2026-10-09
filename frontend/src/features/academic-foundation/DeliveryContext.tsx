import {useEffect,useRef,useState} from 'react';
import {AcademicReadView} from './AcademicReadView.js';
import type {ActivityClient} from './activities.js';
import {historicalSubmission} from './contracts.js';
export function DeliveryContext({client,id,onError}:{client:ActivityClient;id:string;onError:(error:unknown)=>void}){
 const [value,setValue]=useState<ReturnType<typeof historicalSubmission>|null>(null),[busy,setBusy]=useState(false),[open,setOpen]=useState(false),[failed,setFailed]=useState(false);const generation=useRef(0);
 useEffect(()=>()=>{generation.current++;},[id,client]);
 async function consult(){const current=++generation.current;setBusy(true);setValue(null);setFailed(false);setOpen(true);try{const result=await client.history(id);if(current===generation.current)setValue(result);}catch(e){if(current===generation.current){setFailed(true);onError(e);}}finally{if(current===generation.current)setBusy(false);}}
 return <div className="my-4" aria-label="Contexto original de la entrega" aria-busy={busy}><button className="rounded-lg border border-eval-teal px-4 py-2" disabled={busy} onClick={()=>open?setOpen(false):void consult()}>{open?'Ocultar contexto original':'Ver contexto original'}</button>{open&&<div className="mt-3">{busy&&<p role="status">Consultando contexto original…</p>}{failed&&<p role="alert">No se pudo consultar el contexto. Vuelve a consultar.</p>}{value&&<AcademicReadView kind="history" value={value}/>}<button className="my-3 rounded-lg border px-4 py-2" disabled={busy} onClick={()=>void consult()}>Consultar contexto original</button></div>}</div>;
}
