import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {resolve} from 'node:path';
import {certificateOptions} from '../browser-options.mjs';
const root=resolve('.local/eval-dev');
test('owned certificate gives an exact pin without blanket trust bypass',()=>{
  const options=certificateOptions(readFileSync(resolve(root,'cert.pem')));
  assert.equal(options.ignoreHTTPSErrors,false);assert.equal(options.args.length,1);
  assert.match(options.args[0],/^--ignore-certificate-errors-spki-list=[A-Za-z0-9+/]{43}=$/);
});
test('expired and not-yet-valid owned certificates are rejected',()=>{
  const cert=readFileSync(resolve(root,'cert.pem'));
  assert.throws(()=>certificateOptions(cert,0));assert.throws(()=>certificateOptions(cert,Date.UTC(2100,0,1)));
});
test('invalid PEM cannot grant a browser trust exception',()=>assert.throws(()=>certificateOptions('invalid')));
