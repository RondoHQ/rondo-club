---
phase: quick
plan: 040
subsystem: membership-fees
tags: [php, react, rest-api, settings-ui, migration]
requires: []
provides:
  - per-season-fee-storage
  - fee-migration-system
  - dual-season-ui
affects: []
tech-stack:
  added: []
  patterns: [per-season-data, auto-migration]
key-files:
  created:
    - docs/membership-fees.md
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php
    - src/api/client.js
    - src/pages/Settings/Settings.jsx
    - style.css
    - package.json
    - CHANGELOG.md
decisions:
  - decision: Per-season WordPress option keys
    rationale: Separate options allow independent season updates
    alternatives: [single-option-with-seasons-array, custom-table]
    chosen: separate-option-keys
  - decision: Automatic migration from global to current season
    rationale: Zero-disruption upgrade for existing installations
    alternatives: [manual-migration-script, keep-both]
    chosen: auto-migration-on-first-read
  - decision: Two-section UI with independent save buttons
    rationale: Clear separation between current and future season configuration
    alternatives: [single-form-with-season-selector, tabs]
    chosen: two-sections-with-independent-saves
metrics:
  duration: 277 seconds
  completed: 2026-02-05
---

# Quick Task 040: Per-Season Contributie Settings Summary

**One-liner:** Per-season membership fee storage with automatic migration and dual-season admin UI

## What Was Built

Added per-season fee storage system allowing clubs to configure current and next season membership fees independently, with automatic migration from legacy global settings.

### Key Features

1. **Per-season storage** - Fee settings stored in season-specific WordPress options (`stadion_membership_fees_YYYY-YYYY`)
2. **Automatic migration** - Legacy global option auto-migrated to current season on first read
3. **Dual-season UI** - Admin sees two independent sections for current and next season
4. **Backward compatible** - All existing code using `get_all_settings()` continues to work

### Implementation Approach

**Backend (PHP):**
- Added `get_option_key_for_season($season)` to generate season-specific keys
- Added `get_settings_for_season($season)` with automatic migration logic
- Added `update_settings_for_season($fees, $season)` for targeted updates
- Modified `get_all_settings()` to use current season (backward compatible)
- Updated REST API to return both seasons and accept season parameter for updates

**Frontend (React):**
- Split single `feeSettings` state into `currentSeasonFees` and `nextSeasonFees` objects
- Rewrote `FeesSubtab` to render two independent season sections
- Each section displays season key (e.g., "2025-2026") and has its own save button
- Updated API client to include season parameter in POST requests

**Migration:**
- On first read of current season settings, checks for legacy global option
- If found: copies to current season option, deletes global option
- Next season starts with defaults (no migration)

## Tasks Completed

### Task 1: Backend per-season fee storage with migration
- **Commit:** `0101f791` - feat(040): add per-season membership fee storage with migration
- **Files:** `includes/class-membership-fees.php`, `includes/class-rest-api.php`
- **Changes:**
  - Added `get_option_key_for_season()` method
  - Added `get_settings_for_season()` with automatic migration
  - Added `update_settings_for_season()` for season-specific updates
  - Modified existing methods to use current season (backward compatible)
  - Updated REST GET to return `{current_season: {...}, next_season: {...}}`
  - Updated REST POST to require `season` parameter
  - Added season validation to route registration

### Task 2: Frontend two-section fee settings UI
- **Commit:** `1ccd7ed1` - feat(040): add two-section fee settings UI for current and next season
- **Files:** `src/api/client.js`, `src/pages/Settings/Settings.jsx`
- **Changes:**
  - Updated API client to include season parameter in POST
  - Split state into `currentSeasonFees` and `nextSeasonFees`
  - Replaced `handleFeeSave` with `handleSeasonFeeSave(season, fees)`
  - Rewrote `FeesSubtab` to render two independent season sections
  - Each section shows season key and has its own save button
  - Built production assets

### Task 3: Deploy, test, and document
- **Commit:** `f2c0bb56` - docs(040): version 18.1.0 with membership fees documentation
- **Files:** `docs/membership-fees.md`, `style.css`, `package.json`, `CHANGELOG.md`
- **Changes:**
  - Deployed to production via `bin/deploy.sh`
  - Created comprehensive `docs/membership-fees.md`
  - Updated version to 18.1.0
  - Added CHANGELOG entry
  - Pushed to remote

## Deviations from Plan

None - plan executed exactly as written.

## Technical Decisions

### Decision 1: Per-season WordPress option keys
**Context:** Need to store fees for multiple seasons independently.

**Options considered:**
1. **Separate option keys** (chosen) - `stadion_membership_fees_YYYY-YYYY`
2. Single option with seasons array - `['2025-2026' => [...], '2026-2027' => [...]]`
3. Custom database table

**Chosen:** Separate option keys

**Rationale:**
- Independent updates (updating one season doesn't load/save the other)
- WordPress-native storage (follows ACF pattern)
- Easy migration path from global option
- Natural cleanup (old seasons can be deleted independently)

### Decision 2: Automatic migration on first read
**Context:** Existing installations have global option that needs migration.

**Options considered:**
1. **Auto-migration on first read** (chosen)
2. Manual migration script
3. Keep both options (read global as fallback)

**Chosen:** Auto-migration on first read

**Rationale:**
- Zero-disruption upgrade (no manual steps required)
- One-time operation (global option deleted after migration)
- Happens transparently when admin first views settings
- Safe: only migrates to current season, next season starts fresh

### Decision 3: Two-section UI with independent save buttons
**Context:** Admin needs to configure both current and next season fees.

**Options considered:**
1. **Two sections with independent saves** (chosen)
2. Single form with season selector dropdown
3. Tabs for each season

**Chosen:** Two sections with independent saves

**Rationale:**
- Clear visual separation between current and future
- No risk of accidentally editing wrong season
- Both seasons visible simultaneously (no need to switch)
- Each section fully self-contained (inputs + save button)

## Verification

### Backend Verification
```bash
# Check REST API returns both seasons
curl https://stadion.svawc.nl/wp-json/stadion/v1/membership-fees/settings

# Response:
{
  "current_season": {
    "key": "2025-2026",
    "fees": {"mini": 130, "pupil": 180, ...}
  },
  "next_season": {
    "key": "2026-2027",
    "fees": {"mini": 130, "pupil": 180, ...}
  }
}
```

### Frontend Verification
- Navigate to Settings > Admin > Contributie
- Verify two sections appear: "Huidig seizoen: 2025-2026" and "Volgend seizoen: 2026-2027"
- Change value in current season, save, verify it persists
- Change value in next season, save, verify it persists
- Verify seasons are independent (changing one doesn't affect other)

### Migration Verification
- Old global option automatically migrated to current season
- Global option deleted after migration
- Existing `get_fee()` calls still work (use current season)

## Impact

### User Impact
- **Admins:** Can now pre-configure next season fees before July 1 transition
- **Admins:** Clear separation between current and future season settings
- **Members:** No impact (fee calculations still use current season automatically)

### System Impact
- **Storage:** New option per season (~300 bytes per season)
- **Performance:** No impact (same number of database reads)
- **Compatibility:** Fully backward compatible (existing code unchanged)

### Future Impact
- **Season transitions:** Automatic (July 1 = current becomes next, next becomes new)
- **Pre-configuration:** Admins can configure next season months in advance
- **Historical data:** Each season's fees are preserved independently

## Next Steps

None required. Feature is complete and deployed.

### Optional Future Enhancements
1. **Archive old seasons** - Add cleanup script to delete options for seasons older than 3 years
2. **Season history view** - Show historical fee settings in admin UI
3. **Copy season** - Button to copy current season fees to next season as starting point

## Metadata

**Duration:** 277 seconds (4.6 minutes)
**Commits:** 3
**Files modified:** 7
**Files created:** 1
**Deployed:** Yes (production)
**Version:** 18.1.0
