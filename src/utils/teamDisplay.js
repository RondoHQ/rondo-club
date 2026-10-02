export function getSpeeldag(activiteit) {
  if (!activiteit) return '';
  const parts = activiteit.split(/veld\s*-\s*/i);
  return parts.length > 1 ? parts[parts.length - 1].trim() : activiteit;
}

export const teamNameCollator = new Intl.Collator('nl', { numeric: true, sensitivity: 'base' });

export function getTeamNameWithPlayingDay(name, activiteit) {
  const day = getSpeeldag(activiteit);
  if (!name || !/^(maandag|dinsdag|woensdag|donderdag|vrijdag|zaterdag|zondag)$/i.test(day)) return name;
  if (name.toLowerCase().endsWith(` - ${day.toLowerCase()}`)) return name;
  return `${name} - ${day}`;
}
