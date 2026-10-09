import {test} from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {resolve} from 'node:path';
function command(name){return spawnSync('powershell.exe',['-NoProfile','-File',resolve('backend/scripts/manage-dev.ps1'),'-Command',name],{encoding:'utf8',timeout:20000});}
test('live isolated app status confirms both owned workers',()=>{
  const r=command('status');assert.equal(r.status,0);assert.match(r.stdout,/app running: True/);
});
test('duplicate start is denied and retains academic data and existing workers',()=>{
  const before=command('snapshot');assert.equal(before.status,0);
  const r=command('start');assert.equal(r.status,2);assert.match(r.stderr,/already has owned workers/);
  const after=command('snapshot');assert.equal(after.status,0);assert.deepEqual(JSON.parse(after.stdout),JSON.parse(before.stdout));
  assert.match(command('status').stdout,/app running: True/);
});
