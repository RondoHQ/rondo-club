import { useEffect, useMemo, useState } from 'react';
import NarrowcastingScene from './NarrowcastingScenes';
import { buildPlaylistScenes, showsDateTimeForScene } from './playlistScenes';
import { rotateSponsors } from './matchdayScenes';
import {
  cacheBustedPath,
  PLAYLIST_REFRESH_INTERVAL_MS,
  retainUnchangedPlaylist,
  SUPPORTING_DATA_REFRESH_INTERVAL_MS,
} from './displayRefresh';
import PresentationReceiver from './PresentationReceiver';

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
  const [loading, setLoading] = useState(isPreview);
  const [loadError, setLoadError] = useState('');
  const [sceneIndex, setSceneIndex] = useState(0);
  const [sponsorRotationIndex, setSponsorRotationIndex] = useState(0);
  const token = useMemo(() => (isPreview ? '' : resolveToken()), [isPreview]);

  useEffect(() => {
    if (!token && !isPreview) return undefined;
    let active = true;
    const headers = isPreview
      ? { 'X-WP-Nonce': window.rondoConfig?.nonce || '' }
      : { 'X-Rondo-Device-Token': token };
    const request = (path) => fetch(apiUrl(cacheBustedPath(path)), { headers, cache: 'no-store' });

    const loadConfig = async () => {
      const path = isPreview
        ? '/rondo/v1/narrowcasting/preview'
        : '/rondo/v1/narrowcasting/devices/me/config';
      const response = await request(path);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const nextConfig = await response.json();
      if (!active) return;
      setConfig(nextConfig);
      if (!isPreview) localStorage.setItem(CONFIG_KEY, JSON.stringify(nextConfig));
    };

    const loadPlaylist = async () => {
      const selectedPlaylist = new URLSearchParams(window.location.search).get('playlist');
      const path = isPreview
        ? `/rondo/v1/narrowcasting/preview/playlist${selectedPlaylist ? `?playlist_id=${encodeURIComponent(selectedPlaylist)}` : ''}`
        : '/rondo/v1/narrowcasting/devices/me/playlist';
      const response = await request(path);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const nextPlaylist = await response.json();
      if (!active) return;
      setPlaylist((current) => retainUnchangedPlaylist(current, nextPlaylist));
      if (!isPreview) localStorage.setItem(PLAYLIST_KEY, JSON.stringify(nextPlaylist));
    };

    const loadFeed = async () => {
      const path = `/rondo/v1/narrowcasting/feeds/matchday${isPreview ? '?preview=1' : ''}`;
      const response = await request(path);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const nextFeed = await response.json();
      if (!active) return;
      setFeed(nextFeed);
      if (!isPreview) localStorage.setItem(FEED_KEY, JSON.stringify(nextFeed));
    };

    const loadInitialData = async () => {
      try {
        await Promise.all([loadConfig(), loadPlaylist(), loadFeed()]);
        if (active) setLoadError('');
      } catch {
        if (active) {
          if (isPreview) setLoadError('Log in als beheerder om het Club TV-voorbeeld te bekijken.');
        }
      } finally {
        if (active) setLoading(false);
      }
    };

    const refreshPlaylist = () => loadPlaylist().catch(() => {});
    const refreshSupportingData = () => Promise.all([loadConfig(), loadFeed()]).catch(() => {});

    loadInitialData();
    const playlistTimer = isPreview ? null : window.setInterval(refreshPlaylist, PLAYLIST_REFRESH_INTERVAL_MS);
    const supportingDataTimer = isPreview ? null : window.setInterval(refreshSupportingData, SUPPORTING_DATA_REFRESH_INTERVAL_MS);
    return () => {
      active = false;
      if (playlistTimer) window.clearInterval(playlistTimer);
      if (supportingDataTimer) window.clearInterval(supportingDataTimer);
    };
  }, [isPreview, token]);

  const scenes = useMemo(
    () => buildPlaylistScenes(playlist, feed, config?.pilot_message),
    [config?.pilot_message, feed, playlist],
  );
  const sceneDurationSeconds = Math.max(5, Math.min(120, Number(scenes[sceneIndex]?.duration_seconds) || 12));
  const sceneSequenceVersion = `${playlist?.content_version || ''}:${feed?.source?.fetched_at || ''}:${config?.pilot_message || ''}`;

  useEffect(() => {
    if (!scenes.length) return undefined;
    const timer = window.setTimeout(() => {
      setSceneIndex((current) => (current + 1) % scenes.length);
      setSponsorRotationIndex((current) => current + 1);
    }, sceneDurationSeconds * 1000);
    return () => window.clearTimeout(timer);
  }, [sceneDurationSeconds, sceneIndex, sceneSequenceVersion, scenes.length]);

  useEffect(() => {
    setSceneIndex(0);
    setSponsorRotationIndex(0);
  }, [sceneSequenceVersion]);

  useEffect(() => {
    if (sceneIndex >= scenes.length) setSceneIndex(0);
  }, [sceneIndex, scenes.length]);

  const timezone = config?.timezone || 'Europe/Amsterdam';
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
  const slideTitle = sceneTitle(scene);
  const sponsorLogos = feed?.sponsors?.length
    ? rotateSponsors(feed.sponsors, sponsorRotationIndex)
    : (scene?.sponsorLogos || []);
  const showDateTime = showsDateTimeForScene(scene);

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
              <img src={clubLogo} alt={`Logo ${config?.club_name || 'club'}`} className="h-[7.6vw] w-[7.6vw] object-contain" />
            ) : (
              <div className="flex h-[7.6vw] w-[7.6vw] items-center justify-center text-[2.1vw] font-black uppercase tracking-tight text-[var(--scene-accent-readable)]">
                {(config?.club_name || 'Rondo').slice(0, 3)}
              </div>
            )}
            <h1 className="max-w-[62vw] text-[3.1vw] font-bold leading-[1.04] tracking-tight">{slideTitle}</h1>
          </div>
          <SponsorLogoRow sponsors={sponsorLogos.slice(0, 3)} size="top" />
        </header>

        <div key={scene?.id || `${scene?.type}-${sceneIndex}`} className="animate-[fadeIn_500ms_ease-out] py-[1.5vw]"><NarrowcastingScene scene={scene} /></div>

        <footer className="flex items-end justify-between border-t border-[var(--display-border)] pt-[1.4vw]">
          <SponsorLogoRow sponsors={sponsorLogos.slice(3, 8)} size="bottom" />
          {showDateTime ? <DateTimeFooter timezone={timezone} dateLabel={scene?.dateLabel} /> : <div aria-hidden="true" />}
        </footer>
      </div>
      <div className="absolute inset-x-0 bottom-0 h-[0.55vw] bg-[var(--club-accent)]" />
      {!isPreview && (
        <PresentationReceiver
          enabled={Boolean(config?.presentation_enabled)}
          deviceToken={token}
          displayName={config?.name || 'Club TV'}
          roomPresentation={config?.room_presentation}
        />
      )}
    </main>
  );
}

function DateTimeFooter({ timezone, dateLabel }) {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(timer);
  }, []);

  const time = new Intl.DateTimeFormat('nl-NL', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).format(now);
  const date = dateLabel || new Intl.DateTimeFormat('nl-NL', {
    timeZone: timezone,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(now);

  return (
    <div className="text-right">
      <p className="mb-[0.05vw] text-[1.65vw] font-medium leading-none capitalize text-[var(--display-text)] opacity-80">{date}</p>
      <time className="font-mono text-[4.1vw] font-semibold leading-none tabular-nums tracking-[-0.06em]">{time}</time>
    </div>
  );
}

function sceneTitle(scene) {
  const titles = {
    matches: 'Wedstrijdinformatie',
    cancellations: 'Afgelaste wedstrijden',
    results: 'Recente uitslagen',
    unavailable: 'Wedstrijdinformatie',
    welcome: 'Welkom',
  };
  return scene?.title || scene?.sponsor?.name || titles[scene?.type] || 'Clubinformatie';
}

function SponsorLogoRow({ sponsors, size }) {
  const dimensions = size === 'top' ? 'h-[7.2vw] w-[12.75vw]' : 'h-[6.3vw] w-[11.25vw]';
  if (!sponsors.length) return <div aria-hidden="true" />;
  return (
    <div className="flex items-center gap-[0.8vw]">
      {sponsors.map((sponsor) => (
        <div key={sponsor.id} className={`flex ${dimensions} items-center justify-center rounded-[0.65vw] bg-white/90 p-[0.45vw] shadow-sm`}>
          <img src={sponsor.logo_url} alt={sponsor.name} className="h-full w-full object-contain" />
        </div>
      ))}
    </div>
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
