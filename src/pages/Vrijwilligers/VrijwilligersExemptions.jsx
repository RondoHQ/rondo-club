import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ExternalLink, Pencil, Plus, Search, UsersRound, X } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import SortableHeader from '@/components/SortableHeader';
import { comparePersonNames, formatPersonSurname } from '@/utils/formatters';

const REASON_LABELS = {
  commissie: 'Commissielid',
  staff: 'Trainer/Leider',
  betaald: 'Betaalde vrijwilliger',
  handmatig: 'Handmatige vrijstelling',
};

const FILTER_REASONS = ['commissie', 'staff', 'handmatig'];

const REASON_BADGE_COLORS = {
  commissie: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
  staff: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  betaald: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  handmatig: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
};

function ManualExemptionModal({ initialPerson, onClose, onSaved }) {
  const [selectedPerson, setSelectedPerson] = useState(initialPerson);
  const [searchQuery, setSearchQuery] = useState('');
  const [enabled, setEnabled] = useState(true);
  const [reason, setReason] = useState('');
  const [season, setSeason] = useState('');
  const [error, setError] = useState('');
  const deferredSearch = useDeferredValue(searchQuery.trim());

  const { data: searchResults = [], isFetching: isSearching } = useQuery({
    queryKey: ['global-search', 'manual-exemption', deferredSearch],
    queryFn: async () => {
      const response = await prmApi.search(deferredSearch);
      return response.data?.people || [];
    },
    enabled: !selectedPerson && deferredSearch.length >= 2,
    staleTime: 30 * 1000,
  });

  const { data: exemption, isLoading: isLoadingExemption } = useQuery({
    queryKey: ['volunteer', 'exemption', selectedPerson?.id],
    queryFn: async () => (await prmApi.getVolunteerExemption(selectedPerson.id)).data,
    enabled: Boolean(selectedPerson?.id),
  });

  useEffect(() => {
    if (!exemption) return;

    const addingNewExemption = !initialPerson;
    const hasManualExemption = Boolean(exemption.manual?.enabled);
    setEnabled(hasManualExemption || addingNewExemption);
    setReason(exemption.manual?.reason || '');
    setSeason(hasManualExemption ? (exemption.manual?.season || '') : (addingNewExemption ? exemption.season : ''));
  }, [exemption, initialPerson]);

  const saveMutation = useMutation({
    mutationFn: () => prmApi.updateVolunteerExemption(selectedPerson.id, {
      enabled,
      reason: enabled ? reason.trim() : '',
      season: enabled ? season.trim() : '',
    }),
    onSuccess: (response) => onSaved(response.data),
    onError: (requestError) => {
      setError(requestError?.response?.data?.message || 'De vrijstelling kon niet worden opgeslagen.');
    },
  });

  const handleSubmit = (event) => {
    event.preventDefault();
    setError('');
    saveMutation.mutate();
  };

  const automaticReason = exemption?.is_exempt && exemption.reason !== 'handmatig'
    ? exemption.reason_label
    : null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation">
      <div
        className="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800"
        role="dialog"
        aria-modal="true"
        aria-labelledby="manual-exemption-title"
      >
        <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
          <h2 id="manual-exemption-title" className="text-lg font-semibold text-gray-900 dark:text-gray-50">
            {initialPerson ? 'Handmatige vrijstelling bewerken' : 'Handmatige vrijstelling toevoegen'}
          </h2>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            aria-label="Sluiten"
            disabled={saveMutation.isPending}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {!selectedPerson ? (
          <div className="space-y-4 overflow-y-auto p-4">
            <div>
              <label htmlFor="manual-exemption-person-search" className="label">Persoon</label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                <input
                  id="manual-exemption-person-search"
                  type="search"
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                  className="input pl-9"
                  placeholder="Zoek op naam…"
                  autoFocus
                />
              </div>
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Kies bij een gezinsplicht de verantwoordelijke ouder of verzorger.
              </p>
            </div>

            {deferredSearch.length < 2 ? (
              <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                Vul minimaal twee tekens in.
              </p>
            ) : isSearching ? (
              <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Zoeken…</p>
            ) : searchResults.length === 0 ? (
              <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Geen personen gevonden.</p>
            ) : (
              <div className="divide-y divide-gray-100 overflow-hidden rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                {searchResults.map((person) => (
                  <button
                    key={person.id}
                    type="button"
                    onClick={() => setSelectedPerson(person)}
                    className="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700"
                  >
                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">{person.name}</span>
                    {person.former_member ? (
                      <span className="text-xs text-gray-500 dark:text-gray-400">Oud-lid</span>
                    ) : null}
                  </button>
                ))}
              </div>
            )}
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="flex min-h-0 flex-1 flex-col">
            <div className="space-y-4 overflow-y-auto p-4">
              <div className="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-700">
                <div>
                  <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{selectedPerson.name}</p>
                  <Link
                    to={`/people/${selectedPerson.id}`}
                    className="text-xs text-bright-cobalt hover:underline dark:text-electric-cyan"
                    target="_blank"
                  >
                    Persoonsprofiel openen
                  </Link>
                </div>
                {!initialPerson ? (
                  <button
                    type="button"
                    onClick={() => {
                      setSelectedPerson(null);
                      setError('');
                    }}
                    className="text-xs text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                  >
                    Andere kiezen
                  </button>
                ) : null}
              </div>

              {isLoadingExemption ? (
                <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Gegevens laden…</p>
              ) : (
                <>
                  {automaticReason ? (
                    <div className="rounded-md border border-cyan-200 bg-cyan-50 p-3 text-sm text-cyan-900 dark:border-cyan-800 dark:bg-cyan-900/20 dark:text-cyan-200">
                      Deze persoon is al automatisch vrijgesteld: {automaticReason}. Een handmatige vrijstelling kan daarnaast als vangnet worden opgeslagen.
                    </div>
                  ) : null}

                  <label className="flex items-start gap-3 rounded-md border border-gray-200 p-3 dark:border-gray-700">
                    <input
                      type="checkbox"
                      checked={enabled}
                      onChange={(event) => setEnabled(event.target.checked)}
                      className="mt-0.5 h-4 w-4 rounded border-gray-300 text-bright-cobalt focus:ring-bright-cobalt"
                    />
                    <span>
                      <span className="block text-sm font-medium text-gray-900 dark:text-gray-100">Handmatig vrijgesteld</span>
                      <span className="block text-xs text-gray-500 dark:text-gray-400">
                        Zet dit uit om een bestaande handmatige vrijstelling in te trekken.
                      </span>
                    </span>
                  </label>

                  <div>
                    <label htmlFor="manual-exemption-reason" className="label">Reden</label>
                    <textarea
                      id="manual-exemption-reason"
                      value={reason}
                      onChange={(event) => setReason(event.target.value)}
                      className="input min-h-20"
                      placeholder="Bijvoorbeeld langdurige ziekte of erelid"
                      disabled={!enabled}
                      rows={3}
                    />
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Alleen zichtbaar voor beheerders.</p>
                  </div>

                  <div>
                    <label htmlFor="manual-exemption-season" className="label">Geldig voor seizoen</label>
                    <input
                      id="manual-exemption-season"
                      type="text"
                      value={season}
                      onChange={(event) => setSeason(event.target.value)}
                      className="input"
                      placeholder={exemption?.season || '2026-2027'}
                      pattern="[0-9]{4}-[0-9]{4}"
                      maxLength={9}
                      disabled={!enabled}
                    />
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                      Gebruik bijvoorbeeld {exemption?.season || '2026-2027'}, of laat leeg voor een doorlopende vrijstelling.
                    </p>
                  </div>
                </>
              )}

              {error ? (
                <p className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300" role="alert">
                  {error}
                </p>
              ) : null}
            </div>

            <div className="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
              <button type="button" onClick={onClose} className="btn-secondary" disabled={saveMutation.isPending}>
                Annuleren
              </button>
              <button type="submit" className="btn-primary" disabled={isLoadingExemption || saveMutation.isPending}>
                {saveMutation.isPending ? 'Opslaan…' : 'Vrijstelling opslaan'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

export default function VrijwilligersExemptions() {
  useDocumentTitle('Vrijstellingen — Vrijwilligers');
  const queryClient = useQueryClient();
  const [filterReason, setFilterReason] = useState('all');
  const [editorPerson, setEditorPerson] = useState(undefined);
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');

  const { data: eligibility, isLoading } = useQuery({
    queryKey: ['volunteer', 'eligibility', 'with-persons'],
    queryFn: async () => {
      const response = await prmApi.getVolunteerEligibility({ with_persons: 1 });
      return response.data;
    },
    staleTime: 5 * 60 * 1000,
  });

  const exemptPersons = useMemo(() => {
    if (!eligibility?.units) return [];
    const seen = new Set();
    const rows = [];
    for (const unit of eligibility.units) {
      for (const person of unit.persons || []) {
        if (!person.is_exempt || seen.has(person.id)) continue;
        seen.add(person.id);
        rows.push({
          ...person,
          unit_id: unit.unit_id,
          unit_kind: unit.kind,
        });
      }
    }
    return rows;
  }, [eligibility]);

  const filtered = useMemo(() => {
    if (filterReason === 'all') return exemptPersons;
    return exemptPersons.filter((person) => person.reason === filterReason);
  }, [exemptPersons, filterReason]);

  const sortedFiltered = useMemo(() => [...filtered].sort((a, b) => {
    const comparison = comparePersonNames(a, b, sortField);
    return sortOrder === 'asc' ? comparison : -comparison;
  }), [filtered, sortField, sortOrder]);

  const handleSort = (field, order) => {
    setSortField(field);
    setSortOrder(order);
  };

  const counts = useMemo(() => {
    const nextCounts = { all: exemptPersons.length };
    for (const reason of FILTER_REASONS) {
      nextCounts[reason] = exemptPersons.filter((person) => person.reason === reason).length;
    }
    return nextCounts;
  }, [exemptPersons]);

  const handleSaved = (updatedExemption) => {
    queryClient.invalidateQueries({ queryKey: ['volunteer', 'eligibility'] });
    queryClient.invalidateQueries({ queryKey: ['volunteer', 'exemption', updatedExemption.person_id] });
    setEditorPerson(undefined);
    setFilterReason(updatedExemption.reason === 'handmatig' ? 'handmatig' : 'all');
  };

  return (
    <div className="space-y-6">
      <header className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div className="flex items-center gap-3">
          <div className="rounded-lg bg-cyan-50 p-2 dark:bg-gray-700">
            <UsersRound className="h-6 w-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijstellingen</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Personen die automatisch of handmatig zijn vrijgesteld van de vrijwilligersplicht van twee inschrijftaken.
            </p>
          </div>
        </div>
        <button type="button" onClick={() => setEditorPerson(null)} className="btn-primary inline-flex items-center justify-center gap-2">
          <Plus className="h-4 w-4" />
          Vrijstelling toevoegen
        </button>
      </header>

      <div className="card p-4">
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setFilterReason('all')}
            className={`rounded-md px-3 py-1.5 text-sm transition-colors ${
              filterReason === 'all'
                ? 'bg-bright-cobalt text-white dark:bg-electric-cyan dark:text-gray-900'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
            }`}
          >
            Alle ({counts.all})
          </button>
          {FILTER_REASONS.map((reason) => (
            <button
              key={reason}
              type="button"
              onClick={() => setFilterReason(reason)}
              className={`rounded-md px-3 py-1.5 text-sm transition-colors ${
                filterReason === reason
                  ? 'bg-bright-cobalt text-white dark:bg-electric-cyan dark:text-gray-900'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              {REASON_LABELS[reason]} ({counts[reason] || 0})
            </button>
          ))}
        </div>
      </div>

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-300">
            <tr>
              <SortableHeader label="Voornaam" columnId="first_name" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} className="!px-4 !py-2" />
              <SortableHeader label="Achternaam" columnId="last_name" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} className="!px-4 !py-2" />
              <th className="px-4 py-2">Reden vrijstelling</th>
              <th className="px-4 py-2">Eenheid</th>
              <th className="w-20 px-4 py-2"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {isLoading ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Laden…</td>
              </tr>
            ) : filtered.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                  Geen vrijgestelde personen in deze categorie.
                </td>
              </tr>
            ) : (
              sortedFiltered.map((person) => (
                <tr key={person.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-2">
                    <Link to={`/people/${person.id}`} className="text-bright-cobalt hover:underline dark:text-electric-cyan">
                      {person.first_name || '—'}
                    </Link>
                  </td>
                  <td className="px-4 py-2">
                    <Link to={`/people/${person.id}`} className="text-bright-cobalt hover:underline dark:text-electric-cyan">
                      {formatPersonSurname(person.infix, person.last_name) || '—'}
                    </Link>
                  </td>
                  <td className="px-4 py-2">
                    <span className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${REASON_BADGE_COLORS[person.reason] || REASON_BADGE_COLORS.handmatig}`}>
                      {person.reason_label || REASON_LABELS[person.reason] || person.reason}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                    {person.unit_kind === 'gezin' ? 'Gezin' : 'Speler'} · <code className="text-xs">{person.unit_id}</code>
                  </td>
                  <td className="px-4 py-2">
                    <div className="flex items-center justify-end gap-2">
                      {person.reason === 'handmatig' ? (
                        <button
                          type="button"
                          onClick={() => setEditorPerson({ id: person.id, name: person.name })}
                          className="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200"
                          aria-label={`Vrijstelling van ${person.name} bewerken`}
                        >
                          <Pencil className="h-4 w-4" />
                        </button>
                      ) : null}
                      <Link
                        to={`/people/${person.id}`}
                        className="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200"
                        aria-label={`Profiel van ${person.name} openen`}
                      >
                        <ExternalLink className="h-4 w-4" />
                      </Link>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-gray-500 dark:text-gray-400">
        Vrijstellingen komen uit actieve commissierollen, trainer-/leiderrollen en handmatige vrijstellingen.
        Pas staf-rollen aan via{' '}
        <Link to="/settings/admin/capabilities" className="underline">Instellingen → Capabilities</Link>.
      </p>

      {editorPerson !== undefined ? (
        <ManualExemptionModal
          initialPerson={editorPerson}
          onClose={() => setEditorPerson(undefined)}
          onSaved={handleSaved}
        />
      ) : null}
    </div>
  );
}
