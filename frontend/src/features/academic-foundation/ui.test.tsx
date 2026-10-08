import { renderToStaticMarkup } from 'react-dom/server';
import { SessionShell, SessionPanel } from './SessionShell.js';
import { AcademicReadView } from './AcademicReadView.js';
import { createSessionClient, createSessionController, sessionSnapshot } from './session.js';
import { AcademicApiError } from './api.js';
type Case=readonly [string,()=>void|Promise<void>];
function assert(value:unknown):asserts value { if(!value)throw new Error('UI contract assertion failed'); }
function rejects(fn:()=>unknown) { try{fn();return false;}catch{return true;} }
const markup=(kind:'period'|'enrollment'|'assignment'|'history',value:unknown)=>renderToStaticMarkup(<AcademicReadView kind={kind} value={value}/>);
const periodRow={id:'1',name:'  ÁREA  ',start_on:'2026-01-01',end_on:'2026-12-31',state:'closed'};
const assignmentRow={id:'1',teacher_id:'2',academic_period_id:'3',instructional_entry_id:'6',grade_id:'4',section_id:'5',state:'planned',effective_from:'2026-01-01',effective_until:null,operational_start_key:null,operational_end_key:null,replaces_assignment_id:null};
const enrollmentRow={id:'1',student_id:'2',academic_period_id:'3',grade_id:'4',section_id:'5',state:'transferred',effective_from:'2025-01-01',effective_until:'2026-10-07',operational_start_key:'9007199254740993',operational_end_key:'9007199254740994'};
function harness(wires:readonly {body:unknown;status?:number}[]) {
  const calls:{url:string;init:RequestInit}[]=[];
  const client=createSessionClient((async(url:RequestInfo|URL,init?:RequestInit)=>{
    calls.push({url:String(url),init:init??{}});const next=wires[calls.length-1];if(!next)throw new Error('No fixture response');
    return new Response(JSON.stringify(next.body),{status:next.status??200});
  }) as typeof fetch);
  return {client,calls};
}
const noAction=async()=>{};
export const uiCases:readonly Case[]=[
  ['session shell has accessible credential controls',()=>{const html=renderToStaticMarkup(<SessionShell />).toLowerCase();assert(html.includes('autocomplete="username"')&&html.includes('type="password"')&&html.includes('aria-live="polite"'));}],
  ['validated period preserves visible name and closed state',()=>{const html=markup('period',periodRow);assert(html.includes('  ÁREA  ')&&html.includes('Cerrado'));}],
  ['session client is established',()=>assert(createSessionClient(fetch))],
  ['all read renderers fail closed without partial field leakage',()=>{for(const kind of ['period','enrollment','assignment','history'] as const){const html=markup(kind,{displayName:'PRIVATE'});assert(html.includes('No se pudo validar')&&!html.includes('PRIVATE'));}}],
  ['text labels are escaped without inventing name normalization',()=>{const html=markup('period',{...periodRow,name:'  <script>alert(1)</script> Á  '});assert(!html.includes('<script>')&&html.includes('&lt;script&gt;')&&html.includes(' Á  '));}],
  ['planned assignment is clearly nonoperational, with no displayed ID or key',()=>{const html=markup('assignment',assignmentRow);assert(html.includes('Planificado')&&html.includes('no habilita operaciones')&&!html.includes('teacher_id')&&!html.includes('operational_start_key'));}],
  ['transferred enrollment remains historical without expiry inference',()=>{const html=markup('enrollment',enrollmentRow);assert(html.includes('Trasladado')&&html.includes('2025-01-01')&&!html.includes('9007199254740993'));}],
  ['open enrollment end stays explicit',()=>{const html=markup('enrollment',{...enrollmentRow,state:'active',effective_until:null,operational_end_key:null});assert(html.includes('Sin fin registrado'));}],
  ['private content appears only for confirmed authenticated state',()=>{
    for(const phase of ['checking','anonymous','busy','expired','unavailable','authenticated'] as const){
      const html=renderToStaticMarkup(<SessionPanel view={{phase,message:'Estado'}} onLogin={noAction} onLogout={noAction} onRefresh={noAction}><p>PRIVATE RECORD</p></SessionPanel>);
      assert(html.includes('PRIVATE RECORD')===(phase==='authenticated'));}
  }],
  ['UI contains no role/persona/recipient or academic ID entry controls',()=>{const html=renderToStaticMarkup(<SessionShell />);for(const field of ['role','actor','recipient','persona','academic_period_id'])assert(!html.includes(`name="${field}"`));}],
  ['session shape rejects fabricated roles and nonboolean authentication',()=>{assert(rejects(()=>sessionSnapshot({authenticated:'true',csrf_token:'x'})));assert(rejects(()=>sessionSnapshot({authenticated:true,csrf_token:'x',role:'director_admin'})));}],
  ['session bootstrap and login use relative cookie/CSRF transport',async()=>{
    const {client,calls}=harness([{body:{authenticated:false,csrf_token:'first'}},{body:{authenticated:true,csrf_token:'second'}}]);
    assert(!(await client.refresh()).authenticated);assert((await client.login({login:'fixture',password:'fixture-secret'})).authenticated);
    assert(calls[0]?.url==='/auth/session'&&calls[0].init.credentials==='same-origin'&&calls[0].init.cache==='no-store');
    assert(calls[1]?.url==='/auth/login'&&(calls[1].init.headers as Record<string,string>)['X-CSRF-TOKEN']==='first');
    assert(calls[1].init.body===JSON.stringify({login:'fixture',password:'fixture-secret'}));assert(client.csrfToken()==='second');
  }],
  ['login cannot send authority fields or run before CSRF bootstrap',async()=>{
    const {client,calls}=harness([]);assert(rejects(()=>client.login({login:'x',password:'y',...{role:'director_admin'}})));
    try{await client.login({login:'x',password:'y'});throw new Error('Unexpected login');}catch(error){assert(error instanceof AcademicApiError&&error.status===419);}assert(calls.length===0);
  }],
  ['logout rotates token and confirms anonymous state only after response',async()=>{
    const {client,calls}=harness([{body:{authenticated:true,csrf_token:'first'}},{body:{authenticated:false,csrf_token:'next'}}]);
    await client.refresh();assert(!(await client.logout()).authenticated);assert(client.csrfToken()==='next');assert(calls[1]?.url==='/auth/logout'&&calls[1].init.body==='{}');
  }],
  ['credential failure clears CSRF and requires explicit verification',async()=>{
    const {client,calls}=harness([{body:{authenticated:false,csrf_token:'first'}},{body:{error:'invalid_credentials'},status:401}]);const controller=createSessionController(client);
    await controller.refresh();await controller.login({login:'fixture',password:'wrong'});assert(controller.snapshot().phase==='expired');assert(client.csrfToken()===null);assert(calls.length===2);
  }],
  ['busy session operations do not issue duplicate requests',async()=>{
    let resolve!:(response:Response)=>void;let calls=0;
    const client=createSessionClient((()=>{calls++;return new Promise<Response>(done=>{resolve=done;});}) as typeof fetch);
    const controller=createSessionController(client);const pending=controller.refresh();assert(controller.snapshot().phase==='busy');await controller.refresh();assert(calls===1);
    resolve(new Response(JSON.stringify({authenticated:false,csrf_token:'first'})));await pending;assert(controller.snapshot().phase==='anonymous');
  }],
  ['late response cannot restore invalidated UI authorization state',async()=>{
    let resolve!:(response:Response)=>void;const controller=createSessionController(createSessionClient((()=>new Promise<Response>(done=>{resolve=done;})) as typeof fetch));
    const pending=controller.refresh();controller.invalidate();resolve(new Response(JSON.stringify({authenticated:true,csrf_token:'first'})));await pending;assert(controller.snapshot().phase==='expired');
  }],
  ['lost login acknowledgement never replays or claims authentication',async()=>{
    const {client,calls}=harness([{body:{authenticated:false,csrf_token:'first'}}]);const controller=createSessionController(client);await controller.refresh();
    await controller.login({login:'fixture',password:'fixture-secret'});assert(controller.snapshot().phase==='unavailable');assert(calls.length===2);assert(client.csrfToken()===null);
  }],
  ['unsafe malformed acknowledgement hides all authenticated content',async()=>{
    const {client}=harness([{body:{authenticated:true,csrf_token:'first'}},{body:{authenticated:false,csrf_token:'next',debug:'PRIVATE'}}]);const controller=createSessionController(client);
    await controller.refresh();await controller.logout();assert(controller.snapshot().phase==='unavailable');
  }],
  ['renderer historical graph retains original teacher and enrollment labels',()=>{
    const label=(id:string,displayName:string)=>({id,displayName});const interval={effectiveFrom:'2026-01-01',effectiveUntil:null,operationalStartKey:'1',operationalEndKeyExclusive:null};
    const academicPeriod=label('1','Original period'),grade=label('2','Original grade'),section=label('3','Original section');
    const value={submission:{id:'1',acceptedAt:'2026-10-07 10:00:00'},activityReference:{id:'2'},acceptedUnderEnrollment:{id:'3',academicPeriod,grade,section,...interval},
      originalTeachingAssignment:{id:'4',academicPeriod,grade,section,teacher:label('5','Original teacher'),instructionalEntry:{...label('6','Original area'),kind:'area'},...interval}};
    const html=markup('history',value);assert(html.includes('Original teacher')&&html.includes('Original grade')&&html.includes('contexto original'));
    assert(!html.includes('operationalStartKey')&&!html.includes('grade_id'));
  }]
];