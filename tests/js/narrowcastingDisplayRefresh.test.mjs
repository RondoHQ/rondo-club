import assert from 'node:assert/strict';
import test from 'node:test';

import { cacheBustedPath, PLAYLIST_REFRESH_INTERVAL_MS } from '../../src/pages/Narrowcasting/displayRefresh.js';

test('display playlist checks for updates every ten seconds', () => {
  assert.equal(PLAYLIST_REFRESH_INTERVAL_MS, 10000);
});

test('cache buster creates a unique uncached API path', () => {
  assert.equal(
    cacheBustedPath('/rondo/v1/narrowcasting/devices/me/playlist', 12345),
    '/rondo/v1/narrowcasting/devices/me/playlist?rondo_cache_buster=12345',
  );
});

test('cache buster preserves existing preview parameters', () => {
  assert.equal(
    cacheBustedPath('/rondo/v1/narrowcasting/preview/playlist?playlist_id=42', 12345),
    '/rondo/v1/narrowcasting/preview/playlist?playlist_id=42&rondo_cache_buster=12345',
  );
});
