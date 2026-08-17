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

        const feedResponse = await fetch(apiUrl('/rondo/v1/narrowcasting/feeds/matchday'), {
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
      <main className="flex min-h-screen items-center justify-center bg-slate-950 p-8 text-white">
        <p className="text-xl text-slate-300">Voorbeeld laden…</p>
      </main>
    );
  }

  if (isPreview && loadError && !config) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-950 p-8 text-white">
        <div className="max-w-xl text-center">
          <MonitorSetupIcon />
          <h1 className="mt-6 text-4xl font-semibold">Voorbeeld niet beschikbaar</h1>
          <p className="mt-3 text-xl text-slate-300">{loadError}</p>
        </div>
      </main>
    );
  }

  if (!isPreview && !token && !config) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-950 p-8 text-white">
        <div className="max-w-xl text-center">
          <MonitorSetupIcon />
          <h1 className="mt-6 text-4xl font-semibold">Rondo Player</h1>
          <p className="mt-3 text-xl text-slate-300">Deze browser is nog niet door een player gekoppeld.</p>
        </div>
      </main>
    );
  }

  const scene = scenes[sceneIndex] || scenes[0];
  const clubAccent = normalizedHex(config?.branding?.accent_color, '#0891b2');
  const clubBackground = normalizedHex(config?.branding?.background_color, '#ffffff');
  const accentRgb = hexRgb(clubAccent).join(', ');
  const clubAccentSoft = mixHex(clubAccent, '#ffffff', 0.48);
  const clubAccentDark = mixHex(clubAccent, '#000000', 0.35);
  const sceneStyle = {
    '--club-accent': clubAccent,
    '--club-accent-rgb': accentRgb,
    '--club-accent-soft': clubAccentSoft,
    '--club-accent-dark': clubAccentDark,
    '--club-background': clubBackground,
    backgroundColor: scene?.colors?.background || '#09090b',
    color: scene?.colors?.text || '#ffffff',
  };
  const clubLogo = config?.branding?.logo_url;

  return (
    <main className="relative flex min-h-screen overflow-hidden bg-slate-950 text-white transition-colors duration-700" style={sceneStyle}>
      <div
        className="absolute inset-0"
        style={{
          background: `radial-gradient(circle at 8% 2%, rgba(${accentRgb}, .34), transparent 34%), radial-gradient(circle at 94% 100%, rgba(${accentRgb}, .18), transparent 38%), linear-gradient(120deg, rgba(255,255,255,.035), transparent 38%)`,
        }}
      />
      <div className="absolute inset-0 opacity-[0.06] [background-image:linear-gradient(rgba(255,255,255,.16)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.16)_1px,transparent_1px)] [background-size:4vw_4vw]" />
      {clubLogo && (
        <img
          src={clubLogo}
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute right-[-2vw] top-1/2 h-[72vh] w-[48vw] -translate-y-1/2 object-contain opacity-[0.055]"
        />
      )}
      <div className="absolute inset-y-0 left-0 w-[0.7vw] bg-[var(--club-accent)]" />
      <div className="relative flex min-h-screen w-full flex-col justify-between px-[4.4vw] pb-[3vw] pt-[3.2vw]">
        <header className="flex items-center justify-between gap-8">
          <div className="flex items-center gap-[1.5vw]">
            {clubLogo ? (
              <div className="flex h-[6vw] w-[6vw] items-center justify-center rounded-[1vw] bg-[var(--club-background)] p-[0.55vw] shadow-[0_1vw_3vw_rgba(0,0,0,.28)]">
                <img src={clubLogo} alt={`Logo ${config?.club_name || 'club'}`} className="h-full w-full object-contain" />
              </div>
            ) : (
              <div className="flex h-[6vw] w-[6vw] items-center justify-center rounded-[1vw] bg-[var(--club-accent)] text-[2.1vw] font-black uppercase tracking-tight text-white">
                {(config?.club_name || 'Rondo').slice(0, 3)}
              </div>
            )}
            <div>
              <p className="text-[1.05vw] font-bold uppercase tracking-[0.3em] text-[var(--club-accent-soft)]">Club TV</p>
              <h1 className="mt-[0.45vw] text-[3.1vw] font-bold leading-none tracking-tight">{config?.club_name || 'Rondo'}</h1>
            </div>
          </div>
          <div className={`flex items-center gap-[0.65vw] rounded-full border px-[1.15vw] py-[0.62vw] text-[0.95vw] font-medium backdrop-blur-sm ${connected ? 'border-white/15 bg-white/8 text-white/75' : 'border-amber-300/25 bg-amber-300/10 text-amber-100'}`}>
            {connected ? <Wifi className="h-[1.15vw] w-[1.15vw]" /> : <WifiOff className="h-[1.15vw] w-[1.15vw]" />}
            {isPreview ? 'Browserpreview' : connected ? 'Verbonden' : 'Offline · lokaal beeld'}
          </div>
        </header>

        <div key={scene?.id || `${scene?.type}-${sceneIndex}`} className="animate-[fadeIn_500ms_ease-out] py-[1.5vw]"><NarrowcastingScene scene={scene} /></div>

        <footer className="flex items-end justify-between border-t border-white/12 pt-[1.4vw]">
          <div>
            <p className="text-[1.35vw] font-medium capitalize text-white/80">{date}</p>
            <p className={`mt-[0.35vw] text-[0.82vw] ${feedIsStale ? 'text-amber-300' : 'text-white/40'}`}>
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
    <svg aria-hidden="true" viewBox="0 0 64 64" className="mx-auto h-20 w-20 text-cyan-300" fill="none" stroke="currentColor" strokeWidth="3">
      <rect x="7" y="9" width="50" height="36" rx="4" />
      <path d="M23 55h18M32 45v10" />
      <path d="m25 27 5 5 10-11" />
    </svg>
  );
}
