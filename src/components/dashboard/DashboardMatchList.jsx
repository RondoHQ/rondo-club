import { useState } from 'react';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { format, parseYmd } from '@/utils/dateFormat';
import { dashboardRoomLabel, getDashboardMatchPage } from '@/utils/roleDashboard';

const dateLabel = value => format(parseYmd(value), 'EEEE d MMMM');

/** Filters and pagination keep a busy club week readable without dropping fixtures. */
export default function DashboardMatchList({ matches, incomplete, loading, noTeams }) {
  const [date, setDate] = useState('all');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(0);
  const result = getDashboardMatchPage(matches, { date, query, page });
  const days = [...new Set(matches.map(match => match.date))];
  const changeFilter = setter => event => { setter(event.target.value); setPage(0); };

  return <>
    {matches.length > 0 && <div className="dashboard-match-filters">
      <label>Dag<select value={date} onChange={changeFilter(setDate)}>
        <option value="all">Hele week · {matches.length}</option>
        {days.map(day => <option key={day} value={day}>{dateLabel(day)} · {matches.filter(match => match.date === day).length}</option>)}
      </select></label>
      <label>Zoek een team<span className="dashboard-match-search"><Search size={16} aria-hidden="true" /><input type="search" placeholder="Bijvoorbeeld JO13 of tegenstander" value={query} onChange={changeFilter(setQuery)} /></span></label>
    </div>}
    <div id="dashboard-match-results">
      {result.items.map((match, index) => <div key={match.id}>
        {(!index || result.items[index - 1].date !== match.date) && <h3 className="dashboard-match-day">{dateLabel(match.date)}</h3>}
        <details className="dashboard-match-row">
          <summary>
            <span className="dashboard-match-time">{match.time_known === false ? 'N.t.b.' : match.time}</span>
            <span className="dashboard-match-teams"><span>{match.home_team}</span><span>{match.away_team}</span></span>
            <span className={`dashboard-match-location${match.cancelled ? ' is-cancelled' : ''}`}>{match.cancelled ? 'Afgelast' : match.result || match.pitch || 'Veld onbekend'}<small>{match.result && !match.cancelled ? 'Gespeeld' : match.location || (match.home === false || match.club_side === 'away' ? 'Uitwedstrijd' : 'Thuiswedstrijd')}</small></span>
            <ChevronRight className="dashboard-match-chevron" size={16} aria-hidden="true" />
          </summary>
          <dl><dt>Veld</dt><dd>{match.pitch || 'Nog niet ingevuld'}</dd><dt>Locatie</dt><dd>{match.location || 'Nog niet ingevuld'}</dd>{match.result && <><dt>Uitslag</dt><dd>{match.result}</dd></>}<dt>Kleedkamer thuis</dt><dd>{dashboardRoomLabel(match.dressing_rooms?.home)}</dd><dt>Kleedkamer uit</dt><dd>{dashboardRoomLabel(match.dressing_rooms?.away)}</dd><dt>Status</dt><dd>{match.cancelled ? 'Afgelast' : match.status || 'Gepland'}</dd></dl>
        </details>
      </div>)}
      {!loading && !result.total && <p className="py-4 text-sm text-gray-500 dark:text-gray-400">{matches.length ? 'Geen wedstrijden voor deze selectie.' : !incomplete ? (noTeams ? 'Er zijn nog geen teams aan je coördinatorrol gekoppeld.' : 'Geen wedstrijden in dit overzicht.') : 'Er zijn momenteel geen wedstrijden beschikbaar.'}</p>}
    </div>
    {matches.length > 0 && <div className="dashboard-match-pagination">
      <p role="status">{result.total ? `${result.start + 1}–${result.start + result.items.length} van ${result.total} wedstrijden` : '0 wedstrijden'}{incomplete ? ' · mogelijk onvolledig' : ''}</p>
      {result.pages > 1 && <nav aria-label="Wedstrijdpagina’s">
        <button type="button" aria-label="Vorige wedstrijdpagina" aria-controls="dashboard-match-results" disabled={result.page === 0} onClick={() => setPage(result.page - 1)}><ChevronLeft size={16} /></button>
        <span>{result.page + 1} / {result.pages}</span>
        <button type="button" aria-label="Volgende wedstrijdpagina" aria-controls="dashboard-match-results" disabled={result.page + 1 >= result.pages} onClick={() => setPage(result.page + 1)}><ChevronRight size={16} /></button>
      </nav>}
    </div>}
  </>;
}
