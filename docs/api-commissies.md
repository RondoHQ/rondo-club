# Commissies (Committees) API Documentation

This document describes how to use the Stadion REST API to manage commissies (committees).

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
curl -X GET "https://your-site.com/wp-json/wp/v2/commissies" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"
```

Or with the `Authorization` header:

```bash
curl -X GET "https://your-site.com/wp-json/wp/v2/commissies" \
  -H "Authorization: Basic $(echo -n 'username:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)"
```

### Method 2: Session + Nonce (Browser Use)

For requests from the Stadion frontend (same browser session), use the REST nonce:

```
X-WP-Nonce: {nonce_value}
```

The nonce is available in `window.stadionConfig.nonce` when logged in to Stadion.

---

**Access Control:** Users can only see and modify commissies they created themselves. Sharing and workspace visibility can extend access to other users.

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/wp/v2/commissies` | List all accessible commissies |
| `GET` | `/wp/v2/commissies/{id}` | Get single commissie |
| `POST` | `/wp/v2/commissies` | Create new commissie |
| `PUT` | `/wp/v2/commissies/{id}` | Update commissie |
| `DELETE` | `/wp/v2/commissies/{id}` | Delete commissie |

---

## Field Reference

### Basic Information

| Field | Type | Description |
|-------|------|-------------|
| `title` | object | Commissie name (rendered as `title.rendered`) |
| `content` | object | Commissie description (rendered as `content.rendered`) |
| `featured_media` | integer | Logo/image attachment ID |
| `parent` | integer | Parent commissie ID (for hierarchical structure) |

### ACF Fields

| Field | Type | Description | Format |
|-------|------|-------------|--------|
| `acf.website` | string | Commissie website URL | Full URL |
| `acf.contact_info` | array | Contact methods (repeater) | See below |

### Contact Info (Repeater)

```json
"acf": {
  "contact_info": [
    {
      "contact_type": "email",
      "contact_label": "Voorzitter",
      "contact_value": "voorzitter@commissie.nl"
    },
    {
      "contact_type": "phone",
      "contact_label": "Secretaris",
      "contact_value": "+31 6 12345678"
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
| `acf._visibility` | string | Who can see this commissie | `private`, `workspace`, `shared` |
| `acf._assigned_workspaces` | array | Workspace term IDs | `[1, 2, 3]` |

### Hierarchical Structure

Commissies support parent-child relationships. Use the `parent` field to create subcommissies:

| Field | Type | Description |
|-------|------|-------------|
| `parent` | integer | Parent commissie ID (0 for top-level) |
| `menu_order` | integer | Display order within siblings |

---

## Create a Commissie

**Request:**
```http
POST /wp/v2/commissies
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "status": "publish",
  "title": "Jeugdcommissie",
  "content": "Verantwoordelijk voor alle jeugdactiviteiten en -evenementen.",
  "parent": 0,
  "acf": {
    "website": "https://club.nl/jeugd",
    "contact_info": [
      {
        "contact_type": "email",
        "contact_label": "Voorzitter",
        "contact_value": "jeugd@club.nl"
      }
    ],
    "_visibility": "private"
  }
}
```

**Response (201 Created):**
```json
{
  "id": 456,
  "date": "2026-01-26T10:00:00",
  "slug": "jeugdcommissie",
  "status": "publish",
  "type": "commissie",
  "title": {
    "rendered": "Jeugdcommissie"
  },
  "content": {
    "rendered": "<p>Verantwoordelijk voor alle jeugdactiviteiten en -evenementen.</p>"
  },
  "author": 1,
  "parent": 0,
  "featured_media": 0,
  "acf": {
    "website": "https://club.nl/jeugd",
    "contact_info": [...],
    "_visibility": "private",
    "_assigned_workspaces": []
  }
}
```

---

## Create a Subcommissie

To create a subcommissie, set the `parent` field to the parent commissie's ID:

**Request:**
```http
POST /wp/v2/commissies
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "status": "publish",
  "title": "Jeugd A-selectie",
  "content": "Commissie voor de A-junioren",
  "parent": 456,
  "menu_order": 1,
  "acf": {
    "contact_info": [
      {
        "contact_type": "email",
        "contact_label": "Coordinator",
        "contact_value": "a-selectie@club.nl"
      }
    ],
    "_visibility": "private"
  }
}
```

---

## Update a Commissie

**Request:**
```http
PUT /wp/v2/commissies/456
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body (partial update):**
```json
{
  "content": "Nieuwe beschrijving van de commissie",
  "acf": {
    "website": "https://club.nl/jeugd/nieuw"
  }
}
```

**Important:** When updating repeater fields (`contact_info`), you must send the complete array. Partial updates will replace the entire field.

**Response (200 OK):**
Returns the full updated commissie object.

---

## Get a Commissie

**Request:**
```http
GET /wp/v2/commissies/456
X-WP-Nonce: {nonce}
```

**Response:**
```json
{
  "id": 456,
  "title": { "rendered": "Jeugdcommissie" },
  "content": { "rendered": "<p>Verantwoordelijk voor alle jeugdactiviteiten.</p>" },
  "parent": 0,
  "featured_media": 0,
  "acf": {
    "website": "https://club.nl/jeugd",
    "contact_info": [...],
    "_visibility": "private",
    "_assigned_workspaces": []
  }
}
```

---

## List Commissies

**Request:**
```http
GET /wp/v2/commissies?per_page=20&page=1
X-WP-Nonce: {nonce}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | int | 10 | Items per page (max: 100) |
| `page` | int | 1 | Page number |
| `search` | string | - | Search in name |
| `parent` | int | - | Filter by parent ID (0 for top-level) |
| `orderby` | string | date | Sort by: `date`, `title`, `modified`, `menu_order` |
| `order` | string | desc | Sort order: `asc` or `desc` |
| `_fields` | string | - | Limit fields returned (comma-separated) |

**Example - Get top-level commissies only:**
```http
GET /wp/v2/commissies?parent=0&orderby=menu_order&order=asc
```

**Example - Get subcommissies of a parent:**
```http
GET /wp/v2/commissies?parent=456
```

**Example - Search for commissies:**
```http
GET /wp/v2/commissies?search=jeugd&per_page=50
```

**Example - Get only IDs and names (faster):**
```http
GET /wp/v2/commissies?_fields=id,title,parent,acf.website
```

---

## Delete a Commissie

**Request:**
```http
DELETE /wp/v2/commissies/456
X-WP-Nonce: {nonce}
```

**Note:** Deleting a parent commissie does not automatically delete its subcommissies. Child commissies will become orphaned (parent=0).

**Response (200 OK):**
```json
{
  "deleted": true,
  "previous": { ... }
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
  "message": "Sorry, you are not allowed to edit this commissie.",
  "data": { "status": 403 }
}
```

**404 Not Found:**
```json
{
  "code": "rest_post_invalid_id",
  "message": "Invalid commissie ID.",
  "data": { "status": 404 }
}
```

---

## Code Examples

### JavaScript/TypeScript (fetch)

```javascript
const API_BASE = 'https://your-site.com/wp-json';
const nonce = window.stadionConfig?.nonce || 'your-nonce';

// Create a commissie
async function createCommissie(data) {
  const response = await fetch(`${API_BASE}/wp/v2/commissies`, {
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
      parent: data.parentId || 0,
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

// Get commissie hierarchy (top-level with children)
async function getCommissieTree() {
  // Get all commissies
  const response = await fetch(
    `${API_BASE}/wp/v2/commissies?per_page=100&orderby=menu_order&order=asc`,
    {
      headers: { 'X-WP-Nonce': nonce },
      credentials: 'include',
    }
  );

  const commissies = await response.json();

  // Build tree structure
  const topLevel = commissies.filter((c) => c.parent === 0);
  return topLevel.map((parent) => ({
    ...parent,
    children: commissies.filter((c) => c.parent === parent.id),
  }));
}

// Update a commissie
async function updateCommissie(id, data) {
  const response = await fetch(`${API_BASE}/wp/v2/commissies/${id}`, {
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

// Usage
const newCommissie = await createCommissie({
  name: 'Technische Commissie',
  description: 'Verantwoordelijk voor technische zaken',
  website: 'https://club.nl/technisch',
  contacts: [
    { contact_type: 'email', contact_label: 'TC', contact_value: 'tc@club.nl' }
  ],
});

console.log('Created commissie:', newCommissie.id);

// Create a subcommissie
const subCommissie = await createCommissie({
  name: 'Scouting',
  description: 'Scouting en talentidentificatie',
  parentId: newCommissie.id,
});

console.log('Created subcommissie:', subCommissie.id);
```

### PHP (WordPress context)

```php
<?php
// Create a commissie programmatically
$commissie_id = wp_insert_post([
    'post_type'   => 'commissie',
    'post_status' => 'publish',
    'post_title'  => 'Jeugdcommissie',
    'post_content' => 'Verantwoordelijk voor alle jeugdactiviteiten.',
    'post_parent' => 0, // Top-level
    'menu_order'  => 1,
    'post_author' => get_current_user_id(),
]);

if ($commissie_id && !is_wp_error($commissie_id)) {
    // Set ACF fields
    update_field('website', 'https://club.nl/jeugd', $commissie_id);

    // Set contact info (repeater)
    update_field('contact_info', [
        [
            'contact_type'  => 'email',
            'contact_label' => 'Voorzitter',
            'contact_value' => 'jeugd@club.nl',
        ],
    ], $commissie_id);

    // Set visibility
    update_field('_visibility', 'private', $commissie_id);
}

// Create a subcommissie
$sub_commissie_id = wp_insert_post([
    'post_type'   => 'commissie',
    'post_status' => 'publish',
    'post_title'  => 'Jeugd A-selectie',
    'post_content' => 'Commissie voor de A-junioren',
    'post_parent' => $commissie_id,
    'menu_order'  => 1,
    'post_author' => get_current_user_id(),
]);

// Get all commissies for current user
$commissies = get_posts([
    'post_type'      => 'commissie',
    'posts_per_page' => -1,
    'author'         => get_current_user_id(),
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);

// Build hierarchical structure
$tree = [];
foreach ($commissies as $commissie) {
    if ($commissie->post_parent === 0) {
        $tree[$commissie->ID] = [
            'commissie' => $commissie,
            'children'  => [],
        ];
    }
}

foreach ($commissies as $commissie) {
    if ($commissie->post_parent !== 0 && isset($tree[$commissie->post_parent])) {
        $tree[$commissie->post_parent]['children'][] = $commissie;
    }
}
```

### cURL (with Application Password)

```bash
# Create a commissie
curl -X POST "https://your-site.com/wp-json/wp/v2/commissies" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "publish",
    "title": "Jeugdcommissie",
    "content": "Verantwoordelijk voor alle jeugdactiviteiten.",
    "parent": 0,
    "acf": {
      "website": "https://club.nl/jeugd",
      "contact_info": [
        {
          "contact_type": "email",
          "contact_label": "Voorzitter",
          "contact_value": "jeugd@club.nl"
        }
      ]
    }
  }'

# Create a subcommissie
curl -X POST "https://your-site.com/wp-json/wp/v2/commissies" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "publish",
    "title": "Jeugd A-selectie",
    "parent": 456,
    "menu_order": 1,
    "acf": {
      "contact_info": [
        {
          "contact_type": "email",
          "contact_label": "Coordinator",
          "contact_value": "a-selectie@club.nl"
        }
      ]
    }
  }'

# Update a commissie
curl -X PUT "https://your-site.com/wp-json/wp/v2/commissies/456" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Bijgewerkte beschrijving"
  }'

# List top-level commissies
curl -X GET "https://your-site.com/wp-json/wp/v2/commissies?parent=0&orderby=menu_order&order=asc" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"

# List subcommissies of a parent
curl -X GET "https://your-site.com/wp-json/wp/v2/commissies?parent=456" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"

# Delete a commissie
curl -X DELETE "https://your-site.com/wp-json/wp/v2/commissies/456" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"
```

---

## Notes

1. **Title Field:** Commissies use the standard WordPress post title, not ACF fields.

2. **Description:** Commissie descriptions use the WordPress `content` field (editor), not an ACF field.

3. **Hierarchical Structure:** Commissies support parent-child relationships. Use the `parent` field to create subcommissies.

4. **Menu Order:** Use `menu_order` to control the display order of commissies within the same level.

5. **Repeater Fields:** When updating `contact_info`, always send the complete array. WordPress will replace the entire field.

6. **Access Control:** Each user only sees commissies they created. Use visibility settings and sharing to extend access.

7. **Deleting Parents:** Deleting a parent commissie does not cascade to children. Subcommissies become orphaned.

---

*Documentation generated: 2026-01-26*
