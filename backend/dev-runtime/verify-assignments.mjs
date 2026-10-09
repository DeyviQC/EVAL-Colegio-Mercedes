import {chromium} from '../../frontend/node_modules/playwright-core/index.mjs';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import assert from 'node:assert/strict';
import {certificateOptions} from './browser-options.mjs';
const chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);
const passwords=JSON.parse(Buffer.concat(chunks).toString()),root=process.env.EVAL_DEV_ROOT,origin='https://127.0.0.1:8443';
const browser=await chromium.launch({...certificateOptions(readFileSync(resolve(root,'cert.pem'))),headless:true,executablePath:chromium.executablePath()});
const sessions=[];let checks=0;let currentPage;
const today=new Intl.DateTimeFormat('en-CA',{timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
async function login(role,login){
  const context=await browser.newContext({viewport:{width:1024,height:768}});sessions.push(context);const page=await context.newPage();page.setDefaultTimeout(12000);currentPage=page;
  await page.goto(origin);await page.getByLabel('Usuario',{exact:true}).fill(login);await page.getByLabel('Contraseña',{exact:true}).fill(passwords[role]);await page.getByRole('button',{name:'Ingresar',exact:true}).click();await page.getByRole('heading',{name:'Tu espacio académico'}).waitFor();return page;
}
async function ready(page){await page.waitForFunction(()=>document.querySelector('[aria-label="Gestión de asignaciones"]')?.getAttribute('aria-busy')==='false',{},{timeout:12000});}
try{
  const director=await login('director_admin','director');
  await director.evaluate(async()=>{
    const entries=await (await fetch('/academic/catalog/entry')).json();if(entries.data.items.some(row=>row.name==='Organización de verificación'))return;
    const session=await (await fetch('/auth/session')).json();const response=await fetch('/academic/catalog/entry',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':session.csrf_token},body:JSON.stringify({name:'Organización de verificación',kind:'subject'})});if(!response.ok)throw new Error('Verification subject creation failed.');
  });
  // Close only retained records owned by this verification fixture after an interrupted run.
  await director.evaluate(async today=>{
    const session=await (await fetch('/auth/session')).json();let after=null;
    do{const response=await fetch('/academic/assignment-workspace?kind=assignments'+(after?'&after='+after:''));const page=await response.json();if(!response.ok)throw new Error('Fixture recovery lookup failed');for(const row of page.data.items){if(row.state!=='active'||row.entry_name!=='Organización de verificación'||!['Docente de verificación de organización','Docente sucesor de verificación'].includes(row.teacher_name)||row.grade_name!=='2.º'||row.section_name!=='B')continue;const close=await fetch('/academic/assignments/'+row.id+'/close',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':session.csrf_token},body:JSON.stringify({effective_until:today})});if(!close.ok)throw new Error('Fixture recovery close failed');}after=page.data.next_after;}while(after);
  },today);
  await director.getByRole('button',{name:'Organización docente',exact:true}).click();await ready(director);
  const form=director.getByRole('form',{name:'Planificar asignación'});
  await form.getByLabel('Docente',{exact:true}).selectOption({label:'Docente de verificación de organización'});
  await form.getByLabel('Período',{exact:true}).selectOption({label:'EVAL desarrollo 2026 (activo)'});
  await form.getByLabel('Materia o área',{exact:true}).selectOption({label:'Organización de verificación (materia)'});
  await form.getByLabel('Grado',{exact:true}).selectOption({label:'2.º'});await form.getByLabel('Sección',{exact:true}).selectOption({label:'B'});
  await form.getByLabel('Inicio',{exact:true}).fill(today);await form.getByLabel('Fin (opcional)',{exact:true}).fill('2026-12-31');
  await form.getByRole('button',{name:'Planificar asignación',exact:true}).click();await director.getByText('Operación confirmada. Asignaciones actualizadas.',{exact:true}).waitFor();checks++;
  let planned=director.getByRole('article').filter({hasText:'Docente de verificación de organización'}).filter({hasText:'Estado: Planificada'});
  await planned.getByRole('button',{name:'Activar asignación',exact:true}).click();await director.getByRole('form',{name:'Confirmar cambio de asignación'}).getByRole('button',{name:'Cancelar',exact:true}).click();await planned.waitFor();checks++;
  await planned.getByRole('button',{name:'Activar asignación',exact:true}).click();await director.getByRole('button',{name:'Confirmar activación',exact:true}).click();await director.getByText('Operación confirmada. Asignaciones actualizadas.',{exact:true}).waitFor();
  await director.getByRole('article').filter({hasText:'Docente de verificación de organización'}).filter({hasText:'Estado: Activa'}).waitFor();checks++;
  const vice=await login('vice_principal','subdirector');await vice.getByRole('button',{name:'Organización docente',exact:true}).click();await ready(vice);checks++;
  const denied=await vice.evaluate(async()=>Promise.all(['/academic/periods','/academic/enrollments','/academic/catalog/grade'].map(async path=>(await fetch(path)).status)));assert.deepEqual(denied,[403,403,403]);checks++;
  const active=vice.getByRole('article').filter({hasText:'Docente de verificación de organización'}).filter({hasText:'Estado: Activa'});
  await active.getByRole('button',{name:'Reemplazar docente',exact:true}).click();let confirm=vice.getByRole('form',{name:'Confirmar cambio de asignación'});
  await confirm.getByLabel('Nuevo docente',{exact:true}).selectOption({label:'Docente sucesor de verificación'});await confirm.getByRole('button',{name:'Confirmar reemplazo',exact:true}).click();await vice.getByText('Operación confirmada. Asignaciones actualizadas.',{exact:true}).waitFor();
  await vice.getByRole('article').filter({hasText:'Docente de verificación de organización'}).filter({hasText:'Estado: Cerrada'}).last().waitFor();checks++;
  const successor=vice.getByRole('article').filter({has:vice.getByRole('heading',{name:'Docente sucesor de verificación',exact:true})}).filter({hasText:'Estado: Activa'});await successor.waitFor();assert.match(await successor.innerText(),/Asignación de reemplazo/);checks++;
  await successor.getByRole('button',{name:'Cerrar asignación',exact:true}).click();confirm=vice.getByRole('form',{name:'Confirmar cambio de asignación'});await confirm.getByLabel('Fecha de cierre',{exact:true}).fill(today);await confirm.getByRole('button',{name:'Confirmar cierre',exact:true}).click();await vice.getByText('Operación confirmada. Asignaciones actualizadas.',{exact:true}).waitFor();
  await vice.getByRole('article').filter({hasText:'Docente sucesor de verificación'}).filter({hasText:'Estado: Cerrada'}).last().waitFor();checks++;
  assert.equal(await vice.getByRole('article').filter({hasText:'Docente de desarrollo'}).filter({hasText:'Matemática'}).filter({hasText:'Estado: Activa'}).count(),1);checks++;
  for(const [role,name] of [['teacher','docente'],['student','estudiante']]){const page=await login(role,name);assert.equal(await page.evaluate(async()=>(await fetch('/academic/assignment-workspace?kind=assignments')).status),403);checks++;}
  await vice.setViewportSize({width:768,height:1024});assert.equal(await vice.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);checks++;
  await vice.screenshot({path:resolve(root,'subdirector-organizacion.png'),fullPage:true});writeFileSync(resolve(root,'assignment-verification.json'),JSON.stringify({checks,planned:true,activated:true,replaced:true,closed:true,mainAssignmentRetained:true,verifiedAt:new Date().toISOString()},null,2));
  process.stdout.write(JSON.stringify({checks,planned:true,activated:true,replaced:true,closed:true,mainAssignmentRetained:true})+'\n');
}catch(error){if(currentPage)await currentPage.screenshot({path:resolve(root,'assignment-failure.png'),fullPage:true});throw error;}
finally{for(const context of sessions)await context.close();await browser.close();}
