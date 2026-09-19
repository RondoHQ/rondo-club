export function getSpeeldag(activiteit) {
  if (!activiteit) return '';
  const parts = activiteit.split(/veld\s*-\s*/i);
  return parts.length > 1 ? parts[parts.length - 1].trim() : activiteit;
}

export const teamNameCollator = new Intl.Collator('nl', { numeric: true, sensitivity: 'base' });
