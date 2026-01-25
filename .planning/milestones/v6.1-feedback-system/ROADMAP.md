# Roadmap: v6.1 Feedback System

## Overview

This milestone adds a feedback system to Stadion, enabling users to submit bug reports and feature requests from within the application. Feedback is stored as WordPress posts, exposed via authenticated REST API for external tool integration, and managed through a dedicated admin UI.

## Phase Structure

### Phase 1: Backend Foundation

**Goal:** Feedback data can be stored and retrieved through WordPress infrastructure.

**Dependencies:** None

**Requirements:**
- FEED-01: `stadion_feedback` custom post type registration
- FEED-02: ACF field group for feedback metadata (type, status, priority, context fields)
- FEED-03: Feedback statuses (new, in_progress, resolved, declined)

**Plans:** 1 plan

Plans:
- [x] 95-01-PLAN.md — Register CPT and create ACF field group

**Success Criteria:**
1. User can create a feedback post in WordPress admin with all required fields
2. Feedback posts store type (bug/feature_request), status, priority, and context fields
3. Status field enforces valid values (new, in_progress, resolved, declined)
4. Feedback posts are queryable via WP_Query with meta filters

---

### Phase 2: REST API

**Goal:** External tools can create and manage feedback programmatically.

**Dependencies:** Phase 1

**Requirements:**
- FEED-04: REST API endpoints (CRUD) under `stadion/v1/feedback`
- FEED-05: Application password authentication support
- FEED-06: Permission model (own feedback + admin access to all)

**Plans:** 1 plan

Plans:
- [x] 96-01-PLAN.md — Create Feedback REST API with CRUD endpoints and permissions

**Success Criteria:**
1. User can create feedback via `POST /wp-json/stadion/v1/feedback` with application password auth
2. User can list their own feedback via `GET /wp-json/stadion/v1/feedback`
3. User can update/delete their own feedback via PATCH/DELETE endpoints
4. Admin can list, update, and delete any feedback
5. API returns proper error codes for unauthorized access attempts

---

### Phase 3: Frontend Submission

**Goal:** Users can submit and view feedback from within Stadion.

**Dependencies:** Phase 2

**Requirements:**
- FEED-07: Feedback navigation and routing (`/feedback`, `/feedback/:id`)
- FEED-08: Feedback list view with type/status filtering
- FEED-09: Feedback detail view (read-only for non-owners)
- FEED-10: Feedback submission form with conditional fields
- FEED-11: System info capture (browser, version, current page) on opt-in
- FEED-12: File attachments via WordPress media library

**Plans:** 2 plans

Plans:
- [x] 97-01-PLAN.md — API client, TanStack Query hooks, and routing infrastructure
- [x] 97-02-PLAN.md — List view, detail view, and submission modal with conditional fields

**Success Criteria:**
1. User can navigate to Feedback via sidebar menu
2. User can view list of their submitted feedback with type/status badges
3. User can filter feedback by type (Bug/Feature Request) and status
4. User can submit new feedback with title, description, and type-specific fields
5. Bug reports show fields for steps to reproduce, expected/actual behavior
6. Feature requests show use case field
7. User can opt-in to include system info (auto-captured browser/version/URL)
8. User can attach screenshots via drag-and-drop

---

### Phase 4: Admin Management

**Goal:** Administrators can manage all feedback with status workflow and ordering.

**Dependencies:** Phase 3

**Requirements:**
- FEED-13: Admin feedback management UI in Settings
- FEED-14: Status workflow controls (change status to in_progress/resolved/declined)
- FEED-15: Priority assignment (low/medium/high/critical)
- FEED-16: Feedback ordering/sorting capability
- FEED-17: Application password management UI in Settings

**Plans:** 2 plans

Plans:
- [x] 98-01-PLAN.md — API Access tab with application password management
- [x] 98-02-PLAN.md — Admin feedback management page with status/priority controls

**Success Criteria:**
1. Admin can view all feedback from all users in Settings > Feedback tab
2. Admin can change feedback status (new -> in_progress -> resolved/declined)
3. Admin can assign priority to feedback items
4. Admin can sort/reorder feedback by priority, status, or date
5. Admin can filter feedback by type, status, and priority
6. User can generate application passwords via Settings > API Access subtab
7. User can view and revoke existing application passwords

---

## Progress

| Phase | Status | Requirements | Completion |
|-------|--------|--------------|------------|
| Phase 1: Backend Foundation | Complete | FEED-01, FEED-02, FEED-03 | 100% |
| Phase 2: REST API | Complete | FEED-04, FEED-05, FEED-06 | 100% |
| Phase 3: Frontend Submission | Complete | FEED-07, FEED-08, FEED-09, FEED-10, FEED-11, FEED-12 | 100% |
| Phase 4: Admin Management | Complete | FEED-13, FEED-14, FEED-15, FEED-16, FEED-17 | 100% |

## Requirement Coverage

| Requirement | Description | Phase |
|-------------|-------------|-------|
| FEED-01 | `stadion_feedback` CPT registration | Phase 1 |
| FEED-02 | ACF field group for feedback metadata | Phase 1 |
| FEED-03 | Feedback status values | Phase 1 |
| FEED-04 | REST API CRUD endpoints | Phase 2 |
| FEED-05 | Application password authentication | Phase 2 |
| FEED-06 | Permission model (own + admin) | Phase 2 |
| FEED-07 | Feedback navigation and routing | Phase 3 |
| FEED-08 | Feedback list view with filtering | Phase 3 |
| FEED-09 | Feedback detail view | Phase 3 |
| FEED-10 | Submission form with conditional fields | Phase 3 |
| FEED-11 | System info capture | Phase 3 |
| FEED-12 | File attachments | Phase 3 |
| FEED-13 | Admin feedback management UI | Phase 4 |
| FEED-14 | Status workflow controls | Phase 4 |
| FEED-15 | Priority assignment | Phase 4 |
| FEED-16 | Feedback ordering capability | Phase 4 |
| FEED-17 | Application password management UI | Phase 4 |

**Coverage:** 17/17 requirements mapped (100%)

## Deferred to Future Version

- Email notifications on feedback status change
- Slack notifications for new feedback
- Voting/upvoting system
- Integration with external issue trackers (GitHub, Jira)

## Key Implementation Notes

### Backend Patterns (from existing codebase)
- CPT registration: Follow pattern in `includes/class-post-types.php`
- REST endpoints: Extend `Stadion\REST\Base`, follow pattern in `includes/class-rest-api.php`
- ACF fields: Store in `acf-json/` directory for version control

### Scope Decisions (User Confirmed)
- **Global scope:** Feedback is per-installation, not workspace-scoped
- **Admin UI:** Full management interface needed (not API-only)
- **Attachments:** Use WordPress defaults (no custom limits)
- **Notifications:** Deferred to future version
