import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, ArrowLeft, ArrowRight } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

const REASON_LABELS = {
  age_gap_too_small: { label: 'Te klein leeftijdsverschil', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' },
  sibling_gap_too_large: { label: 'Erg groot sibling-verschil', color: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' },
};

export default function VrijwilligersRelationshipQuality() {
  useDocumentTitle('Verdachte familie-relaties — Vrijwilligers');

  const { data, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'relationship-quality'],
    queryFn: async () => (await prmApi.getRelationshipQuality()).data,
    staleTime: 60 * 1000,
  });

  const pairs = data?.pairs || [];

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar Vrijwilligers-dashboard
        </Link>
        <div className="flex items-start gap-3">
          <div className="p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
            <AlertTriangle className="w-6 h-6 text-amber-500" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
              Verdachte familie-relaties
            </h1>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-3xl">
              Deze relaties zijn vastgelegd als ouder/kind of broer-zus, maar het leeftijdsverschil maakt dat onwaarschijnlijk —
              vaak is een ouder/kind-link in werkelijkheid een sibling-relatie. Open een persoon, herstel het{' '}
              <code>relationship_type</code> en de inverse-relatie wordt automatisch bijgewerkt.
            </p>
          </div>
        </div>
      </header>

      {error && (
        <div className="card p-4 bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800 text-sm text-red-700 dark:text-red-300">
          Kon de lijst niet ophalen: {error?.message || 'onbekende fout'}
        </div>
      )}

      {isLoading ? (
        <ContentLoadingSpinner />
      ) : pairs.length === 0 ? (
        <div className="card p-8 text-center text-sm text-gray-500 dark:text-gray-400">
          Geen verdachte relaties gevonden. 🎉
        </div>
      ) : (
        <>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            {pairs.length} verdachte {pairs.length === 1 ? 'relatie' : 'relaties'} — gesorteerd op ernst.
          </p>
          <ul className="space-y-3">
            {pairs.map((pair) => {
              const cfg = REASON_LABELS[pair.reason] || { label: pair.reason, color: 'bg-gray-100 text-gray-700' };
              return (
                <li key={pair.pair_key} className="card p-4">
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-3 flex-wrap">
                        <PersonChip id={pair.person_a_id} name={pair.person_a_name} thumbnail={pair.person_a_thumbnail} leeftijdsgroep={pair.leeftijdsgroep_a} />
                        <span className="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700">
                          {pair.claimed_relation} <ArrowRight className="w-3 h-3" />
                        </span>
                        <PersonChip id={pair.person_b_id} name={pair.person_b_name} thumbnail={pair.person_b_thumbnail} leeftijdsgroep={pair.leeftijdsgroep_b} />
                      </div>
                      <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{pair.detail}</p>
                    </div>
                    <span className={`shrink-0 px-2 py-1 rounded text-xs font-medium ${cfg.color}`}>
                      {cfg.label}
                    </span>
                  </div>
                </li>
              );
            })}
          </ul>
        </>
      )}
    </div>
  );
}

function PersonChip({ id, name, thumbnail, leeftijdsgroep }) {
  return (
    <Link
      to={`/people/${id}`}
      className="inline-flex items-center gap-2 px-2 py-1 rounded bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 text-sm"
    >
      {thumbnail ? (
        <img src={thumbnail} alt="" className="w-6 h-6 rounded-full object-cover shrink-0" />
      ) : (
        <span className="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 inline-block shrink-0" />
      )}
      <span className="text-gray-900 dark:text-gray-100">{name}</span>
      {leeftijdsgroep && (
        <span className="text-xs text-gray-500 dark:text-gray-400">({leeftijdsgroep})</span>
      )}
    </Link>
  );
}
