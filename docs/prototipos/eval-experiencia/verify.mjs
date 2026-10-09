import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';
const root=fileURLToPath(new URL('.',import.meta.url));
const variant=process.argv[2]||'index.html';
assert(['index.html','azul-menta.html'].includes(variant));
const evidence=join(tmpdir(),variant==='index.html'?'eval-visual-preview-evidence':'eval-azul-menta-preview-evidence');await mkdir(evidence,{recursive:true});
const cache=join(process.env.LOCALAPPDATA,'Temp/opencode/eval-u12-browser/chromium-1248/chrome-win64/chrome.exe');
const browser=await chromium.launch({executablePath:cache});let checks=0,network=0;
const check=value=>{assert(value);checks++;};
try{
 const page=await browser.newPage({viewport:{width:1366,height:900}});
 page.on('request',request=>{if(/^https?:/.test(request.url()))network++;});
 await page.goto(new URL(variant,import.meta.url).href);
 check(await page.getByText('PREVIEW VISUAL · Datos sintéticos',{exact:true}).isVisible());
 for(const [role,title] of [['director','El colegio, en un solo espacio'],['subdirector','Organizamos el aprendizaje'],['docente','Hola, Lucía'],['estudiante','Hola, Ana']]){
  await page.getByLabel('Explorar como').selectOption(role);check(await page.getByRole('heading',{name:title,exact:true}).isVisible());
  await page.screenshot({path:join(evidence,role+'-inicio.png'),fullPage:true});
  if(role==='docente'||role==='estudiante'){
   await page.getByRole('button',{name:'Mis cursos',exact:true}).click();await page.getByRole('button',{name:'Entrar al curso →',exact:true}).first().click();
   check(await page.getByRole('heading',{name:'Matemática',exact:true}).isVisible());
   if(variant==='azul-menta.html'){
    check(await page.getByRole('tab',{name:'Semanas',exact:true}).getAttribute('aria-selected')==='true');
    check(await page.getByRole('heading',{name:'Guía de fracciones',exact:true}).isVisible());
    check(await page.getByRole('heading',{name:'Identificamos partes de un todo',exact:true}).isVisible());
    await page.locator('summary').filter({hasText:'Semana 2'}).click();
    check(await page.getByRole('heading',{name:'Resolvemos situaciones cotidianas',exact:true}).isVisible());
    await page.locator('summary').filter({hasText:'Sin semana'}).click();
    check(await page.getByRole('heading',{name:'Material de apoyo del curso',exact:true}).isVisible());
    await page.screenshot({path:join(evidence,role+'-semanas.png'),fullPage:true});
    for(const [block,first,last,start,end] of [['0','Semana 1','Semana 9','16 de marzo','15 de mayo'],['1','Semana 10','Semana 18','25 de mayo','24 de julio'],['2','Semana 19','Semana 27','10 de agosto','9 de octubre'],['3','Semana 28','Semana 36','19 de octubre','18 de diciembre']]){
     await page.getByLabel('Bloque lectivo',{exact:true}).selectOption(block);
     const summaries=page.locator('summary');check(await summaries.count()===10);
     check((await summaries.first().innerText()).includes(first+'\n')&&(await summaries.first().innerText()).includes(start));
     check((await summaries.nth(8).innerText()).includes(last+'\n')&&(await summaries.nth(8).innerText()).includes(end));
    }
    await page.getByLabel('Bloque lectivo',{exact:true}).selectOption('0');
    await page.getByLabel('Mostrar solo semanas con contenido',{exact:true}).check();
    check(await page.locator('summary').count()===3);
    await page.getByLabel('Bloque lectivo',{exact:true}).selectOption('3');
    check(await page.locator('summary').count()===1);
    check(await page.getByText('Este bloque no tiene contenido de demostración.',{exact:false}).isVisible());
    await page.getByLabel('Ir a una semana',{exact:true}).selectOption('27');
    check(await page.getByLabel('Bloque lectivo',{exact:true}).inputValue()==='2');
    check(await page.locator('[data-week="Semana 27"]').evaluate(node=>node.open&&node.querySelector('summary')===document.activeElement));
    check(await page.getByLabel('Mostrar solo semanas con contenido',{exact:true}).isChecked()===false);
    await page.getByRole('tab',{name:'Materiales',exact:true}).click();
   }
   check(await page.getByRole('button',{name:'Descarga no disponible'}).isDisabled());
   await page.screenshot({path:join(evidence,role+'-curso.png'),fullPage:true});
   await page.getByRole('tab',{name:'Actividades',exact:true}).click();check(await page.getByRole('heading',{name:'Exploramos fracciones'}).isVisible());
   await page.getByRole('tab',{name:role==='estudiante'?'Mis entregas':'Entregas',exact:true}).click();check(await page.getByText(/No hay entregas operativas/).isVisible());
  }
  if(role==='director'){await page.getByRole('button',{name:'Aulas y cursos',exact:true}).click();check(await page.getByRole('heading',{name:'2.º B',exact:true}).isVisible());await page.getByRole('button',{name:'Estudiantes por aula',exact:true}).click();check(await page.getByText('Ana Flores',{exact:true}).isVisible());await page.getByRole('button',{name:'Administración',exact:true}).click();check(await page.getByRole('heading',{name:'Períodos',exact:true}).isVisible());await page.screenshot({path:join(evidence,'director-administracion.png'),fullPage:true});}
  if(role==='subdirector'){check(await page.getByRole('button',{name:'Estudiantes por aula',exact:true}).count()===0);await page.getByRole('button',{name:'Supervisión · futura',exact:true}).click();await page.getByRole('button',{name:'Ver propuesta'}).click();check(await page.getByRole('button',{name:'Enviar observación no disponible'}).isDisabled());await page.screenshot({path:join(evidence,'subdirector-supervision.png'),fullPage:true});}
 }
 if(variant==='azul-menta.html'){
  await page.getByLabel('Explorar como').selectOption('director');
  await page.getByRole('navigation',{name:'Navegación principal'}).getByRole('button',{name:'Administración',exact:true}).click();
  await page.getByRole('button',{name:'Revisar calendario · demostración',exact:true}).click();
  check(await page.getByRole('heading',{name:'Calendario institucional',exact:true}).isVisible());
  await page.getByLabel('Referencia del calendario aprobado',{exact:true}).fill('PAT ficticio 2026');
  await page.getByLabel('Confirmo la revisión del calendario · simulación',{exact:true}).check();
  await page.getByRole('button',{name:'Validar borrador · demostración',exact:true}).click();
  check(await page.getByText('Borrador válido en la demostración.',{exact:false}).isVisible());
  await page.getByLabel('Inicio del bloque 2',{exact:true}).fill('2026-03-12');
  await page.getByRole('button',{name:'Validar borrador · demostración',exact:true}).click();
  check(await page.getByText('Revisa las fechas:',{exact:false}).isVisible());
  await page.getByRole('button',{name:'Restablecer referencia nacional',exact:true}).click();
  check(await page.getByLabel('Inicio del bloque 2',{exact:true}).inputValue()==='2026-03-16');
  for(const width of [1024,768,390]){await page.setViewportSize({width,height:900});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:join(evidence,'calendario-'+width+'.png'),fullPage:true});}
 }
 for(const width of [1024,768,390]){await page.setViewportSize({width,height:900});await page.getByLabel('Explorar como').selectOption('estudiante');check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:join(evidence,'estudiante-'+width+'.png'),fullPage:true});if(variant==='azul-menta.html'){await page.getByRole('button',{name:'Mis cursos',exact:true}).click();await page.getByRole('button',{name:'Entrar al curso →',exact:true}).first().click();check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:join(evidence,'semanas-'+width+'.png'),fullPage:true});}}
 await page.goto(new URL(variant,import.meta.url).href);await page.keyboard.press('Tab');check(await page.getByRole('link',{name:'Ir al contenido'}).evaluate(node=>node===document.activeElement));
 check(network===0);const source=await readFile(join(root,'app.js'),'utf8');check(!/\bfetch\s*\(|localStorage|XMLHttpRequest/.test(source));
 console.log(JSON.stringify({checks,networkRequests:network,evidence},null,2));
}finally{await browser.close();}
