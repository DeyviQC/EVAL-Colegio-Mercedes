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
  const teacher=await login('teacher','docente');sessions.push(teacher.context);await course(teacher.page);
  const title='Bienvenida a EVAL';const matches=teacher.page.getByRole('article',{name:title,exact:true});
  if(await matches.count()===0){
    await teacher.page.getByRole('button',{name:'Publicar material',exact:true}).click();
    await teacher.page.getByLabel('Título',{exact:true}).fill(title);await teacher.page.getByLabel('Descripción (opcional)').fill('Archivo persistente para comprobar el recorrido real docente-estudiante.');
    await teacher.page.getByLabel('Archivo',{exact:true}).setInputFiles({name:'bienvenida-eval.pdf',mimeType:'application/pdf',buffer:pdf});
    await teacher.page.getByRole('button',{name:'Publicar',exact:true}).click();await teacher.page.getByText('Material publicado correctamente.',{exact:true}).waitFor();
  }
  const material=matches.first();await material.waitFor();checks++;
  const student=await login('student','estudiante');sessions.push(student.context);await course(student.page);
  assert.equal(await student.page.getByRole('button',{name:'Publicar material',exact:true}).count(),0);checks++;
  const own=student.page.getByRole('article',{name:title,exact:true}).first();await own.waitFor();
  const downloaded=student.page.waitForEvent('download');await own.getByRole('link',{name:'Descargar',exact:true}).click();
  const download=await downloaded;assert.deepEqual(readFileSync(await download.path()),pdf);checks++;
  const teacherDownload=teacher.page.waitForEvent('download');await material.getByRole('link',{name:'Descargar',exact:true}).click();assert.deepEqual(readFileSync(await (await teacherDownload).path()),pdf);checks++;
  const forbidden=await student.page.evaluate(async()=> (await fetch('/academic/periods',{credentials:'same-origin'})).status);assert.equal(forbidden,403);checks++;
  await teacher.page.screenshot({path:resolve(root,'docente-materiales.png'),fullPage:true});
  await student.page.screenshot({path:resolve(root,'estudiante-materiales.png'),fullPage:true});
  writeFileSync(resolve(root,'verification.json'),JSON.stringify({checks,roles:4,material:title,byteIdentical:true,unpinnedCertificateDenied:true,verifiedAt:new Date().toISOString()},null,2));
  process.stdout.write(JSON.stringify({checks,roles:4,byteIdentical:true,unpinnedCertificateDenied:true})+'\n');
}finally{for(const session of sessions)await session.close();await browser.close();for(const role of Object.keys(passwords))passwords[role]='';}
