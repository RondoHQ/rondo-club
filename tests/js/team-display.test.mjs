import assert from 'node:assert/strict';
import test from 'node:test';
import { getSpeeldag, teamNameCollator } from '../../src/utils/teamDisplay.js';

test('team names sort naturally by both age group and team number', () => {
  const names = ['JO10-1', 'JO7-10', 'JO7-2', 'JO7-1', 'JO12-1'];
  assert.deepEqual([...names].sort(teamNameCollator.compare), ['JO7-1', 'JO7-2', 'JO7-10', 'JO10-1', 'JO12-1']);
  assert.deepEqual([...names].sort((a, b) => -teamNameCollator.compare(a, b)), ['JO12-1', 'JO10-1', 'JO7-10', 'JO7-2', 'JO7-1']);
});

test('team activity shows its playing day and handles missing data', () => {
  assert.equal(getSpeeldag('Veld - Zaterdag'), 'Zaterdag');
  assert.equal(getSpeeldag('Veld - Zondag'), 'Zondag');
  assert.equal(getSpeeldag('Veld -  Zaterdag'), 'Zaterdag');
  assert.equal(getSpeeldag('Zaal'), 'Zaal');
  assert.equal(getSpeeldag(null), '');
});
