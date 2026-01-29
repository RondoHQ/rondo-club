# Phase 117: Organization Creation Restrictions - Research

**Researched:** 2026-01-29
**Domain:** React UI component modification (button/control removal)
**Confidence:** HIGH

## Summary

This phase involves removing organization creation controls from TeamsList and CommissiesList components in an existing React/Tailwind codebase. The changes are purely presentational - removing "Nieuw team" and "Nieuwe commissie" buttons along with their associated modal components and handlers. No new libraries, patterns, or architectural changes are required.

The implementation follows the exact same pattern as Phase 116 (Person Edit Restrictions): remove JSX elements that render the creation buttons and clean up associated modal components, state variables, and handler functions. The REST API endpoints remain completely functional for automation.

Both list pages have identical structure for team and commissie creation, making this a parallel implementation applying the same removal pattern to both files.

**Primary recommendation:** Remove the JSX elements entirely rather than conditionally hiding them. This keeps the code clean and matches the pattern established in Phase 116.

## Standard Stack

### Core (Already in Use)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | ^18.2.0 | UI framework | Already in codebase |
| Tailwind CSS | ^3.4.0 | Styling | Already in codebase |
| lucide-react | ^0.309.0 | Icons (Plus) | Already in codebase |
| TanStack Query | Latest | Data fetching/mutations | Already in codebase |
| React Hook Form | Latest | Form handling | Already in codebase |

### Supporting
No additional libraries needed. All required tooling is already present.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Removing JSX | CSS `display:none` | Conditional styling adds complexity and dead code |
| Removing JSX | Conditional rendering with flag | Adds unnecessary indirection for permanent change |
| Removing JSX | Permission checking | Over-engineered for unconditional removal |

**Installation:**
No installation required - existing stack is sufficient.

## Architecture Patterns

### Current Component Structure
```
src/pages/Teams/TeamsList.jsx          # ~953 lines, includes creation flow
src/pages/Commissies/CommissiesList.jsx  # ~953 lines, parallel to TeamsList
src/components/TeamEditModal.jsx       # Modal for team creation/editing
src/components/CommissieEditModal.jsx  # Modal for commissie creation/editing
src/hooks/useTeams.js                  # useCreateTeam hook
src/hooks/useCommissies.js             # useCreateCommissie hook
```

### Pattern 1: Direct JSX Removal
**What:** Remove the entire JSX block for creation buttons rather than conditionally rendering
**When to use:** When a feature is being permanently removed with no runtime conditions
**Example:**
```jsx
// BEFORE: "Nieuw team" button in TeamsList.jsx (line 790)
<button onClick={() => setShowTeamModal(true)} className="btn-primary">
  <Plus className="w-4 h-4 md:mr-2" />
  <span className="hidden md:inline">Nieuw team</span>
</button>

// AFTER: Simply remove the entire <button> element
// (maintain surrounding flex container structure)
```

### Pattern 2: Modal Component Cleanup
**What:** Remove modal components and their associated state/handlers when no longer used
**When to use:** When the modal is exclusively used for the removed functionality
**Example:**
```jsx
// BEFORE: Modal state and rendering in TeamsList.jsx
const [showTeamModal, setShowTeamModal] = useState(false);
const [isCreatingTeam, setIsCreatingTeam] = useState(false);

// Modal JSX (around line 917-923)
<TeamEditModal
  isOpen={showTeamModal}
  onClose={() => setShowTeamModal(false)}
  onSubmit={handleCreateTeam}
  isLoading={isCreatingTeam}
/>

// AFTER: Remove state, handler, modal import, and JSX
```

### Pattern 3: Empty State Updates
**What:** Update empty state messages to remove creation call-to-action
**When to use:** When removing add/create functionality that's referenced in empty states
**Example:**
```jsx
// BEFORE: Empty state with creation CTA (around line 822)
<button onClick={() => setShowTeamModal(true)} className="btn-primary">
  <Plus className="w-4 h-4 md:mr-2" />
  <span className="hidden md:inline">Nieuw team</span>
</button>

// AFTER: Remove button, update message if needed
<p className="text-gray-500 dark:text-gray-400">
  Voeg je eerste team toe via de API of import.
</p>
```

### Anti-Patterns to Avoid
- **Hiding with CSS:** Using `hidden` or `display:none` leaves dead code and handlers
- **Boolean flags for permanent removal:** Creating `ENABLE_CREATE = false` adds indirection
- **Commenting out code:** Creates maintenance burden, use version control instead
- **Partial removal:** Leaving dead handlers when button is removed
- **Breaking modal reuse:** TeamEditModal is also used for editing - only remove creation flow

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Conditional feature flags | Custom feature flag system | Just remove the code | This is a permanent change, not a toggle |
| Permission-based hiding | Role checking wrapper | Remove unconditionally | All users get same restriction per requirements |

**Key insight:** This phase is about removal, not addition. Resist the temptation to add complexity (feature flags, role checks) when simple deletion achieves the goal.

## Common Pitfalls

### Pitfall 1: Breaking Edit Functionality
**What goes wrong:** Removing TeamEditModal entirely when it's also used for editing existing organizations
**Why it happens:** Modal component is named "EditModal" but handles both create and edit
**How to avoid:** Check if modal is used elsewhere (e.g., detail pages for editing). In this case, modals are ONLY used for creation on list pages, so they can be safely removed from list pages
**Warning signs:** Editing existing teams/commissies stops working

### Pitfall 2: Dead Code Left Behind
**What goes wrong:** Removing the button but leaving handler functions, mutations, and state
**Why it happens:** Focus on visible button, forget the supporting code
**How to avoid:** For each removed button, also remove:
  - State variables: `showTeamModal`, `isCreatingTeam`
  - Handler functions: `handleCreateTeam`
  - Mutation hooks: `createTeamMutation` (from useCreateTeam)
  - Modal import: `TeamEditModal` import statement
  - Modal JSX: `<TeamEditModal>` component
**Warning signs:** ESLint warnings about unused variables, unused imports

### Pitfall 3: Inconsistent Empty States
**What goes wrong:** Removing header button but leaving "Voeg je eerste team toe" button in empty state
**Why it happens:** Empty state messages are separate JSX blocks, easy to miss
**How to avoid:** Search for all instances of the creation functionality:
  - Header "Nieuw team" button (line ~790)
  - Empty state "Nieuw team" button (line ~822)
  - No results state if it exists
**Warning signs:** Empty state still shows creation button

### Pitfall 4: Parallel Implementation Drift
**What goes wrong:** Teams and Commissies implementations diverge (one fixed, other missed)
**Why it happens:** Forgetting to apply identical changes to both list pages
**How to avoid:** Implement both TeamsList.jsx and CommissiesList.jsx changes together, verify both files
**Warning signs:** One list allows creation, the other doesn't

### Pitfall 5: REST API Endpoints Modified
**What goes wrong:** Developer mistakenly restricts POST endpoints thinking it's part of "UI restrictions"
**Why it happens:** Misunderstanding requirement to "disable creation" vs "disable UI for creation"
**How to avoid:** Remember constraint API-01: "all REST API functionality remains unchanged." Only touch React components, never touch backend PHP files
**Warning signs:** Sportlink sync stops working, automation scripts fail

## Code Examples

### Example 1: Remove "Nieuw team" Button from Header
Location: `TeamsList.jsx` around line 790-793
```jsx
// REMOVE this entire block:
<button onClick={() => setShowTeamModal(true)} className="btn-primary">
  <Plus className="w-4 h-4 md:mr-2" />
  <span className="hidden md:inline">Nieuw team</span>
</button>
```

### Example 2: Remove "Nieuwe commissie" Button from Header
Location: `CommissiesList.jsx` around line 790-793
```jsx
// REMOVE this entire block:
<button onClick={() => setShowCommissieModal(true)} className="btn-primary">
  <Plus className="w-4 h-4 md:mr-2" />
  <span className="hidden md:inline">Nieuwe commissie</span>
</button>
```

### Example 3: Remove Empty State Creation Button (Teams)
Location: `TeamsList.jsx` around lines 821-825
```jsx
// REMOVE this block:
{!search && (
  <button onClick={() => setShowTeamModal(true)} className="btn-primary">
    <Plus className="w-4 h-4 md:mr-2" />
    <span className="hidden md:inline">Nieuw team</span>
  </button>
)}

// REPLACE with informational text:
{!search && (
  <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">
    Nieuwe teams kunnen via de API of data import worden toegevoegd.
  </p>
)}
```

### Example 4: Remove Empty State Creation Button (Commissies)
Location: `CommissiesList.jsx` around lines 821-825
```jsx
// REMOVE this block:
{!search && (
  <button onClick={() => setShowCommissieModal(true)} className="btn-primary">
    <Plus className="w-4 h-4 md:mr-2" />
    <span className="hidden md:inline">Nieuwe commissie</span>
  </button>
)}

// REPLACE with informational text:
{!search && (
  <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">
    Nieuwe commissies kunnen via de API of data import worden toegevoegd.
  </p>
)}
```

### Example 5: Remove Dead Code - Teams
Location: Various places in `TeamsList.jsx`
```jsx
// REMOVE these state variables (around lines 386-387):
const [showTeamModal, setShowTeamModal] = useState(false);
const [isCreatingTeam, setIsCreatingTeam] = useState(false);

// REMOVE createTeamMutation hook (around lines 513-518):
const createTeamMutation = useCreateTeam({
  onSuccess: (result) => {
    setShowTeamModal(false);
    navigate(`/teams/${result.id}`);
  },
});

// REMOVE handleCreateTeam function (around lines 520-527):
const handleCreateTeam = async (data) => {
  setIsCreatingTeam(true);
  try {
    await createTeamMutation.mutateAsync(data);
  } finally {
    setIsCreatingTeam(false);
  }
};

// REMOVE import statement (around line 9):
import TeamEditModal from '@/components/TeamEditModal';

// REMOVE modal JSX (around lines 917-923):
<TeamEditModal
  isOpen={showTeamModal}
  onClose={() => setShowTeamModal(false)}
  onSubmit={handleCreateTeam}
  isLoading={isCreatingTeam}
/>

// REMOVE from useCreateTeam import (line 5):
import { useCreateTeam, useBulkUpdateTeams } from '@/hooks/useTeams';
// BECOMES:
import { useBulkUpdateTeams } from '@/hooks/useTeams';
```

### Example 6: Remove Dead Code - Commissies
Location: Various places in `CommissiesList.jsx`
```jsx
// Apply identical pattern as Example 5, but for commissies:
// - Remove showCommissieModal, isCreatingCommissie state
// - Remove createCommissieMutation hook
// - Remove handleCreateCommissie function
// - Remove CommissieEditModal import
// - Remove CommissieEditModal JSX
// - Remove useCreateCommissie from imports
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| N/A | N/A | N/A | N/A |

No paradigm shifts relevant to this phase - standard React component modification following Phase 116 pattern.

**Deprecated/outdated:**
- None relevant

## Open Questions

1. **Modal component reuse**
   - What we know: TeamEditModal and CommissieEditModal are used for creation on list pages
   - What's unclear: Are these modals used for editing on detail pages?
   - Recommendation: Based on code analysis, modals are NOT used on detail pages (detail pages have inline editing). Safe to remove from list pages.

2. **Empty state messaging**
   - What we know: Need to update empty state to remove creation CTA
   - What's unclear: Should we add guidance on how to create via API?
   - Recommendation: Add brief informational text: "Nieuwe [teams/commissies] kunnen via de API of data import worden toegevoegd."

3. **Dead code cleanup scope**
   - What we know: Remove unused handlers, state, imports, and modal components
   - What's unclear: Should we remove hook definitions (useCreateTeam, useCreateCommissie) from hook files?
   - Recommendation: NO - leave hook definitions in place. They may be used by tests, detail pages, or future features. Only remove hook usage from list pages.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/pages/Teams/TeamsList.jsx` - Direct code analysis
- `/Users/joostdevalk/Code/stadion/src/pages/Commissies/CommissiesList.jsx` - Direct code analysis
- `/Users/joostdevalk/Code/stadion/src/components/TeamEditModal.jsx` - Modal component analysis
- `/Users/joostdevalk/Code/stadion/src/components/CommissieEditModal.jsx` - Modal component analysis
- `/Users/joostdevalk/Code/stadion/src/hooks/useTeams.js` - Hook implementation
- `/Users/joostdevalk/Code/stadion/.planning/phases/116-person-edit-restrictions/116-RESEARCH.md` - Prior phase pattern

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/package.json` - Stack versions verified

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Direct codebase analysis, no external libraries needed
- Architecture: HIGH - Simple removal pattern matching Phase 116, no architectural decisions
- Pitfalls: HIGH - Based on Phase 116 experience and React refactoring best practices

**Research date:** 2026-01-29
**Valid until:** N/A (codebase-specific, stable patterns)
