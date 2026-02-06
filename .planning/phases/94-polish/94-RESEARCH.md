# Phase 94: Polish - Research

**Researched:** 2026-01-20
**Domain:** Custom Fields Settings UX (drag-and-drop, validation)
**Confidence:** HIGH

## Summary

This phase adds polish to the custom field management UI: drag-and-drop reordering, required/unique validation options, and placeholder text support. The research found excellent conditions for implementation:

1. **dnd-kit already in use** - The project has `@dnd-kit/core`, `@dnd-kit/sortable`, and `@dnd-kit/utilities` installed (v6.3.1/10.0.0/3.2.2), with a working implementation in `DashboardCustomizeModal.jsx` that can be directly referenced.

2. **ACF supports menu_order natively** - Fields have a `menu_order` property that controls display order. The `acf_update_field()` function already accepts this property. The CustomFields Manager already includes `menu_order` implicitly through ACF's field storage.

3. **Validation infrastructure exists** - ACF's `acf/validate_value` filter provides server-side validation. Frontend validation via `react-hook-form` is already in use in `CustomFieldsEditModal.jsx`.

4. **Placeholder already supported** - The backend Manager class already includes `placeholder` in `UPDATABLE_PROPERTIES` and the REST API accepts it. The frontend `FieldFormPanel.jsx` already has placeholder inputs for text fields.

**Primary recommendation:** Follow existing patterns from `DashboardCustomizeModal.jsx` for drag-and-drop, add `menu_order` to field storage, implement validation through both frontend (react-hook-form) and backend (acf/validate_value), and expose placeholder consistently across all text-based field types.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| @dnd-kit/core | 6.3.1 | Drag-and-drop primitives | Already installed, proven in DashboardCustomizeModal |
| @dnd-kit/sortable | 10.0.0 | Sortable list presets | Already installed, verticalListSortingStrategy ideal for field lists |
| @dnd-kit/utilities | 3.2.2 | CSS transform utilities | Already installed, provides CSS.Transform.toString |
| react-hook-form | 7.49.0 | Form validation | Already used in CustomFieldsEditModal |
| ACF Pro | 6.x | Field storage | Native menu_order, required, and validate_value support |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| lucide-react | 0.309.0 | Icons (GripVertical, AlertCircle) | Drag handle, validation indicators |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| @dnd-kit | react-beautiful-dnd | Deprecated by Atlassian, dnd-kit already in project |
| @dnd-kit | Native HTML5 DnD | Less accessible, more browser inconsistencies |
| Custom validation | zod | Adds dependency, react-hook-form sufficient |

**Installation:**
No new dependencies required - all libraries already installed.

## Architecture Patterns

### Recommended Component Structure
```
src/
├── pages/Settings/
│   └── CustomFields.jsx        # Add DndContext, SortableContext wrapper
├── components/
│   ├── SortableFieldRow.jsx    # New: Sortable table row component
│   └── FieldFormPanel.jsx      # Extend: Add validation/placeholder options
└── hooks/
    └── useFieldReorder.js      # New: Handle reorder mutation + optimistic update
```

### Pattern 1: Sortable Table Rows (following DashboardCustomizeModal pattern)
**What:** Wrap field list in DndContext/SortableContext, make each row a SortableItem
**When to use:** Settings page field list
**Example:**
```jsx
// Source: Existing pattern from src/components/DashboardCustomizeModal.jsx
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, TouchSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

function SortableFieldRow({ field, onEdit, onDelete }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: field.key });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  return (
    <tr ref={setNodeRef} style={style} className={isDragging ? 'shadow-lg opacity-90' : ''}>
      <td>
        <button {...attributes} {...listeners} className="cursor-grab active:cursor-grabbing">
          <GripVertical className="w-4 h-4" />
        </button>
      </td>
      {/* ... other cells */}
    </tr>
  );
}
```

### Pattern 2: Optimistic Reorder with Backend Sync
**What:** Update UI immediately, sync to server, rollback on error
**When to use:** Drag-and-drop operations
**Example:**
```jsx
// Source: Pattern from useMutation + queryClient
const reorderMutation = useMutation({
  mutationFn: async ({ postType, orderedKeys }) => {
    // Send new order to backend
    return prmApi.reorderCustomFields(postType, orderedKeys);
  },
  onMutate: async ({ postType, orderedKeys }) => {
    // Cancel any outgoing refetches
    await queryClient.cancelQueries({ queryKey: ['custom-fields', postType] });

    // Snapshot current
    const previousFields = queryClient.getQueryData(['custom-fields', postType]);

    // Optimistically update
    const reorderedFields = orderedKeys.map(key =>
      previousFields.find(f => f.key === key)
    );
    queryClient.setQueryData(['custom-fields', postType], reorderedFields);

    return { previousFields };
  },
  onError: (err, variables, context) => {
    // Rollback
    queryClient.setQueryData(['custom-fields', variables.postType], context.previousFields);
  },
});
```

### Pattern 3: Dual Validation (Frontend + Backend)
**What:** Validate on frontend for UX, backend for security
**When to use:** Required fields, unique constraints
**Example:**
```jsx
// Frontend: react-hook-form validation
const { register, formState: { errors } } = useForm({
  mode: 'onBlur', // Validate on blur
});

<input
  {...register(field.name, {
    required: field.required ? `${field.label} is required` : false,
  })}
/>
{errors[field.name] && (
  <p className="text-sm text-red-600">{errors[field.name].message}</p>
)}
```

```php
// Backend: ACF validate_value hook
// Source: https://www.advancedcustomfields.com/resources/acf-validate_value/
add_filter('acf/validate_value', function($valid, $value, $field, $input_name) {
    if ($valid !== true) return $valid; // Bail if already invalid

    // Required validation
    if (!empty($field['required']) && empty($value)) {
        return sprintf(__('%s is required.'), $field['label']);
    }

    // Unique validation
    if (!empty($field['unique'])) {
        global $post;
        $existing = new WP_Query([
            'post_type' => $post->post_type,
            'meta_key' => $field['name'],
            'meta_value' => $value,
            'post__not_in' => [$post->ID],
            'posts_per_page' => 1,
        ]);
        if ($existing->have_posts()) {
            return sprintf(__('%s must be unique. This value is already in use.'), $field['label']);
        }
    }

    return $valid;
}, 10, 4);
```

### Anti-Patterns to Avoid
- **Re-rendering entire list on drag:** Use memoization and stable keys
- **Frontend-only unique validation:** Must check database; frontend can only show "checking..."
- **Saving on every drag move:** Only save on drop (onDragEnd)
- **Direct array index as key:** Use field.key (stable identifier)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Drag-and-drop | Native HTML5 drag events | @dnd-kit/sortable | Accessibility, touch support, keyboard navigation |
| Array reordering | Manual splice/push | arrayMove from @dnd-kit/sortable | Handles edge cases correctly |
| Field order persistence | Custom order column | ACF menu_order property | Already supported by acf_update_field |
| Unique validation | Custom DB queries | ACF acf/validate_value hook | Integration with ACF's error display |
| Form validation UI | Manual error state | react-hook-form errors | Already integrated, handles all states |

**Key insight:** ACF Pro already has built-in support for field ordering (menu_order) and validation (required, acf/validate_value). The infrastructure exists - we're just exposing it to the React frontend.

## Common Pitfalls

### Pitfall 1: Drag Handle vs Row Dragging
**What goes wrong:** Entire row is draggable, causing accidental drags when clicking buttons
**Why it happens:** Spreading {...attributes} {...listeners} on the row instead of drag handle
**How to avoid:** Only attach listeners to a dedicated drag handle element
**Warning signs:** Users report accidental reorders when trying to edit/delete

### Pitfall 2: menu_order Zero Confusion
**What goes wrong:** First field (menu_order=0) gets moved to end after sync
**Why it happens:** ACF bug with empty() check: `empty(0)` returns true
**How to avoid:** Always start menu_order at 1, not 0. Or ensure backend uses `isset()` not `empty()`
**Warning signs:** First field keeps jumping to last position

### Pitfall 3: Unique Validation Race Condition
**What goes wrong:** Two users save same value simultaneously, both pass validation
**Why it happens:** No database-level unique constraint, only PHP check
**How to avoid:** Consider this acceptable for a personal CRM with limited concurrent users; add post meta unique key if needed
**Warning signs:** Duplicate values exist despite unique constraint

### Pitfall 4: Placeholder vs Instructions Confusion
**What goes wrong:** Users set placeholder for all fields, cluttering the form
**Why it happens:** Unclear which field types support placeholder
**How to avoid:** Only show placeholder option for text, textarea, email, url, number, select
**Warning signs:** Placeholder option visible for checkbox, true/false, image fields

### Pitfall 5: Validation Timing on Draft Save
**What goes wrong:** Required validation triggers on draft save, frustrating users
**Why it happens:** ACF validates on any save, including drafts
**How to avoid:** Frontend validates only on publish; backend uses conditional logic for draft vs publish
**Warning signs:** Users can't save incomplete drafts

## Code Examples

Verified patterns from official sources and existing codebase:

### Sortable List Setup (from DashboardCustomizeModal.jsx)
```jsx
// Source: /Users/joostdevalk/Code/rondo/rondo-club/src/components/DashboardCustomizeModal.jsx lines 107-134
const sensors = useSensors(
  useSensor(PointerSensor, {
    activationConstraint: { distance: 8 },
  }),
  useSensor(TouchSensor, {
    activationConstraint: { delay: 200, tolerance: 8 },
  }),
  useSensor(KeyboardSensor, {
    coordinateGetter: sortableKeyboardCoordinates,
  })
);

const handleDragEnd = (event) => {
  const { active, over } = event;
  if (over && active.id !== over.id) {
    setFields((items) => {
      const oldIndex = items.findIndex(f => f.key === active.id);
      const newIndex = items.findIndex(f => f.key === over.id);
      return arrayMove(items, oldIndex, newIndex);
    });
    // Trigger save mutation here
  }
};
```

### ACF Validation Filter (from official docs)
```php
// Source: https://www.advancedcustomfields.com/resources/acf-validate_value/
function stadion_validate_custom_field($valid, $value, $field, $input_name) {
    // Early exit if already invalid
    if ($valid !== true) {
        return $valid;
    }

    // Only validate our custom fields (key prefix check)
    if (strpos($field['key'], 'field_custom_') !== 0) {
        return $valid;
    }

    // Required validation (ACF handles this, but we can customize message)
    // Unique validation is custom
    if (!empty($field['unique']) && !empty($value)) {
        global $post;
        $existing = get_posts([
            'post_type' => $post->post_type,
            'meta_key' => $field['name'],
            'meta_value' => $value,
            'exclude' => [$post->ID],
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);
        if (!empty($existing)) {
            return sprintf('%s must be unique.', $field['label']);
        }
    }

    return $valid;
}
add_filter('acf/validate_value', 'stadion_validate_custom_field', 10, 4);
```

### Bulk Update Field Order API
```php
// New endpoint: PUT /rondo/v1/custom-fields/{post_type}/order
register_rest_route(
    'rondo/v1',
    '/custom-fields/(?P<post_type>person|team)/order',
    [
        'methods' => 'PUT',
        'callback' => [$this, 'reorder_fields'],
        'permission_callback' => [$this, 'update_item_permissions_check'],
        'args' => [
            'order' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'required' => true,
                'description' => 'Array of field keys in desired order',
            ],
        ],
    ]
);

public function reorder_fields($request) {
    $post_type = $request->get_param('post_type');
    $order = $request->get_param('order');

    foreach ($order as $menu_order => $field_key) {
        $field = acf_get_field($field_key);
        if ($field) {
            $field['menu_order'] = $menu_order + 1; // Start at 1, not 0
            acf_update_field($field);
        }
    }

    return rest_ensure_response(['success' => true]);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| react-beautiful-dnd | @dnd-kit | 2023 | rbd deprecated, dnd-kit is successor |
| ACF field order manual DB | menu_order property | ACF 5.0+ | Built-in, no custom queries needed |
| Custom validation hooks | react-hook-form + ACF validate_value | - | Dual validation ensures UX + security |

**Deprecated/outdated:**
- react-beautiful-dnd: Atlassian deprecated it; use @dnd-kit instead
- Manual post_parent for field order: ACF uses menu_order natively

## Open Questions

Things that couldn't be fully resolved:

1. **Unique validation scope**
   - What we know: Can check uniqueness per post_type or globally
   - What's unclear: Should uniqueness be per-user (access control) or global?
   - Recommendation: Start with per-user (respects access control), allow toggle

2. **Auto-save vs explicit save for reorder**
   - What we know: DashboardCustomizeModal has explicit save button
   - What's unclear: Should field reorder auto-save on drop or require Save?
   - Recommendation: Auto-save on drop (immediate feedback), with undo option

3. **Validation in edit modal vs detail page**
   - What we know: CustomFieldsEditModal handles all custom field editing
   - What's unclear: How does validation error display integrate with modal?
   - Recommendation: Show inline errors under each field, block save if invalid

## Sources

### Primary (HIGH confidence)
- @dnd-kit official docs: https://docs.dndkit.com/presets/sortable
- ACF acf/validate_value: https://www.advancedcustomfields.com/resources/acf-validate_value/
- Existing codebase: `src/components/DashboardCustomizeModal.jsx` (working dnd-kit pattern)
- Existing codebase: `includes/customfields/class-manager.php` (field storage)

### Secondary (MEDIUM confidence)
- ACF acf/update_field: https://www.advancedcustomfields.com/resources/acf-update_field/
- ACF field menu_order: Confirmed via acf-json inspection

### Tertiary (LOW confidence)
- None - all findings verified with official sources or existing code

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already installed and in use
- Architecture: HIGH - Patterns exist in codebase (DashboardCustomizeModal)
- Pitfalls: MEDIUM - Some based on general ACF knowledge, not project-specific

**Research date:** 2026-01-20
**Valid until:** 30 days (stable libraries, no anticipated breaking changes)
