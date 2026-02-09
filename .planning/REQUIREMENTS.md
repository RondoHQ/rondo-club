# Requirements: Rondo Club v22.0 Design Refresh

**Defined:** 2026-02-09
**Core Value:** Club administrators can manage their members, teams, and important dates through a single integrated system

## v22.0 Requirements

Requirements for the design refresh milestone. Each maps to roadmap phases.

### Design Tokens

- [ ] **TOKENS-01**: Tailwind CSS upgraded from v3.4 to v4 with CSS-first configuration (@theme blocks)
- [ ] **TOKENS-02**: Brand color tokens defined: electric-cyan (#0891B2), electric-cyan-light (#22D3EE), bright-cobalt (#2563EB), deep-midnight (#1E3A8A), obsidian (#0F172A)
- [ ] **TOKENS-03**: Montserrat font loaded via Fontsource for heading elements (weights 600, 700)
- [ ] **TOKENS-04**: Cyan-to-cobalt gradient utility available as reusable Tailwind class

### Color Migration

- [ ] **COLOR-01**: Dynamic accent color system removed (useTheme.js, CSS variable injection, accent-* scale)
- [ ] **COLOR-02**: All accent-* class references replaced with fixed brand color equivalents
- [ ] **COLOR-03**: Color picker (react-colorful) removed from Settings UI
- [ ] **COLOR-04**: ClubConfig accent_color WordPress option and REST API field removed
- [ ] **COLOR-05**: Dark mode adapted to new brand color palette (dark: classes updated, not removed)

### Component Styling

- [ ] **COMP-01**: Primary button uses gradient background (cyan → cobalt) with hover lift effect
- [ ] **COMP-02**: Secondary button uses solid bright-cobalt with hover lift effect
- [ ] **COMP-03**: Glass button variant with transparent background and slate-200 border
- [ ] **COMP-04**: Cards have 3px gradient top border with rounded-xl corners
- [ ] **COMP-05**: Input/textarea focus state shows electric-cyan border with 3px cyan glow ring
- [ ] **COMP-06**: Heading elements use gradient text treatment (cyan → cobalt)
- [ ] **COMP-07**: Hover transitions use 200ms ease with translateY(-2px) lift on buttons and cards

### PWA & Backend

- [ ] **PWA-01**: PWA manifest theme-color updated to electric-cyan (#0891B2)
- [ ] **PWA-02**: Favicon updated to fixed brand color (no longer dynamic)
- [ ] **PWA-03**: WordPress login page styled with brand gradient

## Future Requirements

### Visual Effects (deferred)

- **EFFECT-01**: Glass morphism header with backdrop-blur and semi-transparent background
- **EFFECT-02**: Decorative gradient blobs with blur and opacity effects

## Out of Scope

| Feature | Reason |
|---------|--------|
| Dark mode removal | User wants to keep dark mode, adapted to new brand colors |
| Glass morphism header | Deferred — mobile performance concerns, not critical for brand alignment |
| Decorative blobs | Polish feature, can be added later |
| User-selectable themes | Replaced by fixed brand identity |
| Tailwind v3 compatibility | Clean break to v4, single-club app |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| TOKENS-01 | — | Pending |
| TOKENS-02 | — | Pending |
| TOKENS-03 | — | Pending |
| TOKENS-04 | — | Pending |
| COLOR-01 | — | Pending |
| COLOR-02 | — | Pending |
| COLOR-03 | — | Pending |
| COLOR-04 | — | Pending |
| COLOR-05 | — | Pending |
| COMP-01 | — | Pending |
| COMP-02 | — | Pending |
| COMP-03 | — | Pending |
| COMP-04 | — | Pending |
| COMP-05 | — | Pending |
| COMP-06 | — | Pending |
| COMP-07 | — | Pending |
| PWA-01 | — | Pending |
| PWA-02 | — | Pending |
| PWA-03 | — | Pending |

**Coverage:**
- v22.0 requirements: 19 total
- Mapped to phases: 0
- Unmapped: 19 ⚠️

---
*Requirements defined: 2026-02-09*
*Last updated: 2026-02-09 after initial definition*
