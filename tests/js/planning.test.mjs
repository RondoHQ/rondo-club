import assert from 'node:assert/strict';
import test from 'node:test';
import { filterPlanningItems, isPlanningOverdue, planningMonthEnd, planningMovePayload, planningToday, sortPlanningItems } from '../../src/pages/Communication/planningUtils.js';

const today = '2026-09-27';
const item = (id, overrides = {}) => ({ id, title: `Bericht ${id}`, description: '', planned_date: today, actual_date: '', status: 'concept', assignee_id: 0, channel_ids: ['website'], modified_gmt: '2026-09-27T10:00:00+00:00', ...overrides });
const ids = (rows) => rows.map(({ id }) => id);

test('uses Amsterdam calendar dates around midnight and daylight-saving changes', () => {
  assert.equal(planningToday(new Date('2026-09-27T22:30:00Z')), '2026-09-28');
  assert.equal(planningToday(new Date('2026-12-31T23:30:00Z')), '2027-01-01');
  assert.equal(planningToday(new Date('2026-03-29T00:30:00Z')), '2026-03-29');
});

test('adds calendar months with month-end clamping, leap days and year changes', () => {
  assert.equal(planningMonthEnd(today, 2), '2026-11-27');
  assert.equal(planningMonthEnd('2026-12-31', 2), '2027-02-28');
  assert.equal(planningMonthEnd('2027-12-31', 2), '2028-02-29');
  assert.equal(planningMonthEnd('2026-01-31', 1), '2026-02-28');
});

test('defaults to two months including the boundary, overdue and undated concepts', () => {
  const rows = [item(1, { planned_date: '2020-01-01' }), item(2, { planned_date: '' }), item(3, { planned_date: '2026-11-27' }), item(4, { planned_date: '2026-11-28' })];
  assert.deepEqual(ids(filterPlanningItems(rows, {}, today)), [1, 2, 3]);
  assert.deepEqual(ids(filterPlanningItems(rows, { period: 'all' }, today)), [1, 2, 3, 4]);
});

test('completed items use actual dates and a 30-day inclusive window; full history stays accessible', () => {
  const rows = [item(1, { status: 'sent', actual_date: '2026-08-29', planned_date: '2027-01-01' }), item(2, { status: 'sent', actual_date: '2026-08-28' }), item(3, { status: 'sent', actual_date: today }), item(4, { status: 'sent', actual_date: '2026-09-28' })];
  assert.deepEqual(ids(filterPlanningItems(rows, {}, today)), [1, 3]);
  assert.deepEqual(ids(filterPlanningItems(rows, { period: 'all' }, today)), [1, 2, 3, 4]);
});

test('explicit date ranges replace the default window and use the appropriate date for each status', () => {
  const rows = [item(1, { planned_date: '2027-04-01' }), item(2, { planned_date: '' }), item(3, { status: 'sent', planned_date: today, actual_date: '2027-04-01' }), item(4, { status: 'sent', actual_date: '2026-08-01' })];
  assert.deepEqual(ids(filterPlanningItems(rows, { dateFrom: '2027-04-01', dateTo: '2027-04-01' }, today)), [1, 3]);
  assert.deepEqual(ids(filterPlanningItems(rows, { dateTo: '2026-08-01' }, today)), [4]);
});

test('combines text, channel and assignee filters and supports unassigned items', () => {
  const rows = [item(1, { title: 'NIEUWS voor trainers', assignee_id: 12 }), item(2, { description: 'Nieuws voor trainers', assignee_id: 12, channel_ids: ['newsletter'] }), item(3, { title: 'Nieuws voor trainers' })];
  assert.deepEqual(ids(filterPlanningItems(rows, { search: '  nieuws  ', channel: 'website', assignee: '12' }, today)), [1]);
  assert.deepEqual(ids(filterPlanningItems(rows, { assignee: 'unassigned' }, today)), [3]);
});

test('overdue means open and before today, never completed, archived, today or undated', () => {
  const rows = [item(1, { planned_date: '2026-09-26', status: 'ready' }), item(2), item(3, { planned_date: '' }), item(4, { planned_date: '2026-09-26', status: 'sent', actual_date: today }), item(5, { planned_date: '2026-09-26', status: 'skipped' })];
  assert.deepEqual(rows.map((row) => isPlanningOverdue(row, today)), [true, false, false, false, false]);
  assert.deepEqual(ids(filterPlanningItems(rows, { overdueOnly: true }, today)), [1]);
});

test('sorts open items oldest-first with undated last, and completed items newest-first without mutating input', () => {
  const rows = [item(1, { planned_date: '' }), item(2, { planned_date: '2026-10-01' }), item(3, { planned_date: '2026-08-01' })];
  assert.deepEqual(ids(sortPlanningItems(rows)), [3, 2, 1]);
  assert.deepEqual(ids(rows), [1, 2, 3]);
  assert.deepEqual(ids(sortPlanningItems([item(1, { actual_date: '2026-09-01' }), item(2, { actual_date: today })], true)), [2, 1]);
});

test('drag updates only open status and version, never completion, archived items or series', () => {
  const row = item(1, { series_id: 99, channels: [{ channel_id: 'website', actual_date: '' }] });
  assert.deepEqual(planningMovePayload(row, 'preparing'), { status: 'preparing', version: row.modified_gmt });
  for (const status of ['sent', 'cancelled', 'skipped', 'concept', 'invalid']) assert.equal(planningMovePayload(row, status), null);
  for (const status of ['sent', 'cancelled', 'skipped']) assert.equal(planningMovePayload({ ...row, status }, 'concept'), null);
});
