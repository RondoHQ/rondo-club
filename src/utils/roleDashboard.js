/** Combine fixtures from several teams without duplicating cancellations. */
export function combineDashboardMatches(feeds, today, endDate) {
  const matches = new Map();
  for (const { data, teamId } of feeds) {
    for (const match of data?.matches || []) {
      if (match.date < today || match.date > endDate) continue;
      const previous = matches.get(String(match.id));
      matches.set(String(match.id), {
        ...previous, ...match,
        cancelled: Boolean(previous?.cancelled || match.cancelled),
        team_ids: [...new Set([...(previous?.team_ids || []), ...(teamId ? [teamId] : [])])],
      });
    }
  }
  return [...matches.values()].sort((a, b) => a.starts_at.localeCompare(b.starts_at));
}

export function dashboardRoomLabel(value) {
  return String(value ?? '').trim() || 'Nog niet ingevuld';
}

export function moveDashboardBlock(order, index, direction) {
  const target = index + direction;
  if (target < 0 || target >= order.length) return order;
  const next = [...order];
  [next[index], next[target]] = [next[target], next[index]];
  return next;
}
