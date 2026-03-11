# Requirements: Rondo Club v32.0 Interface Touch-up

**Defined:** 2026-03-11
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system

## v32.0 Requirements

Requirements for this milestone. Each maps to roadmap phases.

### Button System

- [ ] **BTN-01**: btn-primary remains filled gradient (current styling preserved)
- [ ] **BTN-02**: btn-secondary restyled to outlined (brand border + brand text, no fill)
- [ ] **BTN-03**: btn-tertiary created as ghost style (text-only, subtle hover background)
- [ ] **BTN-04**: btn-danger restyled to red filled (red bg, white text) and used for all destructive actions
- [ ] **BTN-05**: All four button tiers have proper dark mode variants

### Rollout

- [ ] **ROLL-01**: Invoice detail page applies correct tier hierarchy (send=primary, mark paid=secondary, PDF/payment link=tertiary, delete=danger)
- [ ] **ROLL-02**: All modal dialogs use correct tiers (Save/Submit=primary, Cancel=secondary)
- [ ] **ROLL-03**: Finance pages (list, settings, draft form) use correct tiers
- [ ] **ROLL-04**: People, Teams, Commissies pages use correct tiers
- [ ] **ROLL-05**: Settings pages use correct tiers
- [ ] **ROLL-06**: Feedback, VOG, Contributie, Clothing pages use correct tiers
- [ ] **ROLL-07**: DataTable toolbar and utility buttons use tertiary where appropriate

## Future Requirements

None — this is a focused visual polish milestone.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Reusable Button component (React) | CSS classes sufficient for current needs; component wrapper adds abstraction without clear benefit |
| Glass morphism buttons | Deferred from v22.0, not needed for button hierarchy |
| Button size variants (sm/lg) | Current text-sm/text-xs inline overrides work fine |
| Icon-only button variant | Current flex+gap pattern with icons is sufficient |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| BTN-01 | Phase 212 | Pending |
| BTN-02 | Phase 212 | Pending |
| BTN-03 | Phase 212 | Pending |
| BTN-04 | Phase 212 | Pending |
| BTN-05 | Phase 212 | Pending |
| ROLL-01 | Phase 213 | Pending |
| ROLL-02 | Phase 213 | Pending |
| ROLL-03 | Phase 213 | Pending |
| ROLL-04 | Phase 213 | Pending |
| ROLL-05 | Phase 213 | Pending |
| ROLL-06 | Phase 213 | Pending |
| ROLL-07 | Phase 213 | Pending |

**Coverage:**
- v32.0 requirements: 12 total
- Mapped to phases: 12
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-11*
*Last updated: 2026-03-11 — Traceability filled after roadmap creation*
