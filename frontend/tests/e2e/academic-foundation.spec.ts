import { test, expect } from '@playwright/test';
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
