import assert from 'node:assert/strict';
import test from 'node:test';

import { buildMatchdayScenes, rotateSponsors } from '../../src/pages/Narrowcasting/matchdayScenes.js';

function sponsor(id, priority) {
  return { id, name: `Sponsor ${id}`, logo_url: `https://example.test/${id}.png`, club_tv_priority: priority };
}

test('hidden sponsors are excluded and always sponsors stay on every slide', () => {
  const sponsors = [sponsor(1, 0), sponsor(2, 1), sponsor(3, 2), sponsor(4, 3)];

  for (let index = 0; index < 12; index += 1) {
    const selected = rotateSponsors(sponsors, index, 3);
    assert.equal(selected.some((item) => item.id === 1), false);
    assert.equal(selected.some((item) => item.id === 4), true);
    assert.equal(new Set(selected.map((item) => item.id)).size, selected.length);
  }
});

test('often receives three times as many single-slot rotations as sometimes', () => {
  const sponsors = [sponsor(1, 1), sponsor(2, 2)];
  const counts = new Map([[1, 0], [2, 0]]);

  for (let index = 0; index < 40; index += 1) {
    const [selected] = rotateSponsors(sponsors, index, 1);
    counts.set(selected.id, counts.get(selected.id) + 1);
  }

  assert.equal(counts.get(1), 10);
  assert.equal(counts.get(2), 30);
});

test('at most eight always sponsors occupy the eight available positions', () => {
  const sponsors = Array.from({ length: 8 }, (_, index) => sponsor(index + 1, 3));
  assert.deepEqual(rotateSponsors(sponsors, 0).map((item) => item.id), [1, 2, 3, 4, 5, 6, 7, 8]);
});

test('results use the same five-row page size as match information', () => {
  const results = Array.from({ length: 6 }, (_, index) => ({ id: index + 1, result: '1 - 0' }));
  const scenes = buildMatchdayScenes({
    configured: true,
    target_date: '2026-08-29',
    source: { fetched_at: new Date().toISOString() },
    results,
  });

  assert.deepEqual(scenes.map((scene) => scene.items.length), [5, 1]);
  assert.equal(scenes.every((scene) => scene.type === 'results'), true);
});
