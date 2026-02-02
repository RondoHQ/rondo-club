# Requirements: Stadion v12.1 Contributie Forecast

**Defined:** 2026-02-02
**Core Value:** Enable budget planning with next season fee forecast

## v12.1 Requirements

Requirements for contributie forecast feature. Builds on existing v12.0 fee calculation infrastructure.

### Forecast Calculation

- [ ] **FORE-01**: System calculates next season key from current date (2025-2026 → 2026-2027)
- [ ] **FORE-02**: Forecast uses all current season members as base population
- [ ] **FORE-03**: Forecast applies 100% pro-rata to all members (no mid-season adjustments)
- [ ] **FORE-04**: Forecast maintains family discount groupings from current membership
- [ ] **FORE-05**: Forecast recalculates family positions based on current groupings

### User Interface

- [ ] **UI-01**: Season dropdown selector shows current and next season options
- [ ] **UI-02**: Dropdown displays "(huidig)" label for current season
- [ ] **UI-03**: Dropdown displays "(prognose)" label for forecast season
- [ ] **UI-04**: Selected season updates table to show corresponding data
- [ ] **UI-05**: Forecast view hides Nikki and Saldo columns (no billing data)
- [ ] **UI-06**: Forecast view shows clear visual indicator that this is a projection

### Backend API

- [ ] **API-01**: Fees endpoint accepts optional `forecast=true` parameter
- [ ] **API-02**: Forecast response includes next season key
- [ ] **API-03**: Forecast response omits nikki_total and nikki_saldo fields

### Export

- [ ] **EXP-01**: Google Sheets export works for forecast view
- [ ] **EXP-02**: Exported sheet title includes season and "(Prognose)" indicator
- [ ] **EXP-03**: Exported forecast excludes Nikki columns

## Future Requirements

Deferred to future release.

### Enhancements

- **FORE-F1**: Year-over-year comparison showing current vs forecast totals
- **FORE-F2**: Member count comparison (current vs previous season)
- **FORE-F3**: Category breakdown comparison chart

## Out of Scope

Explicitly excluded from v12.1.

| Feature | Reason |
|---------|--------|
| Age group progression | Members aging up too complex, attrition balances it |
| Member attrition prediction | Not reliable, simple base suffices |
| Nikki integration for forecast | No billing data exists for future season |
| Historical season viewing | Current + next only, not arbitrary past seasons |
| Fee amount changes for forecast | Uses current fee settings, not projected changes |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| FORE-01 | TBD | Pending |
| FORE-02 | TBD | Pending |
| FORE-03 | TBD | Pending |
| FORE-04 | TBD | Pending |
| FORE-05 | TBD | Pending |
| UI-01 | TBD | Pending |
| UI-02 | TBD | Pending |
| UI-03 | TBD | Pending |
| UI-04 | TBD | Pending |
| UI-05 | TBD | Pending |
| UI-06 | TBD | Pending |
| API-01 | TBD | Pending |
| API-02 | TBD | Pending |
| API-03 | TBD | Pending |
| EXP-01 | TBD | Pending |
| EXP-02 | TBD | Pending |
| EXP-03 | TBD | Pending |

**Coverage:**
- v12.1 requirements: 17 total
- Mapped to phases: 0
- Unmapped: 17 ⚠️

---
*Requirements defined: 2026-02-02*
*Last updated: 2026-02-02 after initial definition*
