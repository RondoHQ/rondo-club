import assert from 'node:assert/strict';
import test from 'node:test';
import { formatPersonName } from '../../src/utils/formatters.js';

test('family member names include the complete infix', () => {
  assert.equal(formatPersonName('Lars', 'van der', 'Meer'), 'Lars van der Meer');
  assert.equal(formatPersonName('Milan', 'van der', 'Meer'), 'Milan van der Meer');
});

test('optional name parts do not add extra spaces', () => {
  assert.equal(formatPersonName('Anne', '', 'Jansen'), 'Anne Jansen');
  assert.equal(formatPersonName(undefined, 'van der', 'Meer'), 'van der Meer');
  assert.equal(formatPersonName('', null, ''), '');
});
