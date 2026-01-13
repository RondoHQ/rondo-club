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

### Active

**v2.0 Multi-User:**

*Phase 7 - Data Model & Visibility:*
- [ ] Create `workspace` Custom Post Type
- [ ] Create `workspace_access` taxonomy for post-to-workspace assignment
- [ ] Add `_visibility` post meta (private/workspace/shared)
- [ ] Store workspace memberships in user meta `_workspace_memberships`
- [ ] Add `_shared_with` post meta for direct user shares
- [ ] Update `PRM_Access_Control` to check visibility, workspace membership, and shares

*Phase 8 - Workspace Infrastructure:*
- [ ] Workspace REST endpoints (members, invites)
- [ ] `workspace_invite` CPT for invitation tracking
- [ ] `PRM_Workspace_Members` class for user meta operations
- [ ] `PRM_Sharing` class for share management
- [ ] Email templates for workspace invitations
- [ ] Auto-sync workspace_access taxonomy terms

*Phase 9 - Sharing UI:*
- [ ] ShareModal component for sharing contacts
- [ ] VisibilitySelector component for contact visibility
- [ ] WorkspacesList, WorkspaceDetail, WorkspaceSettings pages
- [ ] List view filtering (All/My Contacts/Shared with Me/by workspace)
- [ ] TanStack Query hooks for workspaces and shares

*Phase 10 - Collaborative Features:*
- [ ] Timeline visibility controls (shared vs private notes)
- [ ] @mentions in notes with notifications
- [ ] Workspace iCal feed endpoint
- [ ] Workspace activity digest via wp_cron
- [ ] Per-workspace notification preferences

*Phase 11 - Migration & Testing:*
- [ ] WP-CLI migration command `wp prm migrate-to-multiuser`
- [ ] Set `_visibility = private` on all existing contacts
- [ ] Test suite for permission scenarios
- [ ] Documentation updates

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
| Workspaces as CPT | Leverages WordPress CRUD, REST API, revisions, trash/restore | Pending |
| Membership in user meta | Easy to query "my workspaces", survives workspace queries | Pending |
| workspace_access taxonomy | Native WP query support, efficient lookups, multiple workspaces per contact | Pending |
| Visibility in post meta | Simple flag per contact, easy to filter | Pending |
| Direct shares in post meta | Keeps share data with post, easy to show "who has access" | Pending |

---
*Last updated: 2026-01-13 — v2.0 milestone created*
