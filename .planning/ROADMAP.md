# Roadmap: Stadion v19.0 Birthdate Simplification

## Overview

Simplify birthdate handling by moving from the Important Dates CPT to a simple person field, then removing the now-unnecessary Important Dates infrastructure. This reduces complexity and aligns with the Sportlink data model where birthdates are person attributes.

## Milestones

- **v19.0 Birthdate Simplification** - Phases 147-148 (in progress)

## Phases

### v19.0 Birthdate Simplification

- [ ] **Phase 147: Birthdate Field & Widget** - Add birthdate to person, display in header, update dashboard widget
- [ ] **Phase 148: Infrastructure Removal** - Delete data and remove Important Dates subsystem

## Phase Details

### Phase 147: Birthdate Field & Widget
**Goal**: Users can see birthdates on person profiles and dashboard shows upcoming birthdays
**Depends on**: Nothing (first phase of milestone)
**Requirements**: BDAY-01, BDAY-02, DASH-01, DASH-02
**Success Criteria** (what must be TRUE):
  1. Person ACF field group includes birthdate date picker field
  2. Birthdate field is read-only in the UI (Sportlink synced)
  3. Person header displays age followed by birthdate in format "34 jaar (6 feb)"
  4. Persons without birthdate show no date parenthetical
  5. Upcoming birthdays widget queries person birthdate meta field
  6. Birthday matching uses month/day comparison for upcoming logic
  7. Widget shows same birthday information as before (name, date, days until)

Plans:
- [ ] 147-01: Add birthdate ACF field, update person header, update dashboard widget

### Phase 148: Infrastructure Removal
**Goal**: Important Dates subsystem completely removed from codebase
**Depends on**: Phase 147
**Requirements**: DATA-01, REMV-01, REMV-02, REMV-03, REMV-04, REMV-05, REMV-06, REMV-07, REMV-08
**Success Criteria** (what must be TRUE):
  1. All important_date posts deleted from production database
  2. important_date CPT registration code removed
  3. date_type taxonomy registration code removed
  4. "Datums" navigation item no longer appears in sidebar
  5. DatesList page and route removed from React app
  6. ImportantDateModal component removed
  7. Important dates card no longer appears on PersonDetail
  8. Timeline and REST endpoints no longer include important date entries
  9. No unused imports or dead code related to Important Dates remains

Plans:
- [ ] 148-01: Delete important_date data and remove CPT/taxonomy registration
- [ ] 148-02: Remove frontend components and navigation

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 147. Birthdate Field & Widget | 0/1 | Not started | - |
| 148. Infrastructure Removal | 0/2 | Not started | - |

---
*Roadmap created: 2026-02-06*
