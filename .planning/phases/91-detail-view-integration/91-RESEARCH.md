# Phase 91: Detail View Integration - Research

**Researched:** 2026-01-19
**Domain:** React detail views, inline editing, custom field rendering
**Confidence:** HIGH

## Summary

This phase integrates custom fields into the Person and Team detail pages. The Stadion codebase already has established patterns for displaying sections on detail pages, editing via modals, and handling ACF data through the REST API. Custom field values are already exposed through ACF's native REST API integration (`show_in_rest: 1` on the field group), so field values are accessible in the `acf` object of person/team responses.

The implementation requires:
1. A new "Custom Fields" section component for detail pages
2. A modal-based editing approach (consistent with existing patterns like ContactEditModal)
3. Type-specific display renderers for the 14 field types
4. Type-specific input components for editing

**Primary recommendation:** Create a reusable `CustomFieldsSection` component that renders in both PersonDetail and TeamDetail, with a corresponding `CustomFieldsEditModal` that handles all field types. This matches existing Stadion patterns.

## Standard Stack

The Stadion codebase already has all required libraries installed and in use.

### Core (Already in Use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.x | UI framework | Already in use |
| TanStack Query | 5.x | Server state/caching | Already used for data fetching |
| react-hook-form | 7.x | Form handling | Already used in ContactEditModal, FieldFormPanel |
| Tailwind CSS | 3.4 | Styling | Already in use sitewide |
| lucide-react | latest | Icons | Already in use |
| date-fns | latest | Date formatting | Already in use |
| @uiw/react-color-sketch | latest | Color picker | Already used in FieldFormPanel |

### No Additional Libraries Needed
All 14 field types can be implemented with existing libraries and native HTML inputs.

## Architecture Patterns

### Recommended Component Structure
```
src/
├── components/
│   ├── CustomFieldsSection.jsx        # Section component for detail pages
│   ├── CustomFieldsEditModal.jsx      # Modal for editing all custom fields
│   └── customfields/
│       ├── index.js                   # Barrel export
│       ├── FieldDisplay.jsx           # Type-aware display component
│       ├── FieldInput.jsx             # Type-aware input component
│       ├── displays/
│       │   ├── TextDisplay.jsx
│       │   ├── ImageDisplay.jsx
│       │   ├── FileDisplay.jsx
│       │   ├── ColorDisplay.jsx
│       │   ├── LinkDisplay.jsx
│       │   ├── RelationshipDisplay.jsx
│       │   └── ...
│       └── inputs/
│           ├── TextInput.jsx
│           ├── ImageInput.jsx
│           ├── FileInput.jsx
│           ├── ColorInput.jsx
│           ├── LinkInput.jsx
│           ├── RelationshipInput.jsx
│           └── ...
```

### Pattern 1: Section Component Pattern (from PersonDetail.jsx)
**What:** Sections are cards with header, content, and optional edit button
**When to use:** For all sections on detail pages
**Example:**
```jsx
// Source: PersonDetail.jsx lines 1756-1860
<div className="card p-6 break-inside-avoid mb-6">
  <div className="flex items-center justify-between mb-4">
    <h2 className="font-semibold">Contact information</h2>
    <button
      onClick={() => setShowContactModal(true)}
      className="btn-secondary text-sm"
    >
      <Pencil className="w-4 h-4 md:mr-1" />
      <span className="hidden md:inline">Edit</span>
    </button>
  </div>
  {/* Section content */}
</div>
```

### Pattern 2: Modal-Based Editing (from ContactEditModal.jsx)
**What:** Full-screen modal with form, save/cancel buttons
**When to use:** For editing complex data sets
**Example:**
```jsx
// Source: ContactEditModal.jsx
<div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl ...">
    {/* Header with title and close button */}
    <form onSubmit={handleSubmit}>
      {/* Form content */}
      {/* Footer with Cancel/Save buttons */}
    </form>
  </div>
</div>
```

### Pattern 3: ACF Data Access Pattern
**What:** Custom fields are in `person.acf` or `team.acf` object
**When to use:** Reading/writing custom field values
**Example:**
```jsx
// Reading field value (field name is the sanitized label)
const value = person.acf?.my_custom_field;

// Writing via updatePerson mutation
await updatePerson.mutateAsync({
  id,
  data: {
    acf: {
      ...sanitizePersonAcf(person.acf, {
        my_custom_field: newValue,
      }),
    },
  },
});
```

### Pattern 4: Data Fetching with TanStack Query
**What:** Fetch custom field definitions separately from entity data
**When to use:** Getting list of defined custom fields
**Example:**
```jsx
// Source: CustomFields.jsx lines 78-93
const { data: personFields = [], isLoading } = useQuery({
  queryKey: ['custom-fields', 'person'],
  queryFn: async () => {
    const response = await prmApi.getCustomFields('person');
    return response.data;
  },
});
```

### Anti-Patterns to Avoid
- **Direct state mutation:** Always use `updatePerson.mutateAsync` or `updateTeam.mutateAsync`
- **Inline editing without confirmation:** Existing patterns use modals, not click-to-edit inline
- **Bypassing ACF:** Always use `acf` object, never raw post meta
- **Creating new API endpoints for standard updates:** Use existing `/wp/v2/people/{id}` with `acf` payload

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date formatting | Custom date parser | `date-fns` format() | Already in use, handles timezones |
| Color picker | Custom RGB inputs | `@uiw/react-color-sketch` | Already imported in FieldFormPanel |
| File/Image upload | Custom upload UI | Existing `prmApi.uploadPersonPhoto` pattern | Handles WordPress media library |
| Form validation | Custom validation | `react-hook-form` | Already used for all forms |
| API caching | Custom state | TanStack Query | Already manages all server state |
| Person/Team search | Custom search | Existing autocomplete patterns | Used in RelationshipEditModal |

**Key insight:** Every UI pattern needed already exists in Stadion. The main work is type-specific rendering.

## Common Pitfalls

### Pitfall 1: Custom Field Key vs Name Confusion
**What goes wrong:** ACF fields have both `key` (e.g., `field_custom_person_linkedin`) and `name` (e.g., `linkedin`). Values are accessed by `name`, not `key`.
**Why it happens:** The Manager stores keys for ACF internal use, but REST API exposes values by name.
**How to avoid:** Always use `person.acf[field.name]` to get values, never `person.acf[field.key]`.
**Warning signs:** Undefined values despite field being defined.

### Pitfall 2: Field Type Mismatches
**What goes wrong:** ACF stores data differently based on return_format settings (e.g., image can be ID, URL, or array).
**Why it happens:** FieldFormPanel allows configuring return formats.
**How to avoid:** Check field's `return_format` property and handle all cases.
**Warning signs:** Cannot display image/file that has a value.

### Pitfall 3: Empty State Handling
**What goes wrong:** No custom fields defined vs fields defined but no values set.
**Why it happens:** Two different empty states need different UI.
**How to avoid:** Check both `personFields.length === 0` (no fields defined) and all fields having no value (all values empty).
**Warning signs:** "No custom fields" message when fields exist but are empty.

### Pitfall 4: Permissions for Field Definitions vs Values
**What goes wrong:** Assuming all users can see field definitions.
**Why it happens:** Field definitions require `manage_options` (admin), but field values are visible to any user who can see the post.
**How to avoid:** Fetch field definitions for display (available to all logged-in users when fetching the post), but don't show Settings link to non-admins.
**Warning signs:** Regular users see empty Custom Fields section.

**Resolution:** The field definitions are needed to display values properly (to get label, type, etc.). However, the `/rondo/v1/custom-fields/{post_type}` endpoint requires `manage_options`. We have two options:
1. Create a new read-only endpoint for non-admins
2. Include field definitions in the person/team REST response

**Recommendation:** Add field definitions to the REST response via a new `rest_prepare_person/team` filter. This keeps all data in one request and avoids permission complexity.

### Pitfall 5: Relationship Field Display
**What goes wrong:** Relationship fields store post IDs, but we need names/thumbnails for display.
**Why it happens:** ACF returns just IDs by default unless return_format is 'object'.
**How to avoid:** Check return_format; if 'id', fetch related post data separately.
**Warning signs:** Seeing numbers instead of names in relationship display.

## Code Examples

### Custom Field Display Switching
```jsx
// Pattern for type-specific rendering
function FieldDisplay({ field, value }) {
  if (value === null || value === undefined || value === '') {
    return <span className="text-gray-400 dark:text-gray-500 italic">Not set</span>;
  }

  switch (field.type) {
    case 'text':
    case 'textarea':
    case 'number':
      return <span>{value}</span>;

    case 'email':
      return (
        <a href={`mailto:${value}`} className="text-accent-600 dark:text-accent-400 hover:underline">
          {value}
        </a>
      );

    case 'url':
      return (
        <a href={value} target="_blank" rel="noopener noreferrer"
           className="text-accent-600 dark:text-accent-400 hover:underline flex items-center gap-1">
          {value}
          <ExternalLink className="w-3 h-3" />
        </a>
      );

    case 'date':
      return <span>{format(new Date(value), field.display_format || 'PP')}</span>;

    case 'select':
      // Value is key, need to show label from choices
      return <span>{field.choices?.[value] || value}</span>;

    case 'checkbox':
      // Value is array of keys
      const labels = (value || []).map(v => field.choices?.[v] || v);
      return <span>{labels.join(', ')}</span>;

    case 'true_false':
      return (
        <span className={value ? 'text-green-600' : 'text-gray-500'}>
          {value ? (field.ui_on_text || 'Yes') : (field.ui_off_text || 'No')}
        </span>
      );

    case 'image':
      // Handle array, url, or id return format
      const imageUrl = typeof value === 'object' ? value.url : value;
      return imageUrl ? (
        <img src={imageUrl} alt="" className="w-16 h-16 object-cover rounded" />
      ) : null;

    case 'file':
      const fileData = typeof value === 'object' ? value : { url: value };
      return (
        <a href={fileData.url} target="_blank" rel="noopener noreferrer"
           className="text-accent-600 dark:text-accent-400 hover:underline flex items-center gap-1">
          <FileIcon className="w-4 h-4" />
          {fileData.filename || 'Download'}
        </a>
      );

    case 'link':
      return (
        <a href={value.url} target={value.target || '_blank'} rel="noopener noreferrer"
           className="text-accent-600 dark:text-accent-400 hover:underline">
          {value.title || value.url}
        </a>
      );

    case 'color_picker':
      return (
        <div className="flex items-center gap-2">
          <div
            className="w-6 h-6 rounded border border-gray-300 dark:border-gray-600"
            style={{ backgroundColor: value }}
          />
          <span className="text-sm text-gray-500">{value}</span>
        </div>
      );

    case 'relationship':
      // Handle single or multiple, object or id return format
      const items = Array.isArray(value) ? value : [value];
      return (
        <div className="flex flex-wrap gap-2">
          {items.filter(Boolean).map((item, i) => {
            const postId = typeof item === 'object' ? item.ID : item;
            const name = typeof item === 'object' ? item.post_title : `#${item}`;
            return (
              <Link key={i} to={`/people/${postId}`} // or /teams/ based on post_type
                className="text-accent-600 dark:text-accent-400 hover:underline">
                {name}
              </Link>
            );
          })}
        </div>
      );

    default:
      return <span>{String(value)}</span>;
  }
}
```

### Section Placement Pattern
```jsx
// In PersonDetail.jsx, add to Profile tab content
{activeTab === 'profile' && (
  <div className="columns-1 md:columns-2 gap-6">
    {/* Existing sections... */}

    {/* Custom Fields section - add after existing sections */}
    <CustomFieldsSection
      postType="person"
      postId={id}
      acfData={person.acf}
      onUpdate={handleUpdateCustomFields}
    />
  </div>
)}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Click-to-edit inline | Modal-based editing | N/A (Stadion standard) | Consistent UX |
| Custom field storage | ACF native storage | Phase 87-90 | Use ACF APIs |

**Current in Stadion:**
- All editing uses modals, not inline editing
- ACF handles field storage and REST exposure
- Custom fields are in their own field group per post type

## Open Questions

Things that couldn't be fully resolved:

1. **Field Definition Access for Non-Admins**
   - What we know: Currently `/rondo/v1/custom-fields/{post_type}` requires `manage_options`
   - What's unclear: Non-admins need field definitions to display values properly
   - Recommendation: Add field definitions to person/team REST response via `rest_prepare` filter, OR create read-only endpoint for field metadata (label, type, choices only)

2. **Section Ordering**
   - What we know: Custom fields should appear on Profile tab
   - What's unclear: Exact position relative to other sections
   - Recommendation: Place after "Relationships" section, as it's the last user-defined content before custom fields

3. **Image/File Upload in Modal**
   - What we know: Existing patterns upload via separate API endpoints
   - What's unclear: Whether to upload immediately or on modal save
   - Recommendation: Upload immediately (like photo upload), show preview, store media ID

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/pages/People/PersonDetail.jsx` - Detail page structure, section patterns
- `/Users/joostdevalk/Code/stadion/src/pages/Teams/TeamDetail.jsx` - Team detail patterns
- `/Users/joostdevalk/Code/stadion/src/components/FieldFormPanel.jsx` - Field type options, form patterns
- `/Users/joostdevalk/Code/stadion/src/components/ContactEditModal.jsx` - Modal editing pattern
- `/Users/joostdevalk/Code/stadion/includes/customfields/class-manager.php` - Field storage, types
- `/Users/joostdevalk/Code/stadion/includes/class-rest-custom-fields.php` - API structure
- `/Users/joostdevalk/Code/stadion/src/api/client.js` - API client methods
- `/Users/joostdevalk/Code/stadion/src/pages/Settings/CustomFields.jsx` - Field management UI

### Secondary (MEDIUM confidence)
- ACF documentation on REST API field exposure (via `show_in_rest: 1`)
- ACF field type value formats

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use in codebase
- Architecture: HIGH - Patterns directly from existing code
- Pitfalls: MEDIUM - Based on code analysis, some edge cases may exist

**Research date:** 2026-01-19
**Valid until:** 2026-02-19 (30 days - stable React patterns)
