import { useEffect, useMemo, useState } from 'react';
import { Wifi, WifiOff } from 'lucide-react';
import NarrowcastingScene from './NarrowcastingScenes';
import { buildPlaylistScenes } from './playlistScenes';

const TOKEN_KEY = 'rondoPlayerToken';
const CONFIG_KEY = 'rondoPlayerConfig';
const FEED_KEY = 'rondoPlayerMatchdayFeed';
const PLAYLIST_KEY = 'rondoPlayerPlaylist';

function readStoredConfig() {
  try {
    return JSON.parse(localStorage.getItem(CONFIG_KEY) || 'null');
  } catch {
    return null;
  }
}

function readStoredFeed() {
  try {
    return JSON.parse(localStorage.getItem(FEED_KEY) || 'null');
  } catch {
    return null;
  }
}

function readStoredPlaylist() {
  try {
    return JSON.parse(localStorage.getItem(PLAYLIST_KEY) || 'null');
  } catch {
    return null;
  }
}

function resolveToken() {
  const hash = new URLSearchParams(window.location.hash.replace(/^#/, ''));
  const supplied = hash.get('token');
  if (supplied) {
    localStorage.setItem(TOKEN_KEY, supplied);
    window.history.replaceState({}, document.title, `${window.location.pathname}${window.location.search}`);
    return supplied;
  }
  return localStorage.getItem(TOKEN_KEY) || '';
}

function apiUrl(path) {
  const root = (window.rondoConfig?.apiUrl || '/wp-json/').replace(/\/$/, '');
  return `${root}${path}`;
}

function normalizedHex(value, fallback) {
  const match = String(value || '').trim().match(/^#([\da-f]{3}|[\da-f]{6})$/i);
  if (!match) return fallback;
  if (match[1].length === 6) return `#${match[1].toLowerCase()}`;
  return `#${match[1].split('').map((character) => character.repeat(2)).join('').toLowerCase()}`;
}

function hexRgb(value) {
  const hex = normalizedHex(value, '#0891b2').slice(1);
  return [0, 2, 4].map((index) => Number.parseInt(hex.slice(index, index + 2), 16));
}

function mixHex(value, target, weight) {
  const sourceRgb = hexRgb(value);
  const targetRgb = hexRgb(target);
  const mixed = sourceRgb.map((component, index) => Math.round(component * (1 - weight) + targetRgb[index] * weight));
  return `#${mixed.map((component) => component.toString(16).padStart(2, '0')).join('')}`;
}

function isDarkHex(value) {
  const [red, green, blue] = hexRgb(value).map((component) => component / 255);
  const luminance = 0.2126 * red + 0.7152 * green + 0.0722 * blue;
  return luminance < 0.52;
}

export default function NarrowcastingDisplay() {
  const isPreview = new URLSearchParams(window.location.search).get('preview') === '1';
  const [config, setConfig] = useState(() => (isPreview ? null : readStoredConfig()));
  const [feed, setFeed] = useState(() => (isPreview ? null : readStoredFeed()));
  const [playlist, setPlaylist] = useState(() => (isPreview ? null : readStoredPlaylist()));
  const [connected, setConnected] = useState(false);
  const [loading, setLoading] = useState(isPreview);
  const [loadError, setLoadError] = useState('');
  const [now, setNow] = useState(new Date());
  const [sceneIndex, setSceneIndex] = useState(0);
  const token = useMemo(() => (isPreview ? '' : resolveToken()), [isPreview]);

  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    if (!token && !isPreview) return undefined;
    let active = true;

    const loadConfig = async () => {
      try {
        const path = isPreview
          ? '/rondo/v1/narrowcasting/preview'
          : '/rondo/v1/narrowcasting/devices/me/config';
        const headers = isPreview
          ? { 'X-WP-Nonce': window.rondoConfig?.nonce || '' }
          : { 'X-Rondo-Device-Token': token };
        const response = await fetch(apiUrl(path), {
          headers,
          cache: 'no-store',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const nextConfig = await response.json();
        if (!active) return;
        setConfig(nextConfig);
        setConnected(true);
        setLoadError('');
        if (!isPreview) localStorage.setItem(CONFIG_KEY, JSON.stringify(nextConfig));

        const selectedPlaylist = new URLSearchParams(window.location.search).get('playlist');
        const playlistPath = isPreview
          ? `/rondo/v1/narrowcasting/preview/playlist${selectedPlaylist ? `?playlist_id=${encodeURIComponent(selectedPlaylist)}` : ''}`
          : '/rondo/v1/narrowcasting/devices/me/playlist';
        const playlistResponse = await fetch(apiUrl(playlistPath), { headers, cache: 'no-store' });
        if (playlistResponse.ok) {
          const nextPlaylist = await playlistResponse.json();
          if (!active) return;
          setPlaylist(nextPlaylist);
          if (!isPreview) localStorage.setItem(PLAYLIST_KEY, JSON.stringify(nextPlaylist));
        }

        const feedPath = `/rondo/v1/narrowcasting/feeds/matchday${isPreview ? '?preview=1' : ''}`;
        const feedResponse = await fetch(apiUrl(feedPath), {
          headers,
          cache: 'no-store',
        });
        if (feedResponse.ok) {
          const nextFeed = await feedResponse.json();
          if (!active) return;
          setFeed(nextFeed);
          if (!isPreview) localStorage.setItem(FEED_KEY, JSON.stringify(nextFeed));
        }
      } catch {
        if (active) {
          setConnected(false);
          if (isPreview) setLoadError('Log in als beheerder om het Club TV-voorbeeld te bekijken.');
        }
      } finally {
        if (active) setLoading(false);
      }
    };

    loadConfig();
    const timer = isPreview ? null : window.setInterval(loadConfig, 60000);
    return () => {
      active = false;
      if (timer) window.clearInterval(timer);
    };
  }, [isPreview, token]);

  const scenes = useMemo(
    () => buildPlaylistScenes(playlist, feed, config?.pilot_message),
    [config?.pilot_message, feed, playlist],
  );

  useEffect(() => {
    if (!scenes.length) return undefined;
    const duration = Math.max(5, Math.min(120, Number(scenes[sceneIndex]?.duration_seconds) || 12));
    const timer = window.setTimeout(() => {
      setSceneIndex((current) => (current + 1) % scenes.length);
    }, duration * 1000);
    return () => window.clearTimeout(timer);
  }, [sceneIndex, scenes]);

  useEffect(() => {
    setSceneIndex(0);
  }, [config?.pilot_message, feed?.source?.fetched_at, playlist?.content_version]);

  useEffect(() => {
    if (sceneIndex >= scenes.length) setSceneIndex(0);
  }, [sceneIndex, scenes.length]);

  const timezone = config?.timezone || 'Europe/Amsterdam';
  const time = new Intl.DateTimeFormat('nl-NL', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).format(now);
  const date = new Intl.DateTimeFormat('nl-NL', {
    timeZone: timezone,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(now);
  const sourceTime = feed?.source?.fetched_at
    ? new Intl.DateTimeFormat('nl-NL', {
      timeZone: timezone,
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(feed.source.fetched_at))
    : null;
  const feedIsStale = feed?.source?.fresh_until
    ? new Date(feed.source.fresh_until).getTime() < now.getTime()
    : false;

  if (isPreview && loading && !config) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-50 p-8 text-slate-950">
        <p className="text-xl text-slate-600">Voorbeeld laden…</p>
      </main>
    );
  }

  if (isPreview && loadError && !config) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-50 p-8 text-slate-950">
        <div className="max-w-xl text-center">
          <MonitorSetupIcon />
          <h1 className="mt-6 text-4xl font-semibold">Voorbeeld niet beschikbaar</h1>
          <p className="mt-3 text-xl text-slate-600">{loadError}</p>
        </div>
      </main>
    );
  }

  if (!isPreview && !token && !config) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-50 p-8 text-slate-950">
        <div className="max-w-xl text-center">
          <MonitorSetupIcon />
          <h1 className="mt-6 text-4xl font-semibold">Rondo Player</h1>
          <p className="mt-3 text-xl text-slate-600">Deze browser is nog niet door een player gekoppeld.</p>
        </div>
      </main>
    );
  }

  const scene = scenes[sceneIndex] || scenes[0];
  const clubAccent = normalizedHex(config?.branding?.accent_color, '#0891b2');
  const clubBackground = normalizedHex(config?.branding?.background_color, '#ffffff');
  const sceneBackground = normalizedHex(scene?.colors?.background, clubBackground);
  const darkScene = isDarkHex(sceneBackground);
  const sceneText = normalizedHex(scene?.colors?.text, darkScene ? '#ffffff' : '#142219');
  const sceneAccent = normalizedHex(scene?.colors?.accent, clubAccent);
  const sceneAccentRgb = hexRgb(sceneAccent).join(', ');
  const sceneAccentReadable = darkScene ? mixHex(sceneAccent, '#ffffff', 0.42) : mixHex(sceneAccent, '#000000', 0.05);
  const sceneStyle = {
    '--club-accent': clubAccent,
    '--club-background': clubBackground,
    '--scene-accent': sceneAccent,
    '--scene-accent-readable': sceneAccentReadable,
    '--display-text': sceneText,
    '--display-muted': darkScene ? 'rgba(255,255,255,.62)' : 'rgba(20,34,25,.62)',
    '--display-border': darkScene ? 'rgba(255,255,255,.15)' : 'rgba(0,105,53,.18)',
    '--display-surface': darkScene ? 'rgba(0,0,0,.2)' : 'rgba(255,255,255,.78)',
    '--display-surface-strong': darkScene ? 'rgba(255,255,255,.1)' : 'rgba(0,105,53,.08)',
    '--display-danger-bg': darkScene ? 'rgba(239,68,68,.16)' : 'rgba(254,226,226,.78)',
    '--display-danger-border': darkScene ? 'rgba(252,165,165,.28)' : 'rgba(220,38,38,.22)',
    '--display-danger-text': darkScene ? '#fecaca' : '#b91c1c',
    '--display-warning-text': darkScene ? '#fde68a' : '#a16207',
    backgroundColor: sceneBackground,
    color: sceneText,
  };
  const clubLogo = config?.branding?.logo_url;

  return (
    <main className="relative flex min-h-screen overflow-hidden transition-colors duration-700" style={sceneStyle}>
      <div
        className="absolute inset-0"
        style={{
          background: `radial-gradient(circle at 8% 2%, rgba(${sceneAccentRgb}, ${darkScene ? '.28' : '.13'}), transparent 38%), radial-gradient(circle at 94% 100%, rgba(${sceneAccentRgb}, ${darkScene ? '.16' : '.08'}), transparent 42%)`,
        }}
      />
      {clubLogo && (
        <img
          src={clubLogo}
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute right-[-2vw] top-1/2 h-[72vh] w-[48vw] -translate-y-1/2 object-contain opacity-[0.065]"
        />
      )}
      <div className="absolute inset-y-0 left-0 w-[0.7vw] bg-[var(--club-accent)]" />
      <div className="relative flex min-h-screen w-full flex-col justify-between px-[4.4vw] pb-[3vw] pt-[3.2vw]">
        <header className="flex items-center justify-between gap-8">
          <div className="flex items-center gap-[1.5vw]">
            {clubLogo ? (
              <div className="flex h-[6vw] w-[6vw] items-center justify-center rounded-[1vw] border border-[var(--display-border)] bg-[var(--club-background)] p-[0.55vw] shadow-[0_1vw_3vw_rgba(0,0,0,.14)]">
                <img src={clubLogo} alt={`Logo ${config?.club_name || 'club'}`} className="h-full w-full object-contain" />
              </div>
            ) : (
              <div className="flex h-[6vw] w-[6vw] items-center justify-center rounded-[1vw] bg-[var(--club-accent)] text-[2.1vw] font-black uppercase tracking-tight text-white">
                {(config?.club_name || 'Rondo').slice(0, 3)}
              </div>
            )}
            <div>
              <p className="text-[1.05vw] font-bold uppercase tracking-[0.3em] text-[var(--scene-accent-readable)]">Club TV</p>
              <h1 className="mt-[0.45vw] text-[3.1vw] font-bold leading-none tracking-tight">{config?.club_name || 'Rondo'}</h1>
            </div>
          </div>
          <div className={`flex items-center gap-[0.65vw] rounded-full border px-[1.15vw] py-[0.62vw] text-[0.95vw] font-medium backdrop-blur-sm ${connected ? 'border-[var(--display-border)] bg-[var(--display-surface)] text-[var(--display-muted)]' : 'border-amber-500/25 bg-amber-300/10 text-[var(--display-warning-text)]'}`}>
            {connected ? <Wifi className="h-[1.15vw] w-[1.15vw]" /> : <WifiOff className="h-[1.15vw] w-[1.15vw]" />}
            {isPreview ? 'Browserpreview' : connected ? 'Verbonden' : 'Offline · lokaal beeld'}
          </div>
        </header>

        <div key={scene?.id || `${scene?.type}-${sceneIndex}`} className="animate-[fadeIn_500ms_ease-out] py-[1.5vw]"><NarrowcastingScene scene={scene} /></div>

        <footer className="flex items-end justify-between border-t border-[var(--display-border)] pt-[1.4vw]">
          <div>
            <p className="text-[1.35vw] font-medium capitalize text-[var(--display-text)] opacity-80">{date}</p>
            <p className={`mt-[0.35vw] text-[0.82vw] ${feedIsStale ? 'text-[var(--display-warning-text)]' : 'text-[var(--display-muted)]'}`}>
              {config?.name}{config?.location ? ` · ${config.location}` : ''}
              {sourceTime ? ` · Sportlink bijgewerkt om ${sourceTime}${feedIsStale ? ' · verouderd' : ''}` : ''}
            </p>
          </div>
          <time className="font-mono text-[4.5vw] font-semibold tabular-nums tracking-[-0.06em]">{time}</time>
        </footer>
      </div>
      <div className="absolute inset-x-0 bottom-0 h-[0.55vw] bg-[var(--club-accent)]" />
    </main>
  );
}

function MonitorSetupIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 64 64" className="mx-auto h-20 w-20 text-emerald-700" fill="none" stroke="currentColor" strokeWidth="3">
      <rect x="7" y="9" width="50" height="36" rx="4" />
      <path d="M23 55h18M32 45v10" />
      <path d="m25 27 5 5 10-11" />
    </svg>
  );
}
