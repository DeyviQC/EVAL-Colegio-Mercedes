import { createObservationClient } from './material-observations.js';
import { createMaterialMaintenanceClient } from './material-maintenance.js';
import { exact, text, decimalId, identityResult } from './contracts.js';
import { AcademicApiError } from './api.js';
export const materialLimit=25*1024*1024;
export function material(value:unknown){
  const row=exact(value,['id','assignment_id','author_id','author_name','title','description','filename','mime','bytes','published_at']);
  if(row.description!==null&&typeof row.description!=='string')throw new Error('Invalid description');
  if(typeof row.bytes!=='number'||!Number.isSafeInteger(row.bytes)||row.bytes<1||row.bytes>materialLimit)throw new Error('Invalid file size');
  const filename=text(row.filename);if(/[\x00-\x1f\x7f\\/]/.test(filename))throw new Error('Invalid filename');
  const mime=text(row.mime);if(!['application/pdf','application/msword','application/vnd.ms-powerpoint','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.openxmlformats-officedocument.presentationml.presentation','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','image/jpeg','image/png'].includes(mime))throw new Error('Invalid type');
  const published=text(row.published_at),parsed=new Date(published.replace(' ','T')+'Z');if(!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/.test(published)||!Number.isFinite(parsed.getTime())||parsed.toISOString().slice(0,19)!==published.replace(' ','T').slice(0,19))throw new Error('Invalid date');
  return {id:decimalId(row.id),assignment_id:decimalId(row.assignment_id),author_id:decimalId(row.author_id),author_name:text(row.author_name),title:text(row.title),description:row.description as string|null,filename,mime,bytes:row.bytes,published_at:published};
}
export type Material=ReturnType<typeof material>;
export function materialsPage(value:unknown){const row=exact(value,['items','next_after']);if(!Array.isArray(row.items)||row.items.length>50)throw new Error('Invalid page');
  const items=row.items.map(material);let prior=0n;for(const item of items){if(BigInt(item.id)<=prior)throw new Error('Invalid order');prior=BigInt(item.id);}
  const next_after=row.next_after===null?null:decimalId(row.next_after);if(next_after!==null&&BigInt(next_after)<prior)throw new Error('Invalid cursor');return {items,next_after};
}
export function materialFileUrl(id:string,disposition:'inline'|'attachment'){if(!['inline','attachment'].includes(disposition))throw new Error('Invalid disposition');return '/academic/materials/'+decimalId(id)+'/file?disposition='+disposition;}
export function publicationForm(title:string,description:string,file:File){
  if(!title.trim()||title.length>255||description.length>10000)throw new Error('Revisa el título y la descripción.');
  if(file.size<1||file.size>materialLimit)throw new Error('Selecciona un archivo de hasta 25 MiB.');
  if(!/\.(pdf|docx?|pptx?|xlsx?|jpe?g|png)$/i.test(file.name))throw new Error('El formato del archivo no está permitido.');
  const form=new FormData();form.set('title',title);if(description)form.set('description',description);form.set('file',file);return form;
}
/** UI validation is convenience only; server remains authoritative for types, macros and scope. */
export function createMaterialClient(transport:typeof fetch,csrf:()=>string){
  const pending=new Set<string>(),uncertain=new Set<string>();
  async function request<T>(path:string,decode:(value:unknown)=>T,body?:FormData){let response:Response;
    const headers:Record<string,string>={Accept:'application/json'};if(body){const token=csrf();if(!token)throw new AcademicApiError('session_not_initialized',419);headers['X-CSRF-TOKEN']=text(token);}
    try{response=await transport('/academic/'+path,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',redirect:'error',headers,...(body?{body}:{})});}
    catch{throw new AcademicApiError('transport_unavailable',null,null,!!body);}
    let wire:unknown;try{wire=await response.json();}catch{throw new AcademicApiError('invalid_response',response.status,null,!!body);}
    if(!response.ok){let category='invalid_response',correlation:string|null=null;
      try{const row=exact(wire,['error'],response.status===503?['correlation_id','automatic_retry']:[]);category=text(row.error);
        if(!/^[a-z_]+$/.test(category)||(response.status===503&&row.automatic_retry!==false))throw new Error();
        correlation=row.correlation_id===undefined||row.correlation_id===null?null:text(row.correlation_id);if(correlation!==null&&!/^[a-f0-9]{32}$/.test(correlation))throw new Error();
      }catch{category='invalid_response';correlation=null;}
      throw new AcademicApiError(category,response.status,correlation,!!body&&(category==='invalid_response'||['commit_outcome_unknown','rollback_outcome_unknown'].includes(category)));
    }
    try{return decode(exact(wire,['data']).data);}catch{throw new AcademicApiError('invalid_response',response.status,null,!!body);}
  }
  return {observations:createObservationClient(csrf,transport),maintenance:createMaterialMaintenanceClient(transport,csrf),search:(id:string,term:string,after:string|null=null)=>{term=term.trim();if(Array.from(term).length>100||/[\x00-\x1f\x7f]/.test(term))throw new Error('Invalid search');return request('my-courses/'+decimalId(id)+'/library?q='+encodeURIComponent(term)+(after?'&after='+decimalId(after):''),materialsPage);},list:(id:string,after:string|null=null)=>request('my-courses/'+decimalId(id)+'/materials'+(after?'?after='+decimalId(after):''),materialsPage),
    detail:(id:string)=>request('materials/'+decimalId(id),material),isUncertain:(id:string)=>uncertain.has(decimalId(id)),
    publish:async(id:string,form:FormData)=>{id=decimalId(id);if(uncertain.has(id))throw new AcademicApiError('commit_outcome_unknown',503,null,true);if(pending.has(id))throw new AcademicApiError('publication_pending',409);
      pending.add(id);try{return await request('my-courses/'+id+'/materials',identityResult,form);}catch(problem){if(problem instanceof AcademicApiError&&problem.outcomeUnknown)uncertain.add(id);throw problem;}finally{pending.delete(id);}}
  };
}
