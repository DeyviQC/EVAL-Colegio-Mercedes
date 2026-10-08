import {chromium} from '../../frontend/node_modules/playwright-core/index.mjs';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
import assert from 'node:assert/strict';
import {certificateOptions} from './browser-options.mjs';
const chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);
const passwords=JSON.parse(Buffer.concat(chunks).toString()),root=process.env.EVAL_DEV_ROOT,origin='https://127.0.0.1:8443';
const browser=await chromium.launch({...certificateOptions(readFileSync(resolve(root,'cert.pem'))),headless:true,executablePath:chromium.executablePath()});
const context=await browser.newContext({viewport:{width:1024,height:768}}),page=await context.newPage();
page.setDefaultTimeout(10000);
let checks=0;
const today=new Intl.DateTimeFormat('en-CA',{timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
try{
  await page.goto(origin);await page.getByLabel('Usuario',{exact:true}).fill('director');await page.getByLabel('Contraseña',{exact:true}).fill(passwords.director_admin);await page.getByRole('button',{name:'Ingresar',exact:true}).click();
  await page.getByRole('button',{name:'Estudiantes por aula',exact:true}).click();
  const create=page.getByRole('form',{name:'Crear matrícula'});
  await page.waitForFunction(()=>!Array.from(document.querySelectorAll('button')).find(button=>button.textContent==='Crear matrícula')?.disabled);
  const fixtureName='Estudiante de verificación de matrículas';
  await create.getByLabel('Estudiante',{exact:true}).selectOption({label:fixtureName});
  await create.getByLabel('Período',{exact:true}).selectOption({label:'EVAL desarrollo 2026'});
  await create.getByLabel('Grado',{exact:true}).selectOption({label:'2.º'});await create.getByLabel('Sección',{exact:true}).selectOption({label:'B'});
  await create.getByLabel('Inicio',{exact:true}).fill(today);await create.getByRole('button',{name:'Crear matrícula',exact:true}).click();
  await page.getByText('Operación confirmada. Matrículas actualizadas.',{exact:true}).waitFor();checks++;
  let current=page.getByRole('article').filter({hasText:fixtureName}).filter({hasText:'Estado: Activa'});await current.waitFor();checks++;
  // Create only a named test destination through the real authenticated catalog API.
  const destination=await page.evaluate(async()=>{
    const headers={Accept:'application/json'};const grades=await (await fetch('/academic/catalog/grade',{headers})).json();const grade=grades.data.items.find(row=>row.name==='2.º');
    const sections=await (await fetch('/academic/catalog/section',{headers})).json();let section=sections.data.items.find(row=>row.name==='Verificación matrículas'&&row.grade_id===grade.id);
    if(!section){const session=await (await fetch('/auth/session',{headers})).json();const result=await fetch('/academic/catalog/section',{method:'POST',headers:{...headers,'Content-Type':'application/json','X-CSRF-TOKEN':session.csrf_token},body:JSON.stringify({name:'Verificación matrículas',grade_id:grade.id})});if(!result.ok)throw new Error('Destination creation failed');section=(await result.json()).data;}
    return section.id;
  });assert.match(destination,/^[1-9][0-9]*$/);
  await page.getByRole('button',{name:'Actualizar matrículas',exact:true}).click();
  await page.waitForFunction(()=>!Array.from(document.querySelectorAll('button')).find(button=>button.textContent==='Crear matrícula')?.disabled);
  current=page.getByRole('article').filter({hasText:fixtureName}).filter({hasText:'Estado: Activa'});
  await current.getByRole('button',{name:'Trasladar',exact:true}).click();
  let confirm=page.getByRole('form',{name:'Confirmar cambio de matrícula'});
  await confirm.getByRole('button',{name:'Cancelar',exact:true}).click();assert.equal(await page.getByRole('form',{name:'Confirmar cambio de matrícula'}).count(),0);checks++;
  await current.getByRole('button',{name:'Trasladar',exact:true}).click();confirm=page.getByRole('form',{name:'Confirmar cambio de matrícula'});
  await confirm.getByLabel('Sección de destino',{exact:true}).selectOption({label:'Verificación matrículas'});await confirm.getByRole('button',{name:'Confirmar traslado',exact:true}).click();
  await page.getByText('Operación confirmada. Matrículas actualizadas.',{exact:true}).waitFor();
  await page.getByRole('article').filter({hasText:fixtureName}).filter({hasText:'Estado: Trasladada'}).last().waitFor();checks++;
  current=page.getByRole('article').filter({hasText:fixtureName}).filter({hasText:'Estado: Activa'});await current.waitFor();
  await current.getByRole('button',{name:'Cerrar matrícula',exact:true}).click();confirm=page.getByRole('form',{name:'Confirmar cambio de matrícula'});
  await confirm.getByLabel('Fecha de cierre',{exact:true}).fill(today);await confirm.getByRole('button',{name:'Confirmar cierre',exact:true}).click();
  await page.getByText('Operación confirmada. Matrículas actualizadas.',{exact:true}).waitFor();await page.getByRole('article').filter({hasText:fixtureName}).filter({hasText:'Estado: Cerrada'}).last().waitFor();checks++;
  const retained=page.getByRole('article').filter({hasText:'Estudiante de desarrollo'}).filter({hasText:'Estado: Activa'});assert.equal(await retained.count(),1);checks++;
  await page.setViewportSize({width:768,height:1024});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);checks++;
  await page.screenshot({path:resolve(root,'director-matriculas.png'),fullPage:true});
  writeFileSync(resolve(root,'enrollment-verification.json'),JSON.stringify({checks,created:true,transferred:true,closed:true,originalStudentRetained:true,verifiedAt:new Date().toISOString()},null,2));
  process.stdout.write(JSON.stringify({checks,created:true,transferred:true,closed:true,originalStudentRetained:true})+'\n');
}catch(error){
  await page.screenshot({path:resolve(root,'enrollment-failure.png'),fullPage:true});
  const diagnostic=await page.evaluate(async()=>{
    const result={dom:{busy:document.querySelector('[aria-label="Gestión de matrículas"]')?.getAttribute('aria-busy'),disabled:document.querySelector('form[aria-label="Crear matrícula"] fieldset')?.disabled,options:Array.from(document.querySelector('select[name="student_id"]')?.options??[]).map(option=>option.text)}};for(const path of ['/academic/enrollments','/academic/enrollment-students','/academic/periods','/academic/catalog/grade','/academic/catalog/section']){
      const response=await fetch(path);const wire=await response.json();result[path]={status:response.status,error:wire.error??null,items:wire.data?.items?.length??null};
    }return result;
  });writeFileSync(resolve(root,'enrollment-failure.json'),JSON.stringify(diagnostic,null,2));throw error;
}finally{await context.close();await browser.close();}
