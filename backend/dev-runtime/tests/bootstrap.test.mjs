import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

const php = resolve(process.env.LOCALAPPDATA, 'Temp/opencode/eval-u1/php/php.exe');
const config = resolve('backend/dev-runtime/config.php');
function assess(grants) {
  const code = 'require $argv[1]; echo json_encode(evalDevCapabilities(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR)),JSON_THROW_ON_ERROR);';
  const result = spawnSync(php, ['-n', '-r', code, config], {
    input: JSON.stringify(grants), encoding: 'utf8',
    env: { SystemRoot: process.env.SystemRoot, TEMP: process.env.TEMP, TMP: process.env.TMP },
  });
  assert.equal(result.status, 0, 'Pure PHP capability assessment must succeed');
  return JSON.parse(result.stdout);
}
const denied = { create_database: false, create_user: false, grant_dev_permissions: false };

test('test-schema privileges cannot provision persistent dev', () => {
  assert.deepEqual(assess(['GRANT USAGE ON *.* TO `fixture`@`localhost`',
    'GRANT ALL PRIVILEGES ON `eval_u11_test`.* TO `fixture`@`localhost` WITH GRANT OPTION']), denied);
});
test('global administrator has the required static capabilities', () => {
  assert.deepEqual(assess(['GRANT ALL PRIVILEGES ON *.* TO `fixture`@`localhost` WITH GRANT OPTION']),
    { create_database: true, create_user: true, grant_dev_permissions: true });
});
test('exact dev schema privileges plus global CREATE USER suffice', () => {
  assert.deepEqual(assess(['GRANT CREATE USER ON *.* TO `fixture`@`localhost`',
    'GRANT ALL PRIVILEGES ON `eval_dev`.* TO `fixture`@`localhost` WITH GRANT OPTION']),
    { create_database: true, create_user: true, grant_dev_permissions: true });
});
test('CREATE alone and missing grant option never imply grant authority', () => {
  assert.deepEqual(assess(['GRANT CREATE ON `eval_dev`.* TO `fixture`@`localhost`']),
    { create_database: true, create_user: false, grant_dev_permissions: false });
});
test('wildcard databases, table/column grants and role assignments fail closed', () => {
  assert.deepEqual(assess(['GRANT ALL PRIVILEGES ON `eval_%`.* TO `fixture`@`localhost` WITH GRANT OPTION',
    'GRANT CREATE USER ON `eval_dev`.* TO `fixture`@`localhost`',
    'GRANT SELECT (`id`) ON `eval_dev`.`local_sessions` TO `fixture`@`localhost`',
    'GRANT `admin_role`@`localhost` TO `fixture`@`localhost`']), denied);
});
test('grant text and credential-like suffixes never appear in output', () => {
  const output = assess(['GRANT USAGE ON *.* TO `fixture`@`localhost` IDENTIFIED BY PASSWORD \'synthetic-secret\'']);
  assert.deepEqual(output, denied);
  assert.equal(JSON.stringify(output).includes('synthetic-secret'), false);
});

test('separately grantable privileges combine only for the exact dev schema', () => {
  assert.deepEqual(assess(['GRANT CREATE USER ON *.* TO `fixture`@`localhost`',
    'GRANT SELECT, INSERT, UPDATE, DELETE ON `eval_dev`.* TO `fixture`@`localhost` WITH GRANT OPTION',
    'GRANT CREATE, DROP, ALTER, INDEX, REFERENCES, TRIGGER ON `eval_dev`.* TO `fixture`@`localhost` WITH GRANT OPTION']),
    { create_database: true, create_user: true, grant_dev_permissions: true });
});
test('partial dev grant authority is not full bootstrap capability', () => {
  assert.deepEqual(assess(['GRANT CREATE USER ON *.* TO `fixture`@`localhost`',
    'GRANT CREATE, SELECT ON `eval_dev`.* TO `fixture`@`localhost` WITH GRANT OPTION']),
    { create_database: true, create_user: true, grant_dev_permissions: false });
});
test('partial revocations prevent optimistic bootstrap authorization', () => {
  assert.deepEqual(assess(['GRANT ALL PRIVILEGES ON *.* TO `fixture`@`localhost` WITH GRANT OPTION',
    'REVOKE INSERT, UPDATE ON `eval_dev`.* FROM `fixture`@`localhost`']), denied);
});
test('capability entry rejects missing binding without connection or secret diagnostics', () => {
  const result = spawnSync(php, ['-n', resolve('backend/dev-runtime/capability.php')], {
    encoding: 'utf8', env: { SystemRoot: process.env.SystemRoot, EVAL_DB_PASSWORD: 'synthetic-secret' },
  });
  assert.equal(result.status, 2);
  assert.equal(result.stdout, '');
  assert.match(result.stderr, /^Owned capability check unavailable; no database mutation performed\.\r?\n$/);
  assert.equal(result.stderr.includes('synthetic-secret'), false);
});
test('wrapper rejects unapproved commands before credential resolution', () => {
  const result = spawnSync('powershell.exe', ['-NoProfile', '-File', resolve('backend/scripts/dev.ps1'),
    '-Command', 'provision'], { encoding: 'utf8' });
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /ValidateSet|ValidationMetadataException/);
});
test('wrapper rejects ambient administrator account selection', () => {
  const result = spawnSync('powershell.exe', ['-NoProfile', '-File', resolve('backend/scripts/dev.ps1'),
    '-Role', 'root'], { encoding: 'utf8' });
  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /ValidateSet|ValidationMetadataException/);
});
