import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Wine, Check, X, Upload } from 'lucide-react';
import { prmApi } from '@/api/client';
import IvaCertificateLink from '@/components/IvaCertificateLink';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';
import SortableHeader from '@/components/SortableHeader';
import { comparePersonNames, formatPersonSurname } from '@/utils/formatters';

const TABS = [
  { id: 'pending', label: 'Wacht op goedkeuring' },
  { id: 'valid',   label: 'Geldig' },
];

function loadIvaPeople() {
  // Dedicated server endpoint — returns only people with IVA-relevant meta set,
  // so the result is small (typically tens of records, not the full member roster).
  return prmApi.getIvaPeople().then((r) => r.data?.people || []);
}

function approveIva(personId, approve) {
  // Dedicated endpoint — gated on the rondo_iva_approve capability per the
  // 2026-05-26 board decision (only bestuurslid kantine can approve).
  return prmApi.approveIva(personId, approve);
}

export default function VrijwilligersIva() {
  useDocumentTitle('IVA en Sociale Hygiëne — Vrijwilligers');
  const queryClient = useQueryClient();
  const [tab, setTab] = useState('pending');
  const [sortField, setSortField] = useState('last_name');
  const [sortOrder, setSortOrder] = useState('asc');
  const [searchParams, setSearchParams] = useSearchParams();

  const { data: people = [], isLoading, error } = useQuery({
    queryKey: ['volunteer', 'iva', 'people'],
    queryFn: loadIvaPeople,
    staleTime: 60 * 1000,
  });

  const approveMutation = useMutation({
    mutationFn: ({ personId, approve }) => approveIva(personId, approve),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'iva', 'people'] });
    },
  });

  // The server normalizes each person to a single `status` enum (missing /
  // pending / valid) using the same IvaStatus rules everywhere else.
  // We just bucket by status — no client-side date math required.
  const buckets = useMemo(() => {
    const out = { pending: [], valid: [] };
    for (const person of people) {
      if (person.status === 'valid') out.valid.push(person);
      else out.pending.push(person);
    }
    return out;
  }, [people]);

  const reviewPersonId = Number.parseInt(searchParams.get('review') || '', 10);
  const reviewPerson = Number.isInteger(reviewPersonId)
    ? people.find((person) => Number(person.id) === reviewPersonId)
    : null;
  const reviewTab = reviewPerson?.status === 'valid' ? 'valid' : 'pending';
  const activeTab = reviewPerson ? reviewTab : tab;
  const active = useMemo(() => {
    const selected = reviewPerson ? [reviewPerson] : (buckets[activeTab] || []);
    return [...selected].sort((a, b) => {
      const comparison = comparePersonNames(a, b, sortField);
      return sortOrder === 'asc' ? comparison : -comparison;
    });
  }, [reviewPerson, buckets, activeTab, sortField, sortOrder]);

  const handleSort = (field, order) => {
    setSortField(field);
    setSortOrder(order);
  };

  const showFullList = (nextTab = activeTab) => {
    setSearchParams({});
    setTab(nextTab);
  };

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-3">
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <Wine className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">IVA en Sociale Hygiëne</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Bewijs voor verantwoord alcohol schenken — geldig voor onbepaalde tijd na goedkeuring.
          </p>
        </div>
      </header>

      <nav className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => showFullList(t.id)}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === t.id
                ? 'border-bright-cobalt text-bright-cobalt dark:border-electric-cyan dark:text-electric-cyan'
                : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
            }`}
          >
            {t.label} ({(buckets[t.id] || []).length})
          </button>
        ))}
      </nav>

      {reviewPerson && (
        <div className="card flex flex-wrap items-center justify-between gap-3 border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-900 dark:bg-cyan-950/30">
          <p className="text-sm text-cyan-900 dark:text-cyan-100">
            Directe review voor <strong>{reviewPerson.name || `Persoon ${reviewPerson.id}`}</strong>.
            {' '}Bekijk hieronder het bewijsstuk en keur het daarna goed.
          </p>
          <button
            type="button"
            onClick={() => showFullList(reviewTab)}
            className="text-sm font-medium text-bright-cobalt hover:underline dark:text-electric-cyan"
          >
            Toon volledige lijst
          </button>
        </div>
      )}

      {error && (
        <div className="card p-4 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
          Kon mensen niet ophalen: {error?.message || 'onbekende fout'}
        </div>
      )}

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
            <tr>
              <SortableHeader label="Voornaam" columnId="first_name" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} className="!px-4 !py-2" />
              <SortableHeader label="Achternaam" columnId="last_name" sortField={sortField} sortOrder={sortOrder} onSort={handleSort} className="!px-4 !py-2" />
              <th className="px-4 py-2">Behaaldatum</th>
              <th className="px-4 py-2">Bewijsstuk</th>
              <th className="px-4 py-2">Status</th>
              <th className="px-4 py-2 text-right">Acties</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {isLoading ? (
              <tr><td colSpan={6} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Laden…</td></tr>
            ) : active.length === 0 ? (
              <tr><td colSpan={6} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                {tab === 'pending' && 'Geen bewijsstukken wachten op goedkeuring.'}
                {tab === 'valid' && 'Nog niemand heeft een geldig IVA-certificaat of diploma Sociale Hygiëne.'}
              </td></tr>
            ) : (
              active.map((person) => {
                const datum    = person.datum_iva || '';
                const certUrl  = person.iva_certificaat || '';
                const approved = !!person.iva_approved;

                return (
                  <tr key={person.id} className={reviewPerson ? 'bg-cyan-50/60 dark:bg-cyan-950/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'}>
                    <td className="px-4 py-2">
                      <Link to={`/people/${person.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                        {person.first_name || '—'}
                      </Link>
                    </td>
                    <td className="px-4 py-2">
                      <Link to={`/people/${person.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                        {formatPersonSurname(person.infix, person.last_name) || '—'}
                      </Link>
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                      {datum ? format(datum, 'dd-MM-yyyy') : <span className="text-gray-400">—</span>}
                    </td>
                    <td className="px-4 py-2">
                      {certUrl ? (
                        <IvaCertificateLink personId={person.id}>Bekijk</IvaCertificateLink>
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      {person.status === 'valid'
                        ? <span className="text-emerald-700 dark:text-emerald-400 text-xs font-medium">Geldig</span>
                        : <span className="text-amber-700 dark:text-amber-400 text-xs font-medium">Wacht op review</span>}
                    </td>
                    <td className="px-4 py-2 text-right">
                      <div className="inline-flex gap-1">
                        {!approved && (datum || certUrl) && (
                          <button
                            onClick={() => approveMutation.mutate({ personId: person.id, approve: true })}
                            disabled={approveMutation.isLoading}
                            className="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300"
                            title="Goedkeuren"
                          >
                            <Check className="w-3 h-3" /> Keur goed
                          </button>
                        )}
                        {approved && (
                          <button
                            onClick={() => approveMutation.mutate({ personId: person.id, approve: false })}
                            disabled={approveMutation.isLoading}
                            className="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-300"
                            title="Goedkeuring intrekken"
                          >
                            <X className="w-3 h-3" /> Intrekken
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      <div className="card p-4 text-xs text-gray-500 dark:text-gray-400 flex items-start gap-2">
        <Upload className="w-4 h-4 mt-0.5 shrink-0" />
        <div>
          Vrijwilligers uploaden hun IVA-certificaat of diploma Sociale Hygiëne zelf via{' '}
          <Link to="/profile/iva" className="text-bright-cobalt dark:text-electric-cyan hover:underline">
            /profile/iva
          </Link>
          . Beide bewijssoorten blijven na goedkeuring geldig; controleer bij Sociale Hygiëne ook of het diploma recht geeft op registratie in het landelijke register.
        </div>
      </div>
    </div>
  );
}
