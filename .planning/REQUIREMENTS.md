# Requirements: Stadion v12.0 Membership Fees

**Defined:** 2026-01-31
**Core Value:** Calculate membership fees with family discounts and pro-rata for mid-season joins

## v12.0 Requirements

Requirements for membership fee calculation system. Each maps to roadmap phases.

### Navigation & Display

- [ ] **NAV-01**: User sees "Contributie" section in sidebar below Leden, above VOG
- [ ] **NAV-02**: List displays columns: Name, Age Group, Base Fee, Family Discount, Final Amount

### Fee Calculation

- [ ] **FEE-01**: System calculates fees based on leeftijdsgroep (ignoring "Meiden" suffix)
- [ ] **FEE-02**: Mini (JO6-JO7), Pupil (JO8-JO11), Junior (JO12-JO19), Senior (JO23/Senioren) use configured base fees
- [ ] **FEE-03**: Recreant/Walking Football uses configured flat fee
- [ ] **FEE-04**: Donateur uses configured flat fee
- [ ] **FEE-05**: Non-playing members (no leeftijdsgroep) excluded from age-based calculation

### Family Discount

- [ ] **FAM-01**: System groups youth members by normalized address
- [ ] **FAM-02**: 2nd youth member at same address gets 25% discount
- [ ] **FAM-03**: 3rd+ youth members at same address get 50% discount
- [ ] **FAM-04**: Discount applied to youngest/cheapest member first
- [ ] **FAM-05**: Family discount only applies to youth (<18), not recreants/donateurs

### Pro-rata Calculation

- [ ] **PRO-01**: System uses Sportlink join date field for pro-rata calculation
- [ ] **PRO-02**: July-Sept = 100%, Oct-Dec = 75%, Jan-Mar = 50%, Apr-Jun = 25%
- [ ] **PRO-03**: Pro-rata applies to all fee types

### Settings

- [ ] **SET-01**: Admin can configure Mini fee amount
- [ ] **SET-02**: Admin can configure Pupil fee amount
- [ ] **SET-03**: Admin can configure Junior fee amount
- [ ] **SET-04**: Admin can configure Senior fee amount
- [ ] **SET-05**: Admin can configure Recreant fee amount
- [ ] **SET-06**: Admin can configure Donateur fee amount

### Filters

- [ ] **FIL-01**: User can filter by address mismatch (siblings with different addresses)

## Future Requirements

None identified for this milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Payment tracking (paid/unpaid) | Calculate only for v12.0, payment tracking deferred to future |
| Invoice generation | External system handles invoicing |
| Payment reminders | Deferred to future milestone |
| Multiple seasons | Focus on current season calculation |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| NAV-01 | TBD | Pending |
| NAV-02 | TBD | Pending |
| FEE-01 | TBD | Pending |
| FEE-02 | TBD | Pending |
| FEE-03 | TBD | Pending |
| FEE-04 | TBD | Pending |
| FEE-05 | TBD | Pending |
| FAM-01 | TBD | Pending |
| FAM-02 | TBD | Pending |
| FAM-03 | TBD | Pending |
| FAM-04 | TBD | Pending |
| FAM-05 | TBD | Pending |
| PRO-01 | TBD | Pending |
| PRO-02 | TBD | Pending |
| PRO-03 | TBD | Pending |
| SET-01 | TBD | Pending |
| SET-02 | TBD | Pending |
| SET-03 | TBD | Pending |
| SET-04 | TBD | Pending |
| SET-05 | TBD | Pending |
| SET-06 | TBD | Pending |
| FIL-01 | TBD | Pending |

**Coverage:**
- v12.0 requirements: 22 total
- Mapped to phases: 0
- Unmapped: 22

---
*Requirements defined: 2026-01-31*
*Last updated: 2026-01-31 after initial definition*
