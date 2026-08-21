export const PLAYLIST_REFRESH_INTERVAL_MS = 10000;
export const SUPPORTING_DATA_REFRESH_INTERVAL_MS = 60000;

export function cacheBustedPath(path, nonce = Date.now()) {
  const separator = path.includes('?') ? '&' : '?';
  return `${path}${separator}rondo_cache_buster=${encodeURIComponent(String(nonce))}`;
}
