export function formatMatchDate(value) {
  if (!value) return '';
  const date = new Date(`${value}T12:00:00`);
  if (Number.isNaN(date.getTime())) return '';

  return new Intl.DateTimeFormat('nl-NL', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  }).format(date);
}

export function splitResult(value) {
  const result = String(value || '').trim();
  const withPenalties = result.match(/^(.*?)\s*\(([^()]+)\)\s*$/);

  if (!withPenalties) {
    return { score: result, penalties: '' };
  }

  return {
    score: withPenalties[1].trim(),
    penalties: withPenalties[2].trim(),
  };
}
