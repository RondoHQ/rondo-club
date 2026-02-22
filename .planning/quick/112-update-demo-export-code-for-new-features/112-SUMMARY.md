---
phase: 112-update-demo-export
plan: 01
subsystem: demo-system
tags: [demo, export, import, invoices, finance]
dependency_graph:
  requires: [rondo_invoice post type, FinanceConfig options, capability maps]
  provides: [updated demo fixture with invoices, finance settings, capability maps]
  affects: [demo.rondo.club]
tech_stack:
  added: []
  patterns: [fixture export/import with anonymization, ref map for invoice post type]
key_files:
  created: []
  modified:
    - includes/class-demo-export.php
    - includes/class-demo-import.php
decisions:
  - Fixture piped via SSH failed — used SCP download-then-upload pattern instead
  - Invoice fixture uploaded after deploy-demo.sh (deploy overwrites fixtures/ dir)
  - installment amounts anonymized by splitting total evenly across N installments
metrics:
  duration: 18 minutes
  completed: 2026-02-22
  tasks_completed: 2
  files_modified: 2
---

# Quick Task 112: Update Demo Export Code for New Features

**One-liner:** Added invoice export/import (with installment meta, date shifting, anonymized amounts) plus finance config and capability map export/import to the demo fixture system.

## What Was Done

### Task 1: Code Changes (commit 2bb4e816)

**class-demo-export.php:**
- Added `invoice` key to `$ref_maps` array
- Updated `build_ref_maps()` to include `rondo_invoice` with all four invoice statuses
- Added `export_invoices()` method that exports all `rondo_invoice` posts with:
  - Anonymized `total_amount` (50-300 for membership, 10-100 for discipline)
  - Demo invoice numbers (`DEMO-{year}-{seq}`)
  - Resolved `person` and `discipline_case` refs
  - Installment metadata (amounts, status, due dates) — Mollie-specific keys stripped
  - `seizoen` taxonomy term slug
  - `payment_link`, `pdf_path`, `qr_code_path` set to null
- Added `export_invoices()` call between discipline_cases and todos
- Added `invoices` to `record_counts` and fixture array
- Updated `export_person_post_meta()` to include `_exclude_from_contributie`
- Updated `export_settings()` to add:
  - Finance config options (anonymized: org_name, org_address, IBAN, email)
  - Email templates replaced with demo placeholders
  - `active_payment_provider` hardcoded to "mollie"
  - `rondo_functie_capability_map` and `rondo_commissie_capability_map`

**class-demo-import.php:**
- Added `rondo_invoice` to `$post_types` in `clean()` for deletion
- Added dynamic `rondo_finance_%` option cleanup in `clean()`
- Added `rondo_functie_capability_map` and `rondo_commissie_capability_map` to static `$option_keys` in `clean()`
- Added `invoices` to `$required_keys` in `read_fixture()`
- Updated record counts log line to include invoices
- Added `import_invoices()` method with:
  - `wp_insert_post` for each invoice
  - ACF field updates including resolved person and line_items refs
  - Post meta for installment plan, count, season, and per-installment data
  - Date shifting for sent_date, due_date, and installment due dates
  - Season slug shifting for `_invoice_season`
  - `seizoen` taxonomy term assignment
- Updated `import()` to call `import_invoices()` after `import_discipline_cases()`

### Task 2: Deploy and Run Export/Import

- Deployed updated theme to production
- Ran export on production: 3978 people, 60 teams, 30 commissies, 112 discipline cases, 10 invoices, 1058 comments
- Copied fixture to demo site via SCP (download + upload, piped SSH approach failed)
- Deployed updated theme to demo site
- Ran `wp rondo demo import --clean` on demo site
- Import result: 3978 people, 60 teams, 30 commissies, 112 discipline cases, 10 invoices, 1058 comments, 30 settings

## Verification

- `wp post list --post_type=rondo_invoice --post_status=any --format=count` → 10
- `wp post list --post_type=person --post_status=any --format=count` → 3978
- `wp option get rondo_finance_org_name` → "Demo Club"
- `wp option get rondo_functie_capability_map` → capability map array populated

## Deviations from Plan

### Auto-fixed Issues

None — plan executed as written, with one operational note:

**[Operational] Fixture file upload timing**
- **Found during:** Task 2, Step 5
- **Issue:** `bin/deploy-demo.sh` syncs all theme files including `fixtures/` directory with `--delete`, overwriting the newly uploaded fixture with the old one from the local repo
- **Fix:** Uploaded the new fixture via SCP again after the deploy completed
- **Future note:** Always upload the fixture file AFTER running `bin/deploy-demo.sh`

## Self-Check: PASSED

- includes/class-demo-export.php: FOUND
- includes/class-demo-import.php: FOUND
- Commit 2bb4e816: FOUND
