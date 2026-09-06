export const CLIENT_ID = 'rondo-mobile-spike';
export const CALLBACK = 'club.rondo.spike://oauth/callback';
export const API_PATH = '/wp-json/rondo-mobile-spike/v1';
export const LOGIN_TTL = 10 * 60 * 1000;

const base64url = (bytes) => btoa(String.fromCharCode(...bytes)).replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', '');
const randomValue = () => base64url(crypto.getRandomValues(new Uint8Array(32)));

// Club endpoints come only from the build's reviewed directory, never a callback or QR URL.
export function validateClubs(input) {
  if (!Array.isArray(input)) throw new Error('Ongeldige clublijst.');
  const ids = new Set();
  return input.map(({ id, name, url, logoUrl }) => {
    const parsed = new URL(url);
    if (!id || !name || ids.has(id) || parsed.protocol !== 'https:' || parsed.username || parsed.password || parsed.search || parsed.hash || parsed.pathname !== '/') {
      throw new Error('De clublijst bevat een ongeldige of dubbele club.');
    }
    let logo;
    if (logoUrl) {
      logo = new URL(logoUrl);
      if (logo.protocol !== 'https:' || logo.username || logo.password || logo.hash) throw new Error('Ongeldig clublogo in de clublijst.');
    }
    ids.add(id);
    return Object.freeze({ id, name, url: parsed.origin, ...(logo ? { logoUrl: logo.href } : {}) });
  });
}

export async function beginLogin(club, now = Date.now()) {
  const verifier = randomValue();
  const challenge = base64url(new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier))));
  const pending = { club, verifier, state: randomValue(), createdAt: now };
  const url = new URL('/wp-admin/admin-post.php', club.url);
  url.search = new URLSearchParams({ action: 'rondo_mobile_spike_authorize', client_id: CLIENT_ID, redirect_uri: CALLBACK, response_type: 'code', scope: 'rondo:spike:read', state: pending.state, code_challenge: challenge, code_challenge_method: 'S256' });
  return { pending, url: url.href };
}

export function readCallback(value, pending, now = Date.now()) {
  const url = new URL(value);
  if (`${url.protocol}//${url.host}${url.pathname}` !== CALLBACK || url.username || url.password || url.hash) throw new Error('Onbekende terugkeerlink.');
  if (!pending || now - pending.createdAt > LOGIN_TTL || now < pending.createdAt) throw new Error('De aanmelding is verlopen. Log opnieuw in.');
  const params = url.searchParams;
  for (const key of ['state', 'code', 'error']) if (params.getAll(key).length > 1) throw new Error('Dubbele aanmeldparameters.');
  if (params.get('state') !== pending.state) throw new Error('Deze aanmelding hoort niet bij deze app-sessie.');
  if (params.has('error')) throw new Error('Aanmelding geannuleerd.');
  const code = params.get('code');
  if (!/^[A-Za-z0-9_-]{43}$/.test(code || '')) throw new Error('De aanmeldcode ontbreekt of is ongeldig.');
  return { grant_type: 'authorization_code', client_id: CLIENT_ID, redirect_uri: CALLBACK, code, code_verifier: pending.verifier };
}

// Invalid callbacks do not consume someone else's pending login.
export class LoginSession {
  pending = null;
  session = null;
  generation = 0;

  clear() {
    this.generation += 1;
    this.pending = null;
    this.session = null;
  }

  async start(club) {
    this.clear();
    const generation = this.generation;
    const login = await beginLogin(club);
    if (generation !== this.generation) throw new Error('Aanmelding geannuleerd.');
    this.pending = login.pending;
    return login.url;
  }

  async finish(url, exchange) {
    const payload = readCallback(url, this.pending);
    const pending = this.pending;
    const generation = this.generation;
    this.pending = null; // Consume before awaiting: duplicate native events cannot exchange twice.
    const token = await exchange(pending.club, payload);
    if (generation !== this.generation) throw new Error('Aanmelding geannuleerd.');
    if (token.token_type !== 'Bearer' || !/^[A-Za-z0-9_-]{43}$/.test(token.access_token || '') || !Number.isFinite(token.expires_in) || token.expires_in <= 0 || token.expires_in > 300) throw new Error('Ongeldig sessieantwoord.');
    this.session = { club: pending.club, token: token.access_token, expiresAt: Date.now() + token.expires_in * 1000 };
    return this.session;
  }
}
