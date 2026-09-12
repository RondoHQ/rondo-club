import test from 'node:test';
import assert from 'node:assert/strict';
import { beginPhotoGesture, movePhotoGesture, photoCropRect } from '../../src/utils/photoCrop.js';

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

const dimensions = { width: 1200, height: 800 };
const crop = { zoom: 1, position: { x: 0.5, y: 0.5 } };
const viewport = { left: 20, top: 100, width: 200 };
const close = (actual, expected) => assert.ok(Math.abs(actual - expected) < 1e-9, `${actual} != ${expected}`);

test('pinch zoom keeps an off-center subject under the moving midpoint in both axes', () => {
  const start = [{ x: 60, y: 130 }, { x: 100, y: 170 }];
  const gesture = beginPhotoGesture(dimensions, crop, start, viewport);
  // Double the diagonal distance while moving the midpoint right and down.
  const next = movePhotoGesture(dimensions, gesture, [{ x: 60, y: 120 }, { x: 140, y: 200 }]);
  close(next.zoom, 2);
  const rect = photoCropRect(1200, 800, next.zoom, next.position);
  close(rect.x + 0.4 * rect.size, 440);
  close(rect.y + 0.3 * rect.size, 200);
});

test('lifting a finger preserves the crop and allows immediate one-finger panning', () => {
  const start = [{ x: 70, y: 200 }, { x: 170, y: 200 }];
  const end = [{ x: 20, y: 200 }, { x: 220, y: 200 }];
  const pinched = movePhotoGesture(dimensions, beginPhotoGesture(dimensions, crop, start, viewport), end);
  const remaining = [end[1]];
  const pan = beginPhotoGesture(dimensions, pinched, remaining, viewport);
  assert.deepEqual(movePhotoGesture(dimensions, pan, remaining), pinched);
  const moved = movePhotoGesture(dimensions, pan, [{ x: 200, y: 220 }]);
  const before = photoCropRect(1200, 800, pinched.zoom, pinched.position);
  const after = photoCropRect(1200, 800, moved.zoom, moved.position);
  assert.equal(after.size, before.size);
  close(after.x - before.x, 40);
  close(after.y - before.y, -40);
});

test('adding a second finger after panning starts from the current crop', () => {
  const panned = { zoom: 2, position: { x: 0.7, y: 0.3 } };
  const points = [{ x: 80, y: 160 }, { x: 150, y: 240 }];
  const next = movePhotoGesture(dimensions, beginPhotoGesture(dimensions, panned, points, viewport), points);
  close(next.zoom, panned.zoom);
  close(next.position.x, panned.position.x);
  close(next.position.y, panned.position.y);
});

test('extreme gestures stay within zoom limits and source bounds, including portrait photos', () => {
  for (const source of [dimensions, { width: 800, height: 1200 }]) {
    const gesture = beginPhotoGesture(source, crop, [{ x: 100, y: 180 }, { x: 140, y: 220 }], viewport);
    for (const points of [
      [{ x: -1000, y: -1000 }, { x: 1000, y: 1000 }],
      [{ x: 119, y: 199 }, { x: 121, y: 201 }],
      [{ x: 900, y: 900 }, { x: 900, y: 900 }],
    ]) {
      const next = movePhotoGesture(source, gesture, points);
      assert.ok(next.zoom >= 1 && next.zoom <= 4);
      const rect = photoCropRect(source.width, source.height, next.zoom, next.position);
      assert.ok(rect.x >= 0 && rect.y >= 0);
      assert.ok(rect.x + rect.size <= source.width && rect.y + rect.size <= source.height);
    }
  }
});

test('coincident starting fingers never divide by zero and ending all pointers clears the gesture', () => {
  const points = [{ x: 100, y: 200 }, { x: 100, y: 200 }];
  const next = movePhotoGesture(dimensions, beginPhotoGesture(dimensions, crop, points, viewport), points);
  assert.deepEqual(next, crop);
  assert.equal(beginPhotoGesture(dimensions, next, [], viewport), null);
});
