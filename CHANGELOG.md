# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [25.1.0] - 2026-02-14

### Changed
- Dashboard birthday card now shows all of today's birthdays even when there are more than 5

## [25.0.1] - 2026-02-14

### Fixed
- Exclude former members (oud-leden) from "Komende herinneringen" on dashboard and in email digests

## [25.0.0] - 2026-02-14

### Added
- Autonomous feedback agent with PR workflow (`bin/get-feedback.sh`)
- Agent creates GitHub PRs instead of deploying directly
- `needs_info` feedback status for agent follow-up questions
- Feedback comments system (`rondo_feedback_comment` comment type)
- REST endpoints: `GET/POST /rondo/v1/feedback/{id}/comments`
- Agent-to-user conversation thread on FeedbackDetail page
- Reply form on `needs_info` feedback (auto-transitions to `approved` on reply)
- `pr_url` and `agent_branch` meta fields on feedback REST API
- PR link display on FeedbackDetail and FeedbackManagement pages
- "Waiting for your response" banner on `needs_info` feedback
- Idle-mode code optimization (`--optimize` flag)
- Optimization file tracker (`logs/optimization-tracker.json`)
- Agent prompt at `.claude/agent-prompt.md`
- Optimization prompt at `.claude/optimize-prompt.md`
- launchd plist template for Mac Mini scheduling
- Developer docs for feedback agent system

### Changed
- `get-feedback.sh` rewritten with PR workflow, branch management, and crash recovery
- Feedback status set to `in_progress` before Claude runs (prevents re-pickup)
- Crash cleanup resets feedback status and returns to main branch
- Branch cleanup after each run (merged `feedback/*` and `optimize/*` branches)

## [24.1.0] - 2026-02-13

### Removed
- Removed `person_label` and `team_label` taxonomy registrations and all database data
- Removed `commissie_label` taxonomy registration and database data
- Removed Settings/Labels management page from frontend
- Removed BulkLabelsModal component and label bulk actions from all list views
- Removed label columns, badges, and filters from PeopleList, TeamsList, CommissiesList
- Removed label add/remove controls from PersonDetail
- Removed all label-related API client methods (12 methods)
- Removed `date_type` field from reminders and iCal birthday data
- Removed `CATEGORIES` line from iCal birthday events
- Removed deprecated `RONDO_Dates_CLI_Command` WP-CLI class
- Removed deprecated `migrate_birthdates` and `update_date_references` WP-CLI commands
- Removed residual `important_date` references from route map, REST API, and CLI
- Removed teams and commissies bulk-update endpoints (were labels-only)

### Changed
- Updated email notification wording from "important dates" to "birthdays"
- Updated CLI reminder messages to reflect birthday-only system
- Simplified people bulk-update endpoint (organization_id only)
- Updated AGENTS.md and developer docs to reflect simplified data model

## [24.0.0] - 2026-02-12

### Added
- Demo data pipeline: `wp rondo demo export` creates anonymized fixture from production data
- Demo data pipeline: `wp rondo demo import [--clean]` loads fixture into any WordPress instance
- Dutch fake data generator (names with infixes, addresses, phone numbers, emails)
- Date-shifting on import so demo data always looks current relative to today
- Data anonymization: fake names, emails, phones, addresses replace real PII
- Weighted fake financial amounts for realistic fee patterns
- Season-aware date shifting for fee configs and discipline case seasons
- Demo fixture file (`fixtures/demo-fixture.json`) committed for portable demo environments
- Demo site banner ("DEMO OMGEVING") distinguishes demo from production
- `bin/deploy-demo.sh` script for deploying to demo.rondo.club

### Removed
- Photos and avatars stripped from demo fixture (photos are identity, not anonymizable)

## [23.2.1] - 2026-02-10

### Changed
- Move VOG settings from Settings > Admin > VOG into the VOG page itself as an admin-only Instellingen tab
- VOG page now uses tabbed layout matching the Contributie page pattern (Overzicht + Instellingen)
- Non-admin users only see the Overzicht tab on the VOG page

### Removed
- VOG subtab from Settings Admin section (all VOG state, effects, handlers, and component removed)

## [23.2.0] - 2026-02-09

### Added
- Former member fee calculation logic: eligible former members appear in contributie list
- `is_former_member_in_season()` method checks if former member qualifies for season (lid-sinds before season end)
- Former members use normal pro-rata based on lid-sinds (leaving doesn't affect fee)
- Former member exclusion from fee forecast (won't be members next season)
- Family discount calculation excludes ineligible former members from family groups
- `is_former_member` field in fee API responses (`/rondo/v1/fees`, `/rondo/v1/fees/person/{id}`)
- Fee cache invalidation on `former_member` field changes
- Former member season eligibility diagnostics in `get_calculation_status()`
- Google Sheets export applies former member fee rules
- Contributie Logic section in developer documentation (`features/former-members.md`)

## [23.1.0] - 2026-02-09

### Added
- "Toon oud-leden" toggle in People list filter dropdown to show former members
- "Oud-lid" badge visual indicator for former members in People list rows
- "Oud-lid" badge in global search results for former members
- `include_former` parameter on `/rondo/v1/people/filtered` endpoint (1=include, empty=exclude)
- `former_member` boolean field in filtered people response
- `former_member` field in `format_person_summary()` (affects search, dashboard, all person summaries)
- Reduced opacity (60%) styling for former member rows in People list
- Former member filter state persisted in URL (`?oudLeden=1`)
- Former member toggle counted in active filters badge
- Export to Google Sheets includes former members when toggle is active
- Developer documentation for former member system (`features/former-members.md`)

## [22.0.0] - 2026-02-09

### Added
- Tailwind CSS v4 with CSS-first @theme configuration and OKLCH color space brand tokens
- Brand color palette: electric-cyan, bright-cobalt, deep-midnight, obsidian
- Montserrat font for headings via @fontsource/montserrat (weights 600, 700)
- Cyan-to-cobalt gradient utilities (bg-brand-gradient, text-brand-gradient)
- Gradient text treatment on page headings and section titles
- Primary gradient buttons (cyan → cobalt) with hover lift effect
- Glass button variant with transparent background
- Card components with 3px gradient top border
- Input/textarea focus states with electric-cyan border and cyan glow ring
- Rondo logo integrated as favicon, login page logo, and sidebar brand mark
- PWA icon generation script using Rondo logo source

### Changed
- Migrated from Tailwind CSS v3.4 to v4 (clean break, no backward compatibility)
- Dark mode adapted to use brand colors (preserved, not removed)
- PWA manifest theme-color updated to electric-cyan (#0891B2)
- Login page restyled with brand gradient and Rondo logo
- Hover transitions standardized to 200ms ease-in-out with translateY(-2px) lift
- useTheme hook simplified (dark mode toggle only, no dynamic color injection)

### Removed
- Dynamic accent color system (CSS variable injection, accent-* scale, data-accent attributes)
- react-colorful color picker dependency
- Color picker UI from Settings page
- ClubConfig accent_color WordPress option and REST API field
- Dead REST API theme endpoints (/rondo/v1/config accent_color field)
- Contact import feature (vCard and Google CSV file upload) - replaced by live Google Contacts API sync
- User approval system
- how_we_met and met_date person fields

## [21.1.0] - 2026-02-09

### Added
- Configurable family discount: admin can set second child and third child discount percentages per season
- Family discount configuration stored per season in WordPress options with fallback to defaults (25%/50%)
- FamilyDiscountSection component in fee category settings UI
- REST API validation for family discount percentages (0-100 range, warning if 2nd >= 3rd)
- Configurable matching rules: each fee category can specify matching teams (by ID) and matching werkfuncties
- Admin UI for selecting teams and werkfuncties per fee category in Settings
- GET /rondo/v1/werkfuncties/available endpoint for listing distinct werkfunctie values
- Auto-migration: existing 'recreant' categories pre-populated with recreational team IDs, 'donateur' with Donateur werkfunctie

### Changed
- `get_family_discount_rate()` reads from per-season config instead of returning hardcoded values
- GET/POST `/rondo/v1/membership-fees/settings` now includes `family_discount` field
- `calculate_fee()` now uses config-driven team and werkfunctie matching instead of hardcoded `is_recreational_team()` and `is_donateur()`
- `is_recreational_team()` and `is_donateur()` deprecated (kept for migration only)

## [21.0.0] - 2026-02-09

### Added
- Per-season fee category management UI in Settings > Beheer > Contributie
- Season selector toggle for managing current and next season categories
- Drag-and-drop reordering of fee categories with sort order persistence
- Inline editing of category fields: label, amount, age classes, youth flag
- Age class coverage summary showing which Sportlink age classes map to which categories
- API validation feedback: errors (blocking) and warnings (informational) displayed in UI
- Fee category data model with per-season storage in WordPress options
- Config-driven fee calculation replacing hardcoded category constants
- REST API endpoints for category CRUD with structured validation

### Changed
- Fee calculation now reads from per-season category configuration instead of hardcoded values
- Fee settings UI is fully dynamic - no hardcoded fee type names
- Category sort order derived from config, removing duplicated arrays across codebase
- Fee list REST API response includes category metadata for dynamic frontend rendering

### Removed
- Hardcoded fee type definitions (mini, pupil, junior, senior, recreant, donateur) from Settings UI
- Hardcoded category_order arrays from REST API, Google Sheets export, and ContributieList
- Old flat-amount fee settings UI replaced with full category management

## [20.0.0] - 2026-02-08

### Added
- Dynamic filter options on People list derived from database values instead of hardcoded arrays
- REST API endpoint `/rondo/v1/people/filter-options` returning available filters with counts
- Filter dropdowns show count of matching people per option (e.g., "Junior (42)")
- Loading and error states for filter dropdowns with retry functionality
- Generic filter infrastructure for easily adding future dynamic filters

### Changed
- People list "Type lid" filter now shows only values that exist in the database
- People list "Leeftijdsgroep" filter now shows only values that exist in the database
- Team detail page player/staff split now reads from configured role settings instead of hardcoded array

### Fixed
- Volunteer role settings API endpoint now accessible to all authenticated users (was admin-only for GET)

### Removed
- Family tree visualization feature (route `/people/:id/family-tree`, vis-network/vis-data dependencies)
- Hardcoded filter option arrays for Type lid and Leeftijdsgroep in PeopleList

## [19.1.0] - 2026-02-07

### Added
- Configurable role classification for volunteer status in Settings > Beheer > Rollen
- REST API endpoints for volunteer role management (`/rondo/v1/volunteer-roles/available`, `/rondo/v1/volunteer-roles/settings`)
- WordPress options `rondo_player_roles` and `rondo_excluded_roles` replace hardcoded constants
- Admin UI to classify Sportlink job titles as Speler, Uitgesloten, or Vrijwilliger
- Saving role classifications triggers automatic volunteer status recalculation for all people

## [19.0.1] - 2026-02-06

### Fixed
- Documentation references to "important dates" updated to reflect birthdate-on-person model
- Removed stale `/dates` route documentation from frontend architecture
- CardDAV sync docs now correctly reference "Birthday (from person record)"
- API docs now correctly describe birth_year as "derived from birthdate field"
- iCal feed docs now describe birthday subscription feature
- Daily digest docs now reference "Upcoming birthdays" instead of "important dates"

## [19.0.0] - 2026-02-06

### Removed
- Important Dates custom post type and all associated functionality
- Important Dates page in frontend navigation
- Date type taxonomy
- Important Dates widget on PersonDetail page
- ImportantDateModal component
- useDates hook and all date-related API endpoints
- Death date display (is_deceased flag now returns false)
- Backend PHP classes: STADION_Important_Dates, STADION_Date_Types
- 1069 important_date records deleted from production database

### Changed
- Birthdays now stored directly on person records via birthdate ACF field
- iCal feeds generate birthday events from person.birthdate instead of important_date posts
- Daily digest reminders now source birthdays from person records
- FamilyTree and PersonDetail now use person.is_deceased flag from allPeople data
- Import (vCard/Google Contacts) saves birthday to person.birthdate field instead of creating important_date

## [18.1.1] - 2026-02-06

### Fixed
- Prognosis (forecast) mode now uses next season's fee rates instead of current season's rates

## [18.1.0] - 2026-02-05

### Added
- Per-season membership fee settings (current and next season)
- Automatic migration of existing global fees to current season
- Documentation for membership fees system (docs/membership-fees.md)

### Changed
- Settings UI shows two fee sections: current season and next season
- API returns both seasons, accepts season parameter for updates
- Each season saves independently with its own button

## [18.0.0] - 2026-02-05

### Removed
- Slack integration (OAuth, REST endpoints, settings UI, and notifications)
- Slack contact type support in contact editing and vCard import/export
- Monica CRM import (backend importer and settings UI)

## [17.0.0] - 2026-02-05

### Added
- ClubConfig backend service for club-wide settings (club name, accent color, FreeScout URL)
- REST API endpoint `/stadion/v1/config` with admin write + all-users read permissions
- Admin-only club configuration section in Settings with react-colorful color picker
- Live preview for club color changes in Settings
- Dynamic WordPress login page styling from club configuration
- PWA theme-color meta tags read from club config

### Changed
- Renamed "awc" accent color to "club" throughout codebase (Tailwind, CSS, React, PHP)
- FreeScout integration URL now reads from club config (hidden when not configured)
- All AWC-specific comments and references removed from source code
- Documentation uses generic placeholders instead of club-specific domains
- Theme now installable by any sports club without code changes

### Removed
- Legacy awc→club migration code (users migrated in v16.0)
- Hardcoded FreeScout URL in PersonDetail component

## [16.0.0] - 2026-02-05

### Added
- Infix (tussenvoegsel) field for person records — supports Dutch naming convention (e.g., "Jan van de Berg")
- ACF `infix` text field between first_name and last_name (read-only, synced from Sportlink)
- Auto-title generation includes infix: "First Infix Last" with no double spaces when empty
- Infix included in REST API filtered endpoint response and JOIN query
- Global search matches infix field (score: 50)
- vCard export/import: infix maps to N field position 3 (Additional Names)
- Google Contacts export: infix maps to middleName
- Google Contacts API import: reads middleName as infix at all 3 import locations
- Google CSV export: populates "Additional Name" column
- Google CSV import: reads "Additional Name" / "Middle Name" column
- CardDAV: infix in create/update flows
- Frontend PersonEditModal: read-only infix field with "Komt van Sportlink" tooltip
- Frontend PeopleList, VOGList: display infix in name column
- `formatPersonName()` utility for consistent name formatting across frontend

## [14.0.0] - 2026-02-04

### Changed
- Optimized QueryClient defaults to prevent duplicate API calls on page load
- Migrated from BrowserRouter to createBrowserRouter for better route handling
- Modal people selectors now load data only when modal opens (lazy loading)
- Created centralized useCurrentUser hook for query deduplication
- VOG count now cached with 5-minute staleTime
- Backend todo count queries now use SQL COUNT instead of fetching all records

## [8.4.0] - 2026-02-01

### Added
- Google Sheets export for Contributie page (export fee list with all 10 columns, Euro formatting, and totals row)

## [8.3.4] - 2026-01-31

### Added
- Justis status filter on VOG page (filter by whether VOG is submitted to Justis)

## [8.3.3] - 2026-01-31

### Added
- Leeftijdsgroep filter dropdown on /people page
- Custom sorting for leeftijdsgroep (Onder 6 < Onder 10 < Senioren)

## [8.3.2] - 2026-01-30

### Added
- VOG exempt commissies setting - exclude commissies without child contact from VOG requirements

## [8.3.1] - 2026-01-29

### Added
- Visual indicators (small vertical lines) on column resize handles for better discoverability

### Changed
- Removed redundant sort dropdown from People list header (table headers now handle sorting)

### Fixed
- Column resize no longer crashes with "Maximum update depth exceeded" error
- Column resize handles pointer capture release errors gracefully

## [8.3.0] - 2026-01-28

### Added
- Smart Android install prompt after user engagement (2 page views or 1 note)
- iOS install instructions modal with visual Add to Home Screen guide
- Periodic service worker update checking (hourly)
- Engagement tracking for install prompt timing

### Changed
- ReloadPrompt text localized to Dutch
- Install prompts respect dismissal preferences with 7-day cooldown

## [8.2.0] - 2026-01-28

### Added
- Pull-to-refresh gesture on all list views (People, Teams, Commissies, Dates, Todos, Feedback)
- Pull-to-refresh on detail views (Person, Team, Commissie) and Dashboard
- Native-like refresh indicator matching Stadion's accent color

### Fixed
- iOS standalone mode no longer triggers page reload from overscroll bounce

## [7.1.0] - 2026-01-26

### Removed
- **Favorites feature:** Removed the ability to mark people as favorites
  - Removed is_favorite ACF field from person records
  - Removed favorites filter from People list
  - Removed favorites dashboard widget
  - Removed favorites star indicator from person cards and detail views
  - Removed favorites from dashboard customization options
  - Removed is_starred import from Monica importer
- **Workspaces feature:** Completely removed multi-user collaboration functionality
  - Removed workspace CPT and workspace_invite CPT
  - Removed workspace_access taxonomy
  - Removed visibility settings (private/workspace/shared) from all entities
  - Removed workspace navigation, routes, pages, and components
  - Removed workspace member management and invites
  - Removed VisibilitySelector from edit modals
  - Removed workspace-related ACF field groups
  - Simplified access control to author-only model
  - Removed bulk visibility/workspace updates from REST APIs

## [7.0.0] - 2026-01-25

### Changed
- **BREAKING:** Forked from Caelis, renamed project to Stadion
- **BREAKING:** Renamed all `PRM_` prefixes to `STADION_`
- **BREAKING:** Renamed REST API namespace from `prm/v1` to `stadion/v1`
- **BREAKING:** Renamed Organizations to Teams (post type `company` → `team`)
- **BREAKING:** Renamed taxonomy from `company_label` to `team_label`
- **BREAKING:** REST endpoints changed from `/companies` to `/teams`
- Renamed frontend config from `prmConfig` to `stadionConfig`
- Updated user role from `caelis_user` to `stadion_user`
- Teams are now designed to be synced from Sportlink (read-only)
- Work History renamed to Team History in person profiles

### Removed
- Investors field from teams (not needed for sports teams)

## [6.0.0] - 2026-01-20

### Added
- Custom Fields system for admin-defined fields on People and Teams
- Settings UI for creating, editing, and deleting custom fields
- Support for 14 field types: Text, Textarea, Number, Email, URL, Date, Select, Checkbox, True/False, Image, File, Link, Color, Relationship
- Drag-and-drop field reordering with optimistic updates
- Required and unique field validation options
- Custom field display in detail views with type-appropriate rendering
- Custom field columns in list views (configurable per field)
- Custom field search integration in People and Teams
- ACF-native storage using field groups and subfield patterns

## [5.0.0] - 2026-01-18

### Added
- Google Contacts bidirectional sync with Stadion as source of truth
- OAuth connection for Google Contacts in Settings > Connections
- Automatic import from Google Contacts with duplicate detection
- Export Stadion contacts to Google Contacts
- Delta sync using Google syncToken for efficient updates
- Configurable sync frequency (15min, hourly, 6hr, daily)
- Conflict detection with Stadion-wins resolution strategy
- Sync history log in Settings showing recent sync operations
- "View in Google Contacts" link on synced person profiles
- WP-CLI commands for Google Contacts management:
  - `wp stadion google-contacts sync --user-id=ID` - trigger sync
  - `wp stadion google-contacts sync --user-id=ID --full` - full resync
  - `wp stadion google-contacts status --user-id=ID` - check status
  - `wp stadion google-contacts conflicts --user-id=ID` - list conflicts
  - `wp stadion google-contacts unlink-all --user-id=ID` - reset sync

## [4.10.0] - 2026-01-17

### Added
- Manual sync trigger button in Google Contacts settings
- Sync frequency dropdown (15min, hourly, 6 hours, daily)
- Background sync status display showing last sync time
- REST endpoints for sync trigger (/google-contacts/sync) and frequency update (/google-contacts/sync-frequency)
- sync_frequency field in GoogleContactsConnection class
- sync_user_manual() method in GoogleContactsSync for on-demand sync

## [4.9.0] - 2026-01-17

### Added
- Fixed height dashboard widgets (280px) with internal scrolling
- 6 skeleton widgets shown during dashboard loading for layout stability
- Multi-calendar selection for Google Calendar connections via checkbox UI
- Sync events from multiple selected calendars in a single connection
- Connection card shows "N calendars selected" for multi-calendar connections
- Backend `get_calendar_ids()` helper normalizes old single-calendar to new array format
- Two-column EditConnectionModal layout (calendar list left, sync settings right)

### Changed
- Calendar selector changed from dropdown to checkbox list
- Save button disabled when no calendars selected for Google connections
- EditConnectionModal width increased (max-w-2xl) to accommodate two columns

### Fixed
- Events widget no longer jumps during date navigation (placeholderData pattern)

## [4.8.0] - 2026-01-17

### Added
- Meeting detail modal with full meeting information (title, time, location, description)
- Meeting attendee list with avatars showing known vs unknown attendees
- Meeting notes section with auto-save for meeting prep
- Add person from meeting attendee with name extraction from email
- Date navigation on meetings widget with prev/next/today buttons
- Add email to existing person flow with choice popup to avoid duplicates

### Fixed
- HTML entity encoding (&amp;) in calendar event titles

## [4.7.0] - 2026-01-17

### Added
- Dinner activity type for tracking dinner meetings
- Zoom activity type for tracking video calls

### Changed
- Phone call activity type renamed to Phone for brevity

### Fixed
- Topbar z-index now stays above selection toolbar on People screen
- Person header spacing between "at" and team name

## [4.5.0] - 2026-01-16

### Added
- Per-connection sync_to_days setting (1 week to 90 days forward)
- Per-connection sync_frequency setting (15 min, 30 min, hourly, 4 hours, daily)
- Background sync respects per-connection frequency settings with `is_sync_due()` check
- Calendar list API endpoint `GET /stadion/v1/calendar/connections/{id}/calendars`
- Calendar selector dropdown in EditConnectionModal
- Connection card displays selected calendar name as subtitle
- `list_calendars()` method in GoogleProvider for fetching available calendars
- `next_sync` timestamp in sync status endpoint

### Fixed
- Duplicate calendar events from race conditions via transient-based sync lock
- Contact matching in CLI/cron contexts by setting user context before queries
- Google vendor class namespace resolution (added absolute namespace prefixes)

## [4.4.0] - 2026-01-16

### Added
- Comprehensive codebase audit (AUDIT.md) with namespace hierarchy design
- PSR-4 namespaces for 38 PHP classes across 9 namespace groups
- Composer autoloading with classmap for includes/ directory
- 38 backward-compatible class aliases (STADION_* → Stadion\*)
- PHPCS Generic.Files.OneClassPerFile rule enabled
- Daily debug.log rotation via WP-Cron with 7-day retention

### Changed
- Split notification channel classes into separate files (one-class-per-file compliance)
- Removed manual stadion_autoloader() function (52 lines)
- All classes now use proper PHP namespaces (Stadion\Core, Stadion\REST, etc.)

## [4.3.0] - 2026-01-16

### Added
- WordPress Coding Standards (WPCS 3.3) installed via Composer
- PHPCS configuration file (phpcs.xml.dist) with WordPress-Extra standard
- Composer lint scripts (`composer lint`, `composer lint:fix`)
- Complete wp-config.php configuration documentation in README.md

### Changed
- Converted entire codebase to short array syntax ([] instead of array())
- Disabled Yoda conditions for improved code readability
- PHPCS violations reduced from 49,450 to 46 (99.9% reduction)
- Text domain standardized to 'stadion' throughout codebase
- All date() calls converted to gmdate() for timezone safety

## [4.2.0] - 2026-01-15

### Added
- DomErrorBoundary component for graceful recovery from browser extension DOM conflicts
- DOM modification prevention via `translate="no"` attribute and Google notranslate meta tag
- Connections tab in Settings with subtabs for Calendars, CardDAV, and Slack
- Automatic calendar event re-matching when person email addresses change
- WP-CLI command `wp prm calendar rematch --user-id=ID` for manual re-matching

### Changed
- Settings page reorganized: external service connections consolidated under Connections tab
- Notifications tab simplified to show only channel toggles and preferences
- OAuth redirect URLs updated to use new Connections tab structure

## [4.1.0] - 2026-01-15

### Added
- Dynamic favicon that updates when accent color changes
- Dashboard restructured to 3-row layout (Stats | Activity | Favorites)
- Timezone-aware meeting times using ISO 8601 format

### Fixed
- Dark mode contrast for CardDAV connection details and search modal
- Deploy procedure now uses two-step rsync to prevent MIME type errors from stale artifacts

## [4.0.0] - 2026-01-15

### Added
- Google Calendar OAuth2 integration with automatic token refresh
- CalDAV provider supporting iCloud, Fastmail, Nextcloud, and generic servers
- Calendar event custom post type for caching synced events
- Email-first contact matching algorithm with confidence scores
- Calendar settings UI with connection management
- Person profile Meetings tab with upcoming/past meetings
- Log as Activity functionality for past meetings
- Background sync via WP-Cron every 15 minutes
- Today's Meetings dashboard widget
- WP-CLI commands: `wp prm calendar sync/status/auto-log`

## [3.8.0] - 2026-01-15

### Added
- Color scheme toggle (Light/Dark/System) in Settings Appearance
- Accent color picker with 4 color options (blue, violet, rose, amber)
- useTheme hook with localStorage caching
- Dark mode support across all components

### Fixed
- Dark mode contrast issues in menus, icons, and overdue items

## [3.7.0] - 2026-01-15

### Added
- TodoModal opens from Dashboard when clicking todo cards (no navigation away)
- View-first mode for existing todos showing formatted date, rendered notes, and person chips
- Edit button in view mode to switch to edit mode

### Changed
- Default due date for new todos changed from today to tomorrow
- Cancel button in edit mode returns to view mode (for existing todos) instead of closing modal

## [3.6.0] - 2026-01-14

### Changed
- Reduced initial bundle from 460 KB to 50 KB via modal lazy loading
- GlobalTodoModal, PersonEditModal, TeamEditModal, and ImportantDateModal now load on demand
- TipTap editor (~370 KB) only loads when opening modals that need it
- Initial load improved from ~767 KB to ~400 KB total

## [3.4.0] - 2026-01-14

### Added
- Labels CRUD interface at `/settings/labels` with tabbed UI for person and team labels
- 8 new API methods for label management (getPersonLabels, createPersonLabel, etc.)
- Awaiting todos count in dashboard stats (5-column grid layout)
- Build-time based refresh detection using manifest.json mtime

### Changed
- Teams list website URLs are now clickable blue links opening in new tab
- Slack contact details simplified to show only label as clickable link
- Timeline panel on person profile now uses full 2-column width on desktop
- Dashboard stats grid expanded from 4 to 5 columns on desktop

### Removed
- Labels column from Teams list (column, sorting, bulk action removed)

## [3.3.0] - 2026-01-14

### Added
- WYSIWYG notes field for todo descriptions (ACF field)
- Multi-person todo linking with related_persons multi-value field
- TodoModal collapsible notes editor with RichTextEditor
- Multi-person chip selector in TodoModal (edit mode only)
- GlobalTodoModal multi-person selection and notes support
- Stacked avatar display in TodosList (max 3 + overflow)
- Stacked avatar display in PersonDetail todos sidebar (max 2 + overflow)
- Cross-person todo visibility with "Also:" indicator
- `wp prm todos migrate-persons` WP-CLI command for data migration
- Thumbnail field in timeline API persons array for avatar display

### Changed
- TodoModal now shows multi-person selector when editing (new todos context-bound to person page)
- PersonDetail sidebar uses smaller avatars (w-5 h-5) for compact display
- Current person filtered from "Also:" display (only shows OTHER linked people)

## [3.2.0] - 2026-01-14

See previous changelog entry for v1.79.0 (Person Profile Polish milestone).

## [1.79.0] - 2026-01-14

### Added
- Current position (job title + team) display in PersonDetail header
- Persistent todos sidebar visible across all PersonDetail tabs
- Mobile todos floating action button (FAB) for screens below lg breakpoint
- Mobile todos slide-up panel with full CRUD functionality
- CSS keyframe animation for mobile panel slide-up effect

### Changed
- PersonDetail layout changed from flex to 3-column CSS grid for equal-width columns
- Timeline endpoint now queries all todo post statuses (stadion_open, stadion_awaiting, stadion_completed)
- Timeline endpoint returns proper `status` field instead of deprecated `is_completed`

### Fixed
- Timeline endpoint wasn't returning todos (was querying 'publish' status instead of custom statuses)

## [1.78.0] - 2026-01-14

### Added
- `TodoCptTest.php` PHPUnit test class with 16 tests covering:
  - CPT registration (post type exists, REST support)
  - Access control (user isolation, admin visibility)
  - REST API CRUD operations (GET, POST, PUT, DELETE)
  - Dashboard integration (open_todos_count)
  - Completion filter functionality

### Changed
- `SearchDashboardTest.php` updated to use CPT-based todos instead of comment-based
  - `createTodo()` helper now creates `stadion_todo` posts
  - Added `STADION_REST_Todos` route registration

## [1.77.0] - 2026-01-14

### Added
- WP-CLI command `wp prm todos migrate` to migrate comment-based todos to CPT
  - Supports `--dry-run` flag to preview changes without modifying data
  - Preserves all metadata: related_person, is_completed, due_date
  - Sets visibility to private (default for migrated todos)
  - Deletes original comments after successful migration

### Changed
- Dashboard `count_open_todos()` now queries `stadion_todo` CPT instead of comments
  - Uses `WP_Query` with access control filtering via `STADION_Access_Control` hooks

### Removed
- Legacy comment-based todo code from `STADION_Comment_Types`:
  - `TYPE_TODO` constant
  - Todo REST routes (`/people/{id}/todos`, `/todos/{id}`)
  - Todo methods: `get_todos()`, `create_todo()`, `update_todo()`, `delete_todo()`
  - Todo meta registration (`is_completed`, `due_date`)
- Legacy `get_all_todos()` method from `STADION_REST_API` (now handled by `STADION_REST_Todos`)
- Legacy `/stadion/v1/todos` route from `STADION_REST_API` (now handled by `STADION_REST_Todos`)

## [1.76.0] - 2026-01-14

### Added
- `STADION_REST_Todos` class for full CRUD operations on the todo CPT via REST API
  - Person-scoped endpoints: GET/POST `/stadion/v1/people/{person_id}/todos`
  - Global endpoints: GET `/stadion/v1/todos` with optional `completed` filter parameter
  - Single todo endpoints: GET/PUT/DELETE `/stadion/v1/todos/{id}`
  - Response format matches existing comment-based todo system for seamless frontend migration
  - Proper permission callbacks using `check_person_access()` and `check_user_approved()`
  - Access control integration via existing `STADION_Access_Control` filters

## [1.75.0] - 2026-01-14

### Added
- `stadion_todo` custom post type for tracking todos/tasks related to people
  - Post type slug: `stadion_todo`, REST base: `todos`
  - Internal only (public: false) but visible in admin and REST API
  - Supports title (todo text), editor (optional notes), and author
  - Menu position after Important Dates with dashicons-yes-alt icon
- ACF field group for todo metadata (`group_todo_fields.json`)
  - `related_person`: post_object field linking to a person (required)
  - `is_completed`: true_false toggle for completion status
  - `due_date`: date_picker for optional due date (Y-m-d format)
  - `_visibility`: select field for private/workspace visibility
  - `_assigned_workspaces`: taxonomy field for workspace assignment (conditional on visibility)
- Access control integration for `stadion_todo` post type
  - Added to `$controlled_post_types` array for automatic query filtering
  - REST API query filtering via `rest_stadion_todo_query` hook
  - REST API single item access via `rest_prepare_stadion_todo` hook
  - Workspace ID conversion via `rest_after_insert_stadion_todo` hook

## [1.74.0] - 2026-01-13

### Changed
- Lazy load heavy third-party libraries for further bundle optimization
  - vis-network (~526 KB) loads only when viewing family tree pages
  - TipTap editor (~371 KB) loads only when opening note/activity modals
  - FamilyTree page chunk reduced from 534 KB to 9 KB
  - QuickActivityModal chunk reduced from 383 KB to 13 KB
  - Initial load now 435 KB raw (no change in core bundles)
  - Extracted richTextUtils.js for proper code splitting

## [1.73.0] - 2026-01-13

### Changed
- Implemented route-based lazy loading with React.lazy and Suspense
  - All 16 page components now load on demand when routes are visited
  - Initial bundle reduced from 1,336 KB to 87 KB (main chunk only)
  - Total initial load (vendor + utils + main + CSS): ~435 KB raw / ~128 KB gzipped
  - Heavy libraries (vis-network, TipTap editor) only load when needed
  - Added PageLoader spinner for smooth loading states

## [1.72.0] - 2026-01-13

### Changed
- Implemented vendor chunking for improved bundle caching
  - Split vendor chunk (React, React DOM, React Router, TanStack Query): 210 KB
  - Split utils chunk (date-fns, clsx, zustand, axios, react-hook-form): 96 KB
  - Main application chunk remains at 1,336 KB (pending lazy loading in future plans)
  - Stable dependencies now cached separately, reducing cache invalidation on app updates

## [1.71.1] - 2026-01-13

### Fixed
- Team edit now properly saves visibility and workspace changes
  - Form was passing visibility values but handleSaveTeam was ignoring them
  - Now uses form values instead of just preserving existing values

## [1.71.0] - 2026-01-13

### Added
- WP-CLI command `wp prm dates regenerate-titles` to update existing Important Date titles
  - Supports `--dry-run` flag to preview changes
  - Skips dates with custom labels
  - Regenerates titles using full names for consistency

### Changed
- Important Date titles now use full names instead of first names only
  - Frontend auto-generation updated to use "Jan Ippen's Birthday" format
  - Backend already used full names (no changes needed)
  - Improves clarity when multiple people share the same first name

## [1.70.1] - 2026-01-13

### Fixed
- Important Date modal now defaults to today's date when creating new dates
- Editing important dates no longer fails with 400 error about required _visibility field
- Cache invalidation after editing important dates now uses correct query key

## [1.70.0] - 2026-01-13

### Added
- Bulk actions for Teams list view
  - Bulk visibility change (private/workspace)
  - Bulk workspace assignment
  - Bulk label management (add/remove mode toggle)
- Actions dropdown in Teams selection toolbar
- Bulk update REST endpoint for teams (`POST /stadion/v1/teams/bulk-update`)
- `useBulkUpdateTeams` hook for React components

### Changed
- Teams now have full bulk action parity with People list view

## [1.69.0] - 2026-01-13

### Added
- Teams list view with tabular layout (replacing card grid)
  - Columns: checkbox, logo, name, industry, website, workspace, labels
  - SortableHeader component for clickable column sorting
  - Selection checkboxes with select all/none functionality
  - Sticky table header and selection toolbar
  - Alternating row colors for better readability
- Header sort controls for Teams
  - Sort field dropdown (Name, Industry, Website, Workspace, Labels)
  - Sort direction toggle button

### Changed
- Teams page now uses list view instead of card grid
- Teams data includes team labels for display

### Removed
- TeamCard component (replaced by TeamListRow)
- Grid-based card layout for teams

## [1.68.0] - 2026-01-13

### Changed
- People list view now has dedicated image column (before First Name)
  - Images/initials shown in narrow column without header label
  - First Name header now aligns directly with first name values

### Removed
- Card view toggle from People list (list view is now the only view)
- PersonCard component (no longer needed)
- View mode localStorage persistence (no longer needed)

## [1.67.0] - 2026-01-13

### Added
- Bulk team assignment modal in list view Actions dropdown
  - Search/filter teams by name
  - Select team to assign to all selected people
  - "Clear team" option to remove current team
- Bulk labels management modal in list view Actions dropdown
  - Add/Remove mode toggle for label operations
  - Multi-select labels for batch operations
  - Add labels appends without replacing existing labels
  - Remove labels removes selected labels from people

## [1.66.1] - 2026-01-13

### Added
- Extended bulk-update endpoint with team and label support
  - `team_id`: Set current team for selected people (or clear with null)
  - `labels_add`: Add person labels to selected people in bulk
  - `labels_remove`: Remove person labels from selected people in bulk

## [1.66.0] - 2026-01-13

### Added
- Clickable column headers in list view with sort direction indicators
  - Click any column header to sort by that field
  - Shows arrow indicator (up/down) for active sort column
  - Click same header again to toggle sort direction
- Sticky table header that remains visible when scrolling
- Sticky selection toolbar when contacts are selected
  - Selection toolbar stays above the table header for easy access

## [1.65.0] - 2026-01-13

### Added
- Split Name column into separate First Name and Last Name columns in list view
- Labels column in list view displaying person labels as styled pills
- Zebra striping on list view rows for improved readability
- Extended sorting options: Team, Workspace, and Labels
  - Team sorting uses team name (empty sorts last)
  - Workspace sorting uses workspace names (empty sorts last)
  - Labels sorting uses first label name (empty sorts last)

## [1.64.1] - 2026-01-13

### Fixed
- Workspace column in list view now updates immediately after bulk workspace assignment (no refresh required)

## [1.64.0] - 2026-01-13

### Added
- Bulk actions UI for managing multiple contacts at once
  - Actions dropdown in selection toolbar with "Change visibility" and "Assign to workspace" options
  - Bulk visibility modal to change privacy settings for selected contacts
  - Bulk workspace modal to assign selected contacts to workspaces
  - Loading states and success/error handling for bulk operations

## [1.63.0] - 2026-01-13

### Added
- Bulk update REST endpoint `/stadion/v1/people/bulk-update` for batch operations
  - Accepts array of person IDs and updates object with visibility and/or workspace assignments
  - Validates ownership of each post before updating
  - Returns success/failure details for each person
- `useBulkUpdatePeople` React hook for bulk operations from the frontend
- `bulkUpdatePeople` API client method for REST endpoint access

## [1.62.4] - 2026-01-13

### Fixed
- Workspace assignments now correctly save when editing a person (using REST API action instead of ACF filter)

## [1.62.3] - 2026-01-13

### Fixed
- Workspace assignments now correctly save when editing a person (added ACF filter to convert workspace post IDs to taxonomy term IDs)

## [1.62.2] - 2026-01-13

### Fixed
- Person edit modal now correctly saves visibility and workspace assignments

## [1.62.1] - 2026-01-13

### Fixed
- List view workspace column now correctly displays workspace names (fixed type coercion for workspace IDs)
- View mode preference (card/list) now persists across page reloads via localStorage

## [1.62.0] - 2026-01-13

### Added
- List view for People screen with tabular layout
  - Toggle between card view and list view using LayoutGrid/List icons
  - Table displays Name (with avatar, deceased marker, favorite star), Team, and Workspace columns
  - Rows link to person detail page
- Multi-select infrastructure for bulk operations
  - Checkbox selection for individual rows and select all/none
  - Header checkbox shows checked/partial/unchecked state based on selection
  - Selection toolbar shows count and clear button
  - Selection automatically clears when filters change

## [1.61.1] - 2026-01-13

### Added
- Multi-user system documentation (`docs/multi-user.md`)
  - Comprehensive guide covering workspaces, visibility, sharing, and collaborative features
  - Migration instructions for upgrading single-user installations
  - REST API endpoint reference for workspace operations

### Changed
- Updated access control documentation (`docs/access-control.md`)
  - Documented permission resolution chain (author > private > workspace > shared > deny)
  - Added permission levels section explaining return values from `get_user_permission()`
  - Documented visibility settings and workspace access checking
  - Added direct sharing documentation with `_shared_with` meta structure

## [1.61.0] - 2026-01-13

### Added
- Multi-user migration WP-CLI command for upgrading existing installations
  - `wp prm multiuser migrate` sets visibility to "private" on all existing contacts, teams, and important dates
  - `wp prm multiuser migrate --dry-run` previews changes without making them
  - `wp prm multiuser validate` checks migration status and reports any posts missing visibility
  - User-friendly output with progress, summary, and next steps guidance

## [1.60.0] - 2026-01-13

### Added
- Workspace activity digest integration for collaborative awareness
  - Daily digest now includes @mention notifications queued via digest preference
  - Workspace activity section shows shared notes from other workspace members (last 24 hours)
  - Email subject line updates to indicate when team activity is included
  - Mentions shown with blue accent styling, workspace activity with green accent styling
- Slack digest support for collaborative content
  - Mentions and workspace activity sections added to Slack notification blocks
  - Consistent formatting with email digest presentation

### Changed
- STADION_Reminders now gathers mentions and workspace activity before sending digests
- Empty digests are now skipped when user has no dates, todos, mentions, or workspace activity

## [1.59.1] - 2026-01-13

### Fixed
- Team editing now includes visibility fields in REST API payload to satisfy ACF required field validation

## [1.59.0] - 2026-01-13

### Added
- @mention notification system for collaborative features
  - STADION_Mention_Notifications class handles notification delivery when users are mentioned
  - Immediate email notifications or queue for daily digest based on user preference
  - User preference stored in `stadion_mention_notifications` user meta (digest/immediate/never)
  - Self-mentions are automatically ignored (no notification sent)
- MentionInput integration in NoteModal for workspace contacts
  - NoteModal now uses MentionInput component for contacts shared with workspaces
  - Regular RichTextEditor used for private contacts (backward compatible)
  - Workspace IDs passed through to enable member autocomplete
- Mention notification preference in Settings
  - New "Mention notifications" dropdown in Notifications tab
  - Three options: Include in daily digest (default), Send immediately, Don't notify me
  - REST API endpoint `/stadion/v1/user/mention-notifications` for preference management

## [1.58.0] - 2026-01-13

### Added
- Note visibility controls for collaborative note-taking
  - Notes can be marked as private (only author sees) or shared (visible to all who can see the contact)
  - Default visibility is private, preserving single-user experience
  - `_note_visibility` comment meta field stores visibility setting
  - API endpoints support visibility parameter for create/update operations
  - Timeline and notes endpoints filter based on visibility
  - NoteModal includes visibility toggle when contact is shared with workspace or other users
  - TimelineView displays Lock/Globe icons indicating note visibility
  - Shared notes have subtle blue left border for visual distinction
- New @mentions infrastructure for collaborative notes
  - MentionInput React component using react-mentions library
  - Workspace member search API endpoint (`/stadion/v1/workspaces/members/search`)
  - STADION_Mentions PHP class for parsing, storing, and rendering @mentions
  - Mentioned user IDs stored in comment meta `_mentioned_users`
  - Action hook `stadion_user_mentioned` fires when users are mentioned
- Workspace iCal calendar feed support
  - New `/workspace/{id}/calendar/{token}.ics` endpoint for workspace calendars
  - Includes important dates for all contacts shared with the workspace
  - Token-based authentication validates user membership
  - Calendar Subscription UI in WorkspaceDetail page with copy button
  - iCal URL API endpoint now returns raw token for constructing workspace URLs

## [1.57.2] - 2026-01-13

### Added
- New `wp prm carddav reset_sync` CLI command to force full CardDAV resync

### Fixed
- CardDAV sync token key consistency (int vs string) causing sync to fail

## [1.57.1] - 2026-01-13

### Fixed
- CardDAV sync now tracks changes made via web UI (previously only tracked CardDAV-originated changes)
- New contacts created in Stadion web interface now properly sync to CardDAV clients (iPhone, etc.)

## [1.57.0] - 2026-01-13

### Changed
- Refactored team creation into shared `useCreateTeam` hook in `useTeams.js` (DRY principle)
- Updated TeamsList.jsx and Layout.jsx to use shared hook

### Fixed
- Add missing `_visibility` and `_assigned_workspaces` fields to team quick-add in Layout.jsx

## [1.56.0] - 2026-01-13

### Changed
- Refactored date creation into shared `useCreateDate` hook in `useDates.js` (DRY principle)
- Updated DatesList.jsx and Layout.jsx to use shared hook

## [1.55.1] - 2026-01-13

### Fixed
- Add missing `_visibility` field to all date creation payloads (DatesList, PersonDetail, Layout quick-add, birthday creation)

## [1.55.0] - 2026-01-13

### Added
- New WP-CLI command `wp prm visibility set_defaults` to set default visibility for existing posts

### Changed
- Refactored `useCreatePerson` hook to contain full person creation logic (payload building, Gravatar sideload, birthday creation)
- Updated PeopleList.jsx and Layout.jsx to use shared `useCreatePerson` hook (DRY principle)

## [1.54.1] - 2026-01-13

### Fixed
- Add missing `_visibility` and `_assigned_workspaces` fields to quick-add person mutation in Layout.jsx

## [1.54.0] - 2026-01-13

### Added
- `WorkspaceSettings` page for workspace owners to edit name/description and delete workspace
- `WorkspaceInviteAccept` page for users to accept workspace invitations via email links
- Routes for `/workspaces/:id/settings` and `/workspace-invite/:token`
- Delete confirmation requires typing workspace name to prevent accidental deletion

## [1.53.0] - 2026-01-13

### Added
- ShareModal component for sharing contacts/teams with specific users
- `useSharing.js` hook with `useShares`, `useAddShare`, `useRemoveShare`, `useUserSearch` hooks
- Share REST endpoints for People (`/stadion/v1/people/{id}/shares`)
- Share REST endpoints for Teams (`/stadion/v1/teams/{id}/shares`)
- User search endpoint (`/stadion/v1/users/search`) for finding users to share with
- Share button in PersonDetail page header
- Share button in TeamDetail page header

## [1.52.0] - 2026-01-13

### Added
- `WorkspacesList` page for viewing and managing workspaces
- `WorkspaceDetail` page with member list and role management
- `WorkspaceCreateModal` for creating new workspaces
- `WorkspaceInviteModal` for sending workspace invitations
- Role badges (Owner, Admin, Member, Viewer) with appropriate styling
- Workspace navigation item in sidebar
- Routes for /workspaces and /workspaces/:id

## [1.51.0] - 2026-01-13

### Added
- `VisibilitySelector` component for setting visibility (private/workspace) on contacts and teams
- Visibility controls integrated into PersonEditModal (add and edit modes)
- Visibility controls integrated into TeamEditModal (add and edit modes)
- Visibility and workspace assignment fields included in person/team create payloads

## [1.50.0] - 2026-01-13

### Added
- Ownership filter (All/My Contacts/Shared with Me) to People list
- Ownership filter (All/My Teams/Shared with Me) to Teams list
- Workspace filter dropdown to People and Teams lists
- Filter chips for active ownership and workspace filters
- "No results match your filters" empty state with clear filters button

## [1.49.0] - 2026-01-13

### Added
- TanStack Query hooks for workspace operations (`useWorkspaces.js`)
- Workspace API methods in `client.js` (workspaces, members, invites)
- Sharing API methods in `client.js` (shares, user search)
- `useWorkspaces`, `useWorkspace`, `useCreateWorkspace`, `useUpdateWorkspace`, `useDeleteWorkspace` hooks
- `useAddWorkspaceMember`, `useRemoveWorkspaceMember`, `useUpdateWorkspaceMember` hooks
- `useWorkspaceInvites`, `useCreateWorkspaceInvite`, `useRevokeWorkspaceInvite` hooks
- `useValidateInvite`, `useAcceptInvite` hooks for public invite flow

## [1.48.0] - 2026-01-13

### Added
- Workspace invitation REST API endpoints in `STADION_REST_Workspaces`
- POST /stadion/v1/workspaces/{id}/invites - Create and send invitation email
- GET /stadion/v1/workspaces/{id}/invites - List pending invites
- DELETE /stadion/v1/workspaces/{id}/invites/{invite_id} - Revoke pending invite
- GET /stadion/v1/invites/{token} - Validate invite (public, no auth required)
- POST /stadion/v1/invites/{token}/accept - Accept invite and join workspace
- HTML email template for invitation notifications

## [1.47.0] - 2026-01-13

### Added
- `workspace_invite` Custom Post Type for tracking workspace invitations
- ACF field group for invite metadata (email, role, token, status, expiry)
- Invites appear in admin under Workspaces menu for easy management

## [1.46.0] - 2026-01-13

### Added
- Workspace term sync functionality in `STADION_Taxonomies`
- Auto-creates `workspace-{ID}` terms when workspaces are published
- Updates term names when workspace titles change
- Removes terms when workspaces are permanently deleted
- ACF field for assigning contacts to workspaces (shown when visibility = workspace)
- New `STADION_REST_Workspaces` class for workspace REST API endpoints
- GET/POST /stadion/v1/workspaces for listing and creating workspaces
- GET/PUT/DELETE /stadion/v1/workspaces/{id} for workspace details and management
- POST /stadion/v1/workspaces/{id}/members for adding members
- DELETE/PUT /stadion/v1/workspaces/{id}/members/{user_id} for removing and updating members
- Permission callbacks for workspace access, admin, and owner checks

## [1.45.0] - 2026-01-13

### Added
- Extended access control for visibility, workspace membership, and direct shares
- `get_accessible_post_ids()` now includes workspace-visible and shared posts
- New `get_user_permission()` method returns permission level (owner, admin, member, viewer, edit, view)
- Full permission resolution chain: author → private → workspace → shares → deny

### Changed
- `user_can_access_post()` now checks full visibility/workspace/share chain

## [1.44.0] - 2026-01-13

### Added
- Workspace membership management system via `STADION_Workspace_Members` class
- User meta storage for workspace memberships with roles (admin, member, viewer)
- Methods for adding, removing, and updating user workspace memberships
- Query helpers to get user workspaces, workspace members, and role checks
- Automatic workspace owner membership: workspace creators are auto-added as admin members
- Protection against removing workspace owner from membership
- ACF visibility field group for Person, Team, and Important Date post types
- Visibility options: private (default), workspace, and shared with specific users
- `STADION_Visibility` helper class for managing visibility and sharing
- Share management methods: add_share, remove_share, get_shares, user_has_share
- `_shared_with` post meta for storing direct user shares with permissions

## [1.43.1] - 2026-01-13

### Added
- workspace_access taxonomy for assigning contacts to workspaces
- REST API endpoint `/wp/v2/workspace_access` for workspace access terms

## [1.43.0] - 2026-01-13

### Added
- Workspace Custom Post Type for multi-user collaboration support
- REST API endpoint `/wp/v2/workspaces` for workspace management

## [1.42.7] - 2026-01-13

### Changed
- Removed all console.error() calls from React components for cleaner production code
- Error handling now shows user-friendly alerts instead of logging to console

## [1.42.6] - 2026-01-13

### Fixed
- Fixed double-encoding of HTML entities in REST API responses (& showing as &amp;)
- sanitize_text() now correctly decodes entities for JSON output (React handles XSS escaping)

## [1.42.5] - 2026-01-13

### Changed
- Added XSS sanitization to REST API responses using WordPress native functions
- Added sanitize_text(), sanitize_rich_content(), sanitize_url() helpers to STADION_REST_Base
- All user-supplied content in API responses now properly escaped (defense-in-depth)

## [1.42.4] - 2026-01-13

### Changed
- Webhook URL validation now restricts to hooks.slack.com domain only (prevents SSRF attacks)
- Added domain validation in both validate_callback and update_slack_webhook method

## [1.42.3] - 2026-01-13

### Changed
- Slack bot tokens are now encrypted using sodium_crypto_secretbox instead of base64 encoding
- Added encrypt_token/decrypt_token helper methods to STADION_REST_Slack class
- Legacy base64-encoded tokens are automatically migrated on first read
- Graceful fallback to base64 if STADION_ENCRYPTION_KEY constant is not defined

## [1.42.2] - 2026-01-09

### Fixed
- ImportantDateModal now correctly displays selected people when editing a date
- ImportantDateModal now handles date_type names from API (converts to IDs for form)
- ImportantDateModal no longer sends `[null]` for date_type when saving

## [1.42.1] - 2026-01-09

### Fixed
- STADION_THEME_VERSION now reads from style.css instead of being hardcoded to 1.0.0

## [1.42.0] - 2026-01-09

### Added
- Version check system for PWA/mobile app cache invalidation
- New `/stadion/v1/version` REST API endpoint returns current theme version
- `useVersionCheck` hook monitors for new versions every 5 minutes and on tab visibility change
- Update banner appears at top of screen when a new version is available, with one-click reload

## [1.41.3] - 2026-01-09

### Fixed
- Gender symbol now aligns properly with pronouns and age on mobile

## [1.41.2] - 2026-01-09

### Fixed
- Pronouns are now properly saved when editing or creating a person

## [1.41.1] - 2026-01-09

### Changed
- PersonDetail: Pronouns now displayed between gender symbol and age (e.g., "♂ — they/them — 35 years old")

## [1.41.0] - 2026-01-09

### Added
- Pronouns field added to person records
- vCard export: Pronouns exported as both PRONOUNS (RFC 9554) and X-PRONOUNS (Apple)
- vCard import: Pronouns parsed from PRONOUNS and X-PRONOUNS properties
- CardDAV: Full pronouns sync support
- PersonEditModal: Pronouns field added alongside gender

## [1.40.1] - 2026-01-09

### Added
- vCard import: Base64 encoded photos are now imported and sideloaded as featured images
- CardDAV: Photo sync support for both base64 encoded and URL-based photos

## [1.40.0] - 2026-01-09

### Added
- vCard export: Social links now use X-SOCIALPROFILE for better client compatibility
- vCard export: GENDER field is now exported (M/F/O/N codes)
- vCard export: Slack contacts exported as IMPP;X-SERVICE-TYPE=Slack
- vCard import: X-SOCIALPROFILE parsing for social network profiles
- vCard import: GENDER field parsing and mapping to system gender values
- vCard import: IMPP parsing for Slack contacts

### Changed
- vCard export: LinkedIn, Twitter, Instagram, Facebook use X-SOCIALPROFILE instead of generic URL

## [1.39.6] - 2026-01-09

### Added
- vCard import: NOTE lines are now imported as timeline notes

### Changed
- vCard import: Multiple NOTE entries are now supported (all imported as separate timeline notes)
- vCard import: Phone labels (Home/Work) now preserved during import
- STADION_VCard_Import now uses notes array internally for consistency

## [1.39.5] - 2026-01-09

### Changed
- vCard import: Email with TYPE=HOME/WORK now sets label to "Home"/"Work"
- vCard import: Phone with TYPE=CELL (even with VOICE/pref) now correctly imports as mobile
- vCard import: Phone with TYPE=HOME/WORK now sets label accordingly
- vCard export: Photos now embedded inline as base64 per RFC 2426 instead of URI reference

## [1.39.4] - 2026-01-09

### Added
- WP-CLI command `wp prm vcard get <person_id>` to export vCard for a person
- WP-CLI command `wp prm vcard parse <file>` to preview what a vCard import would contain

## [1.39.3] - 2026-01-09

### Changed
- Timeline Note and Activity buttons now use distinct icons (StickyNote and MessageCircle) visible on mobile

## [1.39.2] - 2026-01-09

### Changed
- Moved "View family tree" button to Relationships card header
- Simplified Add buttons to just "+" icon with tooltip for: Relationships, Important dates, Addresses, Todos, Work history

## [1.39.1] - 2026-01-09

### Changed
- Tab content within PersonDetail now uses masonry layout (CSS columns)
- Responsive: 1 column on mobile, 2 columns on tablet/desktop
- Cards flow vertically first, then into next column for optimal space usage

## [1.39.0] - 2026-01-09

### Changed
- PersonDetail page now uses tab-based interface with three tabs: Profile, Timeline, and Work
- Profile tab contains: Contact information, Addresses, Important dates, How we met, Relationships
- Timeline tab contains: Todos, Timeline (activities/notes)
- Work tab contains: Work history, Investments, Colleagues
- "Events" section renamed to "Important dates"
- Removed three-column grid layout in favor of cleaner tab-based team

## [1.38.1] - 2026-01-09

### Fixed
- Links in activities/notes now visually styled as links (blue, underlined)
- Links in activities/notes now open in new tab with proper security attributes
- List items (ul/ol) in activities/notes now display proper bullets/numbers

## [1.38.0] - 2026-01-09

### Added
- Colleagues card on PersonDetail page - shows current employees from the same team/teams
- Colleagues are only displayed if the person has a current job (no end date)
- Colleagues sorted alphabetically with job title displayed

## [1.37.0] - 2026-01-09

### Changed
- Slack contacts now appear in Contact Details section (with link) instead of social icons
- WhatsApp icon now appears in social icons if a mobile number exists
- Removed WhatsApp button next to mobile numbers (now in social icons row)
- Links in activities and notes are now automatically clickable (using WordPress make_clickable)

## [1.36.0] - 2026-01-09

### Added
- TeamEditModal now supports full editing with all fields (parent team, investors)
- Parent team selector with searchable dropdown in TeamEditModal
- Investors multi-select (people and teams) in TeamEditModal

### Changed
- TeamDetail "Edit" button now opens TeamEditModal instead of navigating to separate page
- Logo upload remains on TeamDetail page (hover over logo to upload)

### Removed
- TeamForm.jsx removed - all team creation/editing now via TeamEditModal
- Routes `/teams/new` and `/teams/:id/edit` removed

## [1.35.0] - 2026-01-09

### Added
- PersonEditModal now supports full editing with all fields (nickname, gender, how we met, favorite)
- vCard import support in PersonEditModal when creating new people (drag & drop or browse)
- Gravatar auto-fetch when email is provided in person creation
- Birthday creation support when creating a new person

### Changed
- PersonDetail "Edit" button now opens PersonEditModal instead of navigating to separate page
- PersonEditModal shows email/phone/birthday fields only when creating (editing contacts separately)

### Removed
- PersonForm.jsx removed - all person creation/editing now via PersonEditModal
- Routes `/people/new` and `/people/:id/edit` removed

## [1.34.0] - 2026-01-09

### Added
- PersonEditModal: Quick add person from header + button, People list, and empty states
- TeamEditModal: Quick add team from header + button, Teams list, and empty states
- All "Add" buttons throughout the app now open modals instead of navigating to separate pages

### Changed
- Header + button now opens modals for Person, Team, Todo, and Date creation
- People list "Add person" button now opens modal
- Teams list "Add team" button now opens modal
- Dates list "Add date" button now opens modal

## [1.33.0] - 2026-01-09

### Added
- ImportantDateModal: Add/edit important dates directly from person detail page via modal
- RelationshipEditModal: Add/edit relationships directly from person detail page via modal
- AddressEditModal: Add/edit addresses directly from person detail page via modal
- WorkHistoryEditModal: Add/edit work history directly from person detail page via modal

### Changed
- All person-related forms now open as modals instead of navigating to separate pages
- Improved UX with "Add one" links when sections are empty

### Removed
- Standalone page routes for dates, relationships, addresses, and work history forms
- Old form components (DateForm, RelationshipForm, AddressForm, WorkHistoryForm)

## [1.32.0] - 2026-01-09

### Added
- Contact Edit Modal: Edit all contact details (email, phone, social links) in a single modal dialog
- Ability to add, edit, and remove multiple contact entries at once

### Changed
- Replaced individual contact detail edit pages with unified modal editor
- "Add contact detail" button changed to "Edit" button on Contact information card

### Removed
- Individual contact detail edit routes (`/people/:id/contact/new`, `/people/:id/contact/:index/edit`)

## [1.31.0] - 2026-01-09

### Added
- Slack links support for contacts - add direct DM links like `https://workspace.slack.com/archives/D08V2DLMQ13`
- Multiple Slack links can be added per person (e.g., different workspaces) using the label field

## [1.30.2] - 2026-01-09

### Changed
- Add activity modal: Description field now takes 2/3 width on desktop with taller input area (280px)
- Modal width increased to max-w-4xl for better proportions

## [1.30.1] - 2026-01-09

### Fixed
- Chat activity type now shows the correct MessageCircle icon instead of a generic circle in the timeline

### Changed
- Add activity modal redesigned with two-column layout for better UX
- Description field is now larger and prominently placed on the right, making it easier to add call/chat notes

## [1.30.0] - 2026-01-09

### Added
- "Recently contacted" section on Dashboard showing people with most recent activities

## [1.29.1] - 2026-01-09

### Added
- Sort people by "Last modified" date on the People page

## [1.29.0] - 2026-01-09

### Added
- Filter people by birth year on the People page
- Filter people by last modified date (7 days, 30 days, 90 days, 1 year) on the People page

## [1.28.0] - 2026-01-09

### Added
- "Year unknown" option for important dates (birthdays, etc.) when you don't know the year
- Dates with unknown year display without year and skip age calculation

## [1.27.2] - 2026-01-09

### Changed
- Teams in "Add work history" form are now sorted alphabetically

## [1.27.1] - 2026-01-09

### Changed
- Import and Export functionality now integrated directly into Settings Data tab (removed separate pages)
- Updated labels to use sentence case throughout Settings page

### Removed
- Separate Import and Export pages (`/settings/import`, `/settings/export`)

## [1.27.0] - 2026-01-09

### Changed
- Settings page reorganized into tabbed interface for better navigation
  - **Sync** tab: Calendar subscription and CardDAV sync settings
  - **Notifications** tab: Email and Slack notification preferences
  - **Data** tab: Import and export functionality
  - **Admin** tab: User approval, relationship types, and system actions (admin only)
  - **About** tab: Version information

## [1.26.3] - 2026-01-08

### Added
- Admins now receive an email notification when a new user registers and is waiting for approval

## [1.26.2] - 2026-01-08

### Fixed
- Dashboard now live-updates when creating, editing, or deleting contacts and teams (no hard reload needed)

## [1.26.1] - 2026-01-08

### Fixed
- Backend now properly stores HTML content for notes and activities (changed from `sanitize_textarea_field` to `wp_kses_post`)

## [1.26.0] - 2026-01-08

### Added
- Rich text editor for notes and activities with support for bold, italic, lists, and links
- TipTap-based editor with formatting toolbar

## [1.25.9] - 2026-01-08

### Fixed
- CardDAV now stores and uses client-provided URIs for contact lookups, enabling proper sync with Apple Contacts and other clients that use custom URI formats

## [1.25.8] - 2026-01-08

### Added
- `.well-known/carddav` auto-discovery endpoint for proper CardDAV client setup

## [1.25.7] - 2026-01-08

### Added
- Detailed CardDAV logging for create, update, and delete operations (includes person IDs)
- Enhanced auth logging for debugging intermittent failures

## [1.25.6] - 2026-01-08

### Fixed
- CardDAV authentication now uses `wp_verify_fast_hash()` for WordPress 6.8+ BLAKE2b hashing (`$generic$` prefix) with fallback to `wp_check_password()` for older versions
- Reverted custom password storage in favor of native WordPress Application Passwords

## [1.25.5] - 2026-01-08

### Changed
- CardDAV now uses its own password storage with standard WordPress hashing, bypassing SiteGround's custom `$generic$` hash format that couldn't be verified

### Fixed
- CardDAV authentication now works on SiteGround hosting

## [1.25.4] - 2026-01-08

### Fixed
- CardDAV authentication - directly validate against WP_Application_Passwords instead of wp_authenticate() which restricts app passwords to REST/XML-RPC requests

## [1.25.3] - 2026-01-08

### Fixed
- CardDAV authentication - use wp_authenticate() instead of wp_authenticate_application_password() which is only a filter callback

## [1.25.2] - 2026-01-08

### Fixed
- App password creation failing with "Invalid parameter(s): app_id" error - removed unnecessary app_id parameter

## [1.25.1] - 2026-01-08

### Added
- Time field for activities - activities now support recording both date and time
- Activity form defaults to current date and time
- Timeline displays activity time alongside date (e.g., "Yesterday at 14:30", "Jan 5, 2026 at 09:00")
- Relative time display now correctly uses combined date+time (e.g., "30 minutes ago" instead of "about 10 hours ago")

## [1.25.0] - 2026-01-08

### Added
- CardDAV server for bidirectional contact sync with Apple Contacts, Android (DAVx5), Thunderbird, and other CardDAV clients
- App Passwords management UI in Settings for secure CardDAV authentication
- PHP vCard export class for server-side vCard generation
- Sabre/DAV integration with custom backends for authentication, principals, and contacts
- Sync token support for efficient incremental synchronization
- CardDAV documentation with setup guides for all major platforms
- Composer dependency management with sabre/dav 4.7.0

### Technical Details
- CardDAV endpoint at `/carddav/` with full RFC 6352 compliance
- Uses WordPress native Application Passwords for authentication
- Sync tokens track changes per-user for efficient delta sync
- Respects existing access control (users only see their own contacts)

## [1.24.8] - 2026-01-07

### Changed
- Person detail page layout: Timeline now appears above Work history
- Person detail page layout: Addresses moved to sidebar, below Relationships

## [1.24.7] - 2026-01-07

### Added
- Click-to-upload logo on team detail page (hover over logo to see camera icon, click to upload)

## [1.24.6] - 2026-01-07

### Changed
- Major performance improvement: People list now loads significantly faster
- Deceased status (`is_deceased`) is now computed server-side and included in person REST responses
- Eliminated N+1 API queries when loading the People screen (was making one API call per person)

## [1.24.5] - 2026-01-07

### Changed
- Reminder card avatars now match the size of favorites and recently edited people (40px)

## [1.24.4] - 2026-01-07

### Added
- Phone number field on Add Person form with type selector (Mobile/Phone)
- vCard import on Add Person screen now properly imports phone numbers with correct type

### Fixed
- vCard parse endpoint now returns phone_type for proper mobile/phone distinction

## [1.24.3] - 2026-01-07

### Fixed
- vCard import now correctly handles phone numbers with multiple TYPE parameters (e.g., `TEL;type=CELL;type=VOICE;type=pref`)
- Phone type detection prioritizes meaningful types (CELL, MOBILE) over generic ones (VOICE, pref)

## [1.24.2] - 2026-01-07

### Changed
- Dashboard reminder cards now show the reminder title instead of the person name
- Related people avatars in reminder cards moved to the right side for a cleaner layout

## [1.24.1] - 2026-01-07

### Fixed
- Checkbox labels now properly toggle their associated checkboxes when clicked (PersonForm, DateForm)

## [1.24.0] - 2026-01-07

### Added
- Structured address fields: addresses now have separate fields for Street, Postal code, City, State/Province, and Country
- Dedicated Addresses section on person detail page with multi-line display format
- AddressForm component for adding and editing addresses with structured fields
- WP-CLI migration command `wp prm migrate addresses` to migrate existing single-line addresses to new format
- vCard import/export now uses structured address format (ADR property with all components)
- Google Contacts import now maps address components to structured fields
- Monica import now uses structured address fields

### Changed
- Removed "Address" option from contact detail types (addresses now have their own dedicated section)
- Addresses display format: Street / Postal code City / State, Country (each on own line)

## [1.23.0] - 2026-01-07

### Added
- New "Calendar link" contact type for Calendly, Cal.com, and similar scheduling links
- Calendar links display with a calendar icon and open in a new tab when clicked

## [1.22.5] - 2026-01-07

### Fixed
- Work history form: "Currently works here" label is now clickable to toggle the checkbox

## [1.22.4] - 2026-01-07

### Changed
- Email notifications: Removed calendar emoji from section headings (Today, Tomorrow, This week)
- Email notifications: Changed section headings from ALL CAPS to sentence case

## [1.22.3] - 2026-01-07

### Fixed
- Timeline: Edit button for activities now works - opens the activity modal with data prefilled for editing

## [1.22.2] - 2026-01-07

### Added
- Dashboard: Completing a todo now shows the same "Complete & log activity" option as on other screens

## [1.22.1] - 2026-01-07

### Changed
- Todos page now always shows recently completed todos (last 3 days) in a separate "Recently completed" section
- "Show all completed" button only appears when there are older completed todos
- Improved UI: recent completions always visible, older completions hidden by default

## [1.22.0] - 2026-01-07

### Added
- "Complete & log activity" option when completing todos - converts the todo into a timeline activity
- New CompleteTodoModal component that offers choice between just completing or logging as activity
- QuickActivityModal now accepts initialData prop to prefill form fields

### Changed
- When completing a todo, users are now prompted with options: "Just complete" or "Complete & log activity"
- The activity modal is prefilled with the todo content when converting to activity
- After logging the activity, the todo is automatically marked as complete

## [1.21.0] - 2026-01-07

### Added
- "Investments" section on person detail page showing teams they've invested in
- "Invested in" section on team detail page showing teams they've invested in
- New REST API endpoint `/stadion/v1/investments/{id}` to query reverse investor relationships

## [1.20.2] - 2026-01-07

### Fixed
- Investors field now saves and loads properly (changed from ACF relationship to post_object field type)
- Existing investors now appear when editing an team

## [1.20.1] - 2026-01-07

### Fixed
- Investor names now display correctly on team detail page (was showing "Team" instead of actual names)

## [1.20.0] - 2026-01-07

### Added
- New "Investors" field for teams allowing both people and teams to be listed as investors
- Investors section displayed on team detail page with links to people/teams
- Multi-select investor picker in team form with search

## [1.19.3] - 2026-01-07

### Added
- Todos are now included in email and Slack notifications alongside important dates
- Notifications show todos grouped by today, tomorrow, and rest of week with overdue indicators

## [1.19.2] - 2026-01-07

### Added
- New "Chat" activity type for logging chat/messaging interactions

## [1.19.1] - 2026-01-07

### Changed
- Dashboard todo card: moved due date to the right side for cleaner layout

## [1.19.0] - 2026-01-07

### Added
- vCard import on "Add new person" screen - drop a .vcf file to pre-fill the form
- New API endpoint `/stadion/v1/import/vcard/parse` to parse single vCard contact data

## [1.18.0] - 2026-01-07

### Changed
- Search is now a lightbox/modal instead of inline in the header
- Press ⌘K (Mac) or Ctrl+K (Windows/Linux) to open search from anywhere
- Search modal supports keyboard navigation (arrow keys and Enter to select)
- Search button in header shows keyboard shortcut hint

## [1.17.0] - 2026-01-07

### Added
- Teams can now have parent teams (hierarchical structure)
- Parent team selector in team form with searchable dropdown
- Subsidiaries section on team detail page showing child teams
- "Subsidiary of" link displayed on team header when team has a parent

## [1.16.1] - 2026-01-07

### Changed
- Todo due date now defaults to today when creating new todos

## [1.16.0] - 2026-01-07

### Added
- Collapsible search bar in header - opens on click for cleaner UI
- Quick Add menu (+) in header for creating new Person, Team, Todo, or Date
- Global Todo creation with person dropdown - accessible from header and Todos page
- "Add todo" button on Todos list page

### Changed
- Search results dropdown now positioned to the right for better UX

## [1.15.1] - 2026-01-07

### Fixed
- Navigation menu labels now always visible (were incorrectly hidden on mobile)

## [1.15.0] - 2026-01-07

### Added
- New Todos page accessible from the main navigation menu
- Dashboard now shows "Open todos" stat card (4th card in the stats row)
- Dashboard now displays open todos alongside upcoming reminders
- REST API endpoint `GET /stadion/v1/todos` to fetch all todos across all people
- Dashboard API now returns `open_todos_count` in stats

### Changed
- Dashboard layout reorganized: Row 1 has Upcoming reminders + Open todos, Row 2 has Favorites + Recently edited people
- Stats row now shows 4 cards (People, Teams, Events, Open todos)
- Todos page shows all open todos with ability to toggle completion, edit, and delete
- Todos are sorted by due date (earliest first), with completed todos at the bottom

## [1.14.8] - 2026-01-07

### Changed
- Todos now displayed in their own card in the right sidebar, above Relationships
- Todos removed from Timeline section (Timeline now only shows notes and activities)
- Todo card includes toggle, edit, and delete functionality
- Incomplete todos shown first (sorted by due date), completed todos at bottom

## [1.14.7] - 2026-01-07

### Changed
- Todos no longer display creation date (the "• Yesterday" line is removed)
- Only notes and activities show the date/time indicator

## [1.14.6] - 2026-01-07

### Fixed
- Fixed "Failed to update todo" error when updating todo metadata (due date, completion status) without changing content
- Fixed same issue in note updates
- `wp_update_comment` returns 0 when content is unchanged, which was incorrectly treated as an error

## [1.14.5] - 2026-01-07

### Changed
- Todos now display in their own section at the top of the timeline, separate from notes and activities
- Todos are ordered by due date (earliest first) instead of creation date
- Completed todos are shown at the bottom of the todos section
- Todos without due dates appear after those with due dates

## [1.14.4] - 2026-01-07

### Fixed
- Todos now correctly display on person timeline (was being excluded by comment filter)
- Fixed `exclude_from_regular_queries` filter to check for `type__in` in addition to `type` to prevent excluding our custom comment types from timeline queries

## [1.14.3] - 2026-01-07

### Added
- WordPress backend URLs now redirect to SPA frontend routes (e.g., `?post_type=person&p=291` → `/people/291`)

## [1.14.2] - 2026-01-07

### Fixed
- Notification time picker now rounds to nearest 5 minutes (browsers don't enforce step attribute)

## [1.14.1] - 2026-01-07

### Changed
- Notification time picker now uses 5-minute steps to match server cron frequency

## [1.14.0] - 2026-01-07

### Added
- Per-user cron jobs for precise notification timing at each user's preferred time
- Admin button to reschedule all user reminder cron jobs in Settings
- REST API endpoint `POST /stadion/v1/reminders/reschedule-cron` to reschedule all cron jobs
- User cron job cleanup when user is deleted via `delete_user` hook

### Changed
- Notification timing now uses individual cron jobs per user instead of single daily cron
- `update_notification_time` API endpoint now reschedules user's cron job automatically
- `get_cron_status` API endpoint now returns per-user cron status information
- Updated reminders documentation to reflect per-user cron architecture

### Deprecated
- `process_daily_reminders()` method - use `process_user_reminders($user_id)` instead

## [1.13.1] - 2026-01-04

### Added
- WP-CLI command `wp prm reminders trigger` to manually trigger daily reminders
- REST API endpoint `/stadion/v1/reminders/cron-status` to check cron job status

## [1.13.0] - 2026-01-04

### Added
- Enhanced timeline view with visual timeline design, date grouping, and icons
- Quick activity logging modal with activity type selector, date picker, and participant selection
- Todo system: person-specific todos with completion status and optional due dates
- Todo creation and editing modals
- Note creation modal
- Timeline utilities for date formatting, grouping, and activity type icons
- REST API endpoints for todos: GET, POST, PUT, DELETE `/stadion/v1/people/{id}/todos` and `/stadion/v1/todos/{id}`
- Todo comment type (`stadion_todo`) with meta fields: `is_completed` (boolean) and `due_date` (string)
- WP-CLI command `wp prm reminders trigger` to manually trigger daily reminders
- REST API endpoint `/stadion/v1/reminders/cron-status` to check cron job status

### Changed
- Timeline section now displays notes, activities, and todos in a unified view
- Timeline items are grouped by date (Today, Yesterday, This Week, Older)
- Activity types show appropriate icons (call, email, meeting, coffee, lunch, note)
- Completed todos are visually distinct with strikethrough and muted colors
- Overdue todos are highlighted in red
- Timeline endpoint now includes todos alongside notes and activities

## [1.12.2] - 2026-01-04

### Added
- Slack notification target configuration interface in Settings
- Ability to select multiple Slack channels and users for notifications
- REST API endpoints to fetch Slack channels/users and manage notification targets
- Support for sending notifications to multiple targets simultaneously

### Changed
- Simplified notification format: if a person's name appears in the date title (e.g., "Eva Douma's Birthday"), that name becomes the clickable link and the duplicate name below is removed
- Removed header "Your Important Dates - <date>" from Slack notifications

### Fixed
- Fixed Slack API calls to use POST instead of GET for conversations.list and users.list
- Fixed Slack data loading when Slack is already connected on page load
- Fixed checkbox interaction by adding proper cursor styling

## [1.12.1] - 2026-01-04

### Fixed
- Fixed Slack OAuth URL construction to properly pass client_id parameter
- Fixed Slack OAuth authorize endpoint to return JSON instead of redirect (REST API endpoints cannot use wp_redirect)

## [1.12.0] - 2026-01-04

### Added
- Slack OAuth 2.0 integration replacing webhook-based integration
- OAuth flow with "Connect Slack" button in Settings
- Slack Web API support for messaging channels and users directly
- Slash command `/stadion` to view recent reminders in Slack
- Automatic Slack user ID mapping for direct messaging
- Slack workspace name display in Settings
- Event subscription endpoint for Slack URL verification

### Changed
- Slack notifications now use Web API (`chat.postMessage`) instead of incoming webhooks
- Slack connection status shown in Settings instead of webhook URL input
- Legacy webhook support maintained for backward compatibility during migration

### Technical Details
- New REST API endpoints: `/stadion/v1/slack/oauth/authorize`, `/stadion/v1/slack/oauth/callback`, `/stadion/v1/slack/disconnect`, `/stadion/v1/slack/commands`, `/stadion/v1/slack/events`
- User meta keys: `stadion_slack_bot_token`, `stadion_slack_workspace_id`, `stadion_slack_workspace_name`, `stadion_slack_user_id`
- Requires WordPress constants: `STADION_SLACK_CLIENT_ID`, `STADION_SLACK_CLIENT_SECRET`, `STADION_SLACK_SIGNING_SECRET`

## [1.11.4] - 2026-01-04

### Changed
- Changed all Title Case text to Sentence case across the entire app for consistency (e.g., "First Name" → "First name", "Add Person" → "Add person", "Save Changes" → "Save changes")

## [1.11.3] - 2026-01-04

### Changed
- Updated dashboard text labels for consistency: "Upcoming Reminders" → "Upcoming reminders", "Recent People" → "Recently edited people", "Total People" → "Total people", "Important Dates" → "Events"
- Updated "View all" links to be more descriptive: "View all reminders" and "View all people"

## [1.11.2] - 2026-01-04

### Changed
- Dashboard card headers now display appropriate icons (Star for Favorites, Calendar for Upcoming Reminders, Users for Recent People)
- Removed star icons from individual favorite items in the Favorites section (star icon now only shown in header)

## [1.11.1] - 2026-01-04

### Changed
- Search functionality now only searches People and Teams - dates removed from search as they have a dedicated Dates page

## [1.11.0] - 2026-01-04

### Added
- Daily digest reminder system - users receive one notification per day with dates for today, tomorrow, and rest of week
- Multi-channel notification support (Email and Slack)
- User preferences for enabling/disabling notification channels in Settings
- Slack webhook configuration with automatic testing
- Manual trigger button for reminders (admin only) in Settings → Administration
- REST API endpoints for notification channel management and manual triggering

### Changed
- Removed `reminder_days_before` field from important dates - all dates are now included in daily digest if they occur within 7 days
- Reminder emails now use daily digest format instead of individual per-date reminders
- Notification system refactored to use channel-based architecture for extensibility

### Removed
- `reminder_days_before` ACF field from important date post type
- Per-date reminder timing configuration

## [1.10.1] - 2026-01-04

### Fixed
- Fixed pre-selection of newly created person in relationship form - now waits for person to be loaded before selecting

## [1.10.0] - 2026-01-04

### Added
- Added seamless flow to add a new person from the relationship form
- "Add New Person" button appears below the person selector when creating a new relationship
- After creating a person from the relationship form, automatically returns to relationship form with new person pre-selected
- Cancel button in PersonForm now returns to relationship form when coming from relationship flow

## [1.9.2] - 2026-01-04

### Changed
- VCard import now adds to existing contact information instead of replacing it when updating contacts
- Email addresses, phone numbers, addresses, and URLs from VCard are merged with existing entries
- Work history entries are also merged instead of replaced
- Duplicate entries are prevented by checking contact_type + contact_value combinations

## [1.9.1] - 2026-01-04

### Fixed
- VCard import now always imports photos, even when updating existing contacts that already have a photo
- Existing photos are replaced with imported photos to ensure VCard data takes precedence

## [1.9.0] - 2026-01-04

### Added
- Added ability to delete user accounts from the admin approval screen
- Delete button available for both unapproved/denied users and approved users
- When a user is deleted, all their related data (people, teams, dates) is automatically deleted
- Added WordPress hook to clean up user posts when user is deleted via any method

### Changed
- User deletion now permanently removes all associated CRM data

## [1.8.1] - 2026-01-04

### Fixed
- Fixed approval screen display for unapproved users - now shows a properly styled "Account Pending Approval" screen instead of an error
- Changed `/stadion/v1/user/me` endpoint permission callback to allow logged-in users (not just approved) so approval status can be checked

## [1.8.0] - 2026-01-04

### Changed
- Renamed "Teams" to "Teams" throughout the user interface
- Updated all user-facing labels, navigation items, page titles, and form labels
- Post type slug (`team`) and API endpoints (`/wp/v2/teams`) remain unchanged for backward compatibility
- Updated documentation to reflect the new terminology

## [1.7.1] - 2026-01-04

### Changed
- Contact information now always displays in a fixed order: Email, Phone numbers, Addresses, Other

## [1.7.0] - 2026-01-04

### Added
- Added support for Bluesky and Threads social links
- Bluesky and Threads icons appear after Twitter/X in the social icons order

### Changed
- Updated social icons display order: LinkedIn, Twitter/X, Bluesky, Threads, Instagram, Facebook, Website

## [1.6.8] - 2026-01-04

### Changed
- Social icons now always display in a fixed order: LinkedIn, Twitter/X, Instagram, Facebook, Website

## [1.6.7] - 2026-01-04

### Changed
- Replaced LinkedIn icon with custom SVG using official LinkedIn brand icon

## [1.6.6] - 2026-01-04

### Changed
- Replaced social icons with Simple Icons from @icons-pack/react-simple-icons
- Using Simple Icons for Facebook, Instagram, and Twitter/X
- LinkedIn and Website icons remain from Lucide React (Simple Icons doesn't include LinkedIn in this package version)

## [1.6.5] - 2026-01-04

### Fixed
- Replaced Lucide React social icons with Font Awesome solid icons from react-icons for proper solid/filled appearance
- Added react-icons package dependency

## [1.6.4] - 2026-01-04

### Changed
- Increased spacing above social icons (changed from mt-2 to mt-4)
- Changed social icons from outline to solid fill style

## [1.6.3] - 2026-01-04

### Fixed
- Increased spacing between lines in profile header section (changed from space-y-2 to space-y-3)

## [1.6.2] - 2026-01-04

### Changed
- Social icons moved below tags/labels in the profile header
- Increased spacing between lines in the profile header section
- Increased profile photo size by ~17% (from 96px to 112px)

## [1.6.1] - 2026-01-04

### Changed
- Social links (Facebook, LinkedIn, Instagram, Twitter/X, Website) are now displayed in the profile header bar alongside the person's name and picture
- Social links appear as icons on one line without labels
- Social links are no longer shown in the contact information section

## [1.6.0] - 2026-01-04

### Changed
- Social links (Facebook, LinkedIn, Instagram, Twitter/X, Website) are now grouped together and displayed as icons in a row underneath a "Social Links:" label
- Social link icons are clickable and use brand colors (Facebook blue, LinkedIn blue, Instagram pink, Twitter blue)
- Social links no longer show the full URL text, only the icon

## [1.5.3] - 2026-01-04

### Changed
- WhatsApp icon is now always visible (not just on hover) and displayed in WhatsApp green after phone numbers

## [1.5.2] - 2026-01-04

### Fixed
- Fixed missing import for `useQueryClient` in ContactDetailForm component

## [1.5.1] - 2026-01-04

### Changed
- WhatsApp links now use `https://wa.me/` format instead of `whatsapp:` protocol

## [1.5.0] - 2026-01-04

### Added
- Confirmation dialog when deleting relationships - asks if inverse relationship should also be deleted
- Automatic Gravatar sideloading when adding email address to person without an image

### Changed
- Relationship deletion now gives user control over inverse relationship deletion
- Email addresses added to people without images will automatically check for and load Gravatar

## [1.4.9] - 2026-01-04

### Fixed
- Fixed critical error when creating/deleting relationships via REST API
- Fixed handling of WP_Post object parameter from REST API hooks

## [1.4.8] - 2026-01-04

### Fixed
- Fixed bug where sibling relationships weren't creating inverse relationships automatically
- Improved normalization of inverse relationship type IDs to handle different ACF return formats
- Added fallback for symmetric relationships when inverse mapping is missing
- Added REST API hooks to ensure inverse sync happens

## [1.4.7] - 2026-01-04

### Changed
- Changed team logo backgrounds from gray to white for better visibility

## [1.4.6] - 2026-01-04

### Changed
- Increased team logo size in work history from 10x10 to 20x20 (2x bigger)
- Increased team logo size on team detail page from 16x16 to 24x24 (1.5x bigger)

## [1.4.5] - 2026-01-04

### Changed
- Changed team logo display from `object-cover` to `object-contain` so logos are fully visible without cropping
- Added light gray background to team logo containers for better visibility

## [1.4.4] - 2026-01-04

### Changed
- Changed "Register For This Site" notice text to "Register for Stadion"
- No longer hiding the register notice (now displays with updated text)

## [1.4.3] - 2026-01-04

### Changed
- Changed registration page title to "Register for Stadion"
- Changed login page title to "Log in to Stadion"
- Changed lost password page title to "Lost your password for Stadion?"
- Added left border color (#d97706) and 20px top margin to notice.info.message divs

## [1.4.2] - 2026-01-04

### Changed
- Modified registration confirmation message to include approval notice: "Your account is then subject to approval."
- Hidden "Register For This Site" notice on registration page

## [1.4.1] - 2026-01-04

### Changed
- Removed Administration section from Settings page (no longer needed)
- Renamed Configuration section to Administration
- Moved user approval interface to frontend Settings page (Administration section, admin only)
- Added Export Data sub-section to Settings page Data section
- Export supports vCard (.vcf) and Google Contacts CSV formats
- Added back buttons to Import and Export sub-sections

### Removed
- Administration section with WordPress admin links (moved functionality to frontend)

## [1.4.0] - 2026-01-04

### Added
- User approval system - new users default to "Stadion User" role but require admin approval before accessing the system
- Admin interface for approving/denying users:
  - Approval status column in users list
  - Bulk approve/deny actions
  - Individual approve/deny actions in user row
  - Email notification sent when user is approved
- Frontend approval check - unapproved users see a message instead of accessing the app
- Approval status included in user API response

### Changed
- Default role for new registrations is now "Stadion User" instead of Subscriber
- All REST API endpoints now check approval status before allowing access
- Access control system blocks unapproved users from viewing any data
- Users are marked as unapproved by default when registered

## [1.3.1] - 2026-01-04

### Added
- Custom "Stadion User" role automatically created on theme activation
- Role has minimal permissions: can create/edit/delete their own people and teams, upload files
- Role cannot access WordPress admin settings, manage users, or install plugins/themes
- Role is automatically removed on theme deactivation (users reassigned to Subscriber)

## [1.3.0] - 2026-01-04

### Removed
- Removed sharing functionality - the `shared_with` ACF field has been removed from all post types (person, team, important_date)
- Users can now only see posts they created themselves
- Removed sharing tab from person fields in ACF
- Removed all sharing-related logic from access control, reminders, and import classes
- Updated documentation to reflect removal of sharing functionality

### Changed
- Access control simplified - users can only access posts they authored
- Reminder notifications now only go to post authors (no longer includes shared users)

## [1.2.7] - 2026-01-04

### Changed
- Administrators are now restricted on the frontend - they can only see and access people/teams/dates they created themselves
- Administrators still have full access in the WordPress admin area for system management
- This ensures data privacy is maintained even for administrators when using the frontend React SPA

## [1.2.6] - 2026-01-04

### Fixed
- Person and team deletion now properly redirects to list page after successful deletion
- Added error handling for deletion operations with user feedback
- Trashed people and teams can no longer be accessed - users are automatically redirected to the list page
- Access control now filters out trashed posts in REST API responses
- Deletion now uses force delete to permanently remove items instead of moving to trash

## [1.2.5] - 2026-01-04

### Changed
- Hide button label text on mobile devices for buttons with icons and labels to improve mobile UI space efficiency
- Button labels are now visible on medium screens and larger (768px+)
- Affects all action buttons, navigation links, and form buttons throughout the interface

## [1.2.4] - 2026-01-04

### Changed
- Contact information rows now highlight with a subtle background color when hovering over the edit/delete buttons for better visual feedback

## [1.2.3] - 2026-01-04

### Added
- vCard export: Export individual person contacts as vCard (.vcf) files
- Export button in PersonDetail page to download contact as vCard
- vCard export includes: name, nickname, email, phone, mobile, address, website, social media links, team, job title, and birthday
- Compatible with Apple Contacts, Outlook, Android, and other vCard-compatible applications

## [1.2.2] - 2026-01-04

### Added
- Comprehensive documentation covering all system components:
  - `docs/data-model.md` - Post types, taxonomies, and ACF field definitions
  - `docs/access-control.md` - Row-level security system documentation
  - `docs/rest-api.md` - Complete API endpoint reference
  - `docs/frontend-architecture.md` - React SPA structure and patterns
  - `docs/ical-feed.md` - Calendar subscription system
  - `docs/reminders.md` - Email notification system
  - `docs/family-tree.md` - Family tree visualization feature

### Changed
- Updated `docs/import.md` with Google Contacts duplicate detection feature
- Updated `docs/architecture.md` with documentation index linking to all docs

## [1.2.1] - 2026-01-04

### Fixed
- Google Contacts import: Fixed duplicate detection not finding existing contacts due to access control filter interference

## [1.2.0] - 2026-01-04

### Added
- Google Contacts import: Duplicate detection with user choice for each match
- When a contact in the CSV matches an existing person by name, users can choose to:
  - **Update existing**: Merge the CSV data into the existing person (default)
  - **Create new**: Import as a new person (for different people with the same name)
  - **Skip**: Don't import this contact at all
- Duplicate resolution UI shows both CSV data and existing contact details including photo
- Backend returns potential duplicates during validation with existing person details

## [1.1.6] - 2026-01-04

### Added
- Google Contacts import now sideloads profile photos from Google Photos URLs
- Photos are downloaded and set as the person's featured image
- Photo count shown in validation summary and import results

## [1.1.5] - 2026-01-04

### Fixed
- Google Contacts import now supports both old and new Google CSV export formats
- Added support for "First Name"/"Last Name" columns (new format) alongside "Given Name"/"Family Name" (old format)
- Added support for "Team Name"/"Team Title" columns (new format) alongside "Team 1 - Name"/"Team 1 - Title" (old format)
- Added support for "E-mail X - Label"/"Phone X - Label" columns (new format) alongside "E-mail X - Type"/"Phone X - Type" (old format)
- Added support for `--MM-DD` birthday format (dates without year)
- Added Team Department import (appended to job title)
- Improved label formatting to handle Google's special prefixes (e.g., "* Other", "* Work")

## [1.1.4] - 2026-01-04

### Fixed
- Fixed "acf[gender] is not one of..." validation error when editing contact details, work history, or relationships for people without a gender set
- Added `sanitizePersonAcf()` utility function to properly handle empty enum fields and ensure repeater arrays

## [1.1.3] - 2026-01-04

### Changed
- Performance: Implemented conditional class loading - PHP classes are now only loaded when needed
- Added SPL autoloader for on-demand class file loading
- Core classes (Post Types, Taxonomies, Access Control) load on every request
- REST API and Import classes only load for REST requests
- Reminders class only loads for admin and cron contexts
- iCal Feed class loads early for feed requests with optimized early return

## [1.1.2] - 2026-01-04

### Changed
- DRY refactor: Created shared `src/utils/formatters.js` utility module
- Added `decodeHtml()`, `getTeamName()`, `getPersonName()`, and `getPersonInitial()` utility functions
- Removed 7 duplicate `decodeHtml` function definitions across codebase
- All team and person name display now uses consistent utility functions

## [1.1.1] - 2026-01-04

### Fixed
- Team names now properly decode HTML entities (e.g., "Twynstra &amp; Gudde" now displays as "Twynstra & Gudde")
- Fixed on People list, Teams list, Team detail page, and Person detail work history

## [1.1.0] - 2026-01-04

### Added
- vCard import: Import contacts from vCard (.vcf) files exported from Apple Contacts, Outlook, Android, or any vCard-compatible app
- Google Contacts import: Import contacts from Google Contacts CSV export files
- Import page: New tabbed interface for selecting between vCard, Google Contacts, and Monica CRM import methods
- Both imports support: names, nicknames, phone numbers, emails, addresses, websites/social media, teams with job titles, birthdays, notes, and photos (vCard only)
- Duplicate detection: Contacts with matching names are updated instead of duplicated
- Multi-contact support: vCard files containing multiple contacts are fully supported

### Changed
- Import settings page now has a tabbed interface to switch between import methods
- Import page shows helpful instructions for Google Contacts export

## [1.0.123] - 2026-01-04

### Changed
- Settings page: Moved Import functionality to a dedicated submenu page at `/settings/import`
- Settings page: Added "Data" section with link to Import page

### Removed
- Settings page: Removed "Account" section (profile link already in user menu)
- Settings page: Removed "Session" section with Log Out button (already in sidebar)

## [1.0.122] - 2026-01-04

### Changed
- Settings page: Administration and Configuration sections now only visible to administrators
- Relationship Types page: Non-admin users now see "Access Denied" message instead of the management interface
- About section: Now displays the actual theme version from style.css

### Added
- Disabled WordPress admin color scheme picker for all users
- Disabled application passwords for improved security

## [1.0.121] - 2026-01-04

### Fixed
- Dashboard statistics now respect access control: new users see only their own people/teams/dates counts, not totals from all users
- Fixed `wp_count_posts()` bypassing access control by using `get_accessible_post_ids()` for non-admin users

## [1.0.120] - 2026-01-04

### Added
- Relationships panel: Deceased people now show † symbol next to their name
- Family Tree: Deceased people now show † symbol next to their name
- Family Tree: Deceased people have muted gray styling (border, text, placeholder)

## [1.0.119] - 2026-01-04

### Added
- Stadion favicon now displays on the WordPress login page

## [1.0.118] - 2026-01-04

### Fixed
- Login button now properly uses Stadion amber colors (overrides WordPress defaults)
- Added more margin above the login button for better spacing

## [1.0.117] - 2026-01-04

### Added
- Custom Stadion-branded WordPress login page with amber theme colors
- Login page displays Stadion logo and name
- Users are redirected to homepage after successful login

## [1.0.116] - 2026-01-04

### Changed
- Event names now use full names instead of first names only (e.g., "Joost de Valk's Birthday" instead of "Joost's Birthday")
- Updated auto-title generation for important dates to use full names
- Updated Monica import to use full names for birthdays, special dates, and life events

## [1.0.115] - 2026-01-04

### Added
- AGENTS.md: Added production deployment instructions (Rule 5)
- AGENTS.md: Added production server details for automated deployments

## [1.0.114] - 2026-01-04

### Fixed
- Photo uploads now use properly named files based on person/team name instead of original filename
- New REST API endpoints: `/stadion/v1/people/{id}/photo` and `/stadion/v1/teams/{id}/logo/upload`
- Files are saved as `{sanitized-name}.{ext}` (e.g., `john-doe.jpg`) for consistent file paths

## [1.0.113] - 2026-01-04

### Changed
- Rebranded application from "Koinastra" to "Stadion" across all user-facing text
- Updated theme name, description, email notifications, and documentation

## [1.0.112] - 2026-01-04

### Added
- iCal calendar feed: Subscribe to important dates from any calendar app (Apple Calendar, Google Calendar, Outlook)
- iCal feed authentication: Secure token-based URLs for private calendar subscriptions
- Settings: Calendar subscription section with feed URL, copy button, and regenerate token option
- REST API endpoints: `/stadion/v1/user/ical-url` and `/stadion/v1/user/regenerate-ical-token`
- Clickable events: Calendar events link directly to the related person's detail page
- Recurring dates: Dates marked as recurring automatically repeat yearly in the feed

## [1.0.111] - 2024-12-19

### Changed
- Moved "View Family Tree" button to top right of profile header card
- Removed "View Family Tree" button from relationships card

## [1.0.110] - 2024-12-19

### Changed
- Family Tree: Disabled physics, manually reposition spouses after layout
- Family Tree: Spouses are now placed 120px apart (60px from center each)
- Family Tree: More reliable spouse positioning that doesn't conflict with hierarchical layout

## [1.0.109] - 2024-12-19

### Changed
- Family Tree: Much stronger spouse attraction - spouse edges 50px, parent-child 300px
- Family Tree: Spring constant increased to 0.2 (4x stronger)
- Family Tree: Node distance reduced to 100px to allow closer positioning
- Family Tree: 300 stabilization iterations for better settling

## [1.0.108] - 2024-12-19

### Changed
- Family Tree: Stronger spouse attraction - spouse edges 80px, parent-child edges 200px
- Family Tree: Increased spring constant (0.05) for stronger edge attraction
- Family Tree: Increased stabilization iterations (200) for better settling

## [1.0.107] - 2024-12-19

### Changed
- Family Tree: Enabled physics simulation to better position spouses next to each other
- Family Tree: Spouse edges have shorter preferred length (100px) to pull partners together
- Family Tree: Uses hierarchical repulsion to maintain levels while optimizing positions

## [1.0.106] - 2024-12-19

### Changed
- Family Tree: Nodes are now 1.5x bigger (size 45 instead of 30)
- Family Tree: Generation levels now calculated relative to the start person
- Family Tree: Parents at level -1, grandparents -2, children +1, etc.
- Family Tree: Spouses/partners correctly placed on same level via BFS traversal
- Family Tree: Increased spacing between nodes and levels for cleaner layout
- Family Tree: Changed sort method to 'hubsize' for better spouse positioning

## [1.0.105] - 2024-12-19

### Changed
- Family Tree: All nodes now same size (placeholder with initials for people without photos)
- Family Tree: All lines are now straight instead of curved
- Family Tree: Spouses/partners are now on the same level (generation)
- Family Tree: Labels always appear below the circle

## [1.0.104] - 2024-12-19

### Added
- Family Tree: Partner relationships now also shown as spouse connections

## [1.0.103] - 2024-12-19

### Added
- Family Tree: Spouse and lover relationships now shown with pink dashed lines
- Family Tree: Spouses/lovers appear connected horizontally in the tree

## [1.0.102] - 2024-12-19

### Changed
- Family Tree: Switched from react-d3-tree to vis.js (vis-network) for visualization
- Family Tree: Now properly supports multiple parents per child (true family tree structure)
- Family Tree: Hierarchical layout with parents above children
- Family Tree: Interactive zoom, pan, and click-to-navigate functionality
- Family Tree: Nodes show name, gender symbol, age, and birth date

### Added
- vis-network and vis-data packages for graph visualization

### Removed
- react-d3-tree package (replaced by vis.js)
- PersonNode component (vis.js handles node rendering)

## [1.0.101] - 2024-12-19

### Changed
- Family Tree: Reverted to individual person nodes (removed couple merging)
- Family Tree: Simplified tree building - shows primary lineage from eldest ancestor
- Family Tree: Removed virtual root - single tree from eldest ancestor down

### Fixed
- Family Tree: No more empty node at top of tree
- Family Tree: Each person shown as individual node (no incorrect parent-child relationships)

### Known Limitations
- Due to react-d3-tree's single-parent hierarchy, children connect to only one parent
- The tree shows the primary lineage; other parents appear but children only connect once

## [1.0.100] - 2024-12-19

### Changed
- Family Tree: Parents who share children are now shown as couples ("Person & Partner")
- Family Tree: Couple nodes show both photos side-by-side
- Family Tree: Properly hides virtual root node when multiple lineages exist
- Family Tree: Children branch from the couple instead of individual parents

### Fixed
- Family Tree: Both parents now appear in the tree (as a couple unit)
- Family Tree: Virtual root no longer shows as empty node

## [1.0.99] - 2024-12-19

### Fixed
- Family Tree: Fixed inverted getParents/getChildren logic
- Family Tree: Relationship type describes WHO the neighbor is (not the person's role)
- Family Tree: If person has "parent" relationship to neighbor, neighbor IS their parent
- Family Tree: Tree now correctly shows parents above children

## [1.0.98] - 2024-12-19

### Changed
- Family Tree: Complete rewrite of tree building algorithm with clear two-phase approach
- Family Tree: Phase 1 collects all relevant family members (ancestors + their siblings, descendants)
- Family Tree: Phase 2 builds tree from root ancestors downward
- Family Tree: Clean helper functions (getParents, getChildren, getSiblings, findRoots)
- Family Tree: Properly handles multiple lineages with virtual root
- Family Tree: Each person included only once in tree
- Family Tree: Removed complex legacy logic and excessive comments

## [1.0.97] - 2024-12-19

### Fixed
- Family Tree: Fix adjacency list to use correct inverse relationship types
- Family Tree: When edge is "parent", reverse edge should be "child" (and vice versa)
- Family Tree: Fix tree hierarchy so parents appear above children (not inverted)
- Family Tree: Ensure siblings are correctly identified as children of same parent

## [1.0.96] - 2024-12-19

### Fixed
- Family Tree: Refactor findUltimateAncestor to collect ALL ancestors and find eldest by birth date
- Family Tree: Use BFS traversal to find all ancestors (not just those with no parents)
- Family Tree: Ensure current person is included in tree and verify inclusion
- Family Tree: Tree now flows from eldest ancestor (top) down to current person and all descendants

## [1.0.95] - 2024-12-19

### Fixed
- Family Tree: Find oldest ancestor by birth date when multiple people have no parents
- Family Tree: Include siblings in tree visualization (children of same parents)
- Family Tree: Sort siblings by birth date (oldest first)
- Family Tree: Ensure tree flows from oldest (top) to youngest (bottom)

## [1.0.94] - 2024-12-19

### Fixed
- Family Tree: Auto-center and auto-zoom tree on initial render
- Family Tree: Remove blue connector dots, center nodes on connector points
- Family Tree: Fix children ordering - ensure oldest appears first (leftmost)
- Family Tree: Position nodes so connector lines connect to center-top of cards

## [1.0.93] - 2024-12-19

### Changed
- Family Tree: Switched back to react-d3-tree from react-family-tree
- Family Tree: Rewrote TreeVisualization component to use react-d3-tree
- Family Tree: Updated PersonNode to work with foreignObject rendering
- Family Tree: Configured vertical orientation with proper spacing
- Family Tree: Added zoom and pan controls

## [1.0.92] - 2024-12-19

### Fixed
- Family Tree: Center nodes on connector points by offsetting by half node size
- Family Tree: Add padding to container so connectors above nodes are visible
- Family Tree: Ensure connecting lines align with center of person blocks

## [1.0.91] - 2024-12-19

### Fixed
- Family Tree: Separate node display size from spacing (160x100 display, 220x140 spacing)
- Family Tree: Increase spacing between nodes for visible connecting lines
- Family Tree: Reverse children order to compensate for library's rendering order
- Family Tree: Ensure oldest person appears at top of tree

## [1.0.90] - 2024-12-19

### Fixed
- Family Tree: Sort children by birth date (oldest first) in tree builder
- Family Tree: Increase node dimensions (180x120) for better spacing and visibility
- Family Tree: Sort children when building relationships to ensure correct order

## [1.0.89] - 2024-12-19

### Fixed
- Family Tree: Fix node positioning using transform translate instead of absolute positioning
- Family Tree: Library calculates positions using left/top multiplied by half dimensions
- Family Tree: Add absolute positioning class to PersonNode for proper rendering

## [1.0.88] - 2024-12-19

### Fixed
- Family Tree: Prevent duplicate relations in parents/children/siblings arrays
- Family Tree: Added comprehensive validation before rendering
- Family Tree: Create deep copy of nodes array for immutability
- Family Tree: Added detailed logging of node structure for debugging
- Family Tree: Better error handling when root node not found

## [1.0.87] - 2024-12-19

### Fixed
- Family Tree: Filter relations to only include IDs that exist in nodes array
- Family Tree: Prevent library errors from referencing non-existent node IDs
- Family Tree: Added validation to ensure all relation IDs are valid
- Family Tree: Added debugging logs to help diagnose issues

## [1.0.86] - 2024-12-19

### Fixed
- Family Tree: Added comprehensive validation and normalization of node structure
- Family Tree: Ensure all relation objects have valid id and type properties
- Family Tree: Filter out any invalid nodes before passing to library
- Family Tree: Normalize all IDs to strings in relations

## [1.0.85] - 2024-12-19

### Fixed
- Family Tree: Fixed "Cannot read properties of undefined (reading 'find')" error
- Family Tree: Ensure all arrays (parents, children, siblings) are always initialized
- Family Tree: Added defensive checks to prevent undefined array access
- Family Tree: Final validation pass to ensure all nodes have required arrays

## [1.0.84] - 2024-12-19

### Fixed
- Family Tree: Convert IDs to strings (react-family-tree expects string IDs, not numbers)
- Family Tree: Added better error handling and logging for debugging
- Family Tree: Prevent duplicate nodes in flat nodes array
- Family Tree: Improved validation of node structure before processing

## [1.0.83] - 2024-12-19

### Fixed
- Family Tree: Fixed JavaScript error "Cannot read properties of undefined (reading 'length')"
- Family Tree: Added proper null/undefined checks in tree traversal
- Family Tree: Ensure children array always exists (even if empty) for react-family-tree compatibility

## [1.0.82] - 2024-12-19

### Changed
- Family Tree: Switched from react-d3-tree to react-family-tree library
- Family Tree: Now correctly displays oldest ancestors at top, youngest descendants at bottom
- Family Tree: Updated TreeVisualization and PersonNode components for new library API

## [1.0.81] - 2024-12-19

### Fixed
- Family Tree: Ensured tree builds downward from ultimate ancestor (oldest person)
- Family Tree: Ultimate ancestor (oldest, no parents) now appears at top of tree
- Family Tree: All descendants appear below, maintaining proper hierarchy

## [1.0.80] - 2024-12-19

### Fixed
- Family Tree: Fixed duplicate people appearing in tree
- Family Tree: Changed logic to traverse up to find ultimate ancestor, then build tree downward
- Family Tree: Prevents cycles and ensures each person appears only once

## [1.0.79] - 2024-12-19

### Fixed
- Family Tree: Simplified to only show parent/child relationships (ignores niece/nephew/aunt/uncle)
- Family Tree: Fixed hierarchy - parents now correctly appear above root person
- Family Tree: Fixed multiple parents display - all parents now show as siblings at top level
- Family Tree: Corrected relationship direction logic

## [1.0.78] - 2024-12-19

### Fixed
- Family Tree: Fixed hierarchy display - parents now appear above root, children below
- Family Tree: Fixed name truncation by increasing node width and using break-words
- Family Tree: Fixed relationship direction logic (child relationship means parent of root)
- Family Tree: Improved tree structure to show proper up/down hierarchy

## [1.0.77] - 2024-12-19

### Changed
- Family Tree: Increased person node size to fully display gender icon
- Family Tree: Added date of birth display in dd-mm-yyyy format on person nodes
- Family Tree: Improved node spacing and layout

## [1.0.76] - 2024-12-19

### Fixed
- Family Tree: Fixed "Unknown" node names by properly extracting person names from various data formats
- Family Tree: Fixed relationship parsing to handle REST API expanded relationship format (relationship_slug field)
- Family Tree: Improved age calculation from birth_date field
- Family Tree: Added better error handling and debugging

## [1.0.75] - 2024-12-19

### Added
- Family Tree visualization: New family tree feature to visualize family relationships
- Family Tree page: Accessible from person detail page, shows hierarchical family tree
- Tree visualization component: Interactive tree with zoom, pan, and node navigation
- Person nodes: Display person photos, names, ages, and gender symbols in tree
- Tree builder utilities: Builds family tree structure from relationship data
- Family relationship filtering: Automatically filters to show only family relationships (parent, child, sibling, etc.)

### Changed
- Person Detail page: Added "View Family Tree" button in Relationships section

## [1.0.74] - 2024-12-19

### Fixed
- JavaScript error: Fixed "data is not defined" error when saving relationships
- Gender-dependent inverse resolution: Fixed logic to correctly resolve aunt/uncle → niece/nephew based on related person's gender
- Inverse mapping: Aunt can now correctly map to either Niece or Nephew depending on the related person's gender

### Changed
- Gender resolution: When source type is gender-dependent (aunt/uncle), inverse is resolved to target group (niece/nephew) based on related person's gender

## [1.0.73] - 2024-12-19

### Added
- Default relationship configurations: System now ships with pre-configured inverse mappings and gender-dependent settings
- Restore defaults button: Added "Restore Defaults" button in Relationship Types settings page
- REST API endpoint: `/stadion/v1/relationship-types/restore-defaults` to restore default configurations
- Automatic setup: Default configurations are applied when relationship types are first created

### Changed
- Relationship type initialization: Now automatically sets up inverse mappings and gender-dependent groups on first run

## [1.0.72] - 2024-12-19

### Added
- Gender-dependent relationship types: Support for gender-aware inverse relationship resolution
- ACF fields: Added `is_gender_dependent` and `gender_dependent_group` fields to relationship types
- Automatic gender resolution: System automatically resolves gender-dependent types (e.g., aunt/uncle → niece/nephew) based on related person's gender
- Helper functions: `resolve_gender_dependent_inverse()`, `get_types_in_gender_group()`, `infer_gender_type_from_group()`

### Changed
- Inverse relationship sync: Now checks for gender-dependent types and resolves to correct specific type
- Relationship type configuration: Can now mark types as gender-dependent and assign them to groups

## [1.0.71] - 2024-12-19

### Added
- Documentation: Created comprehensive docs/ folder with relationship system documentation
- docs/relationships.md: Complete guide to how bidirectional relationships work
- docs/relationship-types.md: Configuration guide for relationship types and inverse mappings
- docs/architecture.md: Technical architecture documentation with extension points
- README.md: Added links to documentation

## [1.0.70] - 2024-12-19

### Changed
- Relationship Types page: Inverse relationship type selector is now searchable dropdown
- Relationship Types page: Inverse selector includes the type itself (e.g., "Acquaintance" can have "Acquaintance" as inverse)
- Relationship Types page: Improved UX with searchable dropdown similar to person selector

## [1.0.69] - 2024-12-19

### Added
- Settings: New Relationship Types management page accessible from Settings
- Relationship Types page: Edit relationship type names and inverse relationships from the frontend
- Relationship Types page: Create new relationship types with inverse mappings
- Relationship Types page: Delete relationship types
- REST API: Added ACF field support for relationship_type taxonomy terms

## [1.0.68] - 2024-12-19

### Changed
- Inverse relationships: Moved inverse relationship mappings from hardcoded PHP array to ACF taxonomy field
- Relationship types now have an "Inverse Relationship Type" field that can be configured in WordPress admin
- Removed hardcoded `$inverse_mappings` array from `STADION_Inverse_Relationships` class

## [1.0.67] - 2024-12-19

### Fixed
- Monica import: Gender field is now properly imported from Monica CRM SQL exports (maps M→male, F→female, O→prefer_not_to_say)

## [1.0.66] - 2024-12-19

### Added
- Person form: Added gender field with dropdown selection (Male, Female, Non-binary, Other, Prefer not to say)
- Person detail: Gender symbol (♂/♀/⚧) now displays left of age
- Relationships: Automatic bidirectional relationship synchronization - when a relationship is created/updated/deleted from person A to person B, the inverse relationship is automatically created/updated/deleted from B to A
- Inverse relationship mappings for all relationship types (e.g., Parent ↔ Child, Boss ↔ Subordinate, Spouse ↔ Spouse)

### Changed
- Person form: Gender field changed from text input to select dropdown
- Cache invalidation: Related person cache is now invalidated when relationships are updated, ensuring UI reflects inverse relationships immediately

## [1.0.65] - 2024-12-19

### Changed
- Person detail: Contact information section is now hidden for deceased people

## [1.0.64] - 2024-12-19

### Added
- People list: Deceased people now show † next to their name

## [1.0.63] - 2024-12-19

### Added
- Person detail: For deceased people, shows † next to their name
- Person detail: Displays death date and age at death instead of current age for deceased people
- Person detail: "Died" date type now displays † as its icon

## [1.0.62] - 2024-12-19

### Changed
- Teams list: Teams are now sorted alphabetically by name

## [1.0.61] - 2024-12-19

### Changed
- Person detail: Relationships are now sorted by age (descending - oldest first)

## [1.0.60] - 2024-12-19

### Fixed
- Team form: Fixed `getTeam` API method to accept params (including `_embed`), ensuring logos display on team list and in work history
- Team form: Added explicit query refetching after logo upload to ensure embedded media data is refreshed

## [1.0.59] - 2024-12-19

### Fixed
- Team form: Created custom REST endpoint to set team logo using WordPress `set_post_thumbnail()` function, ensuring featured image is properly saved

## [1.0.58] - 2024-12-19

### Fixed
- Team form: Fixed logo upload payload structure - featured_media now properly saved

## [1.0.57] - 2024-12-19

### Fixed
- Team detail: Logo now properly loads and displays on team detail page

## [1.0.56] - 2024-12-19

### Added
- Team form: Logo upload functionality when editing a team
- Person detail: Team logos now displayed in work history section instead of generic icon

## [1.0.55] - 2024-12-19

### Changed
- Dates overview: Today's dates now display in green (matching dashboard reminders)
- Dates overview: Removed days-until indicators, showing only the date number

## [1.0.54] - 2024-12-19

### Added
- Favicon: Added sparkles favicon (SVG) to match the app branding

## [1.0.53] - 2024-12-19

### Changed
- Layout: Changed sidebar logo icon from Home to Sparkles

## [1.0.52] - 2024-12-19

### Changed
- Rebranded application from "Oikos" to "Koinastra"
- Centralized app name configuration in `src/constants/app.js` for easy future changes
- All app name references now use the centralized `APP_NAME` constant

## [1.0.51] - 2024-12-19

### Fixed
- Date form: Prevented form reset from clearing date type selection after user selects a value
- Date form: Added form initialization tracking to prevent unwanted resets

## [1.0.50] - 2024-12-19

### Fixed
- Date form: Date type select now properly updates when selecting a value

## [1.0.49] - 2024-12-19

### Changed
- Person detail: Important dates are now ordered by date ascending (earliest first)

## [1.0.48] - 2024-12-19

### Added
- Layout: Added home icon next to Oikos title in the sidebar

## [1.0.47] - 2024-12-19

### Changed
- Design: Switched color palette from blue to warmer amber tones throughout the application

## [1.0.46] - 2024-12-19

### Changed
- Rebranded application from "Personal CRM" to "Oikos" across all user-facing text
- Updated logo, welcome message, document titles, and email reminders

## [1.0.45] - 2024-12-19

### Added
- User menu: Shows current user's avatar with dropdown menu
- User menu: "Edit profile" link to WordPress user profile page
- User menu: "WordPress admin" link (only visible for admin users)
- Backend: New REST endpoint `/stadion/v1/user/me` to get current user information

## [1.0.44] - 2024-12-19

### Changed
- Dashboard: Upcoming reminders happening today now display in green instead of red

## [1.0.43] - 2024-12-19

### Added
- Person form: Email field when creating a new person
- Person form: Automatically fetches and sets Gravatar profile photo if email has a Gravatar
- Backend: New REST endpoint to sideload Gravatar images for people

## [1.0.42] - 2024-12-19

### Fixed
- Date form: People selector now shows all people, not just the first 100
- Date form: Uses pagination-aware usePeople hook instead of limited direct query

## [1.0.41] - 2024-12-19

### Added
- Person detail: Click on person's photo to upload/change their picture
- Person detail: Photo upload with file validation (image files only, max 5MB)
- Person detail: Loading indicator during photo upload

## [1.0.40] - 2024-12-19

### Added
- Work history: Can now set both "current job" and a future end date simultaneously
- Work history: Daily cron job automatically sets is_current=false when end_date passes

### Changed
- Work history form: End date field is no longer disabled when "current job" is checked
- Work history form: End date can be set even for current positions to schedule automatic transition

## [1.0.39] - 2024-12-19

### Fixed
- Team detail: Employees with end dates in the future are now correctly shown as current employees, not former

## [1.0.38] - 2024-12-19

### Fixed
- Person form: Now properly sets post title when creating or updating a person
- Person form: Data storage now works correctly

### Added
- Person form: Birthday field when creating a new person
- Person form: Automatically creates an important_date post for birthday when provided

## [1.0.37] - 2024-12-19

### Added
- People list: Sort controls to sort by first name or last name, ascending or descending
- People list: Default sorting is now first name ascending (changed from last name)

### Changed
- People list: Sorting now uses a dropdown selector and order toggle button

## [1.0.36] - 2024-12-19

### Changed
- Relationship form: Related Person field now uses a searchable dropdown instead of a simple select
- Relationship form: People are sorted alphabetically by first name, ascending
- Relationship form: Search works by name, first name, or last name

## [1.0.35] - 2024-12-19

### Changed
- Person detail: Work history is now sorted by start date descending (most recent first)
- Person detail: Current positions appear at the top of the work history list

## [1.0.34] - 2024-12-19

### Fixed
- Person detail: HTML entities in relationship names and labels are now properly decoded and displayed

## [1.0.33] - 2024-12-19

### Fixed
- Relationship form: Relationship type dropdown now correctly pre-selects the current relationship type when editing

## [1.0.32] - 2024-12-19

### Fixed
- Relationship form: Relationship type dropdown now shows all available types (increased limit from 10 to 100)

## [1.0.31] - 2024-12-19

### Fixed
- Person detail: Edit and Add Relationship buttons now navigate to dedicated relationship form instead of person edit form
- Person detail: Relationship form allows editing individual relationships without editing the entire person

### Added
- Person detail: New RelationshipForm component for adding and editing relationships independently

## [1.0.30] - 2024-12-19

### Added
- Person detail: Add and remove labels directly from the person detail page
- Person detail: Remove button (X) appears on hover for each label
- Person detail: Dropdown selector to add new labels from available labels

## [1.0.29] - 2024-12-19

### Changed
- Person detail: LinkedIn contact types now show LinkedIn icon instead of label text
- Person detail: LinkedIn icon styled with brand color (blue-600)

## [1.0.28] - 2024-12-19

### Changed
- Team detail: Removed person-level access control restriction - if you can view a team, you can see all its employees
- Team detail: Now checks team access instead of filtering employees by person-level access permissions

## [1.0.27] - 2024-12-19

### Fixed
- Team detail: Fixed team people query by removing unreliable meta_query with ACF repeater fields and filtering in PHP instead
- Team detail: Now properly finds people by checking work_history using ACF's get_field() function which handles repeater fields correctly

## [1.0.26] - 2024-12-19

### Fixed
- Team detail: Fixed bug where employees weren't showing due to missing admin check in access control filtering
- Team detail: Fixed type comparison issue between team IDs (string vs integer) that prevented matching work history entries

## [1.0.25] - 2024-12-19

### Changed
- Performance: Optimized team people query to apply access control filtering early, reducing query scope
- Performance: People list now batches team fetches into a single API call instead of individual queries (fixes N+1 query problem)
- Performance: Made `get_accessible_post_ids` method public in access control class for reuse in optimized queries

## [1.0.24] - 2024-12-19

### Changed
- Team detail: Reorganized employee display into two separate sections: "Current Employees" and "Former Employees"
- Team detail: Each section now has its own card for better visual separation
- Team detail: Improved empty states for both current and former employee sections

## [1.0.23] - 2024-12-19

### Changed
- People list: Now fetches all people using pagination (removed 100 person limit)
- People list: Added lazy loading for person thumbnails to improve page load performance
- Dashboard: Added lazy loading for person thumbnails in Recent People and Reminders sections
- Dates list: Added lazy loading for person thumbnails
- Team detail: Added lazy loading for employee thumbnails

## [1.0.22] - 2024-12-19

### Added
- People list: Filter functionality with favorites toggle and label multi-select
- People list: Active filter chips showing applied filters with quick remove options
- People list: Filter button shows badge count when filters are active
- People list: "No results" state when filters don't match any people

## [1.0.21] - 2024-12-19

### Removed
- Removed duplicate search box from People list page (global search in top bar is sufficient)

## [1.0.20] - 2024-12-19

### Changed
- Dashboard: Upcoming reminders now link to the related person's detail page when clicked
- Dashboard: Increased reminder photo size from 6x6 to 10x10 pixels for better visibility

## [1.0.19] - 2024-12-19

### Added
- Added WhatsApp button for mobile phone numbers in contact details (opens WhatsApp with the phone number)

## [1.0.18] - 2024-12-19

### Fixed
- Fixed tel: links for phone numbers: now properly removes Unicode marks and all non-digit characters (except + at the start)

## [1.0.17] - 2024-12-19

### Fixed
- Changed ACF repeater fields to use empty arrays `[]` instead of `null` when empty, as WordPress REST API requires arrays

## [1.0.16] - 2024-12-19

### Fixed
- Fixed error when saving contact details: ACF repeater fields (work_history, relationships) are now properly formatted as arrays or null

## [1.0.15] - 2024-12-19

### Fixed
- Person names now properly decode HTML entities (e.g., &#8211; displays as a normal dash)

## [1.0.14] - 2024-12-19

### Changed
- People list: People are now sorted alphabetically by last name (with first name as secondary sort)
- People list: Team name is now displayed below each person's name (shows current team or most recent)

## [1.0.13] - 2024-12-19

### Added
- Contact information: Website and URL type contacts (LinkedIn, Twitter, Instagram, Facebook) are now clickable links opening in a new tab
- Contact information: Address type contacts now link to Google Maps opening in a new tab

## [1.0.12] - 2024-12-19

### Fixed
- Date type dropdown now fetches all date types (increased limit from 10 to 100)

## [1.0.11] - 2024-12-19

### Changed
- Date type dropdown now properly fetches and displays all date types from the Date Types taxonomy
- Date types are sorted alphabetically for better user experience

## [1.0.10] - 2024-12-19

### Changed
- Removed "Anniversary" date type, replaced with "Wedding" date type
- Wedding date type now auto-generates title as "Wedding of <person 1> & <person 2>" format
- Updated auto-title generation logic to handle wedding dates with proper format

## [1.0.9] - 2024-12-19

### Changed
- Person detail page: Contact detail edit button now navigates to dedicated contact detail edit form instead of person edit screen
- Person detail page: "Add contact detail" button now navigates to dedicated contact detail form instead of person edit screen

### Added
- Added dedicated Contact Detail form page for adding and editing individual contact details

## [1.0.8] - 2024-12-19

### Changed
- Person detail page: Work history edit button now navigates to dedicated work history edit form instead of person edit screen
- Added dedicated Work History form page for editing individual work history items

## [1.0.7] - 2024-12-19

### Changed
- Person detail page: Work History section now always visible (even when empty)
- Person detail page: Team names now displayed instead of "View Team" link in work history
- Person detail page: Added "Add Work History" button to Work History section

### Added
- Person detail page: Added edit button for each work history item
- Person detail page: Added delete button for each work history item

## [1.0.6] - 2024-12-19

### Added
- Person detail page: Added delete button for each contact detail
- Person detail page: Added delete button for each important date
- Person detail page: Added delete button for each relationship
- Person detail page: Added delete button for each note/timeline item
- Person detail page: Added edit button for each relationship

## [1.0.5] - 2024-12-19

### Changed
- Person detail page: Show person labels underneath age in the main card
- Person detail page: Removed birthday from main card, now appears as first date in Important Dates card
- Person detail page: Contact Information card now always visible with "Add contact detail" button
- Person detail page: Email fields are now clickable mailto: links
- Person detail page: Phone numbers are now clickable tel: links (spaces and dashes removed)
- Person detail page: Added edit button for each contact field
- Person detail page: Important Dates card now always visible with "Add Important Date" button
- Person detail page: Relationships card now always visible with "Add Relationship" button

## [1.0.4] - 2024-12-19

### Fixed
- Fixed 404 errors when navigating to individual person, team, or date pages
- WordPress now properly serves index.php for all app routes, allowing React Router to handle routing
- Disabled rewrite rules for custom post types to prevent URL conflicts
- Fixed React error #310 ("Rendered more hooks than during the previous render") by removing `useParams()` from `useRouteTitle` hook
- `useRouteTitle` now extracts route IDs from pathname instead of using `useParams()`, ensuring consistent hook calls regardless of route context
- Fixed React error #310 on Person Detail pages by moving `useDocumentTitle` hook calls before early returns in all detail and form components
- All hooks are now called consistently on every render, even during loading/error states
- Fixed "Rendered more hooks than during the previous render" error by ensuring useSearch always receives a string (never null)
- Fixed minified React error caused by improper handling of empty search queries
- Added safety checks for search results to prevent property access errors
- Note: After updating, you may need to flush rewrite rules by going to Settings > Permalinks and clicking "Save Changes"

## [1.0.3] - 2024-12-19

### Fixed
- Search form now works and displays results in a dropdown
- Search form is now center-aligned in the header
- User menu placeholder is now right-aligned

## [1.0.2] - 2024-12-19

### Fixed
- Page title now updates dynamically based on current route instead of always showing "Page not found"
- Document title now shows appropriate page names (Dashboard, People, Teams, etc.) and entity names for detail pages

## [1.0.1] - 2024-12-19

### Changed
- Important Dates overview now uses masonry layout for date blocks
- Increased month heading size on Important Dates overview screen

