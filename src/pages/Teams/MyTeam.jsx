import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ChevronDown, Mail, Phone, Users } from 'lucide-react';
import { prmApi } from '@/api/client';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import TabButton from '@/components/TabButton';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

function ContactDetails({ person }) {
  return (
    <div className="min-w-0 space-y-1 text-sm">
      {person.phones.map((phone) => (
        <a key={phone} href={`tel:${phone.replace(/[^+0-9]/g, '')}`} className="flex min-h-11 items-center gap-2 text-bright-cobalt hover:underline dark:text-electric-cyan">
          <Phone className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 [overflow-wrap:anywhere]">{phone}</span>
        </a>
      ))}
      {person.emails.map((email) => (
        <a key={email} href={`mailto:${email}`} className="flex min-h-11 items-center gap-2 text-bright-cobalt hover:underline dark:text-electric-cyan">
          <Mail className="h-4 w-4 shrink-0" aria-hidden="true" />
          <span className="min-w-0 [overflow-wrap:anywhere]">{email}</span>
        </a>
      ))}
      {person.emails.length === 0 && person.phones.length === 0 ? (
        <p className="text-gray-500 dark:text-gray-400">Geen contactgegevens bekend.</p>
      ) : null}
    </div>
  );
}

function PlayerPhoto({ player }) {
  const [failedThumbnail, setFailedThumbnail] = useState(null);
  const nameParts = player.name.trim().split(/\s+/).filter(Boolean);
  const initials = [nameParts[0]?.[0], nameParts.length > 1 ? nameParts.at(-1)?.[0] : ''].join('').toUpperCase() || '?';

  if (player.thumbnail && player.thumbnail !== failedThumbnail) {
    return (
      <img
        src={player.thumbnail}
        alt=""
        width={64}
        height={64}
        loading="lazy"
        onError={() => setFailedThumbnail(player.thumbnail)}
        className="h-16 w-16 shrink-0 rounded-xl bg-gray-100 object-cover dark:bg-gray-700"
      />
    );
  }

  return (
    <span aria-label="Geen foto beschikbaar" className="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-xl font-semibold text-bright-cobalt dark:bg-gray-700 dark:text-electric-cyan">
      {initials}
    </span>
  );
}

function PlayerCard({ player }) {
  return (
    <details className="group overflow-hidden rounded-xl border border-gray-200 bg-white open:border-bright-cobalt dark:border-gray-700 dark:bg-gray-800 dark:open:border-electric-cyan">
      <summary className="flex cursor-pointer list-none items-center gap-4 p-4 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-bright-cobalt dark:hover:bg-gray-700/50 dark:focus-visible:outline-electric-cyan [&::-webkit-details-marker]:hidden">
        <PlayerPhoto player={player} />
        <span className="min-w-0 flex-1">
          <span className="block font-semibold text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{player.name}</span>
          <span className="mt-1 block text-sm text-bright-cobalt dark:text-electric-cyan">
            <span className="group-open:hidden">Contactgegevens</span>
            <span className="hidden group-open:inline">Contactgegevens sluiten</span>
          </span>
        </span>
        <ChevronDown className="h-5 w-5 shrink-0 text-gray-500 group-open:rotate-180 group-open:text-bright-cobalt dark:text-gray-400 dark:group-open:text-electric-cyan" aria-hidden="true" />
      </summary>
      <div className="grid gap-5 border-t border-gray-200 p-4 sm:grid-cols-2 xl:grid-cols-3 dark:border-gray-700">
        <div className="min-w-0">
          <p className="mb-1 text-xs text-gray-500 dark:text-gray-400">Speler</p>
          <p className="mb-2 text-sm font-semibold text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{player.name}</p>
          <ContactDetails person={player} />
        </div>
        {player.parents.map((parent) => (
          <div key={parent.id} className="min-w-0 border-t border-gray-200 pt-4 sm:border-0 sm:pt-0 dark:border-gray-700">
            <p className="mb-1 text-xs text-gray-500 dark:text-gray-400">Ouder/verzorger</p>
            <p className="mb-2 text-sm font-semibold text-gray-900 [overflow-wrap:anywhere] dark:text-gray-100">{parent.name}</p>
            <ContactDetails person={parent} />
          </div>
        ))}
        {player.parents.length === 0 ? <p className="text-sm text-gray-500 dark:text-gray-400">Geen ouders/verzorgers gekoppeld.</p> : null}
      </div>
    </details>
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

  const handleTeamKeyDown = (event, index) => {
    let nextIndex;
    if (event.key === 'ArrowRight') nextIndex = (index + 1) % teams.length;
    else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + teams.length) % teams.length;
    else if (event.key === 'Home') nextIndex = 0;
    else if (event.key === 'End') nextIndex = teams.length - 1;
    else return;

    event.preventDefault();
    setSelectedTeamId(String(teams[nextIndex].id));
    event.currentTarget.parentElement.querySelectorAll('[role="tab"]')[nextIndex].focus();
  };

  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mijn team</h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Je spelers en hun contactgegevens bij elkaar.</p>
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
            <div className="border-b border-gray-200 dark:border-gray-700">
              <div role="tablist" aria-label="Mijn teams" className="flex flex-wrap gap-x-8 gap-y-2">
                {teams.map((item, index) => (
                  <TabButton
                    key={item.id}
                    id={`my-team-tab-${item.id}`}
                    role="tab"
                    aria-selected={team?.id === item.id}
                    aria-controls="my-team-roster"
                    tabIndex={team?.id === item.id ? 0 : -1}
                    label={item.name}
                    isActive={team?.id === item.id}
                    onClick={() => setSelectedTeamId(String(item.id))}
                    onKeyDown={(event) => handleTeamKeyDown(event, index)}
                    style={{ minHeight: 44 }}
                  />
                ))}
              </div>
            </div>
          ) : null}

          {team ? (
            <section
              key={team.id}
              id="my-team-roster"
              role={teams.length > 1 ? 'tabpanel' : undefined}
              aria-labelledby={teams.length > 1 ? `my-team-tab-${team.id}` : 'my-team-name'}
              tabIndex={teams.length > 1 ? 0 : undefined}
              className="space-y-4"
            >
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 id="my-team-name" className="text-xl font-semibold text-gray-900 dark:text-gray-100">{teams.length > 1 ? 'Spelers' : team.name}</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400">{team.players.length} {team.players.length === 1 ? 'speler' : 'spelers'}</p>
              </div>
              {team.players.length === 0 ? <div className="card p-6 text-sm text-gray-600 dark:text-gray-400">Er zijn nog geen actuele spelers aan dit team gekoppeld.</div> : null}
              <div className="space-y-3">
                {team.players.map((player) => <PlayerCard key={player.id} player={player} />)}
              </div>
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
