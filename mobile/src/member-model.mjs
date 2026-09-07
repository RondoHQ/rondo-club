export const personName = (person) => [person.fields?.first_name, person.fields?.infix, person.fields?.last_name].filter(Boolean).join(' ') || 'Clublid';

export function clubToday(timeZone = 'Europe/Amsterdam', now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(now);
  return ['year', 'month', 'day'].map((type) => parts.find((part) => part.type === type).value).join('-');
}

export function clubNow(timeZone = 'Europe/Amsterdam', now = new Date()) {
  const time = new Intl.DateTimeFormat('en-GB', { timeZone, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(now);
  return `${clubToday(timeZone, now)} ${time}`;
}

export function monthDays(month) {
  const [year, number] = month.split('-').map(Number);
  const first = new Date(Date.UTC(year, number - 1, 1));
  const count = new Date(Date.UTC(year, number, 0)).getUTCDate();
  return { blanks: (first.getUTCDay() + 6) % 7, dates: Array.from({ length: count }, (_, i) => `${month}-${String(i + 1).padStart(2, '0')}`) };
}

export function moveMonth(month, delta) {
  const [year, number] = month.split('-').map(Number);
  return new Date(Date.UTC(year, number - 1 + delta, 1)).toISOString().slice(0, 7);
}

export function dateLabel(date, options = { weekday: 'long', day: 'numeric', month: 'long' }) {
  return new Intl.DateTimeFormat('nl-NL', { ...options, timeZone: 'UTC' }).format(new Date(`${date.slice(0, 10)}T12:00:00Z`));
}

// WordPress shift datetimes are local wall times in the club timezone, not UTC instants.
export const shiftTime = (shift) => `${shift.start_datetime?.slice(11, 16) || ''}–${shift.end_datetime?.slice(11, 16) || ''}`;
export const orderedShifts = (shifts) => [...shifts].sort((a, b) => a.start_datetime.localeCompare(b.start_datetime) || a.id - b.id);
export const upcomingShifts = (shifts, now) => orderedShifts(shifts.filter((s) => ['open', 'vol'].includes(s.status) && s.start_datetime >= now));
export const availableShifts = (day) => orderedShifts((day?.shifts || []).filter((s) => s.can_signup === true && !s.is_signed_up));

export function calendarIndex(days, ownShifts) {
  const mine = new Set(ownShifts.filter((s) => ['open', 'vol'].includes(s.status)).map((s) => s.start_datetime.slice(0, 10)));
  const index = new Map(days.map((day) => [day.date, { ...day, available: availableShifts(day).length, mine: mine.has(day.date) || day.shifts.some((s) => s.is_signed_up) }]));
  for (const date of mine) if (!index.has(date)) index.set(date, { date, shifts: [], available: 0, mine: true });
  return index;
}

// Only fixed club pages are opened externally. Never forward server-provided wallet URLs/nonces.
export function clubPage(club, page) {
  const paths = { volunteer: '/vrijwillig', profile: '/mijn-gegevens' };
  if (!paths[page]) throw new Error('Onbekende clubpagina.');
  return new URL(paths[page], club.url).href;
}

export function safeClubLogo(value, club) {
  try {
    const url = new URL(value);
    return url.origin === club.url && url.protocol === 'https:' && !url.username && !url.password ? url.href : '';
  } catch { return ''; }
}
