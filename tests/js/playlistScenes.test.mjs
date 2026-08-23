import assert from 'node:assert/strict';
import test from 'node:test';

import { showsDateTimeForScene } from '../../src/pages/Narrowcasting/playlistScenes.js';

test('manual playlist slides do not show a date or clock', () => {
  for (const type of ['announcement', 'sponsor', 'image', 'video', 'fallback']) {
    assert.equal(showsDateTimeForScene({ type }), false, `${type} should hide date and time`);
  }
});

test('matchday slides keep their relevant date and clock', () => {
  for (const type of ['matches', 'cancellations', 'results']) {
    assert.equal(showsDateTimeForScene({ type }), true, `${type} should show date and time`);
  }
});
