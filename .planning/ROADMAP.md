# Roadmap: Rondo Club

## Milestones

- ✅ **v20.0 Configurable Roles** — Phases 151-154 (shipped 2026-02-08) — [Archive](milestones/v20.0-ROADMAP.md)
- ✅ **v21.0 Per-Season Fee Categories** — Phases 155-161 (shipped 2026-02-09) — [Archive](milestones/v21.0-ROADMAP.md)
- ✅ **v22.0 Design Refresh** — Phases 162-165 (shipped 2026-02-09) — [Archive](milestones/v22.0-ROADMAP.md)
- ✅ **v23.0 Former Members** — Phases 166-169 (shipped 2026-02-09) — [Archive](milestones/v23.0-ROADMAP.md)
- ✅ **v24.0 Demo Data** — Phases 170-174 (shipped 2026-02-12) — [Archive](milestones/v24.0-ROADMAP.md)
- 🚧 **v24.1 Dead Feature Removal** — Phases 175-177 (in progress)

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

<details>
<summary>v22.0 Design Refresh (Phases 162-165) — SHIPPED 2026-02-09</summary>

- [x] Phase 162: Foundation - Tailwind v4 & Tokens (1/1 plan) — completed 2026-02-09
- [x] Phase 163: Color System Migration (3/3 plans) — completed 2026-02-09
- [x] Phase 164: Component Styling & Dark Mode (2/2 plans) — completed 2026-02-09
- [x] Phase 165: PWA & Backend Cleanup (1/1 plan) — completed 2026-02-09

</details>

<details>
<summary>v23.0 Former Members (Phases 166-169) — SHIPPED 2026-02-09</summary>

- [x] Phase 166: Backend Foundation (1/1 plan) — completed 2026-02-09
- [x] Phase 167: Core Filtering (1/1 plan) — completed 2026-02-09
- [x] Phase 168: Visibility Controls (1/1 plan) — completed 2026-02-09
- [x] Phase 169: Contributie Logic (1/1 plan) — completed 2026-02-09

</details>

<details>
<summary>v24.0 Demo Data (Phases 170-174) — SHIPPED 2026-02-12</summary>

- [x] Phase 170: Fixture Format Design (1/1 plan) — completed 2026-02-11
- [x] Phase 171: Export Command Foundation (4/4 plans) — completed 2026-02-11
- [x] Phase 172: Data Anonymization (3/3 plans) — completed 2026-02-11
- [x] Phase 173: Import Command (3/3 plans) — completed 2026-02-11
- [x] Phase 174: End-to-End Verification (2/2 plans) — completed 2026-02-12

</details>

### 🚧 v24.1 Dead Feature Removal (In Progress)

**Milestone Goal:** Remove unused taxonomies (person_label, team_label) and clean up residual important_date/date_type references left over from v19.0, simplifying the codebase.

#### Phase 175: Backend Cleanup
**Goal**: Remove taxonomy registration and REST endpoints, clean up residual references
**Depends on**: Nothing (first phase)
**Requirements**: LABEL-01, LABEL-02, LABEL-03, LABEL-09, CLEAN-01, CLEAN-02, CLEAN-03, CLEAN-04
**Success Criteria** (what must be TRUE):
  1. person_label and team_label taxonomies are unregistered and removed from the database
  2. REST API responses no longer include label fields
  3. important_date and date_type references are removed from reminders, iCal, and CLI systems
  4. Backend code passes linting with no references to removed features
**Plans**: 2 plans

Plans:
- [ ] 175-01-PLAN.md — Remove label taxonomies and REST API label references
- [ ] 175-02-PLAN.md — Remove residual important_date/date_type references and deprecated CLI commands

#### Phase 176: Frontend Cleanup
**Goal**: Remove UI components, columns, badges, and modals for labels
**Depends on**: Phase 175
**Requirements**: LABEL-04, LABEL-05, LABEL-06, LABEL-07, LABEL-08, LABEL-10
**Success Criteria** (what must be TRUE):
  1. Settings/Labels page is removed from navigation and deleted
  2. Label columns are removed from PeopleList, TeamsList, CommissiesList
  3. BulkLabelsModal and label bulk actions are removed from all list views
  4. Frontend builds successfully with no TypeScript/ESLint errors
**Plans**: TBD

Plans:
- [ ] 176-01: TBD

#### Phase 177: Documentation Updates
**Goal**: Update documentation to reflect simplified data model
**Depends on**: Phase 176
**Requirements**: DOCS-01, DOCS-02
**Success Criteria** (what must be TRUE):
  1. AGENTS.md no longer references removed taxonomies or labels feature
  2. Developer documentation reflects simplified data model (2 CPTs, not 3)
  3. Data model diagrams/tables show current state without removed features
**Plans**: TBD

Plans:
- [ ] 177-01: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 175 → 176 → 177

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 175. Backend Cleanup | v24.1 | 0/2 | Planned | - |
| 176. Frontend Cleanup | v24.1 | 0/TBD | Not started | - |
| 177. Documentation Updates | v24.1 | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-09*
*Last updated: 2026-02-13 — v24.1 Dead Feature Removal milestone added*
