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
- [x] **Phase 125: Family Discount** - Address grouping and tiered discount logic
- [x] **Phase 126: Pro-rata & UI** - Join date calculation, list page, and filters
- [ ] **Phase 127: Fee Caching** - Fix pro-rata field, denormalize fees to person meta
- [ ] **Phase 128: Google Sheets Export** - Export fee data to Google Sheets

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
  1. System groups members by normalized address (postal code + house number)
  2. First (most expensive) youth member pays full fee
  3. Second youth member at same address gets 25% discount
  4. Third and subsequent youth members get 50% discount
  5. Discount applies to cheapest member first (descending by base fee)
  6. Recreants and donateurs do not receive family discount
**Plans**: 2 plans

Plans:
- [x] 125-01-PLAN.md — Address normalization and family grouping (normalize_postal_code, extract_house_number, get_family_key, build_family_groups)
- [x] 125-02-PLAN.md — Tiered discount calculation logic (get_family_discount_rate, calculate_fee_with_family_discount)

### Phase 126: Pro-rata & UI
**Goal**: Users can view calculated fees with pro-rata
**Depends on**: Phase 125
**Requirements**: NAV-01, NAV-02, PRO-01, PRO-02, PRO-03
**Success Criteria** (what must be TRUE):
  1. "Contributie" section appears in sidebar below Leden, above VOG
  2. List displays Name, Age Group, Base Fee, Family Discount, Final Amount columns
  3. July-September joins pay 100% of calculated fee
  4. October-December joins pay 75% of calculated fee
  5. January-March joins pay 50% of calculated fee
  6. April-June joins pay 25% of calculated fee
  7. Pro-rata applies after family discount calculation
**Plans**: 2 plans

Plans:
- [x] 126-01-PLAN.md — Pro-rata calculation based on Sportlink join date (get_prorata_percentage, calculate_full_fee)
- [x] 126-02-PLAN.md — Contributie list page and REST endpoint (sidebar nav, /fees endpoint, ContributieList.jsx)

### Phase 127: Fee Caching
**Goal**: Fees are cached per person for fast list loading and correct pro-rata calculation
**Depends on**: Phase 126
**Requirements**: PRO-04, CACHE-01, CACHE-02
**Success Criteria** (what must be TRUE):
  1. Pro-rata uses `lid-sinds` field (not `registratiedatum`)
  2. Calculated fees stored in person meta (_fee_base, _fee_family_discount, _fee_prorata, _fee_final)
  3. Fees recalculated when age group changes
  4. Fees recalculated when address changes
  5. Fees recalculated when team membership changes
  6. Fees recalculated when lid-sinds changes
  7. Fees recalculated when fee settings change (bulk recalculation)
  8. Contributie list loads in < 1 second
**Plans**: 3 plans

Plans:
- [x] 127-01-PLAN.md - Fix PRO-04 bug (lid-sinds), cache storage methods
- [x] 127-02-PLAN.md - FeeCacheInvalidator class with ACF hooks
- [x] 127-03-PLAN.md - REST endpoint optimization, bulk recalculation

### Phase 128: Google Sheets Export
**Goal**: Users can export fee data to Google Sheets
**Depends on**: Phase 127
**Requirements**: EXP-01
**Success Criteria** (what must be TRUE):
  1. Export button on Contributie page
  2. Exports to Google Sheets with columns: Name, Age Group, Base Fee, Family Discount, Pro-rata %, Final Amount
  3. Uses existing Google OAuth connection
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 123 -> 124 -> 125 -> 126 -> 127 -> 128

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 123. Settings & Backend Foundation | 2/2 | ✓ Complete | 2026-01-31 |
| 124. Fee Calculation Engine | 2/2 | ✓ Complete | 2026-01-31 |
| 125. Family Discount | 2/2 | ✓ Complete | 2026-01-31 |
| 126. Pro-rata & UI | 2/2 | ✓ Complete | 2026-01-31 |
| 127. Fee Caching | 3/3 | ✓ Complete | 2026-02-01 |
| 128. Google Sheets Export | 0/? | Not started | - |

---
*Roadmap created: 2026-01-31*
*Last updated: 2026-02-01 (Scope adjusted - added Phase 127, 128; removed FIL-01)*
