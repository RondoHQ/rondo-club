import test from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'vite';
import { fileURLToPath } from 'node:url';

// Exercise actual compiled pilot behavior; the Node default remains the isolated spike.
async function pilotModule(file) {
  const result = await build({ configFile: false, mode: 'pilot', logLevel: 'silent', build: { write: false, minify: false, lib: { entry: fileURLToPath(new URL(`../src/${file}`, import.meta.url)), formats: ['es'] } } });
  return import(`data:text/javascript;base64,${Buffer.from(result[0].output[0].code).toString('base64')}`);
}

test('compiled pilot authorizes only read access using the fixed verified AWC callback', async () => {
  const auth = await pilotModule('auth.mjs');
  assert.equal(auth.API_PATH, '/wp-json/rondo-mobile-pilot/v1');
  assert.deepEqual(auth.VALID_SCOPES, ['rondo:pilot:read']);
  assert.equal(auth.canChangeShifts(auth.PROFILE_SCOPE), false);
  const club = { id: 'awc', name: 'AWC', url: 'https://rondo.svawc.nl' };
  const { pending, url } = await auth.beginLogin(club);
  const params = new URL(url).searchParams;
  assert.equal(params.get('scope'), 'rondo:pilot:read');
  assert.equal(params.get('action'), 'rondo_mobile_pilot_authorize');
  assert.equal(params.get('redirect_uri'), 'https://rondo.svawc.nl/rondo-app/callback');
  const callback = `${auth.CALLBACK}?state=${pending.state}&code=${'c'.repeat(43)}`;
  assert.equal(auth.readCallback(callback, pending).client_id, 'rondo-awc-pilot');
  for (const invalid of [callback.replace('https:', 'http:'), callback.replace('rondo.svawc.nl', 'other.example.test'), callback.replace('/rondo-app/callback', '/other'), callback.replace(auth.CALLBACK, 'club.rondo.spike://oauth/callback')]) assert.throws(() => auth.readCallback(invalid, pending));
});

test('compiled pilot device session refuses writes before sending any request', async () => {
  const { DeviceSession } = await pilotModule('device-session.mjs');
  let calls = 0;
  const session = new DeviceSession({ vault: {}, clubs: [], request: async () => { calls++; } });
  session.session = { scope: 'rondo:pilot:read', token: 'a'.repeat(43), expiresAt: Date.now() + 300000 };
  await assert.rejects(session.changeShift(1, 'signup', false));
  await assert.rejects(session.changeProfile('phones', { mobile_1: '0612345678' }));
  assert.equal(calls, 0);
});
