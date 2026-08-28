export function formatTournamentDate(value, withTime = false) {
  if (!value) return 'Niet ingesteld';
  const dateParts = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
  const date = !withTime && dateParts
    ? new Date(Number(dateParts[1]), Number(dateParts[2]) - 1, Number(dateParts[3]))
    : new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('nl-NL', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  }).format(date);
}

export function formatTournamentCurrency(value) {
  return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(value) || 0);
}

export function toDateInput(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

export function tournamentStatusLabel(status) {
  return {
    draft: 'Concept',
    open: 'Open',
    closed: 'Gesloten',
    archived: 'Gearchiveerd',
  }[status] || status;
}
