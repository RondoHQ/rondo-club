import assert from 'node:assert/strict';
import test from 'node:test';

import {
  allEligibleTournamentAssignments,
  tournamentAssignmentDelta,
  tournamentAssignmentCounts,
  tournamentAssignmentNeedsSync,
} from '../../src/pages/Tournaments/tournamentSelections.js';

test('select all includes every eligible team and all of its staff', () => {
  const selected = allEligibleTournamentAssignments([
    { id: 10, assignees: [{ user_id: 1 }, { user_id: 2 }] },
    { id: 20, assignees: [] },
    { id: 30, assignees: [{ user_id: 3 }] },
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
  assert.equal(tournamentAssignmentNeedsSync(current, candidates, [1]), true);
  assert.equal(tournamentAssignmentNeedsSync(candidates, candidates, [1]), false);
});
