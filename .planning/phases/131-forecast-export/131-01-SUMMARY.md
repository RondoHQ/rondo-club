---
phase: 131-forecast-export
plan: 01
title: "Add forecast parameter to export-fees endpoint with conditional columns"
subsystem: api-integration
tags: [google-sheets, export, forecast, contributie]
requires:
  - 130-01
provides:
  - forecast-export-support
  - conditional-sheet-columns
affects:
  - future-export-features
tech-stack:
  added: []
  patterns:
    - conditional-spreadsheet-formatting
    - parameter-based-column-selection
key-files:
  created: []
  modified:
    - includes/class-rest-google-sheets.php
    - src/pages/Contributie/ContributieList.jsx
decisions:
  - slug: forecast-export-title-format
    label: "Forecast export title includes (Prognose) suffix"
    detail: "Spreadsheet title format: 'Contributie YYYY-YYYY (Prognose) - DATE'"
  - slug: forecast-eight-column-layout
    label: "Forecast exports use 8-column layout (no Nikki columns)"
    detail: "Nikki Total and Saldo columns excluded from forecast spreadsheets"
metrics:
  duration: ~15m
  completed: 2026-02-02
---

# Phase 131 Plan 01: Add forecast parameter to export-fees endpoint with conditional columns Summary

Forecast export to Google Sheets with conditional columns and (Prognose) title indicator

## What Was Built

Added forecast parameter support to Google Sheets export endpoint for contributie data:

**Backend modifications:**
- export_fees endpoint accepts optional `forecast` boolean parameter
- When forecast=true: uses next season, 100% pro-rata, excludes Nikki columns
- Spreadsheet title includes "(Prognose)" suffix for forecast exports
- Column count dynamic (8 for forecast, 10 for current season)
- Nikki column formatting conditionally applied only for current season

**Frontend modifications:**
- ContributieList passes `forecast: isForecast` to export API call
- Export button works correctly in both current and forecast views

## Requirements Coverage

| Requirement | Description | Implementation |
|-------------|-------------|----------------|
| EXP-01 | Export button works when viewing forecast | Tasks 1+2 - forecast parameter passed end-to-end |
| EXP-02 | Exported sheet title includes season and (Prognose) | Task 1 - title logic conditionally adds suffix |
| EXP-03 | Exported forecast excludes Nikki columns | Task 1 - conditional column logic in data/formatting |

## Technical Implementation

### Parameter Flow
1. Frontend: `isForecast` state → `prmApi.exportFeesToSheets({ forecast: isForecast })`
2. REST route: `forecast` parameter registered (boolean, default false)
3. `fetch_fee_data()`: Uses forecast parameter to determine season and calculation method
4. `build_fee_spreadsheet_data()`: Uses forecast parameter for conditional headers/columns

### Conditional Logic
- **Season selection:** Current season vs next season
- **Pro-rata:** Actual pro-rata vs 100% for forecast
- **Nikki data:** Included for current, excluded for forecast
- **Column count:** 10 vs 8 columns
- **Title format:** "Contributie YYYY-YYYY" vs "Contributie YYYY-YYYY (Prognose)"

## Tasks Completed

| Task | Name | Type | Commit | Status |
|------|------|------|--------|--------|
| 1 | Add forecast parameter to export_fees endpoint | auto | fc98ed5 | Complete |
| 2 | Pass forecast parameter in frontend export call | auto | f460a83 | Complete |
| 3 | Human verification | checkpoint:human-verify | - | Approved |

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

**Ready to proceed:** Yes

Phase 131 complete. Forecast export functionality fully integrated with existing contributie system.

**Milestone v12.1 status:** All 3 phases complete (129, 130, 131)
- Backend forecast calculation ✓
- Frontend season selector ✓
- Export integration ✓

**Recommended next steps:**
1. Complete milestone v12.1 and mark todo as done
2. User acceptance testing across full forecast workflow
3. Consider next milestone from ROADMAP.md

## Verification Log

**UAT completed:** 2026-02-02

Verified:
- Current season export: 10 columns, no "(Prognose)" in title
- Forecast export: 8 columns, "(Prognose)" in title
- All members show 100% pro-rata in forecast
- Currency and percentage formatting correct in both modes
- No Nikki columns appear in forecast spreadsheet

**Status:** Approved

## Files Modified

### includes/class-rest-google-sheets.php
- Added forecast parameter to export_fees route registration
- Modified export_fees() to extract and pass forecast parameter
- Updated spreadsheet title logic to include "(Prognose)" suffix
- Made column count dynamic (8 vs 10) based on forecast
- Conditional Nikki column formatting (only when not forecast)
- Modified fetch_fee_data() to accept forecast parameter and use next season
- Updated forecast calculation logic (100% pro-rata, no Nikki data)
- Modified build_fee_spreadsheet_data() for conditional headers and columns

### src/pages/Contributie/ContributieList.jsx
- Updated handleExportToSheets() to pass `forecast: isForecast` parameter

## Lessons Learned

**What worked well:**
- Conditional column logic cleanly separated forecast from current season
- Parameter-based approach allows single endpoint to handle both modes
- Existing isForecast state made frontend integration trivial

**Patterns established:**
- Conditional spreadsheet formatting based on boolean flags
- Parameter-driven column selection in export endpoints
- Forecast mode consistently applies full year assumptions (100% pro-rata)

## Related Documentation

- Phase 129: Backend forecast calculation logic
- Phase 130: Frontend season selector implementation
- API: `/stadion/v1/google-sheets/export-fees` with forecast parameter
