import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { CalendarClock, Plus, Settings, Pencil } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';

export default function VrijwilligersDiensten() {
  useDocumentTitle('Diensten — Vrijwilligers');

  const adminUrl = (path) => `${window.rondoConfig?.adminUrl || '/wp-admin/'}${path}`;

  const { data: typesData, isLoading: typesLoading } = useQuery({
    queryKey: ['volunteer', 'dienst-types'],
    queryFn: async () => (await prmApi.getDienstTypes({ per_page: 50 })).data,
    staleTime: 5 * 60 * 1000,
  });

  const { data: shiftsData, isLoading: shiftsLoading } = useQuery({
    queryKey: ['volunteer', 'dienst-shifts'],
    queryFn: async () => (await prmApi.getDienstShifts({ per_page: 50, orderby: 'date', order: 'desc' })).data,
    staleTime: 60 * 1000,
  });

  const types = Array.isArray(typesData) ? typesData : [];
  const shifts = Array.isArray(shiftsData) ? shiftsData : [];

  return (
    <div className="space-y-6">
      <header className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <CalendarClock className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Diensten</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Catalog van diensttypes, sjablonen en concrete shifts.
            </p>
          </div>
        </div>
        <div className="flex gap-2">
          <Link to="/vrijwilligers/sjablonen" className="btn-tertiary inline-flex items-center gap-1.5">
            <Settings className="w-4 h-4" /> Sjablonen
          </Link>
          <Link to="/vrijwilligers/diensten/nieuw" className="btn-primary inline-flex items-center gap-1.5">
            <Plus className="w-4 h-4" /> Nieuwe dienst
          </Link>
        </div>
      </header>

      <section>
        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-3">
          Diensttypes ({types.length})
        </h2>
        {typesLoading ? (
          <div className="card p-6 text-center text-gray-500 dark:text-gray-400">Laden…</div>
        ) : types.length === 0 ? (
          <div className="card p-6 text-center text-gray-500 dark:text-gray-400">
            Geen diensttypes gevonden. De seeder maakt deze automatisch aan bij de eerstvolgende init.
          </div>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {types.map((type) => {
              const meta = type.meta || {};
              const color = meta.color || '#6b7280';
              return (
                <div key={type.id} className="card p-4 flex flex-col gap-2">
                  <div className="flex items-start gap-3">
                    <span className="w-3 h-3 rounded-full mt-1.5 shrink-0" style={{ background: color }} />
                    <div className="min-w-0 flex-1">
                      <div className="font-medium text-gray-900 dark:text-gray-100">
                        {type.title?.rendered || type.title}
                      </div>
                      {meta.description && (
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                          {meta.description}
                        </p>
                      )}
                    </div>
                    <a
                      href={adminUrl(`post.php?post=${type.id}&action=edit`)}
                      className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                      title="Bewerken in WP-admin (alleen voor admins)"
                    >
                      <Pencil className="w-4 h-4" />
                    </a>
                  </div>
                  <div className="flex flex-wrap gap-1 text-xs">
                    {meta.vog_required && (
                      <span className="px-2 py-0.5 bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300 rounded">
                        VOG
                      </span>
                    )}
                    {meta.iva_required && (
                      <span className="px-2 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 rounded">
                        IVA
                      </span>
                    )}
                    {meta.sleutel_involved && (
                      <span className="px-2 py-0.5 bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 rounded">
                        Sleutel
                      </span>
                    )}
                    <span className="ml-auto text-gray-400">
                      Cap. {meta.default_capacity || 1}
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      <section>
        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-3">
          Recente shifts ({shifts.length})
        </h2>
        {shiftsLoading ? (
          <div className="card p-6 text-center text-gray-500 dark:text-gray-400">Laden…</div>
        ) : shifts.length === 0 ? (
          <div className="card p-8 text-center">
            <div className="text-gray-500 dark:text-gray-400 mb-2">Nog geen diensten gepland.</div>
            <p className="text-xs text-gray-400 dark:text-gray-500 max-w-md mx-auto">
              Maak een ad-hoc dienst aan via &ldquo;Nieuwe dienst&rdquo; rechtsboven, of zet een wekelijks{' '}
              <Link to="/vrijwilligers/sjablonen" className="underline">sjabloon</Link> klaar — de expander rolt sjablonen elke nacht uit naar concrete diensten voor de komende 12 weken.
            </p>
          </div>
        ) : (
          <div className="card overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
                <tr>
                  <th className="px-4 py-2">Titel</th>
                  <th className="px-4 py-2">Start</th>
                  <th className="px-4 py-2">Eind</th>
                  <th className="px-4 py-2">Status</th>
                  <th className="px-4 py-2 w-12"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {shifts.map((shift) => {
                  const meta = shift.meta || {};
                  return (
                    <tr key={shift.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                        <Link to={`/vrijwilligers/diensten/${shift.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                          {shift.title?.rendered || shift.title || `Shift ${shift.id}`}
                        </Link>
                      </td>
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                        {meta.start_datetime ? format(meta.start_datetime, 'dd-MM-yyyy HH:mm') : '—'}
                      </td>
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                        {meta.end_datetime ? format(meta.end_datetime, 'dd-MM-yyyy HH:mm') : '—'}
                      </td>
                      <td className="px-4 py-2 text-xs">
                        {meta.status || 'open'}
                      </td>
                      <td className="px-4 py-2">
                        <Link to={`/vrijwilligers/diensten/${shift.id}`} className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Bewerken">
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
      </section>
    </div>
  );
}
