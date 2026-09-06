import { useEffect, useRef, useState } from 'react';
import { Capacitor } from '@capacitor/core';
import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { LoginSession, validateClubs } from './auth.mjs';
import { request } from './transport.mjs';
import './style.css';

const clubs = validateClubs(JSON.parse(import.meta.env.VITE_SPIKE_CLUBS || '[]'));
const auth = new LoginSession();

export default function MobileSpike() {
  const [club, setClub] = useState(null);
  const [search, setSearch] = useState('');
  const [screen, setScreen] = useState('clubs');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [profile, setProfile] = useState(null);
  const alive = useRef(true);

  function reset() {
    auth.clear();
    setProfile(null);
    setBusy(false);
    setError('');
    setScreen('clubs');
    setClub(null);
  }

  useEffect(() => {
    alive.current = true;
    let listener;
    async function handle(url) {
      if (!alive.current) return;
      try {
        const session = await auth.finish(url, (selected, data) => request(selected, '/token', { method: 'POST', data }));
        if (Capacitor.getPlatform() === 'ios') await Browser.close().catch(() => {});
        const generation = auth.generation;
        const me = await request(session.club, '/read?resource=me', { token: session.token });
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

  useEffect(() => {
    if (!profile || !auth.session) return;
    const timer = setTimeout(() => {
      auth.clear();
      setProfile(null);
      setScreen('confirm');
      setError('Je proefsessie is verlopen. Log opnieuw in.');
    }, Math.max(0, auth.session.expiresAt - Date.now()));
    return () => clearTimeout(timer);
  }, [profile]);

  async function login() {
    setBusy(true);
    setError('');
    const generation = auth.generation;
    try {
      const metadata = await request(club, '/config');
      if (generation !== auth.generation) return;
      if (metadata.protocol !== 'rondo-mobile-spike-v1' || metadata.club_url !== club.url) throw new Error('Deze club is nog niet beschikbaar voor de proef.');
      if (!Capacitor.isNativePlatform()) throw new Error('Open de geïnstalleerde proefapp om in te loggen.');
      const url = await auth.start(club);
      await Browser.open({ url });
      setScreen('login');
    } catch (failure) {
      setError(failure.message);
      setBusy(false);
    }
  }

  async function logout() {
    const session = auth.session;
    reset();
    if (session) {
      try { await request(session.club, '/revoke', { method: 'POST', token: session.token }); }
      catch { setError('Lokaal uitgelogd. De clubsessie verloopt uiterlijk binnen vijf minuten.'); }
    }
  }

  const results = clubs.filter((item) => item.name.toLocaleLowerCase('nl').includes(search.toLocaleLowerCase('nl')));
  return <main>
    <header><span className="wordmark">rondo<span>●</span></span><span className="badge">Technische proef</span></header>
    {club && <p className="club">{club.name}</p>}
    {error && <p role="alert" className="error">{error}</p>}
    {screen === 'clubs' && <section>
      <p className="eyebrow">Jouw club, dichtbij</p><h1>Welkom bij Rondo</h1><p>Kies je club om in te loggen en je eigen gegevens te bekijken.</p>
      <label htmlFor="search">Zoek je club</label><input id="search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Naam van je club" />
      {results.map((item) => <button className="club-card" key={item.id} onClick={() => { setClub(item); setError(''); setScreen('confirm'); }}><strong>{item.name}</strong><small>{new URL(item.url).hostname}</small><span aria-hidden="true">→</span></button>)}
      {!results.length && <p>{clubs.length ? 'Geen club gevonden. Probeer een andere naam.' : 'Er is nog geen testclub ingesteld voor deze proefversie.'}</p>}
    </section>}
    {screen === 'confirm' && <section><h1>Inloggen bij {club.name}</h1><p>Je logt in via de website van je club. Daarna keer je terug naar Rondo.</p><p className="domain">{new URL(club.url).hostname}</p><button disabled={busy} onClick={login}>{busy ? 'Club controleren…' : 'Inloggen bij mijn club'}</button><button className="secondary" onClick={reset}>Terug naar clubkeuze</button></section>}
    {screen === 'login' && <section><h1>Rond je aanmelding af</h1><p>Gebruik het geopende browservenster. Na toestemming kom je terug in deze app.</p><button className="secondary" onClick={() => { auth.clear(); setBusy(false); setScreen('confirm'); }}>Aanmelding annuleren</button></section>}
    {screen === 'home' && <section><p className="eyebrow">Je bent ingelogd</p><h1>Hallo, {profile?.name}</h1><p>Je gegevens zijn opgehaald bij {club.name} met je bestaande clubrechten.</p><div className="info">Deze eerste proef controleert de verbinding en je aanmelding. Passen en de vrijwilligerskalender volgen daarna.</div></section>}
    {screen === 'more' && <section><h1>Meer</h1><button className="secondary" onClick={logout}>Mijn clubs · uitloggen en wisselen</button></section>}
    {profile && <nav aria-label="Hoofdnavigatie"><button className={screen === 'home' ? 'active' : ''} onClick={() => setScreen('home')}>Start</button><button className={screen === 'more' ? 'active' : ''} onClick={() => setScreen('more')}>Meer</button></nav>}
    <footer>Proefversie · sessie maximaal vijf minuten</footer>
  </main>;
}
