---
phase: quick-126
plan: "01"
subsystem: People/Addresses
tags: [acf, addresses, country-code, sync]
dependency_graph:
  requires: []
  provides: [country_code field in addresses repeater]
  affects: [AddressEditModal, PersonDetail, rondo-sync]
tech_stack:
  added: []
  patterns: [ACF sub_field addition, React form field addition]
key_files:
  created: []
  modified:
    - acf-json/group_person_fields.json
    - src/components/AddressEditModal.jsx
    - src/pages/People/PersonDetail.jsx
decisions:
  - Country code displayed in parentheses after country name (e.g. "Netherlands (NL)") for readable display
  - State/Country row expanded to 3-column grid to fit Landcode input alongside Provincie and Land
metrics:
  duration: "~10 minutes"
  completed: "2026-03-11"
  tasks_completed: 2
  files_modified: 3
---

# Quick Task 126: Add country_code next to country name in addresses

**One-liner:** Added `country_code` ACF field to addresses repeater and exposed it in AddressEditModal (Landcode input in 3-column grid) and PersonDetail (shown as "Netherlands (NL)").

## What Was Done

Rondo-sync already sent `country_code` in address payloads but the field didn't exist in rondo-club, causing values to be silently dropped. This task wired up the full stack:

1. **ACF JSON** — Added `field_address_country_code` text field (maxlength 3, 25% width) as the last sub_field in the addresses repeater, after `field_address_country`.

2. **AddressEditModal** — Added `country_code` to defaultValues, both reset calls (edit and new), and handleFormSubmit. Changed the "State and Country" 2-column grid to a 3-column grid, adding a Landcode input after Land.

3. **PersonDetail** — Updated address display to show country code in parentheses when present (e.g. "Netherlands (NL)"), falling back to just country name when not set.

## Verification

- ACF JSON valid, `field_address_country_code` present
- ESLint passes with zero warnings on both modified React files
- `npm run build` succeeded
- Deployed to production at https://rondo.svawc.nl/

## Deviations from Plan

None — plan executed exactly as written.

## Commits

- `724f1af4` — feat(quick-126): add country_code ACF field to addresses repeater
- `371d0597` — feat(quick-126): add country_code to address edit modal and detail display
