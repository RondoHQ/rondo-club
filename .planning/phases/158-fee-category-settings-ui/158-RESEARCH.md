# Phase 158: Fee Category Settings UI - Research

**Researched:** 2026-02-09
**Domain:** React form UI for managing fee categories in WordPress theme settings
**Confidence:** HIGH

## Summary

Phase 158 requires building a settings UI for admins to manage fee categories per season. The good news: this project already has robust patterns established for exactly this type of work. The existing codebase provides complete reference implementations for:

1. **Drag-and-drop reordering** - CustomFields.jsx uses @dnd-kit with optimistic updates
2. **Form management** - react-hook-form is used throughout for validation and state
3. **Settings page structure** - Settings.jsx has a mature subtab system with admin-only sections
4. **API client patterns** - TanStack Query with optimistic updates is the standard
5. **Tailwind component patterns** - Consistent card/button/input styling established

The Phase 157 REST API is already implemented and provides structured validation responses (errors vs warnings), making frontend error handling straightforward. The primary work is UI composition using existing patterns, not introducing new libraries or techniques.

**Primary recommendation:** Build the fee category UI as a new subtab under Admin > Contributie, following the CustomFields.jsx drag-and-drop pattern and the existing Settings.jsx form patterns. Use react-hook-form for validation, TanStack Query for data fetching, and @dnd-kit for reordering.

## Standard Stack

The established libraries/tools for this project (already installed):

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| @dnd-kit/core | ^6.3.1 | Drag-and-drop core | Modern, accessible, React-first DnD library - already used in CustomFields.jsx |
| @dnd-kit/sortable | ^10.0.0 | Sortable list wrapper | Handles vertical list sorting - pattern established in CustomFields.jsx |
| react-hook-form | ^7.49.0 | Form state/validation | Lightweight, established pattern across 10 components |
| @tanstack/react-query | ^5.17.0 | Server state management | Standard for all API calls, supports optimistic updates |
| Tailwind CSS | ^3.4.0 | Styling | Consistent design system across entire app |
| lucide-react | ^0.309.0 | Icons | Standard icon library used throughout |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @dnd-kit/utilities | ^3.2.2 | Transform utilities | For drag preview transforms (CSS.Transform.toString) |
| axios | ^1.6.0 | HTTP client | Via prmApi abstraction in src/api/client.js |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| @dnd-kit | react-beautiful-dnd | dnd-kit is more modern, better mobile support, already installed |
| react-hook-form | formik | RHF is lighter, uncontrolled inputs, already the project standard |
| Custom validation | yup/zod | REST API already provides validation - simpler to rely on server-side |

**Installation:**
No new packages needed. All required dependencies already installed.

## Architecture Patterns

### Recommended Project Structure

Based on existing Settings.jsx pattern (file is 3827 lines with multiple subtabs):

```
src/pages/Settings/
├── Settings.jsx                    # Main settings page with tab routing
│   └── FeesSubtab component        # Existing simple fees subtab (lines 3283-3398)
│       → EXTEND with category management UI
├── CustomFields.jsx                # Reference for drag-and-drop pattern
├── RelationshipTypes.jsx           # Reference for CRUD with validation
└── Labels.jsx                      # Reference for simple list management
```

**Decision: Extend existing FeesSubtab vs. create new component**

Option A: Replace FeesSubtab inline (recommended)
- Keep all fee settings in one place
- Simpler for users (one subtab for all fee config)
- Con: Makes Settings.jsx even larger

Option B: Create new FeeCategories.jsx component
- Cleaner separation of concerns
- Easier to test in isolation
- Con: Adds navigation complexity (where do users find it?)

**Recommendation:** Option A (extend FeesSubtab) because fee amounts and category structure are tightly coupled concepts. Users configuring fees should see both.

### Pattern 1: Drag-and-Drop Sortable List (from CustomFields.jsx)

**What:** Vertical list with drag handles, optimistic reordering, visual feedback
**When to use:** Any list where order matters and needs to persist
**Example:**
```jsx
// Source: src/pages/Settings/CustomFields.jsx (lines 6-21, 241-251, 370-408)

import {
  DndContext,
  closestCenter,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

// Sensor configuration (supports mouse, touch, keyboard)
const sensors = useSensors(
  useSensor(PointerSensor, {
    activationConstraint: { distance: 8 }, // 8px threshold prevents accidental drags
  }),
  useSensor(TouchSensor, {
    activationConstraint: { delay: 200, tolerance: 8 }, // 200ms delay for mobile
  }),
  useSensor(KeyboardSensor, {
    coordinateGetter: sortableKeyboardCoordinates,
  })
);

// Sortable row component
function SortableRow({ item, onEdit, onDelete }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: item.key });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  return (
    <tr
      ref={setNodeRef}
      style={style}
      className={`group hover:bg-gray-50 dark:hover:bg-gray-800 ${isDragging ? 'shadow-lg opacity-90 bg-white dark:bg-gray-900' : ''}`}
    >
      <td className="px-2 py-4 w-10">
        <button
          {...attributes}
          {...listeners}
          className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-grab active:cursor-grabbing touch-none"
          aria-label="Sleep om te herordenen"
        >
          <GripVertical className="w-4 h-4" />
        </button>
      </td>
      {/* ...rest of row */}
    </tr>
  );
}

// Wrapper with DndContext
<DndContext
  sensors={sensors}
  collisionDetection={closestCenter}
  onDragEnd={handleDragEnd}
>
  <SortableContext items={items.map(i => i.key)} strategy={verticalListSortingStrategy}>
    <tbody>
      {items.map((item) => (
        <SortableRow key={item.key} item={item} onEdit={onEdit} onDelete={onDelete} />
      ))}
    </tbody>
  </SortableContext>
</DndContext>
```

### Pattern 2: Optimistic Mutation with Rollback (from CustomFields.jsx)

**What:** Update UI immediately, then sync with server; rollback on error
**When to use:** Drag-and-drop, toggles, any action where instant feedback matters
**Example:**
```jsx
// Source: src/pages/Settings/CustomFields.jsx (lines 222-238)

const reorderMutation = useMutation({
  mutationFn: async (order) => prmApi.reorderCustomFields(activeTab, order),
  onMutate: async (order) => {
    // Cancel outgoing queries
    await queryClient.cancelQueries({ queryKey: ['custom-fields', activeTab] });

    // Snapshot current state
    const previousFields = queryClient.getQueryData(['custom-fields', activeTab]);

    // Optimistically update
    const reorderedFields = order.map(key => previousFields.find(f => f.key === key));
    queryClient.setQueryData(['custom-fields', activeTab], reorderedFields);

    // Return rollback data
    return { previousFields };
  },
  onError: (err, order, context) => {
    // Rollback on error
    queryClient.setQueryData(['custom-fields', activeTab], context.previousFields);
  },
  onSettled: () => {
    // Re-fetch to ensure sync
    queryClient.invalidateQueries({ queryKey: ['custom-fields', activeTab] });
  },
});
```

### Pattern 3: Form Validation with react-hook-form (established pattern)

**What:** Uncontrolled forms with validation, used across 10 components
**When to use:** Any form with multiple inputs and validation needs
**Example:**
```jsx
// Source: src/components/TeamEditModal.jsx (lines 1-2, 71-76)

import { useForm } from 'react-hook-form';

const { register, handleSubmit, reset, formState: { errors } } = useForm({
  defaultValues: {
    title: '',
    website: '',
  },
});

// In JSX
<input
  {...register('title', { required: 'Name is required' })}
  className="input"
/>
{errors.title && <span className="text-red-500 text-sm">{errors.title.message}</span>}
```

### Pattern 4: Season Selector (from existing FeesSubtab)

**What:** Toggle or dropdown to switch between current and next season config
**Current implementation:** Two separate sections, one for each season (lines 3380-3381)
**Recommendation:** Add a season selector toggle at the top, show one season at a time

**Why:** Phase 158 UI-03 requires "season selector for managing category configs of different seasons". Current approach (showing both) works for simple amounts but becomes unwieldy with full category tables.

```jsx
// Recommended pattern (inspired by existing tab pattern)
const [selectedSeason, setSelectedSeason] = useState('current');

<div className="flex gap-2 mb-6">
  <button
    onClick={() => setSelectedSeason('current')}
    className={`px-4 py-2 rounded ${selectedSeason === 'current' ? 'bg-accent-600 text-white' : 'bg-gray-200 text-gray-700'}`}
  >
    Huidig seizoen ({currentSeasonKey})
  </button>
  <button
    onClick={() => setSelectedSeason('next')}
    className={`px-4 py-2 rounded ${selectedSeason === 'next' ? 'bg-accent-600 text-white' : 'bg-gray-200 text-gray-700'}`}
  >
    Volgend seizoen ({nextSeasonKey})
  </button>
</div>

{/* Then show only the selected season's category table */}
```

### Anti-Patterns to Avoid

- **Controlled form inputs for large lists:** Use react-hook-form's uncontrolled pattern, not useState for each field
- **Eager validation on every keystroke:** Validate on blur or submit, not onChange (UX issue for free-form text)
- **Hardcoded fee types:** Phase 157 removes hardcoded types (mini, pupil, etc.) - UI must be fully dynamic
- **Missing touch support in DnD:** Always include TouchSensor with delay for mobile devices

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Drag-and-drop | Custom mousedown/touchstart handlers | @dnd-kit/sortable | Accessibility (keyboard), mobile, edge cases (scrolling, nested), cancel on escape |
| Form validation | Custom onChange validators | react-hook-form + server validation | Uncontrolled inputs perform better, validation is already in REST API |
| Optimistic updates | Manual state tracking | TanStack Query onMutate/onError | Rollback on error, automatic retry, request deduplication |
| Error display | Toast notifications | Inline validation messages | Contextual errors (field-level) are clearer than global toasts for forms |
| Age class coverage visualization | Custom overlap calculator | Simple pass-through from API warnings | Phase 157 API already returns structured warnings for overlaps |

**Key insight:** The Phase 157 REST API provides rich validation (errors vs warnings, structured field paths). Don't reimplement validation in the frontend - display what the API returns and let users fix issues server-side.

## Common Pitfalls

### Pitfall 1: Not Handling API Validation Warnings

**What goes wrong:** Phase 157 API returns both `errors` (block save) and `warnings` (informational). If UI only shows errors, users miss important info like "Age class 'Onder 10' is assigned to multiple categories."

**Why it happens:** Traditional APIs only return errors. This API distinguishes errors vs warnings.

**How to avoid:**
- Check `response.warnings` in addition to catching error responses
- Display warnings as yellow/info messages, errors as red/blocking
- Don't block form submission on warnings, but show them prominently

**Warning signs:**
```jsx
// BAD: Only handles errors
try {
  await mutation.mutateAsync(data);
} catch (error) {
  setErrorMessage(error.response.data.message);
}

// GOOD: Handles both errors and warnings
try {
  const response = await mutation.mutateAsync(data);
  if (response.warnings) {
    setWarnings(response.warnings); // Show yellow info
  }
  setSuccess(true);
} catch (error) {
  if (error.response?.data?.errors) {
    setErrors(error.response.data.errors); // Show red blocking
  }
}
```

### Pitfall 2: Assuming All Categories Have age_classes

**What goes wrong:** A category with `age_classes: []` or `null` is valid (acts as catch-all for unmatched age classes). UI that requires age_classes will break or prevent saving valid configs.

**Why it happens:** Phase 156 CONTEXT.md allows empty age_classes as catch-all pattern.

**How to avoid:**
- Make age_classes field optional in forms
- Show "(Catch-all)" or similar UI hint when age_classes is empty
- Don't validate age_classes as required

**Warning signs:** Form validation that marks age_classes as required

### Pitfall 3: Mutating Data Before Drag-End

**What goes wrong:** Updating state on dragStart or dragMove causes stutter and broken animations.

**Why it happens:** React re-renders during drag interrupt the DnD transform calculations.

**How to avoid:**
- Only update state in `onDragEnd` handler, never in dragStart/dragMove
- Use `isDragging` class for visual feedback, not state changes
- Let @dnd-kit manage transform during drag

**Warning signs:** Drag animations that jump or snap back during drag

### Pitfall 4: Not Persisting sort_order to Server

**What goes wrong:** User drags categories to reorder, sees change locally, refreshes page, order reverts.

**Why it happens:** Frontend derives sort_order from array index but doesn't save it to the API.

**How to avoid:**
- After drag-end, rebuild categories object with updated sort_order values
- Send entire updated categories object to POST /membership-fees/settings
- Use optimistic update so UI feels instant

**Warning signs:** Reordering works until page refresh

### Pitfall 5: Case-Sensitive Age Class Matching in Coverage Display

**What goes wrong:** Age class "Onder 10" in config doesn't match "onder 10" from API, UI shows gap when there isn't one.

**Why it happens:** Sportlink data is inconsistent with capitalization.

**How to avoid:** Phase 157 validation already normalizes (lines 2669, 2724 in class-rest-api.php use `strtolower(trim())`). Trust the API warnings for overlaps, don't reimplement matching.

**Warning signs:** UI showing "gap" warnings that admin can't reproduce manually

## Code Examples

Verified patterns from existing codebase:

### Example 1: Fee Settings API Client Methods

```javascript
// Source: src/api/client.js (lines 294-296)
// Already implemented in codebase

prmApi = {
  // Membership Fee Settings (admin only)
  getMembershipFeeSettings: () => api.get('/rondo/v1/membership-fees/settings'),
  updateMembershipFeeSettings: (settings, season) =>
    api.post('/rondo/v1/membership-fees/settings', { ...settings, season }),
}
```

**Usage in component:**
```jsx
// Fetch settings
const { data: feeSettings, isLoading } = useQuery({
  queryKey: ['membership-fee-settings'],
  queryFn: async () => {
    const response = await prmApi.getMembershipFeeSettings();
    return response.data;
  },
});

// Access categories for current season
const currentCategories = feeSettings?.current_season?.categories || {};
const nextCategories = feeSettings?.next_season?.categories || {};
```

### Example 2: Complete CRUD with Drag-and-Drop

```jsx
// Pattern composite from CustomFields.jsx

function FeeCategoryManager({ season, seasonKey, categories, onUpdate }) {
  const queryClient = useQueryClient();

  // Mutations
  const updateMutation = useMutation({
    mutationFn: async (updatedCategories) =>
      prmApi.updateMembershipFeeSettings({ categories: updatedCategories }, seasonKey),
    onSuccess: (response) => {
      queryClient.setQueryData(['membership-fee-settings'], response.data);
      if (response.data.warnings) {
        setWarnings(response.data.warnings);
      }
    },
    onError: (error) => {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
    },
  });

  // Drag-and-drop handler
  const handleDragEnd = (event) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const categoryArray = Object.values(categories);
      const oldIndex = categoryArray.findIndex(c => c.slug === active.id);
      const newIndex = categoryArray.findIndex(c => c.slug === over.id);

      // Reorder
      const reordered = arrayMove(categoryArray, oldIndex, newIndex);

      // Rebuild with new sort_order
      const updatedCategories = reordered.reduce((acc, cat, index) => {
        acc[cat.slug] = { ...cat, sort_order: index };
        return acc;
      }, {});

      updateMutation.mutate(updatedCategories);
    }
  };

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 8 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  return (
    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
      <SortableContext items={Object.keys(categories)} strategy={verticalListSortingStrategy}>
        <div className="space-y-2">
          {Object.values(categories)
            .sort((a, b) => a.sort_order - b.sort_order)
            .map(category => (
              <SortableCategoryRow
                key={category.slug}
                category={category}
                onEdit={handleEdit}
                onDelete={handleDelete}
              />
            ))}
        </div>
      </SortableContext>
    </DndContext>
  );
}
```

### Example 3: Age Class Coverage Display

```jsx
// Pattern for visualizing which age classes are covered
// Use API warnings directly - don't reimplement overlap detection

function AgeCoverageSummary({ categories, warnings }) {
  // Get all unique age classes across categories
  const allAgeClasses = new Set();
  Object.values(categories).forEach(cat => {
    if (cat.age_classes && Array.isArray(cat.age_classes)) {
      cat.age_classes.forEach(ac => allAgeClasses.add(ac));
    }
  });

  // Find overlaps from API warnings
  const overlaps = warnings?.filter(w => w.field?.includes('age_classes')) || [];

  return (
    <div className="card p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
      <h4 className="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
        Leeftijdsklasse dekking
      </h4>
      <div className="space-y-2">
        {Object.entries(categories)
          .sort((a, b) => a[1].sort_order - b[1].sort_order)
          .map(([slug, cat]) => (
            <div key={slug} className="flex items-center gap-2 text-sm">
              <span className="font-medium text-gray-900 dark:text-gray-100 w-24">
                {cat.label}:
              </span>
              <span className="text-gray-600 dark:text-gray-400">
                {cat.age_classes?.length > 0
                  ? cat.age_classes.join(', ')
                  : '(Catch-all voor niet-toegewezen klassen)'
                }
              </span>
            </div>
          ))}
      </div>

      {overlaps.length > 0 && (
        <div className="mt-3 pt-3 border-t border-blue-200 dark:border-blue-700">
          <p className="text-xs font-semibold text-amber-700 dark:text-amber-400 mb-1">
            Overlappende toewijzingen:
          </p>
          {overlaps.map((warning, idx) => (
            <p key={idx} className="text-xs text-amber-600 dark:text-amber-500">
              {warning.message}
            </p>
          ))}
        </div>
      )}
    </div>
  );
}
```

### Example 4: Category Edit Form with react-hook-form

```jsx
// Pattern for inline editing of category fields

function CategoryEditForm({ category, onSave, onCancel }) {
  const { register, handleSubmit, formState: { errors } } = useForm({
    defaultValues: {
      slug: category?.slug || '',
      label: category?.label || '',
      amount: category?.amount || 0,
      age_classes: category?.age_classes?.join(', ') || '', // CSV input
      is_youth: category?.is_youth || false,
    },
  });

  const onSubmit = (data) => {
    const formatted = {
      ...data,
      age_classes: data.age_classes
        .split(',')
        .map(s => s.trim())
        .filter(s => s.length > 0),
      amount: parseFloat(data.amount),
    };
    onSave(formatted);
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Slug (URL-vriendelijk)
        </label>
        <input
          {...register('slug', {
            required: 'Slug is verplicht',
            pattern: {
              value: /^[a-z0-9-]+$/,
              message: 'Alleen kleine letters, cijfers en streepjes'
            }
          })}
          className="input"
          disabled={!!category} // Can't change slug when editing
        />
        {errors.slug && <span className="text-red-500 text-sm">{errors.slug.message}</span>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Label (weergavenaam)
        </label>
        <input
          {...register('label', { required: 'Label is verplicht' })}
          className="input"
        />
        {errors.label && <span className="text-red-500 text-sm">{errors.label.message}</span>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Bedrag (&euro;)
        </label>
        <input
          type="number"
          step="0.01"
          {...register('amount', {
            required: 'Bedrag is verplicht',
            min: { value: 0, message: 'Bedrag kan niet negatief zijn' }
          })}
          className="input"
        />
        {errors.amount && <span className="text-red-500 text-sm">{errors.amount.message}</span>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Leeftijdsklassen (komma-gescheiden, bijv. "Onder 9, Onder 10")
        </label>
        <input
          {...register('age_classes')}
          placeholder="Laat leeg voor catch-all"
          className="input"
        />
        <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
          Sportlink leeftijdsklassen die onder deze categorie vallen. Laat leeg om alle niet-toegewezen klassen te matchen.
        </p>
      </div>

      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          {...register('is_youth')}
          id="is_youth"
          className="rounded"
        />
        <label htmlFor="is_youth" className="text-sm text-gray-700 dark:text-gray-300">
          Jeugdcategorie (voor family discounts)
        </label>
      </div>

      <div className="flex gap-2">
        <button type="submit" className="btn-primary">
          Opslaan
        </button>
        <button type="button" onClick={onCancel} className="btn-secondary">
          Annuleren
        </button>
      </div>
    </form>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded fee types in Settings UI | Fully dynamic category-driven UI | Phase 157 (Feb 2026) | UI must read categories from API, not assume mini/pupil/junior/senior/recreant/donateur |
| Flat amount inputs per fee type | Structured category objects with age_classes | Phase 155-157 (Feb 2026) | UI now manages complex objects, not simple number inputs |
| Age ranges (min/max) | Sportlink age class strings | Phase 156 (Feb 2026) | UI displays/edits string arrays, not number inputs |
| Client-side validation | Server-side validation with structured errors | Phase 157 (Feb 2026) | UI displays API errors, doesn't reimplement validation |

**Deprecated/outdated:**
- Hardcoded fee type names (mini, pupil, junior, senior, recreant, donateur) removed from API in Phase 157
- Simple number input UI pattern (lines 3293-3398 in Settings.jsx) is outdated for category management - needs table/card-based layout

## Open Questions

Things that couldn't be fully resolved:

1. **Should catch-all categories be shown differently in the UI?**
   - What we know: Categories with empty age_classes are valid (catch-all pattern per Phase 156)
   - What's unclear: Should they have special visual treatment (icon, badge, different row style)?
   - Recommendation: Show "(Catch-all)" text hint next to age classes field when empty, subtle visual distinction

2. **How to handle season switching UX?**
   - What we know: Users need to manage categories for both current and next season (UI-03)
   - What's unclear: Toggle buttons (like example), dropdown, or side-by-side view?
   - Recommendation: Toggle buttons (shown in Pattern 4) - simpler than dropdown, clearer than side-by-side for mobile

3. **Should adding a new category show inline or in a modal?**
   - What we know: CustomFields.jsx uses a slide-out panel (FieldFormPanel component)
   - What's unclear: Does a modal/panel make sense for categories, or inline row editing?
   - Recommendation: Inline editing for simplicity (categories have fewer fields than custom fields, panel might be overkill)

## Sources

### Primary (HIGH confidence)
- Existing codebase at `/Users/joostdevalk/Code/rondo/rondo-club/src/`
  - CustomFields.jsx - drag-and-drop pattern with @dnd-kit
  - Settings.jsx - settings page structure, subtabs, form patterns
  - api/client.js - API client methods including getMembershipFeeSettings
  - TeamEditModal.jsx - react-hook-form pattern
  - package.json - installed dependencies
- Phase 157 implementation at `includes/class-rest-api.php` (lines 2605-2750)
  - API endpoint shapes
  - Validation structure (errors vs warnings)
  - Category data model

### Secondary (MEDIUM confidence)
- Phase 155-157 PLAN.md and CONTEXT.md files
  - Data model decisions
  - Validation rules
  - Age class matching patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries installed, patterns established in codebase
- Architecture: HIGH - Multiple reference implementations in existing codebase
- Pitfalls: HIGH - Derived from actual API implementation and data model constraints
- Code examples: HIGH - All examples extracted from working codebase or directly match established patterns

**Research date:** 2026-02-09
**Valid until:** 2026-03-11 (30 days - stable libraries, no major version changes expected)
