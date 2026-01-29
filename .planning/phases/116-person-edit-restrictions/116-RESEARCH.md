# Phase 116: Person Edit Restrictions - Research

**Researched:** 2026-01-29
**Domain:** React UI component modification (button/control removal)
**Confidence:** HIGH

## Summary

This phase involves removing specific UI controls from the PersonDetail component in an existing React/Tailwind codebase. The changes are purely presentational - removing buttons and making work history display non-interactive. No new libraries, patterns, or architectural changes are required.

The implementation is straightforward: remove JSX elements that render the delete button, add address button, add function button, and edit/delete controls on work history items. The work history section should also have its interactive CSS classes (hover effects, pointer cursor) removed to appear as static content.

**Primary recommendation:** Remove the JSX elements entirely rather than conditionally hiding them. This keeps the code clean and matches the user's decision that controls should "simply not render."

## Standard Stack

### Core (Already in Use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | ^18.2.0 | UI framework | Already in codebase |
| Tailwind CSS | ^3.4.0 | Styling | Already in codebase |
| lucide-react | ^0.309.0 | Icons (Trash2, Pencil, Plus) | Already in codebase |

### Supporting
No additional libraries needed. All required tooling is already present.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Removing JSX | CSS `display:none` | Conditional styling adds complexity and dead code |
| Removing JSX | Conditional rendering with flag | Adds unnecessary indirection for permanent change |

**Installation:**
No installation required - existing stack is sufficient.

## Architecture Patterns

### Current Component Structure
```
src/pages/People/PersonDetail.jsx  # ~2900 lines, single component
```

### Pattern 1: Direct JSX Removal
**What:** Remove the entire JSX block for unwanted buttons rather than conditionally rendering
**When to use:** When a feature is being permanently removed with no runtime conditions
**Example:**
```jsx
// BEFORE: Delete button in header actions
<button onClick={handleDelete} className="btn-danger-outline">
  <Trash2 className="w-4 h-4 md:mr-2" />
  <span className="hidden md:inline">Verwijderen</span>
</button>

// AFTER: Simply remove the entire <button> element
// (leave surrounding buttons intact)
```

### Pattern 2: Static Content Display
**What:** Remove interactive CSS classes and click handlers to make content appear as static text
**When to use:** When converting interactive lists to read-only display
**Example:**
```jsx
// BEFORE: Interactive work history item with hover and buttons
<div key={originalIndex} className="flex items-start group">
  {/* ... content ... */}
  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
    <button onClick={() => {...}} className="p-1 hover:bg-gray-100">
      <Pencil className="w-4 h-4" />
    </button>
  </div>
</div>

// AFTER: Static display without hover group and buttons
<div key={index} className="flex items-start">
  {/* ... content only ... */}
</div>
```

### Anti-Patterns to Avoid
- **Hiding with CSS:** Using `hidden` or `display:none` leaves dead code and handlers
- **Boolean flags for permanent removal:** Creating `ENABLE_DELETE = false` adds indirection
- **Commenting out code:** Creates maintenance burden, use version control instead
- **Partial removal:** Leaving dead handlers (handleDelete) when button is removed

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Conditional feature flags | Custom feature flag system | Just remove the code | This is a permanent change, not a toggle |
| Permission-based hiding | Role checking wrapper | Remove unconditionally | All users get same restriction per requirements |

**Key insight:** This phase is about removal, not addition. Resist the temptation to add complexity (feature flags, role checks) when simple deletion achieves the goal.

## Common Pitfalls

### Pitfall 1: Dead Code Left Behind
**What goes wrong:** Removing the button but leaving the handler function and related state
**Why it happens:** Grep finds the button, developer removes it, doesn't trace dependencies
**How to avoid:** For each removed button, also remove: handler function, related state variables, modal components if orphaned
**Warning signs:** ESLint warnings about unused variables, handlers that are never called

### Pitfall 2: Inconsistent Empty States
**What goes wrong:** Removing add button but leaving "Nog geen X. Toevoegen" link in empty state
**Why it happens:** Empty state messages are separate JSX blocks, easy to miss
**How to avoid:** Search for all instances of the "add" functionality, including empty state CTAs
**Warning signs:** Clicking "Toevoegen" link still opens the modal

### Pitfall 3: Work History Still Appears Interactive
**What goes wrong:** Removing buttons but leaving `group` class and hover transitions
**Why it happens:** Interactive styling is on parent elements, not just buttons
**How to avoid:** Remove `group` class from container, remove `group-hover:opacity-100` transitions
**Warning signs:** Visual hover effect still appears even though nothing is clickable

### Pitfall 4: Incomplete Address Section Treatment
**What goes wrong:** Removing add button but leaving edit/delete buttons on existing addresses
**Why it happens:** Requirements mention "add address" but context discusses full restriction
**How to avoid:** Verify exact scope - per CONTEXT.md, only add button is removed; edit/delete remain
**Warning signs:** N/A - clarify scope if uncertain

### Pitfall 5: Modal Components Left Mounted
**What goes wrong:** WorkHistoryEditModal still imported and rendered even when never shown
**Why it happens:** Focus on buttons, forget the modal component itself
**How to avoid:** If modal is never opened, remove import and JSX for the modal
**Warning signs:** Bundle includes unused modal code

## Code Examples

### Example 1: Remove Delete Button from Header
Location: `PersonDetail.jsx` around line 1566
```jsx
// REMOVE this entire block:
<button onClick={handleDelete} className="btn-danger-outline">
  <Trash2 className="w-4 h-4 md:mr-2" />
  <span className="hidden md:inline">Verwijderen</span>
</button>
```

### Example 2: Remove Add Address Button
Location: `PersonDetail.jsx` around lines 2051-2061
```jsx
// REMOVE this entire block:
<button
  onClick={() => {
    setEditingAddress(null);
    setEditingAddressIndex(null);
    setShowAddressModal(true);
  }}
  className="btn-secondary text-sm"
  title="Adres toevoegen"
>
  <Plus className="w-4 h-4" />
</button>
```

### Example 3: Remove Add Function Button
Location: `PersonDetail.jsx` around lines 2293-2303
```jsx
// REMOVE this entire block:
<button
  onClick={() => {
    setEditingWorkHistory(null);
    setEditingWorkHistoryIndex(null);
    setShowWorkHistoryModal(true);
  }}
  className="btn-secondary text-sm"
  title="Functie toevoegen"
>
  <Plus className="w-4 h-4" />
</button>
```

### Example 4: Remove Work History Edit/Delete Controls
Location: `PersonDetail.jsx` around lines 2343-2363
```jsx
// REMOVE the entire controls div:
<div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
  <button onClick={() => {...}} className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded" title="Functie bewerken">
    <Pencil className="w-4 h-4 text-gray-400 hover:text-gray-600" />
  </button>
  <button onClick={() => handleDeleteWorkHistory(originalIndex)} className="p-1 hover:bg-red-50 rounded" title="Functie verwijderen">
    <Trash2 className="w-4 h-4 text-gray-400 hover:text-red-600" />
  </button>
</div>

// ALSO change the parent div from:
<div key={originalIndex} className="flex items-start group">
// TO:
<div key={index} className="flex items-start">
```

### Example 5: Remove Empty State Add Links
Location: Various places in PersonDetail.jsx
```jsx
// For addresses (around line 2118), CHANGE from:
<p className="text-sm text-gray-500 text-center py-4">
  Nog geen adressen. <button onClick={() => {...}} className="text-accent-600 hover:underline">Toevoegen</button>
</p>

// TO:
<p className="text-sm text-gray-500 text-center py-4">
  Nog geen adressen.
</p>

// Same pattern for work history (around line 2370)
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| N/A | N/A | N/A | N/A |

No paradigm shifts relevant to this phase - standard React component modification.

**Deprecated/outdated:**
- None relevant

## Open Questions

1. **Address edit/delete buttons**
   - What we know: CONTEXT.md says only "add address" button is removed
   - What's unclear: Should existing addresses still be editable/deletable?
   - Recommendation: Follow CONTEXT.md literally - only remove add button, leave edit/delete

2. **Dead code cleanup scope**
   - What we know: Remove unused handlers and state
   - What's unclear: How aggressive to be (e.g., remove entire modal component if orphaned?)
   - Recommendation: Remove obviously dead code; keep modal if any path still uses it

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/pages/People/PersonDetail.jsx` - Direct code analysis
- `/Users/joostdevalk/Code/stadion/.planning/phases/116-person-edit-restrictions/116-CONTEXT.md` - User decisions

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/package.json` - Stack versions verified

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Direct codebase analysis, no external libraries needed
- Architecture: HIGH - Simple removal pattern, no architectural decisions
- Pitfalls: HIGH - Based on common React refactoring issues

**Research date:** 2026-01-29
**Valid until:** N/A (codebase-specific, stable patterns)
