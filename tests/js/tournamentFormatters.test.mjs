import assert from 'node:assert/strict';
import test from 'node:test';

import {
  formatTournamentDate,
  tournamentPaymentStatus,
  toDateInput,
  toDateTimeInput,
} from '../../src/pages/Tournaments/tournamentFormatters.js';

test('tournament dates are formatted without a time by default', () => {
  assert.equal(formatTournamentDate('2030-05-10T23:59:59+02:00'), '10 mei 2030');
});

test('date inputs keep the calendar date from the API value', () => {
  assert.equal(toDateInput('2030-05-10T23:59:59+02:00'), '2030-05-10');
  assert.equal(toDateInput(''), '');
});

test('tournament schedule inputs keep the local date and time from the API value', () => {
  assert.equal(toDateTimeInput('2030-05-10T14:30:00+02:00'), '2030-05-10T14:30');
  assert.equal(toDateTimeInput('2030-05-10 14:30:00'), '2030-05-10T14:30');
  assert.equal(toDateTimeInput(''), '');
});

test('submitted tournament entries expose their payment progress', () => {
  assert.deepEqual(
    tournamentPaymentStatus({ registration_status: 'submitted', payment_state: 'open' }),
    { label: 'Betaling open', tone: 'open' },
  );
  assert.deepEqual(
    tournamentPaymentStatus({ registration_status: 'submitted', payment_state: 'paid' }),
    { label: 'Betaald', tone: 'success' },
  );
  assert.deepEqual(
    tournamentPaymentStatus({ registration_status: 'open', payment_state: 'not_applicable' }),
    { label: 'Niet ingeschreven', tone: 'pending' },
  );
});
