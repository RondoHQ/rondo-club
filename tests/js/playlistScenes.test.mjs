import assert from 'node:assert/strict';
import test from 'node:test';

import { showsDateTimeForScene } from '../../src/pages/Narrowcasting/playlistScenes.js';

test('manual playlist slides without date and clock keep them hidden', () => {
  for (const type of ['sponsor', 'video', 'fallback']) {
    assert.equal(showsDateTimeForScene({ type }), false, `${type} should hide date and time`);
  }
});

test('matchday, announcement and image slides show a date and clock', () => {
  for (const type of ['matches', 'cancellations', 'results', 'announcement', 'image']) {
    assert.equal(showsDateTimeForScene({ type }), true, `${type} should show date and time`);
  }
});
