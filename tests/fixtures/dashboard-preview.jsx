import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import RoleDashboard from '../../src/components/dashboard/RoleDashboard';
import api from '../../src/api/client';
import './dashboard-preview.css';

// Only synthetic records. This fixture exercises the actual dashboard component and requests.
const params = new URLSearchParams(location.search);
const role = params.get('role') || 'combined';
const coordinator = role !== 'secretary';
const secretary = role !== 'coordinator';
const context = { coordinator, secretary, enabled: true };
const user = { id: 1, dashboard_context: context };
const order = ['attention', ...(coordinator ? ['birthdays'] : []), 'matches', ...(coordinator ? ['teams'] : [])];
const teams = coordinator && !params.has('no-teams') ? [{ id: 1, name: 'JO13-1', player_count: 16 }, { id: 2, name: 'JO13-2', player_count: 15 }] : [];
let layout = JSON.parse(sessionStorage.getItem(`dashboard-${role}`) || 'null') || { order, hidden: [] };
const birthdays = coordinator ? ['Sam Jansen', 'Mila de Vries', 'Noah Bakker'].map((title, index) => ({ id: index + 1, title, date_value: `2013-09-${23 + index}`, next_occurrence: `2026-09-${23 + index}`, days_until: index, team_ids: [index === 2 ? 2 : 1], related_people: [] })) : [];
const matches = Array.from({ length: 6 }, (_, index) => ({ id: String(index), date: '2026-09-26', starts_at: `2026-09-26T${10 + index}:00:00+02:00`, time: `${10 + index}:00`, home_team: index === 3 ? 'Bezoekers JO13-2' : `AWC ${index < 2 ? `JO13-${index + 1}` : `JO${14 + index}-1`}`, away_team: index === 3 ? 'AWC JO13-2' : 'Bezoekers JO13-1', cancelled: index === 5, club_side: index === 3 ? 'away' : 'home', pitch: `Veld ${index % 3 + 1}`, dressing_rooms: { home: index === 1 ? '' : '3', away: index === 2 ? '0 - geen kleedkamer' : '4' } }));
api.defaults.adapter = async config => {
  if ((params.has('error') || (params.has('club-error') && !config.params?.team_id)) && config.url.endsWith('/matches')) throw new Error('Test: feed unavailable');
  if (params.has('save-error') && config.method === 'post') throw new Error('Test: save unavailable');
  let data;
  if (config.url.endsWith('/workspace')) data = { context, layout, teams, birthdays, tasks: params.has('empty') ? [] : [{ id: 7, content: 'Teamindeling voor zaterdag controleren', due_date: '2026-09-23T12:00:00+02:00', status: 'open' }], today: '2026-09-23', end_date: '2026-09-29', day: 3, training: coordinator ? [{ block_id: 'one', team_ids: [1, 2], day: 3, start: '18:00', duration: 75, pitch_id: '2', size: 2, offset: 0 }] : [], pitches: [{ id: '2', name: 'Veld 2' }], has_training_schedule: true };
  else if (config.url.endsWith('/matches')) data = { matches: params.has('empty') ? [] : config.params?.team_id ? matches.slice(0, 2) : matches, updated_at: '2026-09-23T09:42:00+02:00', stale: params.has('stale'), matched: true };
  else if (config.url.endsWith('/layout')) { layout = JSON.parse(config.data); sessionStorage.setItem(`dashboard-${role}`, JSON.stringify(layout)); data = layout; }
  else if (config.url.endsWith('/user/me')) data = user;
  else data = [];
  return { data, status: 200, statusText: 'OK', headers: {}, config };
};
const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
client.setQueryData(['current-user'], user);
document.documentElement.classList.toggle('dark', params.has('dark'));
createRoot(document.getElementById('root')).render(<QueryClientProvider client={client}><BrowserRouter><main className="min-h-screen bg-gray-50 p-4 dark:bg-gray-900 sm:p-8"><div className="mx-auto max-w-6xl"><p className="mb-6 text-sm text-gray-500 dark:text-gray-400">Testvoorbeeld met fictieve gegevens</p><RoleDashboard user={user} /></div></main></BrowserRouter></QueryClientProvider>);
