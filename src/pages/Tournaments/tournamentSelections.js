export function currentTournamentTeams(teams = []) {
  return teams.filter((team) => Number(team.player_count) > 0);
}

export function allEligibleTournamentAssignments(teams = []) {
  return Object.fromEntries(
    teams
      .filter((team) => team.assignees?.some((assignee) => assignee.email))
      .map((team) => [team.id, team.assignees.filter((assignee) => assignee.email).map((assignee) => assignee.person_id)]),
  );
}

export function tournamentAssignmentCounts(selected = {}) {
  const userIds = Object.values(selected);
  return {
    teamCount: userIds.length,
    assigneeCount: userIds.reduce((total, ids) => total + ids.length, 0),
    hasTeamWithoutAssignee: userIds.some((ids) => ids.length === 0),
  };
}

export function tournamentAssignmentDelta(currentPersonIds = [], selectedPersonIds = []) {
  const current = new Set(currentPersonIds.map(Number));
  const selected = new Set(selectedPersonIds.map(Number));
  const addedCount = [...selected].filter((userId) => !current.has(userId)).length;
  const removedCount = [...current].filter((userId) => !selected.has(userId)).length;
  return {
    addedCount,
    removedCount,
    changed: addedCount > 0 || removedCount > 0,
  };
}

export function tournamentAssignmentNeedsSync(currentAssignees = [], candidates = [], selectedPersonIds = []) {
  const currentByPerson = new Map(currentAssignees.map((assignee) => [Number(assignee.person_id), assignee]));
  const candidatesByPerson = new Map(candidates.map((candidate) => [Number(candidate.person_id), candidate]));
  return selectedPersonIds.some((userId) => {
    const current = currentByPerson.get(Number(userId));
    const candidate = candidatesByPerson.get(Number(userId));
    if (!current || !candidate) return false;
    return ['user_id', 'name', 'role', 'email', 'mobile'].some((field) => (
      String(current[field] ?? '') !== String(candidate[field] ?? '')
    ));
  });
}
