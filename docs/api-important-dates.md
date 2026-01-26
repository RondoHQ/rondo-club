# Important Dates API Documentation

This document describes how to use the Stadion REST API to manage important dates (birthdays, anniversaries, etc.).

## Base URL

All endpoints are relative to your WordPress installation:
```
https://your-site.com/wp-json/
```

## Authentication

The API supports two authentication methods:

### Method 1: Application Password (Recommended for External Integrations)

Use HTTP Basic Authentication with a WordPress Application Password. This is the recommended method for scripts, external services, and API integrations.

1. Generate an Application Password in WordPress: **Users > Profile > Application Passwords**
2. Use your WordPress username and the generated password (with spaces)

```bash
curl -X GET "https://your-site.com/wp-json/wp/v2/important-dates" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"
```

Or with the `Authorization` header:

```bash
curl -X GET "https://your-site.com/wp-json/wp/v2/important-dates" \
  -H "Authorization: Basic $(echo -n 'username:xxxx xxxx xxxx xxxx xxxx xxxx' | base64)"
```

### Method 2: Session + Nonce (Browser Use)

For requests from the Stadion frontend (same browser session), use the REST nonce:

```
X-WP-Nonce: {nonce_value}
```

The nonce is available in `window.stadionConfig.nonce` when logged in to Stadion.

---

**Access Control:** Users can only see and modify important dates they created themselves.

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/wp/v2/important-dates` | List all accessible dates |
| `GET` | `/wp/v2/important-dates/{id}` | Get single date |
| `POST` | `/wp/v2/important-dates` | Create new date |
| `PUT` | `/wp/v2/important-dates/{id}` | Update date |
| `DELETE` | `/wp/v2/important-dates/{id}` | Delete date |

---

## Field Reference

### Required Fields

| Field | Type | Description | Format |
|-------|------|-------------|--------|
| `acf.date_value` | string | The date | `Y-m-d` (e.g., "1990-06-15") |
| `acf.related_people` | array | Person IDs linked to this date | `[123, 456]` |

### Optional Fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `acf.year_unknown` | boolean | `false` | Whether the year is unknown (e.g., for a birthday where only day/month is known) |
| `acf.is_recurring` | boolean | `true` | Whether this date repeats yearly |
| `acf.custom_label` | string | `""` | Override the auto-generated title |

---

## Create an Important Date

**Request:**
```http
POST /wp/v2/important-dates
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body:**
```json
{
  "status": "publish",
  "acf": {
    "date_value": "1990-06-15",
    "related_people": [123],
    "year_unknown": false,
    "is_recurring": true,
    "custom_label": ""
  }
}
```

**Response (201 Created):**
```json
{
  "id": 789,
  "date": "2026-01-26T10:30:00",
  "slug": "jan-de-vries-verjaardag",
  "status": "publish",
  "type": "important_date",
  "title": {
    "rendered": "Verjaardag - Jan de Vries"
  },
  "author": 1,
  "acf": {
    "date_value": "1990-06-15",
    "related_people": [123],
    "year_unknown": false,
    "is_recurring": true,
    "custom_label": ""
  }
}
```

**Note:** The `title` is automatically generated from the date type and related people names. You don't need to set it manually. Use `custom_label` to override.

---

## Update an Important Date

**Request:**
```http
PUT /wp/v2/important-dates/789
Content-Type: application/json
X-WP-Nonce: {nonce}
```

**Body (partial update):**
```json
{
  "acf": {
    "date_value": "1990-06-20",
    "custom_label": "Jan's Verjaardag"
  }
}
```

**Response (200 OK):**
Returns the full updated date object.

---

## Get an Important Date

**Request:**
```http
GET /wp/v2/important-dates/789
X-WP-Nonce: {nonce}
```

**Response:**
```json
{
  "id": 789,
  "title": { "rendered": "Verjaardag - Jan de Vries" },
  "acf": {
    "date_value": "1990-06-15",
    "related_people": [123],
    "year_unknown": false,
    "is_recurring": true,
    "custom_label": ""
  }
}
```

---

## List Important Dates

**Request:**
```http
GET /wp/v2/important-dates?per_page=20&page=1
X-WP-Nonce: {nonce}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | int | 10 | Items per page (max: 100) |
| `page` | int | 1 | Page number |
| `search` | string | - | Search in title |
| `orderby` | string | date | Sort by: `date`, `title`, `modified` |
| `order` | string | desc | Sort order: `asc` or `desc` |
| `_fields` | string | - | Limit fields returned (comma-separated) |

**Example - Get only IDs and dates (faster):**
```http
GET /wp/v2/important-dates?_fields=id,title,acf.date_value,acf.related_people
```

---

## Delete an Important Date

**Request:**
```http
DELETE /wp/v2/important-dates/789
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
  "message": "Sorry, you are not allowed to edit this date.",
  "data": { "status": 403 }
}
```

**404 Not Found:**
```json
{
  "code": "rest_post_invalid_id",
  "message": "Invalid date ID.",
  "data": { "status": 404 }
}
```

---

## Code Examples

### JavaScript/TypeScript (fetch)

```javascript
const API_BASE = 'https://your-site.com/wp-json';
const nonce = window.stadionConfig?.nonce || 'your-nonce';

// Create an important date
async function createImportantDate(data) {
  const response = await fetch(`${API_BASE}/wp/v2/important-dates`, {
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

// Update an important date
async function updateImportantDate(id, data) {
  const response = await fetch(`${API_BASE}/wp/v2/important-dates/${id}`, {
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
const birthday = await createImportantDate({
  date_value: '1990-06-15',
  related_people: [123],
  is_recurring: true,
});

console.log('Created date:', birthday.id);
```

### cURL (with Application Password)

```bash
# Create an important date
curl -X POST "https://your-site.com/wp-json/wp/v2/important-dates" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "publish",
    "acf": {
      "date_value": "1990-06-15",
      "related_people": [123],
      "is_recurring": true
    }
  }'

# Update an important date
curl -X PUT "https://your-site.com/wp-json/wp/v2/important-dates/789" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "acf": {
      "custom_label": "Jan'\''s Speciale Dag"
    }
  }'

# List important dates
curl -X GET "https://your-site.com/wp-json/wp/v2/important-dates?per_page=10" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"

# Delete an important date
curl -X DELETE "https://your-site.com/wp-json/wp/v2/important-dates/789" \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"
```

---

## Notes

1. **Auto-generated Title:** The post title is automatically created from the date type (via taxonomy) and related people names. Use `custom_label` to override.

2. **Year Unknown:** When `year_unknown` is `true`, only the month and day are used for recurring reminders. The year is ignored.

3. **Recurring Dates:** When `is_recurring` is `true`, reminders are sent annually. Set to `false` for one-time dates.

4. **Related People:** Each important date must be linked to at least one person. The date will appear on those people's profiles.

5. **Access Control:** Each user only sees important dates they created. Dates inherit visibility from linked people when workspace sharing is enabled.

6. **Daily Digest Reminders:** Upcoming important dates (within 7 days) are included in the daily reminder digest email.

---

*Documentation generated: 2026-01-26*
