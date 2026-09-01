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
