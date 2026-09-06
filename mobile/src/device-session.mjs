import { CLIENT_ID, LOGIN_TTL, MEMBER_SCOPE, READ_SCOPE, LoginSession, authorizationUrl, beginLogin, readCallback } from './auth.mjs';

const validToken = (value) => /^[A-Za-z0-9_-]{43}$/.test(value || '');
const empty = () => ({ version: 1, active: null, pending: null, revocations: [] });
const expired = () => Object.assign(new Error('Je aanmelding is verlopen. Log opnieuw in.'), { status: 401 });

// All vault mutations and rotations are serialized. Logout invalidates in-flight reads immediately.
export class DeviceSession extends LoginSession {
  state = empty();
  queue = Promise.resolve();
  refreshing = null;
  callbackInFlight = null;
  writing = false;
  constructor({ vault, request, clubs, now = Date.now }) {
    super();
    Object.assign(this, { vault, request, clubs, now });
  }
  clear() {
    super.clear();
    this.callbackInFlight = null;
  }
  async start(club) {
    if (!this.clubs.some((entry) => entry.id === club.id && entry.url === club.url)) throw new Error('Onbekende club.');
    this.clear();
    const generation = this.generation;
    return this.exclusive(async () => {
      if (this.state.active) throw new Error('Log eerst uit voordat je een nieuwe aanmelding start.');
      const login = await beginLogin(club, this.now());
      if (generation !== this.generation) throw expired();
      const { verifier, state, createdAt, scope } = login.pending;
      await this.save({ ...this.state, pending: { clubId: club.id, clubUrl: club.url, verifier, state, createdAt, scope } });
      if (generation !== this.generation) throw expired();
      this.pending = login.pending;
      return login.url;
    });
  }
  pendingFrom(record) {
    const club = this.clubFor(record);
    if (!club || ![undefined, READ_SCOPE, MEMBER_SCOPE].includes(record.scope) || !validToken(record.verifier) || !validToken(record.state) || !Number.isFinite(record.createdAt) || record.createdAt > this.now() || this.now() - record.createdAt >= LOGIN_TTL) return null;
    return { club, verifier: record.verifier, state: record.state, createdAt: record.createdAt, scope: record.scope || READ_SCOPE };
  }
  async resumeLogin() {
    const generation = this.generation;
    return this.exclusive(async () => {
      const pending = this.pendingFrom(this.state.pending);
      if (!pending || generation !== this.generation) {
        this.pending = null;
        await this.save({ ...this.state, pending: null });
        throw expired();
      }
      const url = await authorizationUrl(pending);
      if (generation !== this.generation) throw expired();
      return url;
    });
  }
  exclusive(operation) {
    const next = this.queue.then(operation);
    this.queue = next.catch(() => {});
    return next;
  }
  clubFor(record) {
    return this.clubs.find((club) => club.id === record?.clubId && club.url === record?.clubUrl);
  }
  validRecord(record) {
    return this.clubFor(record) && validToken(record.refreshToken) && Number.isFinite(record.expiresAt) && record.expiresAt > this.now();
  }
  async save(next) {
    if (next.active || next.pending || next.revocations.length) await this.vault.write({ value: JSON.stringify(next) });
    else await this.vault.clear();
    this.state = next;
  }
  async flush() {
    const remaining = [];
    for (const record of this.state.revocations) {
      if (!this.validRecord(record)) continue;
      try { await this.request(this.clubFor(record), '/revoke', { method: 'POST', data: { refresh_token: record.refreshToken } }); }
      catch { remaining.push(record); }
    }
    if (remaining.length !== this.state.revocations.length) await this.save({ ...this.state, revocations: remaining });
  }
  async accept(club, pair, generation) {
    if (![undefined, READ_SCOPE, MEMBER_SCOPE].includes(pair.scope) || pair.token_type !== 'Bearer' || !validToken(pair.access_token) || !validToken(pair.refresh_token) || !Number.isFinite(pair.expires_in) || pair.expires_in <= 0 || pair.expires_in > 300 || !Number.isFinite(pair.refresh_expires_at) || pair.refresh_expires_at * 1000 <= this.now() || pair.refresh_expires_at * 1000 > this.now() + 31 * 86400000) throw new Error('Ongeldig sessieantwoord.');
    const active = { clubId: club.id, clubUrl: club.url, refreshToken: pair.refresh_token, expiresAt: pair.refresh_expires_at * 1000 };
    try {
      await this.save({ ...this.state, active, pending: null });
    } catch (error) {
      this.session = null;
      await this.request(club, '/revoke', { method: 'POST', data: { refresh_token: pair.refresh_token } }).catch(() => {});
      throw error;
    }
    if (generation !== this.generation) throw expired();
    this.session = { club, scope: pair.scope || READ_SCOPE, token: pair.access_token, expiresAt: this.now() + pair.expires_in * 1000 };
    return this.session;
  }
  async finish(url) {
    if (this.callbackInFlight?.url === url && this.callbackInFlight.generation === this.generation) return this.callbackInFlight.promise;
    let data;
    try { data = readCallback(url, this.pending, this.now()); }
    catch (error) {
      if (error.code === 'login_cancelled') await this.logout();
      throw error;
    }
    const club = this.pending.club;
    const generation = this.generation;
    this.pending = null;
    const promise = this.exclusive(async () => {
      if (generation !== this.generation) throw expired();
      // Consume durably before exchange, so killing the process cannot replay a callback.
      await this.save({ ...this.state, pending: null });
      const pair = await this.request(club, '/token', { method: 'POST', data });
      // A queued logout revokes any family issued while it was in flight.
      return this.accept(club, pair, generation);
    });
    this.callbackInFlight = { url, generation, promise };
    return promise;
  }
  async restore() {
    const generation = this.generation;
    return this.exclusive(async () => {
      const { value } = await this.vault.read();
      const stored = value ? JSON.parse(value) : empty();
      if (stored.version !== 1 || !Array.isArray(stored.revocations)) throw new Error('De opgeslagen aanmelding kan niet worden gelezen. Log uit om opnieuw te beginnen.');
      this.state = { ...stored, pending: stored.pending || null };
      if (generation !== this.generation) throw expired();
      this.pending = !this.state.active ? this.pendingFrom(this.state.pending) : null;
      if (this.state.pending && !this.pending) await this.save({ ...this.state, pending: null });
      await this.flush();
      if (!this.validRecord(this.state.active)) {
        await this.save({ ...this.state, active: null });
        return null;
      }
      return this.rotate(generation);
    });
  }
  async rotate(generation) {
    const active = this.state.active;
    if (!this.validRecord(active)) throw expired();
    const club = this.session?.club || this.clubFor(active);
    try {
      const pair = await this.request(club, '/token', { method: 'POST', data: { grant_type: 'refresh_token', client_id: CLIENT_ID, refresh_token: active.refreshToken } });
      return await this.accept(club, pair, generation);
    } catch (error) {
      if (generation === this.generation && (error.code === 'invalid_grant' || error.status === 401)) {
        this.session = null;
        await this.save({ ...this.state, active: null });
        throw expired();
      }
      throw error;
    }
  }
  async token(force = false) {
    if (!force && this.session?.expiresAt > this.now() + 30000) return this.session;
    if (!this.refreshing) {
      const generation = this.generation;
      this.refreshing = this.exclusive(() => {
        if (generation !== this.generation) throw expired();
        return this.rotate(generation);
      }).finally(() => { this.refreshing = null; });
    }
    return this.refreshing;
  }
  async read(path) {
    const generation = this.generation;
    let session = await this.token();
    let result;
    try { result = await this.request(session.club, path, { token: session.token }); }
    catch (error) {
      if (error.status !== 401 || generation !== this.generation) throw error;
      // Another query may already have rotated the rejected access token.
      session = await this.token(this.session?.token === session.token);
      result = await this.request(session.club, path, { token: session.token });
    }
    if (generation !== this.generation) throw expired();
    return result;
  }
  async changeShift(shiftId, action, forceOverlap = false) {
    if (this.writing) throw new Error('Je vorige wijziging wordt nog verwerkt.');
    this.writing = true;
    const generation = this.generation;
    try {
      const session = await this.token();
      if (generation !== this.generation) throw expired();
      if (session.scope !== MEMBER_SCOPE) throw Object.assign(new Error('Log opnieuw in om je diensten via de app te wijzigen.'), { code: 'consent_required', status: 403 });
      // Never retry a POST automatically: a lost response may already have changed the signup.
      const result = await this.request(session.club, '/shift', { method: 'POST', token: session.token, data: { shift_id: shiftId, action, force_overlap: forceOverlap } });
      if (generation !== this.generation) throw expired();
      return result;
    } finally { this.writing = false; }
  }

  async logout() {
    this.clear();
    return this.exclusive(async () => {
      this.pending = null;
      // If startup could not read the vault, explicit logout can still erase it.
      const revocations = [...this.state.revocations, ...(this.state.active ? [this.state.active] : [])].filter((record) => this.validRecord(record));
      await this.save({ version: 1, active: null, pending: null, revocations });
      // Durably signed out before attempting a network request; offline revocations survive restart.
      await this.flush();
    });
  }
}
