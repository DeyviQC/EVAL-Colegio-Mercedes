import { createAcademicClient, AcademicApiError } from './api.js';
import { catalogPage, periodPage } from './directory.js';
import { renderToStaticMarkup } from 'react-dom/server';
import { createElement } from 'react';
import { FoundationWorkspace } from './FoundationWorkspace.js';
const assert=(value:unknown)=>{if(!value)throw new Error('Directory assertion failed');};
function rejects(fn:()=>unknown){try{fn();}catch{return;}throw new Error('Expected rejection');}
export const directoryCases:[string,()=>void|Promise<void>][]=[
 ['directory decodes empty and retained inactive rows',()=>{assert(periodPage({items:[],next_after:null}).items.length===0);const row=catalogPage('grade')({items:[{id:'1',name:'  ÁREA ',is_active:false}],next_after:null}).items[0]!;assert(row.name==='  ÁREA '&&!row.is_active);}],
 ['directory rejects extra keys numeric IDs and invalid cursors',()=>{for(const wire of [{items:[],next_after:'1'},{items:[],next_after:null,total:1},{items:[{id:1,name:'Grade',is_active:true}],next_after:null}])rejects(()=>catalogPage('grade')(wire));}],
 ['period and catalog write transport uses actual commands and PATCH',async()=>{const calls:{url:string;options:RequestInit}[]=[];const client=createAcademicClient((async(url,options)=>{calls.push({url:String(url),options:options!});return new Response(JSON.stringify({data:{id:'1'}}));}) as typeof fetch,()=> 'csrf');
   await client.createPeriod({name:'  ÁREA ',start_on:'2026-01-01',end_on:'2026-12-31'});await client.transitionPeriod('1','close');await client.updateCatalog('grade','1',{is_active:false});
   assert(calls[0]!.options.body===JSON.stringify({name:'  ÁREA ',start_on:'2026-01-01',end_on:'2026-12-31'}));assert(calls[2]!.options.method==='PATCH');assert((calls[2]!.options.headers as Record<string,string>)['X-CSRF-TOKEN']==='csrf');
   rejects(()=>client.createCatalog('grade',{name:'Grade',role:'director_admin'}));rejects(()=>client.updateCatalog('section','1',{grade_id:'2'} as never));
 }],
 ['uncertain lifecycle response is never replayed',async()=>{let calls=0;const client=createAcademicClient((async()=>{calls++;return new Response(JSON.stringify({error:'commit_outcome_unknown',correlation_id:'abc',automatic_retry:false}),{status:503});}) as typeof fetch,()=> 'csrf');try{await client.transitionPeriod('1','close');throw new Error('Expected failure');}catch(error){assert(error instanceof AcademicApiError&&error.outcomeUnknown);}assert(calls===1);}],
 ['workspace has labeled required fields and no authority inputs',()=>{const client=createAcademicClient(fetch,()=> 'csrf');const html=renderToStaticMarkup(createElement(FoundationWorkspace,{section:'periods',client,onExpired:()=>{}}));assert(html.includes('name="start_on"')&&html.includes('name="end_on"')&&html.includes('required'));assert(!html.includes('name="role"')&&!html.includes('name="actor"'));}],
];
