---
phase: quick-123
plan: "01"
subsystem: addresses
tags: [acf, address, data-model, react, php]
dependency_graph:
  requires: []
  provides: [structured-address-fields]
  affects: [person-detail, address-edit-modal, vcard-export, invoice-pdf, demo-export, membership-fees, wp-cli]
tech_stack:
  added: []
  patterns: [split-address-fields, structured-address-data]
key_files:
  created: []
  modified:
    - acf-json/group_person_fields.json
    - src/components/AddressEditModal.jsx
    - src/pages/People/PersonDetail.jsx
    - src/utils/vcard.js
    - includes/class-vcard-export.php
    - includes/class-invoice-pdf-generator.php
    - includes/class-demo-export.php
    - includes/class-membership-fees.php
    - includes/class-wp-cli.php
decisions:
  - "CardDAV import stores combined street component in street_name with empty house_number/house_number_addition (CardDAV ADR field does not split these)"
  - "get_family_key() now reads house_number + house_number_addition directly instead of calling extract_house_number() on a combined street string"
  - "DemoAnonymizer::generate_address() internal ['street'] key retained as-is; demo-export maps it to street_name when building ACF payload"
metrics:
  duration: "~15 minutes"
  completed_date: "2026-03-10"
  tasks_completed: 3
  files_modified: 9
---

# Quick-123: Replace street Field with street_name, house_number, house_number_addition

Split person address `street` ACF sub-field into three structured sub-fields (`street_name`, `house_number`, `house_number_addition`) and updated all consumers in React and PHP.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Replace ACF street sub-field | 97652270 | acf-json/group_person_fields.json, fixtures/demo-fixture.json (git-ignored) |
| 2 | Update React components | 75095a61 | AddressEditModal.jsx, PersonDetail.jsx, src/utils/vcard.js |
| 3 | Update all PHP code | 93ee51bf | class-vcard-export.php, class-invoice-pdf-generator.php, class-demo-export.php, class-membership-fees.php, class-wp-cli.php |

## Changes Made

### ACF Field Definition
- Replaced single `field_address_street` (100% width) with three new sub-fields:
  - `field_address_street_name` (50% width, label "Straatnaam")
  - `field_address_house_number` (25% width, label "Huisnummer")
  - `field_address_house_number_addition` (25% width, label "Toevoeging")

### React
- `AddressEditModal.jsx`: Three inputs in `grid grid-cols-4 gap-4` layout (street_name col-span-2, house_number col-span-1, addition col-span-1)
- `PersonDetail.jsx`: Concatenates `[street_name, house_number, house_number_addition].filter(Boolean).join(' ')`
- `src/utils/vcard.js`: Same concatenation for vCard ADR street component

### PHP
- `class-vcard-export.php`: Two vCard generation methods use `trim(street_name . ' ' . house_number . ' ' . house_number_addition)`; CardDAV import stores combined street in `street_name` with empty `house_number`/`house_number_addition`
- `class-invoice-pdf-generator.php`: Concatenates three fields for PDF address line
- `class-demo-export.php`: Both raw export and anonymized export use new field names
- `class-membership-fees.php`: `get_family_key()` reads `house_number` + `house_number_addition` directly (no longer parses from combined street string)
- `class-wp-cli.php`: Migration stores legacy full-address string in `street_name`; display concatenates three fields

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check

- [x] ACF JSON has street_name, house_number, house_number_addition — no old street field
- [x] AddressEditModal shows three inputs
- [x] PersonDetail concatenates correctly
- [x] No `address.street` (non-underscore) in src/
- [x] No `['street']` in includes/ address arrays (only `$fake_address['street']` which is DemoAnonymizer internal)
- [x] npm run lint: PASS
- [x] npm run build: PASS
- [x] Deployed to production: https://rondo.svawc.nl/

## Self-Check: PASSED
