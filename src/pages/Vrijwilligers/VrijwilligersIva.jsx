import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Wine, Check, X, ExternalLink, Upload } from 'lucide-react';
import { wpApi, prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';

const TABS = [
  { id: 'pending', label: 'Wacht op goedkeuring' },
  { id: 'approved', label: 'Goedgekeurd' },
  { id: 'missing',  label: 'Niet ingeleverd' },
];

function loadPeopleWithIvaFields() {
  // Pull the full people list once; filter client-side. People list is small enough
  // (a few hundred records typically) and re-using existing endpoints keeps this MVP small.
  return prmApi.getFilteredPeople({ per_page: 1000 }).then((r) => r.data);
}

function approveIva(personId, approve) {
  // Person updates go through the standard wp/v2/people endpoint.
  return wpApi.updatePerson(personId, {
    meta: { 'iva-approved': approve },
  });
}

export default function VrijwilligersIva() {
  useDocumentTitle('IVA — Vrijwilligers');
  const queryClient = useQueryClient();
  const [tab, setTab] = useState('pending');

  const { data, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'iva', 'people'],
    queryFn: loadPeopleWithIvaFields,
    staleTime: 60 * 1000,
  });

  const approveMutation = useMutation({
    mutationFn: ({ personId, approve }) => approveIva(personId, approve),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer', 'iva', 'people'] });
    },
  });

  const people = data?.people || data || [];

  const buckets = useMemo(() => {
    const pending = [];
    const approved = [];
    const missing = [];
    for (const person of people) {
      const datum = person.datum_iva || person['datum-iva'] || person.meta?.['datum-iva'] || '';
      const isApproved = !!(person['iva-approved'] || person.iva_approved || person.meta?.['iva-approved']);
      const cert = person['iva-certificaat'] || person.iva_certificaat || person.acf?.['iva-certificaat'];

      if (!datum && !cert) {
        missing.push(person);
      } else if (isApproved) {
        approved.push(person);
      } else {
        pending.push(person);
      }
    }
    return { pending, approved, missing };
  }, [people]);

  const active = buckets[tab] || [];

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-3">
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <Wine className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">IVA — Alcoholcertificaat</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Instructie Verantwoord Alcoholschenken — vereist voor wie achter de bar staat.
          </p>
        </div>
      </header>

      <nav className="flex gap-6 border-b border-gray-200 dark:border-gray-700">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              tab === t.id
                ? 'border-bright-cobalt text-bright-cobalt dark:border-electric-cyan dark:text-electric-cyan'
                : 'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'
            }`}
          >
            {t.label} ({(buckets[t.id] || []).length})
          </button>
        ))}
      </nav>

      {error && (
        <div className="card p-4 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
          Kon mensen niet ophalen: {error?.message || 'onbekende fout'}
        </div>
      )}

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
            <tr>
              <th className="px-4 py-2">Naam</th>
              <th className="px-4 py-2">Datum IVA</th>
              <th className="px-4 py-2">Certificaat</th>
              <th className="px-4 py-2">Status</th>
              <th className="px-4 py-2 text-right">Acties</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {isLoading ? (
              <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Laden…</td></tr>
            ) : active.length === 0 ? (
              <tr><td colSpan={5} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                {tab === 'pending' && 'Geen IVA-certificaten wachten op goedkeuring.'}
                {tab === 'approved' && 'Nog niemand heeft een goedgekeurd IVA-certificaat.'}
                {tab === 'missing' && 'Iedereen heeft minimaal iets ingeleverd.'}
              </td></tr>
            ) : (
              active.map((person) => {
                const datum = person.datum_iva || person['datum-iva'] || person.meta?.['datum-iva'] || '';
                const isApproved = !!(person['iva-approved'] || person.iva_approved || person.meta?.['iva-approved']);
                const cert = person['iva-certificaat'] || person.iva_certificaat || person.acf?.['iva-certificaat'];
                const certUrl = typeof cert === 'object' ? (cert?.url || cert?.link) : (typeof cert === 'string' ? cert : '');

                return (
                  <tr key={person.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-2">
                      <Link to={`/people/${person.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                        {person.name || person.title?.rendered || `Persoon ${person.id}`}
                      </Link>
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                      {datum ? format(datum, 'dd-MM-yyyy') : <span className="text-gray-400">—</span>}
                    </td>
                    <td className="px-4 py-2">
                      {certUrl ? (
                        <a href={certUrl} target="_blank" rel="noopener noreferrer"
                           className="text-bright-cobalt dark:text-electric-cyan hover:underline inline-flex items-center gap-1">
                          <ExternalLink className="w-3.5 h-3.5" /> Bekijk
                        </a>
                      ) : (
                        <span className="text-gray-400">—</span>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      {isApproved
                        ? <span className="text-emerald-700 dark:text-emerald-400 text-xs font-medium">Goedgekeurd</span>
                        : datum || certUrl
                          ? <span className="text-amber-700 dark:text-amber-400 text-xs font-medium">Wacht op review</span>
                          : <span className="text-gray-500 text-xs">Niet ingeleverd</span>
                      }
                    </td>
                    <td className="px-4 py-2 text-right">
                      <div className="inline-flex gap-1">
                        {!isApproved && (datum || certUrl) && (
                          <button
                            onClick={() => approveMutation.mutate({ personId: person.id, approve: true })}
                            disabled={approveMutation.isLoading}
                            className="inline-flex items-center gap-1 px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300"
                            title="Goedkeuren"
                          >
                            <Check className="w-3 h-3" /> Goedkeur
                          </button>
                        )}
                        {isApproved && (
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
          Vrijwilligers uploaden hun IVA-certificaat via de standaard person-bewerk-flow (ACF veld
          <code className="mx-1 text-xs">iva-certificaat</code>). Geldigheidstermijn wordt nog vastgesteld door het bestuur —
          op dit moment vervalt een goedgekeurd certificaat niet automatisch.
        </div>
      </div>
    </div>
  );
}
