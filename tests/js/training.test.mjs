import test from 'node:test';
import assert from 'node:assert/strict';
import { conflictingBlocks, trainingDefaults, editableSchedule, fieldPart, fieldOffsets, fieldStep, trainingColor, contrastText, schedulePayload, toMinutes, toTime } from '../../src/utils/training.js';
const block = { block_id: 'a', day: 1, start: '18:00', duration: 60, pitch_id: 'p', offset: 0, size: 2, team_ids: [1] };
test('field and team collisions; adjacent slots and different versions remain independent', () => {
  assert.equal(conflictingBlocks([block, { ...block, block_id: 'b', offset: 2, team_ids: [2] }]).size, 0);
  assert.equal(conflictingBlocks([block, { ...block, block_id: 'b', start: '19:00' }]).size, 0);
  assert.equal(conflictingBlocks([block, { ...block, block_id: 'b', pitch_id: 'other' }]).size, 2);
  assert.equal(conflictingBlocks([block, { ...block, block_id: 'b', team_ids: [] }]).size, 2);
});
test('per-team overrides inherit other age-group defaults', () => {
  const settings = { age_groups: [{ id: 'o13', duration: 75, size: 2 }], teams: [{ team_id: 1, age_group_id: 'o13', duration: 90, size: null }] };
  assert.deepEqual(trainingDefaults(1, settings), { duration: 90, size: 2 });
  assert.deepEqual(trainingDefaults(2, settings), { duration: 60, size: 1 });
});
test('wire-only names are stripped without mutating cached data and midnight is formatted', () => {
  const original = { blocks: [{ ...block, team_names: ['O13-1'] }] };
  assert.equal('team_names' in editableSchedule(original).blocks[0], false);
  assert.deepEqual(original.blocks[0].team_names, ['O13-1']);
  assert.equal(toTime(toMinutes('23:00') + 60), '24:00');
  assert.equal(fieldPart({ offset: 2, size: 2 }), 'CD');
});

test('eighth and three-quarter positions preserve existing quarters', () => {
  assert.deepEqual(fieldOffsets(0.5), [0, 0.5, 1, 1.5, 2, 2.5, 3, 3.5]);
  assert.deepEqual(fieldOffsets(3), [0, 1]);
  assert.deepEqual(fieldOffsets(2), [0, 2]);
  assert.equal(fieldStep(3), 1);
  assert.equal(fieldPart({ offset: 3.5, size: 0.5 }), 'D2');
  assert.equal(fieldPart({ offset: 1, size: 3 }), 'BCD');
  const a = { ...block, size: 0.5, team_ids: [] };
  assert.equal(conflictingBlocks([a, { ...a, block_id: 'b', offset: 0.5 }]).size, 0);
  assert.equal(conflictingBlocks([a, { ...a, block_id: 'b', offset: 0 }]).size, 2);
});
test('colors follow team selection or explicit group, with readable contrast and no writes to derived colors', () => {
  const settings = { age_groups: [{ id: 'o13', color: '#b3de69' }, { id: 'keepers', color: '#ffffb3' }], teams: [{ team_id: 1, age_group_id: 'o13' }] };
  assert.equal(trainingColor(block, settings), '#b3de69');
  assert.equal(trainingColor({ ...block, age_group_id: 'keepers' }, settings), '#ffffb3');
  assert.equal(trainingColor({ ...block, color: '#123456' }), '#123456');
  assert.equal(contrastText('#b3de69'), '#000000');
  assert.equal(contrastText('#123456'), '#ffffff');
  assert.equal('color' in schedulePayload({ name: 'Test', blocks: [{ ...block, color: '#123456' }] }).blocks[0], false);
});
