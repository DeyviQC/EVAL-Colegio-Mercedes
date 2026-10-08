import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';
const root=fileURLToPath(new URL('.',import.meta.url));
const evidence=join(tmpdir(),'eval-visual-preview-evidence');await mkdir(evidence,{recursive:true});
const cache=join(process.env.LOCALAPPDATA,'Temp/opencode/eval-u12-browser/chromium-1248/chrome-win64/chrome.exe');
const browser=await chromium.launch({executablePath:cache});let checks=0,network=0;
const check=value=>{assert(value);checks++;};
try{
 const page=await browser.newPage({viewport:{width:1366,height:900}});
 page.on('request',request=>{if(/^https?:/.test(request.url()))network++;});
 await page.goto(new URL('index.html',import.meta.url).href);
 check(await page.getByText('PREVIEW VISUAL · Datos sintéticos',{exact:true}).isVisible());
 for(const [role,title] of [['director','El colegio, en un solo espacio'],['subdirector','Organizamos el aprendizaje'],['docente','Hola, Lucía'],['estudiante','Hola, Ana']]){
  await page.getByLabel('Explorar como').selectOption(role);check(await page.getByRole('heading',{name:title,exact:true}).isVisible());
  await page.screenshot({path:join(evidence,role+'-inicio.png'),fullPage:true});
  if(role==='docente'||role==='estudiante'){
   await page.getByRole('button',{name:'Mis cursos',exact:true}).click();await page.getByRole('button',{name:'Entrar al curso →',exact:true}).first().click();
   check(await page.getByRole('heading',{name:'Matemática',exact:true}).isVisible());
   check(await page.getByRole('button',{name:'Descarga no disponible'}).isDisabled());
   await page.screenshot({path:join(evidence,role+'-curso.png'),fullPage:true});
   await page.getByRole('tab',{name:'Actividades',exact:true}).click();check(await page.getByRole('heading',{name:'Exploramos fracciones'}).isVisible());
   await page.getByRole('tab',{name:role==='estudiante'?'Mis entregas':'Entregas',exact:true}).click();check(await page.getByText(/No hay entregas operativas/).isVisible());
  }
  if(role==='director'){await page.getByRole('button',{name:'Aulas y cursos',exact:true}).click();check(await page.getByRole('heading',{name:'2.º B',exact:true}).isVisible());await page.getByRole('button',{name:'Estudiantes por aula',exact:true}).click();check(await page.getByText('Ana Flores',{exact:true}).isVisible());await page.getByRole('button',{name:'Administración',exact:true}).click();check(await page.getByRole('heading',{name:'Períodos',exact:true}).isVisible());await page.screenshot({path:join(evidence,'director-administracion.png'),fullPage:true});}
  if(role==='subdirector'){check(await page.getByRole('button',{name:'Estudiantes por aula',exact:true}).count()===0);await page.getByRole('button',{name:'Supervisión · futura',exact:true}).click();await page.getByRole('button',{name:'Ver propuesta'}).click();check(await page.getByRole('button',{name:'Enviar observación no disponible'}).isDisabled());await page.screenshot({path:join(evidence,'subdirector-supervision.png'),fullPage:true});}
 }
 for(const width of [1024,768,390]){await page.setViewportSize({width,height:900});await page.getByLabel('Explorar como').selectOption('estudiante');check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:join(evidence,'estudiante-'+width+'.png'),fullPage:true});}
 await page.goto(new URL('index.html',import.meta.url).href);await page.keyboard.press('Tab');check(await page.getByRole('link',{name:'Ir al contenido'}).evaluate(node=>node===document.activeElement));
 check(network===0);const source=await readFile(join(root,'app.js'),'utf8');check(!/\bfetch\s*\(|localStorage|XMLHttpRequest/.test(source));
 console.log(JSON.stringify({checks,networkRequests:network,evidence},null,2));
}finally{await browser.close();}
