const SELECTION_STORAGE_VERSION = 1;

export const ACCESS_EVENT_SELECTION_KEY = 'rondo-access-event-selection';

export function chooseAccessMatch(matches, storedSelection, localDate) {
  const candidates = Array.isArray(matches) ? matches.filter((match) => match.is_selectable) : [];
  const activeMatches = candidates.filter((match) => match.is_active);
  if (
    storedSelection?.version === SELECTION_STORAGE_VERSION
    && storedSelection?.date === localDate
    && storedSelection?.sourceId
  ) {
    const storedMatch = candidates.find((match) => match.id === storedSelection.sourceId);
    if (storedMatch && (storedMatch.is_active || activeMatches.length === 0)) {
      return storedMatch;
    }
  }

  return activeMatches.length === 1 ? activeMatches[0] : null;
}

export function createStoredAccessMatch(match, localDate) {
  return {
    version: SELECTION_STORAGE_VERSION,
    date: localDate,
    sourceId: match.id,
  };
}

export function readStoredAccessMatch(storage) {
  try {
    const value = storage?.getItem(ACCESS_EVENT_SELECTION_KEY);
    return value ? JSON.parse(value) : null;
  } catch {
    return null;
  }
}
