import test from 'node:test';
import assert from 'node:assert/strict';
import { photoCropRect } from '../../src/utils/photoCrop.js';

test('landscape and portrait photos stay inside source bounds while zooming and panning', () => {
  for (const [width, height] of [[1200, 800], [800, 1200], [100, 100]]) {
    for (const zoom of [1, 2, 4]) {
      for (const position of [{ x: 0, y: 0 }, { x: 1, y: 1 }, { x: -1, y: 2 }]) {
        const rect = photoCropRect(width, height, zoom, position);
        assert.ok(rect.x >= 0 && rect.y >= 0);
        assert.ok(rect.x + rect.size <= width);
        assert.ok(rect.y + rect.size <= height);
      }
    }
  }
});

test('initial square crop is centered and zoom can select a specific corner', () => {
  assert.deepEqual(photoCropRect(1200, 800, 1, { x: 0.5, y: 0.5 }), { x: 200, y: 0, size: 800 });
  assert.deepEqual(photoCropRect(800, 1200, 2, { x: 1, y: 1 }), { x: 400, y: 800, size: 400 });
});
