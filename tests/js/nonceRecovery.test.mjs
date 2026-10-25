import test from 'node:test';
import assert from 'node:assert/strict';
import axios from 'axios';
import { installNonceRecovery } from '../../src/api/nonce-recovery.js';

const nonceError = (config, code = 'rest_cookie_invalid_nonce', status = 403) => new axios.AxiosError(
  'Request rejected', undefined, config, undefined, { status, data: { code }, config },
);

function setup({ adapter, fetchSession = async () => ({ userId: 16, nonce: 'fresh' }), reload } = {}) {
  const config = { nonce: 'old-tab', userId: 16, sessionRefreshUrl: '/wp-admin/admin-ajax.php?action=rondo_rest_session' };
  const calls = [];
  const api = axios.create({ adapter: async request => {
    calls.push({ nonce: request.headers['X-WP-Nonce'], url: request.url, method: request.method, data: request.data });
    if (adapter) return adapter(request);
    if (request.headers['X-WP-Nonce'] !== 'fresh') throw nonceError(request);
    return { status: 200, data: { people: [{ id: 123 }] }, config: request };
  } });
  api.interceptors.request.use(request => {
    request.headers['X-WP-Nonce'] = config.nonce;
    return request;
  });
  installNonceRecovery(api, config, { fetchSession, reload });
  return { api, config, calls };
}

test('old tab recovers search after another window logs in; concurrent failures share one refresh', async () => {
  let refreshes = 0;
  const { api, calls, config } = setup({ fetchSession: async () => {
    refreshes++;
    await new Promise(resolve => setImmediate(resolve));
    return { userId: 16, nonce: 'fresh' };
  } });
  const results = await Promise.all(Array.from({ length: 4 }, () => api.get('/assignable-people')));
  assert.equal(refreshes, 1);
  assert.equal(config.nonce, 'fresh');
  assert.equal(calls.length, 8);
  for (const result of results) assert.deepEqual(result.data.people, [{ id: 123 }]);
});

test('a delayed failure uses the already renewed nonce without refreshing again', async () => {
  let refreshes = 0;
  let release;
  const delayed = new Promise(resolve => { release = resolve; });
  const { api } = setup({
    fetchSession: async () => { refreshes++; return { userId: 16, nonce: 'fresh' }; },
    adapter: async request => {
      if (request.headers['X-WP-Nonce'] !== 'fresh') {
        if (request.url === '/slow') await delayed;
        throw nonceError(request);
      }
      return { status: 200, data: [], config: request };
    },
  });
  const slow = api.get('/slow');
  await api.get('/fast');
  release();
  await slow;
  assert.equal(refreshes, 1);
});

test('a nonce rejection before a write safely replays the original body once', async () => {
  const { api, calls } = setup();
  await api.post('/assign', { personId: 123 });
  assert.equal(calls.length, 2);
  assert.equal(calls[1].method, 'post');
  assert.equal(calls[1].data, calls[0].data);
});

test('persistent nonce rejection stops after one retry', async () => {
  const { api, calls } = setup({ adapter: async request => { throw nonceError(request); } });
  await assert.rejects(api.get('/search'));
  assert.equal(calls.length, 2);
});

test('permission, network and server errors are never refreshed or replayed', async () => {
  for (const [code, status] of [['rest_forbidden', 403], ['rest_cookie_invalid_nonce', 500], ['not_logged_in', 401]]) {
    const { api, calls } = setup({
      fetchSession: async () => assert.fail('must not refresh'),
      adapter: async request => { throw nonceError(request, code, status); },
    });
    await assert.rejects(api.post('/assign', {}));
    assert.equal(calls.length, 1);
  }
});

test('expired login or changed account never replays a pending write', async () => {
  for (const outcome of ['logged-out', 'different-user', 'missing-nonce']) {
    let reloads = 0;
    const { api, calls, config } = setup({
      fetchSession: async () => {
        if (outcome === 'logged-out') throw new Error('Not logged in');
        return { userId: outcome === 'different-user' ? 99 : 16, nonce: outcome === 'missing-nonce' ? '' : 'fresh' };
      },
      reload: () => { reloads++; },
    });
    await assert.rejects(api.post('/assign', {}));
    assert.equal(calls.length, 1);
    assert.equal(config.nonce, 'old-tab');
    assert.equal(reloads, outcome === 'different-user' ? 1 : 0);
  }
});

test('refresh bypasses the REST API and caches, using the current browser cookie', async () => {
  const originalFetch = globalThis.fetch;
  let refreshes = 0;
  globalThis.fetch = async (url, options) => {
    refreshes++;
    assert.equal(url, '/wp-admin/admin-ajax.php?action=rondo_rest_session');
    assert.deepEqual(options, { credentials: 'same-origin', cache: 'no-store' });
    return { ok: true, json: async () => ({ success: true, data: { userId: 16, nonce: 'fresh' } }) };
  };
  try {
    const config = { nonce: 'old-tab', userId: 16, sessionRefreshUrl: '/wp-admin/admin-ajax.php?action=rondo_rest_session' };
    const client = axios.create({ adapter: async request => {
      if (request.headers['X-WP-Nonce'] !== 'fresh') throw nonceError(request);
      return { status: 200, data: [], config: request };
    } });
    client.defaults.headers.common['X-WP-Nonce'] = config.nonce;
    installNonceRecovery(client, config);
    await client.get('/search');
    assert.equal(refreshes, 1);
    assert.equal(config.nonce, 'fresh');
  } finally {
    globalThis.fetch = originalFetch;
  }
});
