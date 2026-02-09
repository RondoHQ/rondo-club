# Rondo Club Multi-User Transformation

## What This Is

A React-powered WordPress theme for sports club management. Administrators manage members, teams, fees, and important dates through a modern branded SPA with Tailwind CSS v4, OKLCH color tokens, and PWA support.

## Core Value

Club administrators can manage their members, teams, and important dates through a single integrated system.

## Requirements

### Validated

<!-- Existing functionality from codebase that must continue working -->

- Personal CRM with people, teams, dates management — existing
- WordPress theme with React SPA frontend — existing
- REST API communication (wp/v2 + rondo/v1 namespaces) — existing
- Slack integration for notifications (OAuth, webhooks) — existing
- CardDAV sync support via Sabre/DAV — existing
- Import from Google Contacts, Monica CRM, vCard — existing
- Export to vCard and Google CSV — existing
- User-scoped data isolation — existing (will be extended)
- Email and Slack notification channels — existing
- iCal feed generation — existing

**v1.0 Tech Debt Cleanup (shipped 2026-01-13):**
- Split `class-rest-api.php` into domain-specific classes — v1.0
- Remove 48 `console.error()` calls from production code — v1.0
- Create `.env.example` documenting required environment variables — v1.0
- Consolidate duplicated `decodeHtml()` logic — v1.0
- Encrypt Slack tokens with sodium — v1.0
- Add server-side XSS protection with wp_kses — v1.0
- Validate Slack webhook URLs (whitelist hooks.slack.com) — v1.0

**v2.0 Multi-User (shipped 2026-01-13):**
- Workspace CPT with role-based membership (Admin/Member/Viewer) — v2.0
- Contact visibility system (private/workspace/shared) — v2.0
- `workspace_access` taxonomy for post-to-workspace assignment — v2.0
- Workspace invitation system with 7-day expiring tokens — v2.0
- ShareModal and VisibilitySelector React components — v2.0
- WorkspacesList, WorkspaceDetail, WorkspaceSettings pages — v2.0
- @mentions in notes with MentionInput component — v2.0
- Mention notification preferences (immediate/digest/never) — v2.0
- Workspace iCal calendar feeds with token auth — v2.0
- Workspace activity digest in daily reminders — v2.0
- WP-CLI migration command `wp prm multiuser migrate` — v2.0
- Multi-user documentation in `docs/multi-user.md` — v2.0

**v2.1 Bulk Operations (shipped 2026-01-13):**
- Card/list view toggle for people screen — v2.1
- Tabular list view with Name, Team, Workspace columns — v2.1
- Checkbox multi-selection with Set-based state — v2.1
- Bulk update REST endpoint `/rondo/v1/people/bulk-update` — v2.1
- Bulk visibility change modal (Private/Workspace) — v2.1
- Bulk workspace assignment modal — v2.1

**v2.2 List View Polish (shipped 2026-01-13):**
- Split Name into First Name / Last Name columns — v2.2
- Labels column with styled pills — v2.2
- SortableHeader component with click sorting — v2.2
- Sticky table header and selection toolbar — v2.2
- BulkTeamModal with search and clear — v2.2
- BulkLabelsModal with add/remove mode — v2.2

**v2.3 List View Unification (shipped 2026-01-13):**
- Removed card view from People, list-only UI — v2.3
- Dedicated image column in People list — v2.3
- Teams list view with sortable columns — v2.3
- Teams selection and bulk action infrastructure — v2.3
- Bulk visibility, workspace, labels for Teams — v2.3
- Full parity between People and Teams list views — v2.3

**v2.5 Performance (shipped 2026-01-13):**
- Vite manual chunks for vendor (React) and utils (date-fns, etc.) — v2.5
- Route-based lazy loading with React.lazy + Suspense — v2.5
- Heavy library lazy loading (vis-network, TipTap) — v2.5
- Initial bundle reduced from 1,646 KB to 435 KB (73% reduction) — v2.5

**v3.0 Testing Infrastructure (shipped 2026-01-13):**
- PHPUnit via wp-browser (Codeception) with WPLoader — v3.0
- 120 tests covering access control, REST API, search, relationships — v3.0
- Separate test database (stadion_test) for isolation — v3.0

**v3.1 Pending Response Tracking (shipped 2026-01-14):**
- stadion_todo custom post type (migrated from comments) — v3.1
- WordPress post statuses (stadion_open, stadion_awaiting, stadion_completed) — v3.1
- Awaiting response tracking with timestamps and aging indicators — v3.1
- Filter UI (Open/Awaiting/Completed tabs) across all views — v3.1
- WP-CLI migration: `wp prm migrate-todos` — v3.1
- 25 PHPUnit tests for todo functionality (145 total) — v3.1

**v3.2 Person Profile Polish (shipped 2026-01-14):**
- Current position (job title + team) display in person header — v3.2
- Persistent todos sidebar on PersonDetail page (visible across all tabs) — v3.2
- Mobile todos access via floating action button + slide-up panel — v3.2
- 3-column grid layout for equal-width content columns — v3.2
- Timeline endpoint updated for todo post statuses — v3.2

**v3.3 Todo Enhancement (shipped 2026-01-14):**
- WYSIWYG notes ACF field for todo descriptions — v3.3
- Multi-person todo linking (related_persons multi-value field) — v3.3
- TodoModal with collapsible notes editor and multi-person selector — v3.3
- Stacked avatar display in TodosList and PersonDetail sidebar — v3.3
- Cross-person todo visibility with "Also:" indicator — v3.3
- WP-CLI migration: `wp prm todos migrate-persons` — v3.3

**v3.4 UI Polish (shipped 2026-01-14):**
- Clickable website links in Teams list — v3.4
- Labels column removed from Teams list — v3.4
- Simplified Slack contact display (label only, clickable) — v3.4
- Build-time based refresh detection (manifest.json mtime) — v3.4
- Awaiting todos count in dashboard stats (5-column grid) — v3.4
- Full-width Timeline panel on person profile desktop — v3.4
- Labels CRUD interface at /settings/labels (tabbed UI) — v3.4

**v3.5 Bug Fixes & Polish (shipped 2026-01-14):**
- X (Twitter) logo color updated to black — v3.5
- Dashboard AwaitingTodoCard rounded corners — v3.5
- Search prioritizes first name matches (scoring system) — v3.5
- Important date titles persist user edits (custom_label detection) — v3.5
- Dashboard cache invalidates on todo mutations from PersonDetail — v3.5
- custom_label included in important date API response — v3.5

**v3.6 Quick Wins & Performance (shipped 2026-01-14):**
- Awaiting checkbox toggle in Dashboard for quick completion — v3.6
- Email addresses auto-lowercased on save via ACF filter — v3.6
- Modal lazy loading with React.lazy + Suspense — v3.6
- Main bundle reduced from 460 KB to 50 KB (89% reduction) — v3.6
- Initial page load reduced from ~767 KB to ~400 KB — v3.6

**v4.0 Calendar Integration (shipped 2026-01-15):**
- Google Calendar OAuth2 integration with google/apiclient library — v4.0
- CalDAV provider for iCloud, Fastmail, Nextcloud, generic servers — v4.0
- calendar_event CPT for caching synced events — v4.0
- RONDO_Credential_Encryption class for secure OAuth token storage — v4.0
- Email-first contact matching algorithm with confidence scores — v4.0
- Calendar settings UI with connection management (/settings/calendars) — v4.0
- Person profile Meetings tab with upcoming/past meetings — v4.0
- Log as Activity functionality for past meetings — v4.0
- Background sync via WP-Cron every 15 minutes — v4.0
- Today's Meetings dashboard widget — v4.0
- WP-CLI: `wp prm calendar sync/status/auto-log` — v4.0

**v4.1 Bug Fixes & Polish (shipped 2026-01-15):**
- Dark mode contrast fixes for CardDAV connection details and search modal — v4.1
- Two-step rsync deploy procedure preventing MIME type errors from stale artifacts — v4.1
- Dashboard restructured to 3-row layout (Stats | Activity | Favorites) — v4.1
- Timezone-aware meeting times using ISO 8601 format — v4.1
- Dynamic favicon that updates when accent color changes — v4.1

**v4.2 Settings & Stability (shipped 2026-01-15):**
- DOM modification prevention (translate="no", Google notranslate meta tag) — v4.2
- DomErrorBoundary for graceful recovery from browser extension DOM conflicts — v4.2
- Settings restructure with Connections tab (Calendars/CardDAV/Slack subtabs) — v4.2
- Automatic calendar event re-matching when person emails change — v4.2
- WP-CLI command: `wp prm calendar rematch --user-id=ID` — v4.2

**v4.3 Performance & Documentation (shipped 2026-01-16):**
- React frontend validated against 40+ performance rules (already optimized, no changes needed) — v4.3
- Complete wp-config.php configuration documentation in README.md — v4.3
- WPCS 3.3 installed via Composer with phpcs.xml.dist configuration — v4.3
- PHPCS violations reduced from 49,450 to 46 (99.9% reduction) — v4.3
- Composer lint scripts (`composer lint`, `composer lint:fix`) — v4.3
- Short array syntax enforced across entire codebase ([] instead of array()) — v4.3
- Yoda conditions disabled for improved readability — v4.3

**v4.4 Code Team (shipped 2026-01-16):**
- Comprehensive codebase audit identifying 41 classes across 39 PHP files — v4.4
- Split notification channel classes into separate files (one-class-per-file compliance) — v4.4
- PSR-4 namespaces added to 38 PHP classes across 9 namespace groups — v4.4
- Composer autoloading with classmap for includes/ directory — v4.4
- 38 backward-compatible class aliases (RONDO_* → Rondo\*) — v4.4
- Manual stadion_autoloader() function removed (52 lines) — v4.4
- PHPCS Generic.Files.OneClassPerFile rule enabled — v4.4

**v4.5 Calendar Sync Control (shipped 2026-01-16):**
- Per-connection sync_to_days setting (1 week to 90 days forward) — v4.5
- Per-connection sync_frequency setting (15 min to daily) — v4.5
- Background sync respects per-connection frequency settings — v4.5
- Calendar list API for Google and CalDAV providers — v4.5
- Calendar selector UI in EditConnectionModal — v4.5
- Connection card displays selected calendar name — v4.5
- Sync lock to prevent duplicate events from race conditions — v4.5
- User context in calendar matcher for CLI/cron access control — v4.5

**v4.7 Dark Mode & Activity Polish (shipped 2026-01-17):**
- Dark mode support for WorkHistoryEditModal and AddressEditModal — v4.7
- Settings subtab button contrast improved (dark:text-gray-300) — v4.7
- TimelineView dark mode with 13 variants for activity labels — v4.7
- ImportantDateModal people badge contrast improved (dark:text-accent-200) — v4.7
- QuickActivityModal selected button solid background pattern (dark:bg-accent-800) — v4.7
- Dinner and Zoom activity types added with proper icons — v4.7
- Phone call renamed to Phone (preserved call ID for existing data) — v4.7
- Topbar z-index fixed to z-30 (above selection toolbar z-20) — v4.7
- Person header spacing fixed (" at " with trailing space) — v4.7

**v4.8 Meeting Enhancements (shipped 2026-01-17):**
- Meeting detail modal with title, time, location, description, attendees — v4.8
- Meeting notes section with auto-save for meeting prep — v4.8
- Add person from meeting attendees with name extraction — v4.8
- Date navigation with prev/next/today buttons — v4.8
- Add email to existing person choice popup — v4.8
- Fixed HTML entity encoding in calendar event titles — v4.8

**v4.9 Dashboard & Calendar Polish (shipped 2026-01-17):**
- Fixed height dashboard widgets (280px) with internal scrolling — v4.9
- 6 skeleton widgets during loading for layout stability — v4.9
- placeholderData pattern prevents Events widget layout jump during date navigation — v4.9
- Multi-calendar selection per Google Calendar connection via checkbox UI — v4.9
- get_calendar_ids() static helper for backward-compatible calendar format normalization — v4.9
- Two-column EditConnectionModal layout (calendars left, sync settings right) — v4.9
- Connection card shows "N calendars selected" count — v4.9

**v5.0 Google Contacts Sync (shipped 2026-01-18):**
- Google Contacts OAuth with incremental scope addition — v5.0
- Import from Google with field mapping, duplicate detection, photo sideloading — v5.0
- Export to Google with reverse field mapping and etag conflict handling — v5.0
- Delta sync using Google syncToken for efficient change detection — v5.0
- Conflict resolution with Rondo Club-wins strategy and activity logging — v5.0
- Settings UI with sync history viewer — v5.0
- "View in Google Contacts" link on person profiles — v5.0
- WP-CLI commands: sync, sync --full, status, conflicts, unlink-all — v5.0

**v5.0.1 Meeting Card Polish (shipped 2026-01-18):**
- 24h time format for meeting times — v5.0.1
- Past meetings dimmed with 50% opacity — v5.0.1
- Current meetings highlighted with accent ring — v5.0.1
- Calendar name displayed in meeting cards — v5.0.1
- WP-CLI command for HTML entity cleanup — v5.0.1

**v6.0 Custom Fields (shipped 2026-01-21):**
- ACF-native field group management (no custom tables) — v6.0
- 14 field types: Text, Textarea, Number, Email, URL, Date, Select, Checkbox, True/False, Image, File, Link, Color Picker, Relationship — v6.0
- Settings subtab for custom field management (People/Org toggle) — v6.0
- Dedicated "Custom Fields" section on Person/Team detail views — v6.0
- Custom field values included in global search — v6.0
- Admin-only field management, global visibility — v6.0
- Custom field columns in list views with configurable show/hide — v6.0
- Drag-and-drop field reordering, required/unique validation — v6.0

**v6.1 Feedback System (shipped 2026-01-21):**
- stadion_feedback custom post type with ACF fields (type, status, priority, context) — v6.1
- REST API endpoints under rondo/v1/feedback with CRUD operations — v6.1
- Application password authentication support for API access — v6.1
- Frontend feedback page with list view, detail view, and submission form — v6.1
- Admin management UI in Rondo Club for status changes and ordering — v6.1
- Settings UI for managing application passwords — v6.1
- System info capture (browser, version, current page) on opt-in — v6.1

**v7.0 Dutch Localization (shipped 2026-01-25):**
- Dutch date formatting foundation with centralized dateFormat.js utility — v7.0
- Complete navigation translation (Leden, Teams, Commissies, Datums, Taken, Instellingen) — v7.0
- Dashboard fully localized with Dutch stat labels, widget titles, empty states — v7.0
- Entity pages translated: Leden, Teams, Commissies with forms and modals — v7.0
- Settings pages completed: all 6 tabs (Weergave, Koppelingen, Meldingen, Gegevens, Beheer, Info) — v7.0
- Global UI elements: buttons, dialogs, activity types, contact types, rich text editor — v7.0
- 36 localization requirements delivered across 8 phases (99-106) — v7.0

**v8.0 PWA Enhancement (shipped 2026-01-28):**
- Web App Manifest with vite-plugin-pwa, icons, theme color, standalone display mode — v8.0
- Service worker for asset caching and offline support with cached API data — v8.0
- iOS meta tags, Apple Touch icons, and safe area CSS for notched devices — v8.0
- Pull-to-refresh gesture on all list and detail views — v8.0
- iOS overscroll prevention in standalone mode — v8.0
- Smart Android install prompt after user engagement — v8.0
- iOS install instructions modal for Add to Home Screen — v8.0
- Update notification with refresh button and hourly checking — v8.0
- Dutch localization of all PWA notifications and prompts — v8.0
- Lighthouse PWA score 90+ verified on real devices — v8.0

**v9.0 People List Performance & Customization (shipped 2026-01-29):**
- Server-side pagination with `/rondo/v1/people/filtered` endpoint (100 per page) — v9.0
- Server-side filtering by labels, ownership, modified date, birth year — v9.0
- Server-side sorting by name, modified, custom fields with type-appropriate ORDER BY — v9.0
- Birthdate denormalized to `_birthdate` meta for fast birth year filtering — v9.0
- Custom $wpdb endpoint with JOINs for single-query data fetch — v9.0
- Per-user column preferences (visibility, order, width) stored in user_meta — v9.0
- Column customization UI with drag-drop reordering and resize handles — v9.0
- "Tonen als kolom in lijstweergave" removed from custom field settings — v9.0
- 14x data transfer reduction (1400+ → 100 per page) — v9.0

**v10.0 Read-Only UI for Sportlink Data (shipped 2026-01-29):**
- Remove "Verwijderen" (delete) button from PersonDetail — v10.0
- Remove "Voeg adres toe" (add address) button from PersonDetail — v10.0
- Remove "Functie toevoegen" (add function) button from work history — v10.0
- Make work history items non-editable in UI — v10.0
- Add `editable_in_ui` setting to custom fields — v10.0
- Disable creating new teams in Rondo Club UI — v10.0
- Disable creating new commissies in Rondo Club UI — v10.0
- Keep all edit/add/remove functionality available in REST API — v10.0

**v12.0 Membership Fees (shipped 2026-02-01):**
- Contributie section in sidebar below Leden, above VOG — v12.0
- Age-based fee calculation (Mini, Pupil, Junior, Senior) with configurable amounts — v12.0
- Recreational/Donateur flat fees with configurable amounts — v12.0
- Family discount calculation (25% 2nd child, 50% 3rd+ at same address) — v12.0
- Pro-rata calculation based on lid-sinds field (quarterly tiers) — v12.0
- Fee caching with automatic invalidation on relevant field changes — v12.0
- Nikki integration showing contributions vs calculated fees — v12.0
- Google Sheets export with Dutch formatting and auto-open — v12.0

**v12.1 Contributie Forecast (shipped 2026-02-03):**
- Backend forecast calculation with get_next_season_key() method — v12.1
- API forecast parameter for next season projections (100% pro-rata) — v12.1
- Family discounts applied using existing address grouping logic — v12.1
- Season selector dropdown with "(huidig)" and "(prognose)" labels — v12.1
- Conditional column rendering hiding Nikki/Saldo in forecast mode — v12.1
- Forecast indicator badge with TrendingUp icon — v12.1
- Google Sheets export with "(Prognose)" title suffix and 8-column layout — v12.1

**v13.0 Discipline Cases (shipped 2026-02-03):**
- Discipline case CPT with 11 ACF fields (dossier-id, person, match/charges/sanctions/fees) — v13.0
- Shared `seizoen` taxonomy with current season support — v13.0
- `fairplay` capability for access control (admins auto-assigned) — v13.0
- Discipline cases list page with season filter (fairplay users only) — v13.0
- Person detail Tuchtzaken tab showing linked cases — v13.0
- Read-only UI consistent with Sportlink data model — v13.0

**v14.0 Performance Optimization (shipped 2026-02-04):**
- Eliminated duplicate API calls via createBrowserRouter migration and ES module fix — v14.0
- Modal lazy loading for QuickActivityModal, TodoModal, GlobalTodoModal — v14.0
- Centralized useCurrentUser hook with 5-minute staleTime caching — v14.0
- VOG count caching preventing refetch on every navigation — v14.0
- Backend todo counts using wp_count_posts() instead of get_posts() — v14.0

**v15.0 Personal Tasks (shipped 2026-02-04):**
- User isolation for tasks via post_author filtering in WP_Query and REST API — v15.0
- Dashboard todo counts filtered by current user — v15.0
- WP-CLI command for task ownership verification — v15.0
- Tasks navigation accessible to all users (no capability gating) — v15.0
- Personal tasks info messages in UI with Dutch text — v15.0

**v16.0 Infix / Tussenvoegsel (shipped 2026-02-05):**
- ACF infix text field between first_name and last_name (read-only in UI) — v16.0
- Auto-title generation uses array_filter + implode pattern for safe concatenation — v16.0
- REST API filtered endpoint includes infix JOIN and response field — v16.0
- Global search includes infix with score 50 — v16.0
- vCard/Google Contacts/CardDAV import/export maps infix to middle name — v16.0
- formatPersonName() utility for consistent name formatting — v16.0

**v17.0 De-AWC (shipped 2026-02-05):**
- ClubConfig service class with Options API storage for club_name, accent_color, freescout_url — v17.0
- REST API endpoint /rondo/v1/config (admin write, all-users read) — v17.0
- Admin-only club configuration UI in Settings with react-colorful color picker — v17.0
- Renamed awc accent color to club throughout codebase (Tailwind, CSS, React, PHP) — v17.0
- Dynamic login page and PWA theme-color from club configuration — v17.0
- FreeScout URL externalized to club config (hidden when not configured) — v17.0
- Zero club-specific hardcoded references in source code — v17.0

**v19.0 Birthdate Simplification (shipped 2026-02-06):**
- Birthdate ACF field on person records (date picker, read-only in UI) — v19.0
- Person header displays birthdate after age ("43 jaar (6 feb 1982)") — v19.0
- Dashboard birthday widget queries person birthdate meta directly — v19.0
- Important Dates CPT, date_type taxonomy completely removed — v19.0
- Datums navigation, DatesList page, ImportantDateModal removed — v19.0
- Reminders and iCal systems generate from person birthdate field — v19.0
- Data model reduced from 3 CPTs to 2 (person, team) — v19.0

**v20.0 Configurable Roles (shipped 2026-02-08):**
- Admin can configure which job titles count as "player roles" (options from actual data) — v20.0
- Admin can configure which roles are excluded/honorary (options from actual data) — v20.0
- Age group filter options derived dynamically from the database — v20.0
- Member type filter options derived dynamically from the database — v20.0
- Generic filter infrastructure via get_dynamic_filter_config() — v20.0
- Team detail player/staff split driven by configured role settings — v20.0
- Default role fallbacks removed from rondo-sync (skip-and-warn pattern) — v20.0

**v21.0 Per-Season Fee Categories (shipped 2026-02-09):**
- ✓ Fee categories (slug, label, amount, age classes, youth flag, sort order) configurable per season — v21.0
- ✓ Admin can manage fee categories via Settings UI with add/edit/remove/reorder — v21.0
- ✓ Admin can configure Sportlink age class-to-category mappings per season — v21.0
- ✓ New seasons auto-copy categories from the previous season — v21.0
- ✓ Config-driven fee calculation replaces hardcoded parse_age_group() — v21.0
- ✓ Frontend receives category metadata from API (no hardcoded FEE_CATEGORIES) — v21.0
- ✓ Category order, youth_categories, and VALID_TYPES all derived from season config — v21.0
- ✓ Family discount percentages configurable per season — v21.0
- ✓ Team and werkfunctie matching rules configurable per category — v21.0

**v22.0 Design Refresh (shipped 2026-02-09):**
- ✓ Tailwind CSS v4 with CSS-first @theme configuration and OKLCH color tokens — v22.0
- ✓ Brand color palette: electric-cyan, bright-cobalt, deep-midnight, obsidian (fixed, not dynamic) — v22.0
- ✓ Montserrat font for headings via Fontsource (weights 600, 700) — v22.0
- ✓ Cyan-to-cobalt gradient utility for buttons, headings, card borders — v22.0
- ✓ Dynamic accent color system removed (useTheme simplified, react-colorful uninstalled, ClubConfig accent_color removed) — v22.0
- ✓ Component styling: gradient buttons, 3px gradient card borders, cyan glow focus rings, 200ms hover lift — v22.0
- ✓ Dark mode adapted to brand colors (preserved, not removed) — v22.0
- ✓ PWA assets updated to electric-cyan, favicon fixed, dead REST API endpoints removed — v22.0
- ✓ Rondo logo integrated as favicon, login page, and sidebar brand mark — v22.0

### Active

## Current Milestone: v23.0 Former Members

**Goal:** Archive former members when they leave the club (detected by rondo-sync), hiding them from default views while preserving all data. Findable through global search and a filter toggle on the Leden list.

**Target features:**
- Former member status field on person records (set via REST API by rondo-sync)
- REST API endpoint documentation for marking members as former
- Archived by default: hidden from Leden list, dashboard stats, and team rosters
- Filter toggle on Leden list to show former members ("Toon oud-leden")
- Global search includes former members (with visual distinction)
- Contributie: include former members who were active during part of the season (no pro-rata for leavers), exclude otherwise
- Team rosters automatically exclude former members
- All historical data preserved (notes, activities, relationships, fees)

### Out of Scope

- Mobile app — future consideration
- Real-time updates (WebSockets) — future enhancement
- Real-time Google sync (webhook-based) — will use polling/cron like Calendar
- Multiple Google accounts simultaneously — future enhancement
- Team/team sync to Google Contact groups — contacts only
- Two-way relationship sync — Google doesn't have relationship concepts
- Contact group/label sync — future enhancement after core sync works
- Public-facing feedback portal — internal use only
- Voting/upvoting system — can be added later
- Email notifications on feedback status change — deferred to future version
- Integration with external issue trackers (GitHub, Jira) — API access sufficient

## Context

**Codebase State (post v1.0):**
- WordPress theme (PHP 8.0+) with React 18 SPA
- REST API split into domain-specific classes (Base, People, Teams, Slack, Import/Export)
- Security hardened (sodium encryption, XSS protection, webhook validation)
- Production code cleaned up (no console.error, documented env vars)
- Full codebase map available in `.planning/codebase/`

**Key Existing Files:**
- `includes/class-rest-base.php` — Base REST class with shared utilities
- `includes/class-access-control.php` — Current author-based access control (to be extended)
- `includes/class-post-types.php` — CPT registration (add workspace here)
- `includes/class-taxonomies.php` — Taxonomy registration (add workspace_access here)

**Reference Documents:**
- `Rondo Club-Multi-User-Project-Plan.md` — Detailed technical design for multi-user phases
- `.planning/milestones/v5.0-google-contacts-sync.md` — Technical design for Google Contacts sync
- `docs/prd/Custom-Fields-Implementation-Plan.md` — PRD for custom fields (adapted to ACF-native approach)

## Constraints

- **Backward Compatibility**: Existing single-user functionality must continue working. Default visibility = private preserves current behavior.
- **WordPress Primitives**: Use CPT, taxonomies, user meta, post meta — no custom tables.
- **No Breaking Changes**: All existing REST API endpoints must continue working.
- **Progressive Disclosure**: Keep UI simple by default, reveal complexity only when needed.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Workspaces as CPT | Leverages WordPress CRUD, REST API, revisions, trash/restore | ✓ Good |
| Membership in user meta | Easy to query "my workspaces", survives workspace queries | ✓ Good |
| workspace_access taxonomy | Native WP query support, efficient lookups, multiple workspaces per contact | ✓ Good |
| Visibility in post meta | Simple flag per contact, easy to filter | ✓ Good |
| Direct shares in post meta | Keeps share data with post, easy to show "who has access" | ✓ Good |
| Invitation tokens 32-char | Secure, URL-safe, no special characters | ✓ Good |
| Invites expire 7 days | Reasonable timeframe for action | ✓ Good |
| Mention markup @[Name](id) | react-mentions standard format | ✓ Good |
| Mentions default to digest | Reduces notification fatigue | ✓ Good |
| Vendor + utils chunking | Stable deps cached separately | ✓ Good |
| Route-based lazy loading | Pages load on demand | ✓ Good |
| Component-level Suspense for heavy libs | vis-network/TipTap load only when needed | ✓ Good |
| Todo CPT not Comment | Todos are posts; richer metadata, proper access control | ✓ Good |
| WordPress post statuses for todos | Mutually exclusive states (open/awaiting/completed) | ✓ Good |
| Auto-timestamp on awaiting | awaiting_since set/cleared automatically on state change | ✓ Good |
| Urgency color scheme | Yellow 0-2d, orange 3-6d, red 7+d for visual priority | ✓ Good |
| Current positions from sortedWorkHistory | Reuses existing sorted data via useMemo | ✓ Good |
| Sidebar hidden below lg | Mobile gets FAB instead of sidebar | ✓ Good |
| FAB at z-40 | Above content, below modals (z-50) | ✓ Good |
| Panel closes on action | Edit/Add close panel before modal opens | ✓ Good |
| 3-column grid layout | Equal-width columns for content and sidebar | ✓ Good |
| Deprecated fields during transition | Keep person_id/person_name/person_thumbnail for backward compat | ✓ Good |
| LIKE query for ACF serialized arrays | Format `"%d"` matches ID in serialized string | ✓ Good |
| Notes sanitization with wp_kses_post | Consistent with notes/activities rich text handling | ✓ Good |
| Multi-person selector edit-only | New todos context-bound to person page | ✓ Good |
| Notes section collapsed by default | Avoid modal height bloat | ✓ Good |
| Stacked avatars max 3/2 | 3 in TodosList, 2 in compact PersonDetail sidebar | ✓ Good |
| Filter current person from "Also:" | Only shows OTHER linked people | ✓ Good |
| Search scoring system (100/80/60/40/20) | First name exact highest, then starts-with, contains, last name, general | ✓ Good |
| Backend auto-detects custom titles | Compare to would-be auto-generated, save to custom_label | ✓ Good |
| useRef for title edit tracking | Avoid re-renders from useState | ✓ Good |
| custom_label in API response | Frontend knows when title was customized | ✓ Good |
| Direct completion for awaiting todos | Quick status change, no modal needed | ✓ Good |
| ACF update_value filter for email | Normalize at save time, not display | ✓ Good |
| null Suspense fallback for modals | Modals overlay content, no spinner needed | ✓ Good |
| google/apiclient for OAuth | Official library, reliable token refresh | ✓ Good |
| Sodium encryption for OAuth tokens | Secure credential storage, AUTH_KEY-derived key | ✓ Good |
| Event uniqueness via UID + connection | Prevents duplicates across syncs | ✓ Good |
| Email-first contact matching | Avoids false positives from common names | ✓ Good |
| 24h transient cache for email lookups | Balances freshness with performance | ✓ Good |
| 15-minute cron interval | Balance between freshness and API load | ✓ Good |
| One user per cron run | Round-robin prevents API rate limit hits | ✓ Good |
| Conditional dashboard widget | Graceful degradation when no calendar connected | ✓ Good |
| DOM errors documented as benign | React 18 StrictMode artifacts, no fix needed | ✓ Good |
| Two-step rsync deploy | Sync dist/ with --delete separately to prevent stale artifacts | ✓ Good |
| Dashboard 3-row layout | Stats row always 3 cols, Activity row with conditional Meetings | ✓ Good |
| React manages favicon dynamically | Removed PHP static favicon, React uses inline SVG data URLs | ✓ Good |
| ISO 8601 for meeting times | Timezone offset preserved in API, JavaScript parses correctly | ✓ Good |

| Error boundary pattern for DOM sync errors | Catches DOM-specific errors (NotFoundError, removeChild, insertBefore), preserves query cache | ✓ Good |
| Subtab navigation pattern | URL-based subtab routing (tab=connections&subtab=calendars) | ✓ Good |
| Re-match on save approach | Triggers on every person save, acceptable performance for background operation | ✓ Good |
| WordPress-Extra standard | Stricter than WordPress-Core, includes best practices | ✓ Good |
| Yoda conditions disabled | Prefer readable `$var === 'value'` over WordPress-mandated `'value' === $var` | ✓ Good |
| Short array syntax enforced | Modern PHP convention `[]` instead of `array()` | ✓ Good |
| Strategic PHPCS exclusions | CardDAV/Sabre naming, short ternary, deprecated functions kept as documented | ✓ Good |
| WP-CLI multi-class exception | Keep 9 CLI command classes in one file (conditionally loaded, logically grouped) | ✓ Good |
| Composer classmap alongside PSR-4 | Supports current class-*.php naming during transition to standard PSR-4 | ✓ Good |
| Backward-compatible class aliases | All RONDO_* class names work via class_alias() for existing code | ✓ Good |
| Global class backslash prefix | PHP/WP classes (DateTime, WP_Error, etc.) need `\` in namespaced files | ✓ Good |
| Dark mode contrast pattern | Consistently use gray-300/gray-400 for better contrast (not gray-400/gray-500) | ✓ Good |
| Solid background for dark mode selected states | Use dark:bg-accent-800 with dark:text-accent-100 (semi-transparent accent-900/30 unreliable) | ✓ Good |
| Activity type ID preservation | Keep 'call' ID when renaming to "Phone" to preserve existing activity data | ✓ Good |
| Inline popup over modal for attendee addition | Reduces friction for two-option choice | ✓ Good |
| Case-insensitive duplicate email detection | Prevents duplicate emails regardless of case | ✓ Good |
| Date parameter YYYY-MM-DD format | Standard ISO format, validated with regex | ✓ Good |
| useTodayMeetings as alias to useDateMeetings | Backward compatibility with new date navigation | ✓ Good |
| Lazy loading PersonEditModal in MeetingDetailModal | Avoids chunk size increase | ✓ Good |
| prefillData prop pattern | Pass { first_name, last_name, email } for pre-filling forms from external context | ✓ Good |
| Preserve ACF required fields on email addition | Include first_name, last_name when updating contact_info | ✓ Good |
| 280px content height for dashboard widgets | Comfortably displays ~5 items while keeping widget size manageable | ✓ Good |
| 6 skeleton widgets during loading | Shows typical dashboard layout for visual consistency | ✓ Good |
| placeholderData for layout stability | TanStack Query v5 pattern preventing layout jump during date navigation | ✓ Good |
| get_calendar_ids() static helper | Centralizes backward compatibility for calendar format normalization | ✓ Good |
| Two-column responsive modal layout | md:grid-cols-2 stacks on small screens, fits modal content | ✓ Good |
| Wider modal for two columns | max-w-2xl accommodates two-column layout | ✓ Good |
| Separate OAuth callback for contacts | Different post-auth behavior vs calendar (redirect to subtab, pending_import flag) | ✓ Good |
| User-level connection for contacts | Contacts sync is account-wide, unlike calendar which is per-resource | ✓ Good |
| Fill gaps only on import | Never overwrite existing Rondo Club data, only fill empty fields | ✓ Good |
| Three-way conflict comparison | Compare Google vs Rondo Club vs snapshot to detect actual conflicts | ✓ Good |
| Rondo Club wins by default | Source of truth design, deletions in Google only unlink in Rondo Club | ✓ Good |
| Sync history in connection meta | Last 10 entries, efficient storage without unbounded growth | ✓ Good |
| ACF-native field storage | Field groups per post type (group_custom_fields_{post_type}), no custom tables | ✓ Good |
| Field key naming pattern | field_custom_{post_type}_{slug} for consistency and traceability | ✓ Good |
| Soft delete via active flag | Preserve stored data when field definition is deactivated | ✓ Good |
| Separate /metadata endpoint | Non-admin read access for field structure, admin-only for CRUD | ✓ Good |
| Custom field search priority 30 | Lower than name matches (60-100), higher than general search (20) | ✓ Good |
| menu_order starts at 1 | ACF convention, not 0-based | ✓ Good |
| Unique validation per user | Scoped to current user's posts, not global uniqueness | ✓ Good |
| SortableFieldRow with dnd-kit | Consistent drag-drop pattern for table rows in Settings | ✓ Good |
| RelationshipItemCompact for list view | Async fetch for relationship names in compact column display | ✓ Good |
| Season key format YYYY-YYYY | Human-readable, standard sports season format | ✓ Good |
| Family key format POSTALCODE-HOUSENUMBER | Street name ignored for flexible matching | ✓ Good |
| Tiered discount 0%/25%/50% | Position 1/2/3+ per FAM requirements | ✓ Good |
| Quarterly pro-rata tiers | 100%/75%/50%/25% for Jul-Sep/Oct-Dec/Jan-Mar/Apr-Jun | ✓ Good |
| Null vs 0 for missing Nikki data | Distinguishes "no data" from "zero balance" | ✓ Good |
| Red/green color coding for saldo | Positive (owes money) = red, zero/negative = green | ✓ Good |
| Forecast ignores season parameter | Always uses next season for consistency | ✓ Good |
| 100% pro-rata for forecast | Full year assumption for budget planning | ✓ Good |
| Nikki fields omitted from forecast | Future season has no billing data | ✓ Good |
| Native select for season dropdown | Consistent with existing UI patterns | ✓ Good |
| Instant column hiding without animation | Immediate table reflow per UX decision | ✓ Good |
| Forecast export title includes (Prognose) | Clear visual indicator in exported spreadsheet | ✓ Good |
| Forecast exports 8-column layout | Nikki columns excluded from future season | ✓ Good |
| Post Object for person link | Returns single integer (not array), cleaner REST response | ✓ Good |
| Single charge code field | Matches Sportlink data model, simplifies storage | ✓ Good |
| seizoen as shared taxonomy | Enables reuse across future features (fees, events) | ✓ Good |
| Client-side person filtering | More reliable than ACF meta queries for discipline cases | ✓ Good |
| FairplayRoute wrapper pattern | Consistent with existing ProtectedRoute, reusable | ✓ Good |
| Hide tab if zero cases | Reduces UI clutter, better UX for persons without discipline history | ✓ Good |
| Disabled refetchOnWindowFocus | Prevents unnecessary API calls on tab switches for personal CRM use case | ✓ Good |
| createBrowserRouter data router pattern | Module-scoped route configuration prevents router recreation on renders | ✓ Good |
| ES module without version query string | Browser ES module cache keys by full URL; version query caused double execution | ✓ Good |
| Modal lazy-loading pattern | `usePeople({}, { enabled: isOpen })` pattern for conditional data fetching | ✓ Good |
| Centralized useCurrentUser hook | Single cache entry shared by 6 components with 5-minute staleTime | ✓ Good |
| wp_count_posts() for todo counts | Efficient SQL COUNT instead of fetching all post IDs | ✓ Good |
| Task isolation via post_author filter | Users see only their own tasks, consistent UX for all including admins | ✓ Good |
| suppress_filters=false for get_posts() | WordPress get_posts() bypasses pre_get_posts by default; must explicitly enable filters | ✓ Good |
| Direct author check in permission callback | Single-todo endpoints verify post_author matches current user for isolation | ✓ Good |
| Individual WordPress option keys for club config | Separate options (stadion_club_name, stadion_accent_color, stadion_freescout_url) allow independent updates | ✓ Good |
| REST partial updates via null-checking | POST endpoint checks $request->get_param() !== null before updating each field | ✓ Good |
| Dynamic CSS variable injection for club color | When club color differs from default, useTheme.js injects full 50-900 scale as inline styles on :root | ✓ Good |
| Auto-migration for breaking localStorage changes | loadPreferences() auto-converts 'awc' to 'club' on load for zero user disruption | ✓ Good |
| Lighthouse artifact exclusion from AWC cleanup | lighthouse-full.json contains svawc.nl references but is historical test data, not source code | ✓ Good |
| Integration URLs externalized to config | FreeScout link checks window.stadionConfig first, hides feature if not configured | ✓ Good |
| Generic filter config pattern | `get_dynamic_filter_config()` maps filter key → meta_key + sort_method, makes future filters trivial | ✓ Good |
| Smart age group sorting | Numeric extraction from "Onder X" pattern, then gender variant detection | ✓ Good |
| Member type priority sorting | Explicit priority array with unknown types at end (priority 99) | ✓ Good |
| 5-minute staleTime for filter/role settings | Rarely-changing data, changes only on sync | ✓ Good |
| GET role settings accessible to all users | Non-admins need role data for team detail page display | ✓ Good |
| Skip-and-warn in rondo-sync | Missing role descriptions logged as warning, entry skipped — makes data quality visible | ✓ Good |
| Remove member_type from sync layer | Classification now happens in Rondo Club settings, not sync pipeline | ✓ Good |
| PRAGMA table_info before ALTER TABLE | Safe migration pattern for SQLite schema changes | ✓ Good |
| Slug-keyed category objects | Categories stored as slug→{label, amount, age_classes, is_youth, sort_order} for O(1) lookup | ✓ Good |
| No backward compat for flat amounts | Clean break from old format, single-club app with manual data setup | ✓ Good |
| Age class exact string matching | Match Sportlink age class strings against category config arrays, not regex/ranges | ✓ Good |
| Lowest sort_order wins for age class conflicts | Deterministic resolution when age class assigned to multiple categories | ✓ Good |
| Full replacement pattern for POST settings | Categories array fully replaced on save, simpler than patch operations | ✓ Good |
| Errors vs warnings in validation | Errors block save (duplicate slugs), warnings are informational (duplicate age classes) | ✓ Good |
| Auto-slug from label | Slug auto-derived when creating categories, reduces admin error | ✓ Good |
| Database-driven age class multi-select | Age classes from filter-options endpoint, not free text input | ✓ Good |
| is_youth relabeled "Familiekorting mogelijk?" | Reflects actual business meaning for admins | ✓ Good |
| Separate family discount option | rondo_family_discount_{season} avoids conflicts with category saves | ✓ Good |
| Copy-forward for new season config | New seasons auto-inherit categories and discount config from previous | ✓ Good |
| Config-driven matching rules | Team/werkfunctie matching per category replaces hardcoded is_recreational_team()/is_donateur() | ✓ Good |
| Priority order: youth > team > werkfunctie > age-class | Deterministic category assignment hierarchy | ✓ Good |
| Fixed palette for category colors | Colors auto-assigned by sort_order position, no per-category color config needed | ✓ Good |
| Clean break to Tailwind v4 | No backward compatibility with v3, single-club app | ✓ Good |
| OKLCH color space for brand tokens | Wider P3 gamut, perceptually uniform lightness | ✓ Good |
| Keep dark mode, adapt to brand colors | User preference to preserve dark mode, not remove it | ✓ Good |
| Fixed brand color in PHP (#0891b2 sRGB) | PHP-rendered elements use hardcoded electric-cyan, no dynamic theming | ✓ Good |
| Hardcoded rgba(8, 145, 178, ...) in PHP | Electric-cyan transparency effects for login page | ✓ Good |
| ease-in-out for Tailwind v4 transitions | Tailwind v4 doesn't support ease, use ease-in-out instead | ✓ Good |
| Exclude modal h2 and filter labels from gradient | Visual distinction for error headings and form labels | ✓ Good |
| Glass morphism deferred to future | Mobile performance concerns, not critical for brand alignment | Deferred |
| Decorative blobs deferred to future | Polish feature, can be added later | Deferred |

---
*Last updated: 2026-02-09 after v22.0 Design Refresh shipped*
