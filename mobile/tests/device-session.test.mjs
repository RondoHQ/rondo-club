import test from 'node:test';
import assert from 'node:assert/strict';
import { DeviceSession } from '../src/device-session.mjs';

const club = { id: 'a', name: 'Test club', url: 'https://club.example.test' };
const now = 1800000000000;
const record = { clubId: 'a', clubUrl: club.url, refreshToken: 'r'.repeat(43), expiresAt: now + 86400000 };
const pair = (letter = 'b') => ({ access_token: letter.repeat(43), refresh_token: letter.toUpperCase().repeat(43), token_type: 'Bearer', expires_in: 300, refresh_expires_at: record.expiresAt / 1000 });
function setup(request, initial = { version: 1, active: record, revocations: [] }) {
  let stored = initial ? JSON.stringify(initial) : null;
  const vault = { read: async () => ({ value: stored }), write: async ({ value }) => { stored = value; }, clear: async () => { stored = null; } };
  const auth = new DeviceSession({ vault, request, clubs: [club], now: () => now });
  return { auth, vault, stored: () => stored && JSON.parse(stored) };
}

test('cold launch rotates before access and only persists refresh credentials and reviewed club identity', async () => {
  const { auth, stored } = setup(async (_, path) => path === '/token' ? pair() : { name: 'Member' });
  await auth.restore();
  assert.equal(auth.session.token, 'b'.repeat(43));
  assert.deepEqual(Object.keys(stored().active).sort(), ['clubId', 'clubUrl', 'expiresAt', 'refreshToken']);
  assert.equal(stored().active.refreshToken, 'B'.repeat(43));
  assert.deepEqual(await auth.read('/read?resource=me'), { name: 'Member' });
});

test('simultaneous expired reads coalesce a single refresh request', async () => {
  let rotations = 0;
  const { auth } = setup(async (_, path) => { if (path === '/token') { rotations++; return pair(); } return {}; });
  await auth.restore();
  auth.session.expiresAt = now;
  await Promise.all([auth.read('/read?resource=me'), auth.read('/read?resource=household'), auth.read('/read?resource=calendar')]);
  assert.equal(rotations, 2);
});

test('network failure retains encrypted login; revoked refresh clears it', async () => {
  const network = setup(async () => { throw new Error('Offline'); });
  await assert.rejects(network.auth.restore(), /Offline/);
  assert.deepEqual(network.stored().active, record);
  assert.equal(network.auth.session, null);
  const denied = setup(async () => { throw Object.assign(new Error(), { code: 'invalid_grant' }); });
  await assert.rejects(denied.auth.restore(), { status: 401 });
  assert.equal(denied.stored(), null);
});

test('unreviewed club and expired persisted credentials are never sent', async () => {
  for (const active of [{ ...record, clubUrl: 'https://attacker.test' }, { ...record, expiresAt: now - 1 }]) {
    const { auth, stored } = setup(async () => assert.fail('Unexpected network request'), { version: 1, active, revocations: [] });
    assert.equal(await auth.restore(), null);
    assert.equal(stored(), null);
  }
});

test('offline logout is durable and revocation is retried at the next launch', async () => {
  let offline = false;
  let revoked = 0;
  const env = setup(async (_, path) => {
    if (path === '/token') return pair();
    if (offline) throw new Error('Offline');
    revoked++; return { revoked: true };
  });
  await env.auth.restore();
  offline = true;
  await env.auth.logout();
  assert.equal(env.auth.session, null);
  assert.equal(env.stored().active, null);
  assert.equal(env.stored().revocations.length, 1);
  offline = false;
  assert.equal(await env.auth.restore(), null);
  assert.equal(revoked, 1);
  assert.equal(env.stored(), null);
});

test('logout during rotation cannot resurrect a session and revokes the rotated credential', async () => {
  let release;
  let revoked;
  const env = setup(async (_, path, options) => {
    if (path === '/token') return new Promise((resolve) => { release = () => resolve(pair()); });
    revoked = options.data.refresh_token; return {};
  });
  const restoring = env.auth.restore();
  await new Promise((resolve) => setImmediate(resolve));
  const logout = env.auth.logout();
  release();
  await assert.rejects(restoring, { status: 401 });
  await logout;
  assert.equal(env.auth.session, null);
  assert.equal(env.stored(), null);
  assert.equal(revoked, 'B'.repeat(43));
});

test('vault write failure never publishes access and revokes the issued refresh', async () => {
  let revoked = false;
  const { auth, vault } = setup(async (_, path) => { if (path === '/token') return pair(); revoked = true; return {}; });
  vault.write = async () => { throw new Error('Vault failed'); };
  await assert.rejects(auth.restore(), /Vault failed/);
  assert.equal(auth.session, null);
  assert.equal(revoked, true);
});

test('logout reports storage failure instead of claiming durable logout', async () => {
  const { auth, vault } = setup(async () => pair());
  await auth.restore();
  vault.write = async () => { throw new Error('Vault failed'); };
  await assert.rejects(auth.logout(), /Vault failed/);
  assert.equal(auth.session, null);
});
