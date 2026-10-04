import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Download } from 'lucide-react';
import { prmApi } from '@/api/client';
import { formatCurrency } from '@/utils/formatters';
import { format } from '@/utils/dateFormat';

function ExportForm({ invoiceId, context, onDownloaded }) {
  const [payment, setPayment] = useState(context.defaults);
  const [confirmed, setConfirmed] = useState(false);
  const requestId = useRef(crypto.randomUUID());
  const queryClient = useQueryClient();
  const existing = context.export;
  const queryKey = ['invoice', invoiceId, 'sepa-export'];
  const mutation = useMutation({
    mutationFn: async () => {
      const response = await prmApi.createCreditSepaExport(invoiceId, {
        ...payment,
        confirmed,
        expected_amount_cents: context.amount_cents,
        previous_export_id: existing?.export_id || '',
        request_id: requestId.current,
      });
      return response.data;
    },
    onSuccess: (data) => {
      const url = URL.createObjectURL(new Blob([data.xml], { type: 'application/xml;charset=utf-8' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = data.filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      onDownloaded();
      setConfirmed(false);
      queryClient.invalidateQueries({ queryKey });
    },
    onError: () => {
      setConfirmed(false);
      queryClient.invalidateQueries({ queryKey });
    },
  });

  return (
    <form className="mt-5 space-y-4" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }}>
      {existing && (
        <p role="status" className="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
          Dit betaalbestand is aangemaakt op {format(new Date(existing.created_at), 'd MMM yyyy HH:mm')}. Je downloadt hetzelfde bestand met dezelfde betaalreferentie. Importeer het niet opnieuw als de betaling al in Rabobank staat.
        </p>
      )}
      <p className="text-sm text-gray-700 dark:text-gray-300">Terug te betalen: <strong>{formatCurrency(context.amount_cents / 100, 2)}</strong>. De omschrijving bevat het creditfactuurnummer.</p>
      <fieldset disabled={mutation.isPending || !!existing} className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Ontvanger</legend>
        <div>
          <label htmlFor="sepa-creditor-name" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tenaamstelling ontvanger</label>
          <input id="sepa-creditor-name" className="input w-full" required maxLength={70} value={payment.creditor_name} onChange={(event) => setPayment({ ...payment, creditor_name: event.target.value })} />
        </div>
        <div>
          <label htmlFor="sepa-creditor-iban" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">IBAN ontvanger</label>
          <input id="sepa-creditor-iban" className="input w-full" required maxLength={42} autoCapitalize="characters" spellCheck={false} value={payment.creditor_iban} onChange={(event) => setPayment({ ...payment, creditor_iban: event.target.value.toUpperCase() })} aria-describedby="sepa-recipient-help" />
        </div>
        <p id="sepa-recipient-help" className="text-sm text-gray-600 dark:text-gray-400 sm:col-span-2">Controleer de rekeninghouder en het IBAN. Gegevens uit een gekoppelde oorspronkelijke betaling worden waar beschikbaar ingevuld.</p>
      </fieldset>
      <fieldset disabled={mutation.isPending || !!existing} className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">Afschrijven van</legend>
        <div>
          <label htmlFor="sepa-debtor-name" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tenaamstelling clubrekening</label>
          <input id="sepa-debtor-name" className="input w-full" required maxLength={70} value={payment.debtor_name} onChange={(event) => setPayment({ ...payment, debtor_name: event.target.value })} />
        </div>
        <div>
          <label htmlFor="sepa-debtor-iban" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">IBAN clubrekening (Rabobank)</label>
          <input id="sepa-debtor-iban" className="input w-full" required maxLength={24} autoCapitalize="characters" spellCheck={false} value={payment.debtor_iban} onChange={(event) => setPayment({ ...payment, debtor_iban: event.target.value.toUpperCase() })} />
        </div>
        <div>
          <label htmlFor="sepa-date" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Uitvoerdatum</label>
          <input id="sepa-date" className="input w-full" type="date" required min={existing ? undefined : context.today} value={payment.execution_date} onChange={(event) => setPayment({ ...payment, execution_date: event.target.value })} />
        </div>
      </fieldset>
      <label className="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
        <input type="checkbox" className="mt-1 h-4 w-4 shrink-0" checked={confirmed} disabled={mutation.isPending} onChange={(event) => setConfirmed(event.target.checked)} required />
        <span>Ik heb de betaalgegevens gecontroleerd. Dit bedrag is nog niet terugbetaald of verrekend en er staat geen betaalopdracht hiervoor klaar in Rabobank.</span>
      </label>
      {mutation.isError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{mutation.error.response?.data?.message || 'Downloaden is niet gelukt. Probeer het opnieuw; een eerder aangemaakt bestand blijft bewaard.'}</p>}
      <button type="submit" className="btn-primary gap-2" disabled={!confirmed || mutation.isPending}>
        <Download className="h-4 w-4 shrink-0" aria-hidden="true" />
        {mutation.isPending ? 'Betaalbestand ophalen…' : existing ? 'Hetzelfde betaalbestand opnieuw downloaden' : 'Download betaalbestand voor Rabobank'}
      </button>
      {existing && <p className="text-sm text-gray-600 dark:text-gray-400">De gegevens van een aangemaakt bestand staan vast. Een download bevestigt geen betaling; controleer de verwerking in Rabobank.</p>}
    </form>
  );
}

export default function CreditSepaExport({ invoice }) {
  const [expanded, setExpanded] = useState(false);
  const [downloaded, setDownloaded] = useState(false);
  const eligible = ['sent', 'overdue'].includes(invoice.status);
  const query = useQuery({
    queryKey: ['invoice', invoice.id, 'sepa-export'],
    queryFn: async () => (await prmApi.getCreditSepaExport(invoice.id)).data,
    enabled: expanded && eligible,
    staleTime: 0,
    gcTime: 0,
  });

  return (
    <section className="card p-6" aria-labelledby="sepa-heading">
      <h2 id="sepa-heading" className="text-lg font-semibold text-gray-900 dark:text-gray-100">Terugbetaling via Rabobank</h2>
      {!eligible ? (
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{invoice.status === 'paid' ? 'Deze creditfactuur is als betaald gemarkeerd. Er is geen nieuwe terugbetaling beschikbaar.' : invoice.status === 'cancelled' ? 'Deze creditfactuur is vervallen. Er is geen terugbetaling beschikbaar.' : 'Verstuur de creditfactuur voordat je een betaalbestand maakt.'}</p>
      ) : (
        <>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">Download een SEPA-betaalbestand voor deze creditfactuur. Importeer het in Rabobank en keur daar de betaling goed.</p>
          <button type="button" className="btn-secondary mt-3" aria-expanded={expanded} aria-controls="sepa-form" onClick={() => setExpanded(!expanded)}>{expanded ? 'Sluit betaalgegevens' : 'Betaalbestand voorbereiden'}</button>
          {expanded && <div id="sepa-form">
            {query.isPending && <p role="status" className="mt-4 text-sm text-gray-600 dark:text-gray-400">Betaalgegevens laden…</p>}
            {query.isError && <p role="alert" className="mt-4 text-sm text-red-700 dark:text-red-300">Betaalgegevens laden is niet gelukt. <button type="button" className="underline" onClick={() => query.refetch()}>Probeer opnieuw</button></p>}
            {query.data?.blocked_reason && <p role="alert" className="mt-4 text-sm text-amber-800 dark:text-amber-200">{query.data.blocked_reason}</p>}
            {query.data && !query.data.blocked_reason && <ExportForm key={query.data.export?.export_id || 'new'} invoiceId={invoice.id} context={query.data} onDownloaded={() => setDownloaded(true)} />}
          </div>}
          {downloaded && <p role="status" className="mt-4 text-sm text-green-700 dark:text-green-300">Download gestart. Importeer het bestand in Rabobank en controleer en onderteken daar de betaling. Markeer de creditfactuur pas als betaald nadat de betaling is uitgevoerd.</p>}
        </>
      )}
    </section>
  );
}
