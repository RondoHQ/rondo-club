import { Fragment, useId, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, ChevronLeft, ChevronRight, RefreshCw, UserRoundPlus } from 'lucide-react';
import { prmApi } from '@/api/client';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { addDays, format, formatStoredDateTime } from '@/utils/dateFormat';

const EMPTY_SHIFTS = [];
const fieldClass = 'input w-full';

function shiftLabel(shift) {
  return `${shift.name} · ${formatStoredDateTime(shift.start_datetime, 'EEE d MMM yyyy, HH:mm')}–${formatStoredDateTime(shift.end_datetime, 'HH:mm')}`;
}

function AssignmentForm({ row, onClose, onAssigned }) {
  const id = useId();
  const queryClient = useQueryClient();
  const [personId, setPersonId] = useState(row.people.length === 1 ? String(row.people[0].id) : '');
  const [from, setFrom] = useState(() => format(new Date(), 'yyyy-MM-dd'));
  const [to, setTo] = useState(() => format(addDays(new Date(), 42), 'yyyy-MM-dd'));
  const [task, setTask] = useState('');
  const [shiftId, setShiftId] = useState('');
  const [saveError, setSaveError] = useState('');
  const person = row.people.find((item) => item.id === Number(personId));
  const validPeriod = from && to && to >= from;

  const { data, isLoading, isFetching, error, refetch } = useQuery({
    queryKey: ['volunteer', 'assignment-shifts', row.unit_id, personId, from, to],
    queryFn: async () => (await prmApi.getVolunteerAssignmentShifts({ unit_id: row.unit_id, person_id: Number(personId), from, to })).data,
    enabled: Boolean(personId && validPeriod),
    staleTime: 0,
    gcTime: 0,
  });
  const shifts = data?.shifts ?? EMPTY_SHIFTS;
  const tasks = [...new Set(shifts.map((shift) => shift.name))];
  const visibleShifts = shifts.filter((shift) => !task || shift.name === task);
  const selected = visibleShifts.find((shift) => shift.id === Number(shiftId));

  const assignment = useMutation({
    mutationFn: () => prmApi.assignVolunteerDuty({ unit_id: row.unit_id, person_id: Number(personId), shift_id: selected.id }),
    retry: false,
    onSuccess: (response) => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      queryClient.invalidateQueries({ queryKey: ['people'] });
      onAssigned(`${person.name}: ${shiftLabel(selected)}. ${response.data.notification?.queued ? 'De toewijzingsmail staat klaar voor verzending.' : 'Geen e-mailadres beschikbaar; informeer deze persoon zelf.'}`);
    },
    onError: (requestError) => {
      setSaveError(requestError?.response?.data?.message || 'De toewijzing kon niet worden bevestigd. Ververs het overzicht om te controleren of de dienst is opgeslagen.');
      refetch();
    },
  });

  const resetSelection = () => { setShiftId(''); setSaveError(''); };

  return (
    <form
      id={`assignment-${row.unit_id}`}
      onSubmit={(event) => {
        event.preventDefault();
        if (selected && !assignment.isPending) { setSaveError(''); assignment.mutate(); }
      }}
      className="space-y-4 border-t border-gray-200 bg-gray-50 p-4 sm:p-5 dark:border-gray-700 dark:bg-gray-900/50"
    >
      <h3 className="font-semibold text-gray-900 dark:text-gray-100">Dienst toewijzen aan {row.name}</h3>
      <fieldset disabled={assignment.isPending} className="grid gap-4 sm:grid-cols-3">
        <legend className="sr-only">Persoon en periode</legend>
        <div>
          <label htmlFor={`${id}-person`} className="label">Persoon</label>
          <select id={`${id}-person`} className={fieldClass} value={personId} onChange={(event) => { setPersonId(event.target.value); setTask(''); resetSelection(); }} required>
            <option value="">Kies een persoon</option>
            {row.people.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </select>
        </div>
        <div>
          <label htmlFor={`${id}-from`} className="label">Vanaf</label>
          <input id={`${id}-from`} className={fieldClass} type="date" value={from} onChange={(event) => { setFrom(event.target.value); setTask(''); resetSelection(); }} required />
        </div>
        <div>
          <label htmlFor={`${id}-to`} className="label">Tot en met</label>
          <input id={`${id}-to`} className={fieldClass} type="date" min={from} value={to} onChange={(event) => { setTo(event.target.value); setTask(''); resetSelection(); }} required />
        </div>
      </fieldset>
      {row.kind === 'gezin' && <p className="text-sm text-gray-600 dark:text-gray-300">Kies welke ouder de dienst draait. Bij een spelende ouder tellen diensten eerst voor de eigen verplichting, daarna voor het gezin.</p>}
      {!personId ? <p className="text-sm text-gray-600 dark:text-gray-300">Kies een persoon om passende diensten te zien.</p>
        : !validPeriod ? <p role="alert" className="text-sm text-red-700 dark:text-red-300">Kies een geldige begin- en einddatum.</p>
          : isLoading ? <p role="status" className="text-sm text-gray-600 dark:text-gray-300">Passende diensten laden…</p>
            : error ? <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error.response?.data?.message || 'Diensten laden is niet gelukt.'} <button type="button" className="underline" onClick={() => refetch()}>Opnieuw proberen</button></p>
              : shifts.length === 0 ? <p className="text-sm text-gray-600 dark:text-gray-300">Geen passende open diensten in deze periode. Kies een andere periode. VOG, IVA en poolvereisten worden meegenomen.</p>
                : (
                  <fieldset disabled={assignment.isPending} className="space-y-3">
                    <legend className="sr-only">Dienst kiezen</legend>
                    <div className="max-w-sm">
                      <label htmlFor={`${id}-task`} className="label">Soort dienst</label>
                      <select id={`${id}-task`} className={fieldClass} value={task} onChange={(event) => { setTask(event.target.value); resetSelection(); }}>
                        <option value="">Alle passende diensten</option>
                        {tasks.map((name) => <option key={name}>{name}</option>)}
                      </select>
                    </div>
                    <div className="max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                      {visibleShifts.map((shift) => (
                        <label key={shift.id} className="flex cursor-pointer items-start gap-3 border-b border-gray-100 px-4 py-3 last:border-0 hover:bg-cyan-50 dark:border-gray-700 dark:hover:bg-gray-700">
                          <input type="radio" name={`${id}-shift`} value={shift.id} checked={shiftId === String(shift.id)} onChange={(event) => { setShiftId(event.target.value); setSaveError(''); }} className="mt-1 accent-bright-cobalt" />
                          <span className="min-w-0 text-sm">
                            <span className="block font-medium text-gray-900 dark:text-gray-100">{shift.name}</span>
                            <span className="block text-gray-600 dark:text-gray-300">{formatStoredDateTime(shift.start_datetime, 'EEEE d MMMM yyyy, HH:mm')}–{formatStoredDateTime(shift.end_datetime, 'HH:mm')} · {shift.places === null ? 'Plek beschikbaar' : `${shift.places} ${shift.places === 1 ? 'plek' : 'plekken'} vrij`}</span>
                          </span>
                        </label>
                      ))}
                    </div>
                  </fieldset>
                )}
      {selected && (
        <div className="space-y-2 text-sm text-gray-700 dark:text-gray-200">
          <p>Je wijst <strong>{selected.name}</strong> op <strong>{formatStoredDateTime(selected.start_datetime, 'd MMMM, HH:mm')}–{formatStoredDateTime(selected.end_datetime, 'HH:mm')}</strong> toe aan <strong>{person.name}</strong>.</p>
          <p>{person.has_email ? 'Deze persoon krijgt automatisch een toewijzingsmail en blijft verantwoordelijk voor de bezetting. Zelf afmelden is niet mogelijk.' : 'Deze persoon heeft geen e-mailadres. Informeer de persoon zelf over de dienst en de verantwoordelijkheid voor de bezetting.'}</p>
        </div>
      )}
      {saveError && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{saveError}</p>}
      <div className="flex flex-wrap gap-3">
        <button type="submit" disabled={!selected || isFetching || assignment.isPending || Boolean(error)} className="btn-primary disabled:cursor-not-allowed disabled:opacity-50">{assignment.isPending ? 'Toewijzen…' : 'Dienst toewijzen en bevestigen'}</button>
        <button type="button" onClick={onClose} disabled={assignment.isPending} className="btn-secondary">Annuleren</button>
      </div>
    </form>
  );
}

export default function VrijwilligersIndelen() {
  useDocumentTitle('Nog in te delen — Vrijwilligers');
  const [completed, setCompleted] = useState('0');
  const [planning, setPlanning] = useState('none');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [expanded, setExpanded] = useState(null);
  const [feedback, setFeedback] = useState('');
  const { data, isLoading, isFetching, error, refetch } = useQuery({
    queryKey: ['volunteer', 'assignment-overview', completed, planning, search, page],
    queryFn: async () => (await prmApi.getVolunteerAssignmentOverview({ completed, planning, search, page })).data,
    staleTime: 0,
    gcTime: 0,
  });
  const rows = data?.rows ?? [];
  const changeFilter = (setter, value) => { setter(value); setPage(1); setExpanded(null); };
  const changePage = (value) => { setPage(value); setExpanded(null); };

  return (
    <div className="space-y-6">
      <Link to="/vrijwilligers" className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"><ArrowLeft className="h-4 w-4" aria-hidden="true" /> Terug naar vrijwilligers</Link>
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Nog in te delen</h1>
          <p className="mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-300">Leden en gezinnen die nog diensten moeten inplannen{data?.season ? ` in seizoen ${data.season}` : ''}. Een gezinsverplichting staat één keer in dit overzicht.</p>
        </div>
        <button type="button" onClick={() => refetch()} disabled={isFetching} className="btn-secondary inline-flex items-center gap-2"><RefreshCw className={`h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} aria-hidden="true" /> Verversen</button>
      </header>
      <div className="flex flex-wrap items-end gap-4">
        <div>
          <label htmlFor="assignment-completed" className="label">Afgerond</label>
          <select id="assignment-completed" className="input" value={completed} onChange={(event) => changeFilter(setCompleted, event.target.value)}>
            <option value="0">0 diensten afgerond</option><option value="1">1 dienst afgerond</option><option value="all">Alle aantallen</option>
          </select>
        </div>
        <div>
          <label htmlFor="assignment-planning" className="label">Ingepland</label>
          <select id="assignment-planning" className="input" value={planning} onChange={(event) => changeFilter(setPlanning, event.target.value)}>
            <option value="none">Nog niets ingepland</option><option value="all">Met en zonder planning</option><option value="planned">Al iets ingepland</option>
          </select>
        </div>
        <form className="flex w-full min-w-0 items-end gap-2 sm:min-w-64 sm:flex-1" onSubmit={(event) => { event.preventDefault(); changeFilter(setSearch, searchInput.trim()); }}>
          <div className="min-w-0 flex-1"><label htmlFor="assignment-search" className="label">Naam</label><input id="assignment-search" type="search" className={fieldClass} value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder="Zoek een lid of ouder" /></div>
          <button type="submit" className="btn-secondary">Zoeken</button>
        </form>
      </div>
      <p className="text-sm text-gray-600 dark:text-gray-300">‘Nog nodig’ houdt rekening met afgeronde én ingeplande diensten. Vrijgestelde en volledig ingeplande verplichtingen staan niet in deze lijst.</p>
      {feedback && <p role="status" className="rounded-lg bg-green-50 p-4 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-200">{feedback}</p>}
      {isLoading ? <p role="status" className="card p-8 text-center text-gray-600 dark:text-gray-300">Overzicht laden…</p>
        : error ? <div role="alert" className="card p-6 text-red-700 dark:text-red-300">Het overzicht kon niet worden geladen. <button type="button" className="underline" onClick={() => refetch()}>Opnieuw proberen</button></div>
          : rows.length === 0 ? <div className="card p-8 text-center text-gray-600 dark:text-gray-300"><p>Geen leden of gezinnen gevonden met deze filters.</p><button type="button" className="mt-3 underline" onClick={() => { setCompleted('all'); setPlanning('all'); setSearch(''); setSearchInput(''); setPage(1); }}>Alle open verplichtingen bekijken</button></div>
            : (
              <section aria-label="Open verplichtingen" className="card overflow-hidden">
                <div className="border-b border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">{data.total} {data.total === 1 ? 'open verplichting' : 'open verplichtingen'}</div>
                <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                  {rows.map((row) => (
                    <li key={row.unit_id}>
                      <div className="grid items-center gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto] xl:grid-cols-[minmax(0,1fr)_22rem_auto]">
                        <div className="min-w-0">
                          <div className="font-medium text-gray-900 dark:text-gray-100">{row.people.map((person, index) => <Fragment key={person.id}>{index > 0 && ' / '}<Link to={`/people/${person.id}`} className="hover:underline">{person.name}</Link></Fragment>)}</div>
                          <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">{row.kind === 'gezin' ? 'Gezinsverplichting' : 'Eigen verplichting'} · {row.required} diensten vereist</p>
                        </div>
                        <dl className="grid grid-cols-3 gap-4 text-sm sm:row-start-2 xl:col-start-2 xl:row-start-1">
                          {[['Afgerond', row.completed], ['Ingepland', row.planned], ['Nog nodig', row.needed]].map(([label, value]) => <div key={label}><dt className="text-gray-600 dark:text-gray-300">{label}</dt><dd className={`mt-1 tabular-nums ${label === 'Nog nodig' ? 'font-semibold text-bright-cobalt dark:text-electric-cyan' : 'text-gray-900 dark:text-gray-100'}`}>{value}</dd></div>)}
                        </dl>
                        <button type="button" className="btn-secondary inline-flex items-center justify-center gap-2 sm:col-start-2 sm:row-start-1 xl:col-start-3" aria-expanded={expanded === row.unit_id} aria-controls={`assignment-${row.unit_id}`} onClick={() => setExpanded(expanded === row.unit_id ? null : row.unit_id)}><UserRoundPlus className="h-4 w-4" aria-hidden="true" />{expanded === row.unit_id ? 'Sluiten' : 'Dienst toewijzen'}<span className="sr-only"> voor {row.name}</span></button>
                      </div>
                      {expanded === row.unit_id && <AssignmentForm row={row} onClose={() => setExpanded(null)} onAssigned={(message) => { setFeedback(message); setExpanded(null); setPage(1); }} />}
                    </li>
                  ))}
                </ul>
              </section>
            )}
      {data?.total_pages > 1 && <nav aria-label="Pagina’s" className="flex items-center justify-between gap-3 text-sm"><button className="btn-secondary inline-flex items-center gap-1" disabled={page === 1 || isFetching} onClick={() => changePage(page - 1)}><ChevronLeft className="h-4 w-4" aria-hidden="true" /> Vorige</button><span>Pagina {page} van {data.total_pages}</span><button className="btn-secondary inline-flex items-center gap-1" disabled={page >= data.total_pages || isFetching} onClick={() => changePage(page + 1)}>Volgende <ChevronRight className="h-4 w-4" aria-hidden="true" /></button></nav>}
    </div>
  );
}
