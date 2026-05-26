import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { UsersRound, ExternalLink } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

const REASON_LABELS = {
  commissie: 'Commissielid',
  staff: 'Trainer/Leider',
  betaald: 'Betaalde vrijwilliger',
  handmatig: 'Handmatige vrijstelling',
};

const REASON_BADGE_COLORS = {
  commissie: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300',
  staff: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
  betaald: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
  handmatig: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
};

export default function VrijwilligersExemptions() {
  useDocumentTitle('Vrijstellingen — Vrijwilligers');
  const [filterReason, setFilterReason] = useState('all');

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
        if (!person.is_exempt) continue;
        if (seen.has(person.id)) continue;
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
    return exemptPersons.filter((p) => p.reason === filterReason);
  }, [exemptPersons, filterReason]);

  const counts = useMemo(() => {
    const c = { all: exemptPersons.length };
    for (const reason of Object.keys(REASON_LABELS)) {
      c[reason] = exemptPersons.filter((p) => p.reason === reason).length;
    }
    return c;
  }, [exemptPersons]);

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-3">
        <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
          <UsersRound className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Vrijstellingen</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Personen die automatisch of handmatig zijn vrijgesteld van de 2-diensten-plicht.
          </p>
        </div>
      </header>

      <div className="card p-4">
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setFilterReason('all')}
            className={`text-sm px-3 py-1.5 rounded-md transition-colors ${
              filterReason === 'all'
                ? 'bg-bright-cobalt text-white dark:bg-electric-cyan dark:text-gray-900'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
            }`}
          >
            Alle ({counts.all})
          </button>
          {Object.entries(REASON_LABELS).map(([reason, label]) => (
            <button
              key={reason}
              onClick={() => setFilterReason(reason)}
              className={`text-sm px-3 py-1.5 rounded-md transition-colors ${
                filterReason === reason
                  ? 'bg-bright-cobalt text-white dark:bg-electric-cyan dark:text-gray-900'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              {label} ({counts[reason] || 0})
            </button>
          ))}
        </div>
      </div>

      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
            <tr>
              <th className="px-4 py-2">Naam</th>
              <th className="px-4 py-2">Reden vrijstelling</th>
              <th className="px-4 py-2">Eenheid</th>
              <th className="px-4 py-2 w-12"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
            {isLoading ? (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                  Laden…
                </td>
              </tr>
            ) : filtered.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                  Geen vrijgestelde personen in deze categorie.
                </td>
              </tr>
            ) : (
              filtered.map((person) => (
                <tr key={person.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                  <td className="px-4 py-2">
                    <Link
                      to={`/people/${person.id}`}
                      className="text-bright-cobalt dark:text-electric-cyan hover:underline"
                    >
                      {person.name}
                    </Link>
                  </td>
                  <td className="px-4 py-2">
                    <span
                      className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${
                        REASON_BADGE_COLORS[person.reason] || REASON_BADGE_COLORS.handmatig
                      }`}
                    >
                      {person.reason_label || REASON_LABELS[person.reason] || person.reason}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-gray-500 dark:text-gray-400 text-xs">
                    {person.unit_kind === 'gezin' ? 'Gezin' : 'Speler'} ·{' '}
                    <code className="text-xs">{person.unit_id}</code>
                  </td>
                  <td className="px-4 py-2">
                    <Link
                      to={`/people/${person.id}`}
                      className="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200"
                    >
                      <ExternalLink className="w-4 h-4" />
                    </Link>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-gray-500 dark:text-gray-400">
        Vrijstellingen komen uit vier auto-routes (commissielid, trainer/leider, betaalde vrijwilliger) plus de
        handmatige vlag op personen. Pas staf-rollen aan via{' '}
        <Link to="/settings/admin/capabilities" className="underline">Instellingen → Capabilities</Link>.
      </p>
    </div>
  );
}
