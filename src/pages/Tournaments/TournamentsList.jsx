import { Link } from 'react-router-dom';
import { CalendarDays, Plus, Trophy } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useTournaments } from '@/hooks/useTournaments';
import { formatTournamentCurrency, formatTournamentDate, tournamentStatusLabel } from './tournamentFormatters';

export default function TournamentsList() {
  useDocumentTitle('Toernooien');
  const { data: tournaments = [], isLoading, error } = useTournaments();

  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Toernooien</h1>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Organiseer teaminschrijvingen vanuit de actuele kaderbezetting.
          </p>
        </div>
        <Link to="/toernooien/nieuw" className="btn-primary inline-flex items-center justify-center">
          <Plus className="mr-2 h-4 w-4" />
          Toernooi toevoegen
        </Link>
      </div>

      {error ? (
        <div className="card p-6 text-sm text-red-600 dark:text-red-400">
          De toernooien konden niet worden geladen.
        </div>
      ) : null}

      {!error && tournaments.length === 0 ? (
        <div className="card p-10 text-center">
          <Trophy className="mx-auto h-10 w-10 text-gray-400" />
          <h2 className="mt-3 font-semibold text-gray-900 dark:text-gray-100">Nog geen toernooien</h2>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Maak het eerste toernooi aan om teams uit te nodigen.</p>
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        {tournaments.map((tournament) => (
          <Link
            key={tournament.id}
            to={`/toernooien/${tournament.id}`}
            className="card block p-5 transition-colors hover:border-electric-cyan dark:hover:border-electric-cyan"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <h2 className="font-semibold text-gray-900 dark:text-gray-100">{tournament.name}</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{tournament.organizer || tournament.location || 'Geen organisator ingesteld'}</p>
              </div>
              <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                {tournamentStatusLabel(tournament.lifecycle_status)}
              </span>
            </div>
            <div className="mt-4 flex items-center text-sm text-gray-600 dark:text-gray-400">
              <CalendarDays className="mr-2 h-4 w-4" />
              Interne deadline: {formatTournamentDate(tournament.internal_deadline)}
            </div>
            <div className="mt-4 grid grid-cols-2 gap-3 border-t border-gray-200 pt-4 text-center sm:grid-cols-4 dark:border-gray-700">
              <div><div className="text-lg font-semibold text-gray-900 dark:text-gray-100">{tournament.submitted_entry_count || 0}/{tournament.entry_count || 0}</div><div className="text-xs text-gray-500">Ingeschreven</div></div>
              <div><div className="text-lg font-semibold text-gray-900 dark:text-gray-100">{tournament.registered_team_count || 0}</div><div className="text-xs text-gray-500">Toernooiteams</div></div>
              <div><div className="text-lg font-semibold text-gray-900 dark:text-gray-100">{tournament.player_count || 0}</div><div className="text-xs text-gray-500">Spelers</div></div>
              <div><div className="text-lg font-semibold text-gray-900 dark:text-gray-100">{formatTournamentCurrency(tournament.outstanding_amount)}</div><div className="text-xs text-gray-500">Openstaand</div></div>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
