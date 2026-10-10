import { useQuery } from '@tanstack/react-query';
import { Users } from 'lucide-react';
import { prmApi } from '@/api/client';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import RosterMemberCard from '@/components/RosterMemberCard';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useDocumentTitle } from '@/hooks/useDocumentTitle';

export default function MyCommittees() {
  useDocumentTitle('Mijn commissies');
  const { data: user } = useCurrentUser();
  const { data = [], isLoading, error, refetch } = useQuery({
    queryKey: ['my-committees', user?.id],
    queryFn: async () => (await prmApi.getMyCommittees()).data,
    enabled: Boolean(user?.has_my_committees),
    staleTime: 0,
    gcTime: 0,
    refetchOnMount: 'always',
    refetchOnWindowFocus: true,
    refetchOnReconnect: true,
    refetchInterval: 60_000,
    networkMode: 'always',
    retry: false,
  });
  const committees = user?.has_my_committees ? data : [];

  if (isLoading) return <ContentLoadingSpinner />;

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mijn commissies</h1>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">De leden en functies van je commissies bij elkaar.</p>
      </div>

      {error ? (
        <div className="card space-y-3 p-6" role="alert">
          <p className="text-sm text-red-600 dark:text-red-400">
            {error.response?.status === 403 ? 'Je hebt geen toegang meer tot een actuele commissie. Neem bij vragen contact op met een beheerder.' : 'Je commissies konden niet worden geladen.'}
          </p>
          <button type="button" onClick={() => refetch()} className="btn-secondary">Opnieuw proberen</button>
        </div>
      ) : committees.length > 0 ? (
        committees.map((committee) => (
          <section key={committee.id} aria-labelledby={`committee-${committee.id}`} className="space-y-3">
            <div>
              <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 id={`committee-${committee.id}`} className="text-xl font-semibold text-gray-900 dark:text-gray-100">{committee.name}</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400">{committee.members.length} {committee.members.length === 1 ? 'lid' : 'leden'}</p>
              </div>
              {committee.can_view_contacts ? <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Als voorzitter kun je de contactgegevens van je commissieleden bekijken.</p> : null}
            </div>
            {committee.members.length === 0 ? (
              <p className="card p-6 text-sm text-gray-600 dark:text-gray-400">Er zijn nog geen actuele leden aan deze commissie gekoppeld.</p>
            ) : (
              <div className="space-y-3">
                {committee.members.map((person) => (
                  <RosterMemberCard key={person.id} person={person} canViewContacts={committee.can_view_contacts === true} contactLabel="Commissielid" showParents={false} />
                ))}
              </div>
            )}
          </section>
        ))
      ) : (
        <div className="card p-8 text-center">
          <Users className="mx-auto h-10 w-10 text-gray-400" aria-hidden="true" />
          <h2 className="mt-3 text-lg font-semibold text-gray-900 dark:text-gray-100">Geen actuele commissies</h2>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Zodra je een actieve commissierol hebt, verschijnt hier het ledenoverzicht.</p>
        </div>
      )}
    </div>
  );
}
