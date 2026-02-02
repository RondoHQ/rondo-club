# Roadmap: Stadion v12.1 Contributie Forecast

## Overview

Add next season forecast capability to the contributie page, enabling budget planning. Users can toggle between current season (actual billing data) and next season (projected fees based on current membership). Builds on v12.0 fee calculation infrastructure with minimal new complexity.

## Milestones

- 🚧 **v12.1 Contributie Forecast** - Phases 129-131 (in progress)

## Phases

### 🚧 v12.1 Contributie Forecast (In Progress)

**Milestone Goal:** Enable budget planning with next season fee forecast

- [ ] **Phase 129: Backend Forecast Calculation** - Next season calculation logic and API support
- [ ] **Phase 130: Frontend Season Selector** - Dropdown UI and forecast display
- [ ] **Phase 131: Forecast Export** - Google Sheets export for forecast view

## Phase Details

### Phase 129: Backend Forecast Calculation
**Goal**: API returns forecast data for next season with correct fee calculations
**Depends on**: v12.0 (existing fee calculation infrastructure)
**Requirements**: FORE-01, FORE-02, FORE-03, FORE-04, FORE-05, API-01, API-02, API-03
**Success Criteria** (what must be TRUE):
  1. API accepts `forecast=true` parameter and returns next season key (2026-2027)
  2. Forecast response includes all current season members with 100% pro-rata fees
  3. Forecast correctly applies family discounts based on current address groupings
  4. Forecast response omits nikki_total and nikki_saldo fields (no billing data exists)
**Plans**: 1 plan
  - [ ] 129-01-PLAN.md - Add forecast parameter to /fees endpoint with next season calculation

### Phase 130: Frontend Season Selector
**Goal**: Users can switch between current and forecast view with clear visual distinction
**Depends on**: Phase 129
**Requirements**: UI-01, UI-02, UI-03, UI-04, UI-05, UI-06
**Success Criteria** (what must be TRUE):
  1. Season dropdown shows current season with "(huidig)" and next season with "(prognose)"
  2. Selecting forecast updates table to show projected fees
  3. Forecast view hides Nikki and Saldo columns
  4. Clear visual indicator (label/badge) shows this is a projection, not actual data
**Plans**: TBD

### Phase 131: Forecast Export
**Goal**: Users can export forecast data to Google Sheets for budget planning
**Depends on**: Phase 130
**Requirements**: EXP-01, EXP-02, EXP-03
**Success Criteria** (what must be TRUE):
  1. Export button works when viewing forecast
  2. Exported sheet title includes season and "(Prognose)" indicator
  3. Exported forecast excludes Nikki columns
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 129. Backend Forecast Calculation | v12.1 | 0/1 | Planned | - |
| 130. Frontend Season Selector | v12.1 | 0/TBD | Not started | - |
| 131. Forecast Export | v12.1 | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-02*
*Last updated: 2026-02-02*
