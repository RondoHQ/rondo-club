import assert from 'node:assert/strict';
import test from 'node:test';

import {
  cacheBustedPath,
  PLAYLIST_REFRESH_INTERVAL_MS,
  retainUnchangedPlaylist,
} from '../../src/pages/Narrowcasting/displayRefresh.js';

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

test('unchanged playlist polling preserves object identity so slide rotation can continue', () => {
  const current = {
    playlist_id: 42,
    content_version: 'same-scenes',
    override: false,
    generated_at: '2026-08-23T19:00:00Z',
  };
  const refreshed = {
    ...current,
    generated_at: '2026-08-23T19:00:10Z',
  };

  assert.strictEqual(retainUnchangedPlaylist(current, refreshed), current);
});

test('changed playlist content replaces the stored manifest', () => {
  const current = { playlist_id: 42, content_version: 'old', override: false };
  const refreshed = { playlist_id: 42, content_version: 'new', override: false };

  assert.strictEqual(retainUnchangedPlaylist(current, refreshed), refreshed);
});
