---
status: verifying
trigger: "vog-list-missing-expired-member"
created: 2026-02-02T00:00:00Z
updated: 2026-02-02T00:04:00Z
---

## Current Focus

hypothesis: CONFIRMED - Boundary condition bug in date comparison
test: Fixed by changing < to <= in VOG date comparison
expecting: Person 3893 (and others with VOG exactly 3 years old) now appear in list
next_action: Apply fix to class-rest-people.php

## Symptoms

expected: Person with expired VOG should appear on /vog page which lists people needing VOG attention
actual: Person 3893 shows expired VOG on profile (/people/3893) but is NOT listed on /vog page
errors: None visible - just missing from list
reproduction: 1) Visit /people/3893 - see "VOG expired" indicator, 2) Visit /vog - person not in list
started: Current behavior - discovered now

## Eliminated

## Evidence

- timestamp: 2026-02-02T00:01:00Z
  checked: VOGList.jsx query parameters
  found: Uses useFilteredPeople with huidigeVrijwilliger: '1', vogMissing: '1', vogOlderThanYears: 3
  implication: Query filters for current volunteers needing VOG (missing or older than 3 years)

- timestamp: 2026-02-02T00:02:00Z
  checked: class-rest-people.php lines 1186-1194
  found: VOG filtering logic - when vog_missing='1' AND vog_older_than_years=3, uses OR: "(dv.meta_value IS NULL OR dv.meta_value = '') OR (dv.meta_value < cutoff_date)"
  implication: Should match both missing VOG AND expired VOG (older than 3 years)

- timestamp: 2026-02-02T00:03:00Z
  checked: Person 3893 meta data via SSH
  found: datum-vog = "2023-02-02", huidig-vrijwilliger = "1"
  implication: Person has VOG dated 2023-02-02, is current volunteer. Cutoff date for 3 years is 2023-02-02. Person's VOG date equals cutoff, not less than cutoff!

- timestamp: 2026-02-02T00:05:00Z
  checked: API response after fix deployed
  found: Person 3893 now appears in filtered people response with correct VOG date
  implication: Fix successful - boundary condition resolved

## Resolution

root_cause: Boundary condition bug in VOG date filtering (lines 1184, 1190, 1198 in class-rest-people.php). Query uses < (less than) comparison, excluding VOG dates that equal the cutoff date. Person 3893 has VOG dated exactly 2023-02-02 (3 years ago today), which equals the cutoff but doesn't satisfy < comparison.
fix: Changed < to <= (less than or equal) in all three VOG date comparisons (lines 1184, 1190, 1198) to include VOG dates that are exactly N years old
verification: Verified via API - person 3893 now appears in filtered results with correct VOG date. Frontend /vog page should now display this person.
files_changed: [includes/class-rest-people.php]
