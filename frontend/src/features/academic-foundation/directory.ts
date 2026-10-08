import { exact, decimalId, period, text } from './contracts.js';
export type CatalogKind='entry'|'grade'|'section';
export type CatalogRow={id:string;name:string;is_active:boolean;kind?:'subject'|'area';grade_id?:string};
export function catalogRow(value:unknown,kind:CatalogKind):CatalogRow{
  const row=exact(value,['id','name','is_active',...(kind==='entry'?['kind']:kind==='section'?['grade_id']:[])]);
  if(typeof row.is_active!=='boolean')throw new Error('Invalid active flag');
  const result:CatalogRow={id:decimalId(row.id),name:text(row.name),is_active:row.is_active};
  if(kind==='entry'){if(row.kind!=='subject'&&row.kind!=='area')throw new Error('Invalid kind');result.kind=row.kind;}
  if(kind==='section')result.grade_id=decimalId(row.grade_id);return result;
}
export function directoryPage<T>(value:unknown,decode:(value:unknown)=>T){
  const row=exact(value,['items','next_after']);if(!Array.isArray(row.items)||row.items.length>50)throw new Error('Invalid page');
  const items=row.items.map(decode);const next_after=row.next_after===null?null:decimalId(row.next_after);
  let prior='0';for(const item of row.items){const id=decimalId((item as {id:unknown}).id);if(BigInt(id)<=BigInt(prior))throw new Error('Unordered page');prior=id;}
  if(next_after!==null&&(items.length!==50||next_after!==prior))throw new Error('Invalid cursor');return {items,next_after};
}
export const periodPage=(value:unknown)=>directoryPage(value,period);
export const catalogPage=(kind:CatalogKind)=>(value:unknown)=>directoryPage(value,item=>catalogRow(item,kind));
