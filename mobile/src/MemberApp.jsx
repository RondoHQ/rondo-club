import { createContext, useContext, useEffect, useState } from 'react';
import { QueryClient, QueryClientProvider, useQuery, useQueryClient } from '@tanstack/react-query';
import { MemoryRouter, NavLink, Link, Route, Routes, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { getMembershipPassBackground, getMembershipPassPresentation } from '../../src/pages/Household/membershipPassUtils';
import { usePassQr } from '../../src/hooks/usePassQr';
import { availableShifts, calendarIndex, clubPage, clubNow, dateLabel, monthDays, moveMonth, personName, safeClubLogo, shiftTime, upcomingShifts } from './member-model.mjs';

const MemberContext = createContext(null);

function useResource(resource, params = {}) {
  const { session, onExpired, read } = useContext(MemberContext);
  return useQuery({
    queryKey: [session.club.id, resource, params],
    queryFn: async ({ signal }) => {
      signal.throwIfAborted();
      try {
        const data = await read(`/read?${new URLSearchParams({ resource, ...params })}`);
        signal.throwIfAborted();
        return data;
      } catch (error) {
        if (error.status === 401 && !signal.aborted) onExpired();
        throw error;
      }
    },
    retry: false,
    staleTime: 30000,
  });
}

function QueryState({ query, children }) {
  if (query.isPending) return <p role="status" className="empty">Gegevens ophalen…</p>;
  if (query.isError) return <div role="alert" className="error"><p>{query.error.message}</p><button className="secondary" onClick={() => query.refetch()}>Opnieuw proberen</button></div>;
  return children(query.data);
}

function ExternalAction({ page, children }) {
  const { session } = useContext(MemberContext);
  const [error, setError] = useState('');
  async function open() {
    setError('');
    try { await Browser.open({ url: clubPage(session.club, page) }); }
    catch { setError('De clubsite kon niet worden geopend. Probeer opnieuw.'); }
  }
  return <><button className="secondary" onClick={open}>{children} ↗</button>{error && <p role="alert" className="error">{error}</p>}</>;
}

function PassLinks({ people }) {
  const passes = people.filter((person) => person.membership_pass);
  if (!passes.length) return <p className="empty">Er is nog geen pas beschikbaar voor jou of je gezin. Neem contact op met je club als je een pas verwacht.</p>;
  return <div className="card-list">{passes.map((person) => <Link className="action-card" key={person.id} to={`/passen/${person.id}`}><span className="card-icon" aria-hidden="true">▣</span><span><strong>{personName(person)}</strong><small>{person.membership_pass.label}</small></span><span aria-hidden="true">›</span></Link>)}</div>;
}

function ShiftCard({ shift }) {
  return <Link className="action-card" to={`/vrijwillig/dienst/${shift.id}?date=${shift.start_datetime.slice(0, 10)}`}><span className="date-tile"><strong>{shift.start_datetime.slice(8, 10)}</strong><small>{dateLabel(shift.start_datetime, { month: 'short' })}</small></span><span><strong>{shift.dienst_type_name || shift.title}</strong><small>{shiftTime(shift)}</small>{shift.is_signed_up ? <small className="positive">Je bent aangemeld</small> : shift.spots_remaining >= 0 ? <small>{shift.spots_remaining} plekken vrij</small> : null}</span><span aria-hidden="true">›</span></Link>;
}

function Home() {
  const { profile, now } = useContext(MemberContext);
  const household = useResource('household');
  const mine = useResource('my-shifts');
  return <section><p className="eyebrow">Jouw club, dichtbij</p><h1>Hallo, {profile.name}</h1><h2>Jouw passen</h2><QueryState query={household}>{(people) => <PassLinks people={people} />}</QueryState><div className="section-title"><h2>Je volgende dienst</h2><Link to="/vrijwillig?tab=mine">Alle diensten</Link></div><QueryState query={mine}>{(data) => {
    const next = upcomingShifts(data.shifts || [], now)[0];
    return next ? <ShiftCard shift={{ ...next, is_signed_up: true }} /> : <div className="empty"><p>Je hebt nog geen komende dienst ingepland.</p><Link className="text-link" to="/vrijwillig">Bekijk de kalender →</Link></div>;
  }}</QueryState><Link className="action-card" to="/gegevens"><span className="card-icon" aria-hidden="true">◉</span><span><strong>Mijn gegevens</strong><small>Jij en je gezin bij de club</small></span><span aria-hidden="true">›</span></Link></section>;
}

function Passes() {
  const household = useResource('household');
  return <section><h1>Passen</h1><p>Je eigen pas en de passen van je gezin, op één plek.</p><QueryState query={household}>{(people) => <PassLinks people={people} />}</QueryState></section>;
}

function PassDetail() {
  const { personId } = useParams();
  const household = useResource('household');
  const [params, setParams] = useSearchParams();
  const role = params.get('role') || '';
  return <section><Link className="back-link" to="/passen">‹ Alle passen</Link><QueryState query={household}>{(people) => {
    const person = people.find((item) => String(item.id) === personId && item.membership_pass);
    if (!person) return <p className="empty">Deze pas is niet beschikbaar voor jouw account.</p>;
    const options = person.membership_pass.role_options || [];
    if (person.membership_pass.requires_role && !options.some((option) => option.key === role)) return <><h1>Kies je pas</h1><p>{personName(person)}</p>{options.map((option) => <button key={option.key} onClick={() => setParams({ role: option.key })}>{option.label}</button>)}</>;
    return <>{options.length > 1 && <button className="secondary" onClick={() => setParams({})}>Andere pas kiezen</button>}<PassContent personId={person.id} role={role || options[0]?.key || ''} label={options.find((option) => option.key === role)?.label || person.membership_pass.label} walletAvailable={Object.values(person.membership_pass.wallets || {}).some((wallet) => wallet.available)} /></>;
  }}</QueryState></section>;
}

function PassContent({ personId, role, label, walletAvailable }) {
  const query = useResource('pass', { person_id: String(personId), role });
  return <QueryState query={query}>{(data) => <><PassCard data={data} label={label} /><p className="caption">Je pas wordt bij iedere scan gecontroleerd op geldigheid.</p>{walletAvailable && <ExternalAction page="profile">Wallet toevoegen via je club</ExternalAction>}</>}</QueryState>;
}

function PassCard({ data, label }) {
  const { session } = useContext(MemberContext);
  const qr = usePassQr(data.token);
  const presentation = getMembershipPassPresentation(data.payload?.pass_type);
  const logo = (!presentation.businessclub && session.club.logoUrl) || safeClubLogo(data.pass?.logo_url, session.club);
  const detail = presentation.sponsor ? data.person?.company_name : data.pass?.role_label || data.person?.team;
  const backgroundColor = getMembershipPassBackground(data.payload?.pass_type, data.pass?.background_color);
  return <article className={`member-pass${presentation.sponsor ? ' sponsor-pass' : ''}`} style={{ backgroundColor }} aria-label="Digitale pas">
    <div className="pass-heading"><span>{session.club.name}</span>{logo && <PassLogo key={logo} url={logo} />}</div>
    <p className="eyebrow">{label}</p><h1>{data.person?.name}</h1>{detail && <p>{detail}</p>}
    {!presentation.sponsor && data.person?.knvb_id && <p>KNVB-ID: {data.person.knvb_id}</p>}
    <div className="qr-panel">{qr.error ? <p role="alert">De QR-code kon niet worden gemaakt. Open je pas opnieuw.</p> : qr.url ? <img src={qr.url} alt={`QR-code van de pas van ${data.person?.name}`} /> : <p role="status">QR-code laden…</p>}</div>
    {data.expires_at && <p>Geldig tot {dateLabel(data.expires_at)}</p>}
  </article>;
}

function PassLogo({ url }) {
  const [failed, setFailed] = useState(false);
  return failed ? null : <img className="pass-logo" src={url} alt="" onError={() => setFailed(true)} />;
}

function Household() {
  const household = useResource('household');
  return <section><h1>Mijn gegevens</h1><QueryState query={household}>{(people) => people.length ? people.map((person) => <article className="panel" key={person.id}><p className="eyebrow">{person.household_role === 'self' ? 'Jij' : 'Je gezin'}</p><h2>{personName(person)}</h2>{person.fields?.email_1 && <p>{person.fields.email_1}</p>}{person.fields?.mobile_1 && <p>{person.fields.mobile_1}</p>}{person.membership_pass && <Link className="text-link" to={`/passen/${person.id}`}>Pas bekijken →</Link>}</article>) : <p className="empty">Je account is nog niet gekoppeld aan een lid. Neem contact op met je club.</p>}</QueryState><ExternalAction page="profile">Gegevens en contributie bij je club</ExternalAction></section>;
}

function Volunteers() {
  const { today, now } = useContext(MemberContext);
  const [params, setParams] = useSearchParams();
  const month = /^20\d{2}-(0[1-9]|1[0-2])$/.test(params.get('month') || '') ? params.get('month') : today.slice(0, 7);
  const dates = monthDays(month);
  const selected = dates.dates.includes(params.get('date')) ? params.get('date') : month === today.slice(0, 7) ? today : `${month}-01`;
  const tab = params.get('tab') === 'mine' ? 'mine' : 'available';
  const calendar = useResource('calendar', { month });
  const mine = useResource('my-shifts');
  function update(next) { setParams({ month, date: selected, tab, ...next }, { replace: true }); }
  return <section><h1>Vrijwillig</h1><p>Kies een dag die bij je past.</p><div className="segments" aria-label="Dienstenweergave"><button aria-pressed={tab === 'available'} onClick={() => update({ tab: 'available' })}>Beschikbaar</button><button aria-pressed={tab === 'mine'} onClick={() => update({ tab: 'mine' })}>Mijn diensten</button></div>{tab === 'mine' ? <QueryState query={mine}>{(data) => {
    const shifts = upcomingShifts(data.shifts || [], now);
    return shifts.length ? shifts.map((shift) => <ShiftCard key={shift.id} shift={{ ...shift, is_signed_up: true }} />) : <p className="empty">Je hebt nog geen komende diensten ingepland.</p>;
  }}</QueryState> : <><div className="month-heading"><button className="secondary" aria-label="Vorige maand" onClick={() => update({ month: moveMonth(month, -1), date: '' })}>‹</button><h2>{dateLabel(`${month}-01`, { month: 'long', year: 'numeric' })}</h2><button className="secondary" aria-label="Volgende maand" onClick={() => update({ month: moveMonth(month, 1), date: '' })}>›</button></div><QueryState query={calendar}>{(data) => {
    const index = calendarIndex(data.days || [], mine.data?.shifts || []);
    const shifts = availableShifts(index.get(selected));
    return <><div className="month-grid" aria-label="Dienstenkalender">{['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'].map((day) => <span key={day} className="weekday" aria-hidden="true">{day}</span>)}{Array.from({ length: dates.blanks }, (_, i) => <span key={`blank-${i}`} />)}{dates.dates.map((date) => {
      const day = index.get(date);
      return <button key={date} className={`calendar-day ${date === today ? 'today' : ''}`} aria-pressed={date === selected} aria-label={`${dateLabel(date)}: ${day?.available || 0} beschikbare diensten${day?.mine ? ', eigen dienst' : ''}`} onClick={() => update({ date })}><strong>{Number(date.slice(8))}</strong><small>{day?.available ? day.available : '\u00a0'}</small>{day?.mine && <i aria-hidden="true" />}</button>;
    })}</div><p className="calendar-legend">Aantal = beschikbare diensten · ● = eigen dienst</p><h2 className="selected-date">{dateLabel(selected)}</h2>{shifts.length ? shifts.map((shift) => <ShiftCard key={shift.id} shift={shift} />) : <p className="empty">Geen beschikbare diensten op deze dag.</p>}</>;
  }}</QueryState></>}<ExternalAction page="volunteer">Aanmelden of afmelden bij je club</ExternalAction></section>;
}

function ShiftDetail() {
  const { shiftId } = useParams();
  const [params] = useSearchParams();
  const { today } = useContext(MemberContext);
  const date = /^20\d{2}-(0[1-9]|1[0-2])-\d{2}$/.test(params.get('date') || '') ? params.get('date') : today;
  const calendar = useResource('calendar', { month: date.slice(0, 7) });
  const mine = useResource('my-shifts');
  return <section><Link className="back-link" to={`/vrijwillig?month=${date.slice(0, 7)}&date=${date}`}>‹ Kalender</Link><QueryState query={calendar}>{(data) => {
    const shift = data.days?.flatMap((day) => day.shifts).find((item) => String(item.id) === shiftId) || mine.data?.shifts?.find((item) => String(item.id) === shiftId);
    if (!shift) return <p className="empty">Deze dienst is niet meer beschikbaar. Bekijk de kalender voor het actuele aanbod.</p>;
    const own = shift.is_signed_up || mine.data?.shifts?.some((item) => item.id === shift.id && ['open', 'vol'].includes(item.status));
    return <><p className="eyebrow">{dateLabel(shift.start_datetime)}</p><h1>{shift.dienst_type_name || shift.title}</h1><article className="panel"><h2>{shiftTime(shift)}</h2><p>{own ? 'Je bent aangemeld voor deze dienst.' : shift.can_signup ? `${shift.spots_remaining < 0 ? 'Er zijn' : shift.spots_remaining} plekken beschikbaar.` : 'Aanmelden is momenteel niet mogelijk.'}</p>{shift.signup_opens_at && <p>Inschrijven opent op {dateLabel(shift.signup_opens_at)}.</p>}</article><ExternalAction page="volunteer">{own ? 'Inschrijving bekijken bij je club' : 'Dienst bekijken bij je club'}</ExternalAction></>;
  }}</QueryState></section>;
}

function More() {
  const { logout, profile } = useContext(MemberContext);
  return <section><h1>Meer</h1><p>{profile.name}</p><Link className="action-card" to="/gegevens"><strong>Mijn gegevens</strong><span>›</span></Link><Link className="action-card" to="/clubs"><strong>Mijn clubs</strong><span>›</span></Link><div className="panel"><h2>Over deze proef</h2><p>Je bekijkt gegevens van je club. Wijzigingen en Wallet toevoegen lopen via de clubsite.</p><p>Je blijft maximaal 30 dagen aangemeld op dit toestel. Via Uitloggen verwijder je je opgeslagen aanmelding.</p></div><button className="secondary" onClick={logout}>Uitloggen</button></section>;
}

function Clubs() {
  const { session, logout } = useContext(MemberContext);
  return <section><Link className="back-link" to="/meer">‹ Meer</Link><h1>Mijn clubs</h1><article className="panel"><p className="eyebrow">Actieve club</p><h2>{session.club.name}</h2><p>{new URL(session.club.url).hostname}</p></article><button onClick={logout}>Uitloggen en andere club kiezen</button><p className="caption">Je lidmaatschap bij de club blijft behouden.</p></section>;
}

function Navigation() {
  const location = useLocation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  useEffect(() => {
    let active = true;
    const handles = [];
    const listen = (event, callback) => App.addListener(event, callback).then((handle) => { if (active) handles.push(handle); else handle.remove(); });
    if (Capacitor.getPlatform() === 'android') listen('backButton', () => { if (location.key !== 'default') navigate(-1); else App.minimizeApp(); });
    listen('appStateChange', ({ isActive }) => { if (isActive) queryClient.invalidateQueries(); });
    Browser.addListener('browserFinished', () => queryClient.invalidateQueries()).then((handle) => { if (active) handles.push(handle); else handle.remove(); });
    return () => { active = false; handles.forEach((handle) => handle.remove()); };
  }, [location.key, navigate, queryClient]);
  useEffect(() => { window.scrollTo(0, 0); }, [location.pathname]);
  return <nav aria-label="Hoofdnavigatie">{[['/', '⌂', 'Start'], ['/passen', '▣', 'Passen'], ['/vrijwillig', '▦', 'Vrijwillig'], ['/meer', '☰', 'Meer']].map(([path, icon, label]) => <NavLink key={path} to={path} end={path === '/'}><span aria-hidden="true">{icon}</span>{label}</NavLink>)}</nav>;
}

export default function MemberApp({ session, profile, logout, onExpired, read }) {
  // One cache and route history per authenticated session; never share across clubs or logins.
  const [client] = useState(() => new QueryClient());
  const now = clubNow(session.club.timeZone);
  const today = now.slice(0, 10);
  useEffect(() => () => { client.cancelQueries(); client.clear(); }, [client]);
  return <QueryClientProvider client={client}><MemberContext.Provider value={{ session, profile, logout, onExpired, read, today, now }}><MemoryRouter><Routes><Route path="/" element={<Home />} /><Route path="/passen" element={<Passes />} /><Route path="/passen/:personId" element={<PassDetail />} /><Route path="/gegevens" element={<Household />} /><Route path="/vrijwillig" element={<Volunteers />} /><Route path="/vrijwillig/dienst/:shiftId" element={<ShiftDetail />} /><Route path="/meer" element={<More />} /><Route path="/clubs" element={<Clubs />} /></Routes><Navigation /></MemoryRouter></MemberContext.Provider></QueryClientProvider>;
}
