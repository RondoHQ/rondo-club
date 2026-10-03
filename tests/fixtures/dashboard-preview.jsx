import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Route, Routes, useLocation } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import RoleDashboard from '../../src/components/dashboard/RoleDashboard';
import Layout from '../../src/components/layout/Layout';
import api from '../../src/api/client';
import './dashboard-preview.css';

// Local, synthetic API adapter: ALL requests are handled here, never forwarded to WordPress.
const params = new URLSearchParams(location.search);
const role = params.get('role') || 'board-secretary';
const board = role.startsWith('board');
const coordinator = ['coordinator', 'combined', 'board-combined'].includes(role);
const secretary = ['secretary', 'combined', 'board-secretary', 'board-combined'].includes(role);
const context = { board, coordinator, secretary, enabled: true };
const user = {
  id: 1, name: 'Joost de Valk', linked_person_name: 'Joost de Valk', linked_person_id: 1,
  dashboard_context: context, feedback_intro_seen: true, is_kader: true, has_my_teams: true,
  can_access_dashboard: true, can_access_commissies: board, can_access_bestuur: board,
  can_access_financieel: board, can_access_vrijwilligers: board, can_access_vog: board,
  can_access_jubilarissen: board, can_access_feedback: true, can_access_teams: true,
  can_access_communication: board, can_manage_sponsors: board,
};
const order = [...(board ? ['birthdays', 'anniversaries', 'attention', 'membership', 'volunteers', 'vog'] : ['attention', ...(coordinator ? ['birthdays'] : [])]), ...(coordinator || secretary ? ['matches'] : []), ...(coordinator ? ['teams'] : [])];
const storageKey = `rondo-brand-preview-${role}`;
function readSavedLayout() {
  try {
    const saved = JSON.parse(sessionStorage.getItem(storageKey));
    if (saved && Array.isArray(saved.order) && saved.order.length === order.length && new Set(saved.order).size === order.length && saved.order.every(id => order.includes(id)) && Array.isArray(saved.hidden)) return { ...saved, defaults: order };
  } catch { /* A fresh preview also works when storage is unavailable. */ }
  return { order, hidden: [], defaults: order, birthday_days: 3 };
}
let layout = readSavedLayout();
const teams = coordinator && !params.has('no-teams') ? [{ id: 1, name: 'JO13-1', player_count: 16 }, { id: 2, name: 'JO13-2', player_count: 15 }] : [];
const birthdays = ['Sam Jansen', 'Mila de Vries', 'Noah Bakker', 'Eva Peters', 'Liam Smits', 'Sofie Willems', 'Lucas Vos'].map((title, index) => ({ id: index + 1, title, date_value: `2013-10-0${3 + index % 3}`, next_occurrence: `2026-10-0${3 + index % 3}`, days_until: index % 3, team_ids: [index % 2 + 1], related_people: [] })).sort((a, b) => a.days_until - b.days_until);
const matches = Array.from({ length: 4 }, (_, index) => ({ id: String(index), date: '2026-10-03', starts_at: `2026-10-03T${13 + index}:00:00+02:00`, time: `${13 + index}:00`, home_team: index === 3 ? 'Bezoekers JO13-2' : `AWC JO13-${index % 2 + 1}`, away_team: index === 3 ? 'AWC JO13-2' : 'Bezoekers JO13-1', cancelled: params.has('cancelled') && index === 2, club_side: index === 3 ? 'away' : 'home', pitch: `Veld ${index % 3 + 1}`, dressing_rooms: { home: index === 1 ? '' : '3', away: index === 2 ? '0 - geen kleedkamer' : '4' } }));
let tasks = [
  { id: 7, content: 'Bezetting zaterdag nalopen', due_date: '2026-10-03T12:00:00+02:00', status: 'open' },
  { id: 8, content: 'Agenda bestuursvergadering voorbereiden', due_date: '2026-10-05T12:00:00+02:00', status: 'open' },
  { id: 9, content: 'Nieuwe aanmeldingen controleren', due_date: '2026-10-07T12:00:00+02:00', status: 'open' },
];
const empty = params.has('empty');
api.defaults.adapter = async config => {
  const path = config.url;
  const method = config.method || 'get';
  let data;
  if (params.has('workspace-error') && path.endsWith('/workspace')) throw new Error('Voorbeeld: dashboard niet beschikbaar');
  if ((params.has('error') || (params.has('club-error') && !config.params?.team_id)) && path.endsWith('/matches')) throw new Error('Voorbeeld: Sportlink niet beschikbaar');
  if (params.has('save-error') && method !== 'get') throw new Error('Voorbeeld: opslaan mislukt');

  if (path.endsWith('/workspace')) data = {
    context, layout, teams,
    birthdays: !empty && (coordinator || board) ? birthdays.filter(person => person.days_until < layout.birthday_days) : [],
    ...(board ? {
      anniversaries: empty ? [] : [{ id: 'anniv-1', title: '75 jaar lid', anniversary_date: '2026-10-04', days_until: 1, person: { id: 21, name: 'Theo van Dijk' } }],
      membership: { season: '2026-2027', from: '2026-07-01', to: '2026-10-03', active: 734, joined: 31, left: 0, left_unknown: 2 },
      volunteers: { window_days: 30, total_shifts: empty ? 0 : 29, open_spots: empty ? 0 : 45, shifts: empty ? [] : [{ id: 31, title: 'Kantinedienst zaterdag', start_datetime: '2026-10-03 17:00:00', spots_remaining: 3 }, { id: 32, title: 'Ontvangst bezoekende teams', start_datetime: '2026-10-04 10:00:00', spots_remaining: 2 }] },
      vog: { not_submitted_to_justis: 2, submitted_to_justis: 86, expiring_soon: 3 },
    } : {}),
    tasks: empty ? [] : tasks.filter(task => task.status !== 'completed'),
    today: '2026-10-03', end_date: '2026-10-09', day: 6,
    training: coordinator ? [{ block_id: 'one', team_ids: [1, 2], day: 6, start: '18:00', duration: 75, pitch_id: '2', size: 2, offset: 0 }] : [],
    pitches: [{ id: '2', name: 'Veld 2' }], has_training_schedule: true,
  };
  else if (path.endsWith('/matches')) data = { matches: empty ? [] : config.params?.team_id ? matches.slice(0, 2) : matches, updated_at: '2026-10-03T09:42:00+02:00', stale: params.has('stale'), matched: true };
  else if (path.endsWith('/layout') && method === 'post') {
    layout = { ...JSON.parse(config.data), defaults: order };
    try { sessionStorage.setItem(storageKey, JSON.stringify(layout)); } catch { /* Keep in-memory changes. */ }
    data = layout;
  }
  else if (/\/todos\/\d+$/.test(path) && method !== 'get') {
    const id = Number(path.split('/').pop());
    tasks = tasks.map(task => task.id === id ? { ...task, ...JSON.parse(config.data) } : task);
    data = tasks.find(task => task.id === id);
  }
  else if (path.endsWith('/user/me')) data = user;
  else if (path === '/rondo/v1/dashboard') data = { stats: { total_people: 1697, total_commissies: 32, open_todos_count: tasks.filter(task => task.status !== 'completed').length } };
  else if (path.endsWith('/people/filtered')) data = { people: [], total: board ? (config.params?.vog_justis_status === 'not_submitted' ? 2 : 86) : 0, total_pages: 1 };
  else if (path.endsWith('/invoices')) data = [];
  else if (path.endsWith('/fees')) data = { members: [] };
  else if (path.endsWith('/current-season')) data = { id: 1, name: '2026-2027' };
  else if (path.endsWith('/discipline-cases')) data = [];
  else if (path.endsWith('/search')) data = { people: [], teams: [], invoices: [] };
  else throw new Error(`Dit onderdeel is niet aangesloten in de lokale preview: ${method} ${path}`);
  return { data, status: 200, statusText: 'OK', headers: { 'x-wp-total': '0' }, config };
};
window.rondoConfig = { isLoggedIn: true, userId: 1, siteName: 'AWC Rondo', logoutUrl: '/?preview-logout', themeUrl: '', isDemoUser: true };
const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
client.setQueryData(['current-user'], user);
document.documentElement.classList.toggle('dark', params.has('dark'));
document.documentElement.style.setProperty('--rondo-admin-bar-offset', '54px');

function PreviewControls() {
  const setParam = (key, value) => { const next = new URLSearchParams(location.search); if (value) next.set(key, value); else next.delete(key); location.assign('/?' + next); };
  return <div className="dashboard-preview-toolbar">
    <strong>Lokale preview <span>· fictieve gegevens</span></strong>
    <label>Rol <select value={role} onChange={event => setParam('role', event.target.value)}>
      <option value="board-secretary">Bestuur + wedstrijdsecretaris</option><option value="board">Bestuur</option><option value="coordinator">Coördinator</option><option value="secretary">Wedstrijdsecretaris</option><option value="board-combined">Alle dashboardrollen</option>
    </select></label>
    <label><input type="checkbox" checked={params.has('dark')} onChange={event => setParam('dark', event.target.checked ? '1' : '')} />Donker</label>
  </div>;
}
function PreviewDestination() {
  const route = useLocation();
  return <div className="mx-auto max-w-2xl py-12"><h1 className="text-2xl font-semibold">Deze lokale preview bevat het dashboard</h1><p className="mt-3 text-gray-600 dark:text-gray-300">De link naar {route.pathname} werkt. Detailpagina’s zijn hier niet aan WordPress gekoppeld.</p><Link className="mt-6 inline-block underline" to="/">Terug naar het dashboard</Link></div>;
}
createRoot(document.getElementById('root')).render(<QueryClientProvider client={client}><BrowserRouter><PreviewControls /><Layout><Routes><Route path="/" element={<RoleDashboard user={user} />} /><Route path="*" element={<PreviewDestination />} /></Routes></Layout></BrowserRouter></QueryClientProvider>);
