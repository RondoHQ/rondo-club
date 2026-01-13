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

### Active

No active requirements. Use `/gsd:discuss-milestone` to plan next work.

### Out of Scope

- Performance optimization (N+1 queries, pagination) — separate milestone
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

---
*Last updated: 2026-01-13 — v2.0 Multi-User shipped*
