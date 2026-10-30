import { test } from 'node:test';
import assert from 'node:assert/strict';
import { reloadForUpdate } from '../../src/utils/app-update.js';

test('reload without a waiting worker leaves existing caches and registrations alone', async () => {
  let reloads = 0;
  await reloadForUpdate(null, () => reloads++);
  assert.equal(reloads, 1);
});

test('explicit update waits for activation and only reloads its caller', async () => {
  const worker = new EventTarget();
  worker.state = 'installed';
  let message;
  worker.postMessage = (value) => { message = value; };
  let reloads = 0;
  const pending = reloadForUpdate({ waiting: worker }, () => reloads++);
  assert.deepEqual(message, { type: 'SKIP_WAITING' });
  assert.equal(reloads, 0);
  worker.state = 'activated';
  worker.dispatchEvent(new Event('statechange'));
  await pending;
  assert.equal(reloads, 1);
  worker.dispatchEvent(new Event('statechange'));
  assert.equal(reloads, 1);
});

test('a failed or unresponsive worker cannot trap the update button', async () => {
  for (const fails of [false, true]) {
    const worker = new EventTarget();
    worker.postMessage = () => { if (fails) throw new Error('Worker disappeared'); };
    let reloads = 0;
    await reloadForUpdate({ waiting: worker }, () => reloads++, 5);
    assert.equal(reloads, 1);
  }
});

// Run the real hook with a small hook-state harness, without a browser or timers.
async function versionHook(currentBuildTime) {
  const { readFile } = await import('node:fs/promises');
  const { runInNewContext } = await import('node:vm');
  const source = (await readFile(new URL('../../src/hooks/useVersionCheck.js', import.meta.url), 'utf8'))
    .replace(/^import .*;$/gm, '')
    .replace('export function', 'function')
    .replace('export default useVersionCheck;', 'useVersionCheck;');
  const slots = [];
  let cursor = 0;
  let calls = 0;
  let response = { buildTime: currentBuildTime, version: '1.0.0' };
  let fail = false;
  const hook = runInNewContext(source, {
    window: { rondoConfig: { buildTime: currentBuildTime, version: '1.0.0' } },
    console: { debug() {} },
    useState(initial) {
      const index = cursor++;
      if (!(index in slots)) slots[index] = typeof initial === 'function' ? initial() : initial;
      return [slots[index], value => { slots[index] = value; }];
    },
    useRef(initial) {
      const index = cursor++;
      return slots[index] ??= { current: initial };
    },
    useCallback: callback => callback,
    useEffect() {},
    prmApi: { async getVersion() {
      calls++;
      if (fail) throw new Error('offline');
      return { data: response };
    } },
  });
  const render = () => { cursor = 0; return hook(); };
  return {
    render,
    get calls() { return calls; },
    async check(data, offline = false) {
      response = data;
      fail = offline;
      await render().checkVersion();
      return render();
    },
  };
}

test('an unchanged release never requests a reload across repeated checks', async () => {
  const hook = await versionHook('build-A');
  for (let i = 0; i < 3; i++) {
    const state = await hook.check({ buildTime: 'build-A', version: '1.0.0' });
    assert.equal(state.hasUpdate, false);
    assert.equal(state.latestBuildTime, null);
  }
});

test('a real deployment is identified by its build, even without a version bump', async () => {
  const hook = await versionHook('build-A');
  const state = await hook.check({ buildTime: 'build-B', version: '1.0.0' });
  assert.equal(state.hasUpdate, true);
  assert.equal(state.latestBuildTime, 'build-B');
  assert.equal(state.latestVersion, '1.0.0');
});

test('a subsequent matching response clears a previously detected update', async () => {
  const hook = await versionHook('build-A');
  await hook.check({ buildTime: 'build-B', version: '1.0.1' });
  const state = await hook.check({ buildTime: 'build-A', version: '1.0.0' });
  assert.equal(state.hasUpdate, false);
  assert.equal(state.latestBuildTime, null);
  assert.equal(state.latestVersion, null);
});

test('missing builds and network failures do not invent a release', async () => {
  const development = await versionHook(null);
  assert.equal((await development.check({ buildTime: 'build-B' })).hasUpdate, false);
  assert.equal(development.calls, 0);
  const hook = await versionHook('build-A');
  assert.equal((await hook.check({ buildTime: null })).hasUpdate, false);
  assert.equal((await hook.check(null, true)).hasUpdate, false);
  await hook.check({ buildTime: 'build-B', version: '1.0.1' });
  assert.equal((await hook.check(null, true)).hasUpdate, true);
});
