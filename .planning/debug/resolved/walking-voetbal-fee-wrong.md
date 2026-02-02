---
status: resolved
trigger: "walking-voetbal-fee-wrong"
created: 2026-02-02T00:00:00Z
updated: 2026-02-02T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - get_current_teams() includes Donateur roles which are non-playing. Fixed by excluding job_title="Donateur" from team list
test: Modified get_current_teams() to skip Donateur roles. Member 4227 now has only "Walking voetbal" team (recreational)
expecting: With both fixes (walking voetbal detection + donateur exclusion), member should get €65 recreant fee
next_action: Deploy updated fix and verify on production

## Symptoms

expected: €65 contribution - Walking Voetbal members should pay reduced fee
actual: €230 showing on contributie page
errors: None - just wrong amount
reproduction: View member 4227 on https://stadion.svawc.nl/contributie or /people/4227
started: Current behavior - discovered now during v12.1 development

## Eliminated

## Evidence

- timestamp: 2026-02-02T00:05:00Z
  checked: class-membership-fees.php calculate_fee() method
  found: Lines 232-248 define is_recreational_team() - checks if team name contains "recreant" OR "walking football"
  implication: Walking Voetbal should be detected as recreational team and member should get €65 recreant fee

- timestamp: 2026-02-02T00:06:00Z
  checked: Fee calculation priority logic (lines 306-347)
  found: Senior with recreational teams gets "recreant" fee (€65), non-recreational teams get "senior" fee (€230)
  implication: If member has both Walking Voetbal AND another non-recreational team, they would get €230 senior fee (line 340: "higher fee wins")

- timestamp: 2026-02-02T00:07:00Z
  checked: Member 4227 (Jo Toonen) production data
  found:
    - leeftijdsgroep: "Senioren"
    - Teams: "Verenigingsbreed" (ID 10776) AND "Walking voetbal" (ID 12870)
    - Both teams marked is_current=1
  implication: Member is on TWO teams - one is Walking voetbal (recreational), but "Verenigingsbreed" is not recognized as recreational

- timestamp: 2026-02-02T00:08:00Z
  checked: is_recreational_team() logic (line 248)
  found: Checks if title contains "recreant" OR "walking football" (case-insensitive)
  implication: "Walking voetbal" != "walking football" - stripos() looking for ENGLISH but team name is DUTCH

- timestamp: 2026-02-02T00:09:00Z
  checked: PHP string matching test
  found: stripos("Walking voetbal", "walking football") = false, BUT stripos("Walking voetbal", "voetbal") = true
  implication: Can detect Dutch walking football teams by checking for "voetbal" substring

- timestamp: 2026-02-02T00:10:00Z
  checked: Fee calculation logic (lines 329-340) and member's team situation
  found: Senior with multiple teams - if ALL recreational → €65, if ANY non-recreational → €230 (comment says "higher fee wins")
  implication: Member is on "Verenigingsbreed" (association-wide, likely for donateurs) AND "Walking voetbal". Even with fix, gets €230 because Verenigingsbreed is treated as non-recreational team

- timestamp: 2026-02-02T00:11:00Z
  checked: Member 4227 work_history details
  found:
    - Team 0: "Verenigingsbreed" with job_title="Donateur"
    - Team 1: "Walking voetbal" with job_title="Teamspeler"
  implication: ROOT CAUSE REFINED - Member has "Donateur" role on Verenigingsbreed team. This is not a playing role and should be excluded from team count. Only "Walking voetbal" (Teamspeler) should count.

- timestamp: 2026-02-02T00:12:00Z
  checked: Test simulation of complete fix
  found: With both fixes (skip Donateur + detect walking voetbal), member has only Walking voetbal team (recreational)
  implication: Fee category = recreant, Base fee = €65 (correct!)

## Resolution

root_cause: The get_current_teams() method includes ALL teams regardless of role. Member 4227 has two teams: "Verenigingsbreed" with role "Donateur" (non-playing) and "Walking voetbal" with role "Teamspeler" (playing). The fee calculation treats both as playing teams, and since "Verenigingsbreed" is not recreational, assigns €230 senior fee instead of €65 recreant fee. Additionally, is_recreational_team() checked for "walking football" (English) but team name is "Walking voetbal" (Dutch).
fix:
  1. Added "walking voetbal" check to is_recreational_team() method (line 248) to detect Dutch walking football teams
  2. Modified get_current_teams() method (line 194-196) to exclude teams where job_title is "Donateur" since donateurs are non-playing members
verification: ✓ VERIFIED ON PRODUCTION
  - Member 4227 get_current_teams() now returns only [12870] (Walking voetbal), excludes Verenigingsbreed
  - Member 4227 calculate_fee() returns category="recreant", base_fee=€65 (correct!)
  - Changes deployed and tested on production
files_changed:
  - includes/class-membership-fees.php
