import assert from 'node:assert/strict';
import test from 'node:test';
import { shouldPlacePopoverBelow } from '../../src/utils/popoverPosition.js';

test('places a popover below an anchor around the middle of the viewport', () => {
  assert.equal(
    shouldPlacePopoverBelow({ top: 380, bottom: 420 }, 844, 360),
    true
  );
});

test('identifies a low anchor that should be recentered before opening on mobile', () => {
  assert.equal(
    shouldPlacePopoverBelow({ top: 730, bottom: 770 }, 844, 360),
    false
  );
});
