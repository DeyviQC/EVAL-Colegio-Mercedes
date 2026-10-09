import { chromium } from '../../frontend/node_modules/playwright-core/index.mjs';
import { readFileSync,writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';
import { certificateOptions } from './browser-options.mjs';
const root=process.env.EVAL_DEV_ROOT,chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);
const passwords=JSON.parse(Buffer.concat(chunks).toString());
const browser=await chromium.launch({...certificateOptions(readFileSync(resolve(root,'cert.pem'))),headless:true,executablePath:chromium.executablePath()});
const contexts=[];let checks=0;
async function login(username,password){const context=await browser.newContext({viewport:{width:768,height:1024},ignoreHTTPSErrors:false});contexts.push(context);const page=await context.newPage();page.setDefaultTimeout(7000);await page.goto('https://127.0.0.1:8443');await page.getByLabel('Usuario',{exact:true}).fill(username);await page.getByLabel('Contraseña',{exact:true}).fill(password);await page.getByRole('button',{name:'Ingresar',exact:true}).click();await page.getByRole('heading',{name:'Tu espacio académico'}).waitFor();return page;}
async function post(page,path,input){return page.evaluate(async({path,input})=>{const session=await (await fetch('/auth/session')).json();const response=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':session.csrf_token},body:JSON.stringify(input)});const result=await response.json();if(!response.ok)throw new Error('Account journey command failed: '+path+' '+result.error);return result.data;},{path,input});}
async function materials(page,title){await page.getByRole('button',{name:'Mis cursos',exact:true}).click();await page.getByRole('article').filter({has:page.getByRole('heading',{name:title,exact:true})}).getByRole('button',{name:'Entrar al curso',exact:true}).click();await page.getByRole('button',{name:'Ver materiales',exact:true}).click();await page.waitForFunction(()=>Array.from(document.querySelectorAll('button')).find(button=>button.textContent==='Consultar materiales')?.disabled===false);}
try{
 const director=await login('director',passwords.director_admin);
 await director.getByRole('button',{name:'Usuarios',exact:true}).click();checks++;
 const username='verificacion.'+Date.now();
 await director.getByLabel('Nombre',{exact:true}).fill('Estudiante de verificación de cuentas');await director.getByLabel('Nombre de usuario',{exact:true}).fill(username);await director.getByLabel('Tipo de cuenta',{exact:true}).selectOption('student');
 await director.getByRole('button',{name:'Crear cuenta',exact:true}).click();
 const initial=await director.getByLabel('Contraseña generada',{exact:true}).inputValue();assert.equal(initial.length,20);checks++;
 const student=await login(username,initial);assert.equal(await student.getByRole('button',{name:'Usuarios',exact:true}).count(),0);checks++;
 const denied=await student.evaluate(async()=>{const response=await fetch('/users');return response.status;});assert.equal(denied,403);checks++;
 await student.getByRole('button',{name:'Mi cuenta',exact:true}).click();await student.getByLabel('Contraseña actual',{exact:true}).fill(initial);await student.getByLabel('Nueva contraseña',{exact:true}).fill('Verificacion-local-2026-'+Date.now());
 const replacement=await student.getByLabel('Nueva contraseña',{exact:true}).inputValue();await student.getByLabel('Confirmar nueva contraseña',{exact:true}).fill(replacement);await student.getByRole('button',{name:'Cambiar contraseña',exact:true}).click();await student.getByRole('button',{name:'Ingresar',exact:true}).waitFor();checks++;
 const renewed=await login(username,replacement);await renewed.getByRole('button',{name:'Mi cuenta',exact:true}).click();checks++;
 const studentAccount=await director.evaluate(async username=>(await (await fetch('/users?username='+encodeURIComponent(username))).json()).data.items[0],username);
 const suffix=Date.now(),courseTitle='Verificación cuentas '+suffix,teacherUsername='docente.cuentas.'+suffix;
 const teacherAccount=await post(director,'/users',{name:'Docente creado desde EVAL',username:teacherUsername,role:'teacher'});
 const period=await director.evaluate(async()=>(await (await fetch('/academic/periods')).json()).data.items.find(row=>row.state==='active'));
 const grade=await post(director,'/academic/catalog/grade',{name:'Verificación cuentas '+suffix});const section=await post(director,'/academic/catalog/section',{name:'A',grade_id:grade.id});const entry=await post(director,'/academic/catalog/entry',{name:courseTitle,kind:'subject'});
 const today=new Intl.DateTimeFormat('en-CA',{timeZone:'America/Lima'}).format(new Date());
 const assignment=await post(director,'/academic/assignments',{teacher_id:teacherAccount.id,academic_period_id:period.id,instructional_entry_id:entry.id,grade_id:grade.id,section_id:section.id,effective_from:today,effective_until:'2026-12-31'});await post(director,'/academic/assignments/'+assignment.id+'/activate',{});
 await post(director,'/academic/enrollments',{student_id:studentAccount.id,academic_period_id:period.id,grade_id:grade.id,section_id:section.id,effective_from:today});checks++;
 const teacherPage=await login(teacherUsername,teacherAccount.password);await materials(teacherPage,courseTitle);await teacherPage.getByRole('button',{name:'Publicar material',exact:true}).click();await teacherPage.getByLabel('Título',{exact:true}).fill('Material de cuentas reales');
 const pdf=Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n');
 await teacherPage.getByLabel('Archivo',{exact:true}).setInputFiles({name:'cuentas-eval.pdf',mimeType:'application/pdf',buffer:pdf});await teacherPage.getByRole('button',{name:'Publicar',exact:true}).click();await teacherPage.getByText('Material publicado correctamente.',{exact:true}).waitFor();checks++;
 await materials(renewed,courseTitle);const downloadPromise=renewed.waitForEvent('download');await renewed.getByRole('article',{name:'Material de cuentas reales',exact:true}).getByRole('link',{name:'Descargar',exact:true}).click();assert.deepEqual(readFileSync(await (await downloadPromise).path()),pdf);checks++;
 await director.getByRole('button',{name:'Ocultar contraseña',exact:true}).click();await director.getByLabel('Buscar usuario',{exact:true}).fill(username);await director.getByRole('button',{name:'Buscar',exact:true}).click();
 let row=director.getByRole('article').filter({hasText:username});await row.waitFor();await row.getByRole('button',{name:'Restablecer contraseña',exact:true}).click();await director.getByRole('button',{name:'Confirmar restablecimiento',exact:true}).click();const reset=await director.getByLabel('Contraseña generada',{exact:true}).inputValue();assert.equal(reset!==initial,true);checks++;
 assert.equal(await renewed.evaluate(async()=>(await fetch('/account')).status),401);checks++;
 const resetSession=await login(username,reset),dormant=await login(username,reset);await director.getByRole('button',{name:'Ocultar contraseña',exact:true}).click();row=director.getByRole('article').filter({hasText:username});await row.getByRole('button',{name:'Desactivar',exact:true}).click();await director.getByRole('button',{name:'Confirmar desactivación',exact:true}).click();await director.getByText('Operación confirmada.',{exact:true}).waitFor();assert.equal(await resetSession.evaluate(async()=>(await fetch('/account')).status),401);checks++;
 row=director.getByRole('article').filter({hasText:username});await row.getByRole('button',{name:'Reactivar',exact:true}).click();await director.getByRole('button',{name:'Confirmar reactivación',exact:true}).click();await director.getByText('Operación confirmada.',{exact:true}).waitFor();checks++;
 assert.equal(await dormant.evaluate(async()=>(await fetch('/account')).status),401);checks++;
 assert.equal(await director.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),true);checks++;
 await director.screenshot({path:resolve(root,'director-usuarios.png'),fullPage:true});writeFileSync(resolve(root,'user-verification.json'),JSON.stringify({checks,created:true,passwordChanged:true,reset:true,deactivated:true,reactivated:true,verifiedAt:new Date().toISOString()},null,2));process.stdout.write(JSON.stringify({checks,created:true,passwordChanged:true,revocation:true})+'\n');
}finally{for(const context of contexts)await context.close();await browser.close();}
