import test from 'node:test';
import assert from 'node:assert/strict';
import { MEMBER_SCOPE, PROFILE_SCOPE, READ_SCOPE } from '../src/auth.mjs';
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

test('pending login survives process death and resumes the identical PKCE request', async () => {
  const env = setup(async () => assert.fail('No token request during pending restore'), null);
  const original = await env.auth.start(club);
  const fresh = new DeviceSession({ vault: env.vault, request: env.auth.request, clubs: [club], now: () => now + 60000 });
  assert.equal(await fresh.restore(), null);
  assert.equal(await fresh.resumeLogin(), original);
  assert.equal(fresh.pending.club.url, club.url);
  assert.deepEqual(Object.keys(env.stored().pending).sort(), ['clubId', 'clubUrl', 'createdAt', 'scope', 'state', 'verifier']);
});

test('cold callback consumes persisted verifier before exchange; duplicate delivery exchanges once', async () => {
  let calls = 0;
  const env = setup(async (_, path, options) => {
    assert.equal(path, '/token');
    assert.equal(env.stored(), null);
    assert.equal(options.data.code_verifier, verifier);
    calls++;
    return pair();
  }, null);
  await env.auth.start(club);
  const { verifier, state } = env.auth.pending;
  const fresh = new DeviceSession({ vault: env.vault, request: env.auth.request, clubs: [club], now: () => now });
  await fresh.restore();
  const url = `club.rondo.spike://oauth/callback?state=${state}&code=${'c'.repeat(43)}`;
  await Promise.all([fresh.finish(url), fresh.finish(url)]);
  assert.equal(calls, 1);
  assert.equal(env.stored().pending, null);
  assert.ok(env.stored().active);
});

test('cancel removes pending login durably and rejects a later callback after restart', async () => {
  const env = setup(async () => assert.fail('Cancelled login must not exchange'), null);
  await env.auth.start(club);
  const url = `club.rondo.spike://oauth/callback?state=${env.auth.pending.state}&code=${'c'.repeat(43)}`;
  await env.auth.logout();
  const fresh = new DeviceSession({ vault: env.vault, request: env.auth.request, clubs: [club], now: () => now });
  await fresh.restore();
  await assert.rejects(fresh.finish(url));
  assert.equal(env.stored(), null);
});

test('denied browser consent clears pending state; unrelated callback leaves it intact', async () => {
  const env = setup(async () => assert.fail('Denied login must not exchange'), null);
  await env.auth.start(club);
  const { state } = env.auth.pending;
  await assert.rejects(env.auth.finish(`club.rondo.spike://oauth/callback?state=${'x'.repeat(43)}&error=access_denied`));
  assert.ok(env.stored().pending);
  await assert.rejects(env.auth.finish(`club.rondo.spike://oauth/callback?state=${state}&error=access_denied`), { code: 'login_cancelled' });
  assert.equal(env.stored(), null);
});

test('expired or unreviewed saved login cannot restore or produce a browser URL', async () => {
  for (const mutate of [(pending) => { pending.createdAt = now - 600000; }, (pending) => { pending.clubUrl = 'https://other.test'; }]) {
    const env = setup(async () => assert.fail('No network request for invalid pending login'), null);
    await env.auth.start(club);
    const stored = env.stored();
    mutate(stored.pending);
    await env.vault.write({ value: JSON.stringify(stored) });
    const fresh = new DeviceSession({ vault: env.vault, request: env.auth.request, clubs: [club], now: () => now });
    assert.equal(await fresh.restore(), null);
    assert.equal(fresh.pending, null);
    await assert.rejects(fresh.resumeLogin(), { status: 401 });
    assert.equal(env.stored(), null);
  }
});

test('vault failure prevents starting browser login', async () => {
  const env = setup(async () => assert.fail('No network request'), null);
  env.vault.write = async () => { throw new Error('Vault unavailable'); };
  await assert.rejects(env.auth.start(club), /Vault unavailable/);
  assert.equal(env.auth.pending, null);
});


test('cancelling while pending storage is in flight cannot reopen login after restart', async () => {
  const env = setup(async () => assert.fail('No network request'), null);
  const write = env.vault.write;
  let release;
  const blocked = new Promise((resolve) => { release = resolve; });
  let started;
  const writing = new Promise((resolve) => { started = resolve; });
  env.vault.write = async (value) => { started(); await blocked; await write(value); };
  const login = env.auth.start(club);
  await writing;
  const cancelled = env.auth.logout();
  const rejected = assert.rejects(login, { status: 401 });
  release();
  await Promise.all([rejected, cancelled]);
  const fresh = new DeviceSession({ vault: env.vault, request: env.auth.request, clubs: [club], now: () => now });
  assert.equal(await fresh.restore(), null);
  assert.equal(fresh.pending, null);
  assert.equal(env.stored(), null);
});


test('old read-only device sessions cannot write or gain consent by refreshing', async () => {
  const env = setup(async (_, path) => { assert.equal(path, '/token'); return pair(); });
  await env.auth.restore();
  assert.equal(env.auth.session.scope, READ_SCOPE);
  await assert.rejects(env.auth.changeShift(12, 'signup'), { code: 'consent_required' });
});

test('member writes never retry a lost response and block simultaneous submissions', async () => {
  let release;
  let writes = 0;
  const env = setup(async (_, path) => {
    if (path === '/token') return { ...pair(), scope: MEMBER_SCOPE };
    writes++;
    return new Promise((_, reject) => { release = () => reject(new Error('Connection lost')); });
  });
  await env.auth.restore();
  const first = env.auth.changeShift(12, 'signup');
  await new Promise((resolve) => setImmediate(resolve));
  await assert.rejects(env.auth.changeShift(12, 'signup'), /vorige wijziging/);
  release();
  await assert.rejects(first, /Connection lost/);
  assert.equal(writes, 1);
});

test('logout during a member write never publishes its late result', async () => {
  let release;
  const env = setup(async (_, path) => {
    if (path === '/token') return { ...pair(), scope: MEMBER_SCOPE };
    if (path === '/revoke') return {};
    return new Promise((resolve) => { release = () => resolve({ signed_up: true }); });
  });
  await env.auth.restore();
  const writing = env.auth.changeShift(12, 'signup');
  await new Promise((resolve) => setImmediate(resolve));
  await env.auth.logout();
  release();
  await assert.rejects(writing, { status: 401 });
  assert.equal(env.auth.session, null);
});

test('legacy pending authorization resumes with read-only consent', async () => {
  const env = setup(async () => assert.fail('No request expected'), null);
  await env.auth.start(club);
  const stored = env.stored();
  delete stored.pending.scope;
  await env.vault.write({ value: JSON.stringify(stored) });
  const fresh = new DeviceSession({ vault: env.vault, request: env.auth.request, clubs: [club], now: () => now });
  await fresh.restore();
  assert.equal(new URL(await fresh.resumeLogin()).searchParams.get('scope'), READ_SCOPE);
});


test('profile consent is separate from old volunteer consent and survives a cold restore', async () => {
  for (const scope of [READ_SCOPE, MEMBER_SCOPE]) {
    const env = setup(async (_, path) => { assert.equal(path, '/token'); return { ...pair(), scope }; });
    await env.auth.restore();
    await assert.rejects(env.auth.changeProfile('email_cancel'), { code: 'consent_required' });
  }
  const env = setup(async (_, path, options) => {
    if (path === '/token') return { ...pair(), scope: PROFILE_SCOPE };
    assert.equal(path, '/profile');
    assert.deepEqual(options.data, { action: 'email_cancel', values: {} });
    return { success: true };
  });
  await env.auth.restore();
  assert.deepEqual(await env.auth.changeProfile('email_cancel'), { success: true });
});

test('profile writes are never retried, block concurrent duty writes, and reject late logout results', async () => {
  let release;
  let count = 0;
  const env = setup(async (_, path) => {
    if (path === '/token') return { ...pair(), scope: PROFILE_SCOPE };
    if (path === '/revoke') return {};
    count++;
    return new Promise((resolve) => { release = () => resolve({ success: true }); });
  });
  await env.auth.restore();
  const writing = env.auth.changeProfile('phones', { mobile_1: '+31612345678' });
  await new Promise((resolve) => setImmediate(resolve));
  await assert.rejects(env.auth.changeShift(12, 'signup'), /vorige wijziging/);
  await env.auth.logout();
  release();
  await assert.rejects(writing, { status: 401 });
  assert.equal(count, 1);
  assert.equal(env.auth.session, null);
});

test('profile write response loss is surfaced once without resending or storing the form', async () => {
  let count = 0;
  const env = setup(async (_, path) => {
    if (path === '/token') return { ...pair(), scope: PROFILE_SCOPE };
    count++; throw new Error('Lost response after storage');
  });
  await env.auth.restore();
  await assert.rejects(env.auth.changeProfile('email_request', { slot: 'secondary', email: 'new@example.test' }), /Lost response/);
  assert.equal(count, 1);
  assert.equal(JSON.stringify(env.stored()).includes('new@example.test'), false);
});
