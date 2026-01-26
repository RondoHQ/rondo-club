---
phase: quick-003
plan: 01
subsystem: documentation
tags: [api, rest, docs]

dependency-graph:
  requires: []
  provides:
    - Updated People API documentation without is_favorite
    - Complete Important Dates API documentation
    - Complete Custom Fields API documentation
  affects: []

tech-stack:
  added: []
  patterns: []

file-tracking:
  key-files:
    modified:
      - docs/api-leden-crud.md
    created:
      - docs/api-important-dates.md
      - docs/api-custom-fields.md

decisions: []

metrics:
  duration: "~5 minutes"
  completed: "2026-01-26"
---

# Quick Task 003: API Documentation Update Summary

**One-liner:** Updated People API docs to remove deprecated is_favorite field, added complete documentation for Important Dates and Custom Fields APIs

## What Was Done

### Task 1: Remove is_favorite from People API docs
- Removed `is_favorite` row from Basic Information table
- Removed `is_favorite` from Create example JSON body
- Removed `is_favorite` from Create response example
- Removed `is_favorite` from Update example body
- Removed `is_favorite` from Get response example
- Updated JavaScript usage example to remove is_favorite update
- Updated PHP example to use `how_we_met` instead of `is_favorite`
- Updated cURL update example to change name fields instead

### Task 2: Create Important Dates API documentation
Created comprehensive API documentation (`docs/api-important-dates.md`) including:
- Authentication methods (Application Password and Nonce)
- All 5 CRUD endpoints documented
- Field reference with required (`date_value`, `related_people`) and optional fields
- `year_unknown` feature for dates without known year
- `is_recurring` for annual vs one-time dates
- JavaScript and cURL code examples
- Notes about auto-title generation and daily digest reminders

### Task 3: Create Custom Fields API documentation
Created comprehensive API documentation (`docs/api-custom-fields.md`) including:
- Admin endpoints (6 total) requiring `manage_options` capability
- User metadata endpoint for read-only field definitions
- All 14 supported field types with type-specific options
- Complete parameter reference tables organized by category:
  - Core parameters (label, type, name, instructions, etc.)
  - Number field options (min, max, step, prepend, append)
  - Date field options (display_format, return_format, first_day)
  - Select/Checkbox/Radio options (choices, allow_null, multiple, etc.)
  - True/False options (ui_on_text, ui_off_text)
  - Image/File options (preview_size, library, size constraints)
  - Relationship options (relation_post_types, filters)
  - Color picker options (enable_opacity)
  - Display options (show_in_list_view, list_view_order)
  - Validation options (unique)
- JavaScript and cURL code examples
- Notes about soft delete, type immutability, and ACF integration

## Commits

| Hash | Description |
|------|-------------|
| 65c9400 | docs(quick-003): remove is_favorite from People API documentation |
| e984c7b | docs(quick-003): add Important Dates API documentation |
| 11a9278 | docs(quick-003): add Custom Fields API documentation |

## Verification

- [x] `grep "is_favorite" docs/api-leden-crud.md` returns 0 matches
- [x] `docs/api-important-dates.md` exists with all CRUD endpoints
- [x] `docs/api-custom-fields.md` exists with all 7 endpoints documented

## Deviations from Plan

None - plan executed exactly as written.
