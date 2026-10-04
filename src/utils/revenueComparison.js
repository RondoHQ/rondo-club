const DAY = 86400000;
const dayNumber = date => Date.parse(`${date.slice(0, 10)}T00:00:00Z`) / DAY;
const monthNumber = date => Number(date.slice(0, 4)) * 12 + Number(date.slice(5, 7)) - 1;

export function previousYear(date) {
  if (!date) return '';
  const [year, month, day] = date.split('-').map(Number);
  const lastDay = new Date(Date.UTC(year - 1, month, 0)).getUTCDate();
  return `${year - 1}-${String(month).padStart(2, '0')}-${String(Math.min(day, lastDay)).padStart(2, '0')}`;
}

export const periodDays = (from, to) => dayNumber(to) - dayNumber(from) + 1;

export function revenueDifference(current, previous) {
  if (current == null || previous == null) return null;
  const cents = Math.round(current * 100) - Math.round(previous * 100);
  return { amount: cents / 100, percent: previous === 0 ? null : cents / Math.abs(Math.round(previous * 100)) * 100 };
}

export function revenueTotal(rows, field = 'omzet_totaal') {
  return rows.length ? rows.reduce((sum, row) => sum + Math.round(row[field] * 100), 0) / 100 : null;
}

// Align calendar offsets, never array indexes: an absent report must not shift later dates.
export function alignRevenuePeriods(rows, comparisonRows, from, comparisonFrom, group) {
  const number = group === 'month' ? monthNumber : dayNumber;
  const slots = new Map();
  for (const [series, data, start] of [[0, rows, from], [1, comparisonRows, comparisonFrom]]) {
    for (const row of data) {
      const offset = number(row.periode) - number(start);
      if (!slots.has(offset)) slots.set(offset, { offset, current: null, previous: null });
      slots.get(offset)[series === 0 ? 'current' : 'previous'] = row;
    }
  }
  return [...slots.values()].sort((a, b) => a.offset - b.offset);
}
