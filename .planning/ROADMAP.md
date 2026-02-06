# Roadmap: Stadion v19.0 Birthdate Simplification

## Overview

Simplify birthdate handling by moving from the Important Dates CPT to a simple person field, then removing the now-unnecessary Important Dates infrastructure. This reduces complexity and aligns with the Sportlink data model where birthdates are person attributes.

## Milestones

- **v19.0 Birthdate Simplification** - Phases 147-150 (in progress)

## Phases

### v19.0 Birthdate Simplification

- [x] **Phase 147: Birthdate Field & Widget** - Add birthdate to person, display in header, update dashboard widget
- [x] **Phase 148: Infrastructure Removal** - Delete data and remove Important Dates subsystem
- [x] **Phase 149: Fix vCard Birthday Export** - Update vCard export to read from person.birthdate
- [ ] **Phase 150: Update Documentation** - Fix stale "important dates" references in docs

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
**Plans:** 2 plans

Plans:
- [x] 148-01-PLAN.md — Delete production data, remove backend PHP code and tests
- [x] 148-02-PLAN.md — Remove frontend components, update documentation, deploy

### Phase 149: Fix vCard Birthday Export
**Goal**: vCard exports include BDAY field from person.birthdate
**Depends on**: Phase 148
**Gap Closure**: Closes integration gap from v19.0 audit (vCard reads from deleted personDates)
**Plans:** 1 plans

Plans:
- [x] 149-01-PLAN.md — Update vCard export to read from person.acf.birthdate

### Phase 150: Update Documentation
**Goal**: Documentation reflects new birthdate model without "important dates" references
**Depends on**: Phase 148
**Gap Closure**: Closes tech debt from v19.0 audit (5 docs with stale references)
**Plans:** 0 plans

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 147. Birthdate Field & Widget | 1/1 | Complete | 2026-02-06 |
| 148. Infrastructure Removal | 2/2 | Complete | 2026-02-06 |
| 149. Fix vCard Birthday Export | 1/1 | Complete | 2026-02-06 |
| 150. Update Documentation | 0/0 | Pending | - |

---
*Roadmap created: 2026-02-06*
*Phase 147 planned: 2026-02-06*
*Phase 147 completed: 2026-02-06*
*Phase 148 planned: 2026-02-06*
*Phase 148 completed: 2026-02-06*
*Phases 149-150 added: 2026-02-06 (gap closure from audit)*
*Phase 149 planned: 2026-02-06*
*Phase 149 completed: 2026-02-06*
