import assert from 'node:assert/strict';
import test from 'node:test';

import {
  formatTournamentDate,
  toDateInput,
} from '../../src/pages/Tournaments/tournamentFormatters.js';

test('tournament dates are formatted without a time by default', () => {
  assert.equal(formatTournamentDate('2030-05-10T23:59:59+02:00'), '10 mei 2030');
});

test('date inputs keep the calendar date from the API value', () => {
  assert.equal(toDateInput('2030-05-10T23:59:59+02:00'), '2030-05-10');
  assert.equal(toDateInput(''), '');
});
