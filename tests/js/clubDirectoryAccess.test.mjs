import test from 'node:test';
import assert from 'node:assert/strict';
import { canAccessClubDirectory, canAccessFootballItem, footballNavigation } from '../../src/utils/footballNavigation.js';
import { canAccessFootball } from '../../src/utils/footballAccess.js';

test('match-affairs users can discover and open the directory without seeing all teams', () => {
  const user = { can_access_wedstrijdzaken: true };
  assert.equal(canAccessFootball(user), true);
  assert.equal(canAccessClubDirectory(user), true);
  assert.deepEqual(footballNavigation.filter(item => canAccessFootballItem(item, user)).map(item => item.href), ['/voetbal/verenigingen']);
});

test('existing access remains available while unprivileged users stay excluded', () => {
  for (const user of [{ is_admin: true }, { can_access_teams: true }]) assert.equal(canAccessClubDirectory(user), true);
  for (const user of [undefined, {}, { can_access_wedstrijdzaken: false }, { can_access_kaderlijst: true }]) assert.equal(canAccessClubDirectory(user), false);
});
