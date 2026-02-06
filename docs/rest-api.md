# REST API

This document describes all REST API endpoints available in Stadion, including both WordPress standard endpoints and custom endpoints.

## Authentication

All API requests require authentication via WordPress session with REST nonce.

**Headers:**
```
X-WP-Nonce: {nonce_value}
```

The nonce is automatically injected by the frontend via `window.wpApiSettings.nonce`.

## API Namespaces

Stadion uses two API namespaces:

| Namespace | Purpose |
|-----------|---------|
| `/wp/v2/` | Standard WordPress REST API for CRUD operations on post types |
| `/stadion/v1/` | Custom endpoints for dashboard, search, and specialized operations |

---

## Standard WordPress Endpoints (`/wp/v2/`)

These endpoints are provided by WordPress with access control applied:

### People

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp/v2/people` | List all accessible people |
| GET | `/wp/v2/people/{id}` | Get single person |
| POST | `/wp/v2/people` | Create new person |
| PUT | `/wp/v2/people/{id}` | Update person |
| DELETE | `/wp/v2/people/{id}` | Delete person |

### Teams

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp/v2/teams` | List all accessible teams |
| GET | `/wp/v2/teams/{id}` | Get single team |
| POST | `/wp/v2/teams` | Create new team |
| PUT | `/wp/v2/teams/{id}` | Update team |
| DELETE | `/wp/v2/teams/{id}` | Delete team |

### Taxonomies

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp/v2/person_label` | List person labels |
| GET | `/wp/v2/team_label` | List team labels |
| GET | `/wp/v2/relationship_type` | List relationship types |

---

## Custom Endpoints (`/stadion/v1/`)

These endpoints provide specialized functionality beyond basic CRUD operations.

### Dashboard

**GET** `/stadion/v1/dashboard`

Returns summary statistics and recent activity for the dashboard.

**Permission:** Logged in users only

**Response:**
```json
{
  "stats": {
    "total_people": 150,
    "total_teams": 45
  },
  "recent_people": [
    {
      "id": 123,
      "name": "John Doe",
      "first_name": "John",
      "infix": "",
      "last_name": "Doe",
      "thumbnail": "https://...",
      "is_favorite": true,
      "labels": ["Family", "Friends"]
    }
  ],
  "upcoming_reminders": [
    {
      "id": 456,
      "title": "John Doe's Birthday",
      "date_value": "2025-01-15",
      "days_until": 5,
      "is_recurring": true
    }
  ],
  "favorites": [...]
}
```

---

### Version

**GET** `/stadion/v1/version`

Returns the current theme version. Used for PWA/mobile app cache invalidation.

**Permission:** Public (no authentication required)

**Response:**
```json
{
  "version": "1.42.0"
}
```

This endpoint is called periodically by the frontend to detect when a new version has been deployed, allowing users to reload and get the latest code.

---

### Global Search

**GET** `/stadion/v1/search`

Search across people and teams.

**Permission:** Logged in users only

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `q` | string | Yes | Search query (minimum 2 characters) |

**Response:**
```json
{
  "people": [
    { "id": 1, "name": "John Doe", "thumbnail": "...", "is_favorite": true, "labels": [] }
  ],
  "teams": [
    { "id": 2, "name": "Acme Corp", "thumbnail": "...", "website": "https://...", "labels": [] }
  ]
}
```

---

### Upcoming Reminders

**GET** `/stadion/v1/reminders`

Get upcoming birthdays for reminders.

**Permission:** Logged in users only

**Parameters:**

| Name | Type | Default | Description |
|------|------|---------|-------------|
| `days_ahead` | int | 30 | Number of days to look ahead (1-365) |

**Response:**
```json
[
  {
    "id": 123,
    "title": "John's Birthday",
    "next_occurrence": "2025-01-20",
    "days_until": 10,
    "related_people": [
      { "id": 456, "name": "John Doe", "thumbnail": "..." }
    ]
  }
]
```

Birthdays are generated from the `birthdate` field on person records.

---

### People by Team

**GET** `/stadion/v1/teams/{team_id}/people`

Get all people who work or worked at a team.

**Permission:** Must have access to the team

**Response:**
```json
{
  "current": [
    {
      "id": 1,
      "name": "John Doe",
      "thumbnail": "...",
      "job_title": "CEO",
      "start_date": "2020-01-15",
      "end_date": ""
    }
  ],
  "former": [
    {
      "id": 2,
      "name": "Jane Smith",
      "thumbnail": "...",
      "job_title": "CTO",
      "start_date": "2018-03-01",
      "end_date": "2023-06-30"
    }
  ]
}
```

---

### Current User

**GET** `/stadion/v1/user/me`

Get information about the currently logged in user.

**Permission:** Logged in users only

**Response:**
```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@example.com",
  "avatar_url": "https://...",
  "is_admin": true,
  "profile_url": "https://.../wp-admin/profile.php",
  "admin_url": "https://.../wp-admin/"
}
```

---

### Person Photo Upload

**POST** `/stadion/v1/people/{person_id}/photo`

Upload and set a person's profile photo. The filename is automatically generated from the person's name.

**Permission:** Must be able to edit the person

**Content-Type:** `multipart/form-data`

**Body:**
- `file` - Image file (JPEG, PNG, GIF, WebP)

**Response:**
```json
{
  "success": true,
  "attachment_id": 789,
  "filename": "john-doe.jpg",
  "thumbnail_url": "https://...",
  "full_url": "https://..."
}
```

---

### Gravatar Sideload

**POST** `/stadion/v1/people/{person_id}/gravatar`

Fetch and set a person's Gravatar as their profile photo.

**Permission:** Must have access to the person

**Body:**
```json
{
  "email": "john@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "attachment_id": 789,
  "thumbnail_url": "https://..."
}
```

If no Gravatar exists:
```json
{
  "success": false,
  "message": "No Gravatar found for this email address"
}
```

---

### Team Logo Upload

**POST** `/stadion/v1/teams/{team_id}/logo/upload`

Upload and set a team's logo. The filename is automatically generated from the team name.

**Permission:** Must be able to edit the team

**Content-Type:** `multipart/form-data`

**Body:**
- `file` - Image file (JPEG, PNG, GIF, WebP, SVG)

**Response:**
```json
{
  "success": true,
  "attachment_id": 789,
  "filename": "acme-corp-logo.png",
  "thumbnail_url": "https://...",
  "full_url": "https://..."
}
```

---

### Set Team Logo (by Media ID)

**POST** `/stadion/v1/teams/{team_id}/logo`

Set a team's logo from an existing media library item.

**Permission:** Must be able to edit the team

**Body:**
```json
{
  "media_id": 789
}
```

**Response:**
```json
{
  "success": true,
  "media_id": 789,
  "thumbnail_url": "https://...",
  "full_url": "https://..."
}
```

---

### Restore Relationship Type Defaults

**POST** `/stadion/v1/relationship-types/restore-defaults`

Restore default inverse relationship mappings and gender-dependent configurations.

**Permission:** Logged in users only

**Response:**
```json
{
  "success": true,
  "message": "Default relationship type configurations have been restored."
}
```

---

### Workspaces

**GET** `/stadion/v1/workspaces`

List all workspaces the current user is a member of.

**Permission:** Logged in users only

**Response:**
```json
[
  {
    "id": 1,
    "name": "My Workspace",
    "description": "Shared team workspace",
    "member_count": 3,
    "role": "owner"
  }
]
```

---

**GET** `/stadion/v1/workspaces/{id}`

Get single workspace with members.

**Permission:** Must be workspace member

**Response:**
```json
{
  "id": 1,
  "name": "My Workspace",
  "description": "Shared team workspace",
  "members": [
    {
      "user_id": 1,
      "display_name": "John Doe",
      "email": "john@example.com",
      "role": "owner"
    }
  ]
}
```

---

**POST** `/stadion/v1/workspaces`

Create a new workspace.

**Permission:** Logged in users only

**Body:**
```json
{
  "name": "New Workspace",
  "description": "Optional description"
}
```

---

**PUT** `/stadion/v1/workspaces/{id}`

Update workspace details.

**Permission:** Must be workspace owner or admin

**Body:**
```json
{
  "name": "Updated Name",
  "description": "Updated description"
}
```

---

**DELETE** `/stadion/v1/workspaces/{id}`

Delete a workspace.

**Permission:** Must be workspace owner

---

### Workspace Members

**POST** `/stadion/v1/workspaces/{id}/members`

Add a member to the workspace.

**Permission:** Must be workspace owner or admin

**Body:**
```json
{
  "user_id": 123,
  "role": "member"
}
```

---

**PUT** `/stadion/v1/workspaces/{id}/members/{user_id}`

Update member role.

**Permission:** Must be workspace owner or admin

**Body:**
```json
{
  "role": "admin"
}
```

---

**DELETE** `/stadion/v1/workspaces/{id}/members/{user_id}`

Remove a member from the workspace.

**Permission:** Must be workspace owner or admin

---

### Workspace Invites

**GET** `/stadion/v1/workspaces/{id}/invites`

List pending invites for a workspace.

**Permission:** Must be workspace owner or admin

---

**POST** `/stadion/v1/workspaces/{id}/invites`

Create and send an email invitation.

**Permission:** Must be workspace owner or admin

**Body:**
```json
{
  "email": "newuser@example.com",
  "role": "member"
}
```

---

**DELETE** `/stadion/v1/workspaces/{id}/invites/{invite_id}`

Revoke a pending invite.

**Permission:** Must be workspace owner or admin

---

**GET** `/stadion/v1/invites/{token}`

Validate an invite token (public endpoint).

**Permission:** Public (no authentication required)

**Response:**
```json
{
  "valid": true,
  "workspace_name": "Team Workspace",
  "invited_by": "John Doe",
  "role": "member"
}
```

---

**POST** `/stadion/v1/invites/{token}/accept`

Accept an invite and join the workspace.

**Permission:** Must be logged in

---

### Direct Sharing (People)

**GET** `/stadion/v1/people/{id}/shares`

Get list of users a person is shared with.

**Permission:** Must be post owner

**Response:**
```json
[
  {
    "user_id": 123,
    "display_name": "Jane Smith",
    "email": "jane@example.com",
    "avatar_url": "https://...",
    "permission": "view"
  }
]
```

---

**POST** `/stadion/v1/people/{id}/shares`

Share a person with another user.

**Permission:** Must be post owner

**Body:**
```json
{
  "user_id": 123,
  "permission": "view"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Shared successfully."
}
```

---

**DELETE** `/stadion/v1/people/{id}/shares/{user_id}`

Remove sharing from a user.

**Permission:** Must be post owner

**Response:**
```json
{
  "success": true,
  "message": "Share removed."
}
```

---

### Direct Sharing (Teams)

**GET** `/stadion/v1/teams/{id}/shares`

Get list of users a team is shared with.

**Permission:** Must be post owner

**Response:** Same format as People shares.

---

**POST** `/stadion/v1/teams/{id}/shares`

Share a team with another user.

**Permission:** Must be post owner

**Body:** Same format as People shares.

---

**DELETE** `/stadion/v1/teams/{id}/shares/{user_id}`

Remove sharing from a user.

**Permission:** Must be post owner

---

### User Search

**GET** `/stadion/v1/users/search`

Search for users to share with.

**Permission:** Logged in users only

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `q` | string | Yes | Search query (minimum 2 characters) |

**Response:**
```json
[
  {
    "id": 123,
    "display_name": "Jane Smith",
    "email": "jane@example.com",
    "avatar_url": "https://..."
  }
]
```

Note: The current user is automatically excluded from search results.

---

### Mention Notifications Preference

**POST** `/stadion/v1/user/mention-notifications`

Update the user's preference for @mention notifications.

**Permission:** Logged in users only

**Body:**
```json
{
  "preference": "digest"
}
```

**Valid values:**
- `digest` - Include mentions in daily digest (default)
- `immediate` - Send email notification immediately when mentioned
- `never` - Do not notify me of mentions

**Response:**
```json
{
  "success": true,
  "mention_notifications": "digest"
}
```

The preference is also returned by GET `/stadion/v1/user/notification-channels` as part of the response:
```json
{
  "channels": ["email"],
  "notification_time": "09:00",
  "mention_notifications": "digest"
}
```

---

### Workspace Member Search

**GET** `/stadion/v1/workspaces/members/search`

Search for workspace members for @mention autocomplete.

**Permission:** Logged in users only

**Parameters:**

| Name | Type | Required | Description |
|------|------|----------|-------------|
| `workspace_ids` | string | Yes | Comma-separated workspace IDs |
| `query` | string | Yes | Search query for member names |

**Response:**
```json
[
  {
    "id": 123,
    "name": "Jane Smith",
    "email": "jane@example.com"
  }
]
```

---

## Response Enhancements

### Person Relationships Expansion

The `rest_prepare_person` filter automatically expands relationship data in person responses:

```json
{
  "acf": {
    "relationships": [
      {
        "related_person": 123,
        "person_name": "Jane Doe",
        "person_thumbnail": "https://...",
        "relationship_type": 5,
        "relationship_name": "Spouse",
        "relationship_slug": "spouse",
        "relationship_label": ""
      }
    ]
  }
}
```

### ACF Fields on Relationship Types

Relationship type taxonomy terms include ACF fields in their REST response:

```json
{
  "id": 5,
  "name": "Parent",
  "slug": "parent",
  "acf": {
    "inverse_relationship_type": 6,
    "is_gender_dependent": false,
    "gender_dependent_group": ""
  }
}
```

---

## Error Responses

All endpoints return standard WordPress REST error format:

```json
{
  "code": "rest_forbidden",
  "message": "You do not have permission to access this item.",
  "data": {
    "status": 403
  }
}
```

Common error codes:

| Code | Status | Description |
|------|--------|-------------|
| `rest_forbidden` | 403 | Access denied |
| `rest_not_found` | 404 | Resource not found |
| `rest_invalid_param` | 400 | Invalid parameter |
| `not_logged_in` | 401 | Authentication required |

---

## Related Documentation

- [Access Control](./access-control.md) - How permissions work
- [Data Model](./data-model.md) - Post types and fields
- [Import](./import.md) - Contact import endpoints
- [VOG Filtered People](./api-vog-filtered-people.md) - VOG tab endpoint with KNVB IDs and volunteer filters

