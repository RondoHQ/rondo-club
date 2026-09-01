import assert from 'node:assert/strict';
import test from 'node:test';

import {
  allEligibleTournamentAssignments,
  tournamentAssignmentCounts,
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
