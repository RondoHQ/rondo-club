# Requirements: Stadion v17.0 De-AWC

**Defined:** 2026-02-05
**Core Value:** Make Stadion installable and configurable by any sports club without code changes

## v17.0 Requirements

### Club Configuration (Backend)

- [x] **CFG-01**: Admin can set club name via WordPress options (used in page title, PWA manifest)
- [x] **CFG-02**: Admin can set club default accent color (hex value) stored in WordPress options
- [x] **CFG-03**: Admin can set FreeScout base URL via WordPress options
- [x] **CFG-04**: REST API endpoint exposes club config (admin write, all-users read)
- [x] **CFG-05**: Sensible defaults when no config exists (no club name = "Stadion", green default color, FreeScout hidden)

### Frontend Settings UI

- [ ] **UI-01**: Club configuration section in Settings (admin-only) with name, color picker, FreeScout URL fields
- [ ] **UI-02**: Club color appears as first accent color option labeled "Club" in user accent color picker
- [ ] **UI-03**: Club color falls back to green (#006935) when not configured by admin

### Color System Refactor

- [ ] **CLR-01**: Rename `awc` color key to `club` in tailwind.config.js, useTheme.js, index.css, Settings.jsx
- [ ] **CLR-02**: Login screen, favicon SVG, and PWA manifest theme-color read default from club config
- [ ] **CLR-03**: All "AWC" comments removed from source code

### Integration Cleanup

- [ ] **INT-01**: FreeScout link in PersonDetail reads base URL from club config (hidden when URL not set)
- [ ] **INT-02**: `svawc.nl` references removed from AGENTS.md documentation (use placeholder)
- [ ] **INT-03**: Theme installable by another club without any code changes

## Future Requirements

None deferred — this milestone is self-contained.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Multi-tenant (multiple clubs per install) | Single-club-per-install is sufficient |
| Custom logo upload | Club identity via color is enough for now |
| i18n/translation system | Already Dutch-only, separate concern |
| Sportlink connection config | Already handled by separate sportlink-sync plugin |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CFG-01 | Phase 144 | Complete |
| CFG-02 | Phase 144 | Complete |
| CFG-03 | Phase 144 | Complete |
| CFG-04 | Phase 144 | Complete |
| CFG-05 | Phase 144 | Complete |
| UI-01 | Phase 145 | Pending |
| UI-02 | Phase 145 | Pending |
| UI-03 | Phase 145 | Pending |
| CLR-01 | Phase 145 | Pending |
| CLR-02 | Phase 145 | Pending |
| CLR-03 | Phase 145 | Pending |
| INT-01 | Phase 146 | Pending |
| INT-02 | Phase 146 | Pending |
| INT-03 | Phase 146 | Pending |

**Coverage:**
- v17.0 requirements: 14 total
- Mapped to phases: 14
- Unmapped: 0

✓ 100% requirement coverage achieved

---
*Requirements defined: 2026-02-05*
*Last updated: 2026-02-05 after roadmap creation*
