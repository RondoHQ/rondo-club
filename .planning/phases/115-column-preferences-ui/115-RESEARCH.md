# Phase 115: Column Preferences UI - Research

**Researched:** 2026-01-29
**Domain:** React UI Components, Drag-and-Drop, Column Resizing, User Preferences
**Confidence:** HIGH

## Summary

Research into implementing a column customization UI for the People list, allowing users to show/hide columns, reorder via drag-and-drop, and adjust column widths. The backend API from Phase 114 already exists at `/stadion/v1/user/list-preferences`.

Key findings: The codebase already uses dnd-kit extensively (CustomFields.jsx implements drag-to-reorder for field management with the same library). The People list currently renders columns based on a `show_in_list_view` custom field property, which will be replaced by per-user preferences. Standard approaches exist for column resizing using native CSS with minimal JavaScript event handling. TanStack Query optimistic updates provide instant UI feedback while preferences save to the server.

The implementation pattern is straightforward: settings modal (similar to bulk action modals already in PeopleList.jsx), drag-drop list using existing dnd-kit setup, column resize via pointer events on dividers, and instant-apply preference updates with optimistic UI.

**Primary recommendation:** Leverage dnd-kit (already installed and proven), use CSS position: sticky for name column, implement resize with native pointer events, apply TanStack Query optimistic updates for instant feedback.

## Standard Stack

### Core Technologies

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| @dnd-kit/core | ^6.3.1 | Drag-and-drop infrastructure | Already installed, modern toolkit with accessibility, used in CustomFields.jsx |
| @dnd-kit/sortable | ^10.0.0 | Sortable list management | Already installed, handles reordering logic |
| @dnd-kit/utilities | ^3.2.2 | Transform utilities for drag animations | Already installed, provides CSS.Transform helpers |
| TanStack Query | Current | State management and optimistic updates | Already in use throughout app, provides caching and invalidation |
| React 18 | Current | UI framework | Project requirement |

### Supporting Utilities

| Utility | Purpose | When to Use |
|---------|---------|-------------|
| PointerEvent API | Column resize drag interactions | Capturing mouse/touch drag on column dividers |
| CSS position: sticky | Fixed name column | Keep first column visible during horizontal scroll |
| localStorage | Column width persistence | Store widths locally, sync with user_meta on change |
| useMutation (TanStack) | Preference updates | PATCH to /stadion/v1/user/list-preferences |
| useQuery (TanStack) | Load preferences | GET from /stadion/v1/user/list-preferences |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| dnd-kit | react-beautiful-dnd | react-beautiful-dnd is archived/unmaintained, dnd-kit is modern |
| dnd-kit | hello-pangea/dnd | hello-pangea is simpler but less customizable, dnd-kit already installed |
| Native PointerEvents | react-table-column-resizer | Library adds dependency, native approach is simple for this use case |
| localStorage + sync | Only user_meta | Every resize would hit server, localStorage provides instant persistence |
| Optimistic updates | Loading states | User sees lag, optimistic updates feel instant |

**Installation:**
No additional packages needed — dnd-kit suite already installed, other utilities are native web APIs.

## Architecture Patterns

### Recommended Component Structure

```
src/pages/People/
├── PeopleList.jsx               # Main list component (already exists)
├── ColumnSettingsModal.jsx      # New: Settings modal for show/hide and reorder
└── ResizableTableHeader.jsx     # New: Table header with resize handles

src/hooks/
├── useListPreferences.js        # New: Hook for GET/PATCH preferences
└── useColumnResize.js           # New: Hook for resize event handling
```

### Pattern 1: Column Preferences Hook (API Integration)

**What:** Custom hook wrapping TanStack Query for preferences GET/PATCH with optimistic updates.

**When to use:** Any component that reads or updates column preferences (modal and table).

**Example:**
```javascript
// Source: Adapted from TanStack Query optimistic update patterns
// https://tanstack.com/query/latest/docs/framework/react/guides/optimistic-updates

export function useListPreferences() {
  const queryClient = useQueryClient();

  // GET preferences
  const { data, isLoading } = useQuery({
    queryKey: ['user', 'list-preferences'],
    queryFn: async () => {
      const response = await prmApi.get('/stadion/v1/user/list-preferences');
      return response.data;
    },
  });

  // PATCH preferences with optimistic update
  const updateMutation = useMutation({
    mutationFn: async (updates) => {
      return prmApi.patch('/stadion/v1/user/list-preferences', updates);
    },
    onMutate: async (updates) => {
      // Cancel outgoing refetches
      await queryClient.cancelQueries({ queryKey: ['user', 'list-preferences'] });

      // Snapshot previous value
      const previous = queryClient.getQueryData(['user', 'list-preferences']);

      // Optimistically update
      queryClient.setQueryData(['user', 'list-preferences'], (old) => ({
        ...old,
        ...updates,
      }));

      return { previous };
    },
    onError: (err, updates, context) => {
      // Rollback on error
      queryClient.setQueryData(['user', 'list-preferences'], context.previous);
    },
    onSettled: () => {
      // Always refetch to ensure sync with server
      queryClient.invalidateQueries({ queryKey: ['user', 'list-preferences'] });
    },
  });

  return {
    preferences: data,
    isLoading,
    updatePreferences: updateMutation.mutate,
    isUpdating: updateMutation.isPending,
  };
}
```

**Apply to Phase 115:**
- ColumnSettingsModal uses this hook for show/hide and reorder updates
- PeopleList uses this hook to fetch visible columns on mount
- Column width adjustments debounce and batch updates to avoid excessive API calls

### Pattern 2: Drag-and-Drop Column Reordering (dnd-kit Integration)

**What:** Sortable list in settings modal using dnd-kit with vertical list strategy.

**When to use:** Settings modal column list (not table headers — per CONTEXT.md, reordering happens only in modal).

**Example from existing codebase:**
```javascript
// Source: src/pages/Settings/CustomFields.jsx (lines 53-120, 303-312)
// This pattern is already proven in production

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
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

function SortableColumnItem({ column, isVisible, onToggle }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: column.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  return (
    <div ref={setNodeRef} style={style} className="flex items-center gap-3 p-3 bg-white rounded border">
      <button
        {...attributes}
        {...listeners}
        className="cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600"
      >
        <GripVertical className="w-4 h-4" />
      </button>
      <input
        type="checkbox"
        checked={isVisible}
        onChange={() => onToggle(column.id)}
        disabled={column.id === 'name'} // Name always visible
      />
      <span className="flex-1">{column.label}</span>
    </div>
  );
}

// In modal component
const sensors = useSensors(
  useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
  useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 8 } })
);

function handleDragEnd(event) {
  const { active, over } = event;
  if (over && active.id !== over.id) {
    const oldIndex = columns.findIndex(c => c.id === active.id);
    const newIndex = columns.findIndex(c => c.id === over.id);
    const newOrder = arrayMove(columns, oldIndex, newIndex);

    // Update preferences immediately (optimistic)
    updatePreferences({
      visible_columns: newOrder.filter(c => visibleSet.has(c.id)).map(c => c.id)
    });
  }
}
```

**Apply to Phase 115:**
- Settings modal renders all available columns (core + custom fields) from backend response
- Name column is filtered out of sortable list (always first, never moves)
- Hidden columns remain in order array but aren't in visible_columns payload
- Drop indicator shows blue line between items during drag

### Pattern 3: Column Width Resize with Pointer Events

**What:** Native pointer event handling on column dividers to capture drag and update widths.

**When to use:** Table header cells with resize handles on borders.

**Example pattern:**
```javascript
// Source: Adapted from https://www.letsbuildui.dev/articles/resizable-tables-with-react-and-css-grid
// and native PointerEvent API patterns

function useColumnResize(columnId, initialWidth = 150, minWidth = 50) {
  const [width, setWidth] = useState(initialWidth);
  const [isResizing, setIsResizing] = useState(false);
  const startXRef = useRef(null);
  const startWidthRef = useRef(null);

  const handlePointerDown = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsResizing(true);
    startXRef.current = e.clientX;
    startWidthRef.current = width;

    // Capture pointer for smooth dragging
    e.target.setPointerCapture(e.pointerId);
  }, [width]);

  const handlePointerMove = useCallback((e) => {
    if (!isResizing) return;

    const delta = e.clientX - startXRef.current;
    const newWidth = Math.max(minWidth, startWidthRef.current + delta);
    setWidth(newWidth);
  }, [isResizing, minWidth]);

  const handlePointerUp = useCallback((e) => {
    if (!isResizing) return;

    setIsResizing(false);
    e.target.releasePointerCapture(e.pointerId);

    // Persist width to preferences (debounced in parent component)
    // Parent will batch multiple resize operations before saving
  }, [isResizing]);

  return {
    width,
    isResizing,
    resizeHandlers: {
      onPointerDown: handlePointerDown,
      onPointerMove: handlePointerMove,
      onPointerUp: handlePointerUp,
    },
  };
}

// Usage in table header
function ResizableHeader({ column, onWidthChange }) {
  const { width, isResizing, resizeHandlers } = useColumnResize(
    column.id,
    column.width || 150,
    50 // minWidth per CONTEXT.md
  );

  useEffect(() => {
    // Debounce width changes to avoid excessive API calls
    const timer = setTimeout(() => {
      onWidthChange(column.id, width);
    }, 300);
    return () => clearTimeout(timer);
  }, [width]);

  return (
    <th style={{ width: `${width}px`, position: 'relative' }}>
      {column.label}
      <div
        {...resizeHandlers}
        className="absolute right-0 top-0 bottom-0 w-1 cursor-col-resize hover:bg-blue-500"
        style={{ touchAction: 'none' }}
      />
    </th>
  );
}
```

**Apply to Phase 115:**
- Each visible column header gets a resize handle on its right border
- Width changes apply immediately to DOM (instant feedback)
- Debounced batch update saves all widths to preferences after 300ms idle
- Minimum width constraint prevents columns from disappearing
- Name column width is also resizable (no special exclusion)

### Pattern 4: Settings Modal Component

**What:** Centered modal with backdrop following existing modal patterns in codebase.

**When to use:** Column customization UI accessed via gear icon in table header.

**Example from existing codebase:**
```javascript
// Source: src/pages/People/PeopleList.jsx BulkOrganizationModal (lines 188-299)
// Existing modal pattern to follow

function ColumnSettingsModal({ isOpen, onClose }) {
  const { preferences, updatePreferences } = useListPreferences();
  const [localColumns, setLocalColumns] = useState([]);

  // Load preferences when modal opens
  useEffect(() => {
    if (isOpen && preferences?.available_columns) {
      setLocalColumns(preferences.available_columns);
    }
  }, [isOpen, preferences]);

  if (!isOpen) return null;

  const handleToggleColumn = (columnId) => {
    const visibleIds = new Set(preferences.visible_columns || []);
    if (visibleIds.has(columnId)) {
      visibleIds.delete(columnId);
    } else {
      visibleIds.add(columnId);
    }

    // Update immediately (optimistic)
    updatePreferences({
      visible_columns: Array.from(visibleIds),
    });
  };

  const handleReset = () => {
    updatePreferences({ reset: true });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50">Kolommen aanpassen</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-4">
          <DndContext sensors={sensors} onDragEnd={handleDragEnd}>
            <SortableContext items={localColumns.map(c => c.id)} strategy={verticalListSortingStrategy}>
              <div className="space-y-2 max-h-96 overflow-y-auto">
                {localColumns
                  .filter(c => c.id !== 'name') // Name column not in sortable list
                  .map(column => (
                    <SortableColumnItem
                      key={column.id}
                      column={column}
                      isVisible={preferences.visible_columns.includes(column.id)}
                      onToggle={handleToggleColumn}
                    />
                  ))}
              </div>
            </SortableContext>
          </DndContext>
        </div>

        <div className="p-4 border-t dark:border-gray-700 flex justify-between items-center">
          <button onClick={handleReset} className="text-sm text-gray-500 hover:text-gray-700">
            Standaardinstellingen herstellen
          </button>
          <button onClick={onClose} className="btn-primary">
            Sluiten
          </button>
        </div>
      </div>
    </div>
  );
}
```

**Apply to Phase 115:**
- Modal matches existing BulkOrganizationModal and BulkLabelsModal patterns
- Gear icon in table header (next to existing filter button)
- Click outside or ESC key closes modal (add event listeners)
- Changes apply instantly via optimistic updates (no "Save" button needed per CONTEXT.md)
- Reset link at bottom clears all preferences (PATCH { reset: true })

### Pattern 5: Remove show_in_list_view from Custom Field Settings

**What:** Remove checkbox and order input from FieldFormPanel, remove field from backend validation.

**When to use:** COL-07 requirement — replaced by per-user column selection.

**Files to modify:**
```javascript
// src/components/FieldFormPanel.jsx (lines 80-82 in current code)
// REMOVE these from default form data:
// show_in_list_view: false,
// list_view_order: 999,

// REMOVE the corresponding form fields from the render section
// (search for "show_in_list_view" and "list_view_order" inputs)

// includes/class-rest-custom-fields.php
// REMOVE show_in_list_view and list_view_order from:
// - Field creation validation schema
// - Field update validation schema
// - Field metadata response (if separate from ACF storage)
```

**Migration strategy:**
- Existing custom fields retain show_in_list_view in ACF (backward compat)
- New fields created after Phase 115 don't have the property
- Frontend ignores show_in_list_view, reads only from user preferences
- No data migration needed (old values are harmless)

### Anti-Patterns to Avoid

- **Reordering columns on table headers directly:** Per CONTEXT.md, reordering happens in modal only (simpler UX, no accidental reorders)
- **Saving width on every pixel change:** Debounce width changes to batch updates every 300ms
- **Separate width endpoint:** Column widths are part of preferences payload, not separate API
- **Loading states during optimistic updates:** Use optimistic updates to show instant feedback, hide loading spinners
- **Modal with Save button:** Per CONTEXT.md, changes apply instantly as user interacts
- **Validating column widths server-side:** Frontend enforces minWidth, server stores whatever is sent (trust client)

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Drag-and-drop infrastructure | Custom mouse event handling | dnd-kit (already installed) | Handles accessibility, touch events, keyboard navigation, edge cases |
| Column resize library | react-table-column-resizer | Native PointerEvent API | Simple use case, library is overkill, native API is well-supported |
| Debouncing | Custom setTimeout logic | Simple useEffect with cleanup | Standard React pattern, avoids bugs |
| Modal backdrop | Custom overlay component | Existing modal pattern from PeopleList | Already proven, maintains consistency |
| Preference caching | Custom cache | TanStack Query | Already in use, handles invalidation and refetching |

**Key insight:** dnd-kit provides professional drag-drop UX with minimal code. Column resizing is simple enough for native APIs. TanStack Query optimistic updates provide instant feedback without complex state management.

## Common Pitfalls

### Pitfall 1: Column Width Flicker on Reload

**What goes wrong:** User resizes columns, refreshes page, columns flash at default width before loading saved widths.

**Why it happens:** Preferences load asynchronously, table renders with default widths before preferences arrive.

**How to avoid:**
- Store widths in localStorage for instant restoration on mount
- Sync localStorage with server preferences (localStorage as cache)
- Initialize table with localStorage widths immediately, update when server response arrives

**Warning signs:**
- Horizontal scrollbar position jumps on load
- Columns visibly resize after 100-200ms delay

**Implementation:**
```javascript
// Load widths immediately from localStorage
const [columnWidths, setColumnWidths] = useState(() => {
  const saved = localStorage.getItem('stadion_column_widths');
  return saved ? JSON.parse(saved) : {};
});

// Update localStorage whenever widths change
useEffect(() => {
  localStorage.setItem('stadion_column_widths', JSON.stringify(columnWidths));
}, [columnWidths]);

// Sync with server preferences (background)
const { data: preferences } = useQuery({
  queryKey: ['user', 'list-preferences'],
  queryFn: fetchPreferences,
  onSuccess: (data) => {
    if (data.column_widths) {
      setColumnWidths(data.column_widths);
      localStorage.setItem('stadion_column_widths', JSON.stringify(data.column_widths));
    }
  },
});
```

### Pitfall 2: Hidden Column Order Lost

**What goes wrong:** User hides column A, reorders remaining columns, shows column A again — it appears at the end instead of original position.

**Why it happens:** Only visible columns are stored in visible_columns array, hidden columns lose their position.

**How to avoid:**
- Backend available_columns response includes ALL columns in consistent order
- visible_columns is a filter of available_columns, not a reorder
- When reordering in modal, update available_columns order (all columns), then filter to visible_columns for save
- Backend stores column_order (all columns) separate from visible_columns (filter)

**Warning signs:**
- Hidden column appears at end when re-shown
- Column order changes unexpectedly

**Implementation:**
```javascript
// Backend returns both
GET /stadion/v1/user/list-preferences
{
  "visible_columns": ["team", "labels", "modified"],
  "column_order": ["team", "labels", "telephone", "modified"], // ALL columns
  "available_columns": [...]
}

// Frontend applies visible_columns as filter over column_order
const orderedVisibleColumns = columnOrder
  .filter(id => visibleColumns.includes(id))
  .map(id => availableColumns.find(c => c.id === id));
```

**Note:** This requires extending Phase 114 backend to store column_order separately from visible_columns. Alternative: Frontend maintains full order locally and only sends visible subset to backend (simpler but less robust across devices).

### Pitfall 3: Name Column Not Always First

**What goes wrong:** Name column can be reordered in modal, ends up in middle of table.

**Why it happens:** Name column not excluded from drag-drop list or order array.

**How to avoid:**
- Filter name column out of sortable list in modal (render separately at top, non-draggable)
- Frontend always prepends name to column order before rendering table
- Backend doesn't include name in column_order or visible_columns (implicitly always first)

**Warning signs:**
- Name column has drag handle in modal
- Table renders with name column not first

**Implementation:**
```javascript
// In modal: exclude name from sortable list
const sortableColumns = availableColumns.filter(c => c.id !== 'name');

// In table: always render name first
const tableColumns = [
  { id: 'name', label: 'Naam', width: nameColumnWidth },
  ...visibleColumns.map(id => availableColumns.find(c => c.id === id)),
];
```

### Pitfall 4: Horizontal Scroll Loses Name Column

**What goes wrong:** User scrolls table horizontally, name column scrolls out of view.

**Why it happens:** Name column not sticky-positioned.

**How to avoid:**
- Apply `position: sticky` and `left: 0` to name column th and td elements
- Add `z-index: 2` to ensure name column overlays other columns
- Apply background color to prevent content showing through

**Warning signs:**
- Name column scrolls off screen with other columns
- Name column appears transparent during scroll

**Implementation:**
```css
/* Name column sticky positioning */
thead th:nth-child(1),
tbody td:nth-child(1) {
  position: sticky;
  left: 0;
  z-index: 2;
  background-color: white;
}

/* Dark mode */
.dark thead th:nth-child(1),
.dark tbody td:nth-child(1) {
  background-color: rgb(31, 41, 55); /* gray-800 */
}
```

**Reference:** [How to Create a Sticky Header & Column in React](https://muhimasri.com/blogs/react-sticky-header-column/)

## Code Examples

### Complete Column Settings Modal

```javascript
import { useState, useEffect, useRef } from 'react';
import { X, GripVertical, Settings } from 'lucide-react';
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
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useListPreferences } from '@/hooks/useListPreferences';

function SortableColumnItem({ column, isVisible, onToggle, isLocked }) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: column.id });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    zIndex: isDragging ? 50 : undefined,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`flex items-center gap-3 p-3 rounded-lg border-2 transition-colors ${
        isDragging ? 'shadow-lg bg-white dark:bg-gray-900' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'
      }`}
    >
      {!isLocked && (
        <button
          {...attributes}
          {...listeners}
          className="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 cursor-grab active:cursor-grabbing touch-none"
        >
          <GripVertical className="w-4 h-4" />
        </button>
      )}
      <input
        type="checkbox"
        checked={isVisible}
        onChange={() => onToggle(column.id)}
        disabled={isLocked}
        className="h-4 w-4 text-accent-600 border-gray-300 rounded focus:ring-accent-500"
      />
      <span className="flex-1 text-sm font-medium text-gray-900 dark:text-gray-50">
        {column.label}
        {column.custom && <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">(aangepast veld)</span>}
      </span>
    </div>
  );
}

export default function ColumnSettingsModal({ isOpen, onClose }) {
  const { preferences, updatePreferences, isLoading } = useListPreferences();
  const [localOrder, setLocalOrder] = useState([]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 8 } })
  );

  // Initialize local order from preferences
  useEffect(() => {
    if (isOpen && preferences?.available_columns) {
      setLocalOrder(preferences.available_columns.filter(c => c.id !== 'name'));
    }
  }, [isOpen, preferences]);

  // Close on ESC key
  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (e) => {
      if (e.key === 'Escape') onClose();
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const visibleSet = new Set(preferences?.visible_columns || []);

  const handleToggleColumn = (columnId) => {
    const newVisible = new Set(visibleSet);
    if (newVisible.has(columnId)) {
      newVisible.delete(columnId);
    } else {
      newVisible.add(columnId);
    }

    // Update immediately (optimistic)
    updatePreferences({
      visible_columns: Array.from(newVisible),
    });
  };

  const handleDragEnd = (event) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      const oldIndex = localOrder.findIndex(c => c.id === active.id);
      const newIndex = localOrder.findIndex(c => c.id === over.id);
      const newOrder = arrayMove(localOrder, oldIndex, newIndex);

      setLocalOrder(newOrder);

      // Update preferences with new order (optimistic)
      updatePreferences({
        visible_columns: newOrder.filter(c => visibleSet.has(c.id)).map(c => c.id),
      });
    }
  };

  const handleReset = () => {
    updatePreferences({ reset: true });
  };

  // Click outside to close
  const modalRef = useRef(null);
  const handleBackdropClick = (e) => {
    if (modalRef.current && !modalRef.current.contains(e.target)) {
      onClose();
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      onClick={handleBackdropClick}
    >
      <div
        ref={modalRef}
        className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4"
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b dark:border-gray-700">
          <h2 className="text-lg font-semibold dark:text-gray-50 flex items-center gap-2">
            <Settings className="w-5 h-5" />
            Kolommen aanpassen
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body */}
        <div className="p-4">
          {isLoading ? (
            <div className="flex items-center justify-center h-64">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400" />
            </div>
          ) : (
            <>
              <p className="text-sm text-gray-600 dark:text-gray-300 mb-4">
                Sleep om te herordenen, vink aan om te tonen.
              </p>
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
              >
                <SortableContext items={localOrder.map(c => c.id)} strategy={verticalListSortingStrategy}>
                  <div className="space-y-2 max-h-96 overflow-y-auto">
                    {localOrder.map((column) => (
                      <SortableColumnItem
                        key={column.id}
                        column={column}
                        isVisible={visibleSet.has(column.id)}
                        onToggle={handleToggleColumn}
                        isLocked={false}
                      />
                    ))}
                  </div>
                </SortableContext>
              </DndContext>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="p-4 border-t dark:border-gray-700 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
          <button
            onClick={handleReset}
            className="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          >
            Standaardinstellingen herstellen
          </button>
          <button onClick={onClose} className="btn-primary">
            Sluiten
          </button>
        </div>
      </div>
    </div>
  );
}
```

### Column Resize Hook

```javascript
import { useState, useCallback, useRef } from 'react';

/**
 * Hook for handling column resize via pointer events
 *
 * @param {string} columnId - Unique column identifier
 * @param {number} initialWidth - Starting width in pixels
 * @param {number} minWidth - Minimum width constraint (default 50px)
 * @returns {object} - width, isResizing, and event handlers
 */
export function useColumnResize(columnId, initialWidth = 150, minWidth = 50) {
  const [width, setWidth] = useState(initialWidth);
  const [isResizing, setIsResizing] = useState(false);
  const startXRef = useRef(null);
  const startWidthRef = useRef(null);

  const handlePointerDown = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();

    setIsResizing(true);
    startXRef.current = e.clientX;
    startWidthRef.current = width;

    // Capture pointer to track movement outside element
    e.currentTarget.setPointerCapture(e.pointerId);
  }, [width]);

  const handlePointerMove = useCallback((e) => {
    if (!isResizing) return;

    const delta = e.clientX - startXRef.current;
    const newWidth = Math.max(minWidth, startWidthRef.current + delta);

    setWidth(newWidth);
  }, [isResizing, minWidth]);

  const handlePointerUp = useCallback((e) => {
    if (!isResizing) return;

    setIsResizing(false);
    e.currentTarget.releasePointerCapture(e.pointerId);
  }, [isResizing]);

  return {
    width,
    isResizing,
    resizeHandlers: {
      onPointerDown: handlePointerDown,
      onPointerMove: handlePointerMove,
      onPointerUp: handlePointerUp,
      onPointerCancel: handlePointerUp, // Handle cancel same as up
    },
  };
}
```

### Resizable Table Header Component

```javascript
import { useEffect } from 'react';
import { useColumnResize } from '@/hooks/useColumnResize';

function ResizableHeader({ column, onWidthChange, children }) {
  const { width, isResizing, resizeHandlers } = useColumnResize(
    column.id,
    column.width || 150,
    50 // minWidth per CONTEXT.md
  );

  // Debounce width change callback
  useEffect(() => {
    const timer = setTimeout(() => {
      onWidthChange(column.id, width);
    }, 300);

    return () => clearTimeout(timer);
  }, [width, column.id, onWidthChange]);

  return (
    <th
      className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider bg-gray-50 dark:bg-gray-800 relative"
      style={{ width: `${width}px`, minWidth: '50px' }}
    >
      {children}
      <div
        {...resizeHandlers}
        className={`absolute right-0 top-0 bottom-0 w-1 cursor-col-resize hover:bg-blue-500 ${
          isResizing ? 'bg-blue-500' : ''
        }`}
        style={{ touchAction: 'none' }}
        title="Drag to resize column"
      />
    </th>
  );
}

export default ResizableHeader;
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Global show_in_list_view on custom fields | Per-user visible_columns preference | Phase 115 (2026-01) | Users customize their own view, admins don't control columns for everyone |
| react-beautiful-dnd | dnd-kit | ~2023 | react-beautiful-dnd archived, dnd-kit modern standard |
| Table libraries for resize | Native PointerEvent API | 2024+ | Simpler code, less overhead for basic resize |
| Separate width storage | Width in preferences object | Phase 115 (2026-01) | Single source of truth, atomic updates |
| Save button in modals | Instant apply with optimistic updates | 2024+ | Faster UX, less clicks |

**Deprecated/outdated:**
- **react-beautiful-dnd:** Library is archived, use dnd-kit instead
- **Column config in custom field definition:** Per-field show_in_list_view replaced by per-user preferences
- **Mouse events for drag:** Use PointerEvent API for unified mouse/touch handling

## Open Questions

### Question 1: Column Width Storage Strategy

**What we know:** Widths can be stored in user_meta (server) or localStorage (client) or both.

**What's unclear:** Should column_widths be part of preferences payload or separate endpoint?

**Recommendation:**
- Store widths in preferences payload: `{ visible_columns: [...], column_widths: { team: 200, labels: 150 } }`
- Use localStorage as instant-access cache, sync with server on change (debounced)
- Pros: Single endpoint, atomic updates, simpler state management
- Cons: Larger payload, but not significant (few columns)

### Question 2: Column Order vs Visible Columns

**What we know:** Hidden columns need to preserve order when re-shown.

**What's unclear:** Should backend store column_order (all columns) separately from visible_columns (filter)?

**Recommendation:**
- Backend stores both: `column_order` (all column IDs in user's preferred order) and `visible_columns` (subset that are shown)
- Frontend filters column_order by visible_columns to render table
- When user reorders in modal, both arrays update
- Pros: Hidden columns maintain position, robust across devices
- Cons: More complex backend logic, requires extending Phase 114

**Alternative (simpler):** Frontend maintains full order locally, only sends visible subset to backend. Hidden columns appear at end when re-shown. Simpler but less ideal UX.

### Question 3: Double-Click Auto-Fit

**What we know:** CONTEXT.md lists "auto-fit on double-click" as Claude's discretion.

**What's unclear:** Is auto-fit worth implementing for initial release?

**Recommendation:**
- Skip for Phase 115 (not in requirements, adds complexity)
- Users can manually resize to preferred width
- Auto-fit could be added in future enhancement if users request it
- Pros: Simpler implementation, less edge cases
- Cons: Users may want auto-fit for convenience

## Sources

### Primary (HIGH confidence)

- **src/pages/Settings/CustomFields.jsx** - Lines 1-434 (dnd-kit implementation already in production)
- **src/pages/People/PeopleList.jsx** - Lines 1-1108 (existing modal patterns, table structure)
- **includes/class-rest-api.php** - Lines 1020-1119 (Phase 114 preferences endpoints)
- **.planning/phases/114-user-preferences-backend/114-RESEARCH.md** - Backend API patterns
- **.planning/phases/115-column-preferences-ui/115-CONTEXT.md** - User decisions and requirements
- **package.json** - dnd-kit v6.3.1, @dnd-kit/sortable v10.0.0, @dnd-kit/utilities v3.2.2 confirmed installed

### Secondary (MEDIUM confidence)

- [Top 5 Drag-and-Drop Libraries for React in 2026](https://puckeditor.com/blog/top-5-drag-and-drop-libraries-for-react) - dnd-kit recommended for advanced use cases
- [TanStack Query Optimistic Updates Guide](https://tanstack.com/query/latest/docs/framework/react/guides/optimistic-updates) - Official documentation on optimistic update patterns
- [React Table Column Resizing Guide](https://www.simple-table.com/blog/react-table-column-resizing-guide) - Column resizing implementation patterns
- [Material React Table Column Resizing](https://www.material-react-table.com/docs/guides/column-resizing) - Best practices for resize feature
- [How to Create a Sticky Header & Column in React](https://muhimasri.com/blogs/react-sticky-header-column/) - CSS sticky positioning patterns
- [Resizable Tables with React and CSS Grid](https://www.letsbuildui.dev/articles/resizable-tables-with-react-and-css-grid/) - Native resize implementation
- [React Modal Component - Material UI](https://mui.com/material-ui/react-modal/) - Modal best practices and backdrop patterns
- [Concurrent Optimistic Updates in React Query | TkDodo's blog](https://tkdodo.eu/blog/concurrent-optimistic-updates-in-react-query) - Advanced TanStack Query patterns

### Tertiary (LOW confidence)

- WebSearch results on drag-drop libraries (cross-referenced with installed packages)
- WebSearch results on modal UI patterns (validated against existing codebase)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - dnd-kit already installed and proven in CustomFields.jsx, TanStack Query in use throughout app
- Architecture: HIGH - Patterns directly adapted from existing codebase (PeopleList modals, CustomFields drag-drop)
- Pitfalls: MEDIUM - Based on common issues in similar features, not yet implemented in this codebase
- Code examples: HIGH - Adapted from working production code (CustomFields.jsx, PeopleList.jsx)

**Research date:** 2026-01-29
**Valid until:** 2026-02-28 (30 days — stable libraries, established patterns)
