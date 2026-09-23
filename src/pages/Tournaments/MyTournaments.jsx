import { Link } from 'react-router-dom';
import { CalendarDays, CheckCircle2, ClipboardList, Mail, Phone } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';
import { useMyTournamentEntries, useTournamentCoordinators } from '@/hooks/useTournaments';
import { formatTournamentDate, tournamentPaymentStatus, tournamentPaymentToneClasses } from './tournamentFormatters';

export default function MyTournaments() {
  useDocumentTitle('Toernooien');
  const { data: user } = useCurrentUser();
  const { data: entries = [], isLoading, error } = useMyTournamentEntries();

  const isEmpty = !isLoading && !error && entries.length === 0;
  const {
    data: coordinators = [],
    isLoading: coordinatorsLoading,
    error: coordinatorsError,
    refetch: refetchCoordinators,
  } = useTournamentCoordinators(isEmpty);

  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Toernooien</h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Gedeelde inschrijfopdrachten van de teams waarvoor je kaderlid bent.
        </p>
      </div>

      {user?.can_manage_tournaments || user?.can_access_financieel ? (
        <div className="flex flex-wrap gap-3">
          <Link to="/toernooien/betalingen" className="btn-secondary">Toernooibetalingen</Link>
          {user?.can_manage_tournaments ? <Link to="/toernooien" className="btn-tertiary">Toernooien beheren</Link> : null}
        </div>
      ) : null}

      {error ? <div className="card p-6 text-sm text-red-600 dark:text-red-400">Je toernooien konden niet worden geladen.</div> : null}

      {isEmpty ? (
        <div className="card px-5 py-8 text-center sm:p-10">
          <ClipboardList aria-hidden="true" className="mx-auto h-10 w-10 text-gray-400" />
          <h2 className="mt-3 font-semibold text-gray-900 dark:text-gray-100">Geen openstaande toernooien</h2>
          <p className="mx-auto mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
            Er zijn nu geen toernooien waar je je voor in kunt schrijven. Als je graag wel aan een toernooi zou meedoen, neem dan contact op met de toernooicoördinator.
          </p>
          <div className="mx-auto mt-6 max-w-2xl border-t border-gray-200 pt-6 text-left dark:border-gray-700">
            <h3 className="font-semibold text-gray-900 dark:text-gray-100">Toernooicoördinatoren</h3>
            {coordinatorsLoading ? <p role="status" className="mt-2 text-sm text-gray-600 dark:text-gray-400">Contactgegevens laden…</p> : null}
            {coordinatorsError ? (
              <div className="mt-2 text-sm">
                <p role="alert" className="text-red-600 dark:text-red-400">De contactgegevens konden niet worden geladen.</p>
                <button type="button" onClick={() => refetchCoordinators()} className="mt-2 min-h-11 text-bright-cobalt underline underline-offset-2 dark:text-electric-cyan">Opnieuw proberen</button>
              </div>
            ) : null}
            {!coordinatorsLoading && !coordinatorsError && coordinators.length === 0 ? (
              <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">Er zijn nog geen toernooicoördinatoren met contactgegevens vermeld in Rondo.</p>
            ) : null}
            <ul className="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
              {coordinators.map((coordinator) => (
                <li key={coordinator.id} className="py-3 first:pt-0 last:pb-0">
                  <p className="font-medium text-gray-900 dark:text-gray-100">{coordinator.name}</p>
                  <div className="mt-1 flex flex-col items-start text-sm">
                    {coordinator.email ? (
                      <a href={`mailto:${coordinator.email}`} className="inline-flex min-h-11 max-w-full items-center gap-2 text-bright-cobalt underline underline-offset-2 dark:text-electric-cyan">
                        <Mail aria-hidden="true" className="h-4 w-4 shrink-0" /><span className="break-all">{coordinator.email}</span>
                      </a>
                    ) : null}
                    {coordinator.phone ? (
                      <a href={`tel:${coordinator.phone.replace(/[^+\d]/g, '')}`} className="inline-flex min-h-11 items-center gap-2 text-bright-cobalt underline underline-offset-2 dark:text-electric-cyan">
                        <Phone aria-hidden="true" className="h-4 w-4 shrink-0" />{coordinator.phone}
                      </a>
                    ) : null}
                    {!coordinator.email && !coordinator.phone ? <p className="mt-1 text-gray-600 dark:text-gray-400">Geen contactgegevens beschikbaar.</p> : null}
                  </div>
                </li>
              ))}
            </ul>
          </div>
        </div>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        {entries.map((entry) => {
          const paymentStatus = tournamentPaymentStatus(entry);
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
                <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${tournamentPaymentToneClasses(paymentStatus.tone)}`}>
                  {paymentStatus.tone === 'success' ? <CheckCircle2 className="mr-1 h-3.5 w-3.5" /> : null}
                  {paymentStatus.label}
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
