import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { formatCurrency } from '@/utils/formatters';

const tabs = [['overview', 'Overzicht'], ['products', 'Producten'], ['vat', 'Btw'], ['business', 'Businessclub']];
const statusLabels = { rondo_draft: 'Concept', rondo_sent: 'Verstuurd', rondo_paid: 'Betaald', rondo_overdue: 'Achterstallig', available: 'Nog te factureren', draft: 'In concept', billed: 'Gefactureerd' };
const amount = (value) => formatCurrency(value || 0, 2);
const dayLabel = (date) => new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'long' }).format(new Date(`${date.slice(0, 10)}T12:00:00`));
const panelClass = 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5';

function DataTable({ columns, rows }) {
  return <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr>{columns.map((column, index) => <th key={column} className={`border-b border-gray-200 dark:border-gray-700 py-3 font-medium text-gray-500 dark:text-gray-400 ${index ? 'text-right' : 'text-left'}`}>{column}</th>)}</tr></thead><tbody>{rows.map((row, index) => <tr key={index}>{row.map((cell, cellIndex) => <td key={cellIndex} className={`py-4 border-b border-gray-100 dark:border-gray-700 ${cellIndex ? 'text-right tabular-nums' : 'text-left'}`}>{cell}</td>)}</tr>)}</tbody></table></div>;
}

export default function Kassaomzet() {
  useDocumentTitle('Kassaomzet');
  const [tab, setTab] = useState('overview');
  const [month, setMonth] = useState(() => new Intl.DateTimeFormat('sv-SE', { year: 'numeric', month: '2-digit', timeZone: 'Europe/Amsterdam' }).format(new Date()));
  const [selectedDay, setSelectedDay] = useState(null);
  const [recipient, setRecipient] = useState({ name: '', address: '', email: '' });
  const [composing, setComposing] = useState(false);
  const { data: user } = useCurrentUser();
  const canWrite = user?.can_edit_financieel ?? false;
  const queryClient = useQueryClient();
  const range = { from: `${month}-01`, to: `${month}-${new Date(Number(month.slice(0, 4)), Number(month.slice(5)), 0).getDate()}` };
  const overview = useQuery({ queryKey: ['twelve', 'overview', month], queryFn: async () => {
    const [summary, categories] = await Promise.all([prmApi.getTwelve('summary', range), prmApi.getTwelve('categories', range)]);
    return { buckets: summary.data.buckets, categories: categories.data.categories };
  }, enabled: tab === 'overview' });
  const details = useQuery({ queryKey: ['twelve', tab, month], queryFn: async () => (await prmApi.getTwelve(tab, range)).data, enabled: tab === 'products' || tab === 'vat' });
  const billing = useQuery({ queryKey: ['twelve', 'billing'], queryFn: async () => (await prmApi.getTwelve('billing')).data, enabled: tab === 'business' });
  const action = useMutation({ mutationFn: async ({ type, id }) => {
    if (type === 'create') return prmApi.createTwelveInvoice(recipient);
    if (type === 'delete') return prmApi.deleteInvoice(id);
    return prmApi.sendInvoice(id);
  }, onSuccess: async () => { setComposing(false); await Promise.all([queryClient.invalidateQueries({ queryKey: ['twelve'] }), queryClient.invalidateQueries({ queryKey: ['invoices'] })]); } });
  const active = tab === 'overview' ? overview : tab === 'business' ? billing : details;
  const buckets = overview.data?.buckets ?? [];
  const totalSales = buckets.reduce((sum, row) => sum + row.omzet_excl_nosale, 0);
  const totalGross = buckets.reduce((sum, row) => sum + row.omzet_incl_nosale, 0);
  const draftInvoices = (billing.data?.invoices ?? []).filter(invoice => invoice.status === 'rondo_draft');

  return <div className="space-y-6 text-gray-900 dark:text-gray-100">
    <div className="flex flex-wrap justify-between items-center gap-4"><div><h1 className="text-2xl font-semibold">Kassaomzet</h1><p className="text-sm text-gray-500 dark:text-gray-400">Omzet en verbruik in de kantine</p></div>{tab !== 'business' && <label className="flex items-center gap-3 text-sm">Maand<input type="month" className="input" value={month} onChange={event => { if (event.target.value) { setMonth(event.target.value); setSelectedDay(null); } }} /></label>}</div>
    <div className="flex flex-wrap gap-6 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Kassaoverzichten">{tabs.map(([key, label]) => <button key={key} type="button" id={`kassa-tab-${key}`} role="tab" aria-controls={`kassa-panel-${key}`} aria-selected={tab === key} onClick={() => { setTab(key); action.reset(); }} className={`pb-3 border-b-2 text-sm font-medium ${tab === key ? 'border-electric-cyan text-cyan-800 dark:text-cyan-200' : 'border-transparent text-gray-500 dark:text-gray-400'}`}>{label}</button>)}</div>
    {action.isError && <p role="alert" className="text-red-700 dark:text-red-300">{action.error?.response?.data?.message || 'De factuuractie is mislukt. Probeer opnieuw; het concept blijft behouden.'}</p>}
    {active.isPending ? <p role="status">Rapportages laden…</p> : active.isError ? <div className={panelClass}><p role="alert">Rapportages konden niet worden geladen.</p><button className="btn-secondary mt-3" onClick={() => active.refetch()}>Opnieuw proberen</button></div> : <section id={`kassa-panel-${tab}`} role="tabpanel" aria-labelledby={`kassa-tab-${tab}`} className="space-y-5">
      {tab === 'overview' && (buckets.length === 0 ? <div className={panelClass}><h2 className="text-lg font-semibold">Nog geen rapportages</h2><p className="text-sm text-gray-500 dark:text-gray-400 mt-2">Voor deze maand zijn nog geen dagrapportages geïmporteerd.</p></div> : <>
        <div className={panelClass}><h2 className="text-lg font-semibold">{new Intl.DateTimeFormat('nl-NL', { month: 'long', year: 'numeric' }).format(new Date(`${month}-01T12:00:00`))}</h2><p className="text-sm text-gray-500 dark:text-gray-400">{buckets.length} geïmporteerde dagrapportages</p><dl className="flex flex-wrap gap-8 mt-5">{[['Omzet excl. no-sale', totalSales, 'Daadwerkelijke verkopen'], ['No-sale', totalGross - totalSales, 'Verbruik zonder betaling'], ['Totaal incl. no-sale', totalGross, `${buckets.reduce((sum, row) => sum + row.producten, 0)} producten`]].map(([label, value, note]) => <div key={label}><dt className="text-sm text-gray-500 dark:text-gray-400">{label}</dt><dd className="text-2xl font-semibold tabular-nums my-1">{amount(value)}</dd><p className="text-sm text-gray-500 dark:text-gray-400">{note}</p></div>)}</dl></div>
        <div className="grid lg:grid-cols-[minmax(0,1fr)_260px] gap-5"><div className={panelClass}><h2 className="text-lg font-semibold">Dagrapportages</h2><DataTable columns={['Dag', 'Omzet', 'No-sale']} rows={buckets.map(row => [<button key={row.periode} onClick={() => setSelectedDay(selectedDay === row.periode ? null : row.periode)} aria-expanded={selectedDay === row.periode} className="text-cyan-800 dark:text-cyan-200 font-medium">{dayLabel(row.periode)}</button>, amount(row.omzet_excl_nosale), amount(row.omzet_incl_nosale - row.omzet_excl_nosale)])} /><p className="text-xs text-gray-500 dark:text-gray-400 mt-4">Alleen geïmporteerde dagen worden getoond.</p>{selectedDay && <div className="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4"><h3 className="font-semibold">{dayLabel(selectedDay)}</h3>{Object.entries(buckets.find(row => row.periode === selectedDay)?.betaalmethoden ?? {}).map(([method, value]) => <div key={method} className="flex justify-between py-2 text-sm"><span>{method}</span><span>{amount(value)}</span></div>)}</div>}</div><div className={panelClass}><h2 className="text-lg font-semibold">No-sale per categorie</h2>{overview.data.categories.map(row => <div key={row.categorie} className="flex justify-between py-4 text-sm gap-3 border-b border-gray-100 dark:border-gray-700"><span>{row.categorie}</span><span className="tabular-nums">{amount(row.bedrag)}</span></div>)}<p className="text-xs text-gray-500 dark:text-gray-400 mt-4">Bedragen inclusief btw.</p></div></div>
      </>)}
      {tab === 'products' && <div className={panelClass}><h2 className="text-lg font-semibold">Verkochte en verbruikte producten</h2><p className="text-sm text-gray-500 dark:text-gray-400">Inclusief no-sale</p><DataTable columns={['Product', 'Aantal', 'Bruto']} rows={(details.data?.products ?? []).map(row => [row.product, row.aantal, amount(row.bruto)])} />{!details.data?.products?.length && <p className="mt-4 text-sm">Geen producten in deze periode.</p>}</div>}
      {tab === 'vat' && <div className={panelClass}><h2 className="text-lg font-semibold">Btw over verkopen</h2><p className="text-sm text-gray-500 dark:text-gray-400">Exclusief no-sale</p><DataTable columns={['Tarief', 'Netto', 'Btw', 'Bruto']} rows={(details.data?.vat ?? []).map(row => [row.tarief, amount(row.netto), amount(row.btw_totaal), amount(row.bruto)])} /></div>}
      {tab === 'business' && billing.data && <>
        <div className={panelClass}><h2 className="text-lg font-semibold">Nog te factureren</h2><div className="flex flex-wrap justify-between items-center gap-4 mt-3"><div><p className="text-3xl font-semibold tabular-nums">{amount(billing.data.total)}</p><p className="text-sm text-gray-500 dark:text-gray-400 mt-2">{amount(billing.data.available)} beschikbaar · {amount(billing.data.total - billing.data.available)} in concept</p></div>{canWrite && <button className="btn-primary" disabled={billing.data.available <= 0 || action.isPending} onClick={() => setComposing(true)}>Maak conceptfactuur</button>}</div><p className="text-xs text-gray-500 dark:text-gray-400 mt-4">Alle ongefactureerde dagen, ook uit eerdere maanden. De teller wordt bijgewerkt na succesvol versturen.</p>
          {composing && <form className="border-t border-gray-200 dark:border-gray-700 mt-5 pt-5 space-y-4" onSubmit={event => { event.preventDefault(); action.mutate({ type: 'create' }); }}><h3 className="font-semibold">Ontvanger Businessclub</h3><div className="grid sm:grid-cols-2 gap-4">{[['name', 'Naam'], ['email', 'E-mailadres']].map(([field, label]) => <label key={field} className="text-sm">{label}<input type={field === 'email' ? 'email' : 'text'} required className="input mt-1 w-full" value={recipient[field]} onChange={event => setRecipient({ ...recipient, [field]: event.target.value })} /></label>)}</div><label className="block text-sm">Factuuradres<textarea required className="input mt-1 w-full" value={recipient.address} onChange={event => setRecipient({ ...recipient, address: event.target.value })} /></label><div className="flex gap-3"><button className="btn-primary" disabled={action.isPending}>Concept aanmaken</button><button type="button" className="btn-secondary" onClick={() => setComposing(false)}>Annuleren</button></div></form>}
          {draftInvoices.map(invoice => <div key={invoice.id} className="border-t border-gray-200 dark:border-gray-700 pt-5 mt-5"><h3 className="font-semibold">Concept {invoice.number} · {amount(invoice.amount)}</h3><p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{invoice.recipient || 'Ontvanger ontbreekt'} · {invoice.email || 'E-mailadres ontbreekt'}</p>{canWrite && <div className="flex flex-wrap gap-3 mt-4"><Link className="btn-secondary" to={`/financien/facturen/${invoice.id}`}>Bekijk concept</Link><button className="btn-primary" disabled={action.isPending || !invoice.email} onClick={() => action.mutate({ type: 'send', id: invoice.id })}>Verstuur factuur</button><button className="btn-secondary" disabled={action.isPending} onClick={() => { if (window.confirm('Concept verwijderen? De omzetregels komen weer beschikbaar.')) action.mutate({ type: 'delete', id: invoice.id }); }}>Concept verwijderen</button></div>}</div>)}
        </div>
        <div className={panelClass}><h2 className="text-lg font-semibold">Omzetregels</h2><DataTable columns={['Dag', 'Bruto', 'Status']} rows={billing.data.rows.map(row => [dayLabel(row.datum), amount(row.bedrag), statusLabels[row.status]])} />{!billing.data.rows.length && <p className="text-sm mt-4">Nog geen businessclub-verbruik geïmporteerd.</p>}</div>
        <div className={panelClass}><h2 className="text-lg font-semibold">Factuurhistorie</h2><DataTable columns={['Factuur', 'Bedrag', 'Status']} rows={billing.data.invoices.map(invoice => [canWrite ? <Link key={invoice.id} className="text-cyan-800 dark:text-cyan-200" to={`/financien/facturen/${invoice.id}`}>{invoice.number}</Link> : invoice.number, amount(invoice.amount), statusLabels[invoice.status] || invoice.status])} />{!billing.data.invoices.length && <p className="text-sm mt-4">Nog geen businessclub-facturen.</p>}</div>
      </>}
    </section>}
  </div>;
}
