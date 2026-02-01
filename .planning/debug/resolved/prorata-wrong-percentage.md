---
status: resolved
trigger: "Pro-rata percentages are incorrect for members who joined before the season started. They should show 100% but are showing various wrong values (e.g., 25%)."
created: 2026-02-01T10:00:00Z
updated: 2026-02-01T10:00:00Z
---

## Current Focus

hypothesis: get_prorata_percentage only checks the MONTH of lid-sinds, not whether the date is before current season start
test: Verify the logic in get_prorata_percentage (lines 887-914 of class-membership-fees.php)
expecting: The function only uses month, not considering whether member joined before current season year
next_action: Confirm by checking the logic - if lid-sinds is April 2020 and season is 2025-2026, it should be 100% but returns 25%

## Symptoms

expected: Members who joined before the current season starts (before July) should have 100% pro-rata
actual: Various wrong percentages. Example: Person ID 4381 shows 25% pro-rata but was already a member before season start
errors: No error messages, just wrong calculations
reproduction: Check person 4381 at https://stadion.svawc.nl/people/4381
started: After Phase 127 (fee caching) implementation

## Eliminated

## Evidence

- timestamp: 2026-02-01T10:10:00Z
  checked: get_prorata_percentage logic in class-membership-fees.php lines 887-914
  found: Function only checks MONTH of lid-sinds, not whether date is before current season start
  implication: Members who joined before July of season year get wrong percentages based on month alone

- timestamp: 2026-02-01T10:12:00Z
  checked: Production data for person 4381
  found: lid-sinds=2025-05-13, current season=2025-2026, prorata returns 0.25
  implication: Confirmed bug - person joined May 2025, before season 2025-2026 started in July, should be 100%

- timestamp: 2026-02-01T10:25:00Z
  checked: Production verification after fix deployment
  found: Person 4381 now shows 100% pro-rata, person 4380 (lid-sinds 2015-10-05) shows 100%
  implication: Fix verified - members who joined before season start now correctly get 100%

## Resolution

root_cause: get_prorata_percentage() only checks the MONTH of lid-sinds date, ignoring the YEAR. Members who joined before the current season start (before July of season start year) should get 100%, but instead get percentages based solely on their join month (e.g., April join = 25%).
fix: Updated get_prorata_percentage to accept optional season parameter and compare lid-sinds against season start date (July 1 of season year). If lid-sinds < season start, returns 100%.
verification: |
  - Person 4381: lid-sinds=2025-05-13, was showing 25%, now correctly shows 100%
  - Person 4380: lid-sinds=2015-10-05, now correctly shows 100% (joined years ago)
  - All test cases pass including edge cases (Q1, Q2, Q3, Q4 within season)
files_changed:
  - includes/class-membership-fees.php
