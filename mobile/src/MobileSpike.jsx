import { useCallback, useEffect, useRef, useState } from 'react';
import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { validateClubs } from './auth.mjs';
import { request } from './transport.mjs';
import { DeviceSession } from './device-session.mjs';
import { vault } from './vault.mjs';
import MemberApp from './MemberApp';
import { safeClubLogo } from './member-model.mjs';
import './style.css';

const clubs = validateClubs(JSON.parse(import.meta.env.VITE_SPIKE_CLUBS || '[]'));
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

  useEffect(() => {
    alive.current = true;
    let listener;
    async function handle(url) {
      if (!alive.current) return;
      try {
        const session = await auth.finish(url);
        if (Capacitor.getPlatform() === 'ios') await Browser.close().catch(() => {});
        const generation = auth.generation;
        const me = await auth.read('/read?resource=me');
        if (!alive.current || generation !== auth.generation) return;
        setClub(session.club);
        setProfile(me);
        setError('');
        setScreen('home');
      } catch (failure) {
        if (alive.current) setError(failure.message);
      } finally {
        if (alive.current) setBusy(false);
      }
    }
    App.addListener('appUrlOpen', ({ url }) => handle(url)).then((value) => {
      if (!alive.current) value.remove();
      else listener = value;
    });
    // A cold start has no in-memory verifier and must ask for a new login.
    App.getLaunchUrl().then((value) => { if (value?.url) handle(value.url); });
    return () => { alive.current = false; listener?.remove(); };
  }, []);

  const restore = useCallback(async () => {
    setBusy(true);
    setError('');
    setScreen('restoring');
    try {
      const session = await auth.restore();
      if (!session) { reset(); return; }
      const generation = auth.generation;
      const [me, metadata] = await Promise.all([auth.read('/read?resource=me'), request(session.club, '/config')]);
      if (!alive.current || generation !== auth.generation) return;
      session.club = { ...session.club, timeZone: metadata.timezone, logoUrl: session.club.logoUrl || safeClubLogo(metadata.logo_url, session.club) };
      setClub(session.club);
      setProfile(me);
      setScreen('home');
    } catch (failure) {
      setProfile(null);
      setScreen(failure.status === 401 ? 'clubs' : 'recover');
      if (failure.status === 401) setClub(null);
      setError(failure.message);
    } finally { setBusy(false); }
  }, [reset]);

  useEffect(() => { restore(); }, [restore]);

  async function login() {
    setBusy(true);
    setError('');
    const generation = auth.generation;
    try {
      const metadata = await request(club, '/config');
      if (generation !== auth.generation) return;
      if (metadata.protocol !== 'rondo-mobile-spike-v1' || metadata.club_url !== club.url) throw new Error('Deze club is nog niet beschikbaar voor de proef.');
      if (!Capacitor.isNativePlatform()) throw new Error('Open de geïnstalleerde proefapp om in te loggen.');
      const selected = { ...club, timeZone: metadata.timezone, logoUrl: club.logoUrl || safeClubLogo(metadata.logo_url, club) };
      setClub(selected);
      const url = await auth.start(selected);
      await Browser.open({ url });
      setScreen('login');
    } catch (failure) {
      setError(failure.message);
      setBusy(false);
    }
  }

  async function logout() {
    setProfile(null);
    setBusy(true);
    setScreen('restoring');
    setError('');
    try { await auth.logout(); reset(); }
    catch { setScreen('recover'); setError('Uitloggen is niet afgerond. Probeer opnieuw om je opgeslagen aanmelding te verwijderen.'); }
    finally { setBusy(false); }
  }

  const results = clubs.filter((item) => item.name.toLocaleLowerCase('nl').includes(search.toLocaleLowerCase('nl')));
  return <main className={profile ? 'member-shell' : ''}>
    <header><div className="header-brand">{club && <ClubLogo key={club.id} club={club} />}<img className="rondo-wordmark" src="./brand/rondo-wordmark.svg" alt="Rondo" /></div><span className="badge">Proefversie</span></header>
    {error && <p role="alert" className="error">{error}</p>}
    {screen === 'restoring' && <p role="status">Je aanmelding controleren…</p>}
    {screen === 'recover' && <section><h1>Aanmelding controleren</h1><p>Maak verbinding met je club om verder te gaan, of log uit op dit toestel.</p><button disabled={busy} onClick={restore}>Opnieuw proberen</button><button disabled={busy} className="secondary" onClick={logout}>Uitloggen op dit toestel</button></section>}
    {screen === 'clubs' && <section>
      <p className="eyebrow">Jouw club, dichtbij</p><h1>Welkom bij Rondo</h1><p>Kies je club om in te loggen en je eigen gegevens te bekijken.</p>
      <label htmlFor="search">Zoek je club</label><input id="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Naam van je club" />
      {results.map((item) => <button className="club-card" key={item.id} onClick={() => { setClub(item); setError(''); setScreen('confirm'); }}><strong>{item.name}</strong><small>{new URL(item.url).hostname}</small><span aria-hidden="true">→</span></button>)}
      {!results.length && <p>{clubs.length ? 'Geen club gevonden. Probeer een andere naam.' : 'Er is nog geen testclub ingesteld voor deze proefversie.'}</p>}
    </section>}
    {screen === 'confirm' && <section><h1>Inloggen bij {club.name}</h1><p>Je logt in via de website van je club. Daarna keer je terug naar Rondo.</p><p className="domain">{new URL(club.url).hostname}</p><button disabled={busy} onClick={login}>{busy ? 'Club controleren…' : 'Inloggen bij mijn club'}</button><button className="secondary" onClick={reset}>Terug naar clubkeuze</button></section>}
    {screen === 'login' && <section><h1>Rond je aanmelding af</h1><p>Gebruik het geopende browservenster. Na toestemming kom je terug in deze app.</p><button className="secondary" onClick={() => { auth.clear(); setBusy(false); setScreen('confirm'); }}>Aanmelding annuleren</button></section>}
    {profile && auth.session && <MemberApp session={auth.session} profile={profile} logout={logout} read={(path) => auth.read(path)} onExpired={() => { setProfile(null); setScreen('recover'); setError('Je aanmelding is verlopen. Log opnieuw in.'); }} />}
    <footer>Proefversie{profile ? ' · aangemeld op dit toestel' : ''}</footer>
  </main>;
}

function ClubLogo({ club }) {
  const [failed, setFailed] = useState(false);
  const initials = club.name.split(/\s+/).slice(0, 2).map((word) => word[0]).join('');
  return <span className="club-logo" title={club.name}>{club.logoUrl && !failed ? <img src={club.logoUrl} alt={club.name} onError={() => setFailed(true)} /> : <span role="img" aria-label={club.name}>{initials}</span>}</span>;
}
