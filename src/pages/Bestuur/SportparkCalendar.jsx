import { useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useSportparkClosures, useSaveSportparkClosure, useDeleteSportparkClosure } from '@/hooks/useSportparkClosures';
import { calendarDate, closuresOnDate, monthDays } from '@/utils/sportparkCalendar';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';

const months = ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'];
const weekdays = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo'];
const emptyClosures = [];
const dateLabel = (date) => date.split('-').reverse().join('-');

export default function SportparkCalendar() {
  const [year, setYear] = useState(() => new Date().getFullYear());
  const [editing, setEditing] = useState(null);
  const [confirmation, setConfirmation] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [selectedDate, setSelectedDate] = useState(null);
  const periodsRef = useRef(null);
  const [message, setMessage] = useState('');
  const editorRef = useRef(null);
  const confirmationRef = useRef(null);
  const { data: closures = emptyClosures, isLoading, isError, refetch } = useSportparkClosures(year);
  const save = useSaveSportparkClosure();
  const remove = useDeleteSportparkClosure();
  const busy = save.isPending || remove.isPending;

  function openEditor(closure) {
    save.reset();
    remove.reset();
    setMessage('');
    setConfirmation(null);
    setDeleting(null);
    setSelectedDate(null);
    setEditing(closure);
    requestAnimationFrame(() => editorRef.current?.focus());
  }

  function newClosure(date = calendarDate(year, 0, 1)) {
    openEditor({ title: '', description: '', fields: { starts_at: date, ends_at: date } });
  }

  function change(key, value) {
    setConfirmation(null);
    save.reset();
    setEditing((previous) => key === 'starts_at' || key === 'ends_at'
      ? { ...previous, fields: { ...previous.fields, [key]: value } }
      : { ...previous, [key]: value });
  }

  async function submit(existingTasks) {
    try {
      const result = await save.mutateAsync({
        id: editing.id,
        title: editing.title,
        description: editing.description || null,
        fields: editing.fields,
        ...(existingTasks ? { existing_tasks: existingTasks, conflict_token: confirmation.conflict_token } : {}),
      });
      setEditing(null);
      setConfirmation(null);
      if (Number(editing.fields.starts_at.slice(0, 4)) > year || Number(editing.fields.ends_at.slice(0, 4)) < year) setYear(Number(editing.fields.starts_at.slice(0, 4)));
      const notificationWarning = result.notification_warnings?.length ? ' Niet alle vrijwilligers konden per e-mail worden bereikt; controleer de geannuleerde taken.' : '';
      setMessage((result.failed.length
        ? `Sluiting opgeslagen, maar ${result.failed.length} taak/taken konden niet worden geannuleerd: ${result.failed.map((failure) => `#${failure.id}: ${failure.message}`).join(' ')}`
        : result.cancelled.length ? `Sluiting opgeslagen; ${result.cancelled.length} ${result.cancelled.length === 1 ? 'inschrijftaak' : 'inschrijftaken'} geannuleerd.` : 'Sluiting opgeslagen.') + notificationWarning);
    } catch (error) {
      if (error.response?.data?.code === 'closure_conflicts') {
        setConfirmation(error.response.data.data);
        requestAnimationFrame(() => confirmationRef.current?.focus());
      }
    }
  }

  async function deleteClosure() {
    try {
      await remove.mutateAsync(deleting.id);
      setDeleting(null);
      setMessage('Sluiting verwijderd. Geannuleerde taken blijven geannuleerd.');
    } catch { /* The mutation error is displayed below. */ }
  }

  const cancelCount = confirmation?.conflicts.filter((task) => task.can_cancel).length ?? 0;
  const error = save.error?.response?.data?.code === 'closure_conflicts' ? null : save.error;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-50">Sportparkkalender</h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Op gesloten dagen worden geen inschrijftaken uit sjablonen aangemaakt.</p>
        </div>
        <button className="btn-primary" onClick={() => newClosure()} disabled={busy}><Plus className="mr-2 h-4 w-4" aria-hidden="true" />Sluiting toevoegen</button>
      </div>

      {message && <p role="status" className="text-sm text-gray-700 dark:text-gray-200">{message}</p>}
      {remove.error && <p role="alert" className="text-red-700 dark:text-red-300">{remove.error.response?.data?.message || 'Verwijderen mislukt. Probeer het opnieuw.'}</p>}

      {editing && (
        <form className="card space-y-4 p-5" onSubmit={(event) => { event.preventDefault(); submit(); }}>
          <h2 ref={editorRef} tabIndex={-1} className="text-lg font-semibold">{editing.id ? 'Sluiting wijzigen' : 'Sluiting toevoegen'}</h2>
          <fieldset disabled={busy} className="space-y-4">
            <div><label className="mb-1 block text-sm font-medium" htmlFor="closure-title">Titel</label><input id="closure-title" className="input w-full" required value={editing.title} onChange={(event) => change('title', event.target.value)} /></div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div><label className="mb-1 block text-sm font-medium" htmlFor="closure-start">Van</label><input id="closure-start" type="date" min="1000-01-01" max="9998-12-31" className="input w-full" required value={editing.fields.starts_at} onChange={(event) => change('starts_at', event.target.value)} /></div>
              <div><label className="mb-1 block text-sm font-medium" htmlFor="closure-end">Tot en met</label><input id="closure-end" type="date" min={editing.fields.starts_at} max="9998-12-31" className="input w-full" required value={editing.fields.ends_at} onChange={(event) => change('ends_at', event.target.value)} /></div>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">De begin- en einddatum tellen allebei mee. Voor één dag kies je tweemaal dezelfde datum.</p>
            <div><label className="mb-1 block text-sm font-medium" htmlFor="closure-description">Omschrijving (optioneel)</label><textarea id="closure-description" className="input w-full" rows={3} value={editing.description || ''} onChange={(event) => change('description', event.target.value)} /></div>
          </fieldset>
          {error && <p role="alert" className="text-red-700 dark:text-red-300">{error.response?.data?.message || 'Opslaan mislukt. Probeer het opnieuw.'}</p>}
          {confirmation ? (
            <section ref={confirmationRef} tabIndex={-1} aria-labelledby="closure-conflicts-title" className="space-y-3 rounded-lg bg-amber-50 p-4 text-amber-950 dark:bg-amber-950 dark:text-amber-100">
              <h3 id="closure-conflicts-title" className="font-semibold">{confirmation.conflicts.length === 1 ? 'Er bestaat 1 inschrijftaak' : `Er bestaan ${confirmation.conflicts.length} inschrijftaken`} in deze periode</h3>
              <ul className="max-h-64 list-disc space-y-1 overflow-auto pl-5 text-sm">
                {confirmation.conflicts.map((task) => <li key={task.id}>{task.title} · {dateLabel(task.starts_at.slice(0, 10))} · {task.assigned_count} ingeschreven{!task.can_cancel ? ' · al begonnen of voltooid, blijft behouden' : ''}</li>)}
              </ul>
              <p className="text-sm">Bij annuleren blijven inschrijvingen bewaard en krijgen ingeschreven vrijwilligers bericht volgens de bestaande annuleringsregels.</p>
              <div className="flex flex-wrap gap-3">
                <button type="button" className="btn-primary" disabled={busy} onClick={() => submit('keep')}>Opslaan en taken behouden</button>
                {cancelCount > 0 && <button type="button" className="btn-secondary" disabled={busy} onClick={() => submit('cancel')}>Opslaan en {cancelCount} {cancelCount === 1 ? 'taak' : 'taken'} annuleren</button>}
              </div>
            </section>
          ) : <button className="btn-primary" disabled={busy} type="submit">{save.isPending ? 'Opslaan…' : 'Sluiting opslaan'}</button>}
          <button type="button" className="btn-tertiary ml-3" disabled={busy} onClick={() => { setEditing(null); setConfirmation(null); }}>Annuleren</button>
        </form>
      )}

      {deleting && <section className="card space-y-3 p-5" aria-label="Sluiting verwijderen">
        <p>Sluiting “{deleting.title}” verwijderen? Deze dagen kunnen daarna weer automatisch worden ingepland; geannuleerde taken worden niet heropend.</p>
        <div className="flex gap-3"><button className="btn-primary" disabled={busy} onClick={deleteClosure}>Sluiting verwijderen</button><button className="btn-tertiary" disabled={busy} onClick={() => setDeleting(null)}>Behouden</button></div>
      </section>}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <button className="btn-tertiary" aria-label="Vorig jaar" disabled={year <= 1000} onClick={() => { setYear(year - 1); setSelectedDate(null); }}><ChevronLeft className="h-5 w-5" /></button>
          <h2 className="text-xl font-semibold tabular-nums" aria-live="polite">{year}</h2>
          <button className="btn-tertiary" aria-label="Volgend jaar" disabled={year >= 9998} onClick={() => { setYear(year + 1); setSelectedDate(null); }}><ChevronRight className="h-5 w-5" /></button>
        </div>
        <p className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"><span className="h-3 w-3 rounded-sm bg-amber-300 dark:bg-amber-800" aria-hidden="true" />Gemarkeerd = sportpark gesloten</p>
      </div>
      {isLoading ? <ContentLoadingSpinner /> : isError ? (
        <div role="alert">De kalender kon niet worden geladen. <button className="btn-secondary" onClick={() => refetch()}>Opnieuw proberen</button></div>
      ) : <>
        <div className="grid gap-x-6 gap-y-8 sm:grid-cols-2 xl:grid-cols-3">
          {months.map((month, index) => <section key={month} aria-label={`${month} ${year}`}>
            <h3 className="mb-3 font-semibold">{month}</h3>
            <div className="grid grid-cols-7 text-center text-sm">
              {weekdays.map((day) => <span key={day} className="py-2 text-xs text-gray-500 dark:text-gray-400">{day}</span>)}
              {monthDays(year, index).map((date, cell) => {
                if (!date) return <span key={`empty-${cell}`} />;
                const periods = closuresOnDate(date, closures);
                return <button key={date} disabled={busy} aria-label={`${dateLabel(date)}${periods.length ? `, gesloten: ${periods.map((period) => period.title).join(', ')}` : ', sluiting toevoegen'}`} title={periods.map((period) => period.title).join(', ')} onClick={() => {
                  if (periods.length === 1) openEditor(periods[0]);
                  else if (periods.length > 1) {
                    setSelectedDate(date);
                    requestAnimationFrame(() => periodsRef.current?.focus());
                  } else newClosure(date);
                }} className={`min-h-11 rounded-sm tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric-cyan ${periods.length ? 'bg-amber-100 font-semibold underline decoration-amber-700 underline-offset-4 hover:bg-amber-200 dark:bg-amber-900 dark:hover:bg-amber-800' : 'hover:bg-gray-100 dark:hover:bg-gray-800'}`}>{Number(date.slice(-2))}</button>;
              })}
            </div>
          </section>)}
        </div>
        <section className="space-y-3">
          <h2 ref={periodsRef} tabIndex={-1} className="text-lg font-semibold">{selectedDate ? `Sluitingsperiodes op ${dateLabel(selectedDate)}` : `Sluitingsperiodes in ${year}`}</h2>
          {selectedDate && <button className="btn-tertiary" onClick={() => setSelectedDate(null)}>Alle sluitingsperiodes tonen</button>}
          {closures.length === 0 ? <p className="text-sm text-gray-600 dark:text-gray-400">Er zijn nog geen sluitingen voor dit jaar. Klik op een dag of voeg een sluiting toe.</p> : <ul className="divide-y divide-gray-200 dark:divide-gray-700">
            {(selectedDate ? closuresOnDate(selectedDate, closures) : closures).map((closure) => <li key={closure.id} className="flex flex-wrap items-center justify-between gap-3 py-4">
              <div><p className="font-medium">{closure.title}</p><p className="text-sm text-gray-600 dark:text-gray-400">{dateLabel(closure.fields.starts_at)} t/m {dateLabel(closure.fields.ends_at)}</p>{closure.description && <p className="mt-1 whitespace-pre-wrap text-sm">{closure.description}</p>}</div>
              <div className="flex gap-2"><button className="btn-secondary" disabled={busy} onClick={() => openEditor(closure)}>Wijzigen<span className="sr-only">: {closure.title}</span></button><button className="btn-tertiary" disabled={busy} onClick={() => { setDeleting(closure); setEditing(null); remove.reset(); window.scrollTo({ top: 0, behavior: 'smooth' }); }}>Verwijderen<span className="sr-only">: {closure.title}</span></button></div>
            </li>)}
          </ul>}
        </section>
      </>}
    </div>
  );
}
