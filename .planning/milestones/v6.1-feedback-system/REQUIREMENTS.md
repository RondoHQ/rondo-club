# Requirements: v6.1 Feedback System

## Overview

Requirements for the Stadion feedback system enabling bug reports and feature requests.

## v1 Requirements

### Backend (Phase 1)

#### FEED-01: Feedback Custom Post Type
Register `stadion_feedback` custom post type with:
- Public: No
- Show in REST: Yes (for custom endpoints)
- REST Base: `feedback`
- Supports: title, editor, author, custom-fields
- Capability type: post (standard permissions)

#### FEED-02: ACF Field Group
Create ACF field group `group_feedback_fields` with:
- `feedback_type` (select): bug, feature_request - Required
- `status` (select): new, in_progress, resolved, declined - Required, default: new
- `priority` (select): low, medium, high, critical - Optional, default: medium
- `browser_info` (text): Auto-captured browser/OS info
- `app_version` (text): Auto-captured Stadion version
- `url_context` (text): Page URL where submitted
- `steps_to_reproduce` (textarea): For bugs only
- `expected_behavior` (textarea): For bugs only
- `actual_behavior` (textarea): For bugs only
- `use_case` (textarea): For feature requests only
- `attachments` (gallery): Screenshots/files

#### FEED-03: Feedback Statuses
Implement status workflow:
- `new` - Initial state for all submissions
- `in_progress` - Being worked on
- `resolved` - Issue fixed or feature implemented
- `declined` - Won't fix / won't implement

### REST API (Phase 2)

#### FEED-04: REST API Endpoints
Register endpoints under `stadion/v1/feedback`:
- `GET /feedback` - List feedback (paginated, filterable)
- `GET /feedback/{id}` - Get single feedback
- `POST /feedback` - Create feedback
- `PATCH /feedback/{id}` - Update feedback
- `DELETE /feedback/{id}` - Delete feedback

Query parameters for list:
- `type` - Filter by bug/feature_request
- `status` - Filter by status
- `priority` - Filter by priority
- `per_page` - Items per page (default: 10, max: 100)
- `page` - Page number
- `orderby` - date, title, priority, status
- `order` - asc, desc

#### FEED-05: Application Password Authentication
Support WordPress application passwords for API access:
- Basic Auth header: `Authorization: Basic base64(username:app_password)`
- Cookie auth for in-app requests (existing pattern)
- Both methods work for all feedback endpoints

#### FEED-06: Permission Model
Implement access control:
- Create: Any logged-in user
- Read list: Own feedback only (admins see all)
- Read single: Own or admin
- Update: Own or admin
- Delete: Own or admin

### Frontend (Phase 3)

#### FEED-07: Navigation and Routing
Add feedback to React SPA:
- Sidebar menu item: "Feedback" with MessageSquarePlus icon
- Routes: `/feedback`, `/feedback/new`, `/feedback/:id`
- Protected routes (require authentication)

#### FEED-08: Feedback List View
Create FeedbackList page:
- Display user's submitted feedback
- Columns: Title, Type badge, Status badge, Date
- Filter tabs: All, Bugs, Feature Requests
- Status filter dropdown
- Click row to view detail

#### FEED-09: Feedback Detail View
Create FeedbackDetail page:
- Full feedback content display
- Type and status badges
- Created/updated timestamps
- Conditional fields based on type
- Attachments gallery
- Edit button (own feedback only)

#### FEED-10: Submission Form
Create FeedbackForm component:
- Type selector (Bug Report / Feature Request)
- Title field (required)
- Description with rich text (required)
- Conditional fields:
  - Bug: steps_to_reproduce, expected_behavior, actual_behavior
  - Feature: use_case
- System info checkbox (opt-in)
- Attachments dropzone
- Submit / Cancel buttons

#### FEED-11: System Info Capture
Auto-capture on opt-in:
- Browser info: `navigator.userAgent` parsed to readable format
- App version: From `wpApiSettings.version`
- URL context: Current `window.location.pathname`
- Capture at submission time, not form load

#### FEED-12: File Attachments
Implement attachment handling:
- Use WordPress media library upload
- Drag-and-drop zone in form
- Preview thumbnails before submission
- Gallery display in detail view
- No custom size/count limits (WordPress defaults)

### Admin Management (Phase 4)

#### FEED-13: Admin Feedback Management UI
Add Settings subtab for feedback management:
- Location: Settings > Feedback
- List all feedback from all users
- Author column showing submitter
- Full CRUD capabilities
- Bulk actions for status changes

#### FEED-14: Status Workflow Controls
Enable status management:
- Dropdown to change status on each row
- Or detail view with status selector
- Valid transitions: new -> any, in_progress -> resolved/declined
- Status change updates modified timestamp

#### FEED-15: Priority Assignment
Enable priority management:
- Priority selector in list view or detail
- Values: low, medium, high, critical
- Visual indicators (color coding)
- Sort by priority option

#### FEED-16: Feedback Ordering
Enable sorting capabilities:
- Sort by: date, priority, status, type
- Ascending/descending toggle
- Remember sort preference in session
- Optional: drag-drop manual ordering

#### FEED-17: Application Password Management
Add Settings subtab for API access:
- Location: Settings > API Access
- Generate new application password
- Password name/description field
- List existing passwords (name, created date)
- Revoke/delete existing passwords
- One-time display of generated password with copy button

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FEED-01 | Phase 1 | Complete |
| FEED-02 | Phase 1 | Complete |
| FEED-03 | Phase 1 | Complete |
| FEED-04 | Phase 2 | Complete |
| FEED-05 | Phase 2 | Complete |
| FEED-06 | Phase 2 | Complete |
| FEED-07 | Phase 3 | Pending |
| FEED-08 | Phase 3 | Pending |
| FEED-09 | Phase 3 | Pending |
| FEED-10 | Phase 3 | Pending |
| FEED-11 | Phase 3 | Pending |
| FEED-12 | Phase 3 | Pending |
| FEED-13 | Phase 4 | Pending |
| FEED-14 | Phase 4 | Pending |
| FEED-15 | Phase 4 | Pending |
| FEED-16 | Phase 4 | Pending |
| FEED-17 | Phase 4 | Pending |

## Out of Scope (v1)

- Email notifications on status change
- Slack notifications for new feedback
- Voting/upvoting system
- External issue tracker integration (GitHub, Jira)
- Public-facing feedback portal
- Workspace-scoped feedback (global per installation)
