import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Mail, Phone, Users } from 'lucide-react';
import { prmApi } from '@/api/client';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

function ContactDetails({ person }) {
  return (
    <div className="min-w-0 space-y-1 text-sm">
      {person.emails.map((email) => (
        <a key={email} href={`mailto:${email}`} className="flex min-h-9 items-center gap-2 text-bright-cobalt hover:underline dark:text-electric-cyan">
          <Mail className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 [overflow-wrap:anywhere]">{email}</span>
        </a>
      ))}
      {person.phones.map((phone) => (
        <a key={phone} href={`tel:${phone.replace(/[^+0-9]/g, '')}`} className="flex min-h-9 items-center gap-2 text-bright-cobalt hover:underline dark:text-electric-cyan">
          <Phone className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 [overflow-wrap:anywhere]">{phone}</span>
        </a>
      ))}
      {person.emails.length === 0 && person.phones.length === 0 ? (
        <p className="text-gray-500 dark:text-gray-400">Geen contactgegevens bekend.</p>
      ) : null}
    </div>
  );
}

export default function MyTeam() {
  useDocumentTitle('Mijn team');
  const { data: user } = useCurrentUser();
  const [selectedTeamId, setSelectedTeamId] = useState('');
  const { data: teams = [], isLoading, error, refetch } = useQuery({
    queryKey: ['my-teams', user?.id],
    queryFn: async () => (await prmApi.getMyTeams()).data,
    enabled: Boolean(user?.has_my_teams),
    staleTime: 0,
    gcTime: 0,
    refetchOnMount: 'always',
    refetchOnWindowFocus: true,
    refetchOnReconnect: true,
    networkMode: 'always',
  });
  const team = teams.find((item) => String(item.id) === selectedTeamId) || teams[0];

  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mijn team</h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Contactgegevens van je spelers en hun ouders/verzorgers.</p>
      </div>

      {error ? (
        <div className="card space-y-3 p-6" role="alert">
          <p className="text-sm text-red-600 dark:text-red-400">
            {error.response?.status === 403 ? 'Je hebt geen actuele teamfunctie meer. Neem bij vragen contact op met een beheerder.' : 'Je team kon niet worden geladen.'}
          </p>
          <button type="button" onClick={() => refetch()} className="btn-secondary">Opnieuw proberen</button>
        </div>
      ) : (
        <>
          {teams.length > 1 ? (
            <div className="max-w-sm">
              <label htmlFor="my-team-select" className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Team</label>
              <select id="my-team-select" className="input" value={team?.id ?? ''} onChange={(event) => setSelectedTeamId(event.target.value)}>
                {teams.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </div>
          ) : null}

          {team ? (
            <section aria-labelledby="my-team-name" className="space-y-4">
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 id="my-team-name" className="text-xl font-semibold text-gray-900 dark:text-gray-100">{team.name}</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400">{team.players.length} {team.players.length === 1 ? 'speler' : 'spelers'}</p>
              </div>
              {team.players.length === 0 ? <div className="card p-6 text-sm text-gray-600 dark:text-gray-400">Er zijn nog geen actuele spelers aan dit team gekoppeld.</div> : null}
              {team.players.map((player) => (
                <article key={player.id} aria-labelledby={`player-${player.id}`} className="card p-5">
                  <h3 id={`player-${player.id}`} className="mb-4 text-lg font-semibold text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{player.name}</h3>
                  <div className="grid gap-5 md:grid-cols-3">
                    <div className="min-w-0">
                      <p className="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">Speler</p>
                      <ContactDetails person={player} />
                    </div>
                    {player.parents.map((parent) => (
                      <div key={parent.id} className="min-w-0">
                        <p className="mb-1 text-sm font-medium text-gray-600 dark:text-gray-400">Ouder/verzorger</p>
                        <p className="mb-2 text-sm font-medium text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{parent.name}</p>
                        <ContactDetails person={parent} />
                      </div>
                    ))}
                    {player.parents.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Geen ouders/verzorgers gekoppeld.</p> : null}
                  </div>
                </article>
              ))}
            </section>
          ) : (
            <div className="card p-8 text-center">
              <Users className="mx-auto mb-3 h-10 w-10 text-gray-400" aria-hidden="true" />
              <p className="text-gray-600 dark:text-gray-400">Er zijn geen actuele teams aan je gekoppeld.</p>
            </div>
          )}
        </>
      )}
    </div>
  );
}
