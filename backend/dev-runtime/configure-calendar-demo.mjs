// Explicitly approved local demonstration calendar; never replace an existing publication.
import {chromium} from '../../frontend/node_modules/playwright-core/index.mjs';
import {nationalCalendar2026} from '../../frontend/.test-build/features/academic-foundation/calendars.js';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import assert from 'node:assert/strict';
import {certificateOptions} from './browser-options.mjs';
const root=process.env.EVAL_DEV_ROOT;if(!root)throw Error('Owned runtime required');const chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);const passwords=JSON.parse(Buffer.concat(chunks).toString());
const browser=await chromium.launch({...certificateOptions(readFileSync(resolve(root,'cert.pem'))),headless:true,executablePath:chromium.executablePath()});
async function login(name,password){const context=await browser.newContext();const page=await context.newPage();await page.goto('https://127.0.0.1:8443');await page.getByLabel('Usuario',{exact:true}).fill(name);await page.getByLabel('Contraseña',{exact:true}).fill(password);await page.getByRole('button',{name:'Ingresar',exact:true}).click();await page.getByRole('heading',{name:'Tu espacio académico',exact:true}).waitFor();return page;}
async function api(page,path,body){return page.evaluate(async({path,body})=>{const session=await(await fetch('/auth/session')).json();const response=await fetch(path,{method:body===undefined?'GET':'POST',cache:'no-store',headers:body===undefined?{}:{'Content-Type':'application/json','X-CSRF-TOKEN':session.csrf_token},...(body===undefined?{}:{body:JSON.stringify(body)})});return {status:response.status,body:await response.json()};},{path,body});}
try{
 const director=await login('director',passwords.director_admin);let after=null,period=null;
 do{const result=await api(director,'/academic/periods'+(after?'?after='+after:''));assert.equal(result.status,200);period=result.body.data.items.find(p=>p.name==='EVAL desarrollo 2026'&&p.state==='active')??period;after=result.body.data.next_after;}while(after);
 assert(period,'Expected named local development period');assert.equal(period.start_on,'2026-01-01');assert.equal(period.end_on,'2026-12-31');
 const before=await api(director,'/academic/periods/'+period.id+'/calendar');assert.equal(before.status,200);let created=false;
 if(before.body.data===null){
  const draft=await api(director,'/academic/periods/'+period.id+'/calendar-drafts',{blocks:nationalCalendar2026()});assert.equal(draft.status,201);
  // Confirmation is the human's acceptance for this named local demonstration, not a school PAT assertion.
  const published=await api(director,'/academic/calendar-drafts/'+draft.body.data.id+'/publish',{expected_version:draft.body.data.version,source:'Demostración local EVAL: referencia MINEDU 2026 aprobada por el usuario; PAT institucional pendiente',confirmed:true});assert.equal(published.status,200);created=true;
 }
 const student=await login('estudiante',passwords.student);let cursor=null,course=null;
 do{const result=await api(student,'/academic/my-courses'+(cursor?'?after='+cursor:''));assert.equal(result.status,200);course=result.body.data.items.find(c=>c.period==='EVAL desarrollo 2026')??course;cursor=result.body.data.next_after;}while(cursor&&!course);
 assert(course);const result=await api(student,'/academic/my-courses/'+course.id+'/weeks');assert.equal(result.status,200);assert(result.body.data.current);const weeks=result.body.data.current.blocks.flatMap(b=>b.weeks);assert.equal(weeks.length,36);
 await student.getByRole('button',{name:'Calendario y semanas',exact:true}).click();await student.getByRole('article',{name:course.subject+' · '+course.grade+' '+course.section,exact:true}).getByRole('button',{name:'Entrar al curso',exact:true}).click();await student.getByLabel('Ir a la semana',{exact:true}).selectOption(weeks[0].id);await student.getByRole('heading',{name:'Semana 1',exact:true}).waitFor();assert.equal(await student.getByText('El calendario institucional todavía no está configurado.',{exact:false}).count(),0);
 console.log(JSON.stringify({period:period.id,calendarCreated:created,replacedExistingCalendar:false,studentCourse:course.id,teachingWeeks:weeks.length,firstWeekVisible:true,source:result.body.data.current.source}));
}finally{await browser.close();}
