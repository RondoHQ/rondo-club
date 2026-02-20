---
status: diagnosed
trigger: "Person 144 (and others) are shown as having to pay €130 in membership fees but they're not active players. Only active players should be paying membership fees."
created: 2026-02-19T00:00:00Z
updated: 2026-02-19T10:00:00Z
---

## Current Focus

hypothesis: CONFIRMED — team-based matching fires BEFORE age-class-based matching for non-youth categories, causing anyone who works WITH a youth team (as trainer/coach/manager) to be matched to that team's fee category rather than their own age-class category
test: Traced calculate_fee() priority order against actual person data for multiple affected people
expecting: n/a — root cause confirmed
next_action: Report root cause — do not fix

## Symptoms

expected: Only active players should have membership fees calculated and displayed
actual: Non-players like person 144 are shown as having to pay €130 in membership fees
errors: No error messages — this is a logic/data issue
reproduction: Go to https://rondo.svawc.nl/people/144 — shows €130 contributie
started: Likely since the fee category system was implemented

## Eliminated

- hypothesis: The matching criteria are too broad because a catch-all category matches everyone
  evidence: No catch-all (catch-all requires null/empty age_classes; all categories have explicit age_classes or matching_teams); the issue is the team-matching priority order
  timestamp: 2026-02-19

## Evidence

- timestamp: 2026-02-19
  checked: class-membership-fees.php calculate_fee() method (lines 591-652)
  found: Priority order is: (1) youth age class, (2) team matching, (3) werkfunctie matching, (4) non-youth age class fallback
  implication: Team matching fires BEFORE non-youth age class matching. Anyone on a team that's in matching_teams gets that team's category, regardless of their actual role.

- timestamp: 2026-02-19
  checked: person 144 (Floyd Watson) post meta
  found: leeftijdsgroep=NULL, huidig-vrijwilliger=1, work_history: Trainer on team 2657 (Mini's), Jeugdbegeleid(st)er on team 2662 (Verenigingsbreed) — NO player role
  implication: Floyd is a trainer/volunteer with zero player indicator. No leeftijdsgroep means he falls through to team matching, which matches Mini's team 2657 → minis category.

- timestamp: 2026-02-19
  checked: fee categories option rondo_membership_fees_2025-2026
  found: "minis" category has matching_teams: [2657]. "senior" category has no matching_teams, only age_classes: ["Senioren", "Senioren Vrouwen"].
  implication: Team 2657 (Mini's) is used to match fee category. Anyone currently linked to this team — regardless of job title — gets the minis fee.

- timestamp: 2026-02-19
  checked: all people with rondo_fee_cache_2025-2026 set to minis category
  found: 56 people total in minis category. Confirmed at least 8 have no leeftijdsgroep or have Senioren leeftijdsgroep but are coaches/trainers on the Mini's team. Examples: Floyd Watson (Trainer, no age class), Bjorn Arts (Ass.-trainer, no age class), Thijs van der Velden (Teammanager, no age class), Mark Jansen (Teammanager, no age class), Lennart Nieuwenhuis (Senioren player + Trainer on 2657 → gets minis instead of senior), Bas Loeffen (Senioren + Trainer 2657 → minis), Wiebe Veltmaat (Senioren + trainer 2657 → minis).
  implication: Two distinct failure modes confirmed: (A) volunteers/trainers with no age class → team match → wrong category; (B) senior players who also coach Mini's → team match fires before age-class fallback → wrong category AND wrong fee amount (€130 instead of €255).

- timestamp: 2026-02-19
  checked: calculate_fee() code path for person 52 (Lennart Nieuwenhuis, Senioren)
  found: leeftijdsgroep=Senioren. get_category_by_age_class("Senioren") returns "senior" (non-youth). Youth check fails (senior is not youth). get_current_teams() returns teams [2605, 2657, 2662]. get_category_by_team_match([2605, 2657, 2662]) iterates categories by sort_order: minis (sort_order=0) has matching_teams=[2657] → MATCH → returns "minis". Never reaches step 4 fallback where "senior" age class would be returned.
  implication: The team-matching step (step 2) pre-empts the age-class fallback (step 4) for non-youth categories. This is a design flaw in the priority order.

## Resolution

root_cause: |
  The calculate_fee() method in class-membership-fees.php applies team-based matching BEFORE the non-youth age class fallback. This causes two failure modes:

  FAILURE MODE A — Trainers/volunteers with no leeftijdsgroep:
  People like Floyd Watson (person 144) have no leeftijdsgroep (they are not registered as players in Sportlink). When their current teams include team 2657 (Mini's), the team-matching step assigns them to the "minis" fee category (€130). They should not be in any fee category at all — they are not playing members.

  FAILURE MODE B — Senior players who also coach Mini's:
  People like Lennart Nieuwenhuis (person 52), Bas Loeffen (115), Wiebe Veltmaat (211) have leeftijdsgroep=Senioren AND also coach team 2657. The priority order is: (1) youth age class match → no (Senioren is not youth), (2) team match → YES matches minis → returns "minis" at €130. They never reach step 4 (non-youth age class fallback → "senior" at €255). These people should be paying €255 as seniors, not €130 as minis.

  ROOT CAUSE IN CODE: calculate_fee() lines 612-624 — team matching runs AFTER youth age class check but BEFORE the non-youth age class fallback. The intent was that matching_teams is used for recreational teams (players without an age class like "Senioren") who play on specific teams. But the logic does not distinguish between someone who IS a player on a team vs someone who WORKS WITH a team as staff.

  The matching_teams mechanism was designed to handle recreational players (who have no Sportlink age class), but it has no guard to prevent it from matching volunteer/trainer roles. The job_title filter in get_current_teams() only excludes "Donateur" roles — not Trainer, Ass.-trainer, Teammanager, Jeugdbegeleid(st)er, etc.

  AFFECTED PEOPLE: At least 8+ people confirmed wrong; likely more among the 56 total in "minis" category and in other categories with matching_teams (recreanten-walking-football has matching_teams: [2660, 2659, 2658, 2661]).

fix: NOT APPLIED — goal was find_root_cause only
verification: NOT APPLICABLE
files_changed: []

suggested_fix_directions: |
  Option A (Simplest): In get_current_teams(), filter out non-player job titles (Trainer, Ass.-trainer/coach, Teammanager, Jeugdbegeleid(st)er, Kaderlid, etc.), keeping only playing roles. Risk: requires maintaining a list of player vs non-player job titles.

  Option B (More robust): Add a "spelend lid" (playing member) flag check before team matching — only apply team matching if the person has a leeftijdsgroep or is marked as an active player. This aligns with the original intent: team matching is for players without an age class, not for staff.

  Option C (Fix priority order): Move the non-youth age class fallback BEFORE team matching in calculate_fee(). This fixes Failure Mode B (senior players who coach Mini's) but does not fix Failure Mode A (trainers with no age class).

  Note: The cached rondo_fee_cache_2025-2026 data will also need to be cleared after any fix so people are recalculated.
