import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';

const COVERAGE = { person: 'Persoonsgegevens', parents: 'Oudercontacten', teams: 'Teams', functions: 'Functies', vog: 'VOG-gegevens' };
const dateLabel = (date) => date ? new Intl.DateTimeFormat('nl-NL', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Amsterdam' }).format(new Date(date)) : 'Nog niet vastgesteld';

export default function OnboardingSimulation() {
  const [input, setInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const { data, isPending, error, isFetching, refetch } = useQuery({
    queryKey: ['onboarding-simulation', search, page],
    queryFn: async () => (await prmApi.getOnboardingSimulation({ search, page })).data,
  });

  return (
    <section className="space-y-4" aria-label="Onboarding simulatie">
      <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-950 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100">
        <h3 className="font-semibold text-blue-950 dark:text-blue-100">Proefweergave — er worden geen e-mails verstuurd</h3>
        <p className="mt-1 text-sm">Bekijk de ontvangers, beschikbare mailblokken en ontbrekende gegevens per persoon. Dit is nog geen volledige e-mailpreview. De gerichte broncontrole vanuit Sync en de verzending worden in volgende stappen aangesloten.</p>
      </div>
      <form className="flex flex-wrap gap-2" onSubmit={(event) => { event.preventDefault(); setSearch(input.trim()); setPage(1); }}>
        <label className="sr-only" htmlFor="onboarding-search">Zoek een persoon</label>
        <input id="onboarding-search" className="input flex-1 min-w-48" value={input} onChange={(event) => setInput(event.target.value)} placeholder="Zoek een persoon" />
        <button className="btn-primary" type="submit">Zoeken</button>
        <button className="btn-secondary" type="button" disabled={isFetching} onClick={() => refetch()}>Vernieuwen</button>
      </form>
      {isPending ? <p role="status">Simulatie laden…</p> : null}
      {error ? <p role="alert" className="text-red-600">{error.response?.data?.message || 'De simulatie kon niet worden geladen.'}</p> : null}
      {data ? <>
        <p className="text-sm text-gray-500 dark:text-gray-300">{data.total} {data.total === 1 ? 'persoon' : 'personen'} gevonden. Nieuwe records staan bovenaan; dit betekent niet dat zij nieuwe leden zijn.</p>
        {data.people.length === 0 ? <p>Geen personen gevonden.</p> : null}
        {data.people.map((person) => <article key={person.person_id} className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
          <Link to={`/people/${person.person_id}`} className="font-semibold text-electric-cyan hover:underline">{person.name}</Link>
          <p className="mt-1 text-sm">Mogelijke verzendtijd: {dateLabel(person.due_at)}</p>
          {person.blockers.length ? <ul className="mt-2 list-inside list-disc text-sm text-amber-800 dark:text-amber-300">{person.blockers.map((text) => <li key={text}>{text}</li>)}</ul> : <p className="mt-2 text-sm">De geregistreerde startvoorwaarden zijn voldaan. Automatische verzending blijft uit.</p>}
          <details className="mt-3">
            <summary className="cursor-pointer font-medium">Ontvangers en inhoud bekijken</summary>
            <div className="mt-3 space-y-3 text-sm">
              <div><h4 className="font-medium">Ontvangers volgens de huidige gegevens</h4>
                {person.recipients.length ? <ul className="mt-1 space-y-1">{person.recipients.map((recipient) => <li key={recipient.email}>
                  {recipient.email} — {[...new Set(recipient.sources.map((source) => source.label))].join(', ')}{recipient.blocked ? ' — geblokkeerd door maildienst' : ''}
                </li>)}</ul> : <p>Geen ontvangeradressen beschikbaar.</p>}
                {person.warnings.map((warning) => <p key={warning} className="mt-1 text-amber-800 dark:text-amber-300">{warning}</p>)}
              </div>
              <div><h4 className="font-medium">Mailblokken en nog uit te werken controles</h4><ul className="mt-1 list-inside list-disc">{person.blocks.map((block) => <li key={block}>{block}</li>)}</ul></div>
              <div><h4 className="font-medium">Bevestigde broncontrole</h4>
                <p>{dateLabel(person.observed_at)}</p>
                <ul className="mt-1 list-inside list-disc">{Object.entries(COVERAGE).map(([key, label]) => <li key={key}>{label}: {person.coverage[key] ? 'opgehaald en opgeslagen' : 'nog niet bevestigd'}</li>)}</ul>
              </div>
            </div>
          </details>
        </article>)}
        {data.total_pages > 1 ? <nav aria-label="Pagina's simulatie" className="flex items-center gap-3">
          <button type="button" className="btn-secondary" disabled={page <= 1 || isFetching} onClick={() => setPage((value) => value - 1)}>Vorige</button>
          <span>Pagina {page} van {data.total_pages}</span>
          <button type="button" className="btn-secondary" disabled={page >= data.total_pages || isFetching} onClick={() => setPage((value) => value + 1)}>Volgende</button>
        </nav> : null}
      </> : null}
    </section>
  );
}
