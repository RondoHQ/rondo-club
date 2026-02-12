---
status: resolved
trigger: "vog-exempt-commissie-not-filtering"
created: 2026-02-12T10:30:00Z
updated: 2026-02-12T10:55:00Z
---

## Current Focus

hypothesis: FALSE - person 998 is correctly marked as volunteer
test: checked all work history positions
expecting: found person 998 has TWO commissie positions, one exempt and one not
next_action: document root cause - user misunderstanding, system working correctly

## Symptoms

expected: Person 998 should NOT appear in the VOG list because their only commissie (2677) is in the exempt_commissies list
actual: Person 998 DOES appear in the VOG list as needing a VOG
errors: None reported
reproduction: Go to /vog on production (https://rondo.svawc.nl), person 998 is listed. Their profile is at /people/998. Their commissie is at /commissies/2677. The exempt commissies setting includes commissie 2677.
started: Unknown — may have never worked correctly, or may be a recent regression

## Eliminated

- hypothesis: VOG filtering logic not checking exempt_commissies
  evidence: Code review shows exemption logic is present and correct (lines 252-265 in class-volunteer-status.php)
  timestamp: 2026-02-12T10:35:00Z

- hypothesis: huidig-vrijwilliger field is stale and not recalculating
  evidence: Manual recalculation script confirmed huidig-vrijwilliger=1 is CORRECT for person 998
  timestamp: 2026-02-12T10:50:00Z

## Evidence

- timestamp: 2026-02-12T10:35:00Z
  checked: class-volunteer-status.php lines 252-265
  found: Exemption logic EXISTS and checks exempt commissies when determining volunteer status
  implication: The logic is correct, but huidig-vrijwilliger might not be recalculating after settings change

- timestamp: 2026-02-12T10:37:00Z
  checked: VOGList.jsx line 286
  found: Frontend filters by huidigeVrijwilliger: '1'
  implication: If huidig-vrijwilliger field is stale (still '1' when it should be '0'), person appears in list

- timestamp: 2026-02-12T10:40:00Z
  checked: Production data for person 998
  found: huidig-vrijwilliger = 1, exempt_commissies includes 2677
  implication: CONFIRMED - huidig-vrijwilliger is stale and not recalculated after exempt setting changes

- timestamp: 2026-02-12T10:45:00Z
  checked: class-rest-api.php lines 2546-2548 and 3452-3467
  found: Recalculation mechanism EXISTS - trigger_vog_recalculation() is called when exempt_commissies changes
  implication: Either the setting was changed through a different method (WP-CLI, database), OR the recalculation wasn't run, OR it failed for person 998

- timestamp: 2026-02-12T10:50:00Z
  checked: Person 998 work history details via recalculation script
  found: Person 998 has TWO current commissie positions:
    1. Verenigingsbreed (2662) - NOT exempt - makes them require VOG
    2. Oud papier (2677) - IS exempt
  implication: System is working CORRECTLY - person 998 legitimately needs a VOG due to non-exempt commissie

## Resolution

root_cause: |
  NO BUG - System working as designed.

  Person 998 correctly appears in the VOG list because they have TWO current commissie positions:
  1. "Verenigingsbreed" (commissie 2662) - NOT in exempt list - requires VOG
  2. "Oud papier" (commissie 2677) - IS in exempt list - does not require VOG

  The VOG requirement is determined per person, not per commissie. If a person has ANY non-exempt
  volunteer position, they need a VOG, even if they also have exempt positions.

  User expectation was that person 998 only had the "oud papier" commissie, but they were unaware
  of the "Verenigingsbreed" position.

fix: No code changes required - user education needed
verification: |
  Verified via production database:
  - Person 998 has work_history with 2 current positions
  - Commissie 2662 ("Verenigingsbreed") is not in exempt list [2457, 2447, 2677, 2665]
  - Commissie 2677 ("Oud papier") is in exempt list
  - VolunteerStatus logic correctly identifies person as volunteer due to position in commissie 2662
  - Manual recalculation confirms huidig-vrijwilliger=1 is correct
files_changed: []
