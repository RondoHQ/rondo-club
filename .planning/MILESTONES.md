# Project Milestones: Rondo Club

## v20.0 Configurable Roles (Shipped: 2026-02-08)

**Delivered:** Replaced hardcoded club-specific arrays with database-driven settings and dynamic queries so any sports club can use Rondo Club without code changes. Filter options are now dynamic, role classifications are configurable, and sync layer has no default fallbacks.

**Phases completed:** 151-154 (4 phases, 4 plans)

**Key accomplishments:**

- Dynamic filter infrastructure deriving People list filter options from actual database values with smart sorting and counts
- Role settings admin UI for configuring player roles and excluded/honorary roles (pre-existing from v19.1.0)
- Business logic wired to configured settings — volunteer status and team detail player/staff split no longer use hardcoded arrays
- Sync layer cleaned up — rondo-sync passes through role descriptions without modification, all hardcoded fallbacks removed
- Generic extensibility patterns established (filter config, skip-and-warn) for future use

**Stats:**

- 50 files changed (rondo-club) + 7 files (rondo-sync)
- +4,853 / -2,229 lines changed (rondo-club)
- 4 phases, 4 plans, 7 tasks
- 2 days (2026-02-06 → 2026-02-08)

**Git range:** `f672b4fb` → `52913e7c` (22 commits, rondo-club)

**What's next:** v21.0 Per-Season Fee Categories

---

## v19.0 Birthdate Simplification (Shipped: 2026-02-06)

**Delivered:** Simplified birthdate handling by moving from the Important Dates CPT to a direct person field, then removed the now-unnecessary Important Dates infrastructure entirely. Reduces complexity and aligns with the Sportlink data model.

**Phases completed:** 147-150 (4 phases, 5 plans)

**Key accomplishments:**

- Birthdate ACF field on person records with Dutch-formatted display ("43 jaar (6 feb 1982)")
- Dashboard birthday widget queries person birthdate meta directly
- Deleted Important Dates CPT, date_type taxonomy, and 1,069 production records
- Removed "Datums" navigation, DatesList page, ImportantDateModal component
- Updated reminders and iCal systems to generate from person birthdate field
- Fixed vCard export to read from person.acf.birthdate (gap closure from audit)
- Updated 5 documentation files removing stale "important dates" references

**Stats:**

- 73 files changed
- +3,331 / -3,605 lines changed (net: -274)
- 4 phases, 5 plans
- Same day (2026-02-06, ~4.5 hours)

**Git range:** `e96940d3` → `80bda5e1` (30 commits)

**What's next:** To be determined

---

## v17.0 De-AWC (Shipped: 2026-02-05)

**Delivered:** Transformed Rondo Club from club-specific to fully configurable — any sports club can install and configure without code changes via admin settings for club name, accent color, and FreeScout URL.

**Phases completed:** 144-146 (3 phases, 4 plans)

**Key accomplishments:**

- ClubConfig backend service with WordPress Options API for club name, accent color, and FreeScout URL
- REST API endpoint `/rondo/v1/config` with admin write + all-users read permissions
- Admin-only club configuration UI in Settings with react-colorful color picker and live preview
- Dynamic color system renaming "awc" to "club" throughout codebase (Tailwind, CSS, React, PHP)
- Dynamic WordPress login page styling and PWA theme-color from club configuration
- FreeScout integration URL externalized (hidden when not configured)
- Zero club-specific hardcoded references — theme now installable by any sports club

**Stats:**

- 67 files modified
- +6,215 / -1,403 lines changed (net: +4,812)
- 3 phases, 4 plans
- 1 day (2026-02-05)

**Git range:** `db380e95` → `a2617711`

**What's next:** To be determined

---

## v16.0 Infix / Tussenvoegsel (Shipped: 2026-02-05)

**Delivered:** Added infix (tussenvoegsel) field to person data model for Dutch naming convention support. Covers storage, display, auto-title, search, REST API, vCard import/export, Google Contacts sync, CardDAV, and frontend UI.

**Phases completed:** 141-143 (3 phases)

**Key accomplishments:**

- ACF `infix` text field between first_name and last_name (read-only in UI)
- Auto-title generation uses `array_filter` + `implode` pattern for safe "First Infix Last" concatenation
- REST API filtered endpoint includes infix JOIN and response field
- Global search includes infix with score 50
- vCard export/import maps infix to N field position 3 (Additional Names)
- Google Contacts export uses `setMiddleName()`, import reads `getMiddleName()` at all 3 locations
- Google CSV export populates "Additional Name" column, import reads it
- CardDAV create/update flows include infix
- Frontend: read-only infix field in PersonEditModal, name display in PeopleList, VOGList, and all other name surfaces
- `formatPersonName()` utility for consistent name formatting

**Files changed:** 20 files across PHP backend, React frontend, and documentation

**Git range:** TBD

**What's next:** To be determined

---

## v15.0 Personal Tasks (Shipped: 2026-02-04)

**Delivered:** Transformed the task system from shared visibility to personal isolation - each user manages their own task list with clear UI messaging indicating tasks are personal.

**Phases completed:** 139-140 (2 plans total)

**Key accomplishments:**

- User isolation for tasks via post_author filtering in WP_Query and REST API
- Dashboard todo counts (open/awaiting) now filter by current user only
- WP-CLI command (`wp rondo tasks verify_ownership`) for task ownership verification
- Tasks navigation accessible to all users without capability gating
- Personal tasks info messages in TodosList page and GlobalTodoModal with Dutch text

**Stats:**

- 27 files changed
- +3,079 / -920 lines changed
- 2 phases, 2 plans
- Same day (2026-02-04)

**Git range:** `726070b2` → `07338c46` (19 commits)

**What's next:** To be determined

---

## v14.0 Performance Optimization (Shipped: 2026-02-04)

**Delivered:** Eliminated duplicate API calls on page load, implemented modal lazy loading for people selectors, centralized query deduplication for current-user and VOG count, and optimized backend todo count queries to use efficient SQL COUNT instead of fetching all records.

**Phases completed:** 135-138 (5 plans total)

**Key accomplishments:**

- Eliminated duplicate API calls by migrating to createBrowserRouter data router pattern and fixing ES module double-load issue (browser cached by full URL including query string)
- Implemented modal lazy loading - QuickActivityModal, TodoModal, GlobalTodoModal fetch 1400+ people records only when opened
- Created centralized useCurrentUser hook with 5-minute caching shared across 6 components
- Added staleTime caching to VOG count preventing refetch on every navigation
- Replaced inefficient get_posts() with wp_count_posts() for backend todo count queries

**Stats:**

- 19 files changed
- +724 / -851 lines changed
- 4 phases, 5 plans
- Same day (2026-02-04, ~3 hours)

**Performance Impact:**
- API calls on dashboard load: 14 → 7 (50% reduction)
- People fetched on dashboard: 1400+ → 0 (100% reduction)
- Current-user calls per session: ~90% reduction
- VOG count calls per session: ~80% reduction

**Git range:** `5ea761b1` → `78db09ab` (36 commits)

**What's next:** To be determined

---

## v13.0 Discipline Cases (Shipped: 2026-02-03)

**Delivered:** Discipline case tracking with capability-based access control - view Sportlink-synced discipline cases in a dedicated list page with season filtering and on person profile Tuchtzaken tabs, restricted to users with the `fairplay` capability.

**Phases completed:** 132-134 (5 plans total)

**Key accomplishments:**

- `discipline_case` CPT with 11 ACF fields (dossier-id, person, match/charges/sanctions/fee)
- Shared `seizoen` taxonomy with current season support
- `fairplay` capability for access control (admins auto-assigned)
- Discipline cases list page with season filter and expandable table rows
- Person detail Tuchtzaken tab (hidden if no cases)
- Read-only UI consistent with Sportlink data model

**Stats:**

- 30 files changed
- +5,853 / -843 lines changed
- 3 phases, 5 plans
- Same day (2026-02-03, ~2 hours)

**Git range:** `feat(132-01)` → `docs(134)`

**What's next:** To be determined

---

## v12.1 Contributie Forecast (Shipped: 2026-02-03)

**Delivered:** Next season fee forecast for budget planning - toggle between current season (actual billing data) and next season (projected fees based on current membership with 100% pro-rata and family discounts).

**Phases completed:** 129-131 (3 plans total)

**Key accomplishments:**

- Backend forecast calculation with `get_next_season_key()` method returning next season key
- API extended with `forecast=true` parameter for next season projections
- 100% pro-rata for all forecast members (full year assumption for budget planning)
- Family discounts correctly applied using existing address grouping logic
- Season selector dropdown with "(huidig)" and "(prognose)" labels
- Conditional column rendering hiding Nikki/Saldo in forecast mode (no billing data exists)
- Forecast indicator badge with visual distinction
- Google Sheets export with "(Prognose)" title suffix and 8-column layout

**Stats:**

- 24 files changed
- +2,825 / -503 lines changed
- 3 phases, 3 plans
- Same day (2026-02-02)

**Git range:** `feat(129-01)` → `feat(131-01)`

**What's next:** To be determined

---

## v12.0 Membership Fees (Shipped: 2026-02-01)

**Delivered:** Complete membership fee calculation system with configurable fee amounts, age-based calculation, family discounts (25%/50% tiers), pro-rata for mid-season joins, Nikki integration for contribution comparison, and Google Sheets export.

**Phases completed:** 123-128 (15 plans total)

**Key accomplishments:**

- Admin settings UI for configuring 6 fee amounts (Mini, Pupil, Junior, Senior, Recreant, Donateur)
- Age-based fee calculation engine with season caching and diagnostics
- Family discount system with address grouping and tiered discounts (25%/50% for 2nd/3rd+ member)
- Pro-rata calculation for mid-season joins (quarterly tiers: 100%/75%/50%/25%)
- Contributie list page with sortable columns, category badges, and visual indicators
- Fee caching with automatic invalidation on relevant field changes
- Nikki integration showing actual contributions vs calculated fees with red/green saldo display
- Google Sheets export with Dutch formatting and auto-open

**Stats:**

- 62 files changed
- +13,289 / -357 lines changed
- 7 phases (including 127.1 insertion), 15 plans
- 2 days (2026-01-31 → 2026-02-01)

**Git range:** `feat(123-01)` → `feat(128-01)`

**What's next:** To be determined

---

## v10.0 Read-Only UI for Sportlink Data (Shipped: 2026-01-29)

**Delivered:** Restricted UI editing capabilities for Sportlink-managed data (person delete, address, work history) while preserving full REST API functionality for automation and sync, plus custom field edit control.

**Phases completed:** 116-118 (3 plans total)

**Key accomplishments:**

- Removed delete, address editing, and work history editing from PersonDetail UI
- Disabled team and commissie creation in UI (list pages and quick-add menu)
- Added `editable_in_ui` property to custom fields with settings toggle
- Implemented read-only display with Lock icon for API-managed fields
- All REST API endpoints preserved for Sportlink automation

**Stats:**

- 25 files changed
- +2,871 / -716 lines changed
- 3 phases, 3 plans
- 1 day (2026-01-29)

**Git range:** `feat(116-01)` → `docs(118)`

**What's next:** To be determined

---

## v9.0 People List Performance & Customization (Shipped: 2026-01-29)

**Delivered:** Server-side pagination with optimized SQL queries, birthdate denormalization for fast filtering, custom field sorting, and per-user column customization with drag-drop reordering and resize.

**Phases completed:** 111-115 (10 plans total)

**Key accomplishments:**

- Server-side pagination with wpdb JOINs reducing data transfer 14x (1400+ → 100 per page)
- Birthdate denormalization syncing birthday dates to `_birthdate` meta for fast birth year filtering
- Custom field sorting with type-appropriate ORDER BY clauses (text, number, date)
- User preferences backend storing column visibility, order, and widths in user_meta
- Column customization UI with show/hide toggles, drag-drop reordering, and resize handles
- Removed "show in list view" from custom field settings (replaced by per-user selection)

**Stats:**

- 66 files changed
- +13,341 / -855 lines changed
- 5 phases, 10 plans
- 1 day (2026-01-29)

**Git range:** `58f4e44` → `74cddcd`

**Tech debt:** Cross-tab sync for column preferences (minor, non-blocking)

**What's next:** To be determined

---

## v8.0 PWA Enhancement (Shipped: 2026-01-28)

**Delivered:** Progressive Web App transformation with installable app experience on iOS and Android, offline support with cached data access, pull-to-refresh gesture, and smart install prompts.

**Phases completed:** 107-110 (15 plans total)

**Key accomplishments:**

- PWA Foundation with Web App Manifest, vite-plugin-pwa, iOS meta tags, and safe area CSS for notched devices
- Offline Support with service worker asset caching, offline fallback page, cached API data display, and offline banner with form mutation protection
- Mobile UX with pull-to-refresh on all list and detail views and iOS overscroll prevention in standalone mode
- Install Experience with smart Android install prompt (engagement-based), iOS install instructions modal, and periodic update notifications
- Dutch localization of all PWA notifications and prompts
- Lighthouse PWA score 90+, verified on real iOS and Android devices in standalone mode

**Stats:**

- 52 files changed
- +5,613 / -911 lines changed
- 4 phases, 15 plans
- 1 day (2026-01-28)

**Git range:** `9e7ac2f` → `3a073af`

**What's next:** To be determined

---

## v7.0 Dutch Localization (Shipped: 2026-01-25)

**Delivered:** Complete Dutch translation of the entire Rondo Club React frontend, including navigation, dashboard, entity pages (Leden, Teams, Commissies, Datums, Taken), settings, and all global UI elements.

**Phases completed:** 99-106 (22 plans total)

**Key accomplishments:**

- Dutch date formatting foundation with centralized dateFormat.js utility and nl locale for all date displays
- Complete navigation translation (Leden, Teams, Commissies, Datums, Taken, Instellingen) with proper Dutch gender agreement
- Dashboard fully localized with Dutch stat labels, widget titles, empty states, and messages
- Entity pages translated: People (Leden), Teams, Commissies with forms, modals, and validation messages
- Settings pages completed: all 6 tabs (Weergave, Koppelingen, Meldingen, Gegevens, Beheer, Info) fully translated
- Global UI elements finished: buttons, dialogs, activity types, contact types, and rich text editor tooltips

**Stats:**

- 131 files changed
- +15,887 / -2,108 lines changed
- 8 phases, 22 plans
- Same day (2026-01-25)

**Git range:** `c20bf2d` → `f9e59c9`

**What's next:** To be determined

---

## v6.0 Custom Fields (Shipped: 2026-01-21)

**Delivered:** Admin-defined custom fields for People and Teams with 14 field types, Settings UI, detail view integration, list view columns, and global search support.

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

**Delivered:** Bidirectional Google Contacts synchronization with import, export, delta sync, conflict resolution (Rondo Club wins), and WP-CLI commands for administration.

**Phases completed:** 79-85 (16 plans total)

**Key accomplishments:**

- Google Contacts OAuth with incremental scope addition (existing Calendar users can add Contacts without re-auth)
- Import from Google with field mapping, email-based duplicate detection, and photo sideloading
- Export to Google with reverse field mapping and etag conflict handling
- Delta sync using Google syncToken for efficient change detection
- Conflict resolution with Rondo Club-wins strategy and activity logging for audit
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
- Fixed person header spacing (" at " between job and team)

**Stats:**

- 28 files changed
- +1,867 / -300 lines changed
- 2 phases, 4 plans
- 1 day from start to ship

**Git range:** `3cc3967` → `3024544`

**What's next:** To be determined

---

## v4.4 Code Team (Shipped: 2026-01-16)

**Delivered:** PHP codebase reteam with PSR-4 namespaces, Composer autoloading, and one-class-per-file compliance.

**Phases completed:** 64-66 (6 plans total)

**Key accomplishments:**

- Comprehensive codebase audit identifying 41 classes across 39 PHP files
- Split notification channel classes into separate files (one-class-per-file compliance)
- Added PSR-4 namespaces to 38 PHP classes across 9 namespace groups
- Configured Composer autoloading with classmap for includes/ directory
- Added 38 backward-compatible class aliases for migration period
- Removed manual stadion_autoloader() function (52 lines deleted)
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

- Current position (job title + team) display in person header
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

- rondo_todo custom post type (migrated from comments)
- WordPress post statuses (rondo_open, rondo_awaiting, rondo_completed)
- Awaiting response tracking with timestamps and aging indicators
- Filter UI (Open/Awaiting/Completed tabs) across all views
- WP-CLI migration: `wp prm migrate-todos`
- 25 PHPUnit tests for todo functionality

**What's next:** v3.2 Person Profile Polish

---

## v2.3 List View Unification (Shipped: 2026-01-13)

**Delivered:** Unified list view experience across People and Teams, removing card view and ensuring consistent UX with full bulk action parity.

**Phases completed:** 16-18 (3 plans total)

**Key accomplishments:**

- Removed card view toggle from People, list view is now the only option
- Added dedicated image column to People list for proper alignment
- Built Teams list view with columns (logo, name, industry, website, workspace, labels)
- Added sortable columns and selection infrastructure to Teams
- Created bulk actions for Teams: visibility, workspace assignment, label management
- Full parity between People and Teams list views

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
- Team, Workspace, Labels sorting options
- BulkTeamModal with search and clear option
- BulkLabelsModal with add/remove mode toggle

**Issues closed:** ISS-001, ISS-002, ISS-003, ISS-004, ISS-005

**What's next:** v2.3 List View Unification

---

## v2.1 Bulk Operations (Shipped: 2026-01-13)

**Delivered:** Efficient bulk management of contacts through a new list view with multi-select and batch actions.

**Phases completed:** 12-13 (3 plans total)

**Key accomplishments:**

- Card/list view toggle for people screen
- Tabular list view with Name, Team, Workspace columns
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

- Split class-rest-api.php into 5 domain-specific classes (Base, People, Teams, Slack, Import/Export)
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

## v21.0 Per-Season Fee Categories (Shipped: 2026-02-09)

**Delivered:** Replaced all hardcoded fee category definitions with fully configurable per-season categories — admins can manage fee categories, family discounts, and matching rules through a settings UI, with all display surfaces rendering dynamically from the API.

**Phases completed:** 155-161 (7 phases, 12 plans, 14 tasks)

**Key accomplishments:**

- Slug-keyed category data model with full metadata (label, amount, age classes, youth flag, sort order) and copy-forward for new seasons
- Config-driven fee calculation using Sportlink age class matching, replacing all hardcoded constants and parse_age_group()
- REST API for full category CRUD with structured validation (errors vs warnings) and full replacement pattern
- Admin Settings UI with drag-and-drop reordering, inline CRUD, age class coverage display, and season selector
- All display surfaces (contributie list, person finance card, Google Sheets export) render dynamically from API metadata
- Family discount percentages configurable per season with separate WordPress option and copy-forward
- Team and werkfunctie matching rules configurable per category, replacing hardcoded is_recreational_team() and is_donateur()

**Stats:**

- 70 files changed
- +14,187 / -863 lines changed
- 7 phases, 12 plans, 14 tasks
- 2 days (2026-02-08 → 2026-02-09)

**Git range:** `feat(155-01)` → `feat(161-02)`

**What's next:** To be determined

---


## v22.0 Design Refresh (Shipped: 2026-02-09)

**Delivered:** Complete visual rebrand of the React SPA — migrated to Tailwind CSS v4 with OKLCH brand tokens, replaced the dynamic accent color system with fixed brand palette (electric-cyan, bright-cobalt, deep-midnight, obsidian), applied gradient text treatment to headings, new button/card/input styling, adapted dark mode, and cleaned up PWA assets and backend theming code.

**Phases completed:** 162-165 (4 phases, 7 plans, 13 tasks)

**Key accomplishments:**

- Tailwind CSS v4 migration with CSS-first @theme configuration, OKLCH color tokens, and Montserrat headings
- Fixed brand palette replacing dynamic accent color system — useTheme hook simplified, react-colorful removed, ClubConfig accent_color eliminated
- Component styling refresh: gradient buttons, cards with 3px gradient top border, cyan glow focus rings, 200ms hover lift transitions
- Gradient text treatment on page headings and section titles (cyan-to-cobalt)
- Dark mode adapted to brand colors (preserved, not removed)
- PWA manifest/favicon updated to electric-cyan, dead REST API theme endpoints removed, Rondo logo integrated throughout

**Stats:**

- 112 files changed
- +8,937 / -4,347 lines changed
- 4 phases, 7 plans, 13 tasks
- 1 day (2026-02-09)

**Git range:** `a854e517` → `57ec0dde` (38 commits)

**What's next:** To be determined

---


## v23.0 Former Members (Shipped: 2026-02-09)

**Delivered:** Archive former members when they leave the club (detected by rondo-sync), hiding them from default views while preserving all data. Findable through global search, a filter toggle on the Leden list, and correctly handled in fee calculations.

**Phases completed:** 166-169 (4 phases, 4 plans, 9 tasks)

**Key accomplishments:**

- Former member ACF field with rondo-sync marking (PUT instead of DELETE) preserving all member history and enabling rejoin detection
- Database-level filtering excludes former members from Leden list, dashboard stats, and team rosters using NULL-safe exclusion pattern
- "Toon oud-leden" toggle with URL-persisted state and "Oud-lid" badges in People list and global search
- Fee calculations correctly include former members active during the season (lid-sinds before season end), exclude from forecast
- Fee cache invalidation on former_member field changes, family discount excludes ineligible former members

**Stats:**

- 33 files changed (rondo-club) + cross-repo (rondo-sync, developer)
- +2,958 / -268 lines changed
- 4 phases, 4 plans, 9 tasks
- 1 day (2026-02-09)

**Git range:** `bcd3d739` → `2945c7b6`

**What's next:** To be determined

---

