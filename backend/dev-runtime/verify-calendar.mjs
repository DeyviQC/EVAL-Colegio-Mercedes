// Real local-session verifier. Run only after explicit calendar maintenance approval.
import {chromium} from '../../frontend/node_modules/playwright-core/index.mjs';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import assert from 'node:assert/strict';
import {certificateOptions} from './browser-options.mjs';
const root=process.env.EVAL_DEV_ROOT;if(!root)throw Error('Owned runtime required');
const chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);
const passwords=JSON.parse(Buffer.concat(chunks).toString());
const origin='https://127.0.0.1:8443';let checks=0;
const browser=await chromium.launch({...certificateOptions(readFileSync(resolve(root,'cert.pem'))),headless:true,executablePath:chromium.executablePath()});
async function login(name,password){const context=await browser.newContext();const page=await context.newPage();await page.goto(origin);await page.getByLabel('Usuario',{exact:true}).fill(name);await page.getByLabel('Contraseña',{exact:true}).fill(password);await page.getByRole('button',{name:'Ingresar',exact:true}).click();await page.getByRole('heading',{name:'Tu espacio académico',exact:true}).waitFor();return page;}
async function api(page,path,body){return page.evaluate(async({path,body})=>{const session=await(await fetch('/auth/session')).json();const response=await fetch(path,{method:body===undefined?'GET':'POST',headers:body===undefined?{}:{'Content-Type':'application/json','X-CSRF-TOKEN':session.csrf_token},...(body===undefined?{}:{body:JSON.stringify(body)})});return {status:response.status,body:await response.json()};},{path,body});}
try{
 const director=await login('director',passwords.director_admin);
 // A new planned fixture period avoids publishing over any existing calendar.
 const fixture=await api(director,'/academic/periods',{name:'Calendar verification '+Date.now(),start_on:'2026-01-01',end_on:'2026-12-31'});assert.equal(fixture.status,201);checks++;
 const period=fixture.body.data.id;
 await director.getByRole('button',{name:'Calendario institucional',exact:true}).click();await director.getByLabel('Período académico',{exact:true}).selectOption(period);await director.getByText('No hay un calendario publicado para este período.',{exact:true}).waitFor();
 await director.getByRole('button',{name:'Usar referencia nacional 2026',exact:true}).click();await director.getByRole('button',{name:'Guardar borrador',exact:true}).click();await director.getByText('Borrador guardado. Todavía no está publicado.',{exact:true}).waitFor();checks++;
 await director.getByLabel('Referencia del calendario aprobado',{exact:true}).fill('Synthetic verification only; not school PAT');await director.getByLabel('Confirmo que el borrador guardado corresponde al calendario aprobado del colegio',{exact:true}).check();await director.getByRole('button',{name:'Publicar calendario',exact:true}).click();await director.getByText('Calendario publicado. Las revisiones anteriores se conservan.',{exact:true}).waitFor();
 const published=await api(director,'/academic/periods/'+period+'/calendar');assert.equal(published.status,200);assert.equal(published.body.data.blocks.reduce((n,b)=>n+b.weeks.length,0),36);checks++;
 const student=await login('estudiante',passwords.student);const denied=await api(student,'/academic/periods/'+period+'/calendar');assert.equal(denied.status,403);checks++;
 const unchanged=await api(director,'/academic/periods/'+period);assert.equal(unchanged.body.data.state,'planned');checks++;
 console.log(JSON.stringify({checks,fixturePeriod:period,fixtureState:'planned',calendarSource:'synthetic verification only',existingCalendarsChanged:false}));
}finally{await browser.close();}
