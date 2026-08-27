import { Link } from 'react-router-dom';
import { CalendarDays, CheckCircle2, ClipboardList } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useMyTournamentEntries } from '@/hooks/useTournaments';
import { formatTournamentDate } from './tournamentFormatters';

export default function MyTournaments() {
  useDocumentTitle('Mijn toernooien');
  const { data: entries = [], isLoading, error } = useMyTournamentEntries();

  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mijn toernooien</h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Gedeelde inschrijfopdrachten van de teams waarvoor je kaderlid bent.
        </p>
      </div>

      {error ? <div className="card p-6 text-sm text-red-600 dark:text-red-400">Je toernooien konden niet worden geladen.</div> : null}

      {!error && entries.length === 0 ? (
        <div className="card p-10 text-center">
          <ClipboardList className="mx-auto h-10 w-10 text-gray-400" />
          <h2 className="mt-3 font-semibold text-gray-900 dark:text-gray-100">Geen openstaande toernooien</h2>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Er zijn nog geen toernooien waarvoor je je hier kunt inschrijven.</p>
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        {entries.map((entry) => {
          const submitted = entry.registration_status === 'submitted';
          return (
            <Link
              key={entry.id}
              to={`/mijn-toernooien/${entry.id}`}
              className="card block p-5 transition-colors hover:border-electric-cyan dark:hover:border-electric-cyan"
            >
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h2 className="font-semibold text-gray-900 dark:text-gray-100">{entry.tournament.name}</h2>
                  <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{entry.team_name}</p>
                </div>
                <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${submitted ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'}`}>
                  {submitted ? <CheckCircle2 className="mr-1 h-3.5 w-3.5" /> : null}
                  {submitted ? 'Ingeschreven' : 'Niet ingeschreven'}
                </span>
              </div>
              <div className="mt-4 flex items-center text-sm text-gray-600 dark:text-gray-400">
                <CalendarDays className="mr-2 h-4 w-4" />
                Deadline: {formatTournamentDate(entry.tournament.internal_deadline)}
              </div>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
