import { test, expect } from '@playwright/test';
import { sectionLabels } from '../../src/features/academic-foundation/navigation.js';
function uiUrl():string {
  const url=new URL(process.env.EVAL_UI_URL??'https://invalid.test');
  if(url.protocol!=='https:'||url.hostname!=='127.0.0.1'||!url.port)throw new Error('Approved isolated HTTPS UI/browser trust harness required');
  return url.origin;
}
// Execution uses the explicitly approved disposable loopback/SPKI harness.
test('session shell presents credential fields without authority selectors',async({page,browser})=>{
  expect(browser.version()).toBe('156.0.8078.4');
  await page.goto(uiUrl());
  await expect(page.getByLabel('Usuario',{exact:true})).toBeVisible();
  await expect(page.getByLabel('Contraseña',{exact:true})).toHaveAttribute('type','password');
  await expect(page.locator('[name="role"], [name="actor"], [name="recipient"]')).toHaveCount(0);
});
test('expired session offers verification without exposing retained records',async({page})=>{
  await page.route('**/auth/session',route=>route.fulfill({status:401,contentType:'application/json',body:JSON.stringify({error:'unauthenticated'})}));
  await page.goto(uiUrl());
  await expect(page.getByRole('button',{name:'Verificar sesión'})).toBeVisible();
  await expect(page.getByRole('button',{name:'Ingresar',exact:true})).toBeDisabled();
  await expect(page.getByText('Historial de entrega',{exact:true})).toHaveCount(0);
});
test('session assets load while external requests are blocked',async({page})=>{
  const origin=uiUrl();let external=0;
  await page.route('**/*',route=>{if(new URL(route.request().url()).origin!==origin){external++;return route.abort();}return route.continue();});
  await page.goto(origin);await expect(page.getByRole('heading',{name:'EVAL',exact:true})).toBeVisible();
  expect(external).toBe(0);
});
test('real DOM login and logout use the local session',async({page})=>{
  const login=process.env.EVAL_BROWSER_LOGIN,password=process.env.EVAL_BROWSER_PASSWORD;
  if(!login||!password)throw new Error('Disposable account required');
  await page.goto(uiUrl());
  await expect(page.getByRole('button',{name:'Ingresar',exact:true})).toBeEnabled();
  expect((await page.context().cookies()).some(cookie=>cookie.name==='__Host-eval_session')).toBe(true);
  await page.getByLabel('Usuario',{exact:true}).fill(login);
  await page.getByLabel('Contraseña',{exact:true}).fill(password);
  const response=page.waitForResponse(response=>new URL(response.url()).pathname==='/auth/login');
  await page.getByRole('button',{name:'Ingresar',exact:true}).click();
  const result=await response;
  expect(result.status(),result.ok()?'':JSON.stringify(await result.json())).toBe(200);
  await expect(page.getByRole('heading',{name:'Tu espacio académico',exact:true})).toBeVisible();
  const cookies=await page.context().cookies();
  expect(cookies.some(cookie=>cookie.secure&&cookie.httpOnly&&cookie.sameSite==='Lax')).toBe(true);
  await page.getByRole('button',{name:'Cerrar sesión',exact:true}).click();
  await expect(page.getByLabel('Contraseña',{exact:true})).toHaveValue('');
  await expect(page.getByRole('heading',{name:'Acceso a EVAL',exact:true})).toBeVisible();
});
test('certificate is rejected without a pin and with a different key pin',async({playwright})=>{
  for(const args of [[],['--ignore-certificate-errors-spki-list=AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']]){
    const browser=await playwright.chromium.launch({channel:'chromium',args});
    try{const page=await browser.newPage({ignoreHTTPSErrors:false});
      await expect(page.goto(uiUrl())).rejects.toThrow(/ERR_CERT_AUTHORITY_INVALID/);
    }finally{await browser.close();}
  }
});
test('four persisted roles expose only their server-owned sections',async({browser})=>{
  const accounts=JSON.parse(process.env.EVAL_BROWSER_ACCOUNTS??'[]') as {role:string;login:string;password:string}[];
  expect(accounts).toHaveLength(4);
  const expected:Record<string,string[]>={director_admin:['periods','catalog','enrollments','assignments'],vice_principal:['assignments'],teacher:['my_assignments'],student:['my_enrollments']};
  for(const account of accounts){
    const context=await browser.newContext({ignoreHTTPSErrors:false});
    try{const page=await context.newPage();await page.goto(uiUrl());
      await expect(page.getByRole('button',{name:'Ingresar',exact:true})).toBeEnabled();
      await page.getByLabel('Usuario',{exact:true}).fill(account.login);await page.getByLabel('Contraseña',{exact:true}).fill(account.password);
      await page.getByRole('button',{name:'Ingresar',exact:true}).click();
      const nav=page.getByRole('navigation',{name:'Secciones académicas'});await expect(nav).toBeVisible();
      await expect(nav.getByRole('button')).toHaveText(expected[account.role]!.map(section=>sectionLabels[section as keyof typeof sectionLabels]));
      if(account.role!=='director_admin'){
        const response=await page.evaluate(async(id)=>{const result=await fetch('/academic/enrollments/'+id);return result.status;},process.env.EVAL_BROWSER_DENIED_ENROLLMENT);
        expect(response).toBe(404);
      }
      await nav.getByRole('button').first().click();
      if(account.role==='director_admin')await expect(page.getByRole('region',{name:'Gestión de períodos'})).toBeVisible();
      else await expect(page.getByText('La consulta de registros de esta sección todavía no está disponible.',{exact:true})).toBeVisible();
      await page.getByRole('button',{name:'Cerrar sesión',exact:true}).click();await expect(nav).toHaveCount(0);
    }finally{await context.close();}
  }
});
async function directorLogin(page:import('@playwright/test').Page){
  await page.goto(uiUrl());await expect(page.getByRole('button',{name:'Ingresar',exact:true})).toBeEnabled();
  await page.getByLabel('Usuario',{exact:true}).fill(process.env.EVAL_BROWSER_LOGIN!);await page.getByLabel('Contraseña',{exact:true}).fill(process.env.EVAL_BROWSER_PASSWORD!);
  await page.getByRole('button',{name:'Ingresar',exact:true}).click();await expect(page.getByRole('navigation',{name:'Secciones académicas'})).toBeVisible();
}
test('Director completes real period lifecycle and catalog operations',async({page})=>{
  await directorLogin(page);await page.getByRole('button',{name:'Períodos académicos',exact:true}).click();
  const form=page.getByRole('form',{name:'Crear período'});await expect(form.getByRole('button',{name:'Crear período'})).toBeEnabled();
  const active=page.getByRole('article').filter({hasText:'Estado: Activo'});
  for(let i=0;await active.count()===0&&i<200;i++){await page.getByRole('button',{name:'Cargar más registros',exact:true}).click();await expect(form.getByRole('button',{name:'Crear período'})).toBeEnabled();}
  await active.getByRole('button',{name:'Solicitar cierre'}).click();await page.getByRole('button',{name:'Confirmar cierre',exact:true}).click();await expect(active).toHaveCount(0);
  const name='Período navegador '+Date.now();await form.getByLabel('Nombre',{exact:true}).fill(name);await form.getByLabel('Inicio',{exact:true}).fill('2026-01-01');await form.getByLabel('Fin',{exact:true}).fill('2026-12-31');
  await form.getByRole('button',{name:'Crear período'}).click();const period=page.getByRole('article',{name,exact:true});await expect(period).toBeVisible();await expect(period).toContainText('Planificado');
  // Close the pre-existing active fixture through the real confirmed lifecycle flow.
  await period.getByRole('button',{name:'Solicitar activación'}).click();await page.getByRole('button',{name:'Cancelar',exact:true}).click();await expect(period).toContainText('Planificado');
  await period.getByRole('button',{name:'Solicitar activación'}).click();await page.getByRole('button',{name:'Confirmar activación',exact:true}).click();await expect(period).toContainText('Activo');
  await period.getByRole('button',{name:'Solicitar cierre'}).click();await page.getByRole('button',{name:'Confirmar cierre',exact:true}).click();await expect(period).toContainText('Cerrado');await expect(period.getByRole('button')).toHaveCount(0);
  await page.getByRole('button',{name:'Catálogo académico',exact:true}).click();const catalog=page.getByRole('region',{name:'Gestión de catálogo'});
  const create=catalog.getByRole('form',{name:'Crear registro de catálogo'});const subject='Materia navegador '+Date.now();await expect(create.getByRole('button',{name:'Crear registro',exact:true})).toBeEnabled();await create.getByLabel('Nombre',{exact:true}).fill(subject);await create.getByRole('button',{name:'Crear registro',exact:true}).click();
  const entry=catalog.getByRole('article',{name:subject,exact:true});await expect(entry).toBeVisible();
  const renamed=subject+' editada';await entry.getByLabel('Nuevo nombre',{exact:true}).fill(renamed);await entry.getByRole('button',{name:'Guardar nombre'}).click();const renamedEntry=catalog.getByRole('article',{name:renamed,exact:true});await expect(renamedEntry).toBeVisible();
  await renamedEntry.getByRole('button',{name:'Desactivar',exact:true}).click();await expect(renamedEntry).toContainText('Inactivo');await renamedEntry.getByRole('button',{name:'Reactivar',exact:true}).click();await expect(renamedEntry.getByRole('button',{name:'Desactivar',exact:true})).toBeVisible();
  await catalog.getByLabel('Tipo de catálogo').selectOption('grade');const grade='Grado navegador '+Date.now();await expect(create.getByRole('button',{name:'Crear registro',exact:true})).toBeEnabled();await create.getByLabel('Nombre',{exact:true}).fill(grade);await create.getByRole('button',{name:'Crear registro',exact:true}).click();await expect(catalog.getByRole('article',{name:grade,exact:true})).toBeVisible();
  await catalog.getByLabel('Tipo de catálogo').selectOption('section');await expect(create.getByRole('button',{name:'Crear registro',exact:true})).toBeEnabled();
  await expect(create.getByLabel('Grado',{exact:true})).toBeEnabled();
  for(let i=0;await create.getByLabel('Grado',{exact:true}).getByRole('option',{name:grade,exact:true}).count()===0&&i<200;i++){
    const options=create.getByLabel('Grado',{exact:true}).getByRole('option');const count=await options.count();
    await create.getByRole('button',{name:'Cargar más grados',exact:true}).click();await expect.poll(()=>options.count()).toBeGreaterThan(count);
  }
  await expect(create.getByLabel('Grado',{exact:true}).getByRole('option',{name:grade,exact:true})).toHaveCount(1);
  await create.getByLabel('Grado',{exact:true}).selectOption({label:grade});const section='Sección navegador '+Date.now();await create.getByLabel('Nombre',{exact:true}).fill(section);await create.getByRole('button',{name:'Crear registro',exact:true}).click();await expect(catalog.getByRole('article',{name:section,exact:true})).toBeVisible();
  await page.getByRole('button',{name:'Cerrar sesión',exact:true}).click();await expect(catalog).toHaveCount(0);
});
test('workspace exposes empty forbidden and uncertain states without automatic replay',async({page})=>{
  await directorLogin(page);let lists=0;
  await page.route('**/academic/periods',route=>{lists++;return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({data:{items:[],next_after:null}})});});
  await page.getByRole('button',{name:'Períodos académicos',exact:true}).click();await expect(page.getByText('No hay registros para mostrar.',{exact:true})).toBeVisible();expect(lists).toBe(1);
  await page.unroute('**/academic/periods');await page.route('**/academic/periods',route=>route.fulfill({status:403,contentType:'application/json',body:JSON.stringify({error:'forbidden'})}));
  await page.getByRole('button',{name:'Actualizar registros',exact:true}).click();await expect(page.getByText('No tienes permiso para esta operación.',{exact:true})).toBeVisible();
  await page.unroute('**/academic/periods');await page.getByRole('button',{name:'Actualizar registros',exact:true}).click();
  const form=page.getByRole('form',{name:'Crear período'});await expect(form.getByRole('button',{name:'Crear período'})).toBeEnabled();let writes=0;
  await page.route('**/academic/periods',route=>{if(route.request().method()==='POST'){writes++;return route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({error:'commit_outcome_unknown',correlation_id:'abc',automatic_retry:false})});}return route.continue();});
  await form.getByLabel('Nombre',{exact:true}).fill('Resultado incierto');await form.getByLabel('Inicio',{exact:true}).fill('2026-01-01');await form.getByLabel('Fin',{exact:true}).fill('2026-12-31');await form.getByRole('button',{name:'Crear período'}).click();
  await expect(page.getByText(/Operaciones suspendidas/)).toBeVisible();await expect(form.getByRole('button',{name:'Crear período'})).toBeDisabled();expect(writes).toBe(1);
});
