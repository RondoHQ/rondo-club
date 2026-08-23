export function localDateValue(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function localDateTimeIso(date, time) {
  return new Date(`${date}T${time}:00`).toISOString();
}

export function rangeForDate(date, days = 1) {
  const start = new Date(`${date}T00:00:00`);
  const end = new Date(start);
  end.setDate(end.getDate() + days);
  return { start: start.toISOString(), end: end.toISOString() };
}

export function contextValue(context) {
  return context.type === 'commissie'
    ? `commissie:${context.commissie_id}`
    : `age_group:${context.age_group_key}`;
}

export function contextPayload(context) {
  if (!context) return {};
  return {
    booking_context_type: context.type,
    commissie_id: context.type === 'commissie' ? context.commissie_id : null,
    age_group_key: context.type === 'age_group' ? context.age_group_key : '',
  };
}

export function formatBookingTime(value, options = {}) {
  if (!value) return '';
  return new Intl.DateTimeFormat('nl-NL', {
    day: options.includeDate === false ? undefined : 'numeric',
    month: options.includeDate === false ? undefined : 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

export function groupBookingsByDate(bookings) {
  const groups = new Map();
  for (const booking of bookings) {
    const key = localDateValue(new Date(booking.start_datetime));
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(booking);
  }
  return [...groups.entries()].sort(([left], [right]) => left.localeCompare(right));
}
