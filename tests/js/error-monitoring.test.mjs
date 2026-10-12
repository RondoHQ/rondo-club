import test from 'node:test';
import assert from 'node:assert/strict';
import { sanitizeErrorEvent } from '../../src/utils/error-event.js';

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
