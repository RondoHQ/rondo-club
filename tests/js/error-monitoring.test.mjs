import test from 'node:test';
import assert from 'node:assert/strict';
import { markChunkLoadError, sanitizeErrorEvent } from '../../src/utils/error-event.js';

test('outgoing browser errors retain stack locations but remove personal data and tokens', () => {
  const clean = sanitizeErrorEvent({
    event_id: 'test', release: 'rondo-club@test',
    request: { url: 'https://rondo.test/people/123', headers: { Authorization: 'SECRET' } },
    user: { email: 'PRIVATE' }, breadcrumbs: [{ message: 'PRIVATE' }], extra: { data: 'PRIVATE' },
    exception: { values: [{ type: 'TypeError', value: 'PRIVATE', mechanism: { type: 'generic', handled: false, data: 'PRIVATE' },
      stacktrace: { frames: [{ filename: 'https://rondo.test/assets/app.js?token=SECRET#PRIVATE', function: 'render', lineno: 42, colno: 8, vars: { member: 'PRIVATE' } }] } }] },
  });
  assert.doesNotMatch(JSON.stringify(clean), /PRIVATE|SECRET|people\/123/);
  assert.equal(clean.release, 'rondo-club@test');
  assert.deepEqual(clean.exception.values[0].stacktrace.frames[0], {
    filename: 'https://rondo.test/assets/app.js', function: 'render', lineno: 42, colno: 8, in_app: undefined,
  });
});

const eventFor = (value, mechanism = 'auto.browser.global_handlers.onunhandledrejection') => ({
  exception: { values: [{ type: 'TypeError', value, mechanism: { type: mechanism, handled: false } }] },
});

test('unknown React and rejection errors remain identifiable without inventing a cause', () => {
  for (const [mechanism, source] of [['auto.function.react.error_handler', 'react'], ['auto.browser.global_handlers.onunhandledrejection', 'unhandled_rejection']]) {
    const clean = sanitizeErrorEvent(eventFor('PRIVATE UNKNOWN ERROR', mechanism), {}, { pathname: '/people/123?search=PRIVATE#SECRET', online: false });
    assert.deepEqual(clean.tags, { error_code: 'unclassified_error', error_source: source, page: '/people/:id', online: 'false' });
    assert.equal(clean.exception.values[0].value, 'Rondo application error');
    assert.doesNotMatch(JSON.stringify(clean), /PRIVATE|SECRET|123/);
  }
});

test('browser and React messages become fixed diagnostic codes without exposing their contents', () => {
  const samples = [
    ['Failed to fetch dynamically imported module: https://site.test/PRIVATE.js?secret=SECRET', 'chunk_load_failed'],
    ['Importing a module script failed.', 'chunk_load_failed'],
    ['error loading dynamically imported module: PRIVATE', 'chunk_load_failed'],
    ['Loading chunk PRIVATE failed.', 'chunk_load_failed'],
    ['Unable to preload CSS for PRIVATE', 'chunk_load_failed'],
    ['Failed to fetch', 'network_request_failed'],
    ['Load failed', 'network_request_failed'],
    ['Network Error', 'network_request_failed'],
    ['NetworkError when attempting to fetch resource.', 'network_request_failed'],
    ['timeout of 45000ms exceeded', 'request_timeout'],
    ["Cannot read properties of undefined (reading 'PRIVATE')", 'missing_value'],
    ["Cannot read properties of null (reading 'PRIVATE')", 'missing_value'],
    ["undefined is not an object (evaluating 'PRIVATE')", 'missing_value'],
    ['PRIVATE is not a function', 'not_callable'],
    ['Minified React error #310; visit https://react.dev/errors/310?args[]=PRIVATE', 'react_render_error'],
  ];
  for (const [message, code] of samples) {
    const clean = sanitizeErrorEvent(eventFor(message));
    assert.equal(clean.tags.error_code, code, message);
    assert.doesNotMatch(JSON.stringify(clean), /PRIVATE|SECRET|site\.test|react\.dev/);
  }
});

test('Vite identifies ambiguous lazy-load errors without reporting duplicates or changing the error', () => {
  const error = new TypeError('Load failed');
  const event = eventFor(error.message, 'auto.function.react.error_handler');
  assert.equal(sanitizeErrorEvent(event, { originalException: error }).tags.error_code, 'network_request_failed');
  markChunkLoadError(error);
  assert.equal(sanitizeErrorEvent(event, { originalException: error }).tags.error_code, 'chunk_load_failed');
  assert.equal(error.message, 'Load failed');
  assert.equal(sanitizeErrorEvent(event, { originalException: new TypeError('Load failed') }).tags.error_code, 'network_request_failed');
  for (const value of [null, undefined, 'PRIVATE', 42]) markChunkLoadError(value);
});

test('HTTP errors retain only bounded status and timeout categories from the SDK hint', () => {
  for (const [status, code] of [[403, 'http_client_error'], [503, 'http_server_error']]) {
    const clean = sanitizeErrorEvent(eventFor('PRIVATE'), { originalException: { response: { status, data: 'SECRET' }, config: { url: '/people/123', headers: { Authorization: 'SECRET' } } } });
    assert.equal(clean.tags.error_code, code);
    assert.equal(clean.tags.http_status, String(status));
    assert.doesNotMatch(JSON.stringify(clean), /PRIVATE|SECRET|people|123/);
  }
  for (const status of [200, 600, '500', 'PRIVATE', NaN]) {
    assert.equal(sanitizeErrorEvent(eventFor('PRIVATE'), { originalException: { response: { status } } }).tags.http_status, undefined);
  }
  assert.equal(sanitizeErrorEvent(eventFor('PRIVATE'), { originalException: { code: 'ETIMEDOUT' } }).tags.error_code, 'request_timeout');
});

test('caller-supplied tags, URLs, contexts and fingerprints cannot bypass the allowlist', () => {
  const event = { ...eventFor('PRIVATE'), tags: { page: '/people/123', error_code: 'SECRET' }, contexts: { diagnostics: { page: 'PRIVATE' } }, fingerprint: ['PRIVATE'], request: { url: 'https://PRIVATE/SECRET' } };
  const clean = sanitizeErrorEvent(event, { originalException: 'PRIVATE', data: 'SECRET' }, { pathname: '/people/PRIVATE%40example.com?search=SECRET#123', online: true, anything: 'PRIVATE' });
  assert.deepEqual(clean.tags, { error_code: 'unclassified_error', error_source: 'unhandled_rejection', page: '/people/:id', online: 'true' });
  assert.doesNotMatch(JSON.stringify(clean), /PRIVATE|SECRET|123|example\.com/);
  assert.equal(sanitizeErrorEvent({}).tags.page, '/:unknown');
  assert.equal(sanitizeErrorEvent({}).tags.online, 'unknown');
});

test('a chained React component stack does not override the underlying error category', () => {
  const event = eventFor('Failed to fetch', 'auto.function.react.error_handler');
  event.exception.values.unshift({ type: 'React ErrorBoundary TypeError', value: 'PRIVATE', mechanism: { type: 'chained', handled: true } });
  const clean = sanitizeErrorEvent(event, { originalException: new TypeError('Failed to fetch') });
  assert.equal(clean.tags.error_code, 'network_request_failed');
  assert.equal(clean.tags.error_source, 'react');
  assert.equal(clean.exception.values.length, 2);
});
