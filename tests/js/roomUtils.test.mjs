import assert from 'node:assert/strict';
import test from 'node:test';
import {
  contextPayload,
  contextValue,
  groupBookingsByDate,
  localDateTimeIso,
  rangeForDate,
  upsertAvailabilityBooking,
} from '../../src/pages/Rooms/roomUtils.js';

test('serializes server-derived booking contexts', () => {
  const commissie = { type: 'commissie', commissie_id: 42 };
  const ageGroup = { type: 'age_group', age_group_key: 'O12' };

  assert.equal(contextValue(commissie), 'commissie:42');
  assert.deepEqual(contextPayload(commissie), {
    booking_context_type: 'commissie',
    commissie_id: 42,
    age_group_key: '',
  });
  assert.equal(contextValue(ageGroup), 'age_group:O12');
  assert.deepEqual(contextPayload(ageGroup), {
    booking_context_type: 'age_group',
    commissie_id: null,
    age_group_key: 'O12',
  });
});

test('creates timezone-explicit booking values and calendar ranges', () => {
  assert.match(localDateTimeIso('2026-09-01', '19:30'), /Z$/);
  const range = rangeForDate('2026-09-01', 7);
  assert.equal((new Date(range.end) - new Date(range.start)) / 86400000, 7);
});

test('groups bookings by local start date in chronological order', () => {
  const groups = groupBookingsByDate([
    { id: 2, start_datetime: '2026-09-02T10:00:00+02:00' },
    { id: 1, start_datetime: '2026-09-01T10:00:00+02:00' },
    { id: 3, start_datetime: '2026-09-01T11:00:00+02:00' },
  ]);

  assert.deepEqual(groups.map(([date]) => date), ['2026-09-01', '2026-09-02']);
  assert.deepEqual(groups[0][1].map(({ id }) => id), [1, 3]);
});

test('keeps availability cache aligned after booking changes', () => {
  const range = rangeForDate('2026-08-24');
  const booking = {
    id: 10510,
    room_id: 10509,
    start_datetime: '2026-08-24T19:00:00+02:00',
    end_datetime: '2026-08-24T20:00:00+02:00',
    effective_end_datetime: '2026-08-24T20:00:00+02:00',
    status: 'confirmed',
    purpose: 'Niet openbaar maken',
  };

  const inserted = upsertAvailabilityBooking([], booking, range);
  assert.deepEqual(inserted, [{
    id: 10510,
    room_id: 10509,
    start_datetime: '2026-08-24T19:00:00+02:00',
    end_datetime: '2026-08-24T20:00:00+02:00',
    status: 'confirmed',
  }]);

  assert.deepEqual(upsertAvailabilityBooking(inserted, { ...booking, status: 'cancelled' }, range), []);
  assert.deepEqual(upsertAvailabilityBooking(inserted, {
    ...booking,
    start_datetime: '2026-08-25T19:00:00+02:00',
    end_datetime: '2026-08-25T20:00:00+02:00',
    effective_end_datetime: '2026-08-25T20:00:00+02:00',
  }, range), []);
});
