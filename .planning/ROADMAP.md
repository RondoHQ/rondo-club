# Roadmap: Caelis

## Milestones

- ✅ [v1.0 Tech Debt Cleanup](milestones/v1.0-tech-debt-cleanup.md) (Phases 1-6) — SHIPPED 2026-01-13
- ✅ [v2.0 Multi-User](milestones/v2.0-multi-user.md) (Phases 7-11) — SHIPPED 2026-01-13
- ✅ [v2.1 Bulk Operations](milestones/v2.1-bulk-operations.md) (Phases 12-13) — SHIPPED 2026-01-13
- ✅ [v2.2 List View Polish](milestones/v2.2-list-view-polish.md) (Phases 14-15) — SHIPPED 2026-01-13
- ✅ [v2.3 List View Unification](milestones/v2.3-list-view-unification.md) (Phases 16-18) — SHIPPED 2026-01-13
- ✅ [v2.4 Bug Fixes](milestones/v2.4-bug-fixes.md) (Phase 19) — SHIPPED 2026-01-13
- ✅ [v2.5 Performance](milestones/v2.5-performance.md) (Phase 20) — SHIPPED 2026-01-13
- ✅ [v3.0 Testing Infrastructure](milestones/v3.0-testing-infrastructure.md) (Phases 21-23) — SHIPPED 2026-01-13
- ✅ [v3.1 Pending Response Tracking](milestones/v3.1-pending-response-tracking.md) (Phases 24-28) — SHIPPED 2026-01-14
- ✅ [v3.2 Person Profile Polish](milestones/v3.2-person-profile-polish.md) (Phases 29-31) — SHIPPED 2026-01-14
- ✅ [v3.3 Todo Enhancement](milestones/v3.3-todo-enhancement.md) (Phases 32-34) — SHIPPED 2026-01-14
- ✅ [v3.4 UI Polish](milestones/v3.4-ui-polish.md) (Phases 35-37) — SHIPPED 2026-01-14
- ✅ [v3.5 Bug Fixes & Polish](milestones/v3.5-bug-fixes-polish.md) (Phases 38-39) — SHIPPED 2026-01-14
- 🚧 **v3.6 Quick Wins & Performance** — Phases 40-41 (in progress)

## Current Status

**Active:** v3.6 Quick Wins & Performance (Phase 40-41)

---

### 🚧 v3.6 Quick Wins & Performance (In Progress)

**Milestone Goal:** Small UX improvements (awaiting checkbox, email normalization) and bundle size optimization.

#### Phase 40: Quick Wins ✓

**Goal**: Add awaiting checkbox toggle to todo lists, auto-lowercase emails on save
**Depends on**: Previous milestone complete
**Research**: Unlikely (internal patterns)
**Plans**: 1/1 complete

Plans:
- [x] 40-01: Awaiting checkbox + email lowercasing

#### Phase 41: Bundle Optimization ✓

**Goal**: Analyze current bundle sizes and optimize (grown since v2.5)
**Depends on**: Phase 40
**Research**: Unlikely (established patterns from v2.5)
**Plans**: 1/1 complete

Plans:
- [x] 41-01: Modal lazy loading (main bundle 460 KB → 50 KB)

---

<details>
<summary>✅ v3.5 Bug Fixes & Polish (Phases 38-39) — SHIPPED 2026-01-14</summary>

**Milestone Goal:** Fix bugs and polish existing functionality with quick UI fixes and API improvements.

**Result:** X logo black, dashboard card styling, search ranking by first name, auto-title respects user edits, dashboard cache sync

**Phases:**
- [x] Phase 38: Quick UI Fixes (1/1 plan) ✓
- [x] Phase 39: API Improvements (1/1 plan + 1 FIX) ✓

**Total:** 2 phases, 3 plans

**Key Accomplishments:**
- X (Twitter) logo color updated to black
- Dashboard AwaitingTodoCard rounded corners
- Search prioritizes first name matches (scoring system)
- Important date titles persist user edits (custom_label detection)
- Dashboard cache invalidates on todo mutations from PersonDetail

See [milestone archive](milestones/v3.5-bug-fixes-polish.md) for full details.

</details>

---

<details>
<summary>✅ v3.4 UI Polish (Phases 35-37) — SHIPPED 2026-01-14</summary>

**Milestone Goal:** Clean up UI, add missing dashboard features, and complete quick wins from backlog.

**Result:** Clickable links, label management UI, dashboard enhancements, build-time refresh detection

**Phases:**
- [x] Phase 35: Quick Fixes (1/1 plan) ✓
- [x] Phase 36: Dashboard Enhancement (1/1 plan) ✓
- [x] Phase 37: Label Management (1/1 plan) ✓

**Total:** 3 phases, 3 plans

**Key Accomplishments:**
- Clickable website links in Organizations list
- Labels column removed from Organizations list
- Simplified Slack contact display
- Build-time based refresh detection
- Awaiting todos count in dashboard stats (5-column grid)
- Full-width Timeline panel on person profile
- Labels CRUD interface at /settings/labels

See [milestone archive](milestones/v3.4-ui-polish.md) for full details.

</details>

---

<details>
<summary>✅ v3.3 Todo Enhancement (Phases 32-34) — SHIPPED 2026-01-14</summary>

**Milestone Goal:** Expand todo functionality with notes/description field, multi-person support, and visual polish.

**Result:** Notes ACF field, multi-person linking, enhanced modals, stacked avatar displays

**Phases:**
- [x] Phase 32: Todo Data Model Enhancement (1/1 plan) ✓
- [x] Phase 33: Todo Modal UI Enhancement (1/1 plan) ✓
- [x] Phase 34: Cross-Person Todo Display (1/1 plan) ✓

**Total:** 3 phases, 3 plans

**Key Accomplishments:**
- WYSIWYG notes field for todo descriptions
- Multi-person todo linking with migration CLI
- TodoModal with notes editor and multi-person selector
- Stacked avatar displays in TodosList and PersonDetail
- Cross-person visibility with "Also:" indicator

See [milestone archive](milestones/v3.3-todo-enhancement.md) for full details.

</details>

---

<details>
<summary>✅ v3.2 Person Profile Polish (Phases 29-31) — SHIPPED 2026-01-14</summary>

**Milestone Goal:** Enhance the PersonDetail page with role/job display in header and a persistent todos sidebar.

**Result:** Current position display, persistent todos sidebar, mobile FAB + slide-up panel

**Phases:**
- [x] Phase 29: Header Enhancement (1/1 plan) ✓
- [x] Phase 30: Todos Sidebar (1/1 plan) ✓
- [x] Phase 31: Person Image Polish (1/1 plan) ✓

**Total:** 3 phases, 3 plans

**Key Accomplishments:**
- Current position (job title + company) display in person header
- Persistent todos sidebar visible across all PersonDetail tabs
- Mobile todos access via floating action button + slide-up panel
- Fixed timeline endpoint to support new todo post statuses
- Equal-width 3-column grid layout for person detail page

See [milestone archive](milestones/v3.2-person-profile-polish.md) for full details.

</details>

<details>
<summary>✅ v3.1 Pending Response Tracking (Phases 24-28) — SHIPPED 2026-01-14</summary>

**Milestone Goal:** Convert todos to a proper post type and add pending response tracking with aging and auto-resolution.

**Result:** prm_todo CPT with WordPress post statuses (open/awaiting/completed), REST API filtering, comprehensive UI

**Phases:**
- [x] Phase 24: Todo Post Type (4/4 plans) ✓
- [x] Phase 25: Todo UI Migration (1/1 plan) ✓
- [x] Phase 26: Pending Response Model (1/1 plan) ✓
- [x] Phase 27: Pending Response UI (2/2 plans) ✓
- [x] Phase 28: Filters & Polish (1/1 plan) ✓

**Total:** 5 phases, 9 plans

**Key Accomplishments:**
- Converted todos from comments to prm_todo CPT
- WordPress post statuses for state management (prm_open, prm_awaiting, prm_completed)
- Awaiting response tracking with timestamps and aging indicators
- Filter UI (Open/Awaiting/Completed tabs) across all views
- 25 PHPUnit tests for todo functionality
- WP-CLI migration scripts for both todo systems

See [milestone archive](milestones/v3.1-pending-response-tracking.md) for full details.

</details>

<details>
<summary>✅ v3.0 Testing Infrastructure (Phases 21-23) — SHIPPED 2026-01-13</summary>

**Milestone Goal:** Establish PHPUnit testing foundation covering access control, REST API, and data model

**Result:** 120 tests covering access control, REST API CRUD, search/dashboard, relationships, sharing

#### Phase 21: PHPUnit Setup ✓

**Goal**: PHPUnit + wp-browser setup with test database configuration
**Depends on**: Previous milestone complete
**Research**: Completed (wp-browser 4.5, WPLoader configuration)
**Result**: wp-browser 4.5.10 installed, 10 smoke tests passing

Plans:
- [x] 21-01: Framework Installation (wp-browser, Codeception, test database, smoke tests)

#### Phase 22: Access Control Tests ✓

**Goal**: User isolation, visibility rules (private/workspace/shared), workspace permissions
**Depends on**: Phase 21
**Research**: Not needed (internal patterns)
**Result**: 55 tests covering all access control patterns, 1 bug fixed

Plans:
- [x] 22-01: User Isolation Tests (18 tests)
- [x] 22-02: Visibility Rules Tests (14 tests, bug fix in class-access-control.php)
- [x] 22-03: Workspace Permissions Tests (23 tests)

#### Phase 23: REST API & Data Model Tests ✓

**Goal**: CRUD operations, search, timeline endpoints, CPT relationships, ACF field handling
**Depends on**: Phase 22
**Research**: Not needed (internal patterns)
**Result**: 65 tests for REST API CRUD, search, dashboard, relationships, sharing

Plans:
- [x] 23-01: CPT CRUD Tests (24 tests for person, company, important_date)
- [x] 23-02: Search & Dashboard Tests (20 tests for search, dashboard, reminders, todos)
- [x] 23-03: Relationships & Shares Tests (21 tests for relationships, sharing, bulk updates)

See [milestone archive](milestones/v3.0-testing-infrastructure.md) for full details.

</details>

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
| v3.0 Testing Infrastructure | 21-23 | 7/7 | Complete ✅ | 2026-01-13 |
| v3.1 Pending Response Tracking | 24-28 | 9/9 | Complete ✅ | 2026-01-14 |
| v3.2 Person Profile Polish | 29-31 | 3/3 | Complete ✅ | 2026-01-14 |
| v3.3 Todo Enhancement | 32-34 | 3/3 | Complete ✅ | 2026-01-14 |
| v3.4 UI Polish | 35-37 | 3/3 | Complete ✅ | 2026-01-14 |
| v3.5 Bug Fixes & Polish | 38-39 | 2/2 | Complete ✅ | 2026-01-14 |
| v3.6 Quick Wins & Performance | 40-41 | 2/2 | Complete ✅ | 2026-01-14 |

**Shipped: 14 milestones, 41 phases, 75 plans**
