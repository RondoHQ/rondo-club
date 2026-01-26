# Custom Fields API Documentation

This document describes how to use the Stadion REST API to manage custom field definitions. Custom fields allow administrators to extend person and team records with additional data types.

## Base URL

All endpoints are relative to your WordPress installation:
```
https://your-site.com/wp-json/stadion/v1/
```

## Authentication & Permissions

### Admin Endpoints (manage_options capability required)

Admin endpoints require the `manage_options` capability (WordPress administrator role). Use HTTP Basic Authentication with a WordPress Application Password:

```bash
curl -X GET "https://your-site.com/wp-json/stadion/v1/custom-fields/person" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx"
```

### User Endpoints (any logged-in user)

The `/metadata` endpoint is available to any logged-in user and provides read-only access to field definitions for display purposes.

```bash
curl -X GET "https://your-site.com/wp-json/stadion/v1/custom-fields/person/metadata" \
  -H "X-WP-Nonce: {nonce}"
```

---

## Endpoints Overview

### Admin Endpoints (manage_options required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/custom-fields/{post_type}` | List all custom fields |
| `POST` | `/custom-fields/{post_type}` | Create new field |
| `GET` | `/custom-fields/{post_type}/{key}` | Get single field |
| `PUT` | `/custom-fields/{post_type}/{key}` | Update field |
| `DELETE` | `/custom-fields/{post_type}/{key}` | Deactivate field (soft delete) |
| `PUT` | `/custom-fields/{post_type}/order` | Reorder fields |

### User Endpoints (any logged-in user)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/custom-fields/{post_type}/metadata` | Read-only field metadata |

**Note:** `{post_type}` must be either `person` or `team`.

---

## Supported Field Types

| Type | Description | Key Options |
|------|-------------|-------------|
| `text` | Single line text | `placeholder`, `maxlength` |
| `textarea` | Multi-line text | `placeholder`, `maxlength` |
| `number` | Numeric input | `min`, `max`, `step`, `prepend`, `append` |
| `url` | URL with validation | `placeholder` |
| `email` | Email with validation | `placeholder` |
| `select` | Dropdown selection | `choices`, `allow_null`, `multiple`, `ui` |
| `checkbox` | Multiple selection checkboxes | `choices`, `layout`, `toggle`, `allow_custom` |
| `radio` | Single selection radio buttons | `choices`, `layout` |
| `true_false` | Boolean toggle | `ui_on_text`, `ui_off_text` |
| `date` | Date picker | `display_format`, `return_format`, `first_day` |
| `image` | Image upload | `preview_size`, `library`, `min_width`, `max_width` |
| `file` | File upload | `library`, `mime_types`, `min_size`, `max_size` |
| `relationship` | Link to other posts | `relation_post_types`, `filters` |
| `color_picker` | Color selection | `enable_opacity` |

---

## Field Parameters

### Required Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `label` | string | Field label displayed to users |
| `type` | string | ACF field type (see supported types above) |

### Core Optional Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `name` | string | auto | Field name/key (auto-generated from label) |
| `instructions` | string | `""` | Help text displayed below field |
| `required` | boolean | `false` | Whether field is required |
| `default_value` | mixed | - | Default value for new posts |
| `placeholder` | string | `""` | Placeholder text for empty field |

### Number Field Options

| Parameter | Type | Description |
|-----------|------|-------------|
| `min` | number | Minimum allowed value |
| `max` | number | Maximum allowed value |
| `step` | number | Step increment |
| `prepend` | string | Text displayed before input (e.g., "$") |
| `append` | string | Text displayed after input (e.g., "kg") |

### Date Field Options

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `display_format` | string | `"F j, Y"` | PHP date format for display |
| `return_format` | string | `"Y-m-d"` | PHP date format for storage |
| `first_day` | integer | `1` | First day of week (0=Sunday, 1=Monday) |

### Select/Checkbox/Radio Options

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `choices` | object | - | Key-value pairs: `{"key": "Label"}` |
| `allow_null` | boolean | `false` | Allow empty selection (Select) |
| `multiple` | boolean | `false` | Allow multiple selections (Select) |
| `ui` | boolean | `true` | Use enhanced UI |
| `layout` | string | `"vertical"` | `"vertical"` or `"horizontal"` |
| `toggle` | boolean | `false` | Show "toggle all" checkbox |
| `allow_custom` | boolean | `false` | Allow custom values |
| `save_custom` | boolean | `false` | Save custom values to choices |

### Text/Textarea Options

| Parameter | Type | Description |
|-----------|------|-------------|
| `maxlength` | integer | Maximum character length |

### True/False Options

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `ui_on_text` | string | `"Ja"` | Text for ON state |
| `ui_off_text` | string | `"Nee"` | Text for OFF state |

### Image/File Options

| Parameter | Type | Description |
|-----------|------|-------------|
| `preview_size` | string | Preview size: `thumbnail`, `medium`, `large` |
| `library` | string | Media filter: `all` or `uploadedTo` |
| `min_width` | integer | Minimum image width in pixels |
| `max_width` | integer | Maximum image width in pixels |
| `min_height` | integer | Minimum image height in pixels |
| `max_height` | integer | Maximum image height in pixels |
| `min_size` | string | Minimum file size (e.g., "1MB") |
| `max_size` | string | Maximum file size (e.g., "5MB") |
| `mime_types` | string | Allowed MIME types (comma-separated) |

### Relationship Options

| Parameter | Type | Description |
|-----------|------|-------------|
| `relation_post_types` | array | Allowed post types: `["person"]`, `["team"]`, or `["person", "team"]` |
| `filters` | array | Search filters: `["search", "post_type"]` |

### Color Picker Options

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `enable_opacity` | boolean | `false` | Enable alpha/opacity slider |

### Display Options

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `show_in_list_view` | boolean | `false` | Show field as column in list view |
| `list_view_order` | integer | `999` | Column order (lower = leftmost) |

### Validation Options

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `unique` | boolean | `false` | Enforce unique values per post type |

---

## Create a Custom Field

**Request:**
```http
POST /stadion/v1/custom-fields/person
Content-Type: application/json
Authorization: Basic {credentials}
```

**Body (text field example):**
```json
{
  "label": "LinkedIn URL",
  "type": "url",
  "instructions": "Full LinkedIn profile URL",
  "placeholder": "https://linkedin.com/in/username",
  "show_in_list_view": true,
  "list_view_order": 10
}
```

**Body (select field example):**
```json
{
  "label": "Contact Status",
  "type": "select",
  "choices": {
    "active": "Actief",
    "inactive": "Inactief",
    "prospect": "Prospect"
  },
  "default_value": "active",
  "allow_null": false,
  "ui": true
}
```

**Body (number field example):**
```json
{
  "label": "Budget",
  "type": "number",
  "min": 0,
  "max": 1000000,
  "step": 100,
  "prepend": "$",
  "instructions": "Annual budget in USD"
}
```

**Response (200 OK):**
```json
{
  "key": "field_cf_linkedin_url",
  "name": "linkedin_url",
  "label": "LinkedIn URL",
  "type": "url",
  "instructions": "Full LinkedIn profile URL",
  "placeholder": "https://linkedin.com/in/username",
  "show_in_list_view": true,
  "list_view_order": 10,
  "active": 1,
  "menu_order": 0
}
```

---

## Get a Custom Field

**Request:**
```http
GET /stadion/v1/custom-fields/person/linkedin_url
Authorization: Basic {credentials}
```

**Response:**
```json
{
  "key": "field_cf_linkedin_url",
  "name": "linkedin_url",
  "label": "LinkedIn URL",
  "type": "url",
  "instructions": "Full LinkedIn profile URL",
  "placeholder": "https://linkedin.com/in/username",
  "show_in_list_view": true,
  "list_view_order": 10,
  "active": 1,
  "menu_order": 0
}
```

---

## Update a Custom Field

**Request:**
```http
PUT /stadion/v1/custom-fields/person/linkedin_url
Content-Type: application/json
Authorization: Basic {credentials}
```

**Body (partial update):**
```json
{
  "label": "LinkedIn Profile",
  "instructions": "Link naar LinkedIn profiel",
  "show_in_list_view": false
}
```

**Response (200 OK):**
Returns the full updated field object.

**Note:** The `type` cannot be changed after field creation to protect existing data integrity.

---

## List Custom Fields

**Request:**
```http
GET /stadion/v1/custom-fields/person
Authorization: Basic {credentials}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include_inactive` | boolean | `false` | Include deactivated fields |

**Response:**
```json
[
  {
    "key": "field_cf_linkedin_url",
    "name": "linkedin_url",
    "label": "LinkedIn URL",
    "type": "url",
    "active": 1,
    "menu_order": 0
  },
  {
    "key": "field_cf_contact_status",
    "name": "contact_status",
    "label": "Contact Status",
    "type": "select",
    "choices": {
      "active": "Actief",
      "inactive": "Inactief"
    },
    "active": 1,
    "menu_order": 1
  }
]
```

---

## Delete (Deactivate) a Custom Field

**Request:**
```http
DELETE /stadion/v1/custom-fields/person/linkedin_url
Authorization: Basic {credentials}
```

**Response (200 OK):**
```json
{
  "success": true,
  "field": {
    "key": "field_cf_linkedin_url",
    "name": "linkedin_url",
    "active": 0
  }
}
```

**Note:** This is a soft delete. The field is deactivated but not removed from the database. Existing data is preserved and can be restored by reactivating the field.

---

## Reorder Custom Fields

**Request:**
```http
PUT /stadion/v1/custom-fields/person/order
Content-Type: application/json
Authorization: Basic {credentials}
```

**Body:**
```json
{
  "order": ["contact_status", "linkedin_url", "budget"]
}
```

**Response (200 OK):**
```json
{
  "success": true
}
```

---

## Get Field Metadata (User Endpoint)

This read-only endpoint is available to any logged-in user. It returns only the display-relevant properties needed to render custom field values in the UI.

**Request:**
```http
GET /stadion/v1/custom-fields/person/metadata
X-WP-Nonce: {nonce}
```

**Response:**
```json
[
  {
    "key": "field_cf_linkedin_url",
    "name": "linkedin_url",
    "label": "LinkedIn URL",
    "type": "url",
    "instructions": ""
  },
  {
    "key": "field_cf_contact_status",
    "name": "contact_status",
    "label": "Contact Status",
    "type": "select",
    "instructions": "",
    "choices": {
      "active": "Actief",
      "inactive": "Inactief",
      "prospect": "Prospect"
    }
  },
  {
    "key": "field_cf_is_vip",
    "name": "is_vip",
    "label": "VIP Contact",
    "type": "true_false",
    "instructions": "",
    "ui_on_text": "Ja",
    "ui_off_text": "Nee"
  }
]
```

**Properties returned:**
- Core: `key`, `name`, `label`, `type`, `instructions`
- Select/Checkbox/Radio: `choices`
- True/False: `ui_on_text`, `ui_off_text`
- Date: `display_format`
- Image/File/Relationship: `return_format`
- Relationship: `post_type`
- Number: `prepend`, `append`
- Display: `show_in_list_view`, `list_view_order`

---

## Error Handling

**400 Bad Request:**
```json
{
  "code": "invalid_field_type",
  "message": "Unsupported field type: invalid",
  "data": { "status": 400 }
}
```

**403 Forbidden:**
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to manage custom fields.",
  "data": { "status": 403 }
}
```

**404 Not Found:**
```json
{
  "code": "not_found",
  "message": "Field not found",
  "data": { "status": 404 }
}
```

---

## Code Examples

### JavaScript (Create Field)

```javascript
const API_BASE = 'https://your-site.com/wp-json/stadion/v1';

// Create a custom field (admin only)
async function createCustomField(postType, fieldConfig) {
  const response = await fetch(`${API_BASE}/custom-fields/${postType}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Basic ' + btoa('admin:xxxx xxxx xxxx xxxx xxxx xxxx'),
    },
    body: JSON.stringify(fieldConfig),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message);
  }

  return response.json();
}

// Usage
const field = await createCustomField('person', {
  label: 'Department',
  type: 'select',
  choices: {
    sales: 'Sales',
    marketing: 'Marketing',
    engineering: 'Engineering',
  },
  show_in_list_view: true,
});

console.log('Created field:', field.name);
```

### cURL Examples

```bash
# Create a text field
curl -X POST "https://your-site.com/wp-json/stadion/v1/custom-fields/person" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "label": "Employee ID",
    "type": "text",
    "unique": true,
    "instructions": "Unique employee identifier"
  }'

# Create a select field
curl -X POST "https://your-site.com/wp-json/stadion/v1/custom-fields/team" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "label": "Industry",
    "type": "select",
    "choices": {
      "tech": "Technology",
      "finance": "Finance",
      "healthcare": "Healthcare"
    }
  }'

# Update a field
curl -X PUT "https://your-site.com/wp-json/stadion/v1/custom-fields/person/employee_id" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{
    "instructions": "Werknemersnummer (uniek)"
  }'

# Delete (deactivate) a field
curl -X DELETE "https://your-site.com/wp-json/stadion/v1/custom-fields/person/employee_id" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx"

# Get field metadata (any logged-in user)
curl -X GET "https://your-site.com/wp-json/stadion/v1/custom-fields/person/metadata" \
  -H "X-WP-Nonce: {nonce}"
```

---

## Notes

1. **Field Key Generation:** The field key is auto-generated from the label with prefix `field_cf_`. For example, "LinkedIn URL" becomes `field_cf_linkedin_url`.

2. **Soft Delete (Deactivation):** Deleting a field only deactivates it. The field definition and all stored data are preserved in the database. This allows restoration if needed.

3. **Type Immutability:** Once created, a field's type cannot be changed. This protects existing data integrity. Create a new field if you need a different type.

4. **Post Type Separation:** Custom fields for `person` and `team` are stored separately. A field created for `person` is not available for `team` and vice versa.

5. **ACF Integration:** Custom fields are stored as ACF field groups and integrate seamlessly with ACF's validation and display systems.

6. **Unique Validation:** Fields with `unique: true` will reject duplicate values within the same post type. Useful for employee IDs, serial numbers, etc.

7. **List View Display:** Fields with `show_in_list_view: true` appear as columns in the person/team list. Use `list_view_order` to control column position.

---

*Documentation generated: 2026-01-26*
