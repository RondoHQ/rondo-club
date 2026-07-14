import { useQuery } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { CalendarClock, Plus, Settings, Pencil } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';
import ShiftCoverageCalendar from '@/components/volunteers/ShiftCoverageCalendar';

export default function VrijwilligersDiensten() {
  useDocumentTitle('Inschrijftaken — Vrijwilligers');
  const [searchParams, setSearchParams] = useSearchParams();
  const selectedDienstType = searchParams.get('diensttype') || '';

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

  const { data: calendarData, isLoading: calendarLoading } = useQuery({
    queryKey: ['shift-calendar', 'manage', selectedDienstType],
    queryFn: async () => (await prmApi.getShiftCalendar({
      view: 'manage',
      dienst_type_id: selectedDienstType || undefined,
    })).data,
    staleTime: 60 * 1000,
  });

  const types = Array.isArray(typesData) ? typesData : [];
  const shifts = Array.isArray(shiftsData) ? shiftsData : [];

  const handleDienstTypeChange = (dienstType) => {
    setSearchParams((previousParams) => {
      const nextParams = new URLSearchParams(previousParams);
      if (dienstType) nextParams.set('diensttype', dienstType);
      else nextParams.delete('diensttype');
      return nextParams;
    }, { replace: true });
  };

  return (
    <div className="space-y-6">
      <header className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-cyan-50 dark:bg-gray-700 rounded-lg">
            <CalendarClock className="w-6 h-6 text-bright-cobalt dark:text-electric-cyan" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Inschrijftaken</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Catalogus van inschrijftaken, sjablonen en geplande inschrijftaken.
            </p>
          </div>
        </div>
        <div className="flex gap-2">
          <Link to="/vrijwilligers/sjablonen" className="btn-tertiary inline-flex items-center gap-1.5">
            <Settings className="w-4 h-4" /> Sjablonen
          </Link>
          <Link to="/vrijwilligers/diensten/nieuw" className="btn-primary inline-flex items-center gap-1.5">
            <Plus className="w-4 h-4" /> Nieuwe inschrijftaak
          </Link>
        </div>
      </header>

      <ShiftCoverageCalendar
        data={calendarData}
        isLoading={calendarLoading}
        selectedDienstType={selectedDienstType}
        onDienstTypeChange={handleDienstTypeChange}
        description="Klik op een gekleurde datum om de bezetting per inschrijftaak te bekijken."
        renderShift={(shift) => (
          <div key={shift.id} className="flex items-center gap-3 rounded-md border border-gray-200 p-3 dark:border-gray-700">
            <span className="h-10 w-2 shrink-0 rounded-full" style={{ background: shift.dienst_type_color || '#6b7280' }} />
            <div className="min-w-0 flex-1">
              <Link
                to={`/vrijwilligers/diensten/${shift.id}`}
                className="font-medium text-bright-cobalt hover:underline dark:text-electric-cyan"
              >
                {shift.dienst_type_name || shift.title}
              </Link>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {format(shift.start_datetime, 'HH:mm')}–{format(shift.end_datetime, 'HH:mm')} · {shift.assigned_count} van {Math.max(1, shift.capacity)} plekken bezet
              </p>
            </div>
            <span className={`text-xs font-medium ${shift.is_filled ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'}`}>
              {shift.is_filled ? 'Ingevuld' : `${Math.max(0, Math.max(1, shift.capacity) - shift.assigned_count)} open`}
            </span>
            <Link
              to={`/vrijwilligers/diensten/${shift.id}`}
              className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
              title="Bewerken"
            >
              <Pencil className="h-4 w-4" />
            </Link>
          </div>
        )}
      />

      <section>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">
            Inschrijftaken ({types.length})
          </h2>
          <Link to="/vrijwilligers/diensttypes/nieuw" className="btn-tertiary inline-flex items-center gap-1.5 text-xs">
            <Plus className="w-3.5 h-3.5" /> Inschrijftaak
          </Link>
        </div>
        {typesLoading ? (
          <div className="card p-6 text-center text-gray-500 dark:text-gray-400">Laden…</div>
        ) : types.length === 0 ? (
          <div className="card p-6 text-center text-gray-500 dark:text-gray-400">
            Nog geen inschrijftaken. Maak er een aan met &ldquo;Inschrijftaak&rdquo; hierboven.
          </div>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {types.map((type) => {
              const acf = type.acf || {};
              const color = acf.color || '#6b7280';
              return (
                <div key={type.id} className="card p-4 flex flex-col gap-2">
                  <div className="flex items-start gap-3">
                    <span className="w-3 h-3 rounded-full mt-1.5 shrink-0" style={{ background: color }} />
                    <div className="min-w-0 flex-1">
                      <div className="font-medium text-gray-900 dark:text-gray-100">
                        {type.title?.rendered || type.title}
                      </div>
                      {acf.description && (
                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                          {acf.description}
                        </p>
                      )}
                    </div>
                    <Link
                      to={`/vrijwilligers/diensttypes/${type.id}`}
                      className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                      title="Inschrijftaak bewerken"
                    >
                      <Pencil className="w-4 h-4" />
                    </Link>
                  </div>
                  <div className="flex flex-wrap gap-1 text-xs">
                    {acf.vog_required && (
                      <span className="px-2 py-0.5 bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300 rounded">
                        VOG
                      </span>
                    )}
                    {acf.iva_required && (
                      <span className="px-2 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 rounded">
                        IVA
                      </span>
                    )}
                    {acf.sleutel_involved && (
                      <span className="px-2 py-0.5 bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300 rounded">
                        Sleutel
                      </span>
                    )}
                    <span className="ml-auto text-gray-400">
                      Cap. {acf.default_capacity || 1}
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
          Recente inschrijftaken ({shifts.length})
        </h2>
        {shiftsLoading ? (
          <div className="card p-6 text-center text-gray-500 dark:text-gray-400">Laden…</div>
        ) : shifts.length === 0 ? (
          <div className="card p-8 text-center">
            <div className="text-gray-500 dark:text-gray-400 mb-2">Nog geen inschrijftaken gepland.</div>
            <p className="text-xs text-gray-400 dark:text-gray-500 max-w-md mx-auto">
              Maak een losse inschrijftaak aan via &ldquo;Nieuwe inschrijftaak&rdquo; rechtsboven, of zet een wekelijks{' '}
              <Link to="/vrijwilligers/sjablonen" className="underline">sjabloon</Link> klaar — de expander rolt sjablonen elke nacht uit naar geplande inschrijftaken.
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
                  const acf = shift.acf || {};
                  return (
                    <tr key={shift.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                      <td className="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">
                        <Link to={`/vrijwilligers/diensten/${shift.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                          {shift.title?.rendered || shift.title || `Inschrijftaak ${shift.id}`}
                        </Link>
                      </td>
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                        {acf.start_datetime ? format(acf.start_datetime, 'dd-MM-yyyy HH:mm') : '—'}
                      </td>
                      <td className="px-4 py-2 text-gray-700 dark:text-gray-300">
                        {acf.end_datetime ? format(acf.end_datetime, 'dd-MM-yyyy HH:mm') : '—'}
                      </td>
                      <td className="px-4 py-2 text-xs">
                        {acf.status || 'open'}
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
