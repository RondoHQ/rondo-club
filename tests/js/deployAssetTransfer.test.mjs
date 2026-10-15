import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

// Execute the actual dist transfer section, replacing SSH/SCP with local transport.
const deployment = readFileSync(new URL('../../bin/deploy.sh', import.meta.url), 'utf8');
const archiveFunction = deployment.slice(deployment.indexOf('archive_sync() {'), deployment.indexOf('\necho -e "${GREEN}'));
const transfer = deployment.slice(deployment.indexOf('php "$SCRIPT_DIR/retain-assets.php"'), deployment.indexOf('echo -e "${YELLOW}Step 3:'));
const binDir = new URL('../../bin/', import.meta.url).pathname;

for (const fallback of ['0', '1']) {
  test(`dist transfer preserves previous chunks and publishes assets first (${fallback === '1' ? 'archive fallback' : 'rsync'})`, (t) => {
    const dir = mkdtempSync(join(tmpdir(), 'rondo-transfer-'));
    t.after(() => rmSync(dir, { recursive: true, force: true }));
    const source = join(dir, 'release');
    const target = join(dir, 'remote');
    for (const path of [`${source}/dist/assets`, `${target}/dist/assets`]) mkdirSync(path, { recursive: true });
    writeFileSync(`${source}/dist/assets/new.js`, '// new chunk');
    writeFileSync(`${source}/dist/sw.js`, '// new worker');
    writeFileSync(`${target}/dist/assets/old.js`, '// old chunk');
    writeFileSync(`${target}/dist/.asset-retention.json`, '{"old.js":123}');
    // Stale source state must never overwrite the server's grace-period history.
    writeFileSync(`${source}/dist/.asset-retention.json`, '{}');
    const harness = `set -euo pipefail
YELLOW= NC=
REMOTE_TARGET=local
RSYNC_SSH=unused
REMOTE_THEME_PATH_RESOLVED="$TARGET"
DEPLOY_REMOTE_THEME_PATH="$TARGET"
SOURCE_DIR="$SOURCE"
SSH_CMD=(local_ssh)
SCP_CMD=(local_scp)
retry_remote() { "$@"; }
local_ssh() { bash -c "$2"; }
local_scp() { cp "$1" "\x24{2#local:}"; }
rsync() {
  if [ "$FALLBACK" = 1 ]; then return 1; fi
  local args=()
  while [ "$#" -gt 0 ]; do
    if [ "$1" = -e ]; then shift 2; else args+=("\x24{1#local:}"); shift; fi
  done
  command rsync "\x24{args[@]}"
}
${archiveFunction}
${transfer}
`;
    // Check transfer order as well as final contents, for both transport paths.
    const metadataStart = harness.indexOf('echo -e "${YELLOW}Step 2b:');
    assert.ok(metadataStart > 0);
    const script = harness.slice(0, metadataStart) + '\ntest -f "$TARGET/dist/assets/new.js"\ntest ! -f "$TARGET/dist/sw.js"\n' + harness.slice(metadataStart);
    const result = spawnSync('bash', ['-c', script], { env: { ...process.env, SCRIPT_DIR: binDir, SOURCE: source, TARGET: target, FALLBACK: fallback }, encoding: 'utf8' });
    assert.equal(result.status, 0, result.stdout + result.stderr);
    assert.equal(readFileSync(`${target}/dist/assets/old.js`, 'utf8'), '// old chunk');
    assert.equal(readFileSync(`${target}/dist/assets/new.js`, 'utf8'), '// new chunk');
    assert.equal(readFileSync(`${target}/dist/sw.js`, 'utf8'), '// new worker');
    assert.deepEqual(JSON.parse(readFileSync(`${target}/dist/.asset-inventory.json`)), ['new.js']);
    assert.equal(readFileSync(`${target}/dist/.asset-retention.json`, 'utf8'), '{"old.js":123}');
  });
}
