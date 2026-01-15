# Custom Fields Implementation Plan

**Caelis CRM - Global Custom Fields for Person & Organization**

---

## Scope

- **Object types:** Person and Organization only (for now)
- **Visibility:** Global across entire install (not per-user or per-workspace)
- **UI location:** New 'Custom Fields' settings pages linked from Admin tab
- **Admin only:** Only administrators can create/edit/delete custom fields

---

## 1. Database Schema

New custom table: `wp_prm_custom_fields`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT PK | Auto-increment primary key |
| object_type | VARCHAR(50) | 'person' or 'company' |
| field_key | VARCHAR(100) | Unique key within object type (e.g., 'linkedin_url') |
| field_type | VARCHAR(50) | text, textarea, number, date, boolean, select, url, email |
| label | VARCHAR(255) | Display label shown in UI |
| description | TEXT | Help text shown below field (optional) |
| placeholder | VARCHAR(255) | Placeholder text for input fields |
| options | JSON | Type-specific config (select options, min/max, etc.) |
| validation | JSON | `{ required: bool, unique: bool, min: num, max: num }` |
| display_order | INT | Sort order in forms (0 = first) |
| is_active | TINYINT(1) | 1 = active, 0 = deactivated (data preserved) |
| show_in_list | TINYINT(1) | Show as column in list view (future) |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

**Storage of values:** Custom field values stored in post_meta with key prefix `custom_` (e.g., `custom_linkedin_url`). This integrates with existing ACF/WordPress patterns.

### Supported Field Types

| Type | Description | Options JSON |
|------|-------------|--------------|
| text | Single-line text input | `{ maxLength: 255 }` |
| textarea | Multi-line text input | `{ rows: 4 }` |
| number | Integer or decimal | `{ min: 0, max: 100, step: 1 }` |
| date | Date picker | `{ includeTime: false }` |
| boolean | Toggle/checkbox | `{ defaultValue: false }` |
| select | Dropdown (single) | `{ choices: [{value, label}], allowEmpty: true }` |
| multiselect | Dropdown (multiple) | `{ choices: [{value, label}] }` |
| url | URL with validation | `{ }` |
| email | Email with validation | `{ }` |

---

## 2. REST API Endpoints

New endpoints under `/prm/v1/custom-fields`

### Field Definitions (Admin only)

```
GET    /prm/v1/custom-fields                    # List all custom fields
GET    /prm/v1/custom-fields?object_type=person # List fields for object type
GET    /prm/v1/custom-fields/{id}               # Get single field definition
POST   /prm/v1/custom-fields                    # Create new custom field
PUT    /prm/v1/custom-fields/{id}               # Update field definition
DELETE /prm/v1/custom-fields/{id}               # Deactivate field (soft delete)
POST   /prm/v1/custom-fields/reorder            # Reorder fields { ids: [3,1,2] }
```

### Schema Discovery (Public)

```
GET    /prm/v1/schema/person    # Get person schema (standard + custom fields)
GET    /prm/v1/schema/company   # Get company schema (standard + custom fields)
```

Schema endpoints return combined standard ACF fields + custom fields for form rendering.

### Example Request/Response

```json
// POST /prm/v1/custom-fields
{
  "object_type": "person",
  "field_key": "linkedin_url",
  "field_type": "url",
  "label": "LinkedIn Profile",
  "description": "Link to their LinkedIn profile",
  "placeholder": "https://linkedin.com/in/...",
  "validation": { "required": false }
}

// Response
{
  "id": 5,
  "object_type": "person",
  "field_key": "linkedin_url",
  "field_type": "url",
  "label": "LinkedIn Profile",
  "meta_key": "custom_linkedin_url",
  "display_order": 0,
  "is_active": true,
  "created_at": "2026-01-15T10:00:00Z"
}
```

---

## 3. UI Components

### 3.1 New Settings Pages

- `/settings/people-fields` - Custom Fields for People
- `/settings/company-fields` - Custom Fields for Organizations

Linked from Admin tab in main Settings, similar to Labels and Relationship Types.

### 3.2 CustomFields.jsx (Settings Page)

Follow existing Labels.jsx pattern:

- Admin-only access check with ShieldAlert fallback
- List of fields with edit/delete buttons
- 'Add Field' button opens modal/form
- Drag-and-drop reordering (optional Phase 2)
- React Query for data fetching/mutations

### 3.3 CustomFieldForm.jsx (Add/Edit Modal)

- Field type selector (dropdown with icons)
- Label input (required)
- Field key input (auto-generated from label, editable)
- Description/help text (optional)
- Placeholder text (optional)
- Type-specific options (e.g., choices for select)
- Validation toggles (required, unique)

### 3.4 Dynamic Field Rendering in Detail Views

Update PersonDetail.jsx and CompanyDetail.jsx to:

1. Fetch custom field definitions on mount
2. Render custom fields in a 'Custom Fields' section
3. Use appropriate input components based on field_type
4. Save values to post_meta via existing update endpoints

---

## 4. PHP Classes

### 4.1 PRM_Custom_Fields_Table

Database table creation and management.

```php
class PRM_Custom_Fields_Table {
    public static function create_table() { /* dbDelta() */ }
    public static function get_fields($object_type = null) { /* SELECT */ }
    public static function get_field($id) { /* SELECT by ID */ }
    public static function create_field($data) { /* INSERT */ }
    public static function update_field($id, $data) { /* UPDATE */ }
    public static function delete_field($id) { /* Set is_active = 0 */ }
    public static function reorder_fields($ids) { /* Update display_order */ }
}
```

### 4.2 PRM_Custom_Fields_REST

REST API endpoint registration and handlers.

```php
class PRM_Custom_Fields_REST extends PRM_REST_Base {
    public function register_routes() {
        // /prm/v1/custom-fields endpoints
        // /prm/v1/schema/{object_type} endpoints
    }

    public function get_items($request) { /* List fields */ }
    public function create_item($request) { /* Create field */ }
    public function update_item($request) { /* Update field */ }
    public function delete_item($request) { /* Deactivate field */ }
    public function get_schema($request) { /* Combined schema */ }

    protected function validate_field_key($key, $object_type) { /* Unique check */ }
    protected function sanitize_field_data($data) { /* Clean input */ }
}
```

### 4.3 PRM_Custom_Fields_Integration

Hooks into existing REST responses to include custom field values.

```php
class PRM_Custom_Fields_Integration {
    public function __construct() {
        // Hook into REST response preparation
        add_filter('rest_prepare_person', [$this, 'add_custom_fields'], 10, 3);
        add_filter('rest_prepare_company', [$this, 'add_custom_fields'], 10, 3);

        // Hook into REST update to save custom field values
        add_action('rest_after_insert_person', [$this, 'save_custom_fields'], 10, 2);
        add_action('rest_after_insert_company', [$this, 'save_custom_fields'], 10, 2);
    }

    public function add_custom_fields($response, $post, $request) {
        // Add custom_fields object to response
        $fields = PRM_Custom_Fields_Table::get_fields($post->post_type);
        foreach ($fields as $field) {
            $response->data['custom_fields'][$field->field_key] =
                get_post_meta($post->ID, 'custom_' . $field->field_key, true);
        }
        return $response;
    }

    public function save_custom_fields($post, $request) {
        $custom = $request->get_param('custom_fields');
        if (!$custom) return;

        $fields = PRM_Custom_Fields_Table::get_fields($post->post_type);
        foreach ($fields as $field) {
            if (isset($custom[$field->field_key])) {
                update_post_meta($post->ID, 'custom_' . $field->field_key,
                    $this->sanitize_value($custom[$field->field_key], $field));
            }
        }
    }
}
```

---

## 5. Implementation Phases

| Phase | Tasks | Estimate |
|-------|-------|----------|
| 1 | Database table + REST endpoints + PHP classes | 1 week |
| 2 | Settings UI (CustomFields.jsx, CustomFieldForm.jsx) | 1-2 weeks |
| 3 | Detail view integration (render + save custom fields) | 1 week |
| 4 | Polish: validation, error handling, drag reorder | 0.5-1 week |
| **Total** | | **3.5-5 weeks** |

---

## 6. File Structure

```
includes/
├── class-prm-custom-fields-table.php        # Database operations
├── class-prm-custom-fields-rest.php         # REST API endpoints
└── class-prm-custom-fields-integration.php  # Hooks into existing REST

src/
├── api/
│   └── client.js                            # Add custom fields API methods
├── pages/
│   └── Settings/
│       ├── CustomFields.jsx                 # Main settings page (per object type)
│       └── CustomFieldForm.jsx              # Add/edit field modal
├── components/
│   └── CustomFieldInput.jsx                 # Dynamic field renderer for detail views
└── hooks/
    └── useCustomFields.js                   # React Query hooks
```
