import test from 'node:test';
import assert from 'node:assert/strict';
import { cancelShiftDay, retryableCancellations } from '../../src/utils/cancelShiftDay.js';

test('day cancellation continues after a failure and retries only failed requests or emails', async () => {
  const shifts = [1, 2, 3, 4].map((id) => ({ id }));
  const calls = [];
  const progress = [];
  const results = await cancelShiftDay(shifts, 'Velden afgekeurd', async (id, data) => {
    calls.push([id, data]);
    if (id === 2) throw { response: { data: { message: 'Dienst is begonnen' } } };
    return { data: { credit_awarded: true, notifications: { failed: id === 3 ? 1 : 0, no_email: id === 4 ? 1 : 0 } } };
  }, (done) => progress.push(done.length));
  assert.deepEqual(calls.map(([id]) => id), [1, 2, 3, 4]);
  assert.ok(calls.every(([, data]) => data.reason === 'Velden afgekeurd'));
  assert.deepEqual(progress, [1, 2, 3, 4]);
  assert.equal(results[1].error, 'Dienst is begonnen');
  assert.deepEqual(retryableCancellations(results).map(({ id }) => id), [2, 3]);
});

test('an empty day sends no cancellation requests', async () => {
  assert.deepEqual(await cancelShiftDay([], '', () => assert.fail('unexpected cancellation')), []);
});
