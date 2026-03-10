---
phase: quick-124
plan: 01
subsystem: invoice-pdf
tags: [invoice, pdf, address, billing, factuur]
dependency_graph:
  requires: []
  provides: [Factuur-labeled address lookup in invoice PDF]
  affects: [includes/class-invoice-pdf-generator.php, docs/prd/Invoice-Fields.md]
tech_stack:
  added: []
  patterns: [addresses repeater label filtering, local closure for formatting]
key_files:
  modified:
    - includes/class-invoice-pdf-generator.php
    - docs/prd/Invoice-Fields.md
decisions:
  - "Use strcasecmp for case-insensitive Factuur label matching"
  - "Extract address formatting into local closure to avoid duplication"
  - "Fall back to first address when no Factuur-labeled entry exists"
metrics:
  duration: "~5 minutes"
  completed: "2026-03-10"
  tasks_completed: 2
  files_modified: 2
---

# Phase quick Plan 124: Remove factuur-adres field, use addresses repeater Summary

Invoice PDF generator updated to prefer the "Factuur"-labeled address from the addresses repeater, falling back to the first address; `factuur-adres` ACF field references removed from documentation.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Update invoice PDF address resolution to prefer Factuur-labeled address | f9151312 | includes/class-invoice-pdf-generator.php |
| 2 | Clean up factuur-adres references in documentation | 252af5b0 | docs/prd/Invoice-Fields.md |

## Decisions Made

1. **Case-insensitive label matching** — Used `strcasecmp` to match "Factuur" label so minor casing variations in Rondo Sync output won't cause silent fallback to wrong address.
2. **Local closure for formatting** — Address-to-street/city formatting extracted into `$format_address` closure to satisfy DRY requirement without adding a separate class method (used only in this one place).
3. **Graceful fallback** — When no Factuur-labeled entry is found, falls back to `$addresses[0]` exactly as before, maintaining backward compatibility.

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- `includes/class-invoice-pdf-generator.php` contains `address_label` and `Factuur` logic
- `docs/prd/Invoice-Fields.md` contains no `factuur-adres` references
- `grep -ri "factuur.adres\|factuur_adres" includes/ src/` returns no matches
- ESLint passes with 0 warnings
- Both commits exist: f9151312, 252af5b0
