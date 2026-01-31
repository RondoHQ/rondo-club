# Roadmap: Stadion v12.0 Membership Fees

## Overview

This milestone implements a membership fee calculation system for Stadion. Starting with configurable fee settings, then building the calculation engine for age-based fees, adding family discount logic with address grouping, and finally integrating pro-rata calculations with a user-facing list view. The system calculates fees but does not track payments (deferred to future milestone).

## Milestones

- 🚧 **v12.0 Membership Fees** - Phases 123-126 (in progress)

## Phases

**Phase Numbering:**
- Integer phases (123, 124, 125, 126): Planned milestone work
- Decimal phases (123.1, 123.2): Urgent insertions if needed (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 123: Settings & Backend Foundation** - Fee configuration UI and calculation service scaffolding
- [x] **Phase 124: Fee Calculation Engine** - Age-based and flat fee calculations
- [ ] **Phase 125: Family Discount** - Address grouping and tiered discount logic
- [ ] **Phase 126: Pro-rata & UI** - Join date calculation, list page, and filters

## Phase Details

### Phase 123: Settings & Backend Foundation
**Goal**: Admin can configure all fee amounts through settings UI
**Depends on**: Nothing (first phase of milestone)
**Requirements**: SET-01, SET-02, SET-03, SET-04, SET-05, SET-06
**Success Criteria** (what must be TRUE):
  1. Admin sees "Contributie" settings subtab under Settings
  2. Admin can set Mini fee amount (default: 130)
  3. Admin can set Pupil fee amount (default: 180)
  4. Admin can set Junior fee amount (default: 230)
  5. Admin can set Senior fee amount (default: 255)
  6. Admin can set Recreant fee amount (default: 65)
  7. Admin can set Donateur fee amount (default: 55)
  8. Fee amounts persist across page reloads
**Plans**: 2 plans

Plans:
- [x] 123-01: Settings backend and calculation service class
- [x] 123-02: Settings UI subtab with fee configuration form

### Phase 124: Fee Calculation Engine
**Goal**: System calculates correct base fees based on member type and age group
**Depends on**: Phase 123
**Requirements**: FEE-01, FEE-02, FEE-03, FEE-04, FEE-05
**Success Criteria** (what must be TRUE):
  1. Mini members (JO6-JO7) get configured Mini fee
  2. Pupil members (JO8-JO11) get configured Pupil fee
  3. Junior members (JO12-JO19) get configured Junior fee
  4. Senior members (JO23/Senioren) get configured Senior fee
  5. Recreant/Walking Football members get flat Recreant fee
  6. Donateur members get flat Donateur fee
  7. "Meiden" suffix in leeftijdsgroep is stripped before matching
  8. Members without leeftijdsgroep are excluded from age-based calculation
**Plans**: 2 plans

Plans:
- [x] 124-01-PLAN.md — Core calculation logic (age group parsing, member type detection, calculate_fee method)
- [x] 124-02-PLAN.md — Season snapshot and caching (season key, snapshot storage, get_fee_for_person API)

### Phase 125: Family Discount
**Goal**: Youth members at same address receive tiered family discounts
**Depends on**: Phase 124
**Requirements**: FAM-01, FAM-02, FAM-03, FAM-04, FAM-05
**Success Criteria** (what must be TRUE):
  1. System groups members by normalized address (street, number, postal code)
  2. First (most expensive) youth member pays full fee
  3. Second youth member at same address gets 25% discount
  4. Third and subsequent youth members get 50% discount
  5. Discount applies to youngest/cheapest member first (descending by base fee)
  6. Recreants and donateurs do not receive family discount
**Plans**: TBD

Plans:
- [ ] 125-01: Address normalization and family grouping
- [ ] 125-02: Tiered discount calculation logic

### Phase 126: Pro-rata & UI
**Goal**: Users can view calculated fees with pro-rata and filter by address issues
**Depends on**: Phase 125
**Requirements**: NAV-01, NAV-02, PRO-01, PRO-02, PRO-03, FIL-01
**Success Criteria** (what must be TRUE):
  1. "Contributie" section appears in sidebar below Leden, above VOG
  2. List displays Name, Age Group, Base Fee, Family Discount, Final Amount columns
  3. July-September joins pay 100% of calculated fee
  4. October-December joins pay 75% of calculated fee
  5. January-March joins pay 50% of calculated fee
  6. April-June joins pay 25% of calculated fee
  7. Pro-rata applies after family discount calculation
  8. User can filter to show address mismatches (siblings at different addresses)
**Plans**: TBD

Plans:
- [ ] 126-01: Pro-rata calculation based on Sportlink join date
- [ ] 126-02: Contributie list page and REST endpoint
- [ ] 126-03: Address mismatch filter

## Progress

**Execution Order:**
Phases execute in numeric order: 123 -> 124 -> 125 -> 126

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 123. Settings & Backend Foundation | 2/2 | ✓ Complete | 2026-01-31 |
| 124. Fee Calculation Engine | 2/2 | ✓ Complete | 2026-01-31 |
| 125. Family Discount | 0/2 | Not started | - |
| 126. Pro-rata & UI | 0/3 | Not started | - |

---
*Roadmap created: 2026-01-31*
*Last updated: 2026-01-31 (Phase 124 complete)*
