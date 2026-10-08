import { test, expect } from '@playwright/test';
function uiUrl():string {
  const url=new URL(process.env.EVAL_UI_URL??'https://invalid.test');
  if(url.protocol!=='https:'||url.hostname!=='127.0.0.1'||!url.port)throw new Error('Approved isolated HTTPS UI/browser trust harness required');
  return url.origin;
}
// Discovery only in this handoff. Execution requires separately approved browser/TLS setup.
test('session shell presents credential fields without authority selectors',async({page})=>{
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
