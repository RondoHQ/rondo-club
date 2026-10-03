import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft } from 'lucide-react';
import { useCreditDraft } from '@/hooks/useCreditDraft';
import { prmApi } from '@/api/client';
import { formatCurrency } from '@/utils/formatters';
import CreditPreview from './CreditPreview';

export default function InjuryCreditForm({ personId, initialSeason = '' }) {
  const [season, setSeason] = useState(initialSeason);
  const [sourceId, setSourceId] = useState('');
  const [start, setStart] = useState('');
  const [end, setEnd] = useState('');
  const [percentage, setPercentage] = useState('');
  const [costs, setCosts] = useState('50');
  const [confirmed, setConfirmed] = useState(false);
  const { data: history, isLoading, error: loadError } = useQuery({
    queryKey: ['invoices', 'person', Number(personId), 'history', season],
    queryFn: () => prmApi.getPersonFinanceHistory(personId, season ? { season } : {}).then(res => res.data),
  });
  const selectedSeason = history?.season || season;
  const sources = (history?.invoices || []).filter(invoice => invoice.invoice_type === 'membership' && invoice.invoice_kind !== 'credit' && invoice.status === 'paid');
  const selectedSource = sources.find(invoice => invoice.id === Number(sourceId)) || sources[0];
  const seasonStart = selectedSeason ? `${selectedSeason.slice(0, 4)}-07-01` : '';
  const seasonEnd = selectedSeason ? `${selectedSeason.slice(5)}-06-30` : '';
  const paid = history?.source === 'rondo' ? selectedSource?.total_amount : history?.contribution_paid;
  const payload = {
    mode: 'injury', person_id: Number(personId), season: selectedSeason,
    source_invoice_id: history?.source === 'rondo' ? selectedSource?.id || 0 : 0,
    injury_start: start, injury_end: end || seasonEnd,
    percentage: Number(percentage), costs: Number(costs), conditions_confirmed: confirmed,
  };
  const { preview, pending, error, reset, submit } = useCreditDraft(payload);
  const change = (setter, value) => { setter(value); reset(); };
  return <div className="max-w-3xl space-y-5">
    <Link to={`/people/${personId}?financeSeason=${selectedSeason}`} className="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300"><ArrowLeft className="w-4 h-4" />Terug naar lid</Link>
    <form onSubmit={submit} className="card p-5 sm:p-6 space-y-5">
      <div><h1 className="text-2xl font-semibold">Creditnota bij blessure</h1><p className="mt-2 text-gray-600 dark:text-gray-300">{history?.person_name || 'Lid laden…'}</p></div>
      <fieldset disabled={pending} className="space-y-5 disabled:opacity-70">
        <label className="block text-sm font-medium">Seizoen<select className="input mt-1 w-full" value={selectedSeason} onChange={event => { change(setSeason, event.target.value); setSourceId(''); setStart(''); setEnd(''); setConfirmed(false); }}>
          {(history?.seasons || [initialSeason]).filter(Boolean).map(value => <option key={value} value={value}>{value}</option>)}
        </select></label>
        {isLoading ? <p role="status">Financiële gegevens laden…</p> : loadError ? <p role="alert" className="text-red-700 dark:text-red-300">De financiële gegevens konden niet worden geladen. Probeer de pagina opnieuw te openen.</p> : <>
          {sources.length > 1 && <label className="block text-sm font-medium">Contributiefactuur<select className="input mt-1 w-full" value={selectedSource?.id || ''} onChange={event => change(setSourceId, event.target.value)}>{sources.map(invoice => <option key={invoice.id} value={invoice.id}>{invoice.invoice_number} · {formatCurrency(invoice.total_amount, 2)}</option>)}</select></label>}
          <div className="border-y border-gray-200 dark:border-gray-700 py-4 space-y-2 text-sm">
            <div className="flex flex-wrap justify-between gap-2"><span>Betaalde contributie {selectedSource ? `· ${selectedSource.invoice_number}` : history?.source === 'nikki' ? '· Nikki' : ''}</span><strong className="tabular-nums">{paid == null ? 'Onbekend' : formatCurrency(paid, 2)}</strong></div>
            <p className="text-gray-600 dark:text-gray-300">{sources.length > 1 ? 'De berekening geldt voor de geselecteerde factuur.' : history?.source === 'nikki' ? 'Bedragen overgenomen uit Nikki; er is geen oorspronkelijke factuur in Rondo.' : selectedSource ? 'Bedrag overgenomen uit de betaalde contributiefactuur.' : 'Er is geen betaalde contributiefactuur beschikbaar voor dit seizoen.'}</p>
            {history?.contribution_paid == null || history?.contribution_paid < history?.contribution_total ? <p className="text-amber-800 dark:text-amber-200">Volledige betaling van de seizoenscontributie is nog niet bevestigd.</p> : null}
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            <label className="block text-sm font-medium">Eerste dag uitval<input className="input mt-1 w-full" type="date" required min={seasonStart} max={seasonEnd} value={start} onChange={event => change(setStart, event.target.value)} /></label>
            <label className="block text-sm font-medium">Laatste dag uitval in dit seizoen<input className="input mt-1 w-full" type="date" required min={start || seasonStart} max={seasonEnd} value={end || seasonEnd} onChange={event => change(setEnd, event.target.value)} /></label>
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            <label className="block text-sm font-medium">Te restitueren percentage<select className="input mt-1 w-full" required value={percentage} onChange={event => change(setPercentage, event.target.value)}><option value="">Kies een percentage</option>{[25, 50, 75, 100].map(value => <option key={value} value={value}>{value}%</option>)}</select></label>
            <label className="block text-sm font-medium">Gemaakte kosten (€)<input className="input mt-1 w-full" type="number" required min="50" step="0.01" value={costs} onChange={event => change(setCosts, event.target.value)} /><span className="block mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">Minimaal €50 wordt ingehouden.</span></label>
          </div>
          <p className="text-sm text-gray-600 dark:text-gray-300">De penningmeester kiest het percentage; Rondo berekent betaalde contributie × percentage − gemaakte kosten.</p>
          <label className="flex items-start gap-3 text-sm"><input type="checkbox" required checked={confirmed} onChange={event => change(setConfirmed, event.target.checked)} className="mt-1 shrink-0 accent-cyan-700" /><span>De trainer of coördinator heeft de langdurige uitval bevestigd. Deze blessure is in dit seizoen ontstaan en is niet al in een eerder seizoen vergoed.</span></label>
          <p className="text-sm text-gray-600 dark:text-gray-300">De regeling geldt na afloop van het seizoen, bij meer dan een half seizoen uitval en volledige betaling. <a className="underline text-cyan-800 dark:text-cyan-200" href="https://www.svawc.nl/leden/blessures/" target="_blank" rel="noreferrer">Bekijk de AWC-voorwaarden</a>.</p>
        </>}
      </fieldset>
      <CreditPreview preview={preview} />
      {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}
      <button className="btn-primary" disabled={pending || isLoading || !!loadError || !paid}>{pending ? 'Bezig…' : preview ? 'Opslaan als concept' : 'Bereken creditnota'}</button>
    </form>
  </div>;
}
