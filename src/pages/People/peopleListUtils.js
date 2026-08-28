/**
 * Resolve the team shown in the people list.
 *
 * Historical positions stay visible in the person detail, but must not be
 * presented as a current team in the list.
 */
export function getCurrentTeamId(person) {
  if (person?.team_id) return person.team_id;

  const workHistory = person?.fields?.work_history || [];
  const currentJob = workHistory.find(job => job.is_current && job.team_id);

  return currentJob?.team_id || null;
}
