import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Mail } from 'lucide-react';
import { prmApi } from '@/api/client';

const dateLabel = (value, includeTime = false) => new Intl.DateTimeFormat('nl-NL', {
  timeZone: 'Europe/Amsterdam', day: 'numeric', month: 'long', year: 'numeric',
  ...(includeTime ? { hour: '2-digit', minute: '2-digit' } : {}),
}).format(new Date(value.length === 10 ? `${value}T12:00:00+01:00` : value));

const overviewLabels = {
  accepted: 'Geaccepteerd voor verzending', failed: 'Verzenden mislukt', uncertain: 'Verzendstatus onbekend',
  reserved: 'Verzendstatus onbekend', disabled: 'Uitgeschakeld', not_recorded: 'Nog niet geregistreerd',
};

export default function VolunteerMailingCard() {
  const queryClient = useQueryClient();
  const [email, setEmail] = useState(null);
  const queryKey = ['volunteer', 'mailing'];
  const { data, isPending, error, refetch } = useQuery({
    queryKey,
    queryFn: async () => (await prmApi.getVolunteerMailing()).data,
    refetchInterval: 60_000,
  });
  const save = useMutation({
    mutationFn: async () => (await prmApi.updateVolunteerMailing({ overview_email: (email ?? data?.overview_email ?? '').trim() })).data,
    onSuccess: (result) => {
      queryClient.setQueryData(queryKey, result);
      setEmail(null);
    },
  });
  const last = data?.last_round;

  return (
    <section className="card p-5 space-y-4" aria-labelledby="volunteer-mailing-title">
      <div className="flex items-start gap-3">
        <Mail className="w-5 h-5 text-bright-cobalt dark:text-electric-cyan shrink-0" aria-hidden="true" />
        <div>
          <h2 id="volunteer-mailing-title" className="font-semibold text-gray-900 dark:text-gray-100">Mailing openstaande diensten</h2>
          <p className="text-sm text-gray-600 dark:text-gray-300 mt-1">Elke twee weken op zondag vanaf 19:00 uur. Leden met open verplichtingen ontvangen passende diensten voor de volgende drie weekenden.</p>
        </div>
      </div>
      {isPending ? <p role="status" className="text-sm">Verzendgegevens laden…</p> : error ? (
        <p role="alert" className="text-sm text-red-700 dark:text-red-300">De verzendgegevens konden niet worden geladen. <button type="button" className="underline" onClick={() => refetch()}>Opnieuw proberen</button></p>
      ) : (
        <>
          <dl className="grid gap-4 sm:grid-cols-2 text-sm">
            <div>
              <dt className="text-gray-500 dark:text-gray-400">Laatste verzendronde</dt>
              <dd className="font-medium mt-1">{last ? dateLabel(last.date) : 'Nog geen verzendronde geregistreerd'}</dd>
              {last && <dd className="text-gray-600 dark:text-gray-300 mt-1">{last.accepted} ledenmails geaccepteerd voor verzending · {last.pending > 0 ? `${last.pending} nog te verwerken` : 'Verwerking afgerond'}<br />{last.skipped > 0 && `${last.skipped} overgeslagen · `}{last.failed} mislukt · {last.uncertain} onbekend<br />Algemeen overzicht: {overviewLabels[last.overview_status] || 'Onbekend'}</dd>}
            </div>
            <div>
              <dt className="text-gray-500 dark:text-gray-400">Volgende verzendronde</dt>
              <dd className="font-medium mt-1">{dateLabel(data.next_at, true)} uur</dd>
              {!data.scheduled && <dd role="alert" className="text-amber-700 dark:text-amber-300 mt-1">De automatische verzending is momenteel niet ingepland.</dd>}
            </div>
          </dl>
          <form className="space-y-2 border-t border-gray-200 dark:border-gray-700 pt-4" onSubmit={(event) => { event.preventDefault(); save.mutate(); }}>
            <label htmlFor="volunteer-mailing-email" className="label">Ontvanger algemeen overzicht</label>
            <div className="flex flex-wrap items-center gap-2">
              <input id="volunteer-mailing-email" type="email" className="input flex-1 min-w-0 sm:max-w-sm" value={email ?? data.overview_email} disabled={save.isPending} onChange={(event) => { setEmail(event.target.value); save.reset(); }} aria-describedby="volunteer-mailing-email-help" />
              <button type="submit" className="btn-secondary" disabled={save.isPending}>{save.isPending ? 'Opslaan…' : 'Opslaan'}</button>
            </div>
            <p id="volunteer-mailing-email-help" className="text-sm text-gray-600 dark:text-gray-300">Dit adres ontvangt per verzendronde één algemeen overzicht van alle open diensten. Laat leeg om dit overzicht uit te schakelen. Wijzigingen gelden vanaf de volgende verzendronde.</p>
            {save.isSuccess && <p role="status" className="text-sm text-green-700 dark:text-green-300">E-mailadres opgeslagen.</p>}
            {save.error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{save.error.response?.data?.message || 'Het e-mailadres kon niet worden opgeslagen.'}</p>}
          </form>
        </>
      )}
    </section>
  );
}
