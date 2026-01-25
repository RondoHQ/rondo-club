# Phase 92: List View Integration - Research

**Researched:** 2026-01-19
**Domain:** List views, table columns, custom field rendering, admin settings
**Confidence:** HIGH

## Summary

This phase integrates custom fields into the People and Teams list views as additional columns. The Stadion codebase has well-established list view patterns in `PeopleList.jsx` and `TeamsList.jsx` that use table-based layouts with sortable headers, row selection, and filtering. Custom field definitions are already accessible via the REST API (`/stadion/v1/custom-fields/{post_type}/metadata`), and custom field values are exposed through ACF's REST API integration in the `acf` object of person/team responses.

The implementation requires:
1. Settings to enable "show in list view" per field (stored in field definition)
2. Settings to configure column order for list view fields
3. Extending list view components to render custom field columns dynamically
4. Type-appropriate rendering for narrow column widths (truncation, icons, badges)

**Primary recommendation:** Add `show_in_list_view` boolean and `list_view_order` integer to field definitions (stored via ACF Manager). Create a `CustomFieldColumn` component that handles type-specific rendering for constrained widths. Extend `PersonListView` and `TeamListView` to render enabled custom field columns after built-in columns.

## Standard Stack

The Stadion codebase already has all required libraries installed and in use.

### Core (Already in Use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.x | UI framework | Already in use |
| TanStack Query | 5.x | Server state/caching | Already used for data fetching |
| Tailwind CSS | 3.4 | Styling | Already in use sitewide |
| lucide-react | latest | Icons | Already in use |
| date-fns | latest | Date formatting | Already in use |

### No Additional Libraries Needed
All column rendering can be implemented with existing libraries. The patterns from Phase 91's `CustomFieldsSection.jsx` (type-specific rendering) can be adapted for narrower column widths.

## Architecture Patterns

### Existing List View Structure

The People list (`PeopleList.jsx`) and Teams list (`TeamsList.jsx`) share identical patterns:

```
PeopleList / TeamsList
├── Header (sort controls, filter dropdown, add button)
├── Selection toolbar (when items selected)
└── PersonListView / TeamListView
    └── <table>
        ├── <thead> with SortableHeader components
        └── <tbody> with PersonListRow / TeamListRow components
```

### Pattern 1: SortableHeader Component
**What:** Clickable column headers with sort indicators
**When to use:** For all sortable columns including custom fields
**Example:**
```jsx
// Source: PeopleList.jsx lines 119-139
function SortableHeader({ field, label, currentSortField, currentSortOrder, onSort }) {
  const isActive = currentSortField === field;
  return (
    <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer"
      >
        {label}
        {isActive && (
          currentSortOrder === 'asc' ? <ArrowUp className="w-3 h-3" /> : <ArrowDown className="w-3 h-3" />
        )}
      </button>
    </th>
  );
}
```

### Pattern 2: Row Component Structure
**What:** Table rows with fixed cell structure
**When to use:** For rendering entity rows with dynamic custom field columns
**Example:**
```jsx
// Source: PeopleList.jsx lines 32-116
function PersonListRow({ person, teamName, workspaces, isSelected, onToggleSelection, isOdd }) {
  return (
    <tr className={`hover:bg-gray-100 dark:hover:bg-gray-700 ${isOdd ? 'bg-gray-50 dark:bg-gray-800/50' : 'bg-white dark:bg-gray-800'}`}>
      {/* Selection checkbox */}
      <td className="pl-4 pr-2 py-3 w-10">...</td>
      {/* Avatar/thumbnail */}
      <td className="w-10 px-2 py-3">...</td>
      {/* Name columns */}
      <td className="px-4 py-3 whitespace-nowrap">...</td>
      {/* Team column */}
      <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">...</td>
      {/* Labels column */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">...</td>
    </tr>
  );
}
```

### Pattern 3: Field Definition Fetch (from Phase 91)
**What:** Fetch custom field metadata for rendering
**When to use:** Getting list of fields to display as columns
**Example:**
```jsx
// Source: CustomFieldsSection.jsx lines 66-72
const { data: fieldDefs = [], isLoading } = useQuery({
  queryKey: ['custom-fields-metadata', postType],
  queryFn: async () => {
    const response = await prmApi.getCustomFieldsMetadata(postType);
    return response.data;
  },
});
```

### Pattern 4: Field Type Display Rendering (from Phase 91)
**What:** Type-specific value rendering
**When to use:** Displaying custom field values in columns
**Example:**
```jsx
// Source: CustomFieldsSection.jsx lines 75-305
const renderFieldValue = (field, value) => {
  if (value === null || value === undefined || value === '') {
    return <span className="text-gray-400 dark:text-gray-500 italic">-</span>;
  }
  switch (field.type) {
    case 'text':
    case 'textarea':
    case 'number':
      return <span>{String(value)}</span>;
    case 'email':
      return <a href={`mailto:${value}`} className="text-accent-600 hover:underline">{value}</a>;
    case 'true_false':
      return <span className={value ? 'text-green-600' : 'text-gray-500'}>{value ? 'Yes' : 'No'}</span>;
    // ... etc
  }
};
```

### Recommended Component Structure
```
src/
├── components/
│   ├── CustomFieldColumn.jsx          # Type-aware column renderer (compact)
│   ├── FieldFormPanel.jsx             # ADD: show_in_list_view toggle
│   └── ...
├── pages/
│   ├── People/
│   │   └── PeopleList.jsx             # MODIFY: add custom field columns
│   ├── Teams/
│   │   └── TeamsList.jsx          # MODIFY: add custom field columns
│   └── Settings/
│       └── CustomFields.jsx           # MODIFY: show list view column config
```

### Anti-Patterns to Avoid
- **Wide columns for custom fields:** Custom field columns should be narrow; use truncation/tooltips
- **Showing all fields by default:** Custom fields should be hidden until admin enables "show in list view"
- **Inline editing in list view:** Stadion pattern is click-to-detail for editing, not inline
- **Separate API call per column:** Fetch all field definitions once, filter by `show_in_list_view`

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Column sorting | Custom sort logic | Existing `sortField`/`sortOrder` state pattern | Already works for all columns |
| Truncating text | Custom CSS | Tailwind `truncate` class | Already used for website column |
| Date formatting | Custom formatter | `date-fns` format() | Already in use |
| Boolean display | Custom icons | Existing true_false render pattern | Consistent with detail view |
| Field metadata | Custom endpoint | `/stadion/v1/custom-fields/{post_type}/metadata` | Already exists from Phase 91 |
| Type-specific rendering | Build from scratch | Adapt from CustomFieldsSection.jsx | Same logic, compact display |

**Key insight:** Phase 91 already implemented full type-specific rendering. For list views, we adapt that rendering to be more compact (truncated text, smaller images, etc.).

## Common Pitfalls

### Pitfall 1: Column Width Management
**What goes wrong:** Custom field columns push built-in columns off screen or cause horizontal scroll.
**Why it happens:** Each custom field adds width without consideration of total table width.
**How to avoid:** Use `max-w-32` or similar constraint on custom field columns, with `truncate` class. Consider limiting to 3-4 visible custom field columns.
**Warning signs:** Horizontal scroll appears, columns are too narrow to read.

### Pitfall 2: Sorting on Complex Field Types
**What goes wrong:** Sorting by image, file, or relationship fields produces unexpected results.
**Why it happens:** These types don't have natural sort order.
**How to avoid:** Only enable sorting for sortable types: text, textarea, number, email, url, date, select, true_false. Disable sort button for other types.
**Warning signs:** Image columns sorted by attachment ID instead of something meaningful.

### Pitfall 3: Field Definition Sync
**What goes wrong:** Admin enables "show in list view" but column doesn't appear.
**Why it happens:** Field definition cached in TanStack Query, not refetched.
**How to avoid:** Invalidate `['custom-fields-metadata', postType]` query when field settings change.
**Warning signs:** Need page refresh to see column changes.

### Pitfall 4: Column Order Persistence
**What goes wrong:** Custom field columns appear in wrong order after reorder.
**Why it happens:** Order stored in field definition but not reflected in query results.
**How to avoid:** Sort enabled fields by `list_view_order` before rendering, with fallback to creation order.
**Warning signs:** Columns jump around unexpectedly.

### Pitfall 5: Data Loading Performance
**What goes wrong:** List views become slow when many custom fields enabled.
**Why it happens:** Each person/team already includes all ACF data, but rendering many columns is slow.
**How to avoid:** Virtual scrolling for very large lists, or limit custom field columns to 4-5 max.
**Warning signs:** Noticeable lag when scrolling or filtering.

## Code Examples

### Compact Column Renderer
```jsx
// CustomFieldColumn.jsx - Compact rendering for list view
function CustomFieldColumn({ field, value }) {
  if (value === null || value === undefined || value === '') {
    return <span className="text-gray-400 dark:text-gray-500">-</span>;
  }

  switch (field.type) {
    case 'text':
    case 'textarea':
      // Truncate long text
      return <span className="truncate block max-w-32">{String(value)}</span>;

    case 'number':
      return (
        <span>
          {field.prepend && <span className="text-gray-400">{field.prepend}</span>}
          {value}
          {field.append && <span className="text-gray-400">{field.append}</span>}
        </span>
      );

    case 'email':
      return (
        <a href={`mailto:${value}`} className="text-accent-600 dark:text-accent-400 hover:underline truncate block max-w-32">
          {value}
        </a>
      );

    case 'url':
      const displayUrl = value.replace(/^https?:\/\//, '').replace(/\/$/, '');
      return (
        <a href={value} target="_blank" rel="noopener noreferrer"
           className="text-accent-600 dark:text-accent-400 hover:underline truncate block max-w-32">
          {displayUrl}
        </a>
      );

    case 'date':
      try {
        // Simple date display for narrow column
        return <span>{format(new Date(value), 'MMM d, yyyy')}</span>;
      } catch {
        return <span>{value}</span>;
      }

    case 'select':
      // Show selected value (already display value from ACF)
      return <span className="truncate block max-w-32">{value}</span>;

    case 'checkbox':
      // Show count or first few items
      if (!Array.isArray(value) || value.length === 0) return <span>-</span>;
      if (value.length <= 2) return <span className="truncate block max-w-32">{value.join(', ')}</span>;
      return <span>{value.length} selected</span>;

    case 'true_false':
      return (
        <span className={value ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500'}>
          {value ? (field.ui_on_text || 'Yes') : (field.ui_off_text || 'No')}
        </span>
      );

    case 'image':
      const imageUrl = typeof value === 'object' ? (value.sizes?.thumbnail || value.url) : value;
      return imageUrl ? (
        <img src={imageUrl} alt="" className="w-8 h-8 rounded object-cover" />
      ) : <span>-</span>;

    case 'color_picker':
      return (
        <div
          className="w-6 h-6 rounded border border-gray-200 dark:border-gray-600"
          style={{ backgroundColor: value }}
          title={value}
        />
      );

    case 'relationship':
      const items = Array.isArray(value) ? value : [value];
      if (items.length === 0 || !items[0]) return <span>-</span>;
      if (items.length === 1) {
        const item = items[0];
        const name = typeof item === 'object' ? item.post_title : `#${item}`;
        return <span className="truncate block max-w-32">{name}</span>;
      }
      return <span>{items.length} linked</span>;

    case 'file':
    case 'link':
      // Just show icon for file/link in narrow column
      return <span className="text-gray-500">View</span>;

    default:
      return <span className="truncate block max-w-32">{String(value)}</span>;
  }
}
```

### Field Definition Extension
```php
// In Manager class, add to UPDATABLE_PROPERTIES:
'show_in_list_view',
'list_view_order',

// Default values in create_field():
'show_in_list_view' => 0,  // Hidden by default
'list_view_order' => 999,  // End of list by default
```

### FieldFormPanel Extension
```jsx
// Add to FieldFormPanel.jsx form state:
show_in_list_view: false,
list_view_order: 999,

// Add checkbox in form:
<div className="pt-4 border-t border-gray-200 dark:border-gray-700">
  <h4 className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3">Display Options</h4>
  <div className="flex items-center gap-2">
    <input
      id="show_in_list_view"
      name="show_in_list_view"
      type="checkbox"
      checked={formData.show_in_list_view}
      onChange={handleChange}
      className="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-accent-600"
    />
    <label htmlFor="show_in_list_view" className="text-sm text-gray-700 dark:text-gray-300">
      Show as column in list view
    </label>
  </div>
</div>
```

### List View Integration
```jsx
// In PeopleList.jsx, add to PersonListView:

// Fetch field definitions
const { data: customFields = [] } = useQuery({
  queryKey: ['custom-fields-metadata', 'person'],
  queryFn: async () => {
    const response = await prmApi.getCustomFieldsMetadata('person');
    return response.data;
  },
});

// Filter to list-view-enabled fields, sorted by order
const listViewFields = useMemo(() => {
  return customFields
    .filter(f => f.show_in_list_view)
    .sort((a, b) => (a.list_view_order || 999) - (b.list_view_order || 999));
}, [customFields]);

// In thead, after built-in columns:
{listViewFields.map(field => (
  <SortableHeader
    key={field.key}
    field={`custom_${field.name}`}
    label={field.label}
    currentSortField={sortField}
    currentSortOrder={sortOrder}
    onSort={onSort}
  />
))}

// In PersonListRow, after built-in cells:
{listViewFields.map(field => (
  <td key={field.key} className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
    <CustomFieldColumn field={field} value={person.acf?.[field.name]} />
  </td>
))}
```

## Data Model Changes

### Field Definition Extension
The existing field definition (stored via ACF) needs two new properties:

| Property | Type | Default | Purpose |
|----------|------|---------|---------|
| `show_in_list_view` | boolean | false | Whether to show field as column |
| `list_view_order` | integer | 999 | Column order (lower = leftmost) |

These are stored in the ACF field definition and exposed via the metadata endpoint.

### Metadata Endpoint Update
The `/stadion/v1/custom-fields/{post_type}/metadata` endpoint needs to include these properties:

```php
// In class-rest-custom-fields.php get_field_metadata()
if ( isset( $field['show_in_list_view'] ) ) {
    $display_props['show_in_list_view'] = (bool) $field['show_in_list_view'];
}
if ( isset( $field['list_view_order'] ) ) {
    $display_props['list_view_order'] = (int) $field['list_view_order'];
}
```

## Settings UI for Column Order

For SETT-07 (column order configuration), two approaches:

### Option A: Drag-and-drop in Settings/CustomFields
Add a reorderable list when "show in list view" is enabled. Use existing drag patterns from WordPress.

### Option B: Per-field order number
Simpler: show numeric input for order when "show in list view" is checked. Lower numbers appear first.

**Recommendation:** Option B for initial implementation. It's simpler and consistent with ACF's own approach. Drag-and-drop can be added later if needed.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Fixed columns only | Dynamic custom field columns | This phase | User configurable |
| Column config in UI preferences | Column config in field definitions | This phase | Admin-controlled, consistent for all users |

**Current in Stadion:**
- List views have fixed columns (name, team, workspace, labels)
- No user column customization exists
- All data already available via ACF in person/team responses

## Open Questions

Things that couldn't be fully resolved:

1. **Maximum Number of Custom Field Columns**
   - What we know: Too many columns cause horizontal scroll
   - What's unclear: Hard limit vs soft warning?
   - Recommendation: Show warning in settings when >4 columns enabled, no hard limit

2. **Column Width Strategy**
   - What we know: Custom fields need constrained width
   - What's unclear: Fixed width vs percentage-based?
   - Recommendation: Use `max-w-32` (128px) for most types, `w-10` for image/color

3. **Sort Implementation for Custom Fields**
   - What we know: Existing sort is client-side in JavaScript
   - What's unclear: Custom field sort should follow same pattern
   - Recommendation: Add custom field sort cases to existing `sortedPeople`/`sortedTeams` useMemo

## Files to Modify

### Backend (PHP)
| File | Change |
|------|--------|
| `includes/customfields/class-manager.php` | Add `show_in_list_view`, `list_view_order` to UPDATABLE_PROPERTIES |
| `includes/class-rest-custom-fields.php` | Expose new properties in metadata endpoint |

### Frontend (React)
| File | Change |
|------|--------|
| `src/components/FieldFormPanel.jsx` | Add "Show in list view" checkbox and order input |
| `src/components/CustomFieldColumn.jsx` | NEW: Compact column renderer |
| `src/pages/People/PeopleList.jsx` | Add custom field columns to table |
| `src/pages/Teams/TeamsList.jsx` | Add custom field columns to table |
| `src/pages/Settings/CustomFields.jsx` | Show column order config (optional) |

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/pages/People/PeopleList.jsx` - List view structure, SortableHeader pattern
- `/Users/joostdevalk/Code/stadion/src/pages/Teams/TeamsList.jsx` - Team list view patterns
- `/Users/joostdevalk/Code/stadion/src/components/CustomFieldsSection.jsx` - Type-specific rendering (Phase 91)
- `/Users/joostdevalk/Code/stadion/src/components/FieldFormPanel.jsx` - Field form patterns
- `/Users/joostdevalk/Code/stadion/includes/customfields/class-manager.php` - Field storage, updatable properties
- `/Users/joostdevalk/Code/stadion/includes/class-rest-custom-fields.php` - API structure, metadata endpoint

### Secondary (MEDIUM confidence)
- Phase 91 Research document - Type rendering patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use in codebase
- Architecture: HIGH - Patterns directly from existing code
- Data model changes: HIGH - Simple extension of existing Manager class
- Column rendering: MEDIUM - Adapted from detail view, may need tweaking

**Research date:** 2026-01-19
**Valid until:** 2026-02-19 (30 days - stable React patterns)
