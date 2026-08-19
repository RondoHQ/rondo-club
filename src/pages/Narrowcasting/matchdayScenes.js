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
  chunks(results, 6).forEach((items, page) => scenes.push({ type: 'results', items, page }));

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

export function rotateSponsors(sponsors, sceneIndex, slots = 6) {
  if (!sponsors.length) return [];
  const count = Math.min(slots, sponsors.length);
  const offset = (sceneIndex * slots) % sponsors.length;
  return Array.from({ length: count }, (_, index) => sponsors[(offset + index) % sponsors.length]);
}
