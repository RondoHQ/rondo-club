import { useState } from 'react';
import { Link } from 'react-router-dom';
import AccessStats from '@/components/AccessStats';
import { useAccessEvents, useAccessEventStats } from '@/hooks/useAccessEvents';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

function matchLabel(event) {
  const date = event.starts_at ? new Intl.DateTimeFormat('nl-NL', {
    dateStyle: 'medium', timeStyle: 'short',
  }).format(new Date(event.starts_at)) : 'Datum onbekend';
  return `${date} · ${event.home_team} – ${event.away_team}`;
}

export default function AccessStatistics() {
  useDocumentTitle('Toegangsstatistieken');
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState(null);
  const eventsQuery = useAccessEvents(page);
  const events = eventsQuery.data?.events || [];
  const selectedEvent = events.find((event) => event.id === selectedId) || events[0];
  const statsQuery = useAccessEventStats(selectedEvent?.id);

  function changePage(nextPage) {
    setSelectedId(null);
    setPage(nextPage);
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">Toegangsstatistieken</h1>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Bekijk de gescande toegangspassen per wedstrijd, tijdens en na afloop.
          </p>
        </div>
        <Link to="/lidpas-scanner" className="btn-secondary">Lidpas Scanner</Link>
      </div>
      {eventsQuery.isError ? (
        <p role="alert" className="card p-5 text-red-600 dark:text-red-400">
          Wedstrijden konden niet worden vernieuwd. <button type="button" className="underline" onClick={() => eventsQuery.refetch()}>Opnieuw proberen</button>
        </p>
      ) : null}
      {eventsQuery.isLoading ? <p>Wedstrijden laden…</p> : null}
      {!eventsQuery.isLoading && !eventsQuery.isError && events.length === 0 ? (
        <p className="card p-5 text-gray-600 dark:text-gray-400">
          Nog geen wedstrijden beschikbaar. Een wedstrijd verschijnt hier zodra deze in de Lidpas Scanner is geselecteerd.
        </p>
      ) : null}
      {selectedEvent ? (
        <>
          <div className="card p-5 space-y-3">
            <label htmlFor="access-match" className="block font-semibold">Wedstrijd</label>
            <select id="access-match" className="input w-full" value={selectedEvent.id} onChange={(event) => setSelectedId(Number(event.target.value))}>
              {events.map((event) => <option key={event.id} value={event.id}>{matchLabel(event)}</option>)}
            </select>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              {selectedEvent.pitch || selectedEvent.location}
              {selectedEvent.cancelled ? ' · Afgelast' : ''}
            </p>
          </div>
          {statsQuery.isError ? (
            <p role="alert" className="card p-5 text-red-600 dark:text-red-400">
              De telling kon niet worden vernieuwd. <button type="button" className="underline" onClick={() => statsQuery.refetch()}>Opnieuw proberen</button>
            </p>
          ) : <AccessStats stats={statsQuery.data} isLoading={statsQuery.isLoading} />}
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Automatisch bijgewerkt elke 5 seconden. Dubbele scans tellen niet mee.
          </p>
        </>
      ) : null}
      {page > 1 || eventsQuery.data?.total_pages > 1 ? (
        <nav aria-label="Wedstrijdarchief" className="flex items-center gap-3">
          <button type="button" className="btn-secondary" disabled={page <= 1 || eventsQuery.isLoading} onClick={() => changePage(page - 1)}>Nieuwere wedstrijden</button>
          <span>Pagina {page}</span>
          <button type="button" className="btn-secondary" disabled={eventsQuery.isLoading || eventsQuery.isError || page >= (eventsQuery.data?.total_pages || 1)} onClick={() => changePage(page + 1)}>Oudere wedstrijden</button>
        </nav>
      ) : null}
    </div>
  );
}
