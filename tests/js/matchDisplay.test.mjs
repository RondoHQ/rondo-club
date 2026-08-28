import assert from 'node:assert/strict';
import test from 'node:test';

import { formatMatchDate, splitResult } from '../../src/pages/Narrowcasting/matchDisplay.js';

test('formats a result date compactly in Dutch', () => {
  assert.equal(formatMatchDate('2026-08-22'), 'za 22 aug');
  assert.equal(formatMatchDate(''), '');
  assert.equal(formatMatchDate('geen-datum'), '');
});

test('separates a penalty shootout from the score after playing time', () => {
  assert.deepEqual(splitResult('1 - 1 (3 - 1)'), {
    score: '1 - 1',
    penalties: '3 - 1',
  });
  assert.deepEqual(splitResult('7 - 9'), {
    score: '7 - 9',
    penalties: '',
  });
});
