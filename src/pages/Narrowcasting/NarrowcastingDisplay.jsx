import { useEffect, useMemo, useState } from 'react';
import { Wifi, WifiOff } from 'lucide-react';

const TOKEN_KEY = 'rondoPlayerToken';
const CONFIG_KEY = 'rondoPlayerConfig';

function readStoredConfig() {
  try {
    return JSON.parse(localStorage.getItem(CONFIG_KEY) || 'null');
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

export default function NarrowcastingDisplay() {
  const isPreview = new URLSearchParams(window.location.search).get('preview') === '1';
  const [config, setConfig] = useState(() => (isPreview ? null : readStoredConfig()));
  const [connected, setConnected] = useState(false);
  const [loading, setLoading] = useState(isPreview);
  const [loadError, setLoadError] = useState('');
  const [now, setNow] = useState(new Date());
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

  return (
    <main className="relative flex min-h-screen overflow-hidden bg-slate-950 text-white">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(6,182,212,0.24),transparent_45%),radial-gradient(circle_at_bottom_left,rgba(37,99,235,0.26),transparent_48%)]" />
      <div className="relative flex min-h-screen w-full flex-col justify-between p-[4vw]">
        <header className="flex items-start justify-between gap-8">
          <div>
            <p className="text-[1.5vw] font-medium uppercase tracking-[0.24em] text-cyan-300">Club TV</p>
            <h1 className="mt-[1vw] text-[4vw] font-semibold leading-none">{config?.club_name || 'Rondo'}</h1>
          </div>
          <div className={`flex items-center gap-[0.7vw] rounded-full border px-[1.3vw] py-[0.7vw] text-[1.1vw] ${connected ? 'border-emerald-400/40 bg-emerald-400/10 text-emerald-200' : 'border-slate-400/30 bg-slate-400/10 text-slate-300'}`}>
            {connected ? <Wifi className="h-[1.4vw] w-[1.4vw]" /> : <WifiOff className="h-[1.4vw] w-[1.4vw]" />}
            {isPreview ? 'Browserpreview' : connected ? 'Verbonden' : 'Offline · lokaal beeld'}
          </div>
        </header>

        <section>
          <p className="max-w-[70vw] text-[4.5vw] font-semibold leading-[1.08] tracking-tight">
            {config?.pilot_message || 'Rondo Player is verbonden'}
          </p>
          <p className="mt-[2vw] text-[2vw] text-slate-300">
            {config?.name}{config?.location ? ` · ${config.location}` : ''}
          </p>
        </section>

        <footer className="flex items-end justify-between">
          <p className="text-[1.5vw] capitalize text-slate-300">{date}</p>
          <time className="font-mono text-[5vw] font-medium tabular-nums tracking-tight">{time}</time>
        </footer>
      </div>
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
