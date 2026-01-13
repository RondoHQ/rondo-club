# Caelis CRM: Multi-User Transformation Project Plan

*Combining the best of Clay.earth and Twenty CRM*

---

## Executive Summary

This project plan outlines the transformation of Caelis from a single-user personal CRM into a multi-user platform that combines the intimate, relationship-focused approach of **Clay.earth** with the collaborative, team-oriented features of **Twenty CRM**. The result will be a unique hybrid: a personal relationship manager that scales to teams while preserving privacy and personal connection.

---

## Vision: The Best of Both Worlds

### From Clay.earth

- **Personal relationship focus:** Deep, thoughtful tracking of individual relationships
- **Reconnect suggestions:** AI-powered prompts to maintain relationships
- **Beautiful, private experience:** Intimate interface for personal network management
- **Automatic enrichment:** Pull data from email, calendar, social connections

### From Twenty CRM

- **Multi-user collaboration:** Shared workspaces with real-time updates
- **Team permissions:** Granular control over who sees what
- **Open-source flexibility:** Self-hosted, customizable, community-driven
- **Pipeline visualization:** Kanban views and deal tracking for teams

---

## Current State Analysis

Caelis is currently a sophisticated WordPress-based personal CRM with a React SPA frontend. Key existing features include:

- Per-user data isolation (author-based access control)
- User approval workflow for new registrations
- Rich contact management with relationships, timeline, photos
- CardDAV server for external sync
- Multi-channel notifications (Email, Slack)
- Import/export (vCard, Google Contacts, Monica CRM)

**Gap:** No sharing mechanism between users. Each user's contacts are completely siloed with no way to collaborate or share selected contacts with others.

---

## Project Phases Overview

| Phase | Description | Effort |
|-------|-------------|--------|
| 1 | Data Model & Visibility System | 2-3 weeks |
| 2 | Workspace & Team Infrastructure | 3-4 weeks |
| 3 | Sharing UI & Permissions Interface | 2-3 weeks |
| 4 | Collaborative Features | 2-3 weeks |
| 5 | Migration, Testing & Polish | 1-2 weeks |

**Total estimated effort: 10-15 weeks**

---

## Phase 1: Data Model & Visibility System

Establish the foundation for private vs. shared contacts using WordPress primitives.

### 1.1 Contact Visibility Field (Post Meta)

Add an ACF field `_visibility` to Person and Company post types:

- **`private`** — Only the owner can see this contact (current default behavior)
- **`workspace`** — Visible to all members of assigned workspaces
- **`shared`** — Visible to specific users (fine-grained sharing)

**Implementation:** Single post meta field via ACF, stored in `wp_postmeta`.

### 1.2 Workspaces as Custom Post Type

Create a new CPT `workspace` to represent team spaces:

```
Post Type: workspace
├── post_title        → Workspace name
├── post_content      → Description
├── post_author       → Owner (creator)
├── post_status       → publish / draft (archived)
└── Meta Fields (ACF):
    ├── _default_visibility    → Default for new contacts (private/workspace)
    └── _workspace_settings    → JSON blob for preferences
```

**Why CPT?** Leverages WordPress's built-in CRUD, REST API, revision history, and trash/restore functionality.

### 1.3 Workspace Membership via User Meta

Store each user's workspace memberships and roles in user meta:

```
User Meta: _workspace_memberships
Value: [
  { "workspace_id": 123, "role": "admin", "joined_at": "2026-01-15" },
  { "workspace_id": 456, "role": "member", "joined_at": "2026-01-20" }
]
```

**Roles:**
- **admin** — Full control (manage members, delete workspace)
- **member** — Add/edit contacts in workspace
- **viewer** — Read-only access

**Why User Meta?** Keeps membership data with the user, easy to query "which workspaces am I in?", and survives workspace queries.

### 1.4 Workspace Assignment via Taxonomy

Create a non-hierarchical taxonomy `workspace_access` to tag posts with workspaces:

```
Taxonomy: workspace_access
├── Terms created dynamically (one per workspace)
├── Term slug = workspace post ID (e.g., "workspace-123")
└── Assigned to: person, company, important_date
```

**Why Taxonomy?**
- Native WordPress query support (`tax_query`)
- Multiple workspaces per contact (just add more terms)
- Efficient lookups via `wp_term_relationships` table
- Works with existing `WP_Query` patterns in `PRM_Access_Control`

### 1.5 Direct User Shares via Post Meta

For sharing with specific users (not via workspace), store in post meta:

```
Post Meta: _shared_with
Value: [
  { "user_id": 5, "permission": "edit", "shared_by": 1, "shared_at": "2026-01-15" },
  { "user_id": 8, "permission": "view", "shared_by": 1, "shared_at": "2026-01-16" }
]
```

**Why Post Meta?** Keeps share data with the post, easy to display "who has access" on the contact detail page.

### 1.6 Update Access Control Layer

Extend `PRM_Access_Control` to check (in order):

1. **Author check** (existing) — User is post author → Full access
2. **Visibility check** — If `_visibility` = `private` → Deny (unless #1)
3. **Workspace check** — If `_visibility` = `workspace`:
   - Get user's workspace IDs from `_workspace_memberships` user meta
   - Query if post has any matching `workspace_access` terms
   - Allow if match found (apply role-based permission)
4. **Direct share check** — Check `_shared_with` post meta for user ID
   - Allow with specified permission level

**Query optimization:** Use `meta_query` combined with `tax_query` in `pre_get_posts`.

### 1.7 Deliverables

- ACF field group: `visibility_settings` (visibility, shared_with)
- CPT registration: `workspace`
- Taxonomy registration: `workspace_access`
- Helper functions for user meta (get/set workspace memberships)
- Updated `PRM_Access_Control` class
- Unit tests for all visibility scenarios

---

## Phase 2: Workspace & Team Infrastructure

Build the workspace system using the WordPress primitives defined in Phase 1.

### 2.1 Workspace CPT Configuration

```php
register_post_type('workspace', [
    'public'              => false,
    'show_in_rest'        => true,
    'rest_base'           => 'workspaces',
    'supports'            => ['title', 'editor', 'author', 'thumbnail'],
    'capability_type'     => 'workspace',
    'map_meta_cap'        => true,
]);
```

**Capabilities:** Map to existing `caelis_user` role, admins get full access.

### 2.2 Workspace Taxonomy Auto-Sync

When a workspace is created/deleted, automatically create/delete the corresponding `workspace_access` term:

- `save_post_workspace` → Create term `workspace-{ID}`
- `before_delete_post` → Delete term, unassign from all posts

### 2.3 Member Management Functions

```php
// Add user to workspace
PRM_Workspace_Members::add($workspace_id, $user_id, $role = 'member');

// Remove user from workspace
PRM_Workspace_Members::remove($workspace_id, $user_id);

// Update role
PRM_Workspace_Members::update_role($workspace_id, $user_id, $new_role);

// Get workspace members
PRM_Workspace_Members::get_members($workspace_id);

// Get user's workspaces
PRM_Workspace_Members::get_user_workspaces($user_id);
```

All operations read/write to user meta `_workspace_memberships`.

### 2.4 Invitation System via Custom Post Type

Create invitations as a lightweight CPT:

```
Post Type: workspace_invite
├── post_author       → Inviter
├── post_status       → pending / accepted / declined
└── Meta Fields:
    ├── _workspace_id     → Target workspace
    ├── _invitee_email    → Email address
    ├── _invitee_user_id  → User ID (if existing user)
    ├── _role             → Invited role
    └── _token            → Unique acceptance token
```

**Why CPT?** Audit trail, easy to query pending invites, works with WP email hooks.

### 2.5 REST API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp/v2/workspaces` | GET/POST | List/create workspaces (standard WP REST) |
| `/wp/v2/workspaces/{id}` | GET/PUT/DELETE | CRUD (standard WP REST) |
| `/prm/v1/workspaces/{id}/members` | GET/POST/DELETE | Manage members |
| `/prm/v1/workspaces/{id}/invite` | POST | Send invitation |
| `/prm/v1/invites/accept` | POST | Accept invite via token |
| `/prm/v1/posts/{id}/share` | POST | Share with user/workspace |
| `/prm/v1/posts/{id}/shares` | GET/DELETE | List/remove shares |

### 2.6 Deliverables

- Workspace CPT registration with REST support
- `workspace_access` taxonomy with auto-sync hooks
- `workspace_invite` CPT for invitations
- `PRM_Workspace_Members` class (user meta operations)
- `PRM_Sharing` class (post meta + taxonomy operations)
- Email templates for invitations
- All REST API endpoints

---

## Phase 3: Sharing UI & Permissions Interface

Build the frontend components for managing visibility and sharing.

### 3.1 Contact Visibility Controls

- Visibility selector on `PersonEditModal` and `CompanyEditModal`
- Quick visibility indicator (lock/globe/users icon) in list views
- Bulk visibility change for selected contacts

### 3.2 Share Modal Component

A reusable modal for sharing any contact/company:

- Search/select users to share with (autocomplete)
- Select workspaces to add contact to (checkboxes)
- Permission level selector (view/edit)
- List of current shares with remove option
- "Shared by [name] on [date]" attribution

### 3.3 Workspace Management Pages

- `/workspaces` — List all workspaces user belongs to
- `/workspaces/:id` — Workspace detail with members, shared contacts
- `/workspaces/:id/settings` — Configuration (admins only)
- Workspace switcher in sidebar

### 3.4 List View Filtering

- Filter dropdown: "All" / "My Contacts" / "Shared with Me" / by workspace
- Visual badge for shared contacts (shows owner avatar)
- Permission indicator (edit vs view-only)

### 3.5 Deliverables

- `ShareModal.jsx` component
- `VisibilitySelector.jsx` component
- `WorkspacesList.jsx`, `WorkspaceDetail.jsx`, `WorkspaceSettings.jsx` pages
- Updated `PeopleList` and `CompaniesList` with ownership filters
- TanStack Query hooks: `useWorkspaces`, `useWorkspaceMembers`, `useShares`

---

## Phase 4: Collaborative Features

Add features that make multi-user collaboration genuinely useful.

### 4.1 Shared Timeline & Activity Feed

- Show author on all timeline items (notes, activities, todos)
- New meta field `_timeline_visibility` on comments: `shared` (default) or `private`
- Activity log CPT or post meta for "John shared Contact X with you"

### 4.2 Collaborative Notes (Clay-inspired)

- @mentions in notes → parsed and stored as user IDs in comment meta
- Notification triggered on mention
- Pinned notes via comment meta `_is_pinned`

### 4.3 Shared Important Dates

- Filter dates list by workspace
- Workspace iCal feed endpoint: `/prm/v1/workspaces/{id}/ical`
- Team reminder setting per workspace

### 4.4 Notification Enhancements

- Hook into share/unshare actions to trigger notifications
- Workspace activity digest (scheduled via `wp_cron`)
- Per-workspace notification preferences in user meta

### 4.5 Deliverables

- Timeline visibility controls (comment meta)
- @mention parser and `PRM_Mention_Notifications` class
- Workspace calendar view component
- Activity feed component
- Digest email cron job

---

## Phase 5: Migration, Testing & Polish

Ensure smooth transition for existing users and production readiness.

### 5.1 Data Migration

- Set `_visibility` = `private` on all existing contacts (preserves current behavior)
- No workspace creation required (users opt-in to workspaces)
- Migration via WP-CLI: `wp prm migrate-to-multiuser`

### 5.2 Testing

- Unit tests for `PRM_Workspace_Members`, `PRM_Sharing`, updated `PRM_Access_Control`
- Integration tests for complex permission scenarios
- React component tests
- E2E tests for sharing workflows

### 5.3 Documentation

- Updated README
- User guide for workspaces and sharing
- Developer docs for permission system

### 5.4 Deliverables

- Migration WP-CLI command
- Test suite
- Documentation

---

## Technical Architecture Summary

### WordPress Primitives Used

| Concept | WordPress Primitive | Storage |
|---------|---------------------|---------|
| Workspaces | Custom Post Type `workspace` | `wp_posts` |
| Workspace membership | User Meta `_workspace_memberships` | `wp_usermeta` |
| Post-to-workspace assignment | Taxonomy `workspace_access` | `wp_terms`, `wp_term_relationships` |
| Contact visibility | Post Meta `_visibility` | `wp_postmeta` |
| Direct user shares | Post Meta `_shared_with` | `wp_postmeta` |
| Invitations | Custom Post Type `workspace_invite` | `wp_posts` |
| Timeline privacy | Comment Meta `_timeline_visibility` | `wp_commentmeta` |

### Permission Resolution Flow

```
User requests contact →
├── Is user the author? → Full access
├── Is _visibility = 'private'? → Deny
├── Is _visibility = 'workspace'?
│   ├── Get user's workspace IDs from user meta
│   ├── Check if contact has matching workspace_access term
│   └── Allow with role-based permission (admin/member/viewer)
├── Check _shared_with post meta for user ID
│   └── Allow with specified permission (edit/view)
└── Deny
```

### New File Structure

**PHP Classes:**
- `class-workspace-post-type.php` — CPT registration
- `class-workspace-taxonomy.php` — Taxonomy registration + sync
- `class-workspace-members.php` — User meta operations
- `class-sharing.php` — Share management (post meta + taxonomy)
- `class-rest-workspaces.php` — Custom REST endpoints
- `class-workspace-invites.php` — Invitation handling

**React Components:**
- `ShareModal.jsx`
- `VisibilitySelector.jsx`
- `WorkspacesList.jsx`
- `WorkspaceDetail.jsx`
- `WorkspaceSettings.jsx`
- `WorkspaceSwitcher.jsx`

---

## Risks & Considerations

### Performance

- User meta JSON parsing on every request → Cache with transients or object cache
- Tax queries on large datasets → Ensure proper indexes, consider `update_term_meta_cache`

### Security

- Permission bypass → Audit all query paths in `PRM_Access_Control`
- Timeline item visibility → Ensure comment queries respect `_timeline_visibility`
- CardDAV sync → Update `PRM_CardDAV_Backend` to respect visibility

### User Experience

- Complexity creep → Default to simple (private), progressive disclosure for sharing
- Clear visual indicators → Lock icons, badges, "shared by" attribution

---

## Recommended Next Steps

1. Review and approve this project plan
2. Create feature branch: `feature/multi-user`
3. Begin Phase 1: Register workspace CPT, taxonomy, and ACF fields
4. Weekly check-ins to review progress and adjust scope

---

*Generated January 2026*
