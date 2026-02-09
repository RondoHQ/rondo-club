---
phase: 161-configurable-matching-rules
plan: 01
subsystem: membership-fees
tags: [backend, api, configuration]
dependencies:
  requires: [160-02-configurable-family-discount]
  provides: [config-driven-fee-matching]
  affects: [membership-fees-calculation]
tech_stack:
  added: []
  patterns: [config-driven-matching, migration-helpers, optional-validation]
key_files:
  created: []
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php
decisions:
  - "Config-driven matching replaces hardcoded is_recreational_team() and is_donateur() checks"
  - "Migration auto-populates matching_teams for 'recreant' category from current database state"
  - "Migration auto-populates matching_werkfuncties=['Donateur'] for 'donateur' category"
  - "Priority order: youth > team matching > werkfunctie matching > age-class fallback"
  - "Team matching uses ANY match (not ALL) - admin explicitly selects which teams map to categories"
  - "Werkfunctie matching uses case-insensitive comparison with trimming"
metrics:
  duration_seconds: 251
  tasks_completed: 2
  files_modified: 2
  commits: 2
  completed_at: 2026-02-09T11:44:19Z
---

# Phase 161 Plan 01: Configurable Matching Rules Implementation Summary

Config-driven team and werkfunctie matching rules replace hardcoded fee calculation logic, completing v21.0's fully configurable fee category system.

## What Was Built

### Task 1: MembershipFees Config-Driven Matching (Commit 209ddd00)

**New Methods:**
- `find_recreational_team_ids()`: Queries all teams, returns IDs matching recreational criteria (used by migration)
- `get_category_by_team_match()`: Config-driven team matching via `matching_teams` arrays, filters deleted teams
- `get_category_by_werkfunctie_match()`: Config-driven werkfunctie matching via `matching_werkfuncties` arrays, case-insensitive
- `maybe_migrate_matching_rules()`: Auto-populates matching_teams for 'recreant', matching_werkfuncties for 'donateur', empty arrays for others

**Rewrote `calculate_fee()`:**
- **Old logic:** Hardcoded checks for `category === 'senior'`, `is_recreational_team()`, `is_donateur()`
- **New logic:** Priority flow: youth > team match > werkfunctie match > age-class fallback
- Removed all hardcoded category slug checks ('recreant', 'donateur', 'senior')
- Config-driven matching uses category objects from WordPress options

**Behavioral Changes:**
- Team matching: Now matches if ANY team appears in `matching_teams` (was: ALL teams must be recreational)
- Werkfunctie matching: Now matches if ANY werkfunctie matches (was: exactly one werkfunctie must be "Donateur")
- More flexible and correct for configurable model - admin explicitly selects which teams/werkfuncties map to categories

**Updated `get_categories_for_season()`:**
- Added `maybe_migrate_matching_rules()` call after `maybe_migrate_age_classes()`
- Applied to both direct load and copy-forward branches
- Migration persists only if changes were made

**Deprecated Methods:**
- `is_recreational_team()`: Now private with @deprecated tag, used only by migration
- `is_donateur()`: Now private with @deprecated tag, used only by migration

### Task 2: REST API Validation and Werkfuncties Endpoint (Commit 44f420c6)

**Extended `validate_category_config()`:**
- Added validation for `matching_teams` (optional, must be array of positive integers)
- Added validation for `matching_werkfuncties` (optional, must be array of non-empty strings)
- Both fields are optional - existing categories without them pass validation

**New Endpoint: `GET /rondo/v1/werkfuncties/available`**
- Returns distinct werkfunctie values from all people in database
- Admin-only permission
- Queries all people with `werkfuncties` meta, unserializes ACF data, returns unique sorted values
- Used by settings UI for multi-select dropdowns

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

**PHP Syntax:** No errors detected in modified files
**Build:** Frontend build succeeded (npm run build) - no PHP errors break the build
**Method Presence:** All new methods exist and are correctly structured
**calculate_fee():** No longer has hardcoded 'recreant'/'donateur'/'senior' slug checks
**Migration:** maybe_migrate_matching_rules() populates defaults for recreant and donateur
**Deprecation:** is_recreational_team() and is_donateur() marked @deprecated

## Integration Points

**WordPress Options:** Category objects stored in `rondo_membership_fees_{season}` now include:
- `matching_teams`: Array of team post IDs (integers)
- `matching_werkfuncties`: Array of werkfunctie strings

**REST API:** `/rondo/v1/membership-fees/settings` endpoint validates new fields via `validate_category_config()`
**Fee Calculation:** `calculate_fee()` uses config-driven matching for all non-youth categories
**Migration:** Existing categories auto-migrate on first load after upgrade

## Next Steps

**Phase 161-02:** Build React UI for configurable matching rules in Settings > Contributie page
- Team multi-select component (fetches from `/wp/v2/team`)
- Werkfunctie multi-select component (fetches from `/rondo/v1/werkfuncties/available`)
- Edit category modal with matching rules tabs
- Save via existing `/rondo/v1/membership-fees/settings` endpoint

## Self-Check: PASSED

**Modified files exist:**
- FOUND: includes/class-membership-fees.php
- FOUND: includes/class-rest-api.php

**Commits exist:**
- FOUND: 209ddd00 (feat(161-01): add config-driven matching rules to MembershipFees)
- FOUND: 44f420c6 (feat(161-01): add REST API validation and werkfuncties endpoint)

**Build artifacts:**
- Frontend build completed successfully (dist/ folder populated)
- No PHP syntax errors detected
