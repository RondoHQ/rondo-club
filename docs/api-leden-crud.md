# Leden (People) API Documentation

This document describes how to use the Stadion REST API to add and update "leden" (people/contacts).

## Base URL

All endpoints are relative to your WordPress installation:
```
https://your-site.com/wp-json/
```

## Authentication

**Method:** WordPress Session + REST Nonce

Every request must include the `X-WP-Nonce` header:

```
X-WP-Nonce: {nonce_value}
```

The nonce is available in `window.stadionConfig.nonce` when logged in to Stadion.

**Access Control:** Users can only see and modify people they created themselves. Sharing and workspace visibility can extend access to other users.

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/wp/v2/people` | List all accessible people |
| `GET` | `/wp/v2/people/{id}` | Get single person |
| `POST` | `/wp/v2/people` | Create new person |
| `PUT` | `/wp/v2/people/{id}` | Update person |
| `DELETE` | `/wp/v2/people/{id}` | Delete person |
| `POST` | `/stadion/v1/people/bulk-update` | Update multiple people |
| `POST` | `/stadion/v1/people/{id}/photo` | Upload profile photo |

---

## Field Reference

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `acf.first_name` | string | Person's first name (required for auto-title generation) |

### Basic Information

| Field | Type | Description | Values/Format |
|-------|------|-------------|---------------|
| `acf.first_name` | string | First name | Any string |
| `acf.last_name` | string | Last name | Any string |
| `acf.nickname` | string | Nickname | Any string |
| `acf.gender` | string | Gender | `male`, `female`, `non_binary`, `other`, `prefer_not_to_say` |
| `acf.pronouns` | string | Pronouns | e.g., "hij/hem", "zij/haar" |
| `acf.is_favorite` | boolean | Mark as favorite | `true` or `false` |

### Contact Information

Contact info is stored as a repeater field with multiple entries:

```json
"acf": {
  "contact_info": [
    {
      "contact_type": "email",
      "contact_label": "Werk",
      "contact_value": "jan@bedrijf.nl"
    },
    {
      "contact_type": "mobile",
      "contact_label": "Privé",
      "contact_value": "+31612345678"
    }
  ]
}
```

**Contact Types:**
- `email` - E-mailadres
- `phone` - Telefoon (vast)
- `mobile` - Mobiel
- `website` - Website
- `linkedin` - LinkedIn
- `twitter` - Twitter/X
- `bluesky` - Bluesky
- `threads` - Threads
- `instagram` - Instagram
- `facebook` - Facebook
- `slack` - Slack
- `calendar` - Agenda link
- `other` - Anders

### Addresses

Addresses are stored as a repeater field:

```json
"acf": {
  "addresses": [
    {
      "address_label": "Thuis",
      "street": "Hoofdstraat 123",
      "postal_code": "1234 AB",
      "city": "Amsterdam",
      "state": "Noord-Holland",
      "country": "Nederland"
    }
  ]
}
```

### Story (How We Met)

| Field | Type | Description | Format |
|-------|------|-------------|--------|
| `acf.how_we_met` | string | How you met this person | Free text |
| `acf.met_date` | string | When you met | `Y-m-d` (e.g., "2024-06-15") |

### Team History

Link people to teams with their role history:

```json
"acf": {
  "work_history": [
    {
      "team": 42,
      "job_title": "Aanvoerder",
      "description": "Aanvoerder van het eerste elftal",
      "start_date": "2020-08-01",
      "end_date": "",
      "is_current": true
    }
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `team` | integer | Team post ID |
| `job_title` | string | Position/role title |
| `description` | string | Role description |
| `start_date` | string | Start date (Y-m-d) |
| `end_date` | string | End date (Y-m-d), empty if current |
| `is_current` | boolean | Currently in this role |

### Relationships

Link people to other people:

```json
"acf": {
  "relationships": [
    {
      "related_person": 123,
      "relationship_type": 5,
      "relationship_label": ""
    }
  ]
}
```

| Field | Type | Description |
|-------|------|-------------|
| `related_person` | integer | Related person post ID |
| `relationship_type` | integer | Relationship type taxonomy term ID |
| `relationship_label` | string | Custom label override |

### Visibility

| Field | Type | Description | Values |
|-------|------|-------------|--------|
| `acf._visibility` | string | Who can see this person | `private`, `workspace`, `shared` |
| `acf._assigned_workspaces` | array | Workspace term IDs | `[1, 2, 3]` |

---

## Create a Person

**Request:**
```http
POST /wp/v2/people
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "status": "publish",
  "acf": {
    "first_name": "Jan",
    "last_name": "de Vries",
    "gender": "male",
    "is_favorite": false,
    "contact_info": [
      {
        "contact_type": "email",
        "contact_label": "Werk",
        "contact_value": "jan.devries@example.nl"
      },
      {
        "contact_type": "mobile",
        "contact_label": "Privé",
        "contact_value": "+31612345678"
      }
    ],
    "addresses": [
      {
        "address_label": "Thuis",
        "street": "Sportlaan 45",
        "postal_code": "1234 AB",
        "city": "Amsterdam",
        "country": "Nederland"
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
  "date": "2026-01-25T14:30:00",
  "slug": "jan-de-vries",
  "status": "publish",
  "type": "person",
  "title": {
    "rendered": "Jan de Vries"
  },
  "author": 1,
  "acf": {
    "first_name": "Jan",
    "last_name": "de Vries",
    "gender": "male",
    "is_favorite": false,
    "contact_info": [...],
    "addresses": [...],
    "work_history": [],
    "relationships": [],
    "is_deceased": false,
    "birth_year": null,
    "_visibility": "private"
  }
}
```

**Note:** The `title` is automatically generated from `first_name` and `last_name`. You don't need to set it manually.

---

## Update a Person

**Request:**
```http
PUT /wp/v2/people/456
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body (partial update):**
```json
{
  "acf": {
    "is_favorite": true,
    "contact_info": [
      {
        "contact_type": "email",
        "contact_label": "Werk",
        "contact_value": "jan@nieuwbedrijf.nl"
      },
      {
        "contact_type": "mobile",
        "contact_label": "Privé",
        "contact_value": "+31612345678"
      }
    ]
  }
}
```

**Important:** When updating repeater fields (contact_info, addresses, work_history, relationships), you must send the complete array. Partial updates will replace the entire field.

**Response (200 OK):**
Returns the full updated person object.

---

## Get a Person

**Request:**
```http
GET /wp/v2/people/456
X-WP-Nonce: {nonce}
```

**Response:**
```json
{
  "id": 456,
  "title": { "rendered": "Jan de Vries" },
  "acf": {
    "first_name": "Jan",
    "last_name": "de Vries",
    "nickname": "",
    "gender": "male",
    "pronouns": "",
    "is_favorite": true,
    "photo_gallery": [],
    "how_we_met": "",
    "met_date": "",
    "contact_info": [...],
    "addresses": [...],
    "work_history": [],
    "relationships": [],
    "is_deceased": false,
    "birth_year": null,
    "_visibility": "private",
    "_assigned_workspaces": []
  }
}
```

---

## List People

**Request:**
```http
GET /wp/v2/people?per_page=20&page=1
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

**Example - Search for people named "Jan":**
```http
GET /wp/v2/people?search=Jan&per_page=50
```

**Example - Get only IDs and names (faster):**
```http
GET /wp/v2/people?_fields=id,title,acf.first_name,acf.last_name
```

---

## Delete a Person

**Request:**
```http
DELETE /wp/v2/people/456
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

## Bulk Update People

Update multiple people at once (e.g., assign to workspace, add labels).

**Request:**
```http
POST /stadion/v1/people/bulk-update
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "ids": [456, 457, 458],
  "updates": {
    "visibility": "workspace",
    "assigned_workspaces": [5],
    "labels_add": [10, 11],
    "labels_remove": [9]
  }
}
```

**Available bulk updates:**

| Field | Type | Description |
|-------|------|-------------|
| `visibility` | string | Set visibility for all |
| `assigned_workspaces` | array | Set workspace IDs |
| `organization_id` | int | Set team association |
| `labels_add` | array | Label term IDs to add |
| `labels_remove` | array | Label term IDs to remove |

**Response:**
```json
{
  "success": true,
  "updated": [456, 457, 458],
  "failed": []
}
```

---

## Upload Profile Photo

**Request:**
```http
POST /stadion/v1/people/456/photo
Content-Type: multipart/form-data
X-WP-Nonce: {nonce}
```

**Form Data:**
- `file`: Image file (JPEG, PNG, GIF, WebP)

**Response:**
```json
{
  "success": true,
  "attachment_id": 789,
  "filename": "jan-de-vries.jpg",
  "thumbnail_url": "https://your-site.com/wp-content/uploads/2026/01/jan-de-vries-150x150.jpg",
  "full_url": "https://your-site.com/wp-content/uploads/2026/01/jan-de-vries.jpg"
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
  "message": "Sorry, you are not allowed to edit this person.",
  "data": { "status": 403 }
}
```

**404 Not Found:**
```json
{
  "code": "rest_post_invalid_id",
  "message": "Invalid person ID.",
  "data": { "status": 404 }
}
```

**400 Bad Request (validation error):**
```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): acf",
  "data": { "status": 400 }
}
```

---

## Code Examples

### JavaScript/TypeScript (fetch)

```javascript
const API_BASE = 'https://your-site.com/wp-json';
const nonce = window.stadionConfig?.nonce || 'your-nonce';

// Create a person
async function createPerson(data) {
  const response = await fetch(`${API_BASE}/wp/v2/people`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: JSON.stringify({
      status: 'publish',
      acf: data,
    }),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

// Update a person
async function updatePerson(id, data) {
  const response = await fetch(`${API_BASE}/wp/v2/people/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'include',
    body: JSON.stringify({ acf: data }),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

// Usage
const newPerson = await createPerson({
  first_name: 'Jan',
  last_name: 'de Vries',
  gender: 'male',
  contact_info: [
    { contact_type: 'email', contact_label: 'Werk', contact_value: 'jan@example.nl' }
  ],
});

console.log('Created person:', newPerson.id);

await updatePerson(newPerson.id, {
  is_favorite: true,
});
```

### PHP (WordPress context)

```php
<?php
// Create a person programmatically
$person_id = wp_insert_post([
    'post_type'   => 'person',
    'post_status' => 'publish',
    'post_author' => get_current_user_id(),
]);

if ($person_id && !is_wp_error($person_id)) {
    // Set ACF fields
    update_field('first_name', 'Jan', $person_id);
    update_field('last_name', 'de Vries', $person_id);
    update_field('gender', 'male', $person_id);

    // Set contact info (repeater)
    update_field('contact_info', [
        [
            'contact_type'  => 'email',
            'contact_label' => 'Werk',
            'contact_value' => 'jan@example.nl',
        ],
    ], $person_id);
}

// Update a person
update_field('is_favorite', true, $person_id);
```

### cURL

```bash
# Create a person
curl -X POST "https://your-site.com/wp-json/wp/v2/people" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Cookie: wordpress_logged_in_xxx=YOUR_COOKIE" \
  -d '{
    "status": "publish",
    "acf": {
      "first_name": "Jan",
      "last_name": "de Vries",
      "gender": "male"
    }
  }'

# Update a person
curl -X PUT "https://your-site.com/wp-json/wp/v2/people/456" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Cookie: wordpress_logged_in_xxx=YOUR_COOKIE" \
  -d '{
    "acf": {
      "is_favorite": true
    }
  }'
```

---

## Notes

1. **Auto-generated Title:** The post title is automatically created from `first_name + last_name`. You don't need to set it.

2. **Repeater Fields:** When updating `contact_info`, `addresses`, `work_history`, or `relationships`, always send the complete array. WordPress will replace the entire field.

3. **Access Control:** Each user only sees people they created. Use visibility settings and sharing to extend access.

4. **Nonce Expiration:** WordPress nonces expire after 24 hours. For long-running integrations, refresh the nonce periodically.

5. **Rate Limiting:** There's no built-in rate limiting, but be mindful of server resources when making bulk requests.

---

*Documentation generated: 2026-01-25*
