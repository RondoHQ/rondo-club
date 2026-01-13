# Roadmap: Caelis

## Milestones

- ✅ [v1.0 Tech Debt Cleanup](milestones/v1.0-tech-debt-cleanup.md) (Phases 1-6) — SHIPPED 2026-01-13
- ✅ [v2.0 Multi-User](milestones/v2.0-multi-user.md) (Phases 7-11) — SHIPPED 2026-01-13
- ✅ [v2.1 Bulk Operations](milestones/v2.1-bulk-operations.md) (Phases 12-13) — SHIPPED 2026-01-13
- ✅ [v2.2 List View Polish](milestones/v2.2-list-view-polish.md) (Phases 14-15) — SHIPPED 2026-01-13
- ✅ [v2.3 List View Unification](milestones/v2.3-list-view-unification.md) (Phases 16-18) — SHIPPED 2026-01-13
- ✅ [v2.4 Bug Fixes](milestones/v2.4-bug-fixes.md) (Phase 19) — SHIPPED 2026-01-13
- ✅ [v2.5 Performance](milestones/v2.5-performance.md) (Phase 20) — SHIPPED 2026-01-13
- 🚧 **v3.0 Testing Infrastructure** — Phases 21-23 (in progress)

## Current Status

**Active:** v3.0 Testing Infrastructure (Phases 21-23)

### 🚧 v3.0 Testing Infrastructure (In Progress)

**Milestone Goal:** Establish PHPUnit testing foundation covering access control, REST API, and data model

#### Phase 21: PHPUnit Setup ✓

**Goal**: PHPUnit + wp-browser setup with test database configuration
**Depends on**: Previous milestone complete
**Research**: Completed (wp-browser 4.5, WPLoader configuration)
**Result**: wp-browser 4.5.10 installed, 10 smoke tests passing

Plans:
- [x] 21-01: Framework Installation (wp-browser, Codeception, test database, smoke tests)

#### Phase 22: Access Control Tests

**Goal**: User isolation, visibility rules (private/workspace/shared), workspace permissions
**Depends on**: Phase 21
**Research**: Unlikely (internal patterns)
**Plans**: TBD

Plans:
- [ ] 22-01: TBD

#### Phase 23: REST API & Data Model Tests

**Goal**: CRUD operations, search, timeline endpoints, CPT relationships, ACF field handling
**Depends on**: Phase 22
**Research**: Unlikely (internal patterns)
**Plans**: TBD

Plans:
- [ ] 23-01: TBD

<details>
<summary>✅ v2.5 Performance (Phase 20) — SHIPPED 2026-01-13</summary>

**Milestone Goal:** Reduce bundle size from 1.6MB to under 500KB through code splitting

**Result:** Initial load reduced from 1,646 KB to 435 KB (73% reduction)

#### Phase 20: Bundle Optimization ✓

Plans:
- [x] 20-01: Vendor chunking (vendor + utils chunks)
- [x] 20-02: Route lazy loading (16 pages)
- [x] 20-03: Heavy library lazy loading (vis-network, TipTap)

See [milestone archive](milestones/v2.5-performance.md) for full details.

</details>

<details>
<summary>✅ v2.4 Bug Fixes (Phase 19) — SHIPPED 2026-01-13</summary>

See [milestone archive](milestones/v2.4-bug-fixes.md) for full details.

</details>

<details>
<summary>✅ v2.3 List View Unification (Phases 16-18) — SHIPPED 2026-01-13</summary>

**Milestone Goal:** Unify the list view experience across People and Organizations, removing card view and ensuring consistent UX.

**Issues addressed:** ISS-006, ISS-007, ISS-008

**Phases:**
- [x] Phase 16: People List View Cleanup (1/1 plans) ✓
- [x] Phase 17: Organizations List View (1/1 plans) ✓
- [x] Phase 18: Organizations Bulk Actions (1/1 plans) ✓

**Total:** 3 phases, 3 plans

**Key Accomplishments:**
- Removed card view toggle from People, list view only
- Added dedicated image column to People list
- Built Organizations list view with columns, sorting, selection
- Added bulk actions (visibility, workspace, labels) to Organizations
- Full parity between People and Organizations list views

See [milestone archive](milestones/v2.3-list-view-unification.md) for full details.

</details>

<details>
<summary>✅ v2.2 List View Polish (Phases 14-15) — SHIPPED 2026-01-13</summary>

See [milestone archive](milestones/v2.2-list-view-polish.md) for full details.

</details>

<details>
<summary>✅ v2.1 Bulk Operations (Phases 12-13) — SHIPPED 2026-01-13</summary>

**Overview:** Enable efficient bulk management of contacts through a new list view with multi-select and batch actions.

**Phases:**
- [x] Phase 12: List View & Selection Infrastructure (1/1 plans) ✓
- [x] Phase 13: Bulk Actions (2/2 plans) ✓

**Total:** 2 phases, 3 plans

**Key Accomplishments:**
- Card/list view toggle for people screen
- Tabular list view with Name, Organization, Workspace columns
- Checkbox multi-selection infrastructure
- Bulk update REST endpoint with ownership validation
- Bulk visibility and workspace assignment modals

See [milestone archive](milestones/v2.1-bulk-operations.md) for full details.

</details>

---

<details>
<summary>✅ v2.0 Multi-User (Phases 7-11) — SHIPPED 2026-01-13</summary>

**Overview:** Transform Caelis from a single-user personal CRM into a multi-user platform combining Clay.earth's intimate relationship focus with Twenty CRM's collaborative features. Adds workspaces, sharing, and team collaboration while preserving privacy.

**Phases:**
- [x] Phase 7: Data Model & Visibility System (4/4 plans) ✓
- [x] Phase 8: Workspace & Team Infrastructure (3/3 plans) ✓
- [x] Phase 9: Sharing UI & Permissions Interface (6/6 plans) ✓
- [x] Phase 10: Collaborative Features (5/5 plans) ✓
- [x] Phase 11: Migration, Testing & Polish (2/2 plans) ✓

**Total:** 5 phases, 20 plans

**Key Accomplishments:**
- Workspace CPT with role-based membership (Admin/Member/Viewer)
- Contact visibility system (private/workspace/shared)
- ShareModal and VisibilitySelector React components
- @mentions in notes with notification preferences
- Workspace iCal calendar feeds
- WP-CLI migration command for existing data

See [milestone archive](milestones/v2.0-multi-user.md) for full details.

</details>

<details>
<summary>✅ v1.0 Tech Debt Cleanup (Phases 1-6) — SHIPPED 2026-01-13</summary>

**Overview:** Cleaned up technical debt in the Caelis personal CRM. Split the monolithic REST API class into domain-specific files, hardened security, and cleaned up code quality issues.

**Phases:**
- [x] Phase 1: REST API Infrastructure (2/2 plans) ✓
- [x] Phase 2: REST API People & Companies (2/2 plans) ✓
- [x] Phase 3: REST API Integrations (2/2 plans) ✓
- [x] Phase 4: Security Hardening (2/2 plans) ✓
- [x] Phase 5: XSS Protection (1/1 plan) ✓
- [x] Phase 6: Code Cleanup (2/2 plans) ✓

**Total:** 6 phases, 11 plans

**Key Accomplishments:**
- Split 107KB class-rest-api.php into 5 domain-specific classes
- Implemented sodium encryption for Slack tokens
- Added server-side XSS protection with wp_kses
- Removed 48 console.error() calls
- Created .env.example with environment documentation

See [milestone archive](milestones/v1.0-tech-debt-cleanup.md) for full details.

</details>

## Progress

| Milestone | Phases | Plans | Status | Completed |
|-----------|--------|-------|--------|-----------|
| v1.0 Tech Debt Cleanup | 1-6 | 11/11 | Complete ✅ | 2026-01-13 |
| v2.0 Multi-User | 7-11 | 20/20 | Complete ✅ | 2026-01-13 |
| v2.1 Bulk Operations | 12-13 | 3/3 | Complete ✅ | 2026-01-13 |
| v2.2 List View Polish | 14-15 | 4/4 | Complete ✅ | 2026-01-13 |
| v2.3 List View Unification | 16-18 | 3/3 | Complete ✅ | 2026-01-13 |
| v2.4 Bug Fixes | 19 | 2/2 | Complete ✅ | 2026-01-13 |
| v2.5 Performance | 20 | 3/3 | Complete ✅ | 2026-01-13 |
| v3.0 Testing Infrastructure | 21-23 | 1/? | In Progress 🚧 | - |

**Shipped: 20 phases, 46 plans**
