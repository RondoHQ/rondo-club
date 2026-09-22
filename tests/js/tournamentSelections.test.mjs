import assert from 'node:assert/strict';
import test from 'node:test';

import {
  allEligibleTournamentAssignments,
  currentTournamentTeams,
  tournamentAssignmentDelta,
  tournamentAssignmentCounts,
  tournamentAssignmentNeedsSync,
} from '../../src/pages/Tournaments/tournamentSelections.js';

test('invitation list and select all exclude teams without current players, even with staff', () => {
  const options = [
    { id: 10, player_count: 12, assignees: [{ person_id: 1, email: 'trainer@example.test' }] },
    { id: 20, player_count: 0, assignees: [{ person_id: 2, email: 'leider@example.test' }] },
    { id: 30, player_count: 8, assignees: [] },
    { id: 40, player_count: 0, assignees: [] },
  ];
  const teams = currentTournamentTeams(options);

  assert.deepEqual(teams.map((team) => team.id), [10, 30]);
  assert.deepEqual(allEligibleTournamentAssignments(teams), { 10: [1] });
  assert.deepEqual(currentTournamentTeams(), []);
  assert.equal(options.length, 4, 'existing registration staff options remain available');
});

test('select all includes every eligible team and all of its staff', () => {
  const selected = allEligibleTournamentAssignments([
    { id: 10, assignees: [{ person_id: 1, email: 'trainer@example.test' }, { person_id: 2, email: 'leider@example.test' }] },
    { id: 20, assignees: [] },
    { id: 30, assignees: [{ person_id: 3, email: 'kader@example.test' }] },
  ]);

  assert.deepEqual(selected, { 10: [1, 2], 30: [3] });
  assert.deepEqual(tournamentAssignmentCounts(selected), {
    teamCount: 2,
    assigneeCount: 3,
    hasTeamWithoutAssignee: false,
  });
});

test('publication detects a selected team without an assignee', () => {
  assert.equal(tournamentAssignmentCounts({ 10: [] }).hasTeamWithoutAssignee, true);
});

test('assignment delta ignores order and counts added and removed staff', () => {
  assert.deepEqual(tournamentAssignmentDelta([1, 2], [2, 1]), {
    addedCount: 0,
    removedCount: 0,
    changed: false,
  });
  assert.deepEqual(tournamentAssignmentDelta([1, 2], [2, 3, 4]), {
    addedCount: 2,
    removedCount: 1,
    changed: true,
  });
});

test('assignment sync detects changed current staff details', () => {
  const current = [{ user_id: 1, person_id: 11, name: 'Trainer', role: 'Leider', email: 'oud@example.test', mobile: '06' }];
  const candidates = [{ user_id: 1, person_id: 11, name: 'Trainer', role: 'Trainer', email: 'nieuw@example.test', mobile: '06' }];
  assert.equal(tournamentAssignmentNeedsSync(current, candidates, [11]), true);
  assert.equal(tournamentAssignmentNeedsSync(candidates, candidates, [11]), false);
});


test('people without accounts remain distinct and unreachable staff are skipped', () => {
  const selected = allEligibleTournamentAssignments([
    { id: 10, assignees: [{ person_id: 31, user_id: 0, email: 'shared@example.test' }, { person_id: 32, user_id: 0, email: 'shared@example.test' }, { person_id: 33, user_id: 0, email: '' }] },
    { id: 20, assignees: [{ person_id: 40, user_id: 0, email: '' }] },
  ]);
  assert.deepEqual(selected, { 10: [31, 32] });
  assert.equal(tournamentAssignmentCounts(selected).assigneeCount, 2);
});
