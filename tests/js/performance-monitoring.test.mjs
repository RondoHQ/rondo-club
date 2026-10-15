import { test } from 'node:test';
import assert from 'node:assert/strict';
import { safePath, sanitizeTransaction } from '../../src/utils/performance-monitoring.js';

test('performance payload retains timings and IDs but strips private data at every level', () => {
  const trace = { trace_id: 'a'.repeat(32), span_id: 'b'.repeat(16), op: 'http.client', data: { token: 'PRIVATE' } };
  const event = sanitizeTransaction({
    event_id: 'c'.repeat(32), transaction: '/people/PRIVATE?token=PRIVATE#PRIVATE',
    start_timestamp: 10, timestamp: 12, release: 'test@1',
    user: { email: 'PRIVATE' }, request: { headers: { cookie: 'PRIVATE' } },
    extra: { secret: 'PRIVATE' }, breadcrumbs: ['PRIVATE'], tags: { name: 'PRIVATE' },
    contexts: { trace, browser: { name: 'PRIVATE' } },
    spans: [{ ...trace, description: 'GET https://example.invalid/api/contact?token=PRIVATE',
      start_timestamp: 10, timestamp: 11, data: { body: 'PRIVATE' }, tags: { name: 'PRIVATE' } }],
  });
  assert.doesNotMatch(JSON.stringify(event), /PRIVATE|example.invalid|cookie|body/);
  assert.equal(event.timestamp - event.start_timestamp, 2);
  assert.equal(event.contexts.trace.trace_id, trace.trace_id);
  assert.equal(event.spans[0].timestamp - event.spans[0].start_timestamp, 1);
  assert.equal(safePath('/api/contact?secret=PRIVATE'), '/api/contact');
  assert.equal(safePath('/PRIVATE/PRIVATE'), '/:id/:id');
});

test('span count is bounded and arbitrary operation labels do not escape', () => {
  const event = sanitizeTransaction({ spans: Array.from({ length: 150 }, () => ({ op: 'PRIVATE', status: 'PRIVATE', description: 'PRIVATE' })) });
  assert.equal(event.spans.length, 100);
  assert.doesNotMatch(JSON.stringify(event), /PRIVATE/);
});
