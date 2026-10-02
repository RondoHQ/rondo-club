import assert from 'node:assert/strict';
import test from 'node:test';
import { getSpeeldag, getTeamNameWithPlayingDay, teamNameCollator } from '../../src/utils/teamDisplay.js';

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

test('team labels distinguish playing days without duplicate or non-day suffixes', () => {
  assert.equal(getTeamNameWithPlayingDay('AWC 1', 'Veld - Zondag'), 'AWC 1 - Zondag');
  assert.equal(getTeamNameWithPlayingDay('AWC 1', 'Veld - Zaterdag'), 'AWC 1 - Zaterdag');
  assert.equal(getTeamNameWithPlayingDay('AWC VR30+1', 'Veld - Vrijdag'), 'AWC VR30+1 - Vrijdag');
  assert.equal(getTeamNameWithPlayingDay('AWC 1 - Zondag', 'Veld - Zondag'), 'AWC 1 - Zondag');
  assert.equal(getTeamNameWithPlayingDay('AWC 1', null), 'AWC 1');
  assert.equal(getTeamNameWithPlayingDay('AWC 1', 'Zaal'), 'AWC 1');
});
