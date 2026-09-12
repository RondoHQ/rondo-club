export const TRAINING_DAYS = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];
export const FIELD_SIZES = [{ value: 0.5, label: 'Achtste veld' }, { value: 1, label: 'Kwart veld' }, { value: 2, label: 'Half veld' }, { value: 3, label: 'Driekwart veld' }, { value: 4, label: 'Heel veld' }];
export const toMinutes = (time) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
export const toTime = (minutes) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
export const fieldPart = (block) => block.size === 0.5 ? `${'ABCD'[Math.floor(block.offset)]}${block.offset % 1 ? 2 : 1}` : 'ABCD'.slice(block.offset, block.offset + block.size);
export const blockTitle = (block, teams = []) => block.label || block.team_ids.map((id) => teams.find((team) => team.id === id)?.name || block.team_names?.[block.team_ids.indexOf(id)] || `Team ${id}`).join(', ');
export const editableSchedule = (schedule) => ({ ...schedule, blocks: schedule.blocks.map((item) => { const block = { ...item }; delete block.team_names; return block; }) });
export const schedulePayload = ({ name, season, revision, blocks }) => ({ name, season, revision, blocks: blocks.map((item) => { const block = { ...item }; delete block.color; delete block.team_names; return block; }) });

export function trainingDefaults(teamId, settings) {
  const team = settings.teams.find((item) => item.team_id === teamId);
  const group = settings.age_groups.find((item) => item.id === team?.age_group_id);
  return { duration: team?.duration ?? group?.duration ?? 60, size: team?.size ?? group?.size ?? 1 };
}

export function conflictingBlocks(blocks) {
  const conflicts = new Set();
  for (let i = 0; i < blocks.length; i++) {
    const a = blocks[i];
    for (let j = i + 1; j < blocks.length; j++) {
      const b = blocks[j];
      if (a.day !== b.day || toMinutes(a.start) >= toMinutes(b.start) + b.duration || toMinutes(b.start) >= toMinutes(a.start) + a.duration) continue;
      if (a.team_ids.some((id) => b.team_ids.includes(id)) || (a.pitch_id === b.pitch_id && a.offset < b.offset + b.size && b.offset < a.offset + a.size)) {
        conflicts.add(a.block_id);
        conflicts.add(b.block_id);
      }
    }
  }
  return conflicts;
}

// Preserve existing quarter units in the API; an eighth is 0.5 quarters.
export const fieldStep = (size) => size === 3 ? 1 : size;
export const fieldOffsets = (size) => Array.from({ length: Math.floor((4 - size) / fieldStep(size)) + 1 }, (_, index) => index * fieldStep(size));

export function trainingColor(block, settings) {
  if (!settings) return block.color || '#cffafe';
  const groupId = block.age_group_id || block.team_ids.map((id) => settings.teams.find((team) => team.team_id === id)?.age_group_id).find(Boolean);
  return settings.age_groups.find((group) => group.id === groupId)?.color || '#cffafe';
}

export function contrastText(color) {
  const channels = color.slice(1).match(/.{2}/g).map((hex) => parseInt(hex, 16) / 255).map((value) => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
  const luminance = channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
  return luminance > 0.179 ? '#000000' : '#ffffff';
}
