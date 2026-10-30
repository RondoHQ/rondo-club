import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import TrainingWeek from '../../src/pages/Training/TrainingWeek';
import api from '../../src/api/client';
import { shiftTrainingDate } from '../../src/utils/training';
import './dashboard-preview.css';

// Synthetic browser fixture. Requests and edits never leave this local adapter.
const params = new URLSearchParams(location.search);
if (params.has('dark')) document.documentElement.classList.add('dark');
const state = new Map();
const blocks = [
  { block_id: 'shared', label: '', team_ids: [1, 2], team_names: ['JO13-1', 'JO13-2'], day: 1, start: '18:00', duration: 60, size: 2, offset: 0, pitch_id: 'one', color: '#cffafe' },
  { block_id: 'cancelled', label: '', team_ids: [3], team_names: ['JO15-1'], day: 1, start: '19:00', duration: 60, size: 2, offset: 0, pitch_id: 'one', color: '#d9f99d' },
];
api.defaults.adapter = async (config) => {
  const payload = config.data ? JSON.parse(config.data) : null;
  if (config.url.endsWith('/attendance')) state.set(`${payload.date}:${payload.block_id}:${payload.team_id}`, { cancelled: payload.cancelled, revision: payload.revision + 1 });
  const date = config.params?.date || payload?.date || '2026-11-02';
  const day = new Date(`${date}T12:00:00Z`).getUTCDay() || 7;
  const monday = shiftTrainingDate(date, 1 - day);
  const occurrences = blocks.map((block) => {
    const date = shiftTrainingDate(monday, block.day - 1);
    const attendance = block.team_ids.map((team_id, index) => ({ team_id, name: block.team_names[index], can_change: !params.has('readonly') && team_id === 1, ...(state.get(`${date}:${block.block_id}:${team_id}`) || { cancelled: team_id === 3, revision: 0 }) }));
    return { ...block, date, attendance, cancelled: attendance.every((item) => item.cancelled) };
  });
  const data = { week_start: monday, week_end: shiftTrainingDate(monday, 6), schedule: { id: 1, revision: 1, name: 'Regulier', blocks: occurrences }, pitches: [{ id: 'one', name: 'Veld 1' }], timezone: 'Europe/Amsterdam' };
  return { data, status: 200, statusText: 'OK', headers: {}, config };
};
const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
client.setQueryData(['current-user'], { id: 1 });
createRoot(document.getElementById('root')).render(<QueryClientProvider client={client}><BrowserRouter><div className="bg-gray-50 min-h-screen p-4 text-gray-900 dark:bg-gray-900 dark:text-gray-100"><p className="mb-4 text-sm">Lokaal testvoorbeeld met fictieve teams</p><main className="mx-auto max-w-6xl"><TrainingWeek teamId={params.has('team') ? 1 : undefined} /></main></div></BrowserRouter></QueryClientProvider>);
