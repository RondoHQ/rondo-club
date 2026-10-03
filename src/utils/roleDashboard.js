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

export function getDashboardMatchPage(matches, { date = 'all', query = '', page = 0, pageSize = 6 } = {}) {
  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('nl-NL');
  const terms = normalize(query.trim()).split(/\s+/).filter(Boolean);
  const filtered = matches.filter(match => (date === 'all' || match.date === date)
    && terms.every(term => normalize(`${match.home_team} ${match.away_team}`).includes(term)));
  const pages = Math.ceil(filtered.length / pageSize);
  const current = Math.max(0, Math.min(page, pages - 1));
  const start = current * pageSize;
  return { items: filtered.slice(start, start + pageSize), total: filtered.length, pages, page: current, start };
}

export function moveDashboardBlock(order, index, direction) {
  const target = index + direction;
  if (target < 0 || target >= order.length) return order;
  const next = [...order];
  [next[index], next[target]] = [next[target], next[index]];
  return next;
}

/** A custom saved order remains a single ordered grid, including keyboard/reading order. */
export function hasDefaultDashboardOrder(layout) {
  return Array.isArray(layout.defaults)
    && layout.order.length === layout.defaults.length
    && layout.order.every((id, index) => id === layout.defaults[index]);
}

export function getBoardSummary(data, visibleBlocks) {
  const items = [];
  const add = (label, value, description, positive = false) => {
    if (Number.isFinite(value)) items.push({ label, value, description, positive });
  };
  if (visibleBlocks.includes('membership') && data.membership) {
    add('Spelende bondsleden', data.membership.active, 'Huidige ledenstand');
    add('Instroom', data.membership.joined, `Seizoen ${data.membership.season}`, true);
  }
  if (visibleBlocks.includes('volunteers') && data.volunteers) {
    add('Open vrijwilligersplekken', data.volunteers.open_spots, `Komende ${data.volunteers.window_days} dagen`);
  }
  if (visibleBlocks.includes('vog') && data.vog) {
    const { not_submitted_to_justis: missing, submitted_to_justis: requested } = data.vog;
    if (Number.isFinite(missing) && Number.isFinite(requested)) {
      add('VOG ontbreekt of verlopen', missing + requested, `${requested.toLocaleString('nl-NL')} aangevraagd bij Justis`);
    }
  }
  return items;
}
