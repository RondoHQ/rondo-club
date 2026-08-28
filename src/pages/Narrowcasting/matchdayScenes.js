function chunks(items, size) {
  const pages = [];
  for (let index = 0; index < items.length; index += size) {
    pages.push(items.slice(index, index + size));
  }
  return pages;
}

function matchdayLabel(value) {
  if (!value) return 'vandaag';
  const date = new Date(`${value}T12:00:00`);
  if (Number.isNaN(date.getTime())) return 'vandaag';
  return new Intl.DateTimeFormat('nl-NL', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(date);
}

export function buildMatchdayScenes(feed, fallbackMessage) {
  if (!feed?.configured) {
    return [{ type: 'welcome', message: fallbackMessage || 'Rondo Club TV is klaar voor gebruik' }];
  }

  const fetchedAt = feed.source?.fetched_at ? new Date(feed.source.fetched_at).getTime() : 0;
  const cacheExpired = !fetchedAt || Date.now() - fetchedAt > 24 * 60 * 60 * 1000;
  if (cacheExpired && feed.source?.last_error) {
    return [{ type: 'unavailable' }];
  }

  const scenes = [];
  const dateLabel = matchdayLabel(feed.target_date);
  const matches = (feed.matches || []).filter((match) => match.club_side !== 'away');
  const cancellations = feed.cancellations || [];
  const results = (feed.results || []).filter((result) => result.result);

  chunks(matches, 5).forEach((items, page) => scenes.push({ type: 'matches', items, page, dateLabel }));
  chunks(cancellations, 6).forEach((items, page) => scenes.push({ type: 'cancellations', items, page, dateLabel }));
  chunks(results, 5).forEach((items, page) => scenes.push({ type: 'results', items, page }));

  if (scenes.length === 0) {
    const message = dateLabel === 'vandaag'
      ? 'Vandaag zijn er geen thuiswedstrijden'
      : `Op ${dateLabel} zijn er geen thuiswedstrijden`;
    scenes.push({ type: 'welcome', message });
  }

  return scenes.map((scene, index) => ({
    ...scene,
    sponsorLogos: rotateSponsors(feed.sponsors || [], index),
  }));
}

export function rotateSponsors(sponsors, sceneIndex, slots = 8) {
  const capacity = Math.max(0, Number(slots) || 0);
  if (!sponsors.length || capacity === 0) return [];

  const eligible = sponsors.filter((sponsor) => sponsor.logo_url && Number(sponsor.club_tv_priority) > 0);
  const always = eligible.filter((sponsor) => Number(sponsor.club_tv_priority) === 3).slice(0, capacity);
  const remainingSlots = capacity - always.length;
  if (remainingSlots === 0) return always;

  const rotating = eligible.filter((sponsor) => {
    const priority = Number(sponsor.club_tv_priority);
    return priority === 1 || priority === 2;
  });
  const tickets = rotating.flatMap((sponsor) => (
    Array.from({ length: Number(sponsor.club_tv_priority) === 2 ? 3 : 1 }, () => sponsor)
  ));
  if (!tickets.length) return always;

  const selected = [];
  const selectedIds = new Set(always.map((sponsor) => sponsor.id));
  const offset = Math.max(0, Number(sceneIndex) || 0) % tickets.length;
  for (let step = 0; step < tickets.length && selected.length < remainingSlots; step += 1) {
    const sponsor = tickets[(offset + step) % tickets.length];
    if (selectedIds.has(sponsor.id)) continue;
    selectedIds.add(sponsor.id);
    selected.push(sponsor);
  }

  return [...always, ...selected];
}
