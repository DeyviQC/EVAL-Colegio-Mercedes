// Read-only local session verification. Credentials arrive through stdin and are never logged.
import assert from 'node:assert/strict';
import {readFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
import {chromium} from '../node_modules/playwright-core/index.mjs';
import {certificateOptions} from '../../backend/dev-runtime/browser-options.mjs';
const root=resolve('.local/eval-dev'),origin='https://127.0.0.1:8443';
const chunks=[];for await(const chunk of process.stdin)chunks.push(chunk);
const passwords=JSON.parse(Buffer.concat(chunks).toString());
const evidence=resolve(root,'institutional-evidence');mkdirSync(evidence,{recursive:true});
const browser=await chromium.launch({...certificateOptions(readFileSync(resolve(root,'cert.pem'))),executablePath:resolve(process.env.LOCALAPPDATA,'Temp/opencode/eval-u12-browser/chromium-1248/chrome-win64/chrome.exe')});
let checks=0,external=0;const errors=[];
const check=value=>{assert(value);checks++;};
try{
 for(const [role,login,profile] of [['director_admin','director','Director / Administrador'],['vice_principal','subdirector','Subdirector'],['teacher','docente','Docente'],['student','estudiante','Estudiante']]){
  const context=await browser.newContext({viewport:{width:1366,height:900}});const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  await context.route('**/*',route=>{if(new URL(route.request().url()).origin!==origin){external++;return route.abort();}return route.continue();});
  await page.goto(origin);await page.waitForFunction(()=>document.querySelector('img')?.naturalWidth>0);
  check(await page.locator('img').evaluate(img=>img.naturalWidth>0));
  if(role==='director_admin'){await page.screenshot({path:resolve(evidence,'login.png'),fullPage:true});await page.getByLabel('Usuario',{exact:true}).focus();check(await page.getByLabel('Usuario',{exact:true}).evaluate(el=>document.activeElement===el));}
  await page.getByLabel('Usuario',{exact:true}).fill(login);await page.getByLabel('Contraseña',{exact:true}).fill(passwords[role]);await page.getByRole('button',{name:'Ingresar',exact:true}).click();
  await page.getByRole('heading',{name:'Bienvenido a EVAL',exact:true}).waitFor();await page.locator('.eval-profile small').getByText(profile,{exact:true}).waitFor();checks++;
  await page.getByRole('button',{name:'Contraer navegación',exact:true}).click();check(await page.locator('.eval-layout').getAttribute('data-collapsed')==='true');
  check(await page.getByRole('navigation',{name:'Secciones académicas'}).getByRole('button',{name:'Inicio',exact:true}).count()===1);
  await page.getByRole('button',{name:'Expandir navegación',exact:true}).click();
  for(const width of [1366,1024,768,390]){await page.setViewportSize({width,height:900});if(width===390)await page.getByRole('button',{name:/Secciones/}).click();
   check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   check(await page.evaluate(()=>[...document.querySelectorAll('button,input,select,summary')].filter(el=>el.getClientRects().length).every(el=>el.getBoundingClientRect().height>=43.5)));
  }
  await page.setViewportSize({width:1366,height:900});await page.screenshot({path:resolve(evidence,role+'-home.png'),fullPage:true});
  if(role==='director_admin'){
   await page.getByRole('navigation',{name:'Secciones académicas'}).getByRole('button',{name:'Calendario institucional',exact:true}).click();
   await page.waitForFunction(()=>document.querySelector('#calendar-period')?.options.length>1&&!document.querySelector('#calendar-period').disabled);
   const value=await page.locator('#calendar-period option').evaluateAll(options=>options.find(o=>o.value&&o.textContent.includes('Activo'))?.value??options.find(o=>o.value)?.value);
   await page.locator('#calendar-period').selectOption(value);await page.waitForFunction(()=>document.querySelector('section[aria-label="Calendario institucional"]')?.getAttribute('aria-busy')==='false');
   check(await page.locator('.eval-period-summary strong').count()>0);check(await page.locator('.eval-calendar-grid article').count()>0);check(await page.locator('.eval-week-grid li').count()>0);
   await page.screenshot({path:resolve(evidence,'calendar.png'),fullPage:true});
   await page.setViewportSize({width:390,height:844});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:resolve(evidence,'calendar-mobile.png'),fullPage:true});
  }
  await page.getByRole('button',{name:'Cerrar sesión',exact:true}).click();await page.getByRole('heading',{name:'Acceso a EVAL',exact:true}).waitFor();checks++;await context.close();
 }
 check(external===0);assert.deepEqual(errors,[]);checks++;console.log(JSON.stringify({checks,externalRequests:external,pageErrors:errors.length,evidence}));
}finally{await browser.close();}
