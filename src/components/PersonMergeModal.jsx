import { useDeferredValue, useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Check, GitMerge, Search, ShieldAlert, X } from 'lucide-react';
import { prmApi, wpApi } from '@/api/client';
import { peopleKeys } from '@/hooks/usePeople';
import { decodeHtml } from '@/utils/formatters';

const REFERENCE_LABELS = {
  relationships: 'relaties',
  comments: 'notities en activiteiten',
  attachments: 'bestanden en foto’s',
  accounts: 'accounts',
  shifts: 'inschrijftaken',
  todos: 'taken',
  invoices: 'facturen',
  cases: 'tuchtzaken',
};

function PersonSummary({ person, selected, onSelect, label }) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`w-full rounded-lg border p-4 text-left transition-colors ${
        selected
          ? 'border-electric-cyan bg-cyan-50 dark:bg-deep-midnight'
          : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
      }`}
      aria-pressed={selected}
    >
      <span className="flex items-start gap-3">
        {person.thumbnail ? (
          <img src={person.thumbnail} alt="" className="h-10 w-10 shrink-0 rounded-full object-cover" />
        ) : (
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-200 font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
            {person.name?.[0] || '?'}
          </span>
        )}
        <span className="min-w-0 flex-1">
          <span className="flex items-center justify-between gap-2">
            <span className="font-medium text-gray-900 dark:text-gray-100">{person.name}</span>
            {selected ? <Check className="h-5 w-5 shrink-0 text-electric-cyan" /> : null}
          </span>
          <span className="mt-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {label} · #{person.id}
          </span>
          <span className="mt-2 block text-sm text-gray-600 dark:text-gray-300">
            {person.knvb_id ? `KNVB ${person.knvb_id}` : 'Geen KNVB-ID'}
            {person.sponsit_id ? ` · Sponsit ${person.sponsit_id}` : ''}
          </span>
          {person.emails?.length ? (
            <span className="mt-1 block truncate text-sm text-gray-500 dark:text-gray-400">{person.emails.join(', ')}</span>
          ) : null}
        </span>
      </span>
    </button>
  );
}

function CandidateResult({ person, onSelect }) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className="flex w-full items-center gap-3 border-b border-gray-100 px-3 py-3 text-left last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/60"
    >
      {person.thumbnail ? (
        <img src={person.thumbnail} alt="" className="h-9 w-9 rounded-full object-cover" />
      ) : (
        <span className="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-sm font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
          {person.name?.[0] || '?'}
        </span>
      )}
      <span className="min-w-0">
        <span className="block truncate font-medium text-gray-900 dark:text-gray-100">{person.name}</span>
        <span className="block text-xs text-gray-500 dark:text-gray-400">
          #{person.id}{person.fields?.knvb_id ? ` · KNVB ${person.fields.knvb_id}` : ''}
        </span>
      </span>
    </button>
  );
}

function normalizeDirectPerson(person) {
  const thumbnail = person._embedded?.['wp:featuredmedia']?.[0]?.source_url
    || person._embedded?.['wp:featuredmedia']?.[0]?.media_details?.sizes?.thumbnail?.source_url
    || null;

  return {
    ...person,
    name: decodeHtml(person.title?.rendered || '') || `Persoon #${person.id}`,
    thumbnail,
  };
}

export default function PersonMergeModal({ currentPerson, onClose, onMerged }) {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState('');
  const [candidate, setCandidate] = useState(null);
  const [keepCurrent, setKeepCurrent] = useState(true);
  const [resolutions, setResolutions] = useState({});
  const [confirmed, setConfirmed] = useState(false);
  const [submissionError, setSubmissionError] = useState('');
  const deferredSearch = useDeferredValue(search.trim().toLowerCase());

  const primaryId = keepCurrent ? Number(currentPerson.id) : Number(candidate?.id || 0);
  const duplicateId = keepCurrent ? Number(candidate?.id || 0) : Number(currentPerson.id);

  const previewQuery = useQuery({
    queryKey: ['person-merge-preview', primaryId, duplicateId],
    queryFn: async () => (await prmApi.getPersonMergePreview(primaryId, duplicateId)).data,
    enabled: primaryId > 0 && duplicateId > 0,
    staleTime: 30 * 1000,
    retry: false,
  });

  const candidateQuery = useQuery({
    queryKey: ['person-merge-candidates', deferredSearch],
    queryFn: async () => {
      const directId = /^\d+$/.test(deferredSearch) ? Number(deferredSearch) : 0;
      const [searchResponse, directPerson] = await Promise.all([
        prmApi.search(deferredSearch),
        directId > 0
          ? wpApi.getPerson(directId, { _embed: true })
              .then((response) => normalizeDirectPerson(response.data))
              .catch((error) => {
                if (error.response?.status === 404) return null;
                throw error;
              })
          : Promise.resolve(null),
      ]);

      const candidatesById = new Map();
      if (directPerson) candidatesById.set(Number(directPerson.id), directPerson);
      for (const person of searchResponse.data?.people || []) {
        candidatesById.set(Number(person.id), person);
      }
      candidatesById.delete(Number(currentPerson.id));

      return Array.from(candidatesById.values()).slice(0, 8);
    },
    enabled: deferredSearch.length >= 2,
    staleTime: 30 * 1000,
    retry: false,
  });

  const mergeMutation = useMutation({
    mutationFn: async () => {
      const conflictChoices = Object.fromEntries(
        (previewQuery.data?.conflicts || []).map((conflict) => [
          conflict.field,
          resolutions[conflict.field] || 'primary',
        ])
      );
      return (
        await prmApi.mergePeople(primaryId, {
          duplicate_id: duplicateId,
          resolutions: conflictChoices,
          confirmed: true,
        })
      ).data;
    },
    onSuccess: async (result) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: peopleKeys.all }),
        queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
        queryClient.invalidateQueries({ queryKey: ['current-user'] }),
      ]);
      onMerged(result.person_id);
    },
  });

  useEffect(() => {
    const handleKeyDown = (event) => {
      if (event.key === 'Escape' && !mergeMutation.isPending) onClose();
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [mergeMutation.isPending, onClose]);

  const selectCandidate = (person) => {
    setCandidate(person);
    setKeepCurrent(true);
    setResolutions({});
    setConfirmed(false);
    setSubmissionError('');
  };

  const changePrimary = (value) => {
    setKeepCurrent(value);
    setResolutions({});
    setConfirmed(false);
    setSubmissionError('');
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSubmissionError('');
    try {
      await mergeMutation.mutateAsync();
    } catch (error) {
      setSubmissionError(error.response?.data?.message || error.message || 'Samenvoegen is niet gelukt.');
    }
  };

  const preview = previewQuery.data;
  const hasBlockers = (preview?.blocking_conflicts?.length || 0) > 0;
  const referenceEntries = Object.entries(preview?.references || {}).filter(([, count]) => count > 0);

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="person-merge-title">
      <div className="fixed inset-0 bg-black/50" onClick={mergeMutation.isPending ? undefined : onClose} />
      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800">
          <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <div className="flex items-center gap-3">
              <span className="rounded-full bg-cyan-50 p-2 text-electric-cyan dark:bg-deep-midnight">
                <GitMerge className="h-5 w-5" />
              </span>
              <div>
                <h2 id="person-merge-title" className="text-lg font-semibold text-gray-900 dark:text-gray-100">Personen samenvoegen</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400">Het dubbele profiel gaat na controle naar de prullenbak.</p>
              </div>
            </div>
            <button type="button" onClick={onClose} disabled={mergeMutation.isPending} className="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50 dark:hover:bg-gray-700 dark:hover:text-gray-300" aria-label="Sluiten">
              <X className="h-5 w-5" />
            </button>
          </div>

          {!candidate ? (
            <div className="space-y-4 p-5">
              <div>
                <label htmlFor="person-merge-search" className="label">Zoek het dubbele profiel</label>
                <div className="relative">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                  <input
                    id="person-merge-search"
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    className="input"
                    style={{ paddingLeft: '2.5rem' }}
                    placeholder="Naam, e-mailadres, KNVB-ID of profielnummer"
                    autoFocus
                  />
                </div>
              </div>
              {candidateQuery.isFetching ? (
                <div className="space-y-2" aria-label="Personen laden">
                  {[1, 2].map((item) => <div key={item} className="h-14 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700" />)}
                </div>
              ) : deferredSearch.length < 2 ? (
                <p className="text-sm text-gray-500 dark:text-gray-400">Typ minimaal twee tekens.</p>
              ) : candidateQuery.isError ? (
                <p className="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                  Zoeken is niet gelukt. Probeer het opnieuw.
                </p>
              ) : candidateQuery.data?.length ? (
                <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                  {candidateQuery.data.map((person) => <CandidateResult key={person.id} person={person} onSelect={() => selectCandidate(person)} />)}
                </div>
              ) : (
                <p className="rounded-lg bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">Geen andere persoon gevonden.</p>
              )}
            </div>
          ) : (
            <form onSubmit={handleSubmit}>
              <div className="max-h-[70vh] space-y-6 overflow-y-auto p-5">
                <div>
                  <div className="mb-3 flex items-center justify-between gap-3">
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Welk profiel blijft bestaan?</h3>
                    <button type="button" onClick={() => setCandidate(null)} className="text-sm text-electric-cyan hover:underline">Andere persoon kiezen</button>
                  </div>
                  {preview ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                      <PersonSummary person={keepCurrent ? preview.primary : preview.duplicate} selected={keepCurrent} onSelect={() => changePrimary(true)} label="Dit profiel behouden" />
                      <PersonSummary person={keepCurrent ? preview.duplicate : preview.primary} selected={!keepCurrent} onSelect={() => changePrimary(false)} label="Dit profiel behouden" />
                    </div>
                  ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                      {[1, 2].map((item) => <div key={item} className="h-36 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-700" />)}
                    </div>
                  )}
                </div>

                {previewQuery.isError ? (
                  <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                    {previewQuery.error.response?.data?.message || 'De samenvoeging kon niet worden voorbereid.'}
                  </div>
                ) : null}

                {preview?.automatic_change_count ? (
                  <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Wordt automatisch gecombineerd</h3>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                      Lege velden worden aangevuld en dubbele lijstitems worden verwijderd.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {preview.automatic_changes.map((label) => (
                        <span key={label} className="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200">{label}</span>
                      ))}
                    </div>
                  </section>
                ) : null}

                {referenceEntries.length ? (
                  <section className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Koppelingen die meeverhuizen</h3>
                    <ul className="mt-2 grid gap-2 text-sm text-gray-600 dark:text-gray-300 sm:grid-cols-2">
                      {referenceEntries.map(([key, count]) => <li key={key}>{count} {REFERENCE_LABELS[key] || key}</li>)}
                    </ul>
                  </section>
                ) : null}

                {preview?.conflicts?.length ? (
                  <section>
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">Kies bij verschillen</h3>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Standaard blijft de waarde van het gekozen hoofdprofiel staan.</p>
                    <div className="mt-3 space-y-3">
                      {preview.conflicts.map((conflict) => {
                        const choice = resolutions[conflict.field] || 'primary';
                        return (
                          <fieldset key={conflict.field} className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <legend className="px-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{conflict.label}</legend>
                            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                              {[
                                ['primary', 'Hoofdprofiel', conflict.primary_value],
                                ['duplicate', 'Dubbel profiel', conflict.duplicate_value],
                              ].map(([value, label, text]) => (
                                <label key={value} className={`cursor-pointer rounded-lg border p-3 ${choice === value ? 'border-electric-cyan bg-cyan-50 dark:bg-deep-midnight' : 'border-gray-200 dark:border-gray-700'}`}>
                                  <span className="flex items-start gap-2">
                                    <input type="radio" name={`merge-${conflict.field}`} value={value} checked={choice === value} onChange={() => setResolutions((current) => ({ ...current, [conflict.field]: value }))} className="mt-1" />
                                    <span>
                                      <span className="block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</span>
                                      <span className="mt-1 block break-words text-sm text-gray-900 dark:text-gray-100">{text || 'Leeg'}</span>
                                    </span>
                                  </span>
                                </label>
                              ))}
                            </div>
                          </fieldset>
                        );
                      })}
                    </div>
                  </section>
                ) : null}

                {hasBlockers ? (
                  <section className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20">
                    <div className="flex items-start gap-3">
                      <ShieldAlert className="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" />
                      <div>
                        <h3 className="font-semibold text-red-800 dark:text-red-200">Samenvoegen is nog niet veilig</h3>
                        <ul className="mt-2 space-y-2 text-sm text-red-700 dark:text-red-300">
                          {preview.blocking_conflicts.map((conflict) => <li key={conflict.field}>{conflict.message}</li>)}
                        </ul>
                      </div>
                    </div>
                  </section>
                ) : null}

                {preview && !hasBlockers ? (
                  <label className="flex items-start gap-3 rounded-lg bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-900/20 dark:text-amber-200">
                    <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                    <span>
                      <input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} className="mr-2" />
                      Ik heb gecontroleerd welk profiel blijft bestaan en welke waarden worden bewaard.
                    </span>
                  </label>
                ) : null}

                {submissionError ? <p className="text-sm text-red-600 dark:text-red-400">{submissionError}</p> : null}
              </div>

              <div className="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900">
                <button type="button" onClick={onClose} className="btn-secondary" disabled={mergeMutation.isPending}>Annuleren</button>
                <button type="submit" className="btn-danger gap-2" disabled={!preview || previewQuery.isFetching || hasBlockers || !confirmed || mergeMutation.isPending}>
                  <GitMerge className="h-4 w-4" />
                  {mergeMutation.isPending ? 'Samenvoegen…' : 'Profielen samenvoegen'}
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
