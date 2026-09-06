export function currentTournamentTeams(teams = []) {
  return teams.filter((team) => Number(team.player_count) > 0);
}

export function allEligibleTournamentAssignments(teams = []) {
  return Object.fromEntries(
    teams
      .filter((team) => team.assignees?.length > 0)
      .map((team) => [team.id, team.assignees.map((assignee) => assignee.user_id)]),
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

export function tournamentAssignmentDelta(currentUserIds = [], selectedUserIds = []) {
  const current = new Set(currentUserIds.map(Number));
  const selected = new Set(selectedUserIds.map(Number));
  const addedCount = [...selected].filter((userId) => !current.has(userId)).length;
  const removedCount = [...current].filter((userId) => !selected.has(userId)).length;
  return {
    addedCount,
    removedCount,
    changed: addedCount > 0 || removedCount > 0,
  };
}

export function tournamentAssignmentNeedsSync(currentAssignees = [], candidates = [], selectedUserIds = []) {
  const currentByUser = new Map(currentAssignees.map((assignee) => [Number(assignee.user_id), assignee]));
  const candidatesByUser = new Map(candidates.map((candidate) => [Number(candidate.user_id), candidate]));
  return selectedUserIds.some((userId) => {
    const current = currentByUser.get(Number(userId));
    const candidate = candidatesByUser.get(Number(userId));
    if (!current || !candidate) return false;
    return ['person_id', 'name', 'role', 'email', 'mobile'].some((field) => (
      String(current[field] ?? '') !== String(candidate[field] ?? '')
    ));
  });
}
