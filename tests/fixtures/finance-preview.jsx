import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import FinancesCard from '../../src/components/FinancesCard';
import CreditNoteForm from '../../src/components/finance/CreditNoteForm';
import InjuryCreditForm from '../../src/components/finance/InjuryCreditForm';
import api from '../../src/api/client';
import './finance-preview.css';
import '@fontsource/montserrat/600.css';
const user = { id: 1, can_access_financieel: true, can_edit_financieel: true, can_access_fairplay: false };
const invoice = { id: 42, person: { id: 1, name: 'Voorbeeldlid' }, invoice_number: '2025C123', invoice_type: 'membership', invoice_kind: 'normal', status: 'paid', total_amount: 237, season: '2025-2026', description: 'Contributie 2025-2026', linked_credits: [] };
api.defaults.adapter = async config => {
  const path = config.url;
  let data;
  if (path.endsWith('/user/me')) data = user;
  else if (path.endsWith('/history')) {
    const season = config.params?.season || '2026-2027';
    data = { person_id: 1, person_name: 'Voorbeeldlid', season, current_season: '2026-2027', seasons: ['2026-2027', '2025-2026', '2024-2025'], source: season === '2024-2025' ? 'nikki' : 'rondo', contribution_total: 237, contribution_paid: 237, invoices: season === '2024-2025' ? [] : [invoice], unassigned_invoices: [] };
  } else if (path.endsWith('/fees/person/1')) data = { season: '2026-2027', calculable: true, category_label: 'Senior', base_fee: 263, final_fee: 263, billing_method: 'rondo', nikki_total: null };
  else if (path.endsWith('/invoices/42')) data = invoice;
  else if (path.endsWith('/credits/preview')) {
    const input = JSON.parse(config.data);
    if (input.mode === 'injury' && !/^\d{4}-\d{2}-\d{2}$/.test(input.injury_start)) throw Object.assign(new Error('Begindatum ontbreekt'), {response:{status:400, data:{message:'Begindatum ontbreekt'}}});
    const amount = input.mode === 'injury' ? 237 * input.percentage / 100 - input.costs : input.amount;
    data = { amount, line_items: input.mode === 'injury' ? [{description: 'Restitutie contributie 2025-2026 (' + input.percentage + '%) · 2025C123', amount: -237 * input.percentage / 100}, {description: 'Inhouding gemaakte kosten', amount: input.costs}] : [{description: input.reason, amount: -amount}] };
  } else if (path.endsWith('/credits')) data = { id: 99 };
  else throw new Error('Geen echte API in dit testvoorbeeld: ' + path);
  return { data, status: 200, statusText: 'OK', headers: {}, config };
};
const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
client.setQueryData(['current-user'], user);
if (new URLSearchParams(location.search).has('dark')) document.documentElement.classList.add('dark');
createRoot(document.getElementById('root')).render(<QueryClientProvider client={client}><BrowserRouter><main className="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 sm:p-8 text-gray-900 dark:text-gray-100"><nav className="flex flex-wrap gap-5 text-sm mb-8"><strong>Testvoorbeeld · fictieve gegevens</strong><Link to="/?financeSeason=2025-2026">Historie</Link><Link to="/credit">Creditnota</Link><Link to="/blessure">Blessure</Link></nav><Routes><Route path="/" element={<div className="person-profile-layout max-w-lg"><h1 className="text-2xl font-semibold mb-6">Voorbeeldlid</h1><FinancesCard personId={1}/></div>}/><Route path="/credit" element={<CreditNoteForm sourceInvoiceId={42}/>}/><Route path="/blessure" element={<InjuryCreditForm personId={1} initialSeason="2025-2026"/>}/><Route path="*" element={<p>Concept opgeslagen in testvoorbeeld.</p>}/></Routes></main></BrowserRouter></QueryClientProvider>);
