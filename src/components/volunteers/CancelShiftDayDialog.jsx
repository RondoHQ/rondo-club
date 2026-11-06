import { useEffect, useRef, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { prmApi } from '@/api/client';
import { format, formatStoredDateTime } from '@/utils/dateFormat';
import { decodeHtml } from '@/utils/formatters';
import { cancelShiftDay, retryableCancellations } from '@/utils/cancelShiftDay';
import { refreshShiftCalendars } from '@/utils/shiftQueryCache';

export default function CancelShiftDayDialog({ date, onClose }) {
  const dialogRef = useRef(null);
  const client = useQueryClient();
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [results, setResults] = useState(null);
  const [progress, setProgress] = useState(null);
  const query = useQuery({
    queryKey: ['volunteer', 'cancel-day-preview', date],
    queryFn: async () => (await prmApi.getShiftCalendar({ view: 'manage', from: date, to: date })).data,
    staleTime: 0,
    refetchOnWindowFocus: false,
  });
  const shifts = (query.data?.days?.find((day) => day.date === date)?.shifts || []).filter((shift) => shift.status !== 'voltooid');
  const retries = results ? retryableCancellations(results) : [];
  const cancelled = results?.filter((result) => result.cancellation).length || 0;

  useEffect(() => {
    const dialog = dialogRef.current;
    const previousFocus = document.activeElement;
    dialog.showModal();
    return () => { dialog.close(); previousFocus?.focus(); };
  }, []);

  const submit = async () => {
    if (busy) return;
    const targets = results ? retries : shifts;
    setBusy(true);
    setProgress({ done: 0, total: targets.length });
    try {
      const updated = await cancelShiftDay(targets, reason, prmApi.cancelDienstShift, (done) => setProgress({ done: done.length, total: targets.length }));
      const changed = new Map(updated.map((result) => [result.shift.id, result]));
      setResults((previous) => previous ? previous.map((result) => changed.get(result.shift.id) || result) : updated);
      await Promise.all([
        refreshShiftCalendars(client),
        client.invalidateQueries({ queryKey: ['volunteer'] }),
        client.invalidateQueries({ queryKey: ['people'] }),
      ]);
    } finally {
      setBusy(false);
    }
  };

  return (
    <dialog ref={dialogRef} onCancel={(event) => { event.preventDefault(); if (!busy) onClose(); }} aria-labelledby="cancel-day-title" aria-describedby="cancel-day-policy" className="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-lg bg-white p-5 text-gray-900 shadow-xl backdrop:bg-black/50 dark:bg-gray-800 dark:text-gray-100">
      <h2 id="cancel-day-title" className="text-lg font-semibold">Dag leegmaken</h2>
      <p className="mt-1 text-sm capitalize">{format(`${date}T12:00:00`, 'EEEE d MMMM yyyy')}</p>
      <p id="cancel-day-policy" className="mt-4 text-sm text-gray-600 dark:text-gray-300">Alle onderstaande inschrijftaken worden geannuleerd. Ingeplande personen ontvangen een annuleringsmail. Bij minder dan 48 uur voor aanvang telt de dienst mee voor hun vrijwilligersplicht; vanaf 48 uur telt deze niet mee. De historie blijft bewaard. Al begonnen of voltooide diensten worden niet geannuleerd.</p>
      {query.isPending && <p role="status" className="mt-4">Dagplanning ophalen…</p>}
      {query.error && <p role="alert" className="mt-4 text-red-700 dark:text-red-300">Dagplanning ophalen mislukt. <button type="button" className="underline" onClick={() => query.refetch()}>Opnieuw proberen</button></p>}
      {!results && !query.isPending && !query.error && <>
        <p className="mt-4 text-sm font-medium">{shifts.length} inschrijftaken te annuleren</p>
        <ul className="mt-2 max-h-48 space-y-2 overflow-y-auto text-sm">
          {shifts.map((shift) => <li key={shift.id}>{formatStoredDateTime(shift.start_datetime, 'HH:mm')}–{formatStoredDateTime(shift.end_datetime, 'HH:mm')} · {decodeHtml(shift.dienst_type_name || shift.title)} · {shift.assigned_count} ingedeeld</li>)}
        </ul>
        <label className="label mt-4" htmlFor="cancel-day-reason">Reden (optioneel, komt in de e-mail)</label>
        <textarea id="cancel-day-reason" className="input mt-1 w-full" rows={2} value={reason} disabled={busy} onChange={(event) => setReason(event.target.value)} />
      </>}
      {busy && <p role="status" className="mt-4 text-sm">{progress.done} van {progress.total} inschrijftaken verwerkt. Houd dit venster open.</p>}
      {results && <div className="mt-4 space-y-3">
        <p role="status" className="font-medium">{cancelled} van {results.length} inschrijftaken geannuleerd.</p>
        {results.map(({ shift, cancellation, error }) => {
          const notifications = cancellation?.notifications;
          if (!error && !notifications?.failed && !notifications?.no_email) return null;
          return <div key={shift.id} role="alert" className="text-sm text-amber-800 dark:text-amber-300">
            <Link className="underline" to={`/vrijwilligers/diensten/${shift.id}`}>{decodeHtml(shift.dienst_type_name || shift.title)} · {formatStoredDateTime(shift.start_datetime, 'HH:mm')}</Link>: {error || 'Dienst geannuleerd.'}
            {notifications?.failed > 0 && <p>{notifications.failed} e-mails niet verzonden. Probeer de mislukte onderdelen opnieuw.</p>}
            {notifications?.no_email > 0 && <p>{notifications.no_email} {notifications.no_email === 1 ? 'persoon heeft' : 'personen hebben'} geen e-mailadres. Neem zelf contact met hen op.</p>}
          </div>;
        })}
      </div>}
      <div className="mt-5 flex flex-wrap justify-end gap-2">
        <button type="button" className="btn-secondary" autoFocus disabled={busy} onClick={onClose}>{results ? 'Sluiten' : 'Terug'}</button>
        {(!results || retries.length > 0) && <button type="button" className="btn-danger" disabled={busy || query.isPending || !!query.error || (!results && !shifts.length)} onClick={submit}>{busy ? 'Bezig…' : results ? 'Mislukte onderdelen opnieuw proberen' : `Ja, ${shifts.length} inschrijftaken annuleren`}</button>}
      </div>
    </dialog>
  );
}
