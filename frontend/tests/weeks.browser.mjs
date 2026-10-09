// Built React, synthetic HTTP fixtures, no database or external services.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFile,mkdir} from 'node:fs/promises';
import {resolve,join,extname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {tmpdir} from 'node:os';
import {chromium} from '../node_modules/playwright/index.mjs';
const dist=fileURLToPath(new URL('../dist/',import.meta.url));
const server=createServer(async(req,res)=>{try{const url=new URL(req.url,'http://localhost');const path=resolve(dist,'.'+(url.pathname==='/'?'/index.html':url.pathname));if(!path.startsWith(resolve(dist)+'\\'))throw Error();res.setHeader('Content-Type',extname(path)==='.js'?'text/javascript':extname(path)==='.css'?'text/css':'text/html');res.end(await readFile(path));}catch{res.statusCode=404;res.end();}});
await new Promise(done=>server.listen(0,'127.0.0.1',done));const origin='http://127.0.0.1:'+server.address().port;const evidence=join(tmpdir(),'eval-weeks-react-evidence');await mkdir(evidence,{recursive:true});
let browser,checks=0,external=0,writes=0,lost=false,denied=false,missingCalendar=false;
const check=value=>{assert(value);checks++;};
const course={id:'2',subject:'Matemática ficticia',kind:'subject',grade:'2',section:'B',period:'Ejemplo 2026',period_state:'active',state:'active',teacher:'Docente ficticio',can_publish:true};
const week='a'.repeat(32),oldWeek='b'.repeat(32);
let association={kind:'material',resource_id:'1',assignment_id:'2',period_id:'3',version:0,calendar_revision_id:null,week_id:null,earlier_calendar:false};
const taskAssociation={...association,kind:'activity',resource_id:'2',version:1,calendar_revision_id:'4',week_id:week};
const material={id:'1',assignment_id:'2',author_id:'1',author_name:'Docente ficticio',title:'Material de prueba',description:null,filename:'sample.pdf',mime:'application/pdf',bytes:50,published_at:'2026-10-08 12:00:00'};
const task={id:'2',assignment_id:'2',title:'Tarea de prueba',instructions:'Actividad ficticia',due_on:null,state:'open',can_close:false,can_submit:false};
const revision=(id,w)=>({id,period_id:'3',source:'Calendario ficticio '+id,recorded_at:'2026-10-08 12:00:00',blocks:[{kind:'teaching',start_on:'2026-03-16',end_on:'2026-03-20',weeks:[{number:1,start_on:'2026-03-16',end_on:'2026-03-20',id:w}]}]});
try{
 browser=await chromium.launch({executablePath:join(process.env.LOCALAPPDATA,'Temp/opencode/eval-u12-browser/chromium-1248/chrome-win64/chrome.exe')});const page=await browser.newPage({viewport:{width:1366,height:900}});
 page.on('request',request=>{if(!request.url().startsWith(origin))external++;});
 await page.route(origin+'/**',async route=>{const request=route.request(),url=new URL(request.url()),path=url.pathname;const respond=data=>route.fulfill({contentType:'application/json',body:JSON.stringify({data})});
  if(path==='/auth/session')return route.fulfill({contentType:'application/json',body:JSON.stringify({authenticated:true,csrf_token:'synthetic-weeks-token'})});
  if(path==='/account')return respond({id:'1',name:'Docente ficticio',username:'docente-demo',can_manage:false});
  if(path==='/academic/navigation')return respond({sections:['my_assignments']});
  if(path==='/academic/my-courses')return respond({items:[course],next_after:null});
  if(path==='/academic/my-courses/2')return respond(course);
  if(path==='/academic/my-courses/2/weeks')return respond({course_id:'2',period_id:'3',current:missingCalendar?null:revision('4',week),retained:[revision('5',oldWeek)]});
  if(path.startsWith('/academic/my-courses/2/weeks/')){if(denied)return route.fulfill({status:403,contentType:'application/json',body:JSON.stringify({error:'forbidden'})});const kind=path.split('/').at(-1),bucket=url.searchParams.get('bucket'),a=kind==='material'?association:taskAssociation,r=kind==='material'?material:task;const match=bucket==='unassigned'?a.week_id===null:bucket==='earlier'?a.earlier_calendar:a.week_id===url.searchParams.get('week')&&a.calendar_revision_id===url.searchParams.get('revision');const items=match&&(!url.searchParams.get('term')||r.title.includes(url.searchParams.get('term')))?[{resource:r,association:a}]:[];return respond({course_id:'2',kind,bucket,total:items.length,items,next_after:null});}
  if(path==='/academic/resource-weeks/material/1'){if(request.method()==='POST'){writes++;const body=request.postDataJSON();check(body.expected_version===association.version&&request.headers()['x-csrf-token']==='synthetic-weeks-token');association={...association,version:association.version+1,calendar_revision_id:body.calendar_revision_id,week_id:body.week_id,earlier_calendar:false};if(lost){lost=false;return route.abort();}}return respond(association);}
  if(path==='/education/activities/2')return respond(task);
  if(path==='/education/activities/2/deliveries')return respond({items:[],next_after:null});
  return route.continue();
 });
 await page.goto(origin);await page.getByRole('button',{name:'Calendario y semanas',exact:true}).waitFor();check(await page.getByRole('button',{name:'Calendario y semanas',exact:true}).isVisible());await page.getByRole('button',{name:'Calendario y semanas',exact:true}).click();await page.getByRole('button',{name:'Entrar al curso',exact:true}).click();await page.getByText('1 materiales · 0 tareas',{exact:true}).waitFor();check(await page.getByRole('button',{name:'Semanas',exact:true}).getAttribute('aria-pressed')==='true');check(await page.getByRole('heading',{name:'Material de prueba',exact:true}).isVisible());
 await page.getByRole('button',{name:'Organizar por semana',exact:true}).first().click();await page.getByLabel('Semana para este recurso',{exact:true}).selectOption(week);await page.getByRole('button',{name:'Guardar semana',exact:true}).click();await page.getByText('No hay contenido para esta selección.',{exact:true}).waitFor();check(writes===1);
 await page.getByLabel('Ir a la semana',{exact:true}).selectOption(week);await page.getByText('1 materiales · 1 tareas',{exact:true}).waitFor();check(await page.getByRole('heading',{name:'Semana 1',exact:true}).evaluate(el=>el===document.activeElement));
 await page.getByRole('button',{name:'Abrir actividad',exact:true}).click();await page.getByRole('button',{name:'Cerrar detalle de actividad',exact:true}).waitFor();check(await page.getByRole('article',{name:'Tarea de prueba',exact:true}).isVisible());await page.getByRole('button',{name:'Cerrar detalle de actividad',exact:true}).click();
 await page.getByRole('button',{name:'Organizar por semana',exact:true}).first().click();await page.getByLabel('Semana para este recurso',{exact:true}).selectOption('');lost=true;await page.getByRole('button',{name:'Guardar semana',exact:true}).click();await page.getByRole('button',{name:'Consultar resultado de la semana',exact:true}).waitFor();check(await page.getByRole('button',{name:'Guardar semana',exact:true}).isDisabled());
 await page.getByRole('button',{name:'Consultar resultado de la semana',exact:true}).click();await page.getByText('Resultado consultado: versión 2 · Sin semana.',{exact:true}).waitFor();await page.getByRole('button',{name:'He revisado el resultado; continuar',exact:true}).click();await page.getByText('0 materiales · 1 tareas',{exact:true}).waitFor();check(writes===2);
 association={...association,version:3,calendar_revision_id:'5',week_id:oldWeek,earlier_calendar:true};await page.getByRole('button',{name:'Calendarios anteriores',exact:true}).click();await page.getByText('1 materiales · 0 tareas',{exact:true}).waitFor();check(await page.getByText('Material · Calendario anterior',{exact:true}).isVisible());
 await page.getByLabel('Filtrar contenido',{exact:true}).fill('ausente');await page.getByRole('button',{name:'Aplicar filtro',exact:true}).click();await page.getByText('No hay contenido para esta selección.',{exact:true}).waitFor();check(await page.getByRole('heading',{name:'Calendarios anteriores',exact:true}).isVisible());
 await page.getByRole('button',{name:'Calendarios anteriores',exact:true}).click();await page.getByRole('heading',{name:'Material de prueba',exact:true}).waitFor();
 for(const width of [1024,768,390]){await page.setViewportSize({width,height:900});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.screenshot({path:join(evidence,'weeks-'+width+'.png'),fullPage:true});}
 missingCalendar=true;await page.getByRole('button',{name:'Sin semana',exact:true}).click();await page.getByText('Ver calendario de referencia 2026',{exact:true}).click();check(await page.locator('.eval-calendar-reference li').count()===36);check(await page.getByLabel('Ir a la semana',{exact:true}).count()===0);
 denied=true;await page.getByRole('button',{name:'Consultar nuevamente',exact:true}).click();await page.getByText('No se pudo consultar o guardar la organización. Consulta nuevamente.',{exact:true}).waitFor();check(await page.getByRole('heading',{name:'Material de prueba',exact:true}).count()===0&&await page.getByLabel('Ir a la semana',{exact:true}).count()===0);check(external===0);console.log(JSON.stringify({checks,writes,externalRequests:external,evidence}));
}finally{await browser?.close();await new Promise(done=>server.close(done));}
