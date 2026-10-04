import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MatchCompensation from '../../src/pages/Finance/MatchCompensation';
import api from '../../src/api/client';
import './dashboard-preview.css';

// Synthetic browser fixture. Every API call is intercepted; no production requests or writes.
const settings = { teams: [{ team_id: 1, scheme: 'awc1', name: 'AWC 1', can_register: true }, { team_id: 2, scheme: 'jo23', name: 'JO23-1', can_register: true }], can_manage: true, can_finance: true, can_configure: true, bank_code: '', retention_policy: 'Testbeleid', available_teams: [{ id: 1, name: 'AWC 1' }, { id: 2, name: 'JO23-1' }], available_users: [{ id: 1, name: 'Testregistrator', teams: [1, 2] }] };
const players = [{ id: 11, name: 'Testspeler Een' }, { id: 12, name: 'Testspeler Twee' }];
const rows = players.map((person) => ({ person_id: person.id, player_name: person.name, nmbrs_name: person.name, basis: 1, bank: 1, days: 2, wins: 1, draws: 1, amount_cents: 6000 }));
const registrations = [];
const batches = [];
api.defaults.adapter = async (config) => {
  const path = config.url;
  const input = config.data ? JSON.parse(config.data) : {};
  let data;
  if (path.endsWith('/settings')) data = settings;
  else if (path.endsWith('/matches')) data = { matches: [{ id: 'sportlink:test', date: '2026-09-01', home: true, home_team: 'AWC 1', away_team: 'Bezoekers', competition: 'Regulier', result: '3-0' }], matched: true, stale: false };
  else if (path.endsWith('/people')) data = { current: players };
  else if (path.endsWith('/registrations') && config.method === 'get') data = registrations;
  else if (path.includes('/registrations')) { data = { id: 21, version: 1, ...input.fields }; registrations.push(data); }
  else if (path.includes('/months?')) data = { rows, scheme: 'jo23', fingerprint: 'test-fingerprint', errors: [], bank_code: '', batches: batches.map((batch) => ({ id: batch.id, version: batch.version, phase: batch.phase, exported: false })) };
  else if (path.endsWith('/batches') && config.method === 'post') { data = { id: 31, version: 1, phase: 'closed', month: input.month, rows, scheme: input.team_id === 1 ? 'awc1' : 'jo23', summary: { exported: false, processing: null } }; batches.push(data); }
  else if (/\/batches\/\d+$/.test(path)) data = batches[0];
  else throw new Error(`Unmocked fixture request: ${path}`);
  return { data: structuredClone(data), status: 200, statusText: 'OK', headers: {}, config };
};
createRoot(document.getElementById('root')).render(<BrowserRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><div className="p-4 sm:p-8"><p className="mb-6 text-sm text-amber-800">Lokale test met verzonnen gegevens</p><MatchCompensation /></div></QueryClientProvider></BrowserRouter>);
