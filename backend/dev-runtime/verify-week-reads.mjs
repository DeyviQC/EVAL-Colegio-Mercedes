// Bounded local read-load smoke: no academic writes, no fixture publication.
import {chromium} from '../../frontend/node_modules/playwright-core/index.mjs';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import assert from 'node:assert/strict';
import {certificateOptions} from './browser-options.mjs';
const root=process.env.EVAL_DEV_ROOT;if(!root)throw Error('Owned runtime required');
const chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);const passwords=JSON.parse(Buffer.concat(chunks).toString());
const origin='https://127.0.0.1:8443';
const browser=await chromium.launch({...certificateOptions(readFileSync(resolve(root,'cert.pem'))),headless:true,executablePath:chromium.executablePath()});
async function login(name,password){const context=await browser.newContext();const page=await context.newPage();await page.goto(origin);await page.getByLabel('Usuario',{exact:true}).fill(name);await page.getByLabel('Contraseña',{exact:true}).fill(password);await page.getByRole('button',{name:'Ingresar',exact:true}).click();await page.getByRole('heading',{name:'Tu espacio académico',exact:true}).waitFor();return page;}
async function read(page,path){return page.evaluate(async path=>{const started=performance.now();const response=await fetch(path,{cache:'no-store',credentials:'same-origin',redirect:'error',signal:AbortSignal.timeout(20000)});return {status:response.status,body:await response.json(),elapsedMs:Math.round(performance.now()-started)};},path);}
try{
 const teacher=await login('docente',passwords.teacher),student=await login('estudiante',passwords.student);
 const directory=await read(teacher,'/academic/my-courses');assert.equal(directory.status,200);const course=directory.body.data.items.find(c=>c.can_publish);assert(course);
 const base='/academic/my-courses/'+course.id+'/weeks';const paths=[base,base+'/material?bucket=unassigned',base+'/activity?bucket=unassigned'];
 const baselines=[];for(const page of [teacher,student]){const baseline=[];for(const path of paths){const response=await read(page,path);assert.equal(response.status,200);baseline.push(JSON.stringify(response.body));}baselines.push(baseline);}
 const started=performance.now(),timings=[];let responses=0;
 // Two independently authenticated sessions, three parallel reads each, four bounded batches.
 for(let batch=0;batch<4;batch++){
  const jobs=[];for(const [index,page] of [teacher,student].entries())for(const [endpoint,path] of paths.entries())jobs.push((async()=>{const response=await read(page,path);assert.equal(response.status,200);assert.equal(JSON.stringify(response.body),baselines[index][endpoint]);assert(Number.isFinite(response.elapsedMs));timings.push(response.elapsedMs);responses++;})());
  const results=await Promise.allSettled(jobs);for(const result of results)if(result.status==='rejected')throw result.reason;
 }
 timings.sort((a,b)=>a-b);
 console.log(JSON.stringify({requests:responses,sessions:2,maxConcurrentRequests:6,statusFailures:0,changedResponses:0,academicWrites:0,elapsedMs:Math.round(performance.now()-started),minMs:timings[0],medianMs:timings[Math.floor(timings.length/2)],p95Ms:timings[Math.ceil(timings.length*.95)-1],maxMs:timings.at(-1),scope:'loopback development read-load smoke; not institutional capacity certification'}));
}finally{await browser.close();}
