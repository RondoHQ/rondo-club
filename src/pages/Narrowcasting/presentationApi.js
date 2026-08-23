const PRESENTATION_ROOT = '/rondo/v1/narrowcasting/presentation';

function apiUrl(path) {
  const root = (window.rondoConfig?.apiUrl || '/wp-json/').replace(/\/$/, '');
  return `${root}${path}`;
}

async function request(path, { method = 'GET', token = '', deviceToken = '', body, signal } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (window.rondoConfig?.nonce) headers['X-WP-Nonce'] = window.rondoConfig.nonce;
  if (token) headers['X-Rondo-Presentation-Token'] = token;
  if (deviceToken) headers['X-Rondo-Device-Token'] = deviceToken;

  const response = await fetch(apiUrl(path), {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
    cache: 'no-store',
    signal,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(data.message || `HTTP ${response.status}`);
    error.status = response.status;
    error.code = data.code || '';
    throw error;
  }
  return data;
}

export function createPresentationSession(deviceToken, signal) {
  return request('/rondo/v1/narrowcasting/devices/me/presentation/session', {
    method: 'POST',
    deviceToken,
    signal,
  });
}

export function joinPresentationSession(code, signal) {
  return request(`${PRESENTATION_ROOT}/join`, {
    method: 'POST',
    body: { code },
    signal,
  });
}

export function getPresentationSignal(sessionId, token, signal) {
  return request(`${PRESENTATION_ROOT}/sessions/${encodeURIComponent(sessionId)}/signal`, {
    token,
    signal,
  });
}

export function sendPresentationSignal(sessionId, token, snapshot, signal) {
  return request(`${PRESENTATION_ROOT}/sessions/${encodeURIComponent(sessionId)}/signal`, {
    method: 'POST',
    token,
    body: snapshot,
    signal,
  });
}

export function emptySignal() {
  return { description: null, candidates: [], hangup: false };
}

export const PRESENTATION_POLL_INTERVAL_MS = 1000;
