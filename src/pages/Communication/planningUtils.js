export const planningColumns = [
  { id: 'concept', label: 'Concept', dot: 'bg-gray-400' },
  { id: 'preparing', label: 'In voorbereiding', dot: 'bg-blue-500' },
  { id: 'ready', label: 'Klaar', dot: 'bg-amber-500' },
  { id: 'sent', label: 'Afgerond', dot: 'bg-emerald-500' },
];

export const openPlanningStatuses = ['concept', 'preparing', 'ready'];
export const archivedPlanningStatuses = ['skipped', 'cancelled'];

export function planningToday(now = new Date()) {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Amsterdam', year: 'numeric', month: '2-digit', day: '2-digit',
  }).format(now);
}

// Work with calendar dates in UTC so browser time zones and DST do not shift the range.
export function planningMonthEnd(today, months) {
  const [year, month, day] = today.split('-').map(Number);
  const lastDay = new Date(Date.UTC(year, month - 1 + months + 1, 0)).getUTCDate();
  return new Date(Date.UTC(year, month - 1 + months, Math.min(day, lastDay))).toISOString().slice(0, 10);
}

export function isPlanningOverdue(item, today) {
  return openPlanningStatuses.includes(item.status) && Boolean(item.planned_date) && item.planned_date < today;
}

export function filterPlanningItems(items, filters, today) {
  const { period = '2', search = '', channel = '', assignee = '', dateFrom = '', dateTo = '', overdueOnly = false } = filters;
  const query = search.trim().toLocaleLowerCase('nl-NL');
  const customRange = Boolean(dateFrom || dateTo);
  const until = period === 'all' ? '' : planningMonthEnd(today, Number(period));
  const since = new Date(`${today}T12:00:00Z`);
  since.setUTCDate(since.getUTCDate() - 29);
  const completedSince = since.toISOString().slice(0, 10);

  return items.filter((item) => {
    const date = item.status === 'sent' ? item.actual_date : item.planned_date;
    if (customRange) {
      if (!date || (dateFrom && date < dateFrom) || (dateTo && date > dateTo)) return false;
    } else if (period !== 'all') {
      if (item.status === 'sent') {
        if (!date || date < completedSince || date > today) return false;
      } else if (date && date > until) return false;
    }
    if (query && !`${item.title} ${item.description || ''}`.toLocaleLowerCase('nl-NL').includes(query)) return false;
    if (channel && !item.channel_ids.includes(channel)) return false;
    if (assignee === 'unassigned' ? Boolean(item.assignee_id) : assignee && String(item.assignee_id) !== assignee) return false;
    return !overdueOnly || isPlanningOverdue(item, today);
  });
}

export function sortPlanningItems(items, completed = false) {
  return [...items].sort((a, b) => {
    const dateOrder = completed
      ? (b.actual_date || '').localeCompare(a.actual_date || '')
      : (a.planned_date || '9999-12-31').localeCompare(b.planned_date || '9999-12-31');
    return dateOrder || a.title.localeCompare(b.title, 'nl') || a.id - b.id;
  });
}

export function planningMovePayload(item, status) {
  if (!openPlanningStatuses.includes(item.status) || !openPlanningStatuses.includes(status) || item.status === status) return null;
  return { status, version: item.modified_gmt };
}
