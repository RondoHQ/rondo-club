import test from 'node:test';
import assert from 'node:assert/strict';
import { monthDays, closuresOnDate } from '../../src/utils/sportparkCalendar.js';

test('calendar uses Monday-first weeks and includes leap day', () => {
  const february = monthDays(2032, 1);
  assert.equal(february.filter(Boolean).length, 29);
  assert.equal(february.at(-1), '2032-02-29');
  assert.equal(monthDays(2026, 8)[0], null);
  assert.equal(monthDays(2026, 8)[1], '2026-09-01');
});

test('closed day highlights include boundaries and all overlapping periods', () => {
  const periods = [
    { fields: { starts_at: '2032-12-24', ends_at: '2033-01-02' } },
    { fields: { starts_at: '2033-01-02', ends_at: '2033-01-03' } },
  ];
  assert.equal(closuresOnDate('2032-12-23', periods).length, 0);
  assert.equal(closuresOnDate('2032-12-24', periods).length, 1);
  assert.equal(closuresOnDate('2033-01-02', periods).length, 2);
  assert.equal(closuresOnDate('2033-01-03', periods).length, 1);
  assert.equal(closuresOnDate('2033-01-04', periods).length, 0);
});
