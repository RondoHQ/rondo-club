import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MatchCompensation from '../../src/pages/Finance/MatchCompensation';
import api from '../../src/api/client';
import './dashboard-preview.css';

// Synthetic browser fixture. Every API call is intercepted; no production requests or writes.
const settings = { teams: [{ team_id: 1, scheme: 'awc1', name: 'AWC 1', can_register: true }, { team_id: 2, scheme: 'jo23', name: 'JO23-1', can_register: true }], can_manage: true, can_finance: true, can_configure: true, bank_code: 'U2151', debtor_number: '23008', company_number: '23008', basis_code: 'U2150', win_code: 'L3090', draw_code: 'L3091', payroll_import_confirmed: false, payroll_corrections_confirmed: false, retention_policy: 'Testbeleid', available_teams: [{ id: 1, name: 'AWC 1' }, { id: 2, name: 'JO23-1' }], available_users: [{ id: 1, name: 'Testregistrator', teams: [1, 2] }] };
const players = [{ id: 11, name: 'Testspeler Een' }, { id: 12, name: 'Testspeler Twee' }];
const rows = players.map((person) => ({ person_id: person.id, player_name: person.name, nmbrs_name: person.name, basis: 1, bank: 1, days: 2, wins: 1, draws: 1, payment_route: person.id === 11 ? 'payroll' : 'sepa_guest', nmbrs_employee_number: person.id === 11 ? '1001' : '', amount_cents: person.id === 11 ? 0 : 16000 }));
const agreements = new Map([[11, [{ team_id: 1, valid_from: '2026-09-01', valid_until: null, payment_route: 'payroll', nmbrs_employee_number: '1001', nmbrs_name: 'Testspeler Een' }]]]);
const registrations = [];
const batches = [];
api.defaults.adapter = async (config) => {
  const path = config.url;
  const input = config.data ? JSON.parse(config.data) : {};
  let data;
  if (path.endsWith('/settings')) data = settings;
  else if (path.endsWith('/matches')) data = { matches: [{ id: 'sportlink:test', date: '2026-09-01', home: true, home_team: 'AWC 1', away_team: 'Bezoekers', competition: 'Regulier', result: '3-0' }], matched: true, stale: false };
  else if (path.includes('/agreements?')) data = players.filter((p) => agreements.has(p.id)).map((p) => ({ person_id: p.id, player_name: p.name, agreements: agreements.get(p.id) }));
  else if (/\/people\/\d+\/agreements$/.test(path)) { const id = Number(path.match(/people\/(\d+)/)[1]); if (config.method === 'patch') agreements.set(id, input.agreements); data = { person_id: id, player_name: players.find((p) => p.id === id).name, can_edit: true, version: JSON.stringify(agreements.get(id) || []), agreements: agreements.get(id) || [] }; }
  else if (path === '/wp/v2/people') data = players.map((p) => ({ id: p.id, title: { rendered: p.name } }));
  else if (path.endsWith('/people')) data = { current: players };
  else if (path.endsWith('/registrations') && config.method === 'get') data = registrations;
  else if (path.includes('/registrations')) { data = { id: 21, version: 1, ...input.fields }; registrations.push(data); }
  else if (path.includes('/months?')) data = { rows, scheme: 'awc1', fingerprint: 'test-fingerprint', errors: [], bank_code: '', batches: batches.map((batch) => ({ id: batch.id, version: batch.version, phase: batch.phase, exported: false })) };
  else if (path.endsWith('/batches') && config.method === 'post') { data = { id: 31, version: 1, phase: 'closed', month: input.month, rows, scheme: input.team_id === 1 ? 'awc1' : 'jo23', schema_version: '2', summary: { exported: false, processing: null, routes: { payroll: { applicable: true, exported: false, processing: null }, sepa_guest: { applicable: true, exported: false, processing: null } } } }; batches.push(data); }
  else if (/\/batches\/\d+$/.test(path)) data = batches[0];
  else throw new Error(`Unmocked fixture request: ${path}`);
  return { data: structuredClone(data), status: 200, statusText: 'OK', headers: {}, config };
};
createRoot(document.getElementById('root')).render(<BrowserRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><div className="p-4 sm:p-8"><p className="mb-6 text-sm text-amber-800">Lokale test met verzonnen gegevens</p><MatchCompensation /></div></QueryClientProvider></BrowserRouter>);
