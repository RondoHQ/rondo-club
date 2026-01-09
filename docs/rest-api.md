# REST API

This document describes all REST API endpoints available in Caelis, including both WordPress standard endpoints and custom endpoints.

## Authentication

All API requests require authentication via WordPress session with REST nonce.

**Headers:**
```
X-WP-Nonce: {nonce_value}
```

The nonce is automatically injected by the frontend via `window.wpApiSettings.nonce`.

## API Namespaces

Caelis uses two API namespaces:

| Namespace | Purpose |
|-----------|---------|
| `/wp/v2/` | Standard WordPress REST API for CRUD operations on post types |
| `/prm/v1/` | Custom endpoints for dashboard, search, and specialized operations |

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

### Companies

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp/v2/companies` | List all accessible companies |
| GET | `/wp/v2/companies/{id}` | Get single company |
| POST | `/wp/v2/companies` | Create new company |
| PUT | `/wp/v2/companies/{id}` | Update company |
| DELETE | `/wp/v2/companies/{id}` | Delete company |

### Important Dates

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp/v2/important-dates` | List all accessible dates |
| GET | `/wp/v2/important-dates/{id}` | Get single date |
| POST | `/wp/v2/important-dates` | Create new date |
| PUT | `/wp/v2/important-dates/{id}` | Update date |
| DELETE | `/wp/v2/important-dates/{id}` | Delete date |

### Taxonomies

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp/v2/person_label` | List person labels |
| GET | `/wp/v2/company_label` | List company labels |
| GET | `/wp/v2/relationship_type` | List relationship types |
| GET | `/wp/v2/date_type` | List date types |

---

## Custom Endpoints (`/prm/v1/`)

These endpoints provide specialized functionality beyond basic CRUD operations.

### Dashboard

**GET** `/prm/v1/dashboard`

Returns summary statistics and recent activity for the dashboard.

**Permission:** Logged in users only

**Response:**
```json
{
  "stats": {
    "total_people": 150,
    "total_companies": 45,
    "total_dates": 200
  },
  "recent_people": [
    {
      "id": 123,
      "name": "John Doe",
      "first_name": "John",
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

**GET** `/prm/v1/version`

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

**GET** `/prm/v1/search`

Search across people, companies, and dates.

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
  "companies": [
    { "id": 2, "name": "Acme Corp", "thumbnail": "...", "website": "https://...", "labels": [] }
  ],
  "dates": [
    { "id": 3, "title": "Anniversary", "date_value": "2025-06-15", "is_recurring": true }
  ]
}
```

---

### Upcoming Reminders

**GET** `/prm/v1/reminders`

Get upcoming important dates with reminders.

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
    "date_value": "2025-01-20",
    "days_until": 10,
    "is_recurring": true,
    "date_type": ["birthday"],
    "related_people": [
      { "id": 456, "name": "John Doe" }
    ]
  }
]
```

---

### People by Company

**GET** `/prm/v1/companies/{company_id}/people`

Get all people who work or worked at a company.

**Permission:** Must have access to the company

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

### Dates by Person

**GET** `/prm/v1/people/{person_id}/dates`

Get all important dates related to a person.

**Permission:** Must have access to the person

**Response:**
```json
[
  {
    "id": 123,
    "title": "Birthday",
    "date_value": "1985-06-15",
    "is_recurring": true,
    "reminder_days_before": 7,
    "date_type": ["birthday"],
    "related_people": [
      { "id": 456, "name": "John Doe" }
    ]
  }
]
```

---

### Current User

**GET** `/prm/v1/user/me`

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

**POST** `/prm/v1/people/{person_id}/photo`

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

**POST** `/prm/v1/people/{person_id}/gravatar`

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

### Company Logo Upload

**POST** `/prm/v1/companies/{company_id}/logo/upload`

Upload and set a company's logo. The filename is automatically generated from the company name.

**Permission:** Must be able to edit the company

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

### Set Company Logo (by Media ID)

**POST** `/prm/v1/companies/{company_id}/logo`

Set a company's logo from an existing media library item.

**Permission:** Must be able to edit the company

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

**POST** `/prm/v1/relationship-types/restore-defaults`

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

