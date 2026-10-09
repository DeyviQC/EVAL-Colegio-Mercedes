// Built React UI with synthetic HTTP fixtures; never contacts the app database.
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import {readFile,mkdir} from 'node:fs/promises';
import {resolve,join,extname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {tmpdir} from 'node:os';
import {chromium} from '../node_modules/playwright/index.mjs';
const dist=fileURLToPath(new URL('../dist/',import.meta.url));
const server=createServer(async(req,res)=>{try{const path=resolve(dist,'.'+(new URL(req.url,'http://localhost').pathname==='/'?'/index.html':new URL(req.url,'http://localhost').pathname));if(!path.startsWith(resolve(dist)+ '\\'))throw new Error();const body=await readFile(path);res.setHeader('Content-Type',extname(path)==='.js'?'text/javascript':extname(path)==='.css'?'text/css':'text/html');res.end(body);}catch{res.statusCode=404;res.end();}});
await new Promise(done=>server.listen(0,'127.0.0.1',done));const origin='http://127.0.0.1:'+server.address().port;
const evidence=join(tmpdir(),'eval-calendar-react-evidence');await mkdir(evidence,{recursive:true});
let browser;let checks=0,external=0,writes=0;
const check=value=>{assert(value);checks++;};
try{
 browser=await chromium.launch({executablePath:join(process.env.LOCALAPPDATA,'Temp/opencode/eval-u12-browser/chromium-1248/chrome-win64/chrome.exe')});
 const page=await browser.newPage({viewport:{width:1366,height:900}});let draft=null,current=null,lost=false;
 page.on('request',request=>{if(!request.url().startsWith(origin))external++;});
 await page.route(origin+'/**',async route=>{const request=route.request(),url=new URL(request.url()),path=url.pathname;const respond=data=>route.fulfill({contentType:'application/json',body:JSON.stringify({data})});
  if(path==='/auth/session')return route.fulfill({contentType:'application/json',body:JSON.stringify({authenticated:true,csrf_token:'synthetic-calendar-token'})});
  if(path==='/account')return respond({id:'1',name:'Dirección ficticia',username:'director-demo',can_manage:true});
  if(path==='/academic/navigation')return respond({sections:['periods','catalog','enrollments','assignments']});
  if(path==='/academic/periods')return respond({items:[{id:'2',name:'Año 2026 · ejemplo',start_on:'2026-01-01',end_on:'2026-12-31',state:'active'},{id:'3',name:'Período cerrado · ejemplo',start_on:'2025-01-01',end_on:'2025-12-31',state:'closed'}],next_after:null});
  if(path.endsWith('/calendar')&&request.method()==='GET')return respond(path.includes('/2/')?current:null);
  if(path.endsWith('/calendar-drafts')&&request.method()==='GET')return respond({items:path.includes('/2/')&&draft?[draft]:[],next_after:null});
  if(request.method()==='POST'&&path.includes('calendar-drafts')){
   writes++;check(request.headers()['x-csrf-token']==='synthetic-calendar-token');const body=request.postDataJSON();
   if(path.endsWith('/calendar-drafts')){draft={id:'4',period_id:'2',base_revision_id:current?.id??null,version:1,state:'draft',blocks:body.blocks};return respond(draft);}
   if(path.endsWith('/edit')){draft={...draft,version:draft.version+1,blocks:body.blocks};if(lost){lost=false;return route.abort();}return respond(draft);}
   if(path.endsWith('/publish')){check(body.confirmed===true&&body.source==='PAT ficticio 2026');current={id:'5',period_id:'2',source:body.source,recorded_at:'2026-10-08 12:00:00.000000',blocks:draft.blocks.map(b=>({...b,weeks:b.weeks.map(w=>({...w,id:w.number.toString(16).padStart(32,'0')}))}))};draft={...draft,state:'published',version:draft.version+1};return respond(current);}
  }
  return route.continue();
 });
 await page.goto(origin);await page.getByRole('button',{name:'Calendario institucional',exact:true}).click();await page.getByLabel('Período académico',{exact:true}).selectOption('2');
 await page.getByText('No hay un calendario publicado para este período.',{exact:true}).waitFor();check(await page.getByText('No hay un calendario publicado para este período.',{exact:true}).isVisible());
 await page.getByRole('button',{name:'Usar referencia nacional 2026',exact:true}).click();
 await page.getByRole('button',{name:'Guardar borrador',exact:true}).click();await page.getByText('Borrador guardado. Todavía no está publicado.',{exact:true}).waitFor();check(writes===1&&draft.blocks.length===9);
 await page.getByLabel('Inicio del bloque',{exact:true}).first().fill('2026-03-01');lost=true;
 await page.getByRole('button',{name:'Guardar borrador',exact:true}).click();await page.getByText('No se confirmó el guardado.',{exact:false}).waitFor();
 check(await page.getByRole('button',{name:'Guardar borrador',exact:true}).isDisabled());check(await page.getByRole('button',{name:'He revisado el resultado; continuar',exact:true}).isDisabled());
 await page.getByRole('button',{name:'Consultar calendario y borradores',exact:true}).click();await page.getByText('Consulta completada.',{exact:false}).waitFor();
 await page.getByRole('button',{name:'He revisado el resultado; continuar',exact:true}).click();await page.getByRole('button',{name:'Borrador 1 · Por revisar · versión 2',exact:true}).click();
 check(await page.getByLabel('Inicio del bloque',{exact:true}).first().inputValue()==='2026-03-01');check(writes===2);
 await page.getByLabel('Referencia del calendario aprobado',{exact:true}).fill('PAT ficticio 2026');await page.getByLabel('Confirmo que el borrador guardado corresponde al calendario aprobado del colegio',{exact:true}).check();await page.getByRole('button',{name:'Publicar calendario',exact:true}).click();await page.getByText('Calendario publicado. Las revisiones anteriores se conservan.',{exact:true}).waitFor();check(writes===3);
 check(await page.getByText('9 bloques · 36 semanas lectivas',{exact:true}).isVisible());
 for(const width of [1024,768,390]){await page.setViewportSize({width,height:900});await page.getByRole('button',{name:'Borrador 1 · Publicado · versión 3',exact:true}).click();check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));check(await page.getByRole('button',{name:'Guardar borrador',exact:true}).isDisabled());await page.screenshot({path:join(evidence,'calendar-'+width+'.png')});}
 await page.getByLabel('Período académico',{exact:true}).selectOption('3');await page.getByText('Período cerrado: solo consulta.',{exact:true}).waitFor();check(await page.getByRole('button',{name:'Guardar borrador',exact:true}).isDisabled());check(writes===3&&external===0);
 console.log(JSON.stringify({checks,writes,externalRequests:external,evidence,scope:'built React with synthetic HTTP fixtures; no real session or database'},null,2));
}finally{if(browser)await browser.close();await new Promise(done=>server.close(done));}
