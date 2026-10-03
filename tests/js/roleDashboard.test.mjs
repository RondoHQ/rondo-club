import test from 'node:test';
import assert from 'node:assert/strict';
import { combineDashboardMatches, dashboardRoomLabel, moveDashboardBlock, getBoardSummary, hasDefaultDashboardOrder, getDashboardMatchPage } from '../../src/utils/roleDashboard.js';

test('overlapping team roles yield one fixture, union team IDs and preserve cancellations', () => {
  const match = { id: '12', date: '2026-09-23', starts_at: '2026-09-23T18:00:00+02:00', cancelled: true };
  const feeds = [
    { teamId: 1, data: { matches: [match, { ...match, id: 'old', date: '2026-09-22' }] } },
    { teamId: 2, data: { matches: [{ ...match, id: 12, cancelled: false }, { ...match, id: 'future', date: '2026-09-30' }] } },
    { teamId: 3, data: null },
  ];
  const combined = combineDashboardMatches(feeds, '2026-09-23', '2026-09-29');
  assert.equal(combined.length, 1);
  assert.equal(combined[0].cancelled, true);
  assert.deepEqual(combined[0].team_ids, [1, 2]);
  assert.deepEqual(feeds[0].data.matches[0], match);
});

test('unknown dressing rooms remain distinct from explicitly no room', () => {
  assert.equal(dashboardRoomLabel(null), 'Nog niet ingevuld');
  assert.equal(dashboardRoomLabel('  '), 'Nog niet ingevuld');
  assert.equal(dashboardRoomLabel('0 - geen kleedkamer'), '0 - geen kleedkamer');
});

test('reordering respects both edges and preserves the original preference', () => {
  const order = ['attention', 'birthdays', 'matches'];
  assert.deepEqual(moveDashboardBlock(order, 0, -1), order);
  assert.deepEqual(moveDashboardBlock(order, 2, 1), order);
  assert.deepEqual(moveDashboardBlock(order, 1, -1), ['birthdays', 'attention', 'matches']);
  assert.deepEqual(order, ['attention', 'birthdays', 'matches']);
});

test('board summary never exposes hidden, absent or unknown counts', () => {
  const data = { membership: { active: 10, joined: 2, season: '2026-2027' }, volunteers: { open_spots: 0, window_days: 30 }, vog: { not_submitted_to_justis: 2, submitted_to_justis: 86 } };
  assert.deepEqual(getBoardSummary(data, []).map(item => item.value), []);
  assert.deepEqual(getBoardSummary(data, ['membership']).map(item => item.value), [10, 2]);
  assert.deepEqual(getBoardSummary(data, ['volunteers', 'vog']).map(item => item.value), [0, 88]);
  assert.deepEqual(getBoardSummary({}, ['membership', 'volunteers', 'vog']), []);
  assert.deepEqual(getBoardSummary({ vog: { not_submitted_to_justis: null, submitted_to_justis: 3 } }, ['vog']), []);
});

test('two-column board composition never overrides a saved custom order', () => {
  const defaults = ['birthdays', 'attention', 'membership'];
  assert.equal(hasDefaultDashboardOrder({ order: [...defaults], defaults, hidden: ['birthdays'] }), true);
  assert.equal(hasDefaultDashboardOrder({ order: ['attention', 'birthdays', 'membership'], defaults }), false);
  assert.equal(hasDefaultDashboardOrder({ order: defaults }), false);
});


test('a busy week remains fully accessible across pages and filter changes', () => {
  const matches = Array.from({ length: 44 }, (_, index) => ({ id: String(index), date: index < 35 ? '2026-10-03' : '2026-10-04', home_team: index % 2 ? 'AWC JO13-1' : 'Équipe 1', away_team: index % 2 ? 'Tegenstander 1' : 'AWC 2', cancelled: index === 13, result: index < 21 ? '2 - 1' : '' }));
  const ids = Array.from({ length: 8 }, (_, page) => getDashboardMatchPage(matches, { page }).items).flat().map(match => match.id);
  assert.deepEqual(ids, matches.map(match => match.id));
  assert.equal(new Set(ids).size, 44);
  const filtered = getDashboardMatchPage(matches, { date: '2026-10-04', query: 'awC jo13', page: 7 });
  assert.equal(filtered.page, 0);
  assert.equal(filtered.total, 5);
  assert.ok(filtered.items.every(match => match.date === '2026-10-04' && match.home_team === 'AWC JO13-1'));
  assert.equal(getDashboardMatchPage(matches, { query: 'equipe' }).total, 22);
  assert.deepEqual(getDashboardMatchPage(matches, { query: 'no match', page: 5 }).items, []);
});
