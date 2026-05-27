import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, CalendarClock, Plus, Pencil } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

const DAYS = ['', 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'];

export default function VrijwilligersSjablonen() {
  useDocumentTitle('Sjablonen — Vrijwilligers');

  const { data: types = [] } = useQuery({
    queryKey: ['volunteer', 'dienst-types'],
    queryFn: async () => (await prmApi.getDienstTypes()).data || [],
    staleTime: 5 * 60 * 1000,
  });

  const { data: templates = [], isLoading } = useQuery({
    queryKey: ['volunteer', 'shift-templates'],
    queryFn: async () => (await prmApi.getShiftTemplates()).data || [],
    staleTime: 60 * 1000,
  });

  const typeMap = useMemo(() => {
    const map = new Map();
    for (const t of types) map.set(t.id, t.title?.rendered || t.title);
    return map;
  }, [types]);

  const rows = useMemo(() => {
    return [...templates].sort((a, b) => {
      const da = Number(a.meta?.day_of_week || 0);
      const db = Number(b.meta?.day_of_week || 0);
      if (da !== db) return da - db;
      return (a.meta?.start_time || '').localeCompare(b.meta?.start_time || '');
    });
  }, [templates]);

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <Link
          to="/vrijwilligers/diensten"
          className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
        >
          <ArrowLeft className="w-3.5 h-3.5" /> Terug naar diensten
        </Link>
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
              <CalendarClock className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
            </div>
            <div>
              <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Sjablonen</h1>
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Wekelijks terugkerende shift-regels. De expander rolt deze elke nacht uit naar concrete diensten voor de komende 12 weken.
              </p>
            </div>
          </div>
          <Link to="/vrijwilligers/sjablonen/nieuw" className="btn-primary inline-flex items-center gap-2 shrink-0">
            <Plus className="w-4 h-4" /> Nieuw sjabloon
          </Link>
        </div>
      </header>

      {isLoading ? (
        <ContentLoadingSpinner />
      ) : rows.length === 0 ? (
        <div className="card p-8 text-center text-sm text-gray-500 dark:text-gray-400">
          Nog geen sjablonen. Maak er één aan om wekelijkse diensten automatisch uit te rollen.
        </div>
      ) : (
        <div className="card overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
              <tr>
                <th className="px-4 py-2">Dienst type</th>
                <th className="px-4 py-2">Dag</th>
                <th className="px-4 py-2">Tijd</th>
                <th className="px-4 py-2">Capaciteit</th>
                <th className="px-4 py-2">Actief</th>
                <th className="px-4 py-2 w-12"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {rows.map((tpl) => {
                const meta = tpl.meta || {};
                return (
                  <tr key={tpl.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-2">
                      <Link to={`/vrijwilligers/sjablonen/${tpl.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                        {typeMap.get(Number(meta.dienst_type_id)) || '—'}
                      </Link>
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{DAYS[Number(meta.day_of_week) || 0] || '—'}</td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                      {meta.start_time || '—'} – {meta.end_time || '—'}
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                      {meta.capacity > 0 ? meta.capacity : <span className="text-gray-400">default</span>}
                    </td>
                    <td className="px-4 py-2 text-gray-700 dark:text-gray-300 text-xs">
                      {meta.active_from || '—'} → {meta.active_until || 'doorlopend'}
                    </td>
                    <td className="px-4 py-2 text-right">
                      <Link
                        to={`/vrijwilligers/sjablonen/${tpl.id}`}
                        className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        title="Bewerken"
                      >
                        <Pencil className="w-4 h-4" />
                      </Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
