# Roadmap: Rondo Club

## Milestones

- ✅ **v20.0 Configurable Roles** — Phases 151-154 (shipped 2026-02-08) — [Archive](milestones/v20.0-ROADMAP.md)
- ✅ **v21.0 Per-Season Fee Categories** — Phases 155-161 (shipped 2026-02-09) — [Archive](milestones/v21.0-ROADMAP.md)
- ✅ **v22.0 Design Refresh** — Phases 162-165 (shipped 2026-02-09) — [Archive](milestones/v22.0-ROADMAP.md)
- ✅ **v23.0 Former Members** — Phases 166-169 (shipped 2026-02-09) — [Archive](milestones/v23.0-ROADMAP.md)
- 🚧 **v24.0 Demo Data** — Phases 170-174 (in progress)

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

### 🚧 v24.0 Demo Data (In Progress)

**Milestone Goal:** Create a demo data pipeline that anonymizes production data into a portable fixture, enabling realistic demo environments that always look "fresh."

#### Phase 170: Fixture Format Design

**Goal**: Define the JSON fixture structure that will contain all demo data
**Depends on**: Nothing (first phase)
**Requirements**: FIX-01, FIX-02
**Success Criteria** (what must be TRUE):
  1. A JSON schema exists documenting the fixture format
  2. The fixture format includes all entity types (people, teams, commissies, discipline cases, tasks, activities, settings)
  3. The fixture format includes all necessary relationship data
  4. The fixture format is self-contained with no external dependencies

**Plans**: 1 plan

Plans:
- [x] 170-01: Design fixture JSON schema

#### Phase 171: Export Command Foundation

**Goal**: Users can export production data to a fixture file
**Depends on**: Phase 170
**Requirements**: EXPORT-01, EXPORT-03, EXPORT-06, EXPORT-07, EXPORT-08
**Success Criteria** (what must be TRUE):
  1. User can run `wp rondo demo export` and it creates a JSON file
  2. The export includes all people records with their ACF fields
  3. The export includes all teams and commissies with their data
  4. The export includes discipline cases, tasks, and activities
  5. All relationships between entities are preserved in the export (person-team links, task-person links, etc.)
  6. Settings data (fee categories, role config, family discount) is included in the export
  7. Team and commissie names appear unchanged in the export

**Plans**: 4 plans

Plans:
- [x] 171-01: WP-CLI export command structure
- [x] 171-02: Export people, teams, commissies
- [x] 171-03: Export discipline cases, tasks, activities
- [x] 171-04: Export settings data

#### Phase 172: Data Anonymization

**Goal**: Production PII is replaced with realistic Dutch fake data
**Depends on**: Phase 171
**Requirements**: EXPORT-02, EXPORT-04, EXPORT-05
**Success Criteria** (what must be TRUE):
  1. All person names are replaced with Dutch fake names (first, infix, last)
  2. All email addresses are replaced with fake emails
  3. All phone numbers are replaced with fake Dutch phone numbers
  4. All addresses are replaced with fake Dutch addresses (street, postal code, city)
  5. All photos and avatars are excluded from the export
  6. Financial amounts (Nikki data) are replaced with plausible fake values

**Plans**: 3 plans

Plans:
- [x] 172-01: Dutch fake data generator class
- [x] 172-02: Anonymize person PII in export pipeline
- [x] 172-03: Strip photos and fake financials

#### Phase 173: Import Command

**Goal**: Users can load the fixture into a target WordPress instance
**Depends on**: Phase 172
**Requirements**: IMP-01, IMP-02, IMP-03
**Success Criteria** (what must be TRUE):
  1. User can run `wp rondo demo import` and it loads all fixture data
  2. All dates are shifted relative to today (birthdays, activities, task creation dates, etc.)
  3. User can run `wp rondo demo import --clean` to wipe existing data before import
  4. Import creates all posts, terms, and meta correctly
  5. Import preserves all relationships from the fixture

**Plans**: 3 plans

Plans:
- [x] 173-01: WP-CLI import command structure
- [x] 173-02: Date-shifting algorithm
- [x] 173-03: Clean flag implementation

#### Phase 174: End-to-End Verification

**Goal**: Demo data renders correctly throughout the entire application
**Depends on**: Phase 173
**Requirements**: IMP-04
**Success Criteria** (what must be TRUE):
  1. Leden page displays all imported people
  2. Teams page displays all imported teams
  3. Contributie page calculates fees for imported people
  4. Tuchtzaken page displays imported discipline cases
  5. Tasks page displays imported tasks
  6. Dashboard widgets display imported data correctly
  7. Global search finds imported people
  8. Person detail pages display all related data (work history, activities, tasks, etc.)

**Plans**: 2 plans

Plans:
- [ ] 174-01: UI verification checklist
- [ ] 174-02: Commit fixture to repository

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 170. Fixture Format Design | v24.0 | 1/1 | ✓ Complete | 2026-02-11 |
| 171. Export Command Foundation | v24.0 | 4/4 | ✓ Complete | 2026-02-11 |
| 172. Data Anonymization | v24.0 | 3/3 | ✓ Complete | 2026-02-11 |
| 173. Import Command | v24.0 | 3/3 | ✓ Complete | 2026-02-11 |
| 174. End-to-End Verification | v24.0 | 0/2 | Not started | - |

---
*Roadmap created: 2026-02-09*
*Last updated: 2026-02-11 — Phase 173 complete*
