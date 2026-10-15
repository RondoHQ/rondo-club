import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, rmSync, utimesSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const script = new URL('../../bin/retain-assets.php', import.meta.url).pathname;
function fixture(t) {
  const dir = mkdtempSync(join(tmpdir(), 'rondo-assets-'));
  mkdirSync(join(dir, 'assets'));
  t.after(() => rmSync(dir, { recursive: true, force: true }));
  const file = (name, value = '// asset') => writeFileSync(join(dir, name), value);
  const run = (mode) => spawnSync('php', [script, mode, dir], { encoding: 'utf8' });
  file('assets/current.js');
  assert.equal(run('inventory').status, 0);
  return { dir, file, run, state: () => JSON.parse(readFileSync(join(dir, '.asset-retention.json'))) };
}

test('old uncached chunks survive deployment regardless of their build mtime', (t) => {
  const f = fixture(t);
  f.file('assets/old.js');
  utimesSync(join(f.dir, 'assets/old.js'), 1, 1);
  assert.equal(f.run('prune').status, 0);
  assert.ok(existsSync(join(f.dir, 'assets/old.js')));
  assert.ok(f.state()['old.js'] >= Math.floor(Date.now() / 1000) - 2);
});

test('retirement ages across releases, expires after seven days, and protects rollbacks', (t) => {
  const f = fixture(t);
  for (const name of ['recent.js', 'expired.js', 'rollback.js']) f.file(`assets/${name}`);
  const now = Math.floor(Date.now() / 1000);
  f.file('.asset-retention.json', JSON.stringify({ 'recent.js': now - 3600, 'expired.js': now - 7 * 86400, 'rollback.js': 1, 'current.js': 1 }));
  f.file('.asset-inventory.json', JSON.stringify(['current.js', 'rollback.js']));
  assert.equal(f.run('prune').status, 0);
  assert.ok(existsSync(join(f.dir, 'assets/recent.js')));
  assert.equal(existsSync(join(f.dir, 'assets/expired.js')), false);
  assert.ok(existsSync(join(f.dir, 'assets/current.js')));
  assert.ok(existsSync(join(f.dir, 'assets/rollback.js')));
  assert.deepEqual(f.state(), { 'recent.js': now - 3600 });
});

test('missing current assets or corrupt retention state fail before deleting anything', (t) => {
  const f = fixture(t);
  f.file('assets/old.js');
  f.file('.asset-retention.json', JSON.stringify({ 'old.js': 1 }));
  f.file('.asset-inventory.json', '["missing.js"]');
  assert.notEqual(f.run('prune').status, 0);
  assert.ok(existsSync(join(f.dir, 'assets/old.js')));
  f.file('.asset-inventory.json', '["current.js"]');
  f.file('.asset-retention.json', '{broken');
  assert.notEqual(f.run('prune').status, 0);
  assert.ok(existsSync(join(f.dir, 'assets/old.js')));
});

test('symlinked assets cannot cause cleanup outside the build directory', (t) => {
  const f = fixture(t);
  f.file('outside.js');
  symlinkSync(join(f.dir, 'outside.js'), join(f.dir, 'assets/link.js'));
  assert.notEqual(f.run('prune').status, 0);
  assert.ok(existsSync(join(f.dir, 'outside.js')));
});
