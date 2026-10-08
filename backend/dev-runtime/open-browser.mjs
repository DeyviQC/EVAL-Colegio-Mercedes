import { chromium } from '../../frontend/node_modules/playwright-core/index.mjs';
import { readFileSync, writeFileSync, existsSync, unlinkSync } from 'node:fs';
import { resolve } from 'node:path';
import { certificateOptions } from './browser-options.mjs';
const root=process.env.EVAL_DEV_ROOT;
const input=[];for await(const chunk of process.stdin)input.push(chunk);
const account=JSON.parse(Buffer.concat(input).toString());
if(!root||!['director_admin','vice_principal','teacher','student'].includes(account.role))throw new Error('Invalid dev browser binding.');
const options=certificateOptions(readFileSync(resolve(root,'cert.pem')));
const stopPath=resolve(root,`browser-stop-${account.role}`);
if(existsSync(stopPath))unlinkSync(stopPath);
const context=await chromium.launchPersistentContext(resolve(root,`browser-${account.role}`),{...options,headless:false,viewport:null});
const timer=setInterval(()=>{if(existsSync(stopPath))void context.close();},500);
const page=context.pages()[0]??await context.newPage();
await page.goto('https://127.0.0.1:8443/');
await page.getByRole('heading',{name:/Acceso a EVAL|Tu espacio académico/}).waitFor();
if(await page.getByLabel('Usuario',{exact:true}).isVisible()){
  await page.getByLabel('Usuario',{exact:true}).fill(account.login);
  await page.getByLabel('Contraseña',{exact:true}).fill(account.password);
  await page.getByRole('button',{name:'Ingresar',exact:true}).click();
  await page.getByRole('heading',{name:'Tu espacio académico'}).waitFor();
}
account.password='';
writeFileSync(resolve(root,`browser-ready-${account.role}`),'authenticated');
await new Promise(resolve=>context.once('close',resolve));
clearInterval(timer);
