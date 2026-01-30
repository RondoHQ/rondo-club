---
phase: quick-027
plan: 01
subsystem: vog-management
tags: [vog, settings, commissies, volunteer-status]
dependency-graph:
  requires: [phase-121, phase-122]
  provides: [exempt-commissies-setting, volunteer-status-recalculation]
  affects: [vog-list, volunteer-status]
tech-stack:
  added: []
  patterns: [multi-select-checkbox-list, settings-recalculation]
file-tracking:
  key-files:
    created: []
    modified:
      - includes/class-vog-email.php
      - includes/class-rest-api.php
      - includes/class-volunteer-status.php
      - src/pages/Settings/Settings.jsx
decisions: []
metrics:
  duration: ~15min
  completed: 2026-01-30
---

# Quick Task 027: VOG Exempt Commissies Setting

**One-liner:** Added VOG settings to exempt commissies from VOG requirements with automatic volunteer status recalculation.

## What Was Built

This task adds the ability for administrators to exempt specific commissies from VOG (Verklaring Omtrent Gedrag) requirements. Some commissies have no interaction with children or data and should not require a VOG.

### Backend Changes

1. **VOGEmail class** (`includes/class-vog-email.php`):
   - Added `OPTION_EXEMPT_COMMISSIES` constant
   - Added `get_exempt_commissies()` method returning array of exempted commissie IDs
   - Added `update_exempt_commissies()` method to save exempted commissie IDs
   - Updated `get_all_settings()` to include `exempt_commissies` in response

2. **REST API** (`includes/class-rest-api.php`):
   - Extended VOG settings endpoint to accept `exempt_commissies` array parameter
   - Added `trigger_vog_recalculation()` method that recalculates volunteer status for all people
   - Returns `people_recalculated` count when exempt commissies change

3. **VolunteerStatus class** (`includes/class-volunteer-status.php`):
   - Made `calculate_and_update_status()` public for external calls
   - Updated `is_volunteer_position()` to check if commissie is in exempt list
   - Exempt commissie positions no longer count toward volunteer status for VOG purposes

### Frontend Changes

1. **Settings page** (`src/pages/Settings/Settings.jsx`):
   - Added `vogCommissies` state to store commissies list
   - Fetch commissies alongside VOG settings on mount
   - Added `exempt_commissies` to initial VOG settings state
   - Added multi-select checkbox list UI for exempt commissies in VOG tab
   - Updated save handler to display recalculation count when exempt commissies change

## Commits

| Hash | Message |
|------|---------|
| 38dd3f46 | feat(quick-027): add exempt commissies backend functionality |
| c78c0985 | feat(quick-027): add exempt commissies UI to VOG settings |
| cbe8d54c | chore(quick-027): bump version to 8.3.2 and update changelog |

## Files Modified

- `includes/class-vog-email.php` - Added exempt commissies option getter/setter
- `includes/class-rest-api.php` - Added recalculation trigger, updated VOG settings endpoint
- `includes/class-volunteer-status.php` - Made method public, added exempt check
- `src/pages/Settings/Settings.jsx` - Added commissies multi-select UI
- `package.json` - Version bump to 8.3.2
- `style.css` - Version bump to 8.3.2
- `CHANGELOG.md` - Added release notes

## Verification

- [x] API: GET /stadion/v1/vog/settings returns exempt_commissies array
- [x] API: POST /stadion/v1/vog/settings accepts exempt_commissies and returns people_recalculated count
- [x] UI: VOG settings tab shows commissies checkbox list
- [x] Functional: Changes to exempt commissies trigger volunteer status recalculation
- [x] Build: Production build succeeds
- [x] Deploy: Feature deployed to production

## Deviations from Plan

None - plan executed exactly as written.
