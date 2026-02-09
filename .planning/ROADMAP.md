# Roadmap: Rondo Club

## Milestones

- ✅ **v20.0 Configurable Roles** — Phases 151-154 (shipped 2026-02-08) — [Archive](milestones/v20.0-ROADMAP.md)
- ✅ **v21.0 Per-Season Fee Categories** — Phases 155-161 (shipped 2026-02-09) — [Archive](milestones/v21.0-ROADMAP.md)
- 🚧 **v22.0 Design Refresh** — Phases 162-165 (in progress)

## Phases

<details>
<summary>v20.0 Configurable Roles (Phases 151-154) — SHIPPED 2026-02-08</summary>

- [x] Phase 151: Dynamic Filters (2/2 plans) — completed 2026-02-07
- [x] Phase 152: Role Settings (0/0 plans, pre-existing) — completed 2026-02-07
- [x] Phase 153: Wire Up Role Settings (1/1 plan) — completed 2026-02-08
- [x] Phase 154: Sync Cleanup (1/1 plan) — completed 2026-02-08

</details>

<details>
<summary>v21.0 Per-Season Fee Categories (Phases 155-161) — SHIPPED 2026-02-09</summary>

- [x] Phase 155: Fee Category Data Model (1/1 plan) — completed 2026-02-08
- [x] Phase 156: Fee Category Backend Logic (2/2 plans) — completed 2026-02-08
- [x] Phase 157: Fee Category REST API (2/2 plans) — completed 2026-02-09
- [x] Phase 158: Fee Category Settings UI (2/2 plans) — completed 2026-02-09
- [x] Phase 159: Fee Category Frontend Display (1/1 plan) — completed 2026-02-09
- [x] Phase 160: Configurable Family Discount (2/2 plans) — completed 2026-02-09
- [x] Phase 161: Configurable Matching Rules (2/2 plans) — completed 2026-02-09

</details>

### 🚧 v22.0 Design Refresh (In Progress)

**Milestone Goal:** Restyle the entire React SPA to match the rondo.club brand design, replacing the dynamic accent color system with fixed brand colors.

#### Phase 162: Foundation - Tailwind v4 & Tokens

**Goal**: Establish stable design foundation with Tailwind v4 architecture and brand color tokens

**Depends on**: Nothing (first phase of milestone)

**Requirements**: TOKENS-01, TOKENS-02, TOKENS-03, TOKENS-04

**Success Criteria** (what must be TRUE):
1. Tailwind CSS v4 build succeeds with @theme configuration in index.css
2. Brand color tokens (electric-cyan, bright-cobalt, deep-midnight, obsidian) are defined and accessible via Tailwind classes
3. Montserrat font loads for heading elements with weights 600 and 700
4. Cyan-to-cobalt gradient utility works in components (bg-brand-gradient)

**Plans:** 1 plan

Plans:
- [x] 162-01-PLAN.md — Tailwind v4 migration, brand tokens, Montserrat font, gradient utility — completed 2026-02-09

---

#### Phase 163: Color System Migration

**Goal**: Replace dynamic accent color system with fixed brand colors throughout the application

**Depends on**: Phase 162

**Requirements**: COLOR-01, COLOR-02, COLOR-03, COLOR-04

**Success Criteria** (what must be TRUE):
1. All accent-* class references are replaced with electric-cyan or bright-cobalt equivalents
2. useTheme.js hook and CSS variable injection system are removed
3. Color picker UI is removed from Settings page
4. ClubConfig accent_color option and REST API field are removed from backend
5. Production build includes no unused accent-* classes

**Plans**: TBD

Plans:
- [ ] 163-01: Color system replacement

---

#### Phase 164: Component Styling & Dark Mode Adaptation

**Goal**: Apply new brand styling to all components and adapt dark mode to use brand colors

**Depends on**: Phase 163

**Requirements**: COLOR-05, COMP-01, COMP-02, COMP-03, COMP-04, COMP-05, COMP-06, COMP-07

**Success Criteria** (what must be TRUE):
1. Primary buttons display cyan-to-cobalt gradient with hover lift effect
2. Secondary buttons use solid bright-cobalt with hover lift effect
3. Glass button variant displays with transparent background and slate-200 border
4. Cards display 3px gradient top border with rounded-xl corners
5. Input and textarea focus states show electric-cyan border with 3px cyan glow ring
6. Heading elements (h1, h2, h3) display gradient text treatment
7. Dark mode uses adapted brand colors throughout (dark: classes updated, not removed)
8. Hover transitions use 200ms ease with translateY(-2px) lift effect

**Plans**: TBD

Plans:
- [ ] 164-01: Component styling updates

---

#### Phase 165: PWA & Backend Cleanup

**Goal**: Update PWA assets and remove all traces of old theming system from backend

**Depends on**: Phase 164

**Requirements**: PWA-01, PWA-02, PWA-03

**Success Criteria** (what must be TRUE):
1. PWA manifest theme-color is set to electric-cyan (#0891B2)
2. Static favicon uses electric-cyan fill (no longer dynamic)
3. WordPress login page displays brand gradient styling
4. Settings page no longer shows theme customization controls
5. Production build contains no dead code from removed theming system

**Plans**: TBD

Plans:
- [ ] 165-01: PWA and backend cleanup

---

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 151. Dynamic Filters | v20.0 | 2/2 | ✓ Complete | 2026-02-07 |
| 152. Role Settings | v20.0 | 0/0 | ✓ Complete | 2026-02-07 |
| 153. Wire Up Role Settings | v20.0 | 1/1 | ✓ Complete | 2026-02-08 |
| 154. Sync Cleanup | v20.0 | 1/1 | ✓ Complete | 2026-02-08 |
| 155. Fee Category Data Model | v21.0 | 1/1 | ✓ Complete | 2026-02-08 |
| 156. Fee Category Backend Logic | v21.0 | 2/2 | ✓ Complete | 2026-02-08 |
| 157. Fee Category REST API | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |
| 158. Fee Category Settings UI | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |
| 159. Fee Category Frontend Display | v21.0 | 1/1 | ✓ Complete | 2026-02-09 |
| 160. Configurable Family Discount | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |
| 161. Configurable Matching Rules | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |
| 162. Foundation | v22.0 | 1/1 | ✓ Complete | 2026-02-09 |
| 163. Color Migration | v22.0 | 0/1 | Not started | - |
| 164. Component Styling | v22.0 | 0/1 | Not started | - |
| 165. PWA & Backend | v22.0 | 0/1 | Not started | - |

---
*Roadmap created: 2026-02-06*
*Last updated: 2026-02-09 — Phase 162 completed*
