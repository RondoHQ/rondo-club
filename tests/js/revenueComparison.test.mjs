import test from 'node:test';
import assert from 'node:assert/strict';
import { alignRevenuePeriods, periodDays, previousYear, revenueDifference, revenueTotal } from '../../src/utils/revenueComparison.js';
const row = (periode, omzet_totaal) => ({ periode, omzet_totaal });

test('previous year clamps leap day and keeps full month endpoints', () => {
  assert.equal(previousYear('2024-02-29'), '2023-02-28');
  assert.equal(previousYear('2026-09-30'), '2025-09-30');
  assert.equal(previousYear(''), '');
});
test('calendar duration does not change across DST', () => {
  assert.equal(periodDays('2026-03-28', '2026-03-30'), 3);
  assert.equal(periodDays('2026-10-24', '2026-10-26'), 3);
});
test('missing days do not shift comparison or become zero', () => {
  const slots = alignRevenuePeriods([row('2026-09-02', 100), row('2026-09-04', 50)], [row('2025-09-01', 0), row('2025-09-04', 25)], '2026-09-01', '2025-09-01', 'day');
  assert.deepEqual(slots.map(s => [s.offset, s.current?.omzet_totaal ?? null, s.previous?.omzet_totaal ?? null]), [[0, null, 0], [1, 100, null], [3, 50, 25]]);
});
test('custom ranges align from each start date, including leap days', () => {
  const slots = alignRevenuePeriods([row('2024-02-29', 10), row('2024-03-01', 20)], [row('2025-09-02', 15)], '2024-02-28', '2025-09-01', 'day');
  assert.deepEqual(slots.map(s => [s.offset, s.current?.periode, s.previous?.periode]), [[1, '2024-02-29', '2025-09-02'], [2, '2024-03-01', undefined]]);
});
test('monthly offsets preserve holes and align across years', () => {
  const slots = alignRevenuePeriods([row('2026-12', 10), row('2027-02', 30)], [row('2024-07', 15), row('2024-08', 20)], '2026-12-10', '2024-07-01', 'month');
  assert.deepEqual(slots.map(s => [s.offset, s.current?.omzet_totaal ?? null, s.previous?.omzet_totaal ?? null]), [[0, 10, 15], [1, null, 20], [2, 30, null]]);
});
test('differences use cents, a zero baseline has no percentage, missing is not zero', () => {
  assert.deepEqual(revenueDifference(0.3, 0.1), { amount: 0.2, percent: 200 });
  assert.deepEqual(revenueDifference(75, 100), { amount: -25, percent: -25 });
  assert.deepEqual(revenueDifference(50, 0), { amount: 50, percent: null });
  assert.deepEqual(revenueDifference(0, 0), { amount: 0, percent: null });
  assert.deepEqual(revenueDifference(-50, -100), { amount: 50, percent: 50 });
  assert.equal(revenueDifference(null, 100), null);
  assert.equal(revenueDifference(100, undefined), null);
  assert.equal(revenueTotal([]), null);
  assert.equal(revenueTotal([row('2026-01-01', 0.1), row('2026-01-02', 0.2)]), 0.3);
});
