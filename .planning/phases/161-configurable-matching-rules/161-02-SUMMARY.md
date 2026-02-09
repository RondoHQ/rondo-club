---
phase: 161-configurable-matching-rules
plan: 02
subsystem: membership-fees
tags: [frontend, ui, admin]
dependencies:
  requires: [161-01-configurable-matching-rules-backend]
  provides: [configurable-matching-rules-ui]
  affects: [fee-category-settings]
tech_stack:
  added: []
  patterns: [multi-select-checkboxes, data-fetching, optimistic-updates]
key_files:
  created: []
  modified:
    - src/pages/Settings/FeeCategorySettings.jsx
    - src/api/client.js
    - CHANGELOG.md
    - ../developer/src/content/docs/features/membership-fees.md
decisions:
  - "Team and werkfunctie matching UI uses multi-select checkbox lists for explicit selection"
  - "Team data fetched from /wp/v2/team (standard WordPress REST), werkfuncties from custom endpoint"
  - "Category cards show matching rules summary (count for teams, names for werkfuncties)"
  - "matching_teams and matching_werkfuncties saved as part of category object via existing save flow"
metrics:
  duration_seconds: 248
  tasks_completed: 2
  files_modified: 4
  commits: 2
  completed_at: 2026-02-09T11:50:51Z
---

# Phase 161 Plan 02: Configurable Matching Rules UI Summary

Admin UI for configuring team and werkfunctie matching rules per fee category completes v21.0's fully configurable fee system.

## What Was Built

### Task 1: Matching Rules UI and API Client (Commit d40bd8d6)

**Added API Client Method:**
- `getAvailableWerkfuncties()` in `prmApi` object
- Fetches from `GET /rondo/v1/werkfuncties/available` endpoint (admin-only)
- Returns array of distinct werkfunctie strings from database

**Updated FeeCategorySettings Component:**

**Data Fetching:**
- Added query for all teams: `wpApi.get('/wp/v2/team')` with 100 per_page limit
- Added query for available werkfuncties: `prmApi.getAvailableWerkfuncties()`
- Both queries use 5-minute stale time for caching
- Data passed to EditCategoryForm via props

**EditCategoryForm Updates:**
- Added `matching_teams` and `matching_werkfuncties` to form state
- Initialized from category props (empty arrays if not present)
- Added toggle handlers for teams and werkfuncties
- Included matching fields in `handleSubmit` → `onSave()` call

**Team Matching UI:**
- Multi-select checkbox list with scrollable container (max-height: 12rem)
- Each checkbox shows team title from `team.title.rendered`
- Checked state based on `formData.matching_teams.includes(team.id)`
- Help text explains "Leden die uitsluitend in geselecteerde teams spelen krijgen deze categorie"

**Werkfunctie Matching UI:**
- Multi-select checkbox list with scrollable container
- Each checkbox shows werkfunctie string
- Checked state based on `formData.matching_werkfuncties.includes(wf)`
- Help text explains matching behavior
- Empty state shows "Geen werkfuncties gevonden"

**SortableCategoryCard Display:**
- Added team count display: "Teams: {count} team(s) geselecteerd"
- Added werkfunctie names display: "Werkfuncties: {names joined with comma}"
- Both conditionally rendered (only if arrays non-empty)

**Save Flow:**
- Matching rules saved via existing `saveMutation.mutate()` flow
- Integrated with existing optimistic updates and error handling
- No changes needed to save handler (spreads all category data)

### Task 2: Version, Changelog, Docs, Deploy (Commit a6692bfb + developer repo)

**Version Bump:**
- Version already at 21.1.0 in `style.css` and `package.json` (from Phase 160)
- No version change needed

**Changelog Update:**
Added to v21.1.0 section:
- Configurable matching rules: each fee category can specify matching teams (by ID) and matching werkfuncties
- Admin UI for selecting teams and werkfuncties per fee category in Settings
- GET /rondo/v1/werkfuncties/available endpoint for listing distinct werkfunctie values
- Auto-migration: existing 'recreant' categories pre-populated with recreational team IDs, 'donateur' with Donateur werkfunctie
- `calculate_fee()` now uses config-driven team and werkfunctie matching instead of hardcoded checks
- `is_recreational_team()` and `is_donateur()` deprecated (kept for migration only)

**Developer Documentation:**
Updated `../developer/src/content/docs/features/membership-fees.md`:
- Added `matching_teams` and `matching_werkfuncties` to category object field definitions
- Documented team and werkfunctie matching behavior with priority order
- Added migration behavior section (auto-population for 'recreant' and 'donateur')
- Documented new `GET /rondo/v1/werkfuncties/available` endpoint
- Updated "Base Fee Determination" section with v21.1+ priority order
- Added deprecated methods section (`is_recreational_team()`, `is_donateur()`)
- Updated version history with Phase 161

**Build and Deploy:**
- Production build succeeded (1674.56 KiB precache, 78 entries)
- Deployed to production via `bin/deploy.sh`
- All caches cleared (WordPress, SiteGround Speed Optimizer, Dynamic Cache)

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

**Build:** Production build succeeded with zero errors
**Lint:** Pre-existing lint errors present (not introduced by this plan)
**Deployment:** Successfully deployed to production (https://stadion.svawc.nl/)
**Code Review:**
- Team query uses wpApi (correct namespace for /wp/v2/team)
- Werkfunctie query uses prmApi (correct namespace for /rondo/v1/werkfuncties/available)
- Both queries passed as props to EditCategoryForm
- Multi-select UI for teams and werkfuncties added after is_youth checkbox
- matching_teams and matching_werkfuncties included in save payload
- Category cards display matching rules summary

## Integration Points

**Frontend → Backend:**
- `GET /wp/v2/team`: Fetches team list for multi-select (standard WordPress REST)
- `GET /rondo/v1/werkfuncties/available`: Fetches distinct werkfunctie values (custom endpoint)
- `POST /rondo/v1/membership-fees/settings`: Saves category config including matching_teams and matching_werkfuncties

**UI Components:**
- EditCategoryForm: Self-contained form with team and werkfunctie multi-selects
- SortableCategoryCard: Displays matching rules summary in read-only view
- FeeCategorySettings: Manages data fetching and passes to child components

**Data Flow:**
1. FeeCategorySettings fetches teams and werkfuncties on mount
2. Passes data to EditCategoryForm via props
3. User selects teams and werkfuncties via checkboxes
4. Form includes matching_teams and matching_werkfuncties in save payload
5. Backend validates and persists to WordPress options
6. Category cards display matching rules summary

## Next Steps

**Phase 161 Complete:** v21.0 Per-Season Fee Categories milestone is now complete!

**Milestone completion includes:**
- Phase 155: Per-season fee category configuration with copy-forward
- Phase 156: Config-driven fee calculation with age class arrays
- Phase 157: REST API with full category CRUD and validation
- Phase 158: Admin UI for fee category management
- Phase 160: Configurable family discount percentages
- Phase 161: Configurable team and werkfunctie matching rules

**User verification:**
1. Navigate to Settings > Contributiecategorieën on production
2. Edit 'Recreant' category → verify recreational teams pre-selected (from migration)
3. Edit 'Donateur' category → verify 'Donateur' werkfunctie pre-selected
4. Save changes → verify matching rules persist
5. Switch to next season → verify matching rules copied forward
6. Check contributie list → verify fee calculations produce correct results

## Self-Check: PASSED

**Modified files exist:**
- FOUND: src/pages/Settings/FeeCategorySettings.jsx
- FOUND: src/api/client.js
- FOUND: CHANGELOG.md
- FOUND: ../developer/src/content/docs/features/membership-fees.md

**Commits exist:**
- FOUND: d40bd8d6 (feat(161-02): add matching rules UI to FeeCategorySettings)
- FOUND: a6692bfb (chore(161-02): update version and changelog for configurable matching rules)

**Deployment:**
- Production deployment completed successfully
- All caches cleared
- Production URL: https://stadion.svawc.nl/

**Build artifacts:**
- dist/ folder populated with 78 precached entries
- Frontend build succeeded with zero errors
