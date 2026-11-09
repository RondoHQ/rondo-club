import { useDeferredValue, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const addedDate = new Intl.DateTimeFormat('nl-NL', { dateStyle: 'long', timeZone: 'Europe/Amsterdam' });

function AddExclusion({ excludedIds, busy, error, onSave, onClose }) {
  const [search, setSearch] = useState('');
  const [person, setPerson] = useState(null);
  const [reason, setReason] = useState('');
  const deferredSearch = useDeferredValue(search.trim());
  const query = useQuery({
    queryKey: ['global-search', 'planner-exclusion', deferredSearch],
    queryFn: async () => (await prmApi.search(deferredSearch)).data?.people || [],
    enabled: !person && deferredSearch.length >= 2,
  });

  return (
    <form className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" onSubmit={(event) => { event.preventDefault(); if (person && reason.trim() && !busy) onSave(person, reason.trim()); }}>
      <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Persoon uitsluiten</h2>
      {person ? (
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <span className="font-medium text-gray-900 dark:text-gray-100">{person.name}</span>
          <button type="button" className="text-sm text-cyan-800 underline dark:text-cyan-200" disabled={busy} onClick={() => setPerson(null)}>Andere persoon kiezen</button>
        </div>
      ) : (
        <div className="mt-3 max-w-lg">
          <label className="label" htmlFor="exclusion-person-search">Zoek een persoon</label>
          <input id="exclusion-person-search" className="input" type="search" autoFocus value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Vul minimaal twee tekens in" />
          {deferredSearch.length >= 2 && <div className="mt-2" aria-live="polite">
            {query.isFetching || deferredSearch !== search.trim() ? <p className="text-sm text-gray-600 dark:text-gray-300">Personen zoeken…</p> : query.isError ? (
              <p role="alert" className="text-sm text-red-700 dark:text-red-300">Zoeken mislukt. <button type="button" className="underline" onClick={() => query.refetch()}>Opnieuw proberen</button></p>
            ) : query.data?.length ? (
              <ul className="max-h-60 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                {query.data.map((candidate) => <li key={candidate.id}>
                  <button type="button" disabled={excludedIds.has(candidate.id)} className="flex min-h-11 w-full items-center justify-between gap-2 rounded px-2 py-2 text-left text-sm hover:bg-gray-50 disabled:cursor-default disabled:text-gray-500 dark:text-gray-100 dark:hover:bg-gray-700 dark:disabled:text-gray-400" onClick={() => setPerson(candidate)}>
                    <span>{candidate.name}</span>
                    {excludedIds.has(candidate.id) && <span className="text-xs">Al uitgesloten</span>}
                  </button>
                </li>)}
              </ul>
            ) : <p className="text-sm text-gray-600 dark:text-gray-300">Geen personen gevonden.</p>}
          </div>}
        </div>
      )}
      {person && <div className="mt-4 max-w-lg">
        <label className="label" htmlFor="exclusion-reason">Reden</label>
        <textarea id="exclusion-reason" className="input" required maxLength={1000} rows={3} value={reason} disabled={busy} onChange={(event) => setReason(event.target.value)} aria-describedby="exclusion-reason-help" />
        <p id="exclusion-reason-help" className="mt-1 text-sm text-gray-600 dark:text-gray-300">Zichtbaar voor vrijwilligersbeheerders en beheerders.</p>
      </div>}
      {error && <p role="alert" className="mt-3 text-sm text-red-700 dark:text-red-300">{error}</p>}
      <div className="mt-4 flex flex-wrap gap-2">
        <button type="submit" className="btn-primary" disabled={busy || !person || !reason.trim()}>{busy ? 'Opslaan…' : 'Toevoegen aan uitsluitlijst'}</button>
        <button type="button" className="btn-secondary text-cyan-800! dark:text-cyan-200!" disabled={busy} onClick={onClose}>Annuleren</button>
      </div>
    </form>
  );
}

export default function VrijwilligersExclusions() {
  useDocumentTitle('Uitsluitlijst');
  const client = useQueryClient();
  const [adding, setAdding] = useState(false);
  const [filter, setFilter] = useState('');
  const [removing, setRemoving] = useState(null);
  const [notice, setNotice] = useState('');
  const query = useQuery({
    queryKey: ['volunteer', 'exclusions'],
    queryFn: async () => (await prmApi.getVolunteerExclusions()).data,
  });
  const mutation = useMutation({
    mutationFn: ({ person, enabled, reason = '' }) => prmApi.updateVolunteerExclusion(person.id, { enabled, reason }),
    onSuccess: async (_response, { person, enabled }) => {
      setNotice(enabled ? `${person.name} is toegevoegd aan de uitsluitlijst.` : `De uitsluiting van ${person.name} is opgeheven.`);
      setAdding(false);
      setRemoving(null);
      await Promise.all([
        client.invalidateQueries({ queryKey: ['volunteer'] }),
        client.invalidateQueries({ queryKey: ['people'] }),
      ]);
    },
  });
  const rows = query.data || [];
  const excludedIds = new Set(rows.map((row) => row.id));
  const visibleRows = rows.filter((row) => `${row.name} ${row.fields.planner_exclusion_reason}`.toLocaleLowerCase('nl').includes(filter.trim().toLocaleLowerCase('nl')));
  const error = mutation.error?.response?.data?.message || (mutation.isError ? 'Opslaan mislukt. Probeer het opnieuw.' : '');

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Uitsluitlijst</h1>
          <p className="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-300">Personen op deze lijst worden niet automatisch ingedeeld, totdat je hun uitsluiting opheft. Andere gezinsleden blijven beschikbaar en de vrijwilligersplicht blijft gelden.</p>
        </div>
        {!adding && <button type="button" className="btn-primary" disabled={query.isPending || query.isError || mutation.isPending} onClick={() => { mutation.reset(); setRemoving(null); setNotice(''); setAdding(true); }}><Plus className="mr-2 h-4 w-4" aria-hidden="true" />Persoon toevoegen</button>}
      </div>
      {notice && <p role="status" className="text-sm text-cyan-800 dark:text-cyan-200">{notice}</p>}
      {adding && <AddExclusion excludedIds={excludedIds} busy={mutation.isPending} error={error} onClose={() => { setAdding(false); mutation.reset(); }} onSave={(person, reason) => mutation.mutate({ person, reason, enabled: true })} />}
      {query.isPending ? <p role="status" className="text-gray-600 dark:text-gray-300">Uitsluitlijst laden…</p> : query.isError ? (
        <p role="alert" className="text-red-700 dark:text-red-300">De uitsluitlijst kon niet worden geladen. <button type="button" className="underline" onClick={() => query.refetch()}>Opnieuw proberen</button></p>
      ) : rows.length === 0 ? (
        <p className="rounded-lg border border-gray-200 p-6 text-gray-600 dark:border-gray-700 dark:text-gray-300">Er staan geen personen op de uitsluitlijst.</p>
      ) : <section aria-label="Uitgesloten personen">
        <label className="label" htmlFor="exclusion-filter">Zoek in de uitsluitlijst</label>
        <div className="relative mb-4 max-w-md">
          <input id="exclusion-filter" type="search" className="input" value={filter} onChange={(event) => setFilter(event.target.value)} placeholder="Naam of reden" />
        </div>
        <p className="mb-2 text-sm text-gray-600 dark:text-gray-300">{visibleRows.length} van {rows.length} personen</p>
        {visibleRows.length === 0 ? <p className="py-4 text-gray-600 dark:text-gray-300">Geen personen gevonden voor deze zoekopdracht.</p> : <ul className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
          {visibleRows.map((row) => <li key={row.id} className="p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
              <div className="min-w-0">
                <Link className="font-medium text-cyan-800 underline-offset-2 hover:underline dark:text-cyan-200" to={`/people/${row.id}`}>{row.name}</Link>
                <p className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-800 dark:text-gray-200">{row.fields.planner_exclusion_reason}</p>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">Toegevoegd op {row.fields.planner_excluded_since ? addedDate.format(new Date(row.fields.planner_excluded_since)) : 'onbekende datum'}</p>
              </div>
              <button type="button" className="btn-secondary text-cyan-800! dark:text-cyan-200! shrink-0 self-start" disabled={mutation.isPending} onClick={() => { setAdding(false); mutation.reset(); setNotice(''); setRemoving(row.id); }}>Uitsluiting opheffen<span className="sr-only"> voor {row.name}</span></button>
            </div>
            {removing === row.id && <div className="mt-4" role="group" aria-label={`Uitsluiting van ${row.name} opheffen`}>
              <p className="text-sm text-gray-800 dark:text-gray-200">{row.name} kan hierna weer automatisch worden ingedeeld.</p>
              {error && <p role="alert" className="mt-2 text-sm text-red-700 dark:text-red-300">{error}</p>}
              <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" className="btn-primary" disabled={mutation.isPending} onClick={() => mutation.mutate({ person: row, enabled: false })}>{mutation.isPending ? 'Opslaan…' : 'Ja, uitsluiting opheffen'}</button>
                <button type="button" className="btn-secondary text-cyan-800! dark:text-cyan-200!" disabled={mutation.isPending} onClick={() => { setRemoving(null); mutation.reset(); }}>Annuleren</button>
              </div>
            </div>}
          </li>)}
        </ul>}
      </section>}
    </div>
  );
}
