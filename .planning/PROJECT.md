# Caelis Multi-User Transformation

## What This Is

A major milestone transforming Caelis from a single-user personal CRM into a multi-user collaborative platform. Combines Clay.earth's intimate relationship focus with Twenty CRM's team collaboration features while preserving privacy and personal connection.

## Core Value

Add workspaces and sharing to enable team collaboration while maintaining the personal, relationship-focused experience that makes Caelis unique.

## Requirements

### Validated

<!-- Existing functionality from codebase that must continue working -->

- Personal CRM with people, companies, dates management — existing
- WordPress theme with React SPA frontend — existing
- REST API communication (wp/v2 + prm/v1 namespaces) — existing
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
- Tabular list view with Name, Organization, Workspace columns — v2.1
- Checkbox multi-selection with Set-based state — v2.1
- Bulk update REST endpoint `/prm/v1/people/bulk-update` — v2.1
- Bulk visibility change modal (Private/Workspace) — v2.1
- Bulk workspace assignment modal — v2.1

**v2.2 List View Polish (shipped 2026-01-13):**
- Split Name into First Name / Last Name columns — v2.2
- Labels column with styled pills — v2.2
- SortableHeader component with click sorting — v2.2
- Sticky table header and selection toolbar — v2.2
- BulkOrganizationModal with search and clear — v2.2
- BulkLabelsModal with add/remove mode — v2.2

**v2.3 List View Unification (shipped 2026-01-13):**
- Removed card view from People, list-only UI — v2.3
- Dedicated image column in People list — v2.3
- Organizations list view with sortable columns — v2.3
- Organizations selection and bulk action infrastructure — v2.3
- Bulk visibility, workspace, labels for Organizations — v2.3
- Full parity between People and Organizations list views — v2.3

**v2.5 Performance (shipped 2026-01-13):**
- Vite manual chunks for vendor (React) and utils (date-fns, etc.) — v2.5
- Route-based lazy loading with React.lazy + Suspense — v2.5
- Heavy library lazy loading (vis-network, TipTap) — v2.5
- Initial bundle reduced from 1,646 KB to 435 KB (73% reduction) — v2.5

**v3.0 Testing Infrastructure (shipped 2026-01-13):**
- PHPUnit via wp-browser (Codeception) with WPLoader — v3.0
- 120 tests covering access control, REST API, search, relationships — v3.0
- Separate test database (caelis_test) for isolation — v3.0

**v3.1 Pending Response Tracking (shipped 2026-01-14):**
- prm_todo custom post type (migrated from comments) — v3.1
- WordPress post statuses (prm_open, prm_awaiting, prm_completed) — v3.1
- Awaiting response tracking with timestamps and aging indicators — v3.1
- Filter UI (Open/Awaiting/Completed tabs) across all views — v3.1
- WP-CLI migration: `wp prm migrate-todos` — v3.1
- 25 PHPUnit tests for todo functionality (145 total) — v3.1

**v3.2 Person Profile Polish (shipped 2026-01-14):**
- Current position (job title + company) display in person header — v3.2
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
- Clickable website links in Organizations list — v3.4
- Labels column removed from Organizations list — v3.4
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

### Active

No active requirements. Use `/gsd:discuss-milestone` to plan next work.

### Out of Scope

- Mobile app — future consideration
- Real-time updates (WebSockets) — future enhancement
- External integrations beyond Slack — future milestone

## Context

**Codebase State (post v1.0):**
- WordPress theme (PHP 8.0+) with React 18 SPA
- REST API split into domain-specific classes (Base, People, Companies, Slack, Import/Export)
- Security hardened (sodium encryption, XSS protection, webhook validation)
- Production code cleaned up (no console.error, documented env vars)
- Full codebase map available in `.planning/codebase/`

**Key Existing Files:**
- `includes/class-rest-base.php` — Base REST class with shared utilities
- `includes/class-access-control.php` — Current author-based access control (to be extended)
- `includes/class-post-types.php` — CPT registration (add workspace here)
- `includes/class-taxonomies.php` — Taxonomy registration (add workspace_access here)

**Reference Document:**
- `Caelis-Multi-User-Project-Plan.md` — Detailed technical design for all phases

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

---
*Last updated: 2026-01-14 — v3.6 Quick Wins & Performance shipped*
