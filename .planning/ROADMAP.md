# Roadmap: Stadion v19.0 Birthdate Simplification

## Overview

Simplify birthdate handling by moving from the Important Dates CPT to a simple person field, then removing the now-unnecessary Important Dates infrastructure. This reduces complexity and aligns with the Sportlink data model where birthdates are person attributes.

## Milestones

- **v19.0 Birthdate Simplification** - Phases 147-148 (in progress)

## Phases

### v19.0 Birthdate Simplification

- [x] **Phase 147: Birthdate Field & Widget** - Add birthdate to person, display in header, update dashboard widget
- [ ] **Phase 148: Infrastructure Removal** - Delete data and remove Important Dates subsystem

## Phase Details

### Phase 147: Birthdate Field & Widget
**Goal**: Users can see birthdates on person profiles and dashboard shows upcoming birthdays
**Depends on**: Nothing (first phase of milestone)
**Requirements**: BDAY-01, BDAY-02, DASH-01, DASH-02
**Plans:** 1 plans

Plans:
- [x] 147-01-PLAN.md — Add birthdate ACF field, update person header, update dashboard widget

### Phase 148: Infrastructure Removal
**Goal**: Important Dates subsystem completely removed from codebase
**Depends on**: Phase 147
**Requirements**: DATA-01, REMV-01, REMV-02, REMV-03, REMV-04, REMV-05, REMV-06, REMV-07, REMV-08
**Plans:** 2 plans (estimated)

Plans:
- [ ] 148-01: Delete important_date data and remove CPT/taxonomy registration
- [ ] 148-02: Remove frontend components and navigation

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 147. Birthdate Field & Widget | 1/1 | Complete ✓ | 2026-02-06 |
| 148. Infrastructure Removal | 0/2 | Not started | - |

---
*Roadmap created: 2026-02-06*
*Phase 147 planned: 2026-02-06*
*Phase 147 completed: 2026-02-06*
