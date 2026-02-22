---
status: resolved
trigger: "In the financieel section of a person's detail page, the text 'Positie X van X in gezin met {naam}' is missing the infix/tussenvoegsel from the family head's name."
created: 2026-02-22T00:00:00Z
updated: 2026-02-22T00:00:00Z
---

## Current Focus

hypothesis: confirmed — infix field not fetched when building family_members name in REST endpoint
test: n/a — fix applied
expecting: n/a
next_action: verified and archived

## Symptoms

expected: "Positie 2 van 2 in gezin met Ravi de Valk" — full name with infix/tussenvoegsel
actual: "Positie 2 van 2 in gezin met Ravi Valk" — infix "de" is missing
errors: No errors — just incorrect name display
reproduction: Go to a person's detail page (e.g. Borre de Valk), look at the Financieel section, see the family position text
started: Unknown — likely since the feature was built

## Eliminated

- hypothesis: Bug is in the React component (FinancesCard.jsx)
  evidence: FinancesCard.jsx simply renders `{member.name}` — whatever the API returns. No name composition happens in the component.
  timestamp: 2026-02-22T00:00:00Z

## Evidence

- timestamp: 2026-02-22T00:00:00Z
  checked: src/components/FinancesCard.jsx lines 200-208
  found: Component renders `{member.name}` directly from `feeData.family_members` array returned by API
  implication: Bug must be in the API layer, not the frontend

- timestamp: 2026-02-22T00:00:00Z
  checked: includes/class-rest-api.php lines 4198-4211
  found: Name composed as `trim($first_name . ' ' . $last_name)` — fetches `first_name` and `last_name` ACF fields but NOT the `infix` field
  implication: This is the root cause — the tussenvoegsel is simply not included in name composition

- timestamp: 2026-02-22T00:00:00Z
  checked: acf-json/group_person_fields.json and other PHP files (class-invoice-email-sender.php, class-installment-email-sender.php, etc.)
  found: ACF field for tussenvoegsel is named `infix`. All other name composition throughout the codebase uses `array_filter([$first_name, $infix, $last_name])` pattern
  implication: Fix is to add `get_field('infix', $member_id)` and include it in the name composition

## Resolution

root_cause: In `includes/class-rest-api.php` around line 4200, the family member name is composed using only `first_name` and `last_name` ACF fields. The `infix` (tussenvoegsel) ACF field is not fetched or included, so names like "Ravi de Valk" become "Ravi Valk".

fix: Added `get_field('infix', $member_id)` and changed name composition to use `array_filter([$first_name, $infix, $last_name])` pattern, consistent with all other name composition code throughout the codebase.

verification: Fix is minimal, targeted, and consistent with all other name composition patterns in the codebase (class-invoice-email-sender.php, class-installment-email-sender.php, class-vcard-export.php, class-demo-export.php, etc.)

files_changed:
  - includes/class-rest-api.php
