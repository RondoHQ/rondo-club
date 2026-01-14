# Project Milestones: Caelis

## v3.3 Todo Enhancement (Shipped: 2026-01-14)

**Delivered:** Expanded todo functionality with notes/description field, multi-person support, and stacked avatar displays across all views.

**Phases completed:** 32-34 (3 plans total)

**Key accomplishments:**

- Added WYSIWYG notes field to todo data model for detailed descriptions
- Changed todos from single-person to multi-person linking
- TodoModal enhanced with collapsible notes editor and multi-person selector
- Stacked avatar display in TodosList and PersonDetail sidebar
- Cross-person todo visibility with "Also:" indicator showing other linked people
- Migration CLI: `wp prm todos migrate-persons`

**Stats:**

- 23 files changed
- +2,022 / -338 lines changed
- 3 phases, 3 plans, 10 tasks
- 1 day from start to ship

**Git range:** `625f701` → `439ea5f`

**What's next:** To be determined

---

## v3.2 Person Profile Polish (Shipped: 2026-01-14)

**Delivered:** Enhanced PersonDetail page with current position display in header, persistent todos sidebar, and mobile todos access via FAB.

**Phases completed:** 29-31 (3 plans total)

**Key accomplishments:**

- Current position (job title + company) display in person header
- Persistent todos sidebar visible across all PersonDetail tabs
- Mobile todos access via floating action button + slide-up panel
- 3-column grid layout for equal-width content columns
- Timeline endpoint updated for todo post statuses

**What's next:** v3.3 Todo Enhancement

---

## v3.1 Pending Response Tracking (Shipped: 2026-01-14)

**Delivered:** Converted todos to custom post type with pending response tracking, aging indicators, and filter UI.

**Phases completed:** 24-28 (9 plans total)

**Key accomplishments:**

- prm_todo custom post type (migrated from comments)
- WordPress post statuses (prm_open, prm_awaiting, prm_completed)
- Awaiting response tracking with timestamps and aging indicators
- Filter UI (Open/Awaiting/Completed tabs) across all views
- WP-CLI migration: `wp prm migrate-todos`
- 25 PHPUnit tests for todo functionality

**What's next:** v3.2 Person Profile Polish

---

## v2.3 List View Unification (Shipped: 2026-01-13)

**Delivered:** Unified list view experience across People and Organizations, removing card view and ensuring consistent UX with full bulk action parity.

**Phases completed:** 16-18 (3 plans total)

**Key accomplishments:**

- Removed card view toggle from People, list view is now the only option
- Added dedicated image column to People list for proper alignment
- Built Organizations list view with columns (logo, name, industry, website, workspace, labels)
- Added sortable columns and selection infrastructure to Organizations
- Created bulk actions for Organizations: visibility, workspace assignment, label management
- Full parity between People and Organizations list views

**Stats:**

- 23 files changed
- +2,590 / -1,014 lines changed
- 3 phases, 3 plans
- 1 day from start to ship

**Git range:** `c932ac6` → `a3246ae`

**Issues closed:** ISS-006, ISS-007, ISS-008

**What's next:** To be determined

---

## v2.2 List View Polish (Shipped: 2026-01-13)

**Delivered:** Complete list view experience with full sorting capabilities, labels display, and extended bulk actions for People.

**Phases completed:** 14-15 (4 plans total)

**Key accomplishments:**

- Split Name column into First Name / Last Name columns
- Labels column with styled pills
- Clickable SortableHeader component with sort indicators
- Sticky table header and selection toolbar
- Organization, Workspace, Labels sorting options
- BulkOrganizationModal with search and clear option
- BulkLabelsModal with add/remove mode toggle

**Issues closed:** ISS-001, ISS-002, ISS-003, ISS-004, ISS-005

**What's next:** v2.3 List View Unification

---

## v2.1 Bulk Operations (Shipped: 2026-01-13)

**Delivered:** Efficient bulk management of contacts through a new list view with multi-select and batch actions.

**Phases completed:** 12-13 (3 plans total)

**Key accomplishments:**

- Card/list view toggle for people screen
- Tabular list view with Name, Organization, Workspace columns
- Checkbox multi-selection infrastructure
- Bulk update REST endpoint with ownership validation
- Bulk visibility and workspace assignment modals

**What's next:** v2.2 List View Polish

---

## v2.0 Multi-User (Shipped: 2026-01-13)

**Delivered:** Multi-user collaboration platform with workspaces, contact visibility (private/workspace/shared), @mentions, and team activity features while preserving single-user backward compatibility.

**Phases completed:** 7-11 (20 plans total)

**Key accomplishments:**

- Workspace CPT with role-based membership system (Admin/Member/Viewer roles) enabling multi-user collaboration
- Visibility framework for contacts with three levels (private/workspace/shared) and workspace_access taxonomy
- Complete workspace invitation system with email invitations, 7-day expiring tokens, and role assignment
- @Mentions infrastructure with MentionInput component, autocomplete, and notification preferences (immediate/digest)
- Workspace-scoped iCal calendar feeds with token-based authentication
- Workspace activity digest integrating @mentions and shared notes into daily reminders

**Stats:**

- 105 files created/modified
- +15,718 / -1,272 lines changed
- 5 phases, 20 plans
- 1 day from start to ship

**Git range:** `feat(07-01)` → `docs(11-01)`

**What's next:** To be determined

---

## v1.0 Tech Debt Cleanup (Shipped: 2026-01-13)

**Delivered:** Split monolithic 107KB REST API into domain-specific classes, hardened security with sodium encryption and XSS protection, cleaned up production code.

**Phases completed:** 1-6 (11 plans total)

**Key accomplishments:**

- Split class-rest-api.php into 5 domain-specific classes (Base, People, Companies, Slack, Import/Export)
- Implemented sodium encryption for Slack tokens with fallback
- Added server-side XSS protection using WordPress native wp_kses functions
- Removed 48 console.error() calls from 11 React files
- Created .env.example documenting 4 required environment variables
- Consolidated decodeHtml() to shared formatters.js utility

**Stats:**

- 60 files created/modified
- +4,779 / -2,083 lines changed
- 6 phases, 11 plans
- 1 day from start to ship

**Git range:** `91806f2` → `f4e307b`

**What's next:** v2.0 Multi-User

---
