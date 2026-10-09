import { exact,text,decimalId } from './contracts.js';
import { AcademicApiError } from './api.js';
export type Notice={id:string;event:'activity_published'|'delivery_accepted'|'delivery_updated'|'delivery_assessed'|'assessment_corrected';message:string;created_at:string;read_at:string|null;section:'activities'|'deliveries'|'grades'|null};
export type NotificationFeed={unread:number;latest_id:string|null;items:Notice[];next_before:string|null};
export function notificationFeed(wire:unknown):NotificationFeed{
 const e=exact(wire,['data']),r=exact(e.data,['unread','latest_id','items','next_before']);if(typeof r.unread!=='number'||!Number.isSafeInteger(r.unread)||r.unread<0||!Array.isArray(r.items)||r.items.length>50)throw new Error('Invalid own feed');
 const latest_id=r.latest_id===null?null:decimalId(r.latest_id);let prior:bigint|null=null;
 const items=r.items.map(value=>{const row=exact(value,['id','event','message','created_at','read_at','section']),id=decimalId(row.id);if(!['activity_published','delivery_accepted','delivery_updated','delivery_assessed','assessment_corrected'].includes(String(row.event))||!['activities','deliveries','grades',null].includes(row.section as Notice['section'])||latest_id===null||BigInt(id)>BigInt(latest_id)||prior!==null&&BigInt(id)>=prior)throw new Error('Invalid notice');prior=BigInt(id);const section=row.section as Notice['section'];if(section!==null&&section!==(row.event==='activity_published'?'activities':['delivery_accepted','delivery_updated'].includes(String(row.event))?'deliveries':'grades'))throw new Error('Invalid shortcut');return {id,event:row.event as Notice['event'],message:text(row.message),created_at:text(row.created_at),read_at:row.read_at===null?null:text(row.read_at),section};});
 const next_before=r.next_before===null?null:decimalId(r.next_before);if(next_before!==null&&(items.length!==50||items.at(-1)?.id!==next_before)||items.filter(n=>n.read_at===null).length>r.unread||latest_id===null&&(r.unread!==0||items.length!==0))throw new Error('Invalid feed cursor or count');
 return {unread:r.unread,latest_id,items,next_before};
}
export function createNotificationClient(csrf:()=>string,transport:typeof fetch=fetch){
 let uncertain=false;
 async function request(path:string,body?:{up_to_id:string}){const unsafe=body!==undefined;if(unsafe&&uncertain)throw new AcademicApiError('outcome_unknown',null,null,true);if(unsafe&&!csrf())throw new AcademicApiError('csrf_missing',419);let response:Response;
  try{response=await transport(path,{method:unsafe?'POST':'GET',credentials:'same-origin',cache:'no-store',redirect:'error',headers:{Accept:'application/json',...unsafe?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()}:{}},...unsafe?{body:JSON.stringify(body)}:{}});}catch{if(unsafe)uncertain=true;throw new AcademicApiError('transport_unavailable',null,null,unsafe);}
  let wire:unknown;try{wire=await response.json();}catch{if(unsafe)uncertain=true;throw new AcademicApiError('invalid_response',response.status,null,unsafe);}if(!response.ok){if(unsafe&&response.status>=500)uncertain=true;const error=typeof (wire as {error?:unknown})?.error==='string'?(wire as {error:string}).error:'invalid_response';throw new AcademicApiError(error,response.status,null,unsafe&&response.status>=500);}return wire;
 }
 async function feed(before:string|null=null){before=before===null?null:decimalId(before);const wire=await request('/notifications'+(before?'?before='+decimalId(before):''));try{const result=notificationFeed(wire);if(before!==null&&result.items.some(n=>BigInt(n.id)>=BigInt(before)))throw new Error();return result;}catch{throw new AcademicApiError('invalid_response',200);}}
 async function markRead(id:string){id=decimalId(id);const wire=await request('/notifications/read',{up_to_id:id});try{const e=exact(wire,['data']),r=exact(e.data,['up_to_id']);if(decimalId(r.up_to_id)!==id)throw new Error();return {up_to_id:id};}catch{uncertain=true;throw new AcademicApiError('invalid_response',200,null,true);}}
 return {feed,markRead,isUncertain:()=>uncertain,review:()=>{uncertain=false;}};
}
export type NotificationClient=ReturnType<typeof createNotificationClient>;
