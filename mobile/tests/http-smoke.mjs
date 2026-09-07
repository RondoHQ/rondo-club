// Optional local-only HTTP integration check. Requires disposable WordPress and fake accounts.
import assert from 'node:assert/strict';
import { beginLogin, readCallback, API_PATH } from '../src/auth.mjs';
const origin = process.env.SPIKE_HTTP_ORIGIN;
assert.ok(origin && ['127.0.0.1', 'localhost'].includes(new URL(origin).hostname), 'Only a loopback WordPress fixture is allowed.');
assert.ok(process.env.SPIKE_TEST_USER && process.env.SPIKE_TEST_PASSWORD, 'Set fixture credentials in the environment.');
const cookies = new Map();
async function request(path, options = {}) {
  const response = await fetch(new URL(path, origin), { ...options, redirect: 'manual', headers: { ...options.headers, Cookie: [...cookies].map(([key, value]) => `${key}=${value}`).join('; ') } });
  for (const cookie of response.headers.getSetCookie()) {
    const [pair] = cookie.split(';');
    const index = pair.indexOf('=');
    cookies.set(pair.slice(0, index), pair.slice(index + 1));
  }
  return response;
}
const club = { id: 'local-fixture', name: 'Local fixture', url: origin };
const { pending, url } = await beginLogin(club);
assert.equal((await request(`${API_PATH}/config`)).status, 200);
const unauthenticated = await request(url);
assert.equal(unauthenticated.status, 302);
assert.ok(unauthenticated.headers.get('location').includes('wp-login.php'));
await request('/wp-login.php');
const login = await request('/wp-login.php', { method: 'POST', body: new URLSearchParams({ log: process.env.SPIKE_TEST_USER, pwd: process.env.SPIKE_TEST_PASSWORD, 'wp-submit': 'Log In', redirect_to: url, testcookie: '1' }) });
assert.equal(login.status, 302);
const consent = await request(url);
assert.equal(consent.status, 200);
const html = await consent.text();
assert.ok(html.includes('Rondo Proef verbinden'));
const nonce = html.match(/name="_wpnonce" value="([^"]+)"/)?.[1];
assert.ok(nonce, 'Consent includes a nonce');
const params = new URL(url).searchParams;
const deniedCsrf = await request('/wp-admin/admin-post.php', { method: 'POST', body: new URLSearchParams([...params, ['decision', 'approve']]) });
assert.equal(deniedCsrf.status, 403);
const approved = await request('/wp-admin/admin-post.php', { method: 'POST', body: new URLSearchParams([...params, ['_wpnonce', nonce], ['decision', 'approve']]) });
assert.equal(approved.status, 302);
const payload = readCallback(approved.headers.get('location'), pending);
// App requests have no browser cookies. This is a real HTTP permission-boundary check.
cookies.clear();
const exchange = await request(`${API_PATH}/token`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
assert.equal(exchange.status, 200);
const token = (await exchange.json()).access_token;
assert.ok(token);
const me = await request(`${API_PATH}/read?resource=me`, { headers: { Authorization: `Bearer ${token}` } });
assert.equal(me.status, 200);
const profile = await me.json();
assert.equal(profile.is_admin, false);
assert.equal(profile.name, 'Proeflid');
const replay = await request(`${API_PATH}/token`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
assert.equal(replay.status, 400);
const revoke = await request(`${API_PATH}/revoke`, { method: 'POST', headers: { Authorization: `Bearer ${token}` } });
assert.equal(revoke.status, 200);
assert.equal((await request(`${API_PATH}/read?resource=me`, { headers: { Authorization: `Bearer ${token}` } })).status, 401);
console.log('PASS: real HTTP login, nonce-protected consent, code exchange, cookie-free own-profile read, replay rejection and revocation.');
