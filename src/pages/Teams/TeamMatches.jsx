import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { CalendarPlus, Copy, Check } from 'lucide-react';
import { prmApi } from '@/api/client';

const dateFormatter = new Intl.DateTimeFormat('nl-NL', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Europe/Amsterdam' });

export default function TeamMatches({ teamId }) {
  const [copied, setCopied] = useState(false);
  const [copyError, setCopyError] = useState(false);
  const [filter, setFilter] = useState('all');
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['team-matches', teamId],
    queryFn: async () => (await prmApi.getTeamMatches(teamId)).data,
    staleTime: 5 * 60 * 1000,
  });

  async function copyCalendar() {
    try {
      await navigator.clipboard.writeText(data.calendar_url);
      setCopied(true);
      setCopyError(false);
    } catch {
      setCopyError(true);
    }
  }

  if (isLoading) return <div className="card p-6 text-gray-500" role="status">Wedstrijden ophalen…</div>;
  if (error) return <div className="card p-6 space-y-3"><p role="alert">{error.response?.data?.message || 'Wedstrijden konden niet worden geladen.'}</p><button className="btn-secondary" onClick={() => refetch()}>Opnieuw proberen</button></div>;

  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Amsterdam' }).format(new Date());
  const matches = data.matches.filter((match) => filter === 'all' || (filter === 'upcoming' ? match.date >= today : match.date < today));

  return (
    <div className="card p-4 sm:p-6 space-y-5">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div><h2 className="font-semibold text-brand-gradient">Wedstrijden {data.season.replace('-', '–')}</h2><p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Thuis- en uitwedstrijden van het hele seizoen, zodra Sportlink ze publiceert.</p></div>
        <div className="flex flex-wrap gap-2 shrink-0">
          <a href={data.calendar_url.replace(/^https?:/, 'webcal:')} className="btn-secondary"><CalendarPlus className="w-4 h-4 mr-2" />Abonneren op agenda</a>
          <button onClick={copyCalendar} className="btn-tertiary">{copied ? <Check className="w-4 h-4 mr-2" /> : <Copy className="w-4 h-4 mr-2" />}{copied ? 'ICS-link gekopieerd' : 'Kopieer ICS-link'}</button>
        </div>
      </div>
      <p className="text-sm text-gray-500 dark:text-gray-400">Voeg de ICS-link als agenda-abonnement toe om wijzigingen te ontvangen. Je agenda-app bepaalt hoe snel die zichtbaar worden.</p>
      {copyError && <label className="block text-sm">Kopiëren lukte niet. Kopieer deze link:<input aria-label="ICS-link" readOnly value={data.calendar_url} onFocus={(event) => event.target.select()} className="input mt-2 w-full" /></label>}
      {data.stale && <p role="status" className="text-sm text-amber-700 dark:text-amber-300">Sportlink is tijdelijk niet bereikbaar. Je ziet de laatst opgehaalde wedstrijden.</p>}
      {!data.matched ? <p className="text-sm text-gray-500 dark:text-gray-400">Dit team staat niet eenduidig in het huidige Sportlink-wedstrijdprogramma. De agenda is beschikbaar zodra Sportlink het team kan koppelen.</p> : <>
        <div className="flex flex-wrap gap-2" aria-label="Wedstrijden filteren">
          {[['all', 'Hele seizoen'], ['upcoming', 'Komend'], ['played', 'Eerder']].map(([value, label]) => <button key={value} aria-pressed={filter === value} onClick={() => setFilter(value)} className={filter === value ? 'btn-secondary' : 'btn-tertiary'}>{label}</button>)}
        </div>
        {matches.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Geen wedstrijden in deze periode.</p> : <ul className="divide-y divide-gray-200 dark:divide-gray-700">
          {matches.map((match) => <li key={match.id} className="py-4 first:pt-0 last:pb-0 flex flex-col sm:flex-row gap-2 sm:gap-4">
            <div className="sm:w-40 shrink-0 text-sm"><p className="font-medium">{dateFormatter.format(new Date(match.starts_at))}</p><p className="text-gray-500 dark:text-gray-400">{match.time_known ? match.time : 'Tijd nog onbekend'} · {match.home ? 'Thuis' : 'Uit'}</p></div>
            <div className="min-w-0 flex-1"><p className={`font-medium break-words ${match.cancelled ? 'line-through text-gray-500' : ''}`}>{match.home_team} - {match.away_team}</p><p className="text-sm text-gray-500 dark:text-gray-400 break-words">{[match.location, match.pitch].filter(Boolean).join(' · ')}</p></div>
            <div className="sm:text-right text-sm shrink-0">{match.cancelled ? <span className="text-red-600 dark:text-red-400 font-medium">{match.status || 'Afgelast'}</span> : match.result ? <span className="font-bold text-lg">{match.result}</span> : <span className="text-gray-500 dark:text-gray-400">{match.date < today ? 'Geen uitslag bekend' : match.status || 'Te spelen'}</span>}</div>
          </li>)}
        </ul>}
      </>}
      {data.updated_at && <p className="text-xs text-gray-500 dark:text-gray-400">Bijgewerkt: {new Intl.DateTimeFormat('nl-NL', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Europe/Amsterdam' }).format(new Date(data.updated_at))}</p>}
    </div>
  );
}
