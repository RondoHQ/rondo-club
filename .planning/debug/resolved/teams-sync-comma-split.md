---
status: resolved
trigger: "teams-sync-comma-split"
created: 2026-01-26T10:00:00Z
updated: 2026-01-26T10:15:00Z
---

## Current Focus

hypothesis: Fix applied - splitting comma-separated team names
test: Testing that fix correctly splits teams like "2, JO7-3" into individual teams
expecting: Should see separate teams created instead of combined names
next_action: Verify the fix works correctly

## Symptoms

expected: Teams sync should create individual teams (e.g., "2" and "JO7-3" as separate teams)
actual: Teams sync creates combined teams like "2, JO7-3" (comma-separated team names as a single team)
errors: No errors, just incorrect data creation
reproduction: Sync teams for people who belong to multiple teams - the team field contains comma-separated values
timeline: Currently happening

## Eliminated

## Evidence

- timestamp: 2026-01-26T10:05:00Z
  checked: /Users/joostdevalk/Code/rondo/rondo-sync/prepare-stadion-teams.js
  found: extractTeamName() function (lines 11-18) returns UnionTeams or ClubTeams field as-is without splitting
  implication: When a member belongs to multiple teams, the field contains "2, JO7-3" and this entire string is treated as one team name

- timestamp: 2026-01-26T10:06:00Z
  checked: prepare-stadion-teams.js runPrepare() function (lines 59-66)
  found: teamSet.add(teamName) adds the entire comma-separated string as a single team
  implication: The Set treats "2, JO7-3" as one unique team instead of splitting it into "2" and "JO7-3"

## Resolution

root_cause: The extractTeamName() function in prepare-stadion-teams.js returns the entire UnionTeams/ClubTeams field value without splitting comma-separated team names. When a member belongs to multiple teams, the data contains "2, JO7-3" and this entire string is added to the team set as a single team.
fix: Modified prepare-stadion-teams.js lines 59-66 to split comma-separated team names using split(','), trim whitespace, filter empty strings, and add each team individually to the Set
verification: PASSED - Ran prepare-stadion-teams.js --verbose and confirmed:
  - 57 unique teams extracted from 1068 members
  - Teams are now individual values (1, 2, 3, 4, 5, etc.)
  - No comma-separated team names found in output (grep test passed)
files_changed: ['/Users/joostdevalk/Code/rondo/rondo-sync/prepare-stadion-teams.js']
