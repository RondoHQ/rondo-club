function chunks(items, size) {
  const pages = [];
  for (let index = 0; index < items.length; index += size) {
    pages.push(items.slice(index, index + size));
  }
  return pages;
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
  const matches = (feed.matches || []).filter((match) => match.club_side !== 'away');
  const roomMatches = matches.filter((match) => (
    match.dressing_rooms?.home
    || match.dressing_rooms?.away
    || match.dressing_rooms?.referee
  ));
  const cancellations = feed.cancellations || [];
  const results = (feed.results || []).filter((result) => result.result);

  chunks(matches, 6).forEach((items, page) => scenes.push({ type: 'matches', items, page }));
  chunks(roomMatches, 5).forEach((items, page) => scenes.push({ type: 'rooms', items, page }));
  chunks(cancellations, 6).forEach((items, page) => scenes.push({ type: 'cancellations', items, page }));
  chunks(results, 6).forEach((items, page) => scenes.push({ type: 'results', items, page }));

  if (scenes.length === 0) {
    scenes.push({ type: 'welcome', message: 'Vandaag zijn er geen thuiswedstrijden' });
  }

  return scenes;
}
