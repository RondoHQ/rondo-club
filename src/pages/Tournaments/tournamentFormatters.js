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

export function toDateTimeInput(value) {
  if (!value) return '';
  return String(value).slice(0, 16).replace(' ', 'T');
}

export function tournamentStatusLabel(status) {
  return {
    draft: 'Concept',
    open: 'Open',
    closed: 'Gesloten',
    archived: 'Gearchiveerd',
  }[status] || status;
}

export function tournamentPaymentStatus(entry) {
  if (entry.registration_status !== 'submitted') {
    return { label: 'Niet ingeschreven', tone: 'pending' };
  }

  return {
    paid: { label: 'Betaald', tone: 'success' },
    open: { label: 'Betaling open', tone: 'open' },
    creating: { label: 'Betaling voorbereiden', tone: 'open' },
    error: { label: 'Betaallink ontbreekt', tone: 'error' },
    expired: { label: 'Betaling vervallen', tone: 'error' },
    not_applicable: { label: 'Ingeschreven', tone: 'success' },
  }[entry.payment_state] || { label: 'Ingeschreven', tone: 'success' };
}

export function tournamentPaymentToneClasses(tone) {
  return {
    success: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
    open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
    error: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
  }[tone];
}
