# Teams API Documentation

This document describes how to use the Stadion REST API to manage teams (organizations, companies, etc.).

## Base URL

All endpoints are relative to your WordPress installation:
```
https://your-site.com/wp-json/
```

## Authentication

The API supports two authentication methods:

### Method 1: Application Password (Recommended for External Integrations)

Use HTTP Basic Authentication with a WordPress Application Password. This is the recommended method for scripts, external services, and API integrations.

1. Generate an Application Password in WordPress: **Users → Profile → Application Passwords**
2. Use your WordPress username and the generated password (with spaces)

```bash
curl -X GET "https://your-site.com/wp-json/wp/v2/teams" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"
```

Or with the `Authorization` header:

```bash
curl -X GET "https://your-site.com/wp-json/wp/v2/teams" \
  -H "Authorization: Basic $(echo -n 'username:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)"
```

### Method 2: Session + Nonce (Browser Use)

For requests from the Stadion frontend (same browser session), use the REST nonce:

```
X-WP-Nonce: {nonce_value}
```

The nonce is available in `window.stadionConfig.nonce` when logged in to Stadion.

---

**Access Control:** Users can only see and modify teams they created themselves. Sharing and workspace visibility can extend access to other users.

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/wp/v2/teams` | List all accessible teams |
| `GET` | `/wp/v2/teams/{id}` | Get single team |
| `POST` | `/wp/v2/teams` | Create new team |
| `PUT` | `/wp/v2/teams/{id}` | Update team |
| `DELETE` | `/wp/v2/teams/{id}` | Delete team |
| `POST` | `/rondo/v1/teams/{id}/logo/upload` | Upload team logo |
| `POST` | `/rondo/v1/teams/{id}/logo` | Set logo from media library |
| `GET` | `/rondo/v1/teams/{id}/people` | Get people associated with team |
| `GET` | `/rondo/v1/teams/{id}/shares` | Get users team is shared with |
| `POST` | `/rondo/v1/teams/{id}/shares` | Share team with user |
| `DELETE` | `/rondo/v1/teams/{id}/shares/{user_id}` | Remove share |

---

## Field Reference

### Basic Information

| Field | Type | Description |
|-------|------|-------------|
| `title` | object | Team name (rendered as `title.rendered`) |
| `content` | object | Team description (rendered as `content.rendered`) |
| `featured_media` | integer | Logo attachment ID |

### ACF Fields

| Field | Type | Description | Format |
|-------|------|-------------|--------|
| `acf.website` | string | Team website URL | Full URL |
| `acf.contact_info` | array | Contact methods (repeater) | See below |

### Contact Info (Repeater)

```json
"acf": {
  "contact_info": [
    {
      "contact_type": "email",
      "contact_label": "Algemeen",
      "contact_value": "info@team.nl"
    },
    {
      "contact_type": "phone",
      "contact_label": "Receptie",
      "contact_value": "+31 20 123 4567"
    }
  ]
}
```

**Contact Types:**
- `phone` - Telefoon
- `email` - E-mailadres
- `address` - Adres
- `other` - Anders

### Visibility

| Field | Type | Description | Values |
|-------|------|-------------|--------|
| `acf._visibility` | string | Who can see this team | `private`, `workspace`, `shared` |
| `acf._assigned_workspaces` | array | Workspace term IDs | `[1, 2, 3]` |

---

## Create a Team

**Request:**
```http
POST /wp/v2/teams
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "status": "publish",
  "title": "Ajax Amsterdam",
  "content": "Professionele voetbalclub uit Amsterdam",
  "acf": {
    "website": "https://www.ajax.nl",
    "contact_info": [
      {
        "contact_type": "email",
        "contact_label": "Algemeen",
        "contact_value": "info@ajax.nl"
      },
      {
        "contact_type": "phone",
        "contact_label": "Stadion",
        "contact_value": "+31 20 311 1444"
      },
      {
        "contact_type": "address",
        "contact_label": "Johan Cruijff ArenA",
        "contact_value": "ArenA Boulevard 1, 1101 AX Amsterdam"
      }
    ],
    "_visibility": "private"
  }
}
```

**Response (201 Created):**
```json
{
  "id": 789,
  "date": "2026-01-26T10:00:00",
  "slug": "ajax-amsterdam",
  "status": "publish",
  "type": "team",
  "title": {
    "rendered": "Ajax Amsterdam"
  },
  "content": {
    "rendered": "<p>Professionele voetbalclub uit Amsterdam</p>"
  },
  "author": 1,
  "featured_media": 0,
  "acf": {
    "website": "https://www.ajax.nl",
    "contact_info": [...],
    "_visibility": "private",
    "_assigned_workspaces": []
  }
}
```

---

## Update a Team

**Request:**
```http
PUT /wp/v2/teams/789
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body (partial update):**
```json
{
  "acf": {
    "website": "https://www.ajax.nl/nl/",
    "contact_info": [
      {
        "contact_type": "email",
        "contact_label": "Klantenservice",
        "contact_value": "service@ajax.nl"
      }
    ]
  }
}
```

**Important:** When updating repeater fields (`contact_info`), you must send the complete array. Partial updates will replace the entire field.

**Response (200 OK):**
Returns the full updated team object.

---

## Get a Team

**Request:**
```http
GET /wp/v2/teams/789
X-WP-Nonce: {nonce}
```

**Response:**
```json
{
  "id": 789,
  "title": { "rendered": "Ajax Amsterdam" },
  "content": { "rendered": "<p>Professionele voetbalclub uit Amsterdam</p>" },
  "featured_media": 123,
  "acf": {
    "website": "https://www.ajax.nl",
    "contact_info": [...],
    "_visibility": "private",
    "_assigned_workspaces": []
  }
}
```

---

## List Teams

**Request:**
```http
GET /wp/v2/teams?per_page=20&page=1
X-WP-Nonce: {nonce}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | int | 10 | Items per page (max: 100) |
| `page` | int | 1 | Page number |
| `search` | string | - | Search in name |
| `orderby` | string | date | Sort by: `date`, `title`, `modified` |
| `order` | string | desc | Sort order: `asc` or `desc` |
| `_fields` | string | - | Limit fields returned (comma-separated) |

**Example - Search for teams:**
```http
GET /wp/v2/teams?search=Ajax&per_page=50
```

**Example - Get only IDs and names (faster):**
```http
GET /wp/v2/teams?_fields=id,title,acf.website
```

---

## Delete a Team

**Request:**
```http
DELETE /wp/v2/teams/789
X-WP-Nonce: {nonce}
```

**Response (200 OK):**
```json
{
  "deleted": true,
  "previous": { ... }
}
```

---

## Upload Team Logo

Upload and set a team's logo. The filename is automatically generated from the team name.

**Request:**
```http
POST /rondo/v1/teams/789/logo/upload
Content-Type: multipart/form-data
X-WP-Nonce: {nonce}
```

**Form Data:**
- `file`: Image file (JPEG, PNG, GIF, WebP, SVG)

**Response:**
```json
{
  "success": true,
  "attachment_id": 456,
  "filename": "ajax-amsterdam-logo.png",
  "thumbnail_url": "https://your-site.com/wp-content/uploads/2026/01/ajax-amsterdam-logo-150x150.png",
  "full_url": "https://your-site.com/wp-content/uploads/2026/01/ajax-amsterdam-logo.png"
}
```

---

## Set Team Logo (by Media ID)

Set a team's logo from an existing media library item.

**Request:**
```http
POST /rondo/v1/teams/789/logo
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "media_id": 456
}
```

**Response:**
```json
{
  "success": true,
  "media_id": 456,
  "thumbnail_url": "https://your-site.com/wp-content/uploads/2026/01/logo-150x150.png",
  "full_url": "https://your-site.com/wp-content/uploads/2026/01/logo.png"
}
```

---

## Get People by Team

Get all people who work or worked at a team.

**Request:**
```http
GET /rondo/v1/teams/789/people
X-WP-Nonce: {nonce}
```

**Response:**
```json
{
  "current": [
    {
      "id": 123,
      "name": "Johan Cruijff",
      "thumbnail": "https://...",
      "job_title": "Technisch Directeur",
      "start_date": "2020-01-15",
      "end_date": ""
    }
  ],
  "former": [
    {
      "id": 456,
      "name": "Marco van Basten",
      "thumbnail": "https://...",
      "job_title": "Speler",
      "start_date": "1982-04-01",
      "end_date": "1987-06-30"
    }
  ]
}
```

---

## Direct Sharing

### Get Team Shares

**Request:**
```http
GET /rondo/v1/teams/789/shares
X-WP-Nonce: {nonce}
```

**Permission:** Must be team owner

**Response:**
```json
[
  {
    "user_id": 5,
    "display_name": "Jan Jansen",
    "email": "jan@example.com",
    "avatar_url": "https://...",
    "permission": "view"
  }
]
```

### Share Team with User

**Request:**
```http
POST /rondo/v1/teams/789/shares
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Permission:** Must be team owner

**Body:**
```json
{
  "user_id": 5,
  "permission": "view"
}
```

**Permission values:**
- `view` - Can view the team
- `edit` - Can view and edit the team

**Response:**
```json
{
  "success": true,
  "message": "Shared successfully."
}
```

### Remove Share

**Request:**
```http
DELETE /rondo/v1/teams/789/shares/5
X-WP-Nonce: {nonce}
```

**Permission:** Must be team owner

**Response:**
```json
{
  "success": true,
  "message": "Share removed."
}
```

---

## Error Handling

**Common Error Responses:**

**401 Unauthorized:**
```json
{
  "code": "rest_not_logged_in",
  "message": "You are not currently logged in.",
  "data": { "status": 401 }
}
```

**403 Forbidden:**
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to edit this team.",
  "data": { "status": 403 }
}
```

**404 Not Found:**
```json
{
  "code": "rest_post_invalid_id",
  "message": "Invalid team ID.",
  "data": { "status": 404 }
}
```

---

## Code Examples

### JavaScript/TypeScript (fetch)

```javascript
const API_BASE = 'https://your-site.com/wp-json';
const nonce = window.stadionConfig?.nonce || 'your-nonce';

// Create a team
async function createTeam(data) {
  const response = await fetch(`${API_BASE}/wp/v2/teams`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: JSON.stringify({
      status: 'publish',
      title: data.name,
      content: data.description || '',
      acf: {
        website: data.website,
        contact_info: data.contacts || [],
      },
    }),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

// Update a team
async function updateTeam(id, data) {
  const response = await fetch(`${API_BASE}/wp/v2/teams/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: JSON.stringify(data),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

// Upload team logo
async function uploadTeamLogo(teamId, file) {
  const formData = new FormData();
  formData.append('file', file);

  const response = await fetch(`${API_BASE}/rondo/v1/teams/${teamId}/logo/upload`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: formData,
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

// Get people by team
async function getPeopleByTeam(teamId) {
  const response = await fetch(`${API_BASE}/rondo/v1/teams/${teamId}/people`, {
    headers: {
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

// Usage
const newTeam = await createTeam({
  name: 'Ajax Amsterdam',
  description: 'Professionele voetbalclub',
  website: 'https://www.ajax.nl',
  contacts: [
    { contact_type: 'email', contact_label: 'Algemeen', contact_value: 'info@ajax.nl' }
  ],
});

console.log('Created team:', newTeam.id);
```

### PHP (WordPress context)

```php
<?php
// Create a team programmatically
$team_id = wp_insert_post([
    'post_type'   => 'team',
    'post_status' => 'publish',
    'post_title'  => 'Ajax Amsterdam',
    'post_content' => 'Professionele voetbalclub uit Amsterdam',
    'post_author' => get_current_user_id(),
]);

if ($team_id && !is_wp_error($team_id)) {
    // Set ACF fields
    update_field('website', 'https://www.ajax.nl', $team_id);

    // Set contact info (repeater)
    update_field('contact_info', [
        [
            'contact_type'  => 'email',
            'contact_label' => 'Algemeen',
            'contact_value' => 'info@ajax.nl',
        ],
        [
            'contact_type'  => 'phone',
            'contact_label' => 'Receptie',
            'contact_value' => '+31 20 123 4567',
        ],
    ], $team_id);

    // Set visibility
    update_field('_visibility', 'private', $team_id);
}

// Get all teams for current user
$teams = get_posts([
    'post_type'      => 'team',
    'posts_per_page' => -1,
    'author'         => get_current_user_id(),
]);

foreach ($teams as $team) {
    $website = get_field('website', $team->ID);
    echo $team->post_title . ': ' . $website . "\n";
}
```

### cURL (with Application Password)

```bash
# Create a team
curl -X POST "https://your-site.com/wp-json/wp/v2/teams" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "publish",
    "title": "Ajax Amsterdam",
    "content": "Professionele voetbalclub uit Amsterdam",
    "acf": {
      "website": "https://www.ajax.nl",
      "contact_info": [
        {
          "contact_type": "email",
          "contact_label": "Algemeen",
          "contact_value": "info@ajax.nl"
        }
      ]
    }
  }'

# Update a team
curl -X PUT "https://your-site.com/wp-json/wp/v2/teams/789" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "acf": {
      "website": "https://www.ajax.nl/nl/"
    }
  }'

# Get people by team
curl -X GET "https://your-site.com/wp-json/rondo/v1/teams/789/people" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"

# Upload team logo
curl -X POST "https://your-site.com/wp-json/rondo/v1/teams/789/logo/upload" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -F "file=@logo.png"

# List teams
curl -X GET "https://your-site.com/wp-json/wp/v2/teams?per_page=10" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"

# Delete a team
curl -X DELETE "https://your-site.com/wp-json/wp/v2/teams/789" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"
```

---

## Notes

1. **Title Field:** Unlike People (which use ACF for first/last name), Teams use the standard WordPress post title.

2. **Description:** Team descriptions use the WordPress `content` field (editor), not an ACF field.

3. **Logo:** Team logos use the WordPress featured image system (`featured_media`). Use the logo upload endpoints for easy management.

4. **Repeater Fields:** When updating `contact_info`, always send the complete array. WordPress will replace the entire field.

5. **Access Control:** Each user only sees teams they created. Use visibility settings and sharing to extend access.

6. **People Association:** People are linked to teams via the person's `work_history` repeater field, not stored on the team itself.

---

*Documentation generated: 2026-01-26*
