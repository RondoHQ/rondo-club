import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import VrijwilligersStatistieken from '../../src/pages/Vrijwilligers/VrijwilligersStatistieken';
import api from '../../src/api/client';
import './dashboard-preview.css';

// Synthetic fixtures only: the adapter never sends requests to WordPress.
const params = new URLSearchParams(location.search);
if (params.has('dark')) document.documentElement.classList.add('dark');
const empty = params.has('empty');
const policy = { definition_version: 1, capacity: 1440, assignments: 550, fill_rate: 38.2, liable_units: 600, on_track: 150, not_started: 300, required: 1240, completed: 250, covered: 500, open: 740, base_required: 2, on_track_rate: 25, not_started_rate: 50, completed_rate: 20.2, covered_rate: 40.3, obligation_capacity_rate: 86.1 };
if (empty) Object.keys(policy).forEach((key) => { if (!['base_required', 'definition_version'].includes(key)) policy[key] = key.endsWith('_rate') ? null : 0; });
const data = {
  season: '2026-2027', available_seasons: ['2026-2027', '2025-2026'], generated_at: '2026-11-15T12:00:00+01:00', policy,
  summary: { total_assignments: empty ? 0 : 550, total_capacity: empty ? 0 : 1440, unique_volunteers: 350, fill_rate: empty ? 0 : 38.2, completed_assignments: 260, upcoming_assignments: 290, average_assignments_per_volunteer: 1.6 },
  by_task_type: empty ? [] : ['Kantine, keuken/luik', 'Kantine, bar', 'Club host', 'Terreinonderhoud', 'Schoonmaak kleedkamers', 'AWC-1 gastheer/gastvrouw', 'Entree AWC 1', 'Sponsorborden schoonmaken', 'Bar businessclub'].map((name, index) => ({ id: index + 1, name, assignments: 20 + index * 3, capacity: 100, fill_rate: 20 + index * 3 })),
  assignment_distribution: empty ? { one: 0, two: 0, three_plus: 0 } : { one: 180, two: 150, three_plus: 20 },
  policy_history: empty ? [] : Array.from({ length: 14 }, (_, index) => ({ ...policy, definition_version: params.has('older-definition') && index === 0 ? 0 : 1, date: `2026-11-${String(index + 1).padStart(2, '0')}`, fill_rate: 32 + index / 2, on_track_rate: 20 + index / 3, open: 810 - index * 5 })),
  obligation_progress: { total_units: 610, exempt: 10, completed: 60, fully_scheduled: 90, partial: 150, not_started: 300, total_required: 1240, total_completed: 250, total_pending: 250 },
  by_team: [], signup_trend: [], account_trend: [], upcoming_shortages: [], upcoming_shortages_total: 0, shortage_window_days: 30,
};
api.defaults.adapter = async (config) => {
  if (params.has('error')) throw new Error('Preview: statistics unavailable');
  if (!config.url.includes('volunteer-statistics')) throw new Error(`Unmocked fixture: ${config.url}`);
  return { data: { ...data, season: config.params?.season || data.season }, status: 200, statusText: 'OK', headers: {}, config };
};

createRoot(document.getElementById('root')).render(
  <BrowserRouter>
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <div className="dashboard-preview-toolbar">Lokale preview · fictieve gegevens</div>
      <main className="mx-auto max-w-7xl p-4 sm:p-8"><VrijwilligersStatistieken /></main>
    </QueryClientProvider>
  </BrowserRouter>,
);
