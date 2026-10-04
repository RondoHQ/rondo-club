import { Fragment, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { formatCurrency } from '@/utils/formatters';
import Omzetontwikkeling from './Omzetontwikkeling';
import ProductIndeling from './ProductIndeling';
import TwelveSchedule from './TwelveSchedule';

const tabs = [['overview', 'Overzicht'], ['trend', 'Omzetontwikkeling'], ['products', 'Producten'], ['vat', 'Btw'], ['business', 'Businessclub']];
const statusLabels = { rondo_draft: 'Concept', rondo_sent: 'Verstuurd', rondo_paid: 'Betaald', rondo_overdue: 'Achterstallig', provisional: 'Dag nog niet afgesloten', historical: 'Historische import', available: 'Nog te factureren', draft: 'In concept', billed: 'Gefactureerd' };
const amount = (value) => formatCurrency(value || 0, 2);
const dayLabel = (date) => new Intl.DateTimeFormat('nl-NL', { day: 'numeric', month: 'long' }).format(new Date(`${date.slice(0, 10)}T12:00:00`));
const panelClass = 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5';

function DataTable({ columns, rows, expandedRows = {} }) {
  return <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr>{columns.map((column, index) => <th key={column} className={`border-b border-gray-200 dark:border-gray-700 py-3 font-medium text-gray-500 dark:text-gray-400 ${index ? 'text-right' : 'text-left'}`}>{column}</th>)}</tr></thead><tbody>{rows.map((row, index) => <Fragment key={index}><tr>{row.map((cell, cellIndex) => <td key={cellIndex} className={`py-4 border-b border-gray-100 dark:border-gray-700 ${cellIndex ? 'text-right tabular-nums' : 'text-left'}`}>{cell}</td>)}</tr>{expandedRows[index] && <tr><td colSpan={columns.length} className="pb-4">{expandedRows[index]}</td></tr>}</Fragment>)}</tbody></table></div>;
}

function ProductsTable({ products, emptyText }) {
  return products.length ? <DataTable columns={['Product', 'Aantal', 'Bruto']} rows={products.map(row => [row.product, row.aantal, amount(row.bruto)])} /> : <p className="mt-4 text-sm">{emptyText}</p>;
}

function DayReportDetails({ day }) {
  const noSales = useQuery({
    queryKey: ['twelve', 'no-sales', day.periode],
    queryFn: async () => (await prmApi.getTwelve('no-sales', { from: day.periode, to: day.periode })).data.transactions,
  });
  const products = useQuery({
    queryKey: ['twelve', 'products', 'day', day.periode],
    queryFn: async () => (await prmApi.getTwelve('products', { from: day.periode, to: day.periode })).data.products,
  });

  return <div id={`kassa-day-${day.periode}`} className="rounded-lg bg-gray-50 dark:bg-gray-900 p-4 space-y-6">
    <section aria-labelledby={`kassa-payments-${day.periode}`}>
      <h3 id={`kassa-payments-${day.periode}`} className="text-sm font-semibold mb-2 dark:text-gray-100">Betaalmethoden · {dayLabel(day.periode)}</h3>
      {Object.keys(day.betaalmethoden ?? {}).length ? Object.entries(day.betaalmethoden).map(([method, value]) => <div key={method} className="flex justify-between py-1 text-sm gap-3"><span>{method}</span><span className="tabular-nums whitespace-nowrap">{amount(value)}</span></div>) : <p className="text-sm text-gray-500 dark:text-gray-400">Er zijn geen betaalmethoden ingelezen.</p>}
    </section>
    <section aria-labelledby={`kassa-nosales-${day.periode}`}>
      <h3 id={`kassa-nosales-${day.periode}`} className="text-sm font-semibold mb-2">No-sales · {dayLabel(day.periode)}</h3>
      {noSales.isPending ? <p role="status" className="text-sm">No-sales laden…</p> : noSales.isError ? <div><p role="alert" className="text-sm">No-sales konden niet worden geladen.</p><button type="button" className="btn-secondary mt-3" onClick={() => noSales.refetch()}>Opnieuw proberen</button></div> : noSales.data.length ? <ul className="divide-y divide-gray-200 dark:divide-gray-700">{noSales.data.map(row => <li key={row.transactionId} className="py-3 text-sm">
        <div className="flex justify-between gap-4"><span>{row.localTime.slice(11)} · {row.category}</span><strong className="tabular-nums whitespace-nowrap">{amount(row.grossCents / 100)}</strong></div>
        {row.terminal && <p className="text-gray-500 dark:text-gray-400">{row.terminal}</p>}
        <p className="mt-1">{row.partial ? 'Gedeeltelijke no-sale: de specifieke producten zijn niet vastgelegd.' : row.products.map(p => `${p.count} × ${p.name}`).join(', ')}</p>
      </li>)}</ul> : <p className="text-sm text-gray-500 dark:text-gray-400">Geen no-sale-details beschikbaar voor deze dag.</p>}
    </section>
    <section aria-labelledby={`kassa-products-${day.periode}`}>
      <h3 id={`kassa-products-${day.periode}`} className="text-sm font-semibold mb-2 dark:text-gray-100">Producten · {dayLabel(day.periode)}</h3>
      <p className="text-sm text-gray-500 dark:text-gray-400">Verkocht en verbruikt, inclusief no-sale. Bedragen inclusief btw.</p>
      {products.isPending ? <p role="status" className="mt-4 text-sm">Producten laden…</p> : products.isError ? <div className="mt-4"><p role="alert" className="text-sm text-red-700 dark:text-red-300">Producten voor deze dag konden niet worden geladen.</p><button type="button" className="btn-secondary mt-3" onClick={() => products.refetch()}>Opnieuw proberen</button></div> : <ProductsTable products={products.data} emptyText="Geen producten ingelezen voor deze dag." />}
    </section>
  </div>;
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
    return { buckets: summary.data.buckets, categories: categories.data.categories, lastSync: summary.data.last_sync, productMix: summary.data.product_mix };
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
  const totalSales = buckets.reduce((sum, row) => sum + row.omzet_totaal, 0);
  const totalCash = buckets.reduce((sum, row) => sum + row.kassaomzet, 0);
  const totalBusiness = buckets.reduce((sum, row) => sum + row.businessclub, 0);
  const totalGross = buckets.reduce((sum, row) => sum + row.omzet_incl_nosale, 0);
  const draftInvoices = (billing.data?.invoices ?? []).filter(invoice => invoice.status === 'rondo_draft');

  return <div className="space-y-6 text-gray-900 dark:text-gray-100">
    <div className="flex flex-wrap justify-between items-center gap-4"><div><h1 className="text-2xl font-semibold">Kassaomzet</h1><p className="text-sm text-gray-500 dark:text-gray-400">Omzet en verbruik in de kantine</p></div>{tab !== 'business' && tab !== 'trend' && tab !== 'groups' && tab !== 'schedule' && <label className="flex items-center gap-3 text-sm">Maand<input type="month" className="input" value={month} onChange={event => { if (event.target.value) { setMonth(event.target.value); setSelectedDay(null); } }} /></label>}</div>
    <div className="flex flex-wrap gap-6 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Kassaoverzichten">{[...tabs, ...(user?.is_admin ? [['groups', 'Productindeling'], ['schedule', 'Synchronisatie']] : [])].map(([key, label]) => <button key={key} type="button" id={`kassa-tab-${key}`} role="tab" aria-controls={`kassa-panel-${key}`} aria-selected={tab === key} onClick={() => { setTab(key); action.reset(); }} className={`pb-3 border-b-2 text-sm font-medium ${tab === key ? 'border-electric-cyan text-cyan-800 dark:text-cyan-200' : 'border-transparent text-gray-500 dark:text-gray-400'}`}>{label}</button>)}</div>
    {action.isError && <p role="alert" className="text-red-700 dark:text-red-300">{action.error?.response?.data?.message || 'De factuuractie is mislukt. Probeer opnieuw; het concept blijft behouden.'}</p>}
    {tab === 'schedule' && user?.is_admin ? <section id="kassa-panel-schedule" role="tabpanel" aria-labelledby="kassa-tab-schedule"><TwelveSchedule /></section> : tab === 'groups' && user?.is_admin ? <section id="kassa-panel-groups" role="tabpanel" aria-labelledby="kassa-tab-groups"><ProductIndeling /></section> : tab === 'trend' ? <section id="kassa-panel-trend" role="tabpanel" aria-labelledby="kassa-tab-trend"><Omzetontwikkeling /></section> : active.isPending ? <p role="status">Rapportages laden…</p> : active.isError ? <div className={panelClass}><p role="alert">Rapportages konden niet worden geladen.</p><button className="btn-secondary mt-3" onClick={() => active.refetch()}>Opnieuw proberen</button></div> : <section id={`kassa-panel-${tab}`} role="tabpanel" aria-labelledby={`kassa-tab-${tab}`} className="space-y-5">
      {tab === 'overview' && (buckets.length === 0 ? <div className={panelClass}><h2 className="text-lg font-semibold">Nog geen rapportages</h2><p className="text-sm text-gray-500 dark:text-gray-400 mt-2">Voor deze maand zijn nog geen dagrapportages geïmporteerd.</p></div> : <>
        <div className={panelClass}><h2 className="text-lg font-semibold">{new Intl.DateTimeFormat('nl-NL', { month: 'long', year: 'numeric' }).format(new Date(`${month}-01T12:00:00`))}</h2><p className="text-sm text-gray-500 dark:text-gray-400">{buckets.length} geïmporteerde dagrapportages</p><dl className="flex flex-wrap gap-8 mt-5">{[['Totale omzet', totalSales, 'Kassaomzet + businessclub'], ['Kassaomzet', totalCash, 'Verkopen via de kassa'], ['Businessclub', totalBusiness, 'Wordt apart gefactureerd'], ['Overig verbruik', totalGross - totalSales, 'No-sales, kortingen en muntverschillen'], ['Totaal producten', totalGross, `${buckets.reduce((sum, row) => sum + row.producten, 0)} producten`]].map(([label, value, note]) => <div key={label}><dt className="text-sm text-gray-500 dark:text-gray-400">{label}</dt><dd className="text-2xl font-semibold tabular-nums my-1">{amount(value)}</dd><p className="text-sm text-gray-500 dark:text-gray-400">{note}</p></div>)}</dl></div>
        {overview.data.productMix && <div className={panelClass}>
          <h2 className="text-lg font-semibold">Entree, food en non-food</h2>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Aandeel van de productbedragen, inclusief btw en no-sale. De indeling geldt voor alle rapporten.</p>
          <dl className="grid grid-cols-2 lg:grid-cols-4 gap-6 mt-5">{overview.data.productMix.groups.map(group => <div key={group.group}><dt className="text-sm text-gray-500 dark:text-gray-400">{group.label}</dt><dd className="text-2xl font-semibold tabular-nums my-1">{group.percentage === null ? '—' : `${new Intl.NumberFormat('nl-NL', { maximumFractionDigits: 2 }).format(group.percentage)}%`}</dd><p className="text-sm tabular-nums">{amount(group.amount)}</p></div>)}</dl>
          {user?.is_admin && <button type="button" className="btn-secondary mt-5" onClick={() => setTab('groups')}>Producten indelen</button>}
          {overview.data.productMix.total <= 0 && <p className="text-sm text-gray-500 dark:text-gray-400 mt-3">Geen percentages bij een totaal van nul of lager.</p>}
        </div>}
        <div className="grid lg:grid-cols-[minmax(0,1fr)_260px] gap-5"><div className={panelClass}><h2 className="text-lg font-semibold">Dagrapportages</h2><DataTable columns={['Dag', 'Kassaomzet', 'Businessclub', 'Totale omzet', 'Overig verbruik']} expandedRows={Object.fromEntries(buckets.flatMap((row, index) => row.periode === selectedDay ? [[index, <DayReportDetails key={row.periode} day={row} />]] : []))} rows={buckets.map(row => [<button key={row.periode} onClick={() => setSelectedDay(selectedDay === row.periode ? null : row.periode)} aria-expanded={selectedDay === row.periode} aria-controls={selectedDay === row.periode ? `kassa-day-${row.periode}` : undefined} className="text-cyan-800 dark:text-cyan-200 font-medium">{dayLabel(row.periode)}{row.provisional && <span className="block text-xs font-normal">Voorlopig</span>}</button>, amount(row.kassaomzet), amount(row.businessclub), <strong key="total">{amount(row.omzet_totaal)}</strong>, amount(row.overig_verbruik)])} /><p className="text-xs text-gray-500 dark:text-gray-400 mt-4">Alleen geïmporteerde dagen worden getoond. De kassadag loopt van 06:00 tot 06:00.</p>{overview.data.lastSync && <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">Laatste import: {new Intl.DateTimeFormat('nl-NL', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Amsterdam' }).format(new Date(overview.data.lastSync))}. Wordt bijgewerkt volgens de ingestelde synchronisatietijden.</p>}</div><div className={panelClass}><h2 className="text-lg font-semibold">Overig verbruik per categorie</h2>{overview.data.categories.filter(row => row.categorie !== 'Businessclub').map(row => <div key={row.categorie} className="flex justify-between py-4 text-sm gap-3 border-b border-gray-100 dark:border-gray-700"><span>{row.categorie}</span><span className="tabular-nums">{amount(row.bedrag)}</span></div>)}<p className="text-xs text-gray-500 dark:text-gray-400 mt-4">Bedragen inclusief btw.</p></div></div>
      </>)}
      {tab === 'products' && <div className={panelClass}><h2 className="text-lg font-semibold">Verkochte en verbruikte producten</h2><p className="text-sm text-gray-500 dark:text-gray-400">Inclusief no-sale</p><ProductsTable products={details.data?.products ?? []} emptyText="Geen producten in deze periode." /></div>}
      {tab === 'vat' && <div className={panelClass}><h2 className="text-lg font-semibold">Btw over verkopen</h2><p className="text-sm text-gray-500 dark:text-gray-400">Exclusief no-sale</p><DataTable columns={['Tarief', 'Netto', 'Btw', 'Bruto']} rows={(details.data?.vat ?? []).map(row => [row.tarief, amount(row.netto), amount(row.btw_totaal), amount(row.bruto)])} /></div>}
      {tab === 'business' && billing.data && <>
        <div className={panelClass}><h2 className="text-lg font-semibold">Nog te factureren</h2><div className="flex flex-wrap justify-between items-center gap-4 mt-3"><div><p className="text-3xl font-semibold tabular-nums">{amount(billing.data.total)}</p><p className="text-sm text-gray-500 dark:text-gray-400 mt-2">{amount(billing.data.available)} beschikbaar · {amount(billing.data.total - billing.data.available)} in concept</p></div>{canWrite && <button className="btn-primary" disabled={billing.data.available <= 0 || action.isPending} onClick={() => setComposing(true)}>Maak conceptfactuur</button>}</div><p className="text-xs text-gray-500 dark:text-gray-400 mt-4">Afgesloten, ongefactureerde dagen. Historische imports van vóór de oorspronkelijke rapportages vallen buiten de facturatie. De teller wordt bijgewerkt na succesvol versturen.</p>
          {composing && <form className="border-t border-gray-200 dark:border-gray-700 mt-5 pt-5 space-y-4" onSubmit={event => { event.preventDefault(); action.mutate({ type: 'create' }); }}><h3 className="font-semibold">Ontvanger Businessclub</h3><div className="grid sm:grid-cols-2 gap-4">{[['name', 'Naam'], ['email', 'E-mailadres']].map(([field, label]) => <label key={field} className="text-sm">{label}<input type={field === 'email' ? 'email' : 'text'} required className="input mt-1 w-full" value={recipient[field]} onChange={event => setRecipient({ ...recipient, [field]: event.target.value })} /></label>)}</div><label className="block text-sm">Factuuradres<textarea required className="input mt-1 w-full" value={recipient.address} onChange={event => setRecipient({ ...recipient, address: event.target.value })} /></label><div className="flex gap-3"><button className="btn-primary" disabled={action.isPending}>Concept aanmaken</button><button type="button" className="btn-secondary" onClick={() => setComposing(false)}>Annuleren</button></div></form>}
          {draftInvoices.map(invoice => <div key={invoice.id} className="border-t border-gray-200 dark:border-gray-700 pt-5 mt-5"><h3 className="font-semibold">Concept {invoice.number} · {amount(invoice.amount)}</h3><p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{invoice.recipient || 'Ontvanger ontbreekt'} · {invoice.email || 'E-mailadres ontbreekt'}</p>{canWrite && <div className="flex flex-wrap gap-3 mt-4"><Link className="btn-secondary" to={`/financien/facturen/${invoice.id}`}>Bekijk concept</Link><button className="btn-primary" disabled={action.isPending || !invoice.email} onClick={() => action.mutate({ type: 'send', id: invoice.id })}>Verstuur factuur</button><button className="btn-secondary" disabled={action.isPending} onClick={() => { if (window.confirm('Concept verwijderen? De omzetregels komen weer beschikbaar.')) action.mutate({ type: 'delete', id: invoice.id }); }}>Concept verwijderen</button></div>}</div>)}
        </div>
        <div className={panelClass}><h2 className="text-lg font-semibold">Omzetregels</h2><DataTable columns={['Dag', 'Bruto', 'Status']} rows={billing.data.rows.map(row => [dayLabel(row.datum), amount(row.bedrag), statusLabels[row.status]])} />{!billing.data.rows.length && <p className="text-sm mt-4">Nog geen businessclub-verbruik geïmporteerd.</p>}</div>
        <div className={panelClass}><h2 className="text-lg font-semibold">Factuurhistorie</h2><DataTable columns={['Factuur', 'Bedrag', 'Status']} rows={billing.data.invoices.map(invoice => [canWrite ? <Link key={invoice.id} className="text-cyan-800 dark:text-cyan-200" to={`/financien/facturen/${invoice.id}`}>{invoice.number}</Link> : invoice.number, amount(invoice.amount), statusLabels[invoice.status] || invoice.status])} />{!billing.data.invoices.length && <p className="text-sm mt-4">Nog geen businessclub-facturen.</p>}</div>
      </>}
    </section>}
  </div>;
}
