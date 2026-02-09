# Roadmap: Rondo Club

## Milestones

- ✅ **v20.0 Configurable Roles** — Phases 151-154 (shipped 2026-02-08) — [Archive](milestones/v20.0-ROADMAP.md)
- ✅ **v21.0 Per-Season Fee Categories** — Phases 155-161 (shipped 2026-02-09) — [Archive](milestones/v21.0-ROADMAP.md)
- ✅ **v22.0 Design Refresh** — Phases 162-165 (shipped 2026-02-09) — [Archive](milestones/v22.0-ROADMAP.md)
- 🚧 **v23.0 Former Members** — Phases 166-169 (in progress)

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

### 🚧 v23.0 Former Members (In Progress)

**Milestone Goal:** Archive former members when they leave the club (detected by rondo-sync), hiding them from default views while preserving all data. Findable through global search and a filter toggle on the Leden list.

#### Phase 166: Backend Foundation
**Goal**: Former member status is stored on person records and accessible via REST API for rondo-sync integration
**Depends on**: Nothing (first phase of milestone)
**Requirements**: DATA-01, DATA-02, SYNC-01, SYNC-02
**Success Criteria** (what must be TRUE):
  1. Person records have a former_member boolean field (default false)
  2. REST API accepts PATCH requests to update former_member status
  3. API documentation describes the endpoint and field for rondo-sync integration
  4. rondo-sync can successfully mark a member as former via the API
**Plans**: 1 plan

Plans:
- [x] 166-01-PLAN.md — Add former_member ACF field, update rondo-sync to mark instead of delete, update API docs

#### Phase 167: Core Filtering
**Goal**: Former members are hidden by default from the Leden list, dashboard stats, and team rosters
**Depends on**: Phase 166
**Requirements**: LIST-01, DASH-01, TEAM-01
**Success Criteria** (what must be TRUE):
  1. Leden list (server-side filtered endpoint) excludes former members by default
  2. Dashboard member count does not include former members
  3. Team rosters do not show former members in player or staff lists
**Plans**: 1 plan

Plans:
- [x] 167-01-PLAN.md — Exclude former members from filtered people endpoint, dashboard stats, and team rosters

#### Phase 168: Visibility Controls
**Goal**: Users can find former members through search and a dedicated filter toggle
**Depends on**: Phase 167
**Requirements**: LIST-02, SRCH-01
**Success Criteria** (what must be TRUE):
  1. Leden list has a "Toon oud-leden" filter toggle
  2. When filter is enabled, former members appear with visual distinction
  3. Global search includes former members with "oud-lid" indicator
  4. Former members are easily discoverable when needed
**Plans**: 1 plan

Plans:
- [x] 168-01-PLAN.md — Add include_former filter param, Toon oud-leden toggle, oud-lid search indicator

#### Phase 169: Contributie Logic
**Goal**: Fee calculations correctly handle former members (include if active during season, full fee with no pro-rata for leavers)
**Depends on**: Phase 167
**Requirements**: FEE-01, FEE-02
**Success Criteria** (what must be TRUE):
  1. Former members with lid-sinds in current season appear in contributie list
  2. Former members who left mid-season are charged full season fee (no pro-rata discount)
  3. Former members who joined after season start are excluded from contributie list
  4. Fee calculation diagnostics correctly explain former member treatment
**Plans**: 1 plan

Plans:
- [x] 169-01-PLAN.md — Former member fee logic, pro-rata override, diagnostics, and documentation

## Progress

**Execution Order:**
Phases execute in numeric order: 166 → 167 → 168 → 169

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 166. Backend Foundation | 1/1 | ✓ Complete | 2026-02-09 |
| 167. Core Filtering | 1/1 | ✓ Complete | 2026-02-09 |
| 168. Visibility Controls | 1/1 | ✓ Complete | 2026-02-09 |
| 169. Contributie Logic | 1/1 | ✓ Complete | 2026-02-09 |

---
*Roadmap created: 2026-02-09*
*Last updated: 2026-02-09 — Phase 169 Contributie Logic complete*
