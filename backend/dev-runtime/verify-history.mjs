import { chromium } from '../../frontend/node_modules/playwright-core/index.mjs';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { request } from 'node:https';
import assert from 'node:assert/strict';
import { certificateOptions } from './browser-options.mjs';
const root=process.env.EVAL_DEV_ROOT;
const chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);
const passwords=JSON.parse(Buffer.concat(chunks).toString());
const pem=readFileSync(resolve(root,'cert.pem')), origin='https://127.0.0.1:8443';
const options=certificateOptions(pem);
await new Promise((done,fail)=>{
  const probe=request(origin,{timeout:5000},response=>{response.resume();fail(new Error('Unpinned certificate unexpectedly trusted.'));});
  probe.on('error',error=>{if(['DEPTH_ZERO_SELF_SIGNED_CERT','SELF_SIGNED_CERT_IN_CHAIN','UNABLE_TO_VERIFY_LEAF_SIGNATURE'].includes(error.code))done();else fail(error);});probe.end();
});
const browser=await chromium.launch({...options,headless:true,executablePath:chromium.executablePath()});
let checks=1;
const pdf=Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n');
async function login(role,login){
  const context=await browser.newContext({ignoreHTTPSErrors:false,viewport:{width:1024,height:768}});const page=await context.newPage();
  await page.goto(origin);await page.getByLabel('Usuario',{exact:true}).fill(login);await page.getByLabel('Contraseña',{exact:true}).fill(passwords[role]);
  await page.getByRole('button',{name:'Ingresar',exact:true}).click();await page.getByRole('heading',{name:'Tu espacio académico'}).waitFor();checks++;
  return {context,page};
}
async function course(page){
  await page.getByRole('button',{name:'Materiales',exact:true}).click();
  await page.getByRole('article',{name:'Matemática · 2.º B',exact:true}).getByRole('button',{name:'Consultar materiales del curso'}).click();
  await page.getByRole('button',{name:'Consultar materiales'}).waitFor();
  await page.waitForFunction(()=>Array.from(document.querySelectorAll('button')).find(button=>button.textContent==='Consultar materiales')?.disabled===false);
}
const sessions=[];
try {
  const director=await login('director_admin','director');sessions.push(director.context);
  await director.page.getByRole('button',{name:'Períodos académicos',exact:true}).click();await director.page.getByRole('article',{name:'EVAL desarrollo 2026',exact:true}).waitFor();checks++;
  const vice=await login('vice_principal','subdirector');sessions.push(vice.context);
  assert.equal(await vice.page.getByRole('button',{name:'Períodos académicos',exact:true}).count(),0);checks++;
  assert.equal(await director.page.getByRole('button',{name:'Materiales',exact:true}).count(),0);checks++;assert.equal(await vice.page.getByRole('button',{name:'Materiales',exact:true}).count(),0);checks++;
  const teacher=await login('teacher','docente');sessions.push(teacher.context);const student=await login('student','estudiante');sessions.push(student.context);
  async function api(page,path){return page.evaluate(async path=>{const r=await fetch(path,{cache:'no-store'});return {status:r.status,body:await r.json()};},path);}
  const snapshotBefore=await api(director.page,'/academic/history?kind=enrollments');assert.equal(snapshotBefore.status,200);assert.equal(snapshotBefore.body.data.items.length,50);assert.ok(snapshotBefore.body.data.next_after);checks++;
  const next=await api(director.page,'/academic/history?kind=enrollments&after='+snapshotBefore.body.data.next_after);assert.equal(next.status,200);assert.ok(next.body.data.items.length>0);assert.equal(snapshotBefore.body.data.items.some(a=>next.body.data.items.some(b=>b.id===a.id)),false);checks++;
  for(const [session,name,kind] of [[director,'Historial','enrollments'],[vice,'Historial','assignments'],[teacher,'Mi historial','assignments'],[student,'Mi historial','enrollments']]){
   await session.page.getByRole('button',{name,exact:true}).click();await session.page.getByLabel('Tipo de historial',{exact:true}).selectOption(kind);await session.page.waitForFunction(()=>document.querySelector('section[aria-label="Historial acad\u00e9mico"]')?.getAttribute('aria-busy')==='false');
   const rows=await api(session.page,'/academic/history?kind='+kind);assert.equal(rows.status,200);assert.ok(rows.body.data.items.length>0);await session.page.getByRole('article',{name:'Registro '+rows.body.data.items[0].id,exact:true}).waitFor();assert.equal(await session.page.getByRole('button',{name:/Crear|Trasladar|Cerrar matr|Reemplazar/}).count(),0);checks++;
   if(session===student){const own=await api(session.page,'/account');assert.ok(rows.body.data.items.every(r=>r.student_id===own.body.data.id));assert.equal(await session.page.getByLabel('Tipo de historial').locator('option').count(),1);checks++;}
   if(session===teacher){const courses=await api(session.page,'/academic/my-courses');const own=await api(session.page,'/account');assert.ok(rows.body.data.items.every(r=>r.teacher_id===own.body.data.id));checks++;}
  }
  assert.equal((await api(vice.page,'/academic/history?kind=enrollments')).status,403);checks++;
  assert.equal((await api(teacher.page,'/academic/history?kind=enrollments')).status,403);checks++;
  assert.equal((await api(student.page,'/academic/history?kind=assignments')).status,403);checks++;
  assert.equal((await api(student.page,'/academic/history?kind=enrollments&student_id=1')).status,422);checks++;
  assert.equal((await api(student.page,'/academic/history?kind=enrollments&after=01')).status,422);checks++;
  await director.page.getByRole('button',{name:'P\u00e1gina siguiente del historial',exact:true}).click();await director.page.getByRole('article',{name:'Registro '+next.body.data.items[0].id,exact:true}).waitFor();checks++;
  const allAssignments=[];let after=null;do{const rows=await api(director.page,'/academic/history?kind=assignments'+(after?'&after='+after:''));allAssignments.push(...rows.body.data.items);after=rows.body.data.next_after;}while(after);assert.ok(allAssignments.some(a=>a.state==='closed'));assert.ok(allAssignments.some(a=>a.replaces_assignment_id!==null));assert.ok(allAssignments.some(a=>a.period_state==='closed'));checks++;
  assert.equal(JSON.stringify((await api(director.page,'/academic/history?kind=enrollments')).body),JSON.stringify(snapshotBefore.body));checks++;
  await student.page.setViewportSize({width:768,height:1024});assert.equal(await student.page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),true);checks++;
  await student.page.screenshot({path:resolve(root,'estudiante-historial.png'),fullPage:true});
  writeFileSync(resolve(root,'history-verification.json'),JSON.stringify({checks,roles:4,scope:true,pagination:true,retainedContext:true,verifiedAt:new Date().toISOString()},null,2));process.stdout.write(JSON.stringify({checks,roles:4,scope:true,pagination:true,retainedContext:true})+'\n');
}finally{for(const session of sessions)await session.close();await browser.close();for(const role of Object.keys(passwords))passwords[role]='';}
