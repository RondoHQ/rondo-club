---
status: resolved
trigger: "profile-missing-infix: On /profile page, 'Gekoppeld persoon' shows 'Joost Valk' instead of 'Joost de Valk' — the infix/tussenvoegsel is missing from the linked person's name display"
created: 2026-02-22T00:00:00Z
updated: 2026-02-22T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED — both `get_current_user()` (line 2786-2788) and `get_users()` (line 2871-2873) in class-rest-api.php compose `linked_person_name` using only `first_name` + `last_name`, skipping the `infix` ACF field
test: Read class-rest-api.php lines 2780-2798 and 2864-2875
expecting: Fix by adding `get_field('infix', $person_id)` and using `implode(' ', array_filter([...]))` pattern (same as line 4203)
next_action: DONE

## Symptoms

expected: "Gekoppeld persoon: Joost de Valk" with the infix included
actual: "Gekoppeld persoon: Joost Valk" — infix "de" is missing
errors: No errors, just wrong name display
reproduction: Go to /profile page, look at "Gekoppeld persoon" text
started: Likely since v30.0 when profile feature was built

## Eliminated

## Evidence

- timestamp: 2026-02-22T00:00:00Z
  checked: src/pages/Profile/Profile.jsx
  found: Component just displays `user.linked_person_name` — no name composition here
  implication: Bug is in the PHP REST endpoint

- timestamp: 2026-02-22T00:00:00Z
  checked: includes/class-rest-api.php lines 2786-2788
  found: `$first = get_field('first_name', ...), $last = get_field('last_name', ...)`, name = trim($first . ' ' . $last) — no infix
  implication: ROOT CAUSE — infix field never fetched or included

- timestamp: 2026-02-22T00:00:00Z
  checked: includes/class-rest-api.php lines 2871-2873
  found: Same pattern in get_users() — also missing infix
  implication: Same bug in admin users list

- timestamp: 2026-02-22T00:00:00Z
  checked: includes/class-rest-api.php line 4201-4203
  found: Correct pattern: `get_field('infix', $member_id)` + `implode(' ', array_filter([$first_name, $infix, $last_name]))`
  implication: Fix pattern to use

## Resolution

root_cause: Both `get_current_user()` and `get_users()` in class-rest-api.php compose linked_person_name from first_name + last_name only, skipping the `infix` ACF field. Built in v30.0 phases 205/207/208 without considering Dutch name infixes.
fix: Added `$infix = get_field('infix', $person_id) ?: '';` and changed to `implode(' ', array_filter([$first, $infix, $last]))` in both get_current_user() and get_users() in class-rest-api.php
verification: Build passes clean, deployed to production (commit 7404a2a3), pushed to remote
files_changed: [includes/class-rest-api.php]
