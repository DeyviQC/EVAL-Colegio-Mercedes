import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';
const php=resolve(process.env.LOCALAPPDATA,'Temp/opencode/eval-u1/php/php.exe');
function binding(overrides={}) {
  const env={EVAL_UNIT:'Dev',EVAL_DB_HOST:'127.0.0.1',EVAL_DB_PORT:'3307',EVAL_DB_NAME:'eval_dev',EVAL_DB_ROLE:'runtime',EVAL_DB_USER:'eval_dev_runtime',...overrides};
  const r=spawnSync(php,['-n','-r','require $argv[1]; echo json_encode(evalDevBinding(json_decode(stream_get_contents(STDIN),true)));',resolve('backend/dev-runtime/config.php')],{input:JSON.stringify(env),encoding:'utf8'});
  assert.equal(r.status,0);return JSON.parse(r.stdout);
}
test('dev runtime permits only its exact isolated binding',()=>assert.equal(binding(),true));
test('dev migration identity is separate',()=>assert.equal(binding({EVAL_DB_ROLE:'migration',EVAL_DB_USER:'eval_dev_migration'}),true));
test('dev rejects test schemas, other hosts, ports, root and mismatched roles',()=>{
  for(const overrides of [{EVAL_DB_NAME:'eval_u11_test'},{EVAL_DB_NAME:'eval'},{EVAL_DB_HOST:'localhost'},{EVAL_DB_PORT:'3306'},{EVAL_UNIT:'U11'},{EVAL_DB_USER:'root'},{EVAL_DB_ROLE:'migration'},{EVAL_DB_USER:'eval_u1_runtime'}])assert.equal(binding(overrides),false);
});
