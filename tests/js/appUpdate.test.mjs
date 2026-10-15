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
