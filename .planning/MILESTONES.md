# Project Milestones: Caelis

## v6.0 Custom Fields (Shipped: 2026-01-21)

**Delivered:** Admin-defined custom fields for People and Organizations with 14 field types, Settings UI, detail view integration, list view columns, and global search support.

**Phases completed:** 87-94 (15 plans total)

**Key accomplishments:**

- ACF Foundation with PHP infrastructure for programmatic field group management
- 14 field types: Text, Textarea, Number, Email, URL, Date, Select, Checkbox, True/False, Image, File, Link, Color Picker, Relationship
- Settings UI with Custom Fields subtab, slide-out panels, and admin-only access
- Detail View integration with CustomFieldsSection component and edit modal
- List View columns with configurable show/hide and column ordering
- Search integration with custom field values included in global search
- Polish features: drag-and-drop reordering, required/unique validation, placeholder text

**Stats:**

- 115 files changed
- +17,425 / -489 lines changed
- 8 phases, 15 plans
- 3 days (2026-01-18 → 2026-01-21)

**Git range:** `5ada801` → `54ee300`

**What's next:** To be determined

---

## v5.0.1 Meeting Card Polish (Shipped: 2026-01-18)

**Delivered:** Dashboard meeting card visual improvements with time-based styling, 24h format, calendar name display, and data cleanup for HTML-encoded titles.

**Phases completed:** 86 (1 plan total)

**Key accomplishments:**

- 24h time format for meeting times (14:30 instead of 2:30 PM)
- Past meetings dimmed with 50% opacity for visual hierarchy
- Currently ongoing meetings highlighted with accent-colored ring
- Calendar name displayed in meeting cards and detail modal
- WP-CLI command `wp prm event cleanup_titles` for HTML entity fixes
- 47 existing event titles cleaned up (`&amp;` → `&`)

**Stats:**

- 13 files changed
- +687 / -272 lines changed
- 1 phase, 1 plan, 3 tasks
- Same day (2026-01-18)

**Git range:** `e367889` → `22f21e8`

**What's next:** To be determined

---

## v5.0 Google Contacts Sync (Shipped: 2026-01-18)

**Delivered:** Bidirectional Google Contacts synchronization with import, export, delta sync, conflict resolution (Caelis wins), and WP-CLI commands for administration.

**Phases completed:** 79-85 (16 plans total)

**Key accomplishments:**

- Google Contacts OAuth with incremental scope addition (existing Calendar users can add Contacts without re-auth)
- Import from Google with field mapping, email-based duplicate detection, and photo sideloading
- Export to Google with reverse field mapping and etag conflict handling
- Delta sync using Google syncToken for efficient change detection
- Conflict resolution with Caelis-wins strategy and activity logging for audit
- Settings UI with sync history viewer and "View in Google Contacts" link on person profiles
- WP-CLI commands: sync, sync --full, status, conflicts, unlink-all

**Stats:**

- 73 files changed
- +14,866 / -405 lines changed
- 7 phases, 16 plans
- 2 days from start to ship (2026-01-17 → 2026-01-18)

**Git range:** `bfee084` → `fde800f`

**What's next:** To be determined

---

## v4.8 Meeting Enhancements (Shipped: 2026-01-17)

**Delivered:** Meeting detail modal with attendees and notes, add person from meeting attendees, date navigation, and add-email-to-existing-person flow.

**Phases completed:** 73-76 (5 plans + 1 FIX total)

**Key accomplishments:**

- Meeting detail modal with title, time, location, description, and attendee list
- Meeting notes section with auto-save for meeting prep
- Add person from meeting - quick-add unknown attendees to contacts with name extraction
- Date navigation with prev/next/today buttons for browsing meetings across days
- Add email to existing person - choice popup to avoid duplicate contacts
- Fixed HTML entity encoding bug in calendar event titles

**Stats:**

- 10 files changed
- +1,322 / -306 lines changed
- 4 phases, 5 plans (+ 1 FIX)
- 1 day from start to ship

**Git range:** `1c9dbe0` → `cef3d90`

**What's next:** To be determined

---

## v4.7 Dark Mode & Activity Polish (Shipped: 2026-01-17)

**Delivered:** Comprehensive dark mode contrast fixes for modals and settings, activity type improvements (Dinner, Zoom), and UI bug fixes.

**Phases completed:** 71-72 (4 plans total)

**Key accomplishments:**

- Dark mode support for WorkHistoryEditModal and AddressEditModal with proper contrast
- Settings subtab button contrast improved (dark:text-gray-300)
- Timeline component dark mode with 13 variants for activity labels
- Added Dinner and Zoom activity types with proper icons
- Renamed "Phone call" to "Phone" for consistency
- Fixed topbar z-index layering (z-30) on People screen
- Fixed person header spacing (" at " between job and company)

**Stats:**

- 28 files changed
- +1,867 / -300 lines changed
- 2 phases, 4 plans
- 1 day from start to ship

**Git range:** `3cc3967` → `3024544`

**What's next:** To be determined

---

## v4.4 Code Organization (Shipped: 2026-01-16)

**Delivered:** PHP codebase reorganization with PSR-4 namespaces, Composer autoloading, and one-class-per-file compliance.

**Phases completed:** 64-66 (6 plans total)

**Key accomplishments:**

- Comprehensive codebase audit identifying 41 classes across 39 PHP files
- Split notification channel classes into separate files (one-class-per-file compliance)
- Added PSR-4 namespaces to 38 PHP classes across 9 namespace groups
- Configured Composer autoloading with classmap for includes/ directory
- Added 38 backward-compatible class aliases for migration period
- Removed manual prm_autoloader() function (52 lines deleted)
- Enabled PHPCS Generic.Files.OneClassPerFile rule

**Stats:**

- 61 files changed
- +3,958 / -1,535 lines changed
- 3 phases, 6 plans
- 1 day from start to ship

**Git range:** `8c90f0e` → `30ff0bd`

**What's next:** To be determined

---

## v4.2 Settings & Stability (Shipped: 2026-01-15)

**Delivered:** DOM stability improvements with error boundary, settings restructure with Connections tab, and automatic calendar event re-matching when person emails change.

**Phases completed:** 58-60 (3 plans total)

**Key accomplishments:**

- DOM stability via translate="no", Google notranslate meta tag, and DomErrorBoundary for graceful recovery
- Settings restructure with Connections tab containing Calendars/CardDAV/Slack subtabs
- Automatic calendar event re-matching when a person's email addresses change
- WP-CLI command: `wp prm calendar rematch --user-id=ID` for manual re-matching

**Stats:**

- 21 files changed
- +2,258 / -664 lines changed
- 3 phases, 3 plans, 7 tasks
- 1 day from start to ship

**Git range:** `27733d8` → `5bdab94`

**What's next:** To be determined

---

## v4.1 Bug Fixes & Polish (Shipped: 2026-01-15)

**Delivered:** Dark mode contrast fixes, improved deploy procedure to prevent MIME type errors, dashboard 3-row layout, and dynamic favicon that updates with accent color.

**Phases completed:** 56-57 (3 plans total)

**Key accomplishments:**

- Dark mode contrast fixes for CardDAV connection details and search modal
- Two-step rsync deploy procedure to prevent MIME type errors from stale build artifacts
- Dashboard restructured to 3-row layout (Stats | Activity | Favorites)
- Timezone-aware meeting times using ISO 8601 format
- Dynamic favicon that updates when accent color changes

**Stats:**

- 23 files changed
- +1,260 / -309 lines changed
- 2 phases, 3 plans, 8 tasks
- 1 day from start to ship

**Git range:** `5ccfbcd` → `84c19c2`

**What's next:** To be determined

---

## v4.0 Calendar Integration (Shipped: 2026-01-15)

**Delivered:** Full calendar integration with Google Calendar and CalDAV (iCloud/Fastmail/Nextcloud) support, automatic contact matching, background sync, and Today's Meetings dashboard widget.

**Phases completed:** 47-55 (11 plans total)

**Key accomplishments:**

- Google Calendar OAuth2 integration with google/apiclient library and automatic token refresh
- CalDAV provider supporting iCloud, Fastmail, Nextcloud, and generic CalDAV servers via Sabre DAV
- Email-first contact matching algorithm with fuzzy name fallback and confidence scores
- Calendar settings UI with connection management, sync controls, and credential testing
- Person profile Meetings tab with "Log as Activity" functionality for past meetings
- Background sync via WP-Cron every 15 minutes with round-robin user rate limiting
- Today's Meetings dashboard widget with attendee avatars and person navigation
- WP-CLI commands: `wp prm calendar sync`, `wp prm calendar status`, `wp prm calendar auto-log`

**Stats:**

- 54 files changed
- +72,880 / -337 lines changed
- 9 phases, 11 plans
- 1 day from start to ship

**Git range:** `011b013` → `7ecf366`

**What's next:** To be determined

---

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
