# Roadmap: Stadion v13.0 Discipline Cases

## Overview

This milestone adds discipline case tracking to Stadion. Cases are synced from Sportlink (read-only in UI) and displayed in a dedicated list page and on person profiles. Access is restricted via a new `fairplay` capability, allowing sensitive disciplinary data to be visible only to authorized users.

## Milestones

- **v13.0 Discipline Cases** - Phases 132-134 (in progress)

## Phases

**Phase Numbering:**
- Continues from v12.1 (ended at Phase 131)
- Integer phases (132, 133, 134): Planned milestone work
- Decimal phases (132.1, etc.): Urgent insertions if needed

### v13.0 Discipline Cases (In Progress)

**Milestone Goal:** Enable tracking and viewing of discipline cases from Sportlink with capability-based access control.

- [x] **Phase 132: Data Foundation** - CPT, ACF fields, seizoen taxonomy, REST endpoints
- [x] **Phase 133: Access Control** - Fairplay capability registration and enforcement
- [x] **Phase 134: Discipline Cases UI** - List page and person integration

## Phase Details

### Phase 132: Data Foundation
**Goal**: Backend infrastructure for discipline case data storage and API access
**Depends on**: Nothing (foundation phase)
**Requirements**: DATA-01, DATA-02, DATA-03, DATA-04
**Success Criteria** (what must be TRUE):
  1. `discipline_case` CPT exists with correct labels (Tuchtzaak/Tuchtzaken)
  2. ACF field group contains all case fields (dossier-id, person, match-date, match-description, team-name, charge-codes, charge-description, sanction-description, processing-date, administrative-fee, is-charged)
  3. `seizoen` taxonomy is registered, non-hierarchical, and REST-enabled
  4. REST API returns discipline cases with all fields via standard wp/v2 endpoints
  5. Cases can be created/updated via REST (Sportlink sync compatibility)
**Plans**: 1 plan

Plans:
- [x] 132-01-PLAN.md — Register discipline_case CPT, seizoen taxonomy, and ACF fields

### Phase 133: Access Control
**Goal**: Capability-based access restriction for discipline case data
**Depends on**: Phase 132
**Requirements**: ACCESS-01, ACCESS-02, ACCESS-03, ACCESS-04
**Success Criteria** (what must be TRUE):
  1. `fairplay` capability is registered and assignable to users
  2. Administrators automatically have fairplay capability after theme activation
  3. Users without fairplay capability cannot access /discipline-cases route (redirected or denied)
  4. Users without fairplay capability do not see Tuchtzaken tab on person detail pages
**Plans**: 1 plan

Plans:
- [x] 133-01-PLAN.md — Register fairplay capability and implement UI access control

### Phase 134: Discipline Cases UI
**Goal**: Complete user interface for viewing discipline cases
**Depends on**: Phase 132, Phase 133
**Requirements**: LIST-01, LIST-02, LIST-03, LIST-04, LIST-05, PERSON-01, PERSON-02, PERSON-03, PERSON-04
**Success Criteria** (what must be TRUE):
  1. Discipline cases list page displays at /discipline-cases route
  2. Table shows Person, Match, Date, Sanction, Season columns
  3. Season filter dropdown filters cases by seizoen taxonomy
  4. Date column is sortable (ascending/descending)
  5. Navigation item for discipline cases visible only to fairplay users
  6. Person detail page shows Tuchtzaken tab (fairplay users only)
  7. Tuchtzaken tab displays all discipline cases linked to that person
  8. Case details are read-only (match, charges, sanctions, fee displayed)
**Plans**: 3 plans

Plans:
- [x] 134-01-PLAN.md — API integration and React hooks for discipline cases
- [x] 134-02-PLAN.md — List page with table view and season filter
- [x] 134-03-PLAN.md — Person detail tab integration

## Progress

**Execution Order:**
Phases execute in numeric order: 132 -> 133 -> 134

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 132. Data Foundation | 1/1 | Complete | 2026-02-03 |
| 133. Access Control | 1/1 | Complete | 2026-02-03 |
| 134. Discipline Cases UI | 3/3 | Complete | 2026-02-03 |

---
*Roadmap created: 2026-02-03*
*Last updated: 2026-02-03 after Phase 134 execution*
