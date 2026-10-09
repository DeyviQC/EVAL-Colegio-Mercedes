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
async function enter(page,match){await page.getByRole('button',{name:'Biblioteca',exact:true}).click();const back=page.getByRole('button',{name:'Volver a mis cursos',exact:true});if(await back.count())await back.click();for(let i=0;i<30;i++){await page.waitForFunction(()=>document.querySelector('section[aria-label="Biblioteca"]')?.getAttribute('aria-busy')==='false');const card=page.getByRole('article').filter({has:page.getByRole('heading',{name:match})});if(await card.count()){await card.first().getByRole('button',{name:'Consultar biblioteca del curso',exact:true}).click();await page.getByLabel('Biblioteca del curso',{exact:true}).getByRole('status').filter({hasText:/resultados en esta/}).waitFor();return;}const more=page.getByRole('button',{name:'Cargar m\u00e1s cursos',exact:true});if(!await more.count())break;await more.click();}throw Error('Library course not found');}
async function search(page,term){await page.getByLabel('Buscar materiales',{exact:true}).fill(term);await page.getByRole('button',{name:'Buscar',exact:true}).click();await page.waitForFunction(()=>document.querySelector('section[aria-label="Biblioteca del curso"]')?.getAttribute('aria-busy')==='false');}
try{
 const teacher=await login('teacher','docente');sessions.push(teacher.context);const student=await login('student','estudiante');sessions.push(student.context);const director=await login('director_admin','director');sessions.push(director.context);const vice=await login('vice_principal','subdirector');sessions.push(vice.context);
 for(const role of [director,vice]){assert.equal(await role.page.getByRole('button',{name:'Biblioteca',exact:true}).count(),0);checks++;}
 await enter(teacher.page,/^Library verification /);let panel=teacher.page.getByLabel('Biblioteca del curso',{exact:true});assert.equal(await panel.getByRole('article').count(),50);checks++;await teacher.page.getByRole('button',{name:'P\u00e1gina siguiente de materiales',exact:true}).click();await teacher.page.getByRole('article',{name:'Beyond first page',exact:true}).waitFor();checks++;
 await search(teacher.page,'needle');assert.equal(await panel.getByRole('article').count(),1);await panel.getByRole('article',{name:'Beyond first page',exact:true}).waitFor();assert.equal(await teacher.page.getByRole('button',{name:'P\u00e1gina siguiente de materiales',exact:true}).count(),0);checks++;
 await search(teacher.page,'%');assert.equal(await panel.getByRole('article').count(),1);await panel.getByRole('article',{name:'Literal 100%_! reference',exact:true}).waitFor();checks++;
 await search(teacher.page,'file-token.pdf');assert.equal(await panel.getByRole('article').count(),1);checks++;
 await search(teacher.page,'no-match-token');await teacher.page.getByText('No hay materiales que coincidan con esta b\u00fasqueda.',{exact:true}).waitFor();checks++;
 await enter(student.page,'Matem\u00e1tica');panel=student.page.getByLabel('Biblioteca del curso',{exact:true});await search(student.page,'bienvenida');const material=panel.getByRole('article',{name:'Bienvenida a EVAL',exact:true}).first();await material.waitFor();assert.equal(await student.page.getByRole('button',{name:'Publicar o consultar materiales',exact:true}).count(),0);checks++;
 const download=student.page.waitForEvent('download');await material.getByRole('link',{name:'Descargar',exact:true}).click();assert.deepEqual(readFileSync(await(await download).path()),pdf);checks++;
 const failure=route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({error:'material_unavailable',correlation_id:'00000000000000000000000000000000',automatic_retry:false})});await student.page.route('**/academic/my-courses/*/library*',failure);await search(student.page,'bienvenida');await panel.getByRole('alert').waitFor();assert.equal(await panel.getByRole('article').count(),0);assert.equal(await student.page.getByText('No hay materiales que coincidan con esta b\u00fasqueda.',{exact:true}).count(),0);checks++;await student.page.unroute('**/academic/my-courses/*/library*',failure);await search(student.page,'bienvenida');await material.waitFor();checks++;
 await enter(teacher.page,'Matem\u00e1tica');await teacher.page.getByRole('button',{name:'Publicar o consultar materiales',exact:true}).click();await teacher.page.getByRole('button',{name:'Publicar material',exact:true}).waitFor();checks++;
 await student.page.setViewportSize({width:768,height:1024});assert.equal(await student.page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),true);checks++;await student.page.screenshot({path:resolve(root,'estudiante-biblioteca.png'),fullPage:true});
 writeFileSync(resolve(root,'library-verification.json'),JSON.stringify({checks,search:true,pagination:true,downloads:true,recovery:true,verifiedAt:new Date().toISOString()},null,2));process.stdout.write(JSON.stringify({checks,search:true,pagination:true,downloads:true,recovery:true})+'\n');
}catch(error){writeFileSync(resolve(root,'library-failure.json'),JSON.stringify({checks,error:String(error)},null,2));process.stdout.write('Library browser failed after '+checks+' checks.\n');throw error;}finally{for(const context of sessions)await context.close();await browser.close();}
