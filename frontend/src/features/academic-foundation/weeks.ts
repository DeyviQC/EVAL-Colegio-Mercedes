import {decimalId,exact,text} from './contracts.js';
import {calendarRevision} from './calendars.js';
import {material} from './materials.js';
import {activity} from './activities.js';
import {AcademicApiError} from './api.js';
export type ResourceKind='material'|'activity';
export type WeekSelection={bucket:'unassigned'|'earlier'|'week';revision?:string;week?:string};
export function weekAssociation(value:unknown){
 const r=exact(value,['kind','resource_id','assignment_id','period_id','version','calendar_revision_id','week_id','earlier_calendar']);
 if(r.kind!=='material'&&r.kind!=='activity'||!Number.isInteger(r.version)||(r.version as number)<0||(r.version as number)>2147483647||typeof r.earlier_calendar!=='boolean')throw Error('Invalid association');
 const revision=r.calendar_revision_id===null?null:decimalId(r.calendar_revision_id);const week=r.week_id===null?null:text(r.week_id);
 if((revision===null)!==(week===null)||week!==null&&!/^[a-f0-9]{32}$/.test(week)||revision===null&&r.earlier_calendar||r.version===0&&revision!==null)throw Error('Invalid week scope');
 return {kind:r.kind as ResourceKind,resource_id:decimalId(r.resource_id),assignment_id:decimalId(r.assignment_id),period_id:decimalId(r.period_id),version:r.version as number,calendar_revision_id:revision,week_id:week,earlier_calendar:r.earlier_calendar};
}
export type WeekAssociation=ReturnType<typeof weekAssociation>;
export function courseCalendar(value:unknown){const r=exact(value,['course_id','period_id','current','retained']);const course_id=decimalId(r.course_id),period_id=decimalId(r.period_id);if(!Array.isArray(r.retained))throw Error('Invalid retained calendars');const current=r.current===null?null:calendarRevision(r.current),retained=r.retained.map(calendarRevision);const ids=new Set<string>();for(const item of [current,...retained])if(item){if(item.period_id!==period_id||ids.has(item.id))throw Error('Invalid calendar scope');ids.add(item.id);}return {course_id,period_id,current,retained};}
export type CourseCalendar=ReturnType<typeof courseCalendar>;
export function weekPage(value:unknown){const r=exact(value,['course_id','kind','bucket','total','items','next_after']);if(r.kind!=='material'&&r.kind!=='activity'||!['week','unassigned','earlier'].includes(String(r.bucket))||!Number.isSafeInteger(r.total)||(r.total as number)<0||!Array.isArray(r.items)||r.items.length>50)throw Error('Invalid week page');
 let prior=0n;const items=r.items.map(raw=>{const pair=exact(raw,['resource','association']);const resource=r.kind==='material'?material(pair.resource):activity(pair.resource),association=weekAssociation(pair.association);if(association.kind!==r.kind||association.resource_id!==resource.id||association.assignment_id!==resource.assignment_id||BigInt(resource.id)<=prior)throw Error('Invalid resource scope');prior=BigInt(resource.id);return {resource,association};});
 const next_after=r.next_after===null?null:decimalId(r.next_after);if((r.total as number)<items.length||next_after!==null&&(items.length!==50||next_after!==items[49]?.resource.id))throw Error('Invalid week cursor');return {course_id:decimalId(r.course_id),kind:r.kind as ResourceKind,bucket:r.bucket as WeekSelection['bucket'],total:r.total as number,items,next_after};
}
export type WeekPage=ReturnType<typeof weekPage>;
export function createWeeksClient(csrf:()=>string,transport:typeof fetch=fetch){
 let pending=false,unknown:string|null=null,reviewed=false,generation=0;
 const key=(kind:ResourceKind,id:string)=>kind+'/'+decimalId(id);
 async function request<T>(path:string,decode:(value:unknown)=>T,body?:unknown,target?:string):Promise<T>{const unsafe=body!==undefined;
  if(unsafe&&(pending||unknown))throw new AcademicApiError(unknown?'week_outcome_unknown':'operation_pending',409,null,unknown!==null);
  const token=unsafe?csrf():'';if(unsafe&&!token)throw new AcademicApiError('csrf_missing',419);if(unsafe){pending=true;generation++;reviewed=false;}
  try{let response:Response;try{response=await transport('/academic/'+path,{method:unsafe?'POST':'GET',credentials:'same-origin',cache:'no-store',redirect:'error',headers:{Accept:'application/json',...(unsafe?{'Content-Type':'application/json','X-CSRF-TOKEN':token}:{})},...(unsafe?{body:JSON.stringify(body)}:{})});}catch{throw new AcademicApiError('transport_unavailable',null,null,unsafe);}
   let wire:unknown;try{wire=await response.json();}catch{throw new AcademicApiError('invalid_response',response.status,null,unsafe);}
   if(!response.ok){let category='invalid_response';try{const r=exact(wire,['error'],response.status===503?['correlation_id','automatic_retry']:[]);category=text(r.error);if(!/^[a-z_]+$/.test(category)||response.status===503&&r.automatic_retry!==false)throw Error();}catch{category='invalid_response';}throw new AcademicApiError(category,response.status,null,unsafe&&(response.status>=500||category==='invalid_response'));}
   try{return decode(exact(wire,['data']).data);}catch{throw new AcademicApiError('invalid_response',response.status,null,unsafe);}
  }catch(error){if(unsafe&&error instanceof AcademicApiError&&error.outcomeUnknown){unknown=target??null;reviewed=false;}throw error;}finally{if(unsafe)pending=false;}
 }
 return {isUncertain:()=>unknown!==null,uncertainTarget:()=>unknown,canResumeWrites:()=>unknown!==null&&reviewed&&!pending,
  resumeWrites:()=>{if(!reviewed||pending)throw Error('Matching consultation required');unknown=null;reviewed=false;},
  calendar:(course:string)=>request('my-courses/'+decimalId(course)+'/weeks',v=>{const result=courseCalendar(v);if(result.course_id!==course)throw Error();return result;}),
  content:(course:string,kind:ResourceKind,selection:WeekSelection,after:string|null=null,term='')=>{const q=new URLSearchParams({bucket:selection.bucket});if(selection.bucket==='week'){q.set('revision',decimalId(selection.revision));if(!selection.week||!/^[a-f0-9]{32}$/.test(selection.week))throw Error('Invalid week');q.set('week',selection.week);}if(after)q.set('after',decimalId(after));if(term.trim())q.set('term',term.trim());return request('my-courses/'+decimalId(course)+'/weeks/'+kind+'?'+q,v=>{const result=weekPage(v);if(result.course_id!==course||result.kind!==kind||result.bucket!==selection.bucket||after!==null&&result.items.some(i=>BigInt(i.resource.id)<=BigInt(after))||result.items.some(i=>selection.bucket==='unassigned'?i.association.week_id!==null:selection.bucket==='earlier'?!i.association.earlier_calendar:i.association.calendar_revision_id!==selection.revision||i.association.week_id!==selection.week))throw Error();return result;});},
  consult:async(kind:ResourceKind,id:string)=>{const target=key(kind,id),before=generation;if(unknown===target)reviewed=false;const result=await request('resource-weeks/'+target,v=>{const row=weekAssociation(v);if(row.kind!==kind||row.resource_id!==id)throw Error();return row;});if(unknown===target&&!pending&&before===generation)reviewed=true;return result;},
  set:(association:WeekAssociation,revision:string|null,week:string|null)=>{if((revision===null)!==(week===null)||week!==null&&!/^[a-f0-9]{32}$/.test(week))throw Error('Invalid week');const target=key(association.kind,association.resource_id);return request('resource-weeks/'+target,v=>{const row=weekAssociation(v);if(row.kind!==association.kind||row.resource_id!==association.resource_id||row.assignment_id!==association.assignment_id||row.period_id!==association.period_id||row.version!==association.version+1||row.calendar_revision_id!==revision||row.week_id!==week)throw Error('Invalid acknowledgement');return row;},{expected_version:association.version,calendar_revision_id:revision===null?null:decimalId(revision),week_id:week},target);}
 };
}
export type WeeksClient=ReturnType<typeof createWeeksClient>;
