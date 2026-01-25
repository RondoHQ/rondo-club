# Phase 72: Activity & Bug Fixes - Research

**Researched:** 2026-01-17
**Domain:** React UI Components, Activity Type Configuration
**Confidence:** HIGH

## Summary

This phase involves two distinct workstreams:
1. **Activity Types (ACT-01, ACT-02, ACT-03)**: Adding "Dinner" and "Zoom" activity types and renaming "Phone call" to "Phone"
2. **Bug Fixes (BUG-01, BUG-02)**: Fixing z-index layering on PeopleList and spacing in person header

All changes are straightforward UI modifications with no architectural impact. The activity types are defined in two locations that must stay synchronized. The bug fixes are isolated CSS/Tailwind changes.

**Primary recommendation:** Implement all changes as simple, surgical edits to existing files with no new components or patterns needed.

## Standard Stack

No new libraries required. This phase uses existing stack:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React 18 | 18.x | UI framework | Already in use |
| Tailwind CSS | 3.4 | Styling | Already in use |
| lucide-react | latest | Icons | Already in use for activity icons |

### No Additional Dependencies
This phase requires no new npm packages. All changes are configuration/styling within existing components.

## Architecture Patterns

### Activity Type Definition Pattern

Activity types are defined in **two synchronized locations**:

1. **`src/components/Timeline/QuickActivityModal.jsx`** (lines 8-16)
   - Defines the ACTIVITY_TYPES array for the modal UI
   - Contains: id, label, and icon for each type

2. **`src/utils/timeline.js`** (lines 121-152)
   - `getActivityTypeIcon()` - Maps type ID to icon name
   - `getActivityTypeLabel()` - Maps type ID to display label

**Pattern for adding new activity types:**
```javascript
// QuickActivityModal.jsx - Add to ACTIVITY_TYPES array
const ACTIVITY_TYPES = [
  { id: 'call', label: 'Phone', icon: Phone },         // Renamed from 'Phone call'
  { id: 'email', label: 'Email', icon: Mail },
  { id: 'chat', label: 'Chat', icon: MessageCircle },
  { id: 'meeting', label: 'Meeting', icon: Users },
  { id: 'coffee', label: 'Coffee', icon: Coffee },
  { id: 'lunch', label: 'Lunch', icon: Utensils },
  { id: 'dinner', label: 'Dinner', icon: Utensils },   // NEW - uses same icon as lunch
  { id: 'zoom', label: 'Zoom', icon: Video },          // NEW - uses Video icon
  { id: 'note', label: 'Other', icon: FileText },
];

// timeline.js - Add to both maps
export function getActivityTypeIcon(type) {
  const iconMap = {
    call: 'Phone',
    email: 'Mail',
    chat: 'MessageCircle',
    meeting: 'Users',
    coffee: 'Coffee',
    lunch: 'Utensils',
    dinner: 'Utensils',   // NEW
    zoom: 'Video',         // NEW
    note: 'FileText',
  };
  return iconMap[type] || 'Circle';
}

export function getActivityTypeLabel(type) {
  const labelMap = {
    call: 'Phone',        // CHANGED from 'Phone call'
    email: 'Email',
    chat: 'Chat',
    meeting: 'Meeting',
    coffee: 'Coffee',
    lunch: 'Lunch',
    dinner: 'Dinner',     // NEW
    zoom: 'Zoom',         // NEW
    note: 'Note',
  };
  return labelMap[type] || type || 'Activity';
}
```

### Z-Index Layering Pattern

Current z-index usage in the application:
| Class | Value | Usage |
|-------|-------|-------|
| z-10 | 10 | Sticky headers (table headers, topbar) |
| z-20 | 20 | Selection toolbars |
| z-40 | 40 | FAB buttons |
| z-50 | 50 | Modals and dropdowns |

**BUG-01 Issue:** The topbar uses `z-10` but the selection toolbar and table sticky header also use `z-10`/`z-20`, causing layering conflicts.

**Fix pattern:** Increase topbar z-index to `z-30` to stay above content but below modals.

### Spacing Pattern

**BUG-02 Issue:** Missing space between "at" and company name in person header.

Current code (line 1469):
```jsx
<span className="text-gray-400 dark:text-gray-500"> at</span>
```

The space before "at" exists, but no space after. Fix by adding a space:
```jsx
<span className="text-gray-400 dark:text-gray-500"> at </span>
```

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Video call icon | Custom SVG | lucide-react Video icon | Already imported in PersonDetail.jsx |
| Activity type registry | Central config file | Keep in-component definitions | Minimal complexity, only 2 locations |

**Key insight:** The activity types array is small enough that duplication across 2 files is acceptable. A central registry would add complexity without proportional benefit.

## Common Pitfalls

### Pitfall 1: Forgetting to Update Both Locations
**What goes wrong:** Adding activity type to QuickActivityModal but not timeline.js
**Why it happens:** Two separate files define activity types
**How to avoid:** Always update both files together:
- `src/components/Timeline/QuickActivityModal.jsx` - ACTIVITY_TYPES array
- `src/utils/timeline.js` - getActivityTypeIcon() and getActivityTypeLabel()
**Warning signs:** New activity type shows in modal but displays as raw ID in timeline

### Pitfall 2: Missing Icon Import
**What goes wrong:** Using Video icon without importing it
**Why it happens:** Assuming icon is already imported
**How to avoid:** Check imports at top of QuickActivityModal.jsx and add Video if missing
**Warning signs:** React error about undefined component

### Pitfall 3: Z-Index Not Applying
**What goes wrong:** Z-index change doesn't fix layering
**Why it happens:** z-index only works on positioned elements (relative, absolute, fixed, sticky)
**How to avoid:** Verify element has position property (topbar already has `sticky`)
**Warning signs:** Element appears behind others despite high z-index

### Pitfall 4: Breaking Existing Activities
**What goes wrong:** Renamed "call" type breaks display of existing activities
**Why it happens:** Changing the type ID instead of just the label
**How to avoid:** Keep type ID as "call", only change the display label
**Warning signs:** Existing phone call activities show wrong icon/label

## Code Examples

### Adding Video Icon Import
```jsx
// QuickActivityModal.jsx - add Video to imports
import { X, Phone, Mail, Users, Coffee, Utensils, FileText, Circle, MessageCircle, Video } from 'lucide-react';
```

### Updated ACTIVITY_TYPES Array
```jsx
// QuickActivityModal.jsx
const ACTIVITY_TYPES = [
  { id: 'call', label: 'Phone', icon: Phone },
  { id: 'email', label: 'Email', icon: Mail },
  { id: 'chat', label: 'Chat', icon: MessageCircle },
  { id: 'meeting', label: 'Meeting', icon: Users },
  { id: 'coffee', label: 'Coffee', icon: Coffee },
  { id: 'lunch', label: 'Lunch', icon: Utensils },
  { id: 'dinner', label: 'Dinner', icon: Utensils },
  { id: 'zoom', label: 'Zoom', icon: Video },
  { id: 'note', label: 'Other', icon: FileText },
];
```

### Topbar Z-Index Fix
```jsx
// Layout.jsx line 531
// BEFORE:
<header className="sticky top-0 z-10 flex items-center h-16 px-4 bg-white border-b border-gray-200 lg:px-6 dark:bg-gray-800 dark:border-gray-700">

// AFTER:
<header className="sticky top-0 z-30 flex items-center h-16 px-4 bg-white border-b border-gray-200 lg:px-6 dark:bg-gray-800 dark:border-gray-700">
```

### Person Header Spacing Fix
```jsx
// PersonDetail.jsx line 1469
// BEFORE:
<span className="text-gray-400 dark:text-gray-500"> at</span>

// AFTER:
<span className="text-gray-400 dark:text-gray-500"> at </span>
```

### TimelineView Icon Map Update
```jsx
// TimelineView.jsx - add Video to ICON_MAP
const ICON_MAP = {
  Phone,
  Mail,
  Users,
  Coffee,
  Utensils,
  FileText,
  Circle,
  MessageCircle,
  Video,  // NEW
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| N/A | Activity types in 2 files | Original implementation | Must update both |

**No deprecated patterns:** All changes use current React/Tailwind patterns already in the codebase.

## Open Questions

1. **Icon choice for Dinner**
   - What we know: Lunch uses Utensils icon
   - What's unclear: Should Dinner use same icon or different?
   - Recommendation: Use Utensils (same as Lunch) - food activities are visually related

2. **Activity type ordering**
   - What we know: Current order appears intentional (call/email first, other last)
   - What's unclear: Where to insert new types
   - Recommendation: Add dinner after lunch (food together), zoom after meeting (meetings together)

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/components/Timeline/QuickActivityModal.jsx` - Activity type definitions
- `/Users/joostdevalk/Code/stadion/src/utils/timeline.js` - Activity type icon/label maps
- `/Users/joostdevalk/Code/stadion/src/components/layout/Layout.jsx` - Topbar component (line 531)
- `/Users/joostdevalk/Code/stadion/src/pages/People/PersonDetail.jsx` - Person header (line 1469)
- `/Users/joostdevalk/Code/stadion/src/pages/People/PeopleList.jsx` - Selection toolbar z-index reference
- `/Users/joostdevalk/Code/stadion/src/components/Timeline/TimelineView.jsx` - Icon map for timeline display

### Secondary (MEDIUM confidence)
- lucide-react documentation - Video icon availability confirmed via existing usage in PersonDetail.jsx

## Metadata

**Confidence breakdown:**
- Activity type changes: HIGH - Direct code inspection, clear pattern
- Bug fixes: HIGH - Direct code inspection, straightforward CSS changes
- Icon availability: HIGH - Video icon already used in codebase

**Research date:** 2026-01-17
**Valid until:** No expiry - changes are to internal codebase patterns
