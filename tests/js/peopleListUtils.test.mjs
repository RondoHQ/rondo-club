import assert from 'node:assert/strict';
import test from 'node:test';

import { getCurrentTeamId } from '../../src/pages/People/peopleListUtils.js';

test('returns the current team from work history', () => {
  const person = {
    fields: {
      work_history: [
        { team_id: 10, is_current: false },
        { team_id: 20, is_current: true },
      ],
    },
  };

  assert.equal(getCurrentTeamId(person), 20);
});

test('does not show the most recent historical team', () => {
  const person = {
    fields: {
      work_history: [
        { team_id: 10, is_current: false, start_date: '2024-07-01' },
        { team_id: 20, is_current: false, start_date: '2025-07-01' },
      ],
    },
  };

  assert.equal(getCurrentTeamId(person), null);
});

test('uses an explicit current team supplied by the API', () => {
  assert.equal(getCurrentTeamId({ team_id: 30, fields: { work_history: [] } }), 30);
});
