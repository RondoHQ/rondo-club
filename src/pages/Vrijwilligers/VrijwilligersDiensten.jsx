import { useCallback, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import { CalendarClock, Copy, ListChecks, Plus, Settings, Pencil, X } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { format } from '@/utils/dateFormat';
import { refreshShiftCalendars, shiftCalendarKeys } from '@/utils/shiftQueryCache';
import AnchoredPopover from '@/components/AnchoredPopover';
import ShiftCoverageCalendar from '@/components/volunteers/ShiftCoverageCalendar';

function DienstTypesPopover({ anchor, isLoading, onClose, types }) {
  const closeButtonRef = useRef(null);
  const titleId = 'dienst-types-popover-title';

  return (
    <AnchoredPopover
      anchor={anchor}
      id="dienst-types-popover"
      labelledBy={titleId}
      initialFocusRef={closeButtonRef}
      maxWidth={768}
      preferredHeight={480}
      onClose={onClose}
      className="p-4 sm:p-5"
    >
      <div className="mb-4 flex items-start justify-between gap-3">
        <div>
          <h2 id={titleId} className="font-semibold text-gray-900 dark:text-gray-100">
            Inschrijftaken ({types.length})
          </h2>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Beheer de soorten inschrijftaken die je kunt inplannen.
          </p>
        </div>
        <button
          ref={closeButtonRef}
          type="button"
          onClick={() => {
            onClose();
            anchor?.focus();
          }}
          className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-bright-cobalt dark:hover:bg-gray-800 dark:hover:text-gray-200 dark:focus:ring-electric-cyan"
          aria-label="Sluiten"
        >
          <X className="h-4 w-4" aria-hidden="true" />
        </button>
      </div>

      <div className="mb-3 flex justify-end">
        <Link to="/vrijwilligers/diensttypes/nieuw" className="btn-tertiary inline-flex items-center gap-1.5 text-xs">
          <Plus className="h-3.5 w-3.5" /> Inschrijftaak
        </Link>
      </div>

      {isLoading ? (
        <div className="rounded-lg border border-gray-200 p-6 text-center text-gray-500 dark:border-gray-700 dark:text-gray-400">Laden…</div>
      ) : types.length === 0 ? (
        <div className="rounded-lg border border-gray-200 p-6 text-center text-gray-500 dark:border-gray-700 dark:text-gray-400">
          Nog geen inschrijftaken. Maak er een aan met &ldquo;Inschrijftaak&rdquo; hierboven.
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {types.map((type) => {
            const fields = type.fields || {};
            const color = fields.color || '#6b7280';
            return (
              <div key={type.id} className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <div className="flex items-start gap-3">
                  <span className="mt-1.5 h-3 w-3 shrink-0 rounded-full" style={{ background: color }} />
                  <div className="min-w-0 flex-1">
                    <div className="font-medium text-gray-900 dark:text-gray-100">
                      {type.title?.rendered || type.title}
                    </div>
                    {fields.description && (
                      <p className="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                        {fields.description}
                      </p>
                    )}
                  </div>
                  <Link
                    to={`/vrijwilligers/diensttypes/${type.id}`}
                    className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                    title="Inschrijftaak bewerken"
                  >
                    <Pencil className="h-4 w-4" />
                  </Link>
                </div>
                <div className="mt-2 flex flex-wrap gap-1 text-xs">
                  {fields.vog_required && (
                    <span className="rounded bg-cyan-100 px-2 py-0.5 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300">VOG</span>
                  )}
                  {fields.iva_required && (
                    <span className="rounded bg-amber-100 px-2 py-0.5 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">IVA</span>
                  )}
                  {fields.sleutel_involved && (
                    <span className="rounded bg-purple-100 px-2 py-0.5 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">Sleutel</span>
                  )}
                  <span className="ml-auto text-gray-400">Cap. {fields.default_capacity || 1}</span>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </AnchoredPopover>
  );
}

function CopyDayModal({ day, error, isLoading, onClose, onSubmit }) {
  const [targetDate, setTargetDate] = useState('');
  const today = format(new Date(), 'yyyy-MM-dd');
  const sourceLabel = format(`${day.date}T12:00:00`, 'EEEE d MMMM yyyy');

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="copy-day-title"
        className="w-full max-w-lg rounded-lg bg-white shadow-xl dark:bg-gray-800"
        onKeyDown={(event) => {
          if (event.key === 'Escape' && !isLoading) onClose();
        }}
      >
        <form
          onSubmit={(event) => {
            event.preventDefault();
            onSubmit(targetDate);
          }}
        >
          <div className="flex items-start justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
            <div>
              <h2 id="copy-day-title" className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Dagplanning kopiëren
              </h2>
              <p className="mt-1 text-sm capitalize text-gray-500 dark:text-gray-400">{sourceLabel}</p>
            </div>
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-gray-700 dark:hover:text-gray-200"
              aria-label="Sluiten"
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          <div className="space-y-4 p-4">
            <p className="text-sm text-gray-600 dark:text-gray-300">
              {day.shift_count} {day.shift_count === 1 ? 'inschrijftaak' : 'inschrijftaken'} met in totaal {day.capacity} {day.capacity === 1 ? 'plek' : 'plekken'}.
              Aanmeldingen en statussen worden niet meegenomen.
            </p>

            <div className="max-h-52 space-y-2 overflow-y-auto rounded-md bg-gray-50 p-3 dark:bg-gray-900/50">
              {day.shifts.map((shift) => (
                <div key={shift.id} className="flex items-center justify-between gap-3 text-sm">
                  <span className="min-w-0 truncate text-gray-800 dark:text-gray-200">
                    {shift.dienst_type_name || shift.title}
                  </span>
                  <span className="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                    {format(shift.start_datetime, 'HH:mm')}–{format(shift.end_datetime, 'HH:mm')} · {Math.max(1, Number(shift.capacity) || 1)}
                  </span>
                </div>
              ))}
            </div>

            <div>
              <label htmlFor="copy-day-target" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Kopiëren naar
              </label>
              <input
                id="copy-day-target"
                type="date"
                value={targetDate}
                min={today}
                onChange={(event) => setTargetDate(event.target.value)}
                className="input w-full"
                required
                autoFocus
              />
            </div>

            {error ? <p role="alert" className="text-sm text-red-600 dark:text-red-400">{error}</p> : null}
          </div>

          <div className="flex justify-end gap-2 rounded-b-lg border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
            <button type="button" onClick={onClose} disabled={isLoading} className="btn-secondary">
              Annuleren
            </button>
            <button type="submit" disabled={isLoading || !targetDate} className="btn-primary inline-flex items-center gap-2">
              <Copy className="h-4 w-4" />
              {isLoading ? 'Kopiëren…' : 'Dag kopiëren'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function VrijwilligersDiensten() {
  useDocumentTitle('Inschrijftaken — Vrijwilligers');
  const queryClient = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const [typesPopoverOpen, setTypesPopoverOpen] = useState(false);
  const [copyDay, setCopyDay] = useState(null);
  const [copyError, setCopyError] = useState('');
  const [feedback, setFeedback] = useState(null);
  const typesButtonRef = useRef(null);
  const selectedDienstType = searchParams.get('diensttype') || '';

  const { data: typesData, isLoading: typesLoading } = useQuery({
    queryKey: ['volunteer', 'dienst-types'],
    queryFn: async () => (await prmApi.getDienstTypes({ per_page: 50 })).data,
    staleTime: 5 * 60 * 1000,
  });

  const { data: recentSignupsData, isLoading: recentSignupsLoading } = useQuery({
    queryKey: ['volunteer', 'recent-shift-signups'],
    queryFn: async () => (await prmApi.getRecentShiftSignups()).data,
    staleTime: 60 * 1000,
  });

  const { data: calendarData, isLoading: calendarLoading } = useQuery({
    queryKey: shiftCalendarKeys.manage(selectedDienstType),
    queryFn: async () => (await prmApi.getShiftCalendar({
      view: 'manage',
      dienst_type_id: selectedDienstType || undefined,
    })).data,
    staleTime: 60 * 1000,
  });

  const types = Array.isArray(typesData) ? typesData : [];
  const recentSignupShifts = Array.isArray(recentSignupsData?.shifts) ? recentSignupsData.shifts : [];

  const handleDienstTypeChange = (dienstType) => {
    setSearchParams((previousParams) => {
      const nextParams = new URLSearchParams(previousParams);
      if (dienstType) nextParams.set('diensttype', dienstType);
      else nextParams.delete('diensttype');
      return nextParams;
    }, { replace: true });
  };
  const closeTypesPopover = useCallback(() => setTypesPopoverOpen(false), []);

  const copyDayMutation = useMutation({
    mutationFn: ({ sourceDate, targetDate }) => prmApi.copyShiftDay(sourceDate, targetDate),
    onSuccess: async (response) => {
      const data = response?.data || {};
      const created = Number(data.created || 0);
      const skipped = Number(data.skipped || 0);
      const targetLabel = format(`${data.target_date}T12:00:00`, 'd MMMM yyyy');

      await refreshShiftCalendars(queryClient);
      setCopyDay(null);
      setCopyError('');
      setFeedback({
        kind: 'success',
        message: created > 0
          ? `${created} ${created === 1 ? 'inschrijftaak is' : 'inschrijftaken zijn'} gekopieerd naar ${targetLabel}.${skipped > 0 ? ` ${skipped} bestaande ${skipped === 1 ? 'inschrijftaak is' : 'inschrijftaken zijn'} overgeslagen.` : ''}`
          : `Op ${targetLabel} ${skipped === 1 ? 'stond deze inschrijftaak' : `stonden deze ${skipped} inschrijftaken`} al. Er is niets toegevoegd.`,
      });
    },
    onError: (err) => {
      setCopyError(err?.response?.data?.message || err?.message || 'De dagplanning kon niet worden gekopieerd.');
    },
  });

  const renderDayActions = useCallback((day, closeDetails) => {
    if (selectedDienstType) {
      return (
        <p className="text-xs text-gray-500 dark:text-gray-400">
          Kies &ldquo;Alle inschrijftaken&rdquo; in het filter om de volledige dagplanning te kopiëren.
        </p>
      );
    }

    return (
      <button
        type="button"
        onClick={() => {
          closeDetails();
          setFeedback(null);
          setCopyError('');
          setCopyDay(day);
        }}
        className="btn-tertiary inline-flex w-full items-center justify-center gap-2"
      >
        <Copy className="h-4 w-4" /> Dagplanning kopiëren
      </button>
    );
  }, [selectedDienstType]);

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
        <div className="flex flex-wrap justify-end gap-2">
          <Link to="/vrijwilligers/sjablonen" className="btn-tertiary inline-flex items-center gap-1.5">
            <Settings className="w-4 h-4" /> Sjablonen
          </Link>
          <button
            ref={typesButtonRef}
            type="button"
            onClick={() => setTypesPopoverOpen((isOpen) => !isOpen)}
            className="btn-tertiary inline-flex items-center gap-1.5"
            aria-expanded={typesPopoverOpen}
            aria-controls="dienst-types-popover"
          >
            <ListChecks className="h-4 w-4" /> Inschrijftaken
          </button>
          <Link to="/vrijwilligers/diensten/nieuw" className="btn-primary inline-flex items-center gap-1.5">
            <Plus className="w-4 h-4" /> Nieuwe inschrijftaak
          </Link>
        </div>
      </header>

      {feedback ? (
        <div
          role="status"
          className={`rounded-md border px-4 py-3 text-sm ${
            feedback.kind === 'success'
              ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200'
              : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200'
          }`}
        >
          {feedback.message}
        </div>
      ) : null}

      {typesPopoverOpen && (
        <DienstTypesPopover
          anchor={typesButtonRef.current}
          isLoading={typesLoading}
          onClose={closeTypesPopover}
          types={types}
        />
      )}

      <ShiftCoverageCalendar
        data={calendarData}
        isLoading={calendarLoading}
        selectedDienstType={selectedDienstType}
        onDienstTypeChange={handleDienstTypeChange}
        description="Klik op een gekleurde datum om de bezetting per inschrijftaak te bekijken."
        detailsVariant="popover"
        renderDayActions={renderDayActions}
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
        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider mb-3">
          Recente aanmeldingen ({recentSignupShifts.length})
        </h2>
        {recentSignupsLoading ? (
          <div className="card p-6 text-center text-gray-500 dark:text-gray-400">Laden…</div>
        ) : recentSignupShifts.length === 0 ? (
          <div className="card p-8 text-center">
            <div className="text-gray-500 dark:text-gray-400">Nog geen recente aanmeldingen.</div>
          </div>
        ) : (
          <div className="card overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 dark:bg-gray-700 text-left text-xs uppercase text-gray-500 dark:text-gray-300">
                <tr>
                  <th className="px-4 py-2">Inschrijftaak</th>
                  <th className="px-4 py-2">Dienstmoment</th>
                  <th className="px-4 py-2">Ingeschreven</th>
                  <th className="px-4 py-2">Laatste aanmelding</th>
                  <th className="px-4 py-2 w-12"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {recentSignupShifts.map((shift) => (
                  <tr key={shift.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                      <Link to={`/vrijwilligers/diensten/${shift.id}`} className="text-bright-cobalt dark:text-electric-cyan hover:underline">
                        {shift.dienst_type_name || shift.title || `Inschrijftaak ${shift.id}`}
                      </Link>
                    </td>
                    <td className="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                      {format(shift.start_datetime, 'EEE d MMM yyyy, HH:mm')}–{format(shift.end_datetime, 'HH:mm')}
                    </td>
                    <td className="px-4 py-3">
                      {shift.signups.map((signup, index) => (
                        <span key={signup.person_id}>
                          {index > 0 ? ', ' : ''}
                          <Link
                            to={`/people/${signup.person_id}`}
                            className="text-bright-cobalt hover:underline dark:text-electric-cyan"
                          >
                            {signup.name}
                          </Link>
                        </span>
                      ))}
                    </td>
                    <td className="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                      {format(shift.latest_signup_at, 'dd-MM-yyyy HH:mm')}
                    </td>
                    <td className="px-4 py-3">
                      <Link to={`/vrijwilligers/diensten/${shift.id}`} className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" title="Openen">
                        <Pencil className="w-4 h-4" />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {copyDay ? (
        <CopyDayModal
          key={copyDay.date}
          day={copyDay}
          error={copyError}
          isLoading={copyDayMutation.isPending}
          onClose={() => {
            if (!copyDayMutation.isPending) {
              setCopyDay(null);
              setCopyError('');
            }
          }}
          onSubmit={(targetDate) => {
            setCopyError('');
            if (targetDate === copyDay.date) {
              setCopyError('De doeldatum moet anders zijn dan de brondatum.');
              return;
            }
            copyDayMutation.mutate({ sourceDate: copyDay.date, targetDate });
          }}
        />
      ) : null}
    </div>
  );
}
