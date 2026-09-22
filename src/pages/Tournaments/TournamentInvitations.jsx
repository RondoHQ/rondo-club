import { useState } from 'react';
import { Send } from 'lucide-react';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import { useInviteTournamentTeams, usePublishTournament, useTournamentAssignmentOptions } from '@/hooks/useTournaments';
import { allEligibleTournamentAssignments, currentTournamentTeams, tournamentAssignmentCounts } from './tournamentSelections';

export default function TournamentInvitations({ tournament, additional = false, onSent }) {
  const optionsQuery = useTournamentAssignmentOptions();
  const publish = usePublishTournament();
  const invite = useInviteTournamentTeams();
  const mutation = additional ? invite : publish;
  const [selected, setSelected] = useState({});
  const [result, setResult] = useState('');
  const excluded = new Set(additional ? tournament.target_team_ids : []);
  const teams = currentTournamentTeams(optionsQuery.data).filter((team) => !excluded.has(team.id));
  const eligible = allEligibleTournamentAssignments(teams);
  const eligibleCount = Object.keys(eligible).length;
  // A refreshed response may remove a team selected in an older browser view.
  const selection = Object.fromEntries(Object.entries(selected).filter(([id]) => teams.some((team) => team.id === Number(id))));
  const counts = tournamentAssignmentCounts(selection);
  const allSelected = eligibleCount > 0 && Object.entries(eligible).every(([id, ids]) => ids.every((personId) => selection[id]?.includes(personId)));
  const withoutAccount = teams.reduce((total, team) => total + team.assignees.filter((person) => selection[team.id]?.includes(person.person_id) && !person.user_id).length, 0);

  const toggleTeam = (team) => setSelected((current) => {
    const next = { ...current };
    if (Object.hasOwn(next, team.id)) delete next[team.id];
    else next[team.id] = eligible[team.id] || [];
    return next;
  });
  const togglePerson = (teamId, personId) => setSelected((current) => {
    const ids = current[teamId] || [];
    return { ...current, [teamId]: ids.includes(personId) ? ids.filter((id) => id !== personId) : [...ids, personId] };
  });
  const send = async () => {
    setResult('');
    try {
      const response = await mutation.mutateAsync({
        id: tournament.id,
        assignments: Object.entries(selection).map(([teamId, personIds]) => ({ team_id: Number(teamId), person_ids: personIds })),
      });
      const emails = Object.values(response.emails || {}).flat();
      const failed = emails.filter((email) => !email.sent).length;
      const sent = emails.filter((email) => email.sent && !email.existing).length;
      const message = `${sent} ${sent === 1 ? 'uitnodiging' : 'uitnodigingen'} verstuurd.${failed ? ` ${failed} uitnodigingen konden niet worden verstuurd. Probeer deze opnieuw via Toewijzing wijzigen bij het team.` : ''}`;
      setSelected({});
      setResult(message);
      onSent?.(message);
    } catch {
      // The mutation error stays visible beside the preserved selection.
    }
  };

  return (
    <section className="card space-y-4 p-5" aria-labelledby="tournament-invitations-title">
      <div>
        <h2 id="tournament-invitations-title" className="text-lg font-semibold text-gray-900 dark:text-gray-100">{additional ? 'Extra teams uitnodigen' : 'Teams en kader uitnodigen'}</h2>
        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">Elk team krijgt één gedeelde inschrijving. Geselecteerde kaderleden ontvangen een uitnodiging; zonder Rondo-account krijgen ze ook uitleg over account aanmaken.</p>
      </div>
      {optionsQuery.isLoading ? <ContentLoadingSpinner /> : null}
      {optionsQuery.error ? <div role="alert" className="text-sm text-red-700 dark:text-red-300">De kaderleden konden niet worden geladen. <button type="button" className="underline" onClick={() => optionsQuery.refetch()}>Opnieuw proberen</button></div> : null}
      {!optionsQuery.isLoading && !optionsQuery.error ? <>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm text-gray-700 dark:text-gray-300">{teams.length === 0 ? 'Er zijn geen extra teams met actuele spelers om uit te nodigen.' : `${eligibleCount} ${eligibleCount === 1 ? 'team' : 'teams'} met bereikbaar kader beschikbaar.`}</p>
            {teams.length > eligibleCount ? <p className="mt-1 text-sm text-amber-800 dark:text-amber-200">{teams.length - eligibleCount} {teams.length - eligibleCount === 1 ? 'team heeft' : 'teams hebben'} geen actueel kaderlid met een e-mailadres.</p> : null}
          </div>
          <button type="button" className="btn-tertiary" disabled={eligibleCount === 0 || mutation.isPending} onClick={() => setSelected(allSelected ? {} : eligible)}>{allSelected ? 'Alles deselecteren' : 'Alle teams selecteren'}</button>
        </div>
        <fieldset disabled={mutation.isPending} className="grid gap-3 lg:grid-cols-2">
          <legend className="sr-only">Teams en kaderleden selecteren</legend>
          {teams.map((team) => {
            const checked = Object.hasOwn(selection, team.id);
            return (
              <div key={team.id} className={`rounded-lg border p-4 ${checked ? 'border-electric-cyan bg-cyan-50/60 dark:bg-cyan-950/20' : 'border-gray-200 dark:border-gray-700'}`}>
                <label className="flex cursor-pointer items-start gap-3">
                  <input type="checkbox" className="mt-1" checked={checked} disabled={!eligible[team.id]} onChange={() => toggleTeam(team)} />
                  <span className="min-w-0 break-words font-medium text-gray-900 dark:text-gray-100">{team.name}<span className="block text-sm font-normal text-gray-600 dark:text-gray-400">{team.age_group}</span></span>
                </label>
                {team.assignees.length === 0 ? <p className="mt-3 text-sm text-amber-800 dark:text-amber-200">Geen actueel kaderlid gevonden.</p> : null}
                {checked || !eligible[team.id] ? <div className="mt-3 space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">{team.assignees.map((person) => (
                  <label key={person.person_id} className="flex cursor-pointer items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" className="mt-0.5" disabled={!person.email} checked={(selection[team.id] || []).includes(person.person_id)} onChange={() => togglePerson(team.id, person.person_id)} />
                    <span className="min-w-0 break-words">{person.name}<span className="block text-gray-600 dark:text-gray-400">{person.role}</span>{!person.user_id && person.email ? <span className="block text-amber-800 dark:text-amber-200">Nog geen Rondo-account; ontvangt uitleg bij de uitnodiging.</span> : null}{!person.email ? <span className="block text-red-700 dark:text-red-300">E-mailadres ontbreekt; vul dit eerst aan bij de relatie.</span> : null}</span>
                  </label>
                ))}</div> : null}
              </div>
            );
          })}
        </fieldset>
      </> : null}
      {counts.teamCount > 0 ? <p className="text-sm text-gray-700 dark:text-gray-300">{counts.teamCount} {counts.teamCount === 1 ? 'team' : 'teams'} geselecteerd · {counts.assigneeCount} uitnodigingen · {withoutAccount} zonder Rondo-account.</p> : null}
      {counts.hasTeamWithoutAssignee ? <p role="alert" className="text-sm text-amber-800 dark:text-amber-200">Selecteer minimaal één kaderlid per team.</p> : null}
      {mutation.error ? <p role="alert" className="text-sm text-red-700 dark:text-red-300">{mutation.error?.response?.data?.message || 'De uitnodigingen konden niet worden verwerkt. Probeer het opnieuw.'}</p> : null}
      {result && !onSent ? <p role="status" className="text-sm text-gray-700 dark:text-gray-300">{result}</p> : null}
      <div className="flex justify-end"><button type="button" className="btn-primary inline-flex items-center" disabled={mutation.isPending || optionsQuery.isLoading || Boolean(optionsQuery.error) || counts.teamCount === 0 || counts.hasTeamWithoutAssignee} onClick={send}><Send className="mr-2 h-4 w-4" />{mutation.isPending ? 'Uitnodigingen versturen…' : additional ? 'Geselecteerde teams uitnodigen' : 'Publiceren en uitnodigen'}</button></div>
    </section>
  );
}
