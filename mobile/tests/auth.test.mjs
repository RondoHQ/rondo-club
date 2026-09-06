import test from 'node:test';
import assert from 'node:assert/strict';
import { beginLogin, readCallback, validateClubs, LoginSession, CALLBACK, LOGIN_TTL } from '../src/auth.mjs';
const club = { id: 'alpha', name: 'Club Alpha', url: 'https://alpha.example.test' };
const code = 'a'.repeat(43);
const callback = (pending) => `${CALLBACK}?state=${pending.state}&code=${code}`;
const tokens = () => ({ token_type: 'Bearer', access_token: 'b'.repeat(43), expires_in: 300 });

test('native login uses PKCE S256, exact callback and no client secret', async () => {
  const { url, pending } = await beginLogin(club);
  const params = new URL(url).searchParams;
  assert.equal(params.get('redirect_uri'), CALLBACK);
  assert.equal(params.get('code_challenge_method'), 'S256');
  assert.equal(params.get('code_challenge'), Buffer.from(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(pending.verifier))).toString('base64url'));
  assert.equal(params.has('client_secret'), false);
  assert.equal(params.has('code_verifier'), false);
});

test('club directory rejects untrusted URLs, duplicates and credentials', () => {
  for (const url of ['http://alpha.test', 'javascript:alert(1)', 'https://user:password@alpha.test', 'https://alpha.test/?redirect=bad', 'https://alpha.test/path']) {
    assert.throws(() => validateClubs([{ ...club, url }]));
  }
  assert.throws(() => validateClubs([club, club]));
  assert.deepEqual(validateClubs([club]), [club]);
  const logoUrl = 'https://www.svawc.nl/wp-content/uploads/2024/02/awc-logo.svg';
  assert.equal(validateClubs([{ ...club, logoUrl }])[0].logoUrl, logoUrl);
  for (const unsafe of ['http://logos.test/club.svg', 'https://user:secret@logos.test/club.svg', 'javascript:alert(1)']) {
    assert.throws(() => validateClubs([{ ...club, logoUrl: unsafe }]));
  }
});

test('callback rejects another scheme, state, duplicates, code and expired login', async () => {
  const { pending } = await beginLogin(club);
  for (const url of [callback(pending).replace('club.rondo.spike:', 'evil:'), `${callback(pending)}&state=other`, callback(pending).replace(pending.state, 'x'), `${CALLBACK}?state=${pending.state}`, `${callback(pending)}#fragment`]) {
    assert.throws(() => readCallback(url, pending));
  }
  assert.throws(() => readCallback(callback(pending), pending, pending.createdAt + LOGIN_TTL + 1));
  assert.throws(() => readCallback(callback(pending), null));
  assert.equal(readCallback(callback(pending), pending).code_verifier, pending.verifier);
});

test('unrelated callback does not cancel the current login', async () => {
  const auth = new LoginSession();
  await auth.start(club);
  const pending = auth.pending;
  await assert.rejects(auth.finish('https://elsewhere.test', tokens));
  assert.equal(auth.pending, pending);
});

test('duplicate callback exchanges once and the original club remains the audience', async () => {
  const auth = new LoginSession();
  await auth.start(club);
  const url = callback(auth.pending);
  let calls = 0;
  const exchange = async (selected) => { calls++; assert.equal(selected.id, club.id); return tokens(); };
  const first = auth.finish(url, exchange);
  await assert.rejects(auth.finish(url, exchange));
  await first;
  assert.equal(calls, 1);
  assert.equal(auth.session.club, club);
});

test('logout or switching clubs discards an in-flight response', async () => {
  const auth = new LoginSession();
  await auth.start(club);
  let respond;
  const finishing = auth.finish(callback(auth.pending), () => new Promise((resolve) => { respond = resolve; }));
  auth.clear();
  respond(tokens());
  await assert.rejects(finishing);
  assert.equal(auth.session, null);
});

test('a previous club callback cannot finish a new club login', async () => {
  const auth = new LoginSession();
  await auth.start(club);
  const old = callback(auth.pending);
  await auth.start({ id: 'beta', name: 'Club Beta', url: 'https://beta.example.test' });
  await assert.rejects(auth.finish(old, tokens));
  assert.equal(auth.pending.club.id, 'beta');
});

test('malformed token replies never become a session', async () => {
  for (const result of [{}, { ...tokens(), expires_in: 3600 }, { ...tokens(), access_token: 'invalid' }]) {
    const auth = new LoginSession();
    await auth.start(club);
    await assert.rejects(auth.finish(callback(auth.pending), async () => result));
    assert.equal(auth.session, null);
  }
});
