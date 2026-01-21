# PRD: Feedback System (Bugs & Feature Requests)

## Overview

Add a feedback system to Caelis that allows users to submit bug reports and feature requests directly from within the application. These submissions will be stored as WordPress posts and exposed via an authenticated REST API using WordPress native application passwords.

## Problem Statement

Currently, there's no built-in mechanism for users to report bugs or request features. Feedback is collected through external channels (email, Slack, etc.), making it difficult to track, prioritize, and manage. A native feedback system would:

- Centralize all feedback in one place
- Enable programmatic access for integration with project management tools
- Provide a consistent submission experience for users
- Allow secure, authenticated API access without exposing user sessions

## Goals

1. Allow users to submit bugs and feature requests from within Caelis
2. Store feedback as WordPress custom post types
3. Expose feedback through REST API endpoints
4. Support authentication via WordPress application passwords
5. Keep the system simple and maintainable

## Non-Goals

- Public-facing feedback portal
- Voting/upvoting system (can be added later)
- Email notifications on submission (can use existing reminder system)
- Integration with external issue trackers (GitHub, Jira, etc.)

---

## Functional Requirements

### 1. Feedback Post Type

Create a new custom post type `caelis_feedback` with the following characteristics:

| Property | Value |
|----------|-------|
| Post Type Slug | `caelis_feedback` |
| Singular | Feedback |
| Plural | Feedback |
| Public | No |
| Show in REST | Yes |
| REST Base | `feedback` |
| Supports | title, editor, author, custom-fields |

### 2. Feedback Fields (ACF)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `feedback_type` | Select | Yes | `bug` or `feature_request` |
| `status` | Select | Yes | `new`, `acknowledged`, `in_progress`, `resolved`, `wont_fix` |
| `priority` | Select | No | `low`, `medium`, `high`, `critical` |
| `browser_info` | Text | No | Auto-captured browser/OS info |
| `app_version` | Text | No | Auto-captured Caelis version |
| `url_context` | Text | No | Page URL where feedback was submitted |
| `steps_to_reproduce` | Textarea | No | For bugs: reproduction steps |
| `expected_behavior` | Textarea | No | For bugs: what should happen |
| `actual_behavior` | Textarea | No | For bugs: what actually happens |
| `use_case` | Textarea | No | For features: why this is needed |
| `attachments` | Gallery | No | Screenshots or files |

### 3. User Interface

#### 3.1 Feedback Button

Add a persistent feedback button in the application UI:

- **Location**: Fixed position, bottom-right corner (or in the user menu)
- **Icon**: Bug icon with dropdown or modal trigger
- **Visibility**: All logged-in users

#### 3.2 Feedback Modal

A modal dialog for submitting feedback:

```
┌─────────────────────────────────────────────────┐
│ Submit Feedback                              ✕  │
├─────────────────────────────────────────────────┤
│                                                 │
│ Type: ○ Bug Report  ○ Feature Request           │
│                                                 │
│ Title *                                         │
│ ┌─────────────────────────────────────────────┐ │
│ │                                             │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ Description *                                   │
│ ┌─────────────────────────────────────────────┐ │
│ │                                             │ │
│ │                                             │ │
│ │                                             │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ [Bug-specific fields shown when type=bug]       │
│ [Feature-specific fields shown when type=feat]  │
│                                                 │
│ Attachments (optional)                          │
│ ┌─────────────────────────────────────────────┐ │
│ │  Drop files or click to upload              │ │
│ └─────────────────────────────────────────────┘ │
│                                                 │
│ ☑ Include system info (browser, version, URL)   │
│                                                 │
│                        [Cancel]  [Submit]       │
└─────────────────────────────────────────────────┘
```

#### 3.3 Confirmation

After successful submission:
- Show success message with feedback reference number (post ID)
- Optionally link to view submitted feedback

### 4. REST API Endpoints

All endpoints under namespace `prm/v1/feedback`.

#### 4.1 List Feedback

```
GET /wp-json/prm/v1/feedback
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `type` | string | Filter by `bug` or `feature_request` |
| `status` | string | Filter by status |
| `priority` | string | Filter by priority |
| `per_page` | int | Items per page (default: 10, max: 100) |
| `page` | int | Page number |
| `orderby` | string | `date`, `title`, `priority`, `status` |
| `order` | string | `asc` or `desc` |

**Response:**

```json
{
  "items": [
    {
      "id": 123,
      "title": "Search doesn't find people by nickname",
      "content": "When I search for...",
      "type": "bug",
      "status": "new",
      "priority": "high",
      "browser_info": "Chrome 120, macOS 14.2",
      "app_version": "2.4.0",
      "url_context": "/people",
      "steps_to_reproduce": "1. Go to search...",
      "expected_behavior": "Should find the person",
      "actual_behavior": "No results shown",
      "attachments": [
        {
          "id": 456,
          "url": "https://...",
          "filename": "screenshot.png"
        }
      ],
      "author": {
        "id": 1,
        "name": "Joost",
        "email": "joost@emilia.capital"
      },
      "created_at": "2026-01-21T10:30:00Z",
      "updated_at": "2026-01-21T10:30:00Z"
    }
  ],
  "total": 42,
  "pages": 5
}
```

#### 4.2 Get Single Feedback

```
GET /wp-json/prm/v1/feedback/{id}
```

Returns single feedback item with same structure as list item.

#### 4.3 Create Feedback

```
POST /wp-json/prm/v1/feedback
```

**Request Body:**

```json
{
  "title": "Add dark mode support",
  "content": "It would be great to have a dark mode...",
  "type": "feature_request",
  "use_case": "I work late at night and...",
  "include_system_info": true
}
```

**Response:** Created feedback object with `id`.

#### 4.4 Update Feedback

```
PATCH /wp-json/prm/v1/feedback/{id}
```

**Request Body:** Partial update with any feedback fields.

**Permissions:** Author can update their own; admins can update any.

#### 4.5 Delete Feedback

```
DELETE /wp-json/prm/v1/feedback/{id}
```

**Permissions:** Author can delete their own; admins can delete any.

### 5. Authentication

#### 5.1 Application Passwords

Use WordPress native application passwords for API authentication:

- Users generate application passwords via **Users → Profile → Application Passwords**
- Or via the Caelis Settings page (add a UI for this)

#### 5.2 Authentication Methods

Support both authentication methods:

**Basic Auth (Application Password):**

```
Authorization: Basic base64(username:application_password)
```

**Cookie Auth (for in-app requests):**

```
X-WP-Nonce: {nonce}
```

#### 5.3 Permissions

| Action | Cookie Auth | Application Password |
|--------|-------------|---------------------|
| Create feedback | ✅ Any logged-in user | ✅ Any valid app password |
| List feedback | ✅ Own feedback | ✅ Own feedback (admins see all) |
| Update feedback | ✅ Own + admins | ✅ Own + admins |
| Delete feedback | ✅ Own + admins | ✅ Own + admins |

---

## Technical Design

### 1. Backend Components

#### 1.1 Post Type Registration

Add to `class-post-types.php`:

```php
'caelis_feedback' => [
    'label' => 'Feedback',
    'public' => false,
    'show_in_rest' => true,
    'rest_base' => 'feedback',
    'supports' => ['title', 'editor', 'author', 'custom-fields'],
    'capability_type' => 'post',
    'map_meta_cap' => true,
]
```

#### 1.2 REST Controller

Create `class-rest-feedback.php`:

- Extend existing REST patterns in the codebase
- Register routes under `prm/v1/feedback`
- Implement CRUD operations with ACF field handling
- Add application password authentication check

#### 1.3 ACF Field Group

Create `acf-json/group_feedback_fields.json`:

- Define all feedback fields
- Set location rule to `caelis_feedback` post type
- Configure conditional logic for bug vs feature request fields

### 2. Frontend Components

#### 2.1 FeedbackButton Component

```
src/components/FeedbackButton.jsx
```

- Floating action button
- Opens feedback modal on click

#### 2.2 FeedbackModal Component

```
src/components/modals/FeedbackModal.jsx
```

- Form with dynamic fields based on type
- File upload for attachments
- System info capture (opt-in)
- Submission handling

#### 2.3 API Client Extension

Add to `src/api/client.js`:

```javascript
feedback: {
  list: (params) => get('/prm/v1/feedback', params),
  get: (id) => get(`/prm/v1/feedback/${id}`),
  create: (data) => post('/prm/v1/feedback', data),
  update: (id, data) => patch(`/prm/v1/feedback/${id}`, data),
  delete: (id) => del(`/prm/v1/feedback/${id}`),
}
```

### 3. Application Password UI

Add to Settings page:

- Section for managing application passwords
- Generate new password with name/description
- List existing passwords with revoke option
- Copy password to clipboard on creation

---

## Data Model

### Post Structure

| WordPress Field | Maps To |
|-----------------|---------|
| `post_title` | Feedback title |
| `post_content` | Feedback description |
| `post_author` | Submitting user |
| `post_status` | Always `publish` |
| `post_date` | Creation timestamp |

### Meta Fields (ACF)

| Meta Key | Type | Default |
|----------|------|---------|
| `feedback_type` | string | - |
| `status` | string | `new` |
| `priority` | string | `medium` |
| `browser_info` | string | - |
| `app_version` | string | - |
| `url_context` | string | - |
| `steps_to_reproduce` | string | - |
| `expected_behavior` | string | - |
| `actual_behavior` | string | - |
| `use_case` | string | - |
| `attachments` | array | `[]` |

---

## Security Considerations

1. **Application Passwords**: Use WordPress native implementation; passwords are hashed and never exposed after creation

2. **Rate Limiting**: Consider adding rate limiting for feedback creation (e.g., max 10 per hour per user)

3. **Input Sanitization**: All text fields sanitized with `sanitize_text_field()` or `wp_kses_post()` for rich content

4. **File Uploads**: Use WordPress media library; validate file types (images, PDFs only)

5. **Access Control**: Users can only see/edit their own feedback unless admin

---

## Success Metrics

- Number of feedback items submitted per week
- Ratio of bugs to feature requests
- Average time from submission to status change
- API usage (requests via application passwords vs in-app)

---

## Open Questions

1. Should feedback be workspace-scoped or global per installation?
2. Should there be an admin UI for managing feedback, or is API access sufficient?
3. Should we add email notifications when feedback status changes?
4. Should attachments be limited in size/count?

---

## Appendix

### Example API Usage

**Creating feedback with curl:**

```bash
curl -X POST \
  -u "joost:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Calendar sync fails for recurring events",
    "content": "When syncing with Google Calendar...",
    "type": "bug",
    "priority": "high",
    "steps_to_reproduce": "1. Connect Google Calendar\n2. Create recurring event\n3. Trigger sync",
    "expected_behavior": "Event should appear in Caelis",
    "actual_behavior": "Only first occurrence syncs"
  }' \
  https://your-site.com/wp-json/prm/v1/feedback
```

**Listing feedback with filtering:**

```bash
curl -u "joost:xxxx xxxx xxxx xxxx xxxx xxxx" \
  "https://your-site.com/wp-json/prm/v1/feedback?type=bug&status=new&priority=high"
```
