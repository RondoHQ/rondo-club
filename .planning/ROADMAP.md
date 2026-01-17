# Roadmap: Caelis v4.9 Dashboard & Calendar Polish

## Overview

This milestone delivers two polish features: fixed-height dashboard widgets for layout stability, and multi-calendar selection for Google Calendar connections. Both are independent UI/UX enhancements building on existing v4.0+ calendar and dashboard infrastructure.

## Phases

**Phase Numbering:**
- Continues from v4.8 (Phases 73-76)
- v4.9 starts at Phase 77

- [x] **Phase 77: Fixed Height Dashboard Widgets** - Consistent widget heights with internal scrolling
- [ ] **Phase 78: Multi-Calendar Selection** - Select multiple Google calendars per connection

## Phase Details

### Phase 77: Fixed Height Dashboard Widgets
**Goal**: All dashboard widgets have consistent fixed heights with internal scrolling when content overflows
**Depends on**: Nothing (first phase of milestone)
**Requirements**: DASH-01, DASH-02, DASH-03, DASH-04, DASH-05, DASH-06, DASH-07, DASH-08
**Success Criteria** (what must be TRUE):
  1. Dashboard layout remains stable during data loading and refresh
  2. Stats row widgets have uniform height
  3. Activity widget scrolls internally when content exceeds fixed height
  4. Meetings widget scrolls internally when content exceeds fixed height
  5. Todos widget scrolls internally when content exceeds fixed height
  6. Favorites widget scrolls internally when content exceeds fixed height
**Plans**: 77-01 (Fixed Height Dashboard Widgets)

### Phase 78: Multi-Calendar Selection
**Goal**: Users can select multiple calendars per Google Calendar connection
**Depends on**: Nothing (independent of Phase 77)
**Requirements**: CAL-01, CAL-02, CAL-03, CAL-04, CAL-05
**Success Criteria** (what must be TRUE):
  1. User can select multiple calendars in EditConnectionModal checkbox UI
  2. Selected calendars stored as array in connection settings
  3. Sync pulls events from all selected calendars
  4. Connection card displays count of selected calendars
  5. Existing single-calendar connections work without user action
**Plans**: 78-01 (Multi-Calendar Selection)

## Progress

**Execution Order:**
Phases execute in numeric order: 77 -> 78

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 77. Fixed Height Dashboard Widgets | 1/1 | Complete | 2026-01-17 |
| 78. Multi-Calendar Selection | 0/1 | Not started | - |
