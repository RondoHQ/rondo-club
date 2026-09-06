import { CLIENT_ID, LoginSession, readCallback } from './auth.mjs';

const validToken = (value) => /^[A-Za-z0-9_-]{43}$/.test(value || '');
const empty = () => ({ version: 1, active: null, revocations: [] });
const expired = () => Object.assign(new Error('Je aanmelding is verlopen. Log opnieuw in.'), { status: 401 });

// All vault mutations and rotations are serialized. Logout invalidates in-flight reads immediately.
export class DeviceSession extends LoginSession {
  state = empty();
  queue = Promise.resolve();
  refreshing = null;
  constructor({ vault, request, clubs, now = Date.now }) {
    super();
    Object.assign(this, { vault, request, clubs, now });
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
    if (next.active || next.revocations.length) await this.vault.write({ value: JSON.stringify(next) });
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
    if (pair.token_type !== 'Bearer' || !validToken(pair.access_token) || !validToken(pair.refresh_token) || !Number.isFinite(pair.expires_in) || pair.expires_in <= 0 || pair.expires_in > 300 || !Number.isFinite(pair.refresh_expires_at) || pair.refresh_expires_at * 1000 <= this.now() || pair.refresh_expires_at * 1000 > this.now() + 31 * 86400000) throw new Error('Ongeldig sessieantwoord.');
    const active = { clubId: club.id, clubUrl: club.url, refreshToken: pair.refresh_token, expiresAt: pair.refresh_expires_at * 1000 };
    try {
      await this.save({ ...this.state, active });
    } catch (error) {
      this.session = null;
      await this.request(club, '/revoke', { method: 'POST', data: { refresh_token: pair.refresh_token } }).catch(() => {});
      throw error;
    }
    if (generation !== this.generation) throw expired();
    this.session = { club, token: pair.access_token, expiresAt: this.now() + pair.expires_in * 1000 };
    return this.session;
  }
  async finish(url) {
    const data = readCallback(url, this.pending);
    const club = this.pending.club;
    const generation = this.generation;
    this.pending = null;
    return this.exclusive(async () => {
      if (generation !== this.generation) throw expired();
      const pair = await this.request(club, '/token', { method: 'POST', data });
      // Persist even if logout started during exchange: the queued logout then revokes this family.
      return this.accept(club, pair, generation);
    });
  }
  async restore() {
    return this.exclusive(async () => {
      const { value } = await this.vault.read();
      const stored = value ? JSON.parse(value) : empty();
      if (stored.version !== 1 || !Array.isArray(stored.revocations)) throw new Error('De opgeslagen aanmelding kan niet worden gelezen. Log uit om opnieuw te beginnen.');
      this.state = stored;
      await this.flush();
      if (!this.validRecord(this.state.active)) {
        await this.save({ ...this.state, active: null });
        return null;
      }
      return this.rotate(this.generation);
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
  async logout() {
    this.clear();
    return this.exclusive(async () => {
      // If startup could not read the vault, explicit logout can still erase it.
      const revocations = [...this.state.revocations, ...(this.state.active ? [this.state.active] : [])].filter((record) => this.validRecord(record));
      await this.save({ version: 1, active: null, revocations });
      // Durably signed out before attempting a network request; offline revocations survive restart.
      await this.flush();
    });
  }
}
