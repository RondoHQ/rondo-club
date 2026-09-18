import assert from 'node:assert/strict';
import test from 'node:test';
import { shiftColumnValue } from '../../src/pages/People/shiftProgress.js';

test('pending data is not presented as an absence of duty', () => {
  assert.equal(shiftColumnValue(undefined, 'shift_status'), '…');
  assert.equal(shiftColumnValue(null, 'shift_status'), 'Geen verplichting');
});

test('zero completed duties is visible while exempt duties have no numeric total', () => {
  assert.equal(shiftColumnValue({ status: 'not_started', completed: 0 }, 'shift_completed'), 0);
  assert.equal(shiftColumnValue({ status: 'exempt', completed: 0 }, 'shift_completed'), '-');
  assert.equal(shiftColumnValue({ status: 'exempt' }, 'shift_status'), 'Vrijgesteld');
});
