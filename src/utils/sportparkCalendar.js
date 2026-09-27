// Calendar dates stay local strings: never convert an all-day date through UTC.
export function calendarDate(year, month, day) {
  return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function monthDays(year, month) {
  const offset = (new Date(year, month, 1).getDay() + 6) % 7;
  return [
    ...Array(offset).fill(null),
    ...Array.from({ length: new Date(year, month + 1, 0).getDate() }, (_, i) => calendarDate(year, month, i + 1)),
  ];
}

export function closuresOnDate(date, closures) {
  return closures.filter(({ fields }) => fields.starts_at <= date && fields.ends_at >= date);
}
