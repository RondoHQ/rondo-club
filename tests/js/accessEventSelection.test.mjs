import test from 'node:test';
import assert from 'node:assert/strict';
import { chooseAccessMatch, createStoredAccessMatch } from '../../src/utils/accessEvents.js';

const activeOne = { id: 'match-1', is_active: true, is_selectable: true, cancelled: false };
const activeTwo = { id: 'match-2', is_active: true, is_selectable: true, cancelled: false };
const upcoming = { id: 'match-3', is_active: false, is_selectable: true, cancelled: false };

test('automatically selects the only active match', () => {
  assert.equal(chooseAccessMatch([activeOne, upcoming], null, '2026-08-25'), activeOne);
});

test('requires a one-time choice for simultaneous active matches', () => {
  assert.equal(chooseAccessMatch([activeOne, activeTwo], null, '2026-08-25'), null);
});

test('reuses the stored same-day choice on the device', () => {
  const stored = createStoredAccessMatch(activeTwo, '2026-08-25');
  assert.equal(chooseAccessMatch([activeOne, activeTwo], stored, '2026-08-25'), activeTwo);
  assert.equal(chooseAccessMatch([activeOne, activeTwo], stored, '2026-08-26'), null);
});

test('a sole active match replaces an older inactive choice', () => {
  const stored = createStoredAccessMatch(upcoming, '2026-08-25');
  assert.equal(chooseAccessMatch([activeOne, upcoming], stored, '2026-08-25'), activeOne);
});

test('an inactive manual choice remains when no match is active', () => {
  const stored = createStoredAccessMatch(upcoming, '2026-08-25');
  assert.equal(chooseAccessMatch([upcoming], stored, '2026-08-25'), upcoming);
});

test('never restores a cancelled match', () => {
  const cancelled = { ...activeOne, is_selectable: false, cancelled: true };
  const stored = createStoredAccessMatch(cancelled, '2026-08-25');
  assert.equal(chooseAccessMatch([cancelled], stored, '2026-08-25'), null);
});

test('does not restore a match outside the selectable day', () => {
  const future = { ...upcoming, is_selectable: false };
  const stored = createStoredAccessMatch(future, '2026-08-25');
  assert.equal(chooseAccessMatch([future], stored, '2026-08-25'), null);
});
