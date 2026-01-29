# Phase 118: Custom Field Edit Control - Research

**Researched:** 2026-01-29
**Domain:** React UI patterns, ACF field metadata management, WordPress REST API
**Confidence:** HIGH

## Summary

Phase 118 adds a UI-level editability control to custom fields, allowing admins to mark fields as read-only in the UI while keeping them accessible via REST API. This supports the use case where external systems (like Sportlink) manage certain field values through API calls, but users should see the data without being able to edit it through the UI.

The implementation involves:
1. Adding an `editable_in_ui` boolean property to custom field definitions (PHP backend)
2. Updating the FieldFormPanel settings UI to include a toggle for this property
3. Modifying CustomFieldsSection to conditionally hide/show the edit button
4. Updating CustomFieldsEditModal to display non-editable fields as read-only with visual indicators

**Primary recommendation:** Use existing ACF field property storage pattern with default `true` value for backward compatibility. Follow the codebase's established patterns for toggle controls and read-only field display.

## Standard Stack

The implementation uses existing technologies already in the codebase:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| ACF Pro | 6.x | Custom field storage | Already used for all custom field definitions |
| React | 18.x | Frontend UI | Existing component architecture |
| TanStack Query | Latest | Data fetching | Already used for custom fields API |
| Lucide React | Latest | Icons | Codebase standard icon library |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| React Hook Form | Latest | Form state | Already used in CustomFieldsEditModal |
| WordPress REST API | Native | Backend communication | Existing API pattern |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| ACF native storage | Custom post meta | ACF provides validation, admin UI, and REST integration out of the box |
| Boolean property | Capability system | Boolean is simpler and matches field-level settings like `required` and `unique` |

**Installation:**
No new dependencies required - all libraries already present in the codebase.

## Architecture Patterns

### Recommended Project Structure
```
includes/customfields/
├── class-manager.php        # Add editable_in_ui to UPDATABLE_PROPERTIES
includes/
└── class-rest-custom-fields.php  # Add editable_in_ui to API parameters

src/components/
├── FieldFormPanel.jsx       # Add toggle in validation section
├── CustomFieldsSection.jsx  # Conditional edit button logic
└── CustomFieldsEditModal.jsx # Read-only field rendering
```

### Pattern 1: Field Property Storage
**What:** Store `editable_in_ui` as an ACF field property alongside existing properties like `required` and `unique`.
**When to use:** For any field-level behavioral settings that affect UI rendering.
**Example:**
```php
// In class-manager.php UPDATABLE_PROPERTIES array
private const UPDATABLE_PROPERTIES = array(
    'label',
    'required',
    'unique',
    'editable_in_ui',  // New property
    // ... other properties
);
```

### Pattern 2: Conditional UI Rendering
**What:** Check `editable_in_ui` property to determine if field should be editable in UI.
**When to use:** In CustomFieldsSection (edit button visibility) and CustomFieldsEditModal (field rendering).
**Example:**
```jsx
// In CustomFieldsSection.jsx
const hasEditableFields = fieldDefs.some(field => field.editable_in_ui !== false);

{hasEditableFields && (
  <button onClick={() => setShowModal(true)} className="btn-secondary">
    <Pencil className="w-4 h-4" />
    Bewerken
  </button>
)}
```

### Pattern 3: Read-Only Field Display
**What:** For non-editable fields in the edit modal, display the current value with a lock icon and explanatory text.
**When to use:** When rendering fields with `editable_in_ui: false` in CustomFieldsEditModal.
**Example:**
```jsx
// In CustomFieldsEditModal.jsx renderFieldInput()
if (field.editable_in_ui === false) {
  return (
    <div className="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
      <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mb-1">
        <Lock className="w-3.5 h-3.5" />
        <span>Wordt beheerd via API</span>
      </div>
      <div className="text-gray-900 dark:text-gray-100">
        {renderFieldValue(field, currentValues[field.name])}
      </div>
    </div>
  );
}
```

### Pattern 4: Default Value Handling
**What:** Default to `true` for backward compatibility. Treat missing property as `true`.
**When to use:** When checking editability in frontend components and when creating new fields.
**Example:**
```jsx
// Frontend check (handles undefined as editable)
const isEditable = field.editable_in_ui !== false;

// Backend default in create_field()
$field['editable_in_ui'] = $field_config['editable_in_ui'] ?? true;
```

### Anti-Patterns to Avoid
- **Graying out non-editable fields:** Creates visual clutter and makes fields look "broken". Instead, use clean read-only presentation.
- **Hiding non-editable fields entirely:** Users need to see the values. Display them as read-only instead.
- **Per-record editability control:** This adds complexity. Keep it at the field definition level (global per field).
- **Blocking REST API access:** The whole point is to allow API updates while restricting UI edits.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Field metadata storage | Custom database table | ACF field properties | ACF provides REST API integration, admin UI, and validation |
| Read-only form inputs | Disabled inputs | Custom read-only display component | Disabled inputs don't submit values and look broken |
| Icon library | Custom SVG icons | Lucide React (already in codebase) | Consistent with existing UI, maintained, accessible |
| Form state | Manual state management | React Hook Form (already used) | Already implemented in CustomFieldsEditModal |

**Key insight:** The existing custom fields system already has patterns for field-level settings (`required`, `unique`, `show_in_list_view`). Follow the same pattern for `editable_in_ui` rather than inventing a new approach.

## Common Pitfalls

### Pitfall 1: Not Handling Missing Property Gracefully
**What goes wrong:** Existing fields don't have `editable_in_ui` property, causing errors or incorrect behavior.
**Why it happens:** Property was added later, so existing field definitions don't include it.
**How to avoid:** Always treat missing property as `true` (editable). Use `field.editable_in_ui !== false` instead of `field.editable_in_ui === true`.
**Warning signs:** Existing fields suddenly become non-editable after adding the feature.

### Pitfall 2: Inconsistent Property Naming
**What goes wrong:** Using camelCase in some places and snake_case in others leads to property not being found.
**Why it happens:** PHP uses snake_case, JavaScript uses camelCase, inconsistent mapping.
**How to avoid:** Use snake_case (`editable_in_ui`) everywhere to match existing ACF field properties like `ui_on_text`, `ui_off_text`, `show_in_list_view`.
**Warning signs:** Property saves correctly but doesn't show up in frontend.

### Pitfall 3: Forgetting to Update get_field_metadata
**What goes wrong:** Property is stored but not returned in the `/metadata` endpoint used by CustomFieldsSection.
**Why it happens:** The metadata endpoint filters properties to only return display-relevant ones.
**How to avoid:** Add `editable_in_ui` to the properties extracted in `get_field_metadata()` in class-rest-custom-fields.php.
**Warning signs:** Setting saves in admin but CustomFieldsEditModal doesn't recognize non-editable fields.

### Pitfall 4: Breaking REST API Access
**What goes wrong:** Making fields non-editable in UI also blocks REST API updates.
**Why it happens:** Adding validation that rejects updates to non-editable fields at the API level.
**How to avoid:** Only implement UI restrictions. The REST API should continue accepting updates for all fields regardless of `editable_in_ui` setting.
**Warning signs:** API import scripts start failing with "field is read-only" errors.

### Pitfall 5: Poor Mobile UX with Lock Icon
**What goes wrong:** Lock icon and text take too much space on mobile, making read-only fields hard to read.
**Why it happens:** Not testing on smaller screens.
**How to avoid:** Use small icon (3.5x3.5 or 4x4), single line layout, and ensure text wraps properly.
**Warning signs:** Read-only fields overflow or get cut off on mobile devices.

## Code Examples

Verified patterns from the codebase:

### Adding Property to Backend (PHP)
```php
// Source: includes/customfields/class-manager.php lines 51-103
private const UPDATABLE_PROPERTIES = array(
    // Core properties.
    'label',
    'name',
    'instructions',
    'required',
    // ... existing properties ...
    'unique',
    'editable_in_ui',  // Add here
);
```

### Exposing Property in Metadata API
```php
// Source: includes/class-rest-custom-fields.php lines 244-301
public function get_field_metadata( $request ): WP_REST_Response {
    $post_type = $request->get_param( 'post_type' );
    $fields    = $this->manager->get_fields( $post_type, false );

    $metadata = array_map(
        function ( $field ) {
            $display_props = array(
                'key'          => $field['key'],
                'name'         => $field['name'],
                'label'        => $field['label'],
                'type'         => $field['type'],
                'instructions' => $field['instructions'] ?? '',
                'editable_in_ui' => $field['editable_in_ui'] ?? true, // Add here
            );
            // ... rest of mapping
            return $display_props;
        },
        $fields
    );

    return rest_ensure_response( $metadata );
}
```

### Settings UI Toggle (React)
```jsx
// Source: Pattern from FieldFormPanel.jsx lines 1246-1275 (Validation Options section)
<div className="pt-4 border-t border-gray-200 dark:border-gray-700">
  <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3">
    Validatie opties
  </h4>
  <div className="space-y-3">
    <div className="flex items-center gap-2">
      <input
        id="required"
        name="required"
        type="checkbox"
        checked={formData.required}
        onChange={handleChange}
        className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
      />
      <label htmlFor="required" className="text-sm text-gray-700 dark:text-gray-300">
        Verplicht veld
      </label>
    </div>
    <p className={hintClass}>Gebruikers moeten een waarde invullen bij opslaan</p>

    {/* Add editable_in_ui toggle here, similar pattern */}
    <div className="flex items-center gap-2 mt-4">
      <input
        id="editable_in_ui"
        name="editable_in_ui"
        type="checkbox"
        checked={formData.editable_in_ui}
        onChange={handleChange}
        className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600 focus:ring-accent-500"
      />
      <label htmlFor="editable_in_ui" className="text-sm text-gray-700 dark:text-gray-300">
        Bewerkbaar in UI
      </label>
    </div>
    <p className={hintClass}>Schakel uit voor velden die via API worden beheerd</p>
  </div>
</div>
```

### Conditional Edit Button Display
```jsx
// Source: Pattern from CustomFieldsSection.jsx lines 447-457
const hasEditableFields = fieldDefs.some(field => field.editable_in_ui !== false);

<div className="flex items-center justify-between mb-4">
  <h2 className="font-semibold">Aangepaste velden</h2>
  {hasEditableFields && (
    <button
      onClick={() => setShowModal(true)}
      className="btn-secondary text-sm"
    >
      <Pencil className="w-4 h-4 md:mr-1" />
      <span className="hidden md:inline">Bewerken</span>
    </button>
  )}
</div>
```

### Read-Only Field Rendering in Modal
```jsx
// Source: Pattern inspired by CustomFieldsEditModal.jsx renderFieldInput() structure
// and TimelineView.jsx lines 1-6 for Lock icon usage
const renderFieldInput = (field) => {
  // Check if field is editable (treat missing property as true)
  if (field.editable_in_ui === false) {
    const currentValue = watch(field.name);
    return (
      <div className="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
          <Lock className="w-3.5 h-3.5" />
          <span>Wordt beheerd via API</span>
        </div>
        <div className="text-gray-900 dark:text-gray-100">
          {renderFieldValue(field, currentValue)}
        </div>
      </div>
    );
  }

  // ... existing editable input rendering
};
```

### Form Default Value Handling
```jsx
// Source: Pattern from FieldFormPanel.jsx lines 29-76 (getDefaultFormData)
const getDefaultFormData = () => ({
  label: '',
  type: 'text',
  instructions: '',
  // ... existing defaults ...
  required: false,
  unique: false,
  editable_in_ui: true,  // Add default here
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hide fields from UI entirely | Show read-only with explanation | 2024+ | Better UX - users understand why field can't be edited |
| Disabled form inputs | Custom read-only display | React best practices | Disabled inputs don't convey "API-managed" concept clearly |
| Gray out non-editable content | Clean presentation with icon indicator | Modern UI design | Reduces visual noise, clearer communication |

**Deprecated/outdated:**
- Using `disabled` attribute for read-only fields: Accessibility issues and doesn't clearly communicate API-managed status
- Hiding entire modal when all fields are non-editable: Better to hide edit button entirely if no editable fields

## Open Questions

None - implementation approach is clear based on existing patterns in the codebase.

## Sources

### Primary (HIGH confidence)
- Codebase inspection: includes/customfields/class-manager.php (field property storage pattern)
- Codebase inspection: includes/class-rest-custom-fields.php (API parameter handling)
- Codebase inspection: src/components/FieldFormPanel.jsx (settings UI patterns)
- Codebase inspection: src/components/CustomFieldsSection.jsx (conditional rendering)
- Codebase inspection: src/components/CustomFieldsEditModal.jsx (form rendering)
- Codebase inspection: src/components/Timeline/TimelineView.jsx (Lock icon usage)
- docs/api-custom-fields.md (API structure and patterns)
- .planning/phases/118-custom-field-edit-control/118-CONTEXT.md (user decisions)

### Secondary (MEDIUM confidence)
- React Hook Form documentation (existing dependency, confirmed usage in CustomFieldsEditModal)
- ACF documentation (field properties API, confirmed usage throughout codebase)

### Tertiary (LOW confidence)
None - all research based on codebase inspection and confirmed existing patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, no new dependencies
- Architecture: HIGH - Existing patterns for field properties well-established
- Pitfalls: HIGH - Based on similar properties (required, unique, show_in_list_view)

**Research date:** 2026-01-29
**Valid until:** 90 days (stable feature, unlikely to change rapidly)

---

## Additional Implementation Notes

### Property Naming Convention
The codebase uses **snake_case** for ACF field properties consistently:
- `ui_on_text`, `ui_off_text` (true_false fields)
- `show_in_list_view`, `list_view_order` (display properties)
- `editable_in_ui` follows this pattern

### Default Value Philosophy
Following the established pattern in the codebase:
- Properties that restrict functionality default to `false` (e.g., `required: false`, `unique: false`)
- Properties that enable functionality default to `true` (e.g., `ui: true` for select fields)
- Since `editable_in_ui` restricts editing, it defaults to `true` (editing enabled)

### UI Placement Rationale
The "Bewerkbaar in UI" toggle is placed in the "Validatie opties" section because:
1. It sits next to "Verplicht" and "Unieke waarde" (similar field behavior controls)
2. All three affect how the field behaves in forms (required, unique values, editable)
3. Maintains visual grouping of field behavior settings
4. Users expect validation and editing controls together

### REST API Constraint (API-01)
From Phase 117 decisions: "API-01 (all REST API functionality remains unchanged)"
- The `/wp/v2/people/{id}` and `/wp/v2/teams/{id}` endpoints must continue accepting updates to ALL fields
- Only the React UI respects `editable_in_ui: false`
- This preserves Sportlink sync and other API integrations
