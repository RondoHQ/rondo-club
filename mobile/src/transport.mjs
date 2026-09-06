import { Capacitor, CapacitorHttp } from '@capacitor/core';
import { API_PATH } from './auth.mjs';

export async function request(club, path, { method = 'GET', data, token } = {}) {
  const url = `${club.url}${API_PATH}${path}`;
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  let result;
  try {
    if (Capacitor.isNativePlatform()) {
      result = await CapacitorHttp.request({ url, method, headers, data, responseType: 'json', disableRedirects: true, connectTimeout: 10000, readTimeout: 15000 });
    } else {
      const response = await fetch(url, { method, headers, body: data ? JSON.stringify(data) : undefined, credentials: 'omit', redirect: 'error', signal: AbortSignal.timeout(15000) });
      result = { status: response.status, data: await response.json() };
    }
  } catch {
    throw new Error('Geen verbinding met je club. Controleer je internetverbinding en probeer opnieuw.');
  }
  if (result.status < 200 || result.status >= 300) {
    const error = new Error(result.status === 401 ? 'Je sessie is verlopen. Log opnieuw in.' : 'De club kan deze aanvraag niet verwerken. Probeer opnieuw.');
    error.status = result.status;
    error.code = result.data?.code;
    if (error.code === 'no_person') error.message = 'Je account is nog niet gekoppeld aan een lid. Neem contact op met je club.';
    if (error.code === 'pass_forbidden' || result.status === 403) error.message = 'Je hebt geen toegang tot deze gegevens. Neem contact op met je club.';
    throw error;
  }
  return result.data;
}
