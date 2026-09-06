import test from 'node:test';
import assert from 'node:assert/strict';
import { availableShifts, calendarIndex, monthDays, moveMonth, upcomingShifts, clubToday, clubPage, safeClubLogo } from '../src/member-model.mjs';

test('calendar counts eligible duties, not capacity, and marks own duties', () => {
  const shifts = [
    { id: 1, start_datetime: '2026-09-12 12:00:00', can_signup: true, spots_remaining: 12 },
    { id: 2, start_datetime: '2026-09-12 09:00:00', can_signup: true, spots_remaining: 2 },
    { id: 3, start_datetime: '2026-09-12 14:00:00', can_signup: true, is_signed_up: true },
    { id: 4, start_datetime: '2026-09-12 15:00:00', can_signup: false },
  ];
  const day = { date: '2026-09-12', shifts };
  assert.deepEqual(availableShifts(day).map((s) => s.id), [2, 1]);
  const index = calendarIndex([day], [{ start_datetime: '2026-09-13 10:00:00', status: 'open' }]);
  assert.equal(index.get(day.date).available, 2);
  assert.equal(index.get(day.date).mine, true);
  assert.equal(index.get('2026-09-13').mine, true);
  assert.equal(index.get('2026-09-13').available, 0);
});

test('month navigation covers year boundaries and leap days with Monday-first alignment', () => {
  assert.equal(moveMonth('2026-12', 1), '2027-01');
  assert.equal(moveMonth('2026-01', -1), '2025-12');
  assert.equal(monthDays('2028-02').dates.length, 29);
  assert.equal(monthDays('2026-09').blanks, 1);
});

test('own duties exclude past and cancelled entries without mutating server data', () => {
  const shifts = [
    { id: 1, status: 'geannuleerd', start_datetime: '2026-09-15 08:00:00' },
    { id: 2, status: 'open', start_datetime: '2026-09-12 08:00:00' },
    { id: 3, status: 'vol', start_datetime: '2026-09-11 08:00:00' },
    { id: 4, status: 'open', start_datetime: '2026-09-01 08:00:00' },
  ];
  assert.deepEqual(upcomingShifts(shifts, '2026-09-06').map((s) => s.id), [3, 2]);
  assert.equal(shifts[0].id, 1);
  assert.deepEqual(upcomingShifts(shifts, '2026-09-11 12:00:00').map((s) => s.id), [2]);
  assert.equal(clubToday('Europe/Amsterdam', new Date('2026-09-05T23:30:00Z')), '2026-09-06');
});

test('external handoffs are fixed club pages without API credentials', () => {
  assert.equal(clubPage({ url: 'https://club.test' }, 'profile'), 'https://club.test/mijn-gegevens');
  assert.throws(() => clubPage({ url: 'https://club.test' }, 'https://attacker.test'));
});

test('header logos only load from the selected HTTPS club', () => {
  const club = { url: 'https://club.test' };
  assert.equal(safeClubLogo('https://club.test/uploads/logo.png', club), 'https://club.test/uploads/logo.png');
  for (const url of ['https://other.test/logo.png', 'javascript:alert(1)', 'https://user:pass@club.test/logo.png', undefined]) assert.equal(safeClubLogo(url, club), '');
});
