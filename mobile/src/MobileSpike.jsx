import { useCallback, useEffect, useRef, useState } from 'react';
import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { openLoginBrowser, closeLoginBrowser } from './login-browser.mjs';
import { PILOT, PILOT_CLUBS, PROTOCOL } from './deployment.mjs';
import { validateClubs } from './auth.mjs';
import { request } from './transport.mjs';
import { DeviceSession } from './device-session.mjs';
import { vault } from './vault.mjs';
import MemberApp from './MemberApp';
import { safeClubLogo } from './member-model.mjs';
import './style.css';

const clubs = validateClubs(PILOT ? PILOT_CLUBS : JSON.parse(import.meta.env.VITE_SPIKE_CLUBS || '[]'));
const auth = new DeviceSession({ vault, request, clubs });

export default function MobileSpike() {
  const [club, setClub] = useState(null);
  const [search, setSearch] = useState('');
  const [screen, setScreen] = useState('restoring');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [profile, setProfile] = useState(null);
  const alive = useRef(true);

  const reset = useCallback(() => {
    auth.clear();
    setProfile(null);
    setBusy(false);
    setError('');
    setScreen('clubs');
    setClub(null);
  }, []);

  const startup = useRef(null);
  const callbackSeen = useRef(false);
  const finishCallback = useRef(null);

  const showPendingOrClubs = useCallback(() => {
    setProfile(null);
    if (auth.pending) {
      setClub(auth.pending.club);
      setScreen('login');
    } else reset();
  }, [reset]);

  const loadHome = useCallback(async (session, generation) => {
    const [me, metadata] = await Promise.all([auth.read('/read?resource=me'), request(session.club, '/config')]);
    if (!alive.current || generation !== auth.generation) return;
    if (metadata.protocol !== PROTOCOL || metadata.club_url !== session.club.url) throw new Error('Deze club is nog niet beschikbaar voor de proef.');
    const selected = { ...session.club, timeZone: metadata.timezone, logoUrl: session.club.logoUrl || safeClubLogo(metadata.logo_url, session.club) };
    auth.session.club = selected;
    setClub(selected);
    setProfile(me);
    setError('');
    setScreen('home');
  }, []);

  const restore = useCallback(async (initialization) => {
    setBusy(true);
    setError('');
    setProfile(null);
    setScreen('restoring');
    try {
      const pending = initialization || auth.restore();
      if (!initialization) startup.current = pending;
      const session = await pending;
      // The callback handler owns completion when a native URL arrived during startup.
      if (initialization && callbackSeen.current) return;
      if (session) await loadHome(session, auth.generation);
      else showPendingOrClubs();
    } catch (failure) {
      if (!alive.current) return;
      setScreen(failure.status === 401 ? 'clubs' : 'recover');
      if (failure.status === 401) setClub(null);
      setError(failure.message);
    } finally { if (alive.current) setBusy(false); }
  }, [loadHome, showPendingOrClubs]);

  useEffect(() => {
    alive.current = true;
    let listener;
    const seen = new Set();
    startup.current = auth.restore();
    async function handle(url) {
      if (!alive.current || seen.has(url)) return;
      seen.add(url);
      callbackSeen.current = true;
      let generation;
      let selected;
      setBusy(true);
      try {
        await startup.current;
        if (!alive.current) return;
        generation = auth.generation;
        selected = auth.pending?.club;
        // iOS can offer a previously handled launch URL again after rebuilding its WebView.
        // An existing authenticated session takes precedence when no login is pending.
        if (!auth.pending && auth.session) { await loadHome(auth.session, generation); return; }
        const session = await auth.finish(url);
        await closeLoginBrowser().catch(() => {});
        await loadHome(session, generation);
      } catch (failure) {
        if (!alive.current || (generation !== undefined && generation !== auth.generation && failure.code !== 'login_cancelled')) return;
        if (failure.code === 'login_cancelled') showPendingOrClubs();
        else if (auth.pending) showPendingOrClubs();
        else if (!auth.session) { setClub(selected || null); setScreen(selected ? 'confirm' : 'recover'); }
        else { setProfile(null); setScreen('recover'); }
        setError(failure.message);
      } finally { if (alive.current) setBusy(false); }
    }
    finishCallback.current = handle;
    App.addListener('appUrlOpen', ({ url }) => handle(url)).then((value) => {
      if (!alive.current) value.remove();
      else listener = value;
    });
    App.getLaunchUrl().then((value) => { if (value?.url) handle(value.url); });
    restore(startup.current);
    return () => { alive.current = false; finishCallback.current = null; listener?.remove(); };
  }, [loadHome, restore, showPendingOrClubs]);

  async function openLoginWindow(url) {
    const callback = await openLoginBrowser(url);
    if (callback && alive.current) await finishCallback.current?.(callback);
  }

  async function login() {
    setBusy(true);
    setError('');
    const generation = auth.generation;
    try {
      const metadata = await request(club, '/config');
      if (generation !== auth.generation) return;
      if (metadata.protocol !== PROTOCOL || metadata.club_url !== club.url) throw new Error('Deze club is nog niet beschikbaar voor de proef.');
      if (!Capacitor.isNativePlatform()) throw new Error('Open de geïnstalleerde proefapp om in te loggen.');
      const selected = { ...club, timeZone: metadata.timezone, logoUrl: club.logoUrl || safeClubLogo(metadata.logo_url, club) };
      setClub(selected);
      const url = await auth.start(selected);
      setScreen('login');
      await openLoginWindow(url);
    } catch (failure) {
      setError(failure.message);
    } finally { setBusy(false); }
  }

  async function resumeLogin() {
    setBusy(true);
    setError('');
    try { await openLoginWindow(await auth.resumeLogin()); }
    catch (failure) { setError(failure.message); if (!auth.pending) setScreen('confirm'); }
    finally { setBusy(false); }
  }

  async function logout() {
    setProfile(null);
    setBusy(true);
    setScreen('restoring');
    setError('');
    try {
      await auth.logout();
      await closeLoginBrowser().catch(() => {});
      reset();
    } catch { setScreen('recover'); setError('Uitloggen is niet afgerond. Probeer opnieuw om je opgeslagen aanmelding te verwijderen.'); }
    finally { setBusy(false); }
  }

  const results = clubs.filter((item) => item.name.toLocaleLowerCase('nl').includes(search.toLocaleLowerCase('nl')));
  return <main className={profile ? 'member-shell' : ''}>
    <header><div className="header-brand">{club && <ClubLogo key={club.id} club={club} />}<img className="rondo-wordmark" src="./brand/rondo-wordmark.svg" alt="Rondo" /></div><span className="badge">{PILOT ? (club?.id === 'demo' ? 'Demodata' : 'Pilot') : 'Proefversie'}</span></header>
    {error && <p role="alert" className="error">{error}</p>}
    {screen === 'restoring' && <p role="status">Je aanmelding controleren…</p>}
    {screen === 'recover' && <section><h1>Aanmelding controleren</h1><p>Maak verbinding met je club om verder te gaan, of log uit op dit toestel.</p><button disabled={busy} onClick={() => restore()}>Opnieuw proberen</button><button disabled={busy} className="secondary" onClick={logout}>Uitloggen op dit toestel</button></section>}
    {screen === 'clubs' && <section>
      <p className="eyebrow">Jouw club, dichtbij</p><h1>Welkom bij Rondo</h1><p>Kies je club om in te loggen en je eigen gegevens te bekijken.</p>
      <label htmlFor="search">Zoek je club</label><input id="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Naam van je club" />
      {results.map((item) => <button className="club-card" key={item.id} onClick={() => { setClub(item); setError(''); setScreen('confirm'); }}><strong>{item.name}</strong><small>{new URL(item.url).hostname}</small><span aria-hidden="true">→</span></button>)}
      {!results.length && <p>{clubs.length ? 'Geen club gevonden. Probeer een andere naam.' : 'Er is nog geen testclub ingesteld voor deze proefversie.'}</p>}
    </section>}
    {screen === 'confirm' && <section><h1>Inloggen bij {club.name}</h1><p>Je logt in via de website van je club. Daarna keer je terug naar Rondo.</p><p className="domain">{new URL(club.url).hostname}</p><button disabled={busy} onClick={login}>{busy ? 'Club controleren…' : 'Inloggen bij mijn club'}</button><button className="secondary" onClick={reset}>Terug naar clubkeuze</button></section>}
    {screen === 'login' && <section><h1>Rond je aanmelding af</h1><p>Log in via de website van je club. Gebruik je een e-maillink? Open die op dit toestel en kies daarna Verbinden.</p><p>Je kunt de app tussendoor sluiten. Je aanmelding blijft tien minuten beschikbaar.</p><button disabled={busy} onClick={resumeLogin}>Inlogvenster opnieuw openen</button><button disabled={busy} className="secondary" onClick={logout}>Aanmelding annuleren</button></section>}
    {profile && auth.session && <MemberApp session={auth.session} profile={profile} logout={logout} read={(path) => auth.read(path)} changeShift={(id, action, force) => auth.changeShift(id, action, force)} requestWallet={(id, role, provider) => auth.requestWallet(id, role, provider)} changeProfile={(action, values) => auth.changeProfile(action, values)} onExpired={() => { setProfile(null); setScreen('recover'); setError('Je aanmelding is verlopen. Log opnieuw in.'); }} />}
    <footer>Proefversie{profile ? ' · aangemeld op dit toestel' : ''}</footer>
  </main>;
}

function ClubLogo({ club }) {
  const [failed, setFailed] = useState(false);
  const initials = club.name.split(/\s+/).slice(0, 2).map((word) => word[0]).join('');
  return <span className="club-logo" title={club.name}>{club.logoUrl && !failed ? <img src={club.logoUrl} alt={club.name} onError={() => setFailed(true)} /> : <span role="img" aria-label={club.name}>{initials}</span>}</span>;
}
