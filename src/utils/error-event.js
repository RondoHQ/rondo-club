import { getInvalidResponseDiagnostics } from '../api/dashboard-response.js';
import { safePath } from './performance-monitoring.js';

// Vite knows whether a rejection came from a lazy asset load, even when the
// browser only says "Load failed". Remember the error, never its URL or message.
const chunkLoadErrors = new WeakSet();
export function markChunkLoadError(error) {
  if (error !== null && (typeof error === 'object' || typeof error === 'function')) {
    chunkLoadErrors.add(error);
  }
}

function diagnosticTags(event, hint, runtime) {
  const values = event.exception?.values || [];
  const original = hint?.originalException;
  const message = typeof original?.message === 'string'
    ? original.message : values.at(-1)?.value;
  const invalidResponse = getInvalidResponseDiagnostics(original);
  const status = original?.response?.status;
  const httpStatus = Number.isInteger(status) && status >= 400 && status <= 599 ? status : undefined;
  const mechanisms = values.map(value => value.mechanism?.type);
  let code = 'unclassified_error';

  if (invalidResponse) {
    code = 'invalid_api_response';
  } else if (chunkLoadErrors.has(original) || /Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module|Loading (?:CSS )?chunk .+ failed|Unable to preload CSS/i.test(message || '')) {
    code = 'chunk_load_failed';
  } else if (httpStatus) {
    code = httpStatus >= 500 ? 'http_server_error' : 'http_client_error';
  } else if (['ECONNABORTED', 'ETIMEDOUT'].includes(original?.code) || /^(?:timeout of \d+ms exceeded|The operation timed out)/i.test(message || '')) {
    code = 'request_timeout';
  } else if (/^(?:Failed to fetch|Load failed|NetworkError when attempting to fetch resource\.?|Network Error)$/i.test(message || '')) {
    code = 'network_request_failed';
  } else if (/^(?:Cannot read (?:properties|property) of (?:undefined|null)|(?:undefined|null) is not an object)/.test(message || '')) {
    code = 'missing_value';
  } else if (/is not a function(?:\.|$)/.test(message || '')) {
    code = 'not_callable';
  } else if (/^Minified React error #\d+;/.test(message || '')) {
    code = 'react_render_error';
  }

  const source = mechanisms.some(type => type?.startsWith('auto.function.react.')) ? 'react'
    : mechanisms.includes('auto.browser.global_handlers.onunhandledrejection') ? 'unhandled_rejection'
    : mechanisms.some(type => type?.startsWith('auto.browser.')) ? 'browser' : 'manual';

  // Runtime values come from this page, never from event tags or an error URL.
  return {
    error_code: code,
    error_source: source,
    page: typeof runtime?.pathname === 'string' ? safePath(runtime.pathname) : '/:unknown',
    online: typeof runtime?.online === 'boolean' ? String(runtime.online) : 'unknown',
    ...(httpStatus ? { http_status: String(httpStatus) } : {}),
    ...invalidResponse,
  };
}

// Only diagnostic metadata leaves the browser; member data stays in Rondo.
export function sanitizeErrorEvent(event, hint = {}, runtime = {}) {
  return {
    event_id: event.event_id, timestamp: event.timestamp, platform: event.platform,
    level: event.level, release: event.release, environment: event.environment,
    sdk: event.sdk, debug_meta: event.debug_meta,
    tags: diagnosticTags(event, hint, runtime),
    exception: event.exception && { values: event.exception.values.map(value => ({
      type: value.type, value: 'Rondo application error',
      mechanism: value.mechanism && { type: value.mechanism.type, handled: value.mechanism.handled },
      stacktrace: value.stacktrace && { frames: value.stacktrace.frames.map(frame => ({
        filename: frame.filename?.split(/[?#]/)[0], function: frame.function,
        lineno: frame.lineno, colno: frame.colno, in_app: frame.in_app,
      })) },
    })) },
  };
}
