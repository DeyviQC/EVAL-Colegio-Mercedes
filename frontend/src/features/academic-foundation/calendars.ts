import {date,decimalId,exact,text} from './contracts.js';
import {directoryPage} from './directory.js';
import {AcademicApiError} from './api.js';
export type CalendarWeek={number:number;start_on:string;end_on:string;id?:string};
export type CalendarBlock={kind:'teaching'|'management';start_on:string;end_on:string;weeks:CalendarWeek[]};
export function calendarBlocks(value:unknown,published=false):CalendarBlock[]{
 if(!Array.isArray(value)||value.length===0||value.length>100)throw new Error('Invalid calendar');
 let prior='',number=1;const ids=new Set<string>();
 const blocks=value.map(raw=>{const r=exact(raw,['kind','start_on','end_on','weeks']);if(r.kind!=='teaching'&&r.kind!=='management'||!Array.isArray(r.weeks))throw new Error('Invalid block');
  const start_on=date(r.start_on),end_on=date(r.end_on);if(start_on>end_on||prior&&start_on<=prior)throw new Error('Overlapping blocks');prior=end_on;let last='';
  const weeks=r.weeks.map(rawWeek=>{const w=exact(rawWeek,['number','start_on','end_on',...(published?['id']:[])]);const start=date(w.start_on),end=date(w.end_on);if(w.number!==number++||start>end||start<start_on||end>end_on||last&&start<=last)throw new Error('Invalid week');last=end;const week:CalendarWeek={number:w.number as number,start_on:start,end_on:end};
   if(published){const id=text(w.id);if(!/^[a-f0-9]{32}$/.test(id)||ids.has(id))throw new Error('Invalid week identity');ids.add(id);week.id=id;}return week;});
  if(r.kind==='management'&&weeks.length||r.kind==='teaching'&&!weeks.length)throw new Error('Invalid teaching weeks');return {kind:r.kind,start_on,end_on,weeks} as CalendarBlock;
 });if(number===1||number>367)throw new Error('Invalid week count');return blocks;
}
export function calendarDraft(value:unknown){const r=exact(value,['id','period_id','base_revision_id','version','state','blocks']);if(!Number.isInteger(r.version)||(r.version as number)<1||(r.version as number)>2147483647||r.state!=='draft'&&r.state!=='published')throw new Error('Invalid draft');return {id:decimalId(r.id),period_id:decimalId(r.period_id),base_revision_id:r.base_revision_id===null?null:decimalId(r.base_revision_id),version:r.version as number,state:r.state as 'draft'|'published',blocks:calendarBlocks(r.blocks)};}
export type CalendarDraft=ReturnType<typeof calendarDraft>;
export function calendarRevision(value:unknown){const r=exact(value,['id','period_id','source','recorded_at','blocks']);const source=text(r.source);if(!source.trim()||source.length>255)throw new Error('Invalid source');return {id:decimalId(r.id),period_id:decimalId(r.period_id),source,recorded_at:text(r.recorded_at),blocks:calendarBlocks(r.blocks,true)};}
export type CalendarRevision=ReturnType<typeof calendarRevision>;
export function nationalCalendar2026():CalendarBlock[]{
 const dates=[['management','03-02','03-13'],['teaching','03-16','05-15'],['management','05-18','05-22'],['teaching','05-25','07-24'],['management','07-27','08-07'],['teaching','08-10','10-09'],['management','10-12','10-16'],['teaching','10-19','12-18'],['management','12-21','12-31']];let number=1;
 return dates.map(([kind,start,end])=>{const start_on='2026-'+start,end_on='2026-'+end;const weeks:CalendarWeek[]=kind==='teaching'?Array.from({length:9},(_,index)=>{const from=new Date(start_on+'T12:00:00Z');from.setUTCDate(from.getUTCDate()+index*7);const until=new Date(from);until.setUTCDate(until.getUTCDate()+4);return {number:number++,start_on:from.toISOString().slice(0,10),end_on:until.toISOString().slice(0,10)};}):[];return {kind:kind as CalendarBlock['kind'],start_on,end_on,weeks};});
}
export function createCalendarClient(csrf:()=>string,transport:typeof fetch=fetch){
 let pending=false,uncertain=false,reviewed=false,uncertainPeriod:string|null=null,writeGeneration=0;
 async function request<T>(path:string,decode:(value:unknown)=>T,body?:unknown,scope:string|null=null):Promise<T>{const unsafe=body!==undefined;if(unsafe&&(pending||uncertain))throw new AcademicApiError(uncertain?'calendar_outcome_unknown':'operation_pending',409,null,uncertain);const token=unsafe?csrf():'';if(unsafe&&!token)throw new AcademicApiError('csrf_missing',419);if(unsafe){pending=true;reviewed=false;writeGeneration++;}
  try{let response:Response;try{response=await transport('/academic/'+path,{method:unsafe?'POST':'GET',credentials:'same-origin',cache:'no-store',redirect:'error',headers:{Accept:'application/json',...(unsafe?{'Content-Type':'application/json','X-CSRF-TOKEN':token}:{})},...(unsafe?{body:JSON.stringify(body)}:{})});}catch{throw new AcademicApiError('transport_unavailable',null,null,unsafe);}
   let wire:unknown;try{wire=await response.json();}catch{throw new AcademicApiError('invalid_response',response.status,null,unsafe);}
   if(!response.ok){let category='invalid_response';try{const r=exact(wire,['error'],response.status===503?['correlation_id','automatic_retry']:[]);category=text(r.error);if(!/^[a-z_]+$/.test(category)||response.status===503&&r.automatic_retry!==false)throw new Error();}catch{category='invalid_response';}throw new AcademicApiError(category,response.status,null,unsafe&&(response.status>=500||category==='invalid_response'));}
   try{return decode(exact(wire,['data']).data);}catch{throw new AcademicApiError('invalid_response',response.status,null,unsafe);}
  }catch(error){if(unsafe&&error instanceof AcademicApiError&&error.outcomeUnknown){uncertain=true;uncertainPeriod=scope;}throw error;}finally{if(unsafe)pending=false;}
 }
 const drafts=(period:string,after:string|null=null)=>request('periods/'+decimalId(period)+'/calendar-drafts'+(after?'?after='+decimalId(after):''),v=>directoryPage(v,calendarDraft));
 return {isUncertain:()=>uncertain,canResumeWrites:()=>reviewed&&!pending,
  consult:async(period:string)=>{reviewed=false;const generation=writeGeneration;const id=decimalId(period);const current=await request('periods/'+id+'/calendar',v=>v===null?null:calendarRevision(v));const items:CalendarDraft[]=[];let after:string|null=null;do{const page=await drafts(id,after);if(page.items.some(row=>row.period_id!==id||after!==null&&BigInt(row.id)<=BigInt(after)))throw new AcademicApiError('invalid_response',200);items.push(...page.items);after=page.next_after;}while(after);if(current&&current.period_id!==id)throw new AcademicApiError('invalid_response',200);reviewed=!pending&&generation===writeGeneration&&(!uncertain||uncertainPeriod===id);return {current,drafts:items};},
  resumeWrites:()=>{if(!reviewed||pending)throw new Error('Consultation required');uncertain=false;uncertainPeriod=null;reviewed=false;},
  create:(period:string,blocks:CalendarBlock[])=>request('periods/'+decimalId(period)+'/calendar-drafts',v=>{const result=calendarDraft(v);if(result.period_id!==period||result.version!==1||result.state!=='draft')throw new Error('Invalid create acknowledgement');return result;},{blocks:calendarBlocks(blocks)},period),
  edit:(draft:CalendarDraft,blocks:CalendarBlock[])=>request('calendar-drafts/'+decimalId(draft.id)+'/edit',v=>{const result=calendarDraft(v);if(result.id!==draft.id||result.period_id!==draft.period_id||result.version!==draft.version+1||result.state!=='draft')throw new Error('Invalid edit acknowledgement');return result;},{expected_version:draft.version,blocks:calendarBlocks(blocks)},draft.period_id),
  publish:(draft:CalendarDraft,source:string,confirmed:boolean)=>{if(!source.trim()||source.length>255||confirmed!==true)throw new Error('Calendar confirmation required');return request('calendar-drafts/'+decimalId(draft.id)+'/publish',v=>{const result=calendarRevision(v);if(result.period_id!==draft.period_id||result.source!==source)throw new Error('Invalid publication acknowledgement');return result;},{expected_version:draft.version,source,confirmed},draft.period_id);}
 };
}
export type CalendarClient=ReturnType<typeof createCalendarClient>;
