# Requirements: Stadion v19.0 Birthdate Simplification

**Defined:** 2026-02-06
**Core Value:** Simplify birthdate handling by moving to person field and removing the Important Dates subsystem

## v19.0 Requirements

### Birthdate Field

- [x] **BDAY-01**: Person has birthdate ACF field (date picker, read-only in UI)
- [x] **BDAY-02**: Person header displays birthdate after age (format: "34 jaar (6 feb)")

### Dashboard Widget

- [x] **DASH-01**: Upcoming birthdays widget queries person birthdate meta
- [x] **DASH-02**: Birthday calculation uses month/day comparison for "upcoming" logic

### Data Cleanup

- [ ] **DATA-01**: All important_date posts deleted from production database

### Infrastructure Removal

- [ ] **REMV-01**: important_date CPT registration removed
- [ ] **REMV-02**: date_type taxonomy registration removed
- [ ] **REMV-03**: "Datums" navigation item removed from sidebar
- [ ] **REMV-04**: DatesList page and route removed
- [ ] **REMV-05**: ImportantDateModal component removed
- [ ] **REMV-06**: Important dates card removed from PersonDetail
- [ ] **REMV-07**: Related REST endpoints removed (/stadion/v1/timeline date entries, etc.)
- [ ] **REMV-08**: Unused imports and hooks cleaned up

## Out of Scope

| Feature | Reason |
|---------|--------|
| Migration of existing birthdays | User will sync from Sportlink, no migration needed |
| Anniversary/membership date fields | Not currently used, can add later if needed |
| Birth year calculation from dates | Already exists via existing birth_year field |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| BDAY-01 | Phase 147 | Complete |
| BDAY-02 | Phase 147 | Complete |
| DASH-01 | Phase 147 | Complete |
| DASH-02 | Phase 147 | Complete |
| DATA-01 | Phase 148 | Pending |
| REMV-01 | Phase 148 | Pending |
| REMV-02 | Phase 148 | Pending |
| REMV-03 | Phase 148 | Pending |
| REMV-04 | Phase 148 | Pending |
| REMV-05 | Phase 148 | Pending |
| REMV-06 | Phase 148 | Pending |
| REMV-07 | Phase 148 | Pending |
| REMV-08 | Phase 148 | Pending |

**Coverage:**
- v19.0 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-06*
*Traceability updated: 2026-02-06*
*Phase 147 requirements completed: 2026-02-06*
