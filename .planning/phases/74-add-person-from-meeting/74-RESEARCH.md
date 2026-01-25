# Phase 74: Add Person from Meeting - Research

**Researched:** 2026-01-17
**Domain:** React frontend, person creation, meeting attendees
**Confidence:** HIGH

## Summary

Phase 74 adds the ability to create a new person directly from an unknown meeting attendee. The codebase already has all the building blocks in place:

1. `MeetingDetailModal.jsx` displays attendees with `AttendeeRow` component that distinguishes matched vs unmatched attendees
2. `useCreatePerson` hook handles person creation with email-based Gravatar sideloading
3. `PersonEditModal.jsx` provides a form interface for person creation

The implementation pattern is clear: add an "Add" button to unmatched attendee rows, extract first/last name from the attendee's name or email, open a PersonEditModal pre-filled with the extracted data, and invalidate meeting queries on success to update the attendee display.

**Primary recommendation:** Extend the existing `AttendeeRow` component to include an "Add" button for unmatched attendees, use the existing `PersonEditModal` and `useCreatePerson` hook, and add meeting-specific query invalidation on person creation.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | Component framework | Already in use |
| TanStack Query | 5.x | Server state management | Already in use for all data fetching |
| react-hook-form | 7.x | Form handling | Already used in PersonEditModal |
| Tailwind CSS | 3.4 | Styling | Already used throughout |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| lucide-react | - | Icons (UserPlus) | Add button icon |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PersonEditModal | Inline quick-add form | Modal is consistent with app patterns, reuses existing tested code |
| Full modal form | Minimal inline fields | Modal provides all fields user might need immediately |

**Installation:**
No new packages required - all dependencies already installed.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── components/
│   ├── MeetingDetailModal.jsx  # Extend AttendeeRow component
│   └── PersonEditModal.jsx     # Reuse for person creation (no changes)
└── hooks/
    ├── usePeople.js            # useCreatePerson hook (extend invalidation)
    └── useMeetings.js          # meetingsKeys for query invalidation
```

### Pattern 1: Pre-filled Modal with Callback
**What:** Open modal with pre-filled data, handle creation, update parent state
**When to use:** When creating entities from contextual data (like attendee info)
**Example:**
```typescript
// Source: Existing pattern in Layout.jsx and PeopleList.jsx
const [showPersonModal, setShowPersonModal] = useState(false);
const [prefillData, setPrefillData] = useState(null);

const handleAddPerson = (attendee) => {
  setPrefillData({
    first_name: extractFirstName(attendee),
    last_name: extractLastName(attendee),
    email: attendee.email,
  });
  setShowPersonModal(true);
};

// Modal receives prefillData and uses it in reset()
```

### Pattern 2: Name Extraction from Email/Display Name
**What:** Parse attendee data to extract first/last name for pre-filling
**When to use:** When attendee has name in email format or display name
**Example:**
```typescript
// Parse "John Doe" -> { first: "John", last: "Doe" }
// Parse "john.doe@example.com" -> { first: "John", last: "Doe" }
function extractNameParts(attendee) {
  const name = attendee.name || attendee.email?.split('@')[0] || '';

  // Handle display name format: "First Last"
  if (attendee.name && !attendee.name.includes('@')) {
    const parts = attendee.name.trim().split(/\s+/);
    return {
      first_name: parts[0] || '',
      last_name: parts.slice(1).join(' ') || '',
    };
  }

  // Handle email-only: "john.doe@example.com"
  const localPart = attendee.email?.split('@')[0] || '';
  const nameParts = localPart.split(/[._-]/).map(
    part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase()
  );

  return {
    first_name: nameParts[0] || '',
    last_name: nameParts.slice(1).join(' ') || '',
  };
}
```

### Pattern 3: Query Invalidation After Creation
**What:** Invalidate meeting queries after person creation to update matched status
**When to use:** When person creation affects meeting attendee matching
**Example:**
```typescript
// Source: Existing pattern in usePeople.js useCreatePerson
export function useCreatePerson({ onSuccess } = {}) {
  const queryClient = useQueryClient();

  return useMutation({
    // ... mutation function
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['reminders'] });
      // NEW: Invalidate meetings to trigger re-matching
      queryClient.invalidateQueries({ queryKey: meetingsKeys.today });
      queryClient.invalidateQueries({ queryKey: ['person-meetings'] });
      onSuccess?.(result);
    },
  });
}
```

### Anti-Patterns to Avoid
- **Inline person creation form:** Don't build a mini-form inside AttendeeRow. Use the existing PersonEditModal for consistency and maintainability.
- **Client-side matching after creation:** Don't try to update attendee.matched locally. Let the server re-compute matches on next query.
- **Blocking UI during creation:** Use the existing isLoading pattern from PersonEditModal.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Person creation form | Custom inline form | PersonEditModal | Form validation, vCard import, visibility selector all built-in |
| Person API call | Direct axios call | useCreatePerson hook | Handles Gravatar sideload, birthday creation, cache invalidation |
| Query invalidation | Manual state update | TanStack Query invalidation | Automatic refetch, server-authoritative matching |
| Name parsing | Complex regex | Simple split logic | Email local part is already normalized, display names are straightforward |

**Key insight:** The entire person creation flow exists and is battle-tested. The only new code needed is the UI trigger (button) and pre-fill data extraction.

## Common Pitfalls

### Pitfall 1: Forgetting to Invalidate Meeting Queries
**What goes wrong:** Person is created but attendee list still shows them as unknown
**Why it happens:** useCreatePerson doesn't know about meetings context
**How to avoid:** Add meeting query invalidation to useCreatePerson onSuccess, OR use onSuccess callback to invalidate from the modal host
**Warning signs:** Attendee still gray after adding person (requires modal close/reopen)

### Pitfall 2: Name Extraction Edge Cases
**What goes wrong:** Names like "O'Brien" or "van der Berg" parsed incorrectly
**Why it happens:** Simple split on space doesn't handle all naming conventions
**How to avoid:** Keep logic simple - first word is first name, rest is last name. User can edit in modal.
**Warning signs:** "Jean-Pierre van Damme" becomes first: "Jean-Pierre", last: "van Damme" (acceptable)

### Pitfall 3: Stale Prefill Data
**What goes wrong:** Modal shows data from previous attendee when reopened quickly
**Why it happens:** Modal state not properly reset between opens
**How to avoid:** Reset prefill state when modal closes, use useEffect to update form when isOpen changes
**Warning signs:** Wrong name appears in form fields

### Pitfall 4: Double-Adding Same Person
**What goes wrong:** User clicks add on same attendee twice before modal opens
**Why it happens:** No loading/disabled state on the add button
**How to avoid:** Disable add button while modal is open or a creation is pending
**Warning signs:** Two identical persons created

## Code Examples

Verified patterns from the existing codebase:

### AttendeeRow with Add Button
```jsx
// Source: Extend existing AttendeeRow in MeetingDetailModal.jsx
function AttendeeRow({ attendee, onAddPerson }) {
  const displayName = attendee.person_name || attendee.name || attendee.email || 'Unknown';

  const content = (
    <div className="flex items-center gap-3 py-2">
      {/* Avatar */}
      {attendee.matched && attendee.thumbnail ? (
        <img src={attendee.thumbnail} alt={displayName} className="w-8 h-8 rounded-full object-cover" />
      ) : (
        <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
          <User className="w-4 h-4 text-gray-500 dark:text-gray-400" />
        </div>
      )}

      {/* Name and email */}
      <div className="flex-1 min-w-0">
        <p className={`text-sm truncate ${
          attendee.matched
            ? 'font-medium text-accent-600 dark:text-accent-400'
            : 'text-gray-600 dark:text-gray-400'
        }`}>
          {displayName}
        </p>
        {attendee.matched && attendee.email && attendee.email !== displayName && (
          <p className="text-xs text-gray-500 dark:text-gray-500 truncate">{attendee.email}</p>
        )}
      </div>

      {/* Add button for unmatched attendees */}
      {!attendee.matched && onAddPerson && (
        <button
          onClick={() => onAddPerson(attendee)}
          className="flex-shrink-0 p-1.5 text-gray-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
          title="Add as contact"
        >
          <UserPlus className="w-4 h-4" />
        </button>
      )}
    </div>
  );

  // Wrap in Link only if matched
  if (attendee.matched && attendee.person_id) {
    return (
      <Link
        to={`/people/${attendee.person_id}`}
        className="block rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors px-2 -mx-2"
      >
        {content}
      </Link>
    );
  }

  return <div className="px-2 -mx-2">{content}</div>;
}
```

### Name Extraction Utility
```jsx
// Source: New utility function
function extractNameFromAttendee(attendee) {
  // Prefer explicit name over email-derived name
  if (attendee.name && !attendee.name.includes('@')) {
    const parts = attendee.name.trim().split(/\s+/);
    return {
      first_name: parts[0] || '',
      last_name: parts.slice(1).join(' ') || '',
    };
  }

  // Fall back to email local part
  if (attendee.email) {
    const localPart = attendee.email.split('@')[0];
    // Handle john.doe, john_doe, john-doe patterns
    const nameParts = localPart.split(/[._-]/).map(
      part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase()
    );
    return {
      first_name: nameParts[0] || '',
      last_name: nameParts.slice(1).join(' ') || '',
    };
  }

  return { first_name: '', last_name: '' };
}
```

### Modal State Management in MeetingDetailModal
```jsx
// Source: Pattern from Layout.jsx
export default function MeetingDetailModal({ isOpen, onClose, meeting }) {
  const [showPersonModal, setShowPersonModal] = useState(false);
  const [personPrefill, setPersonPrefill] = useState(null);
  const [isCreatingPerson, setIsCreatingPerson] = useState(false);

  const queryClient = useQueryClient();

  const createPersonMutation = useCreatePerson({
    onSuccess: (result) => {
      setShowPersonModal(false);
      setPersonPrefill(null);
      // Invalidate meeting queries to update attendee matching
      queryClient.invalidateQueries({ queryKey: meetingsKeys.today });
      queryClient.invalidateQueries({ queryKey: ['person-meetings'] });
    },
  });

  const handleAddPerson = (attendee) => {
    const { first_name, last_name } = extractNameFromAttendee(attendee);
    setPersonPrefill({
      first_name,
      last_name,
      email: attendee.email || '',
    });
    setShowPersonModal(true);
  };

  const handleCreatePerson = async (data) => {
    setIsCreatingPerson(true);
    try {
      await createPersonMutation.mutateAsync(data);
    } finally {
      setIsCreatingPerson(false);
    }
  };

  // ... rest of component

  return (
    <>
      {/* Main modal content */}
      <div className="fixed inset-0 z-50 ...">
        {/* ... attendees list with onAddPerson prop */}
        {sortedAttendees.map((attendee, index) => (
          <AttendeeRow
            key={attendee.email || index}
            attendee={attendee}
            onAddPerson={handleAddPerson}
          />
        ))}
      </div>

      {/* Person creation modal */}
      <PersonEditModal
        isOpen={showPersonModal}
        onClose={() => {
          setShowPersonModal(false);
          setPersonPrefill(null);
        }}
        onSubmit={handleCreatePerson}
        isLoading={isCreatingPerson}
        prefillData={personPrefill}
      />
    </>
  );
}
```

### PersonEditModal Enhancement (prefillData prop)
```jsx
// Source: Extend existing PersonEditModal.jsx
export default function PersonEditModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  person = null,
  prefillData = null  // NEW: for pre-filling from external context
}) {
  // In useEffect for reset:
  useEffect(() => {
    if (isOpen) {
      if (person) {
        // Editing mode - use person data
        // ... existing code
      } else if (prefillData) {
        // NEW: Pre-fill mode - use provided data
        reset({
          first_name: prefillData.first_name || '',
          last_name: prefillData.last_name || '',
          nickname: '',
          gender: '',
          pronouns: '',
          email: prefillData.email || '',
          phone: '',
          phone_type: 'mobile',
          birthday: '',
          how_we_met: '',
          is_favorite: false,
        });
        setVisibility('private');
        setSelectedWorkspaces([]);
      } else {
        // Creating - reset to defaults
        // ... existing code
      }
    }
  }, [isOpen, person, prefillData, reset]);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual state management | TanStack Query | v3.0 | Automatic cache invalidation on mutations |
| Prop drilling for forms | react-hook-form | v2.0 | Simpler form handling, validation built-in |
| Custom modals per feature | Reusable modal components | v2.0 | Consistent UX, less code duplication |

**Deprecated/outdated:**
- None applicable - codebase uses current patterns

## Open Questions

Things that couldn't be fully resolved:

1. **Calendar event re-sync timing**
   - What we know: Person creation triggers query invalidation which refetches meeting data
   - What's unclear: Does the server immediately re-match attendees or is this deferred?
   - Recommendation: Test and verify server re-matching happens before query response

2. **Multiple unmatched attendees with same email**
   - What we know: Attendees are keyed by email in the map
   - What's unclear: How to handle duplicates (shouldn't happen but edge case)
   - Recommendation: Use email || index as key (already done in current code)

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/components/MeetingDetailModal.jsx` - Current attendee display logic
- `/Users/joostdevalk/Code/stadion/src/components/PersonEditModal.jsx` - Form structure and props
- `/Users/joostdevalk/Code/stadion/src/hooks/usePeople.js` - useCreatePerson hook with onSuccess pattern
- `/Users/joostdevalk/Code/stadion/src/hooks/useMeetings.js` - Meeting query keys for invalidation
- `/Users/joostdevalk/Code/stadion/src/components/layout/Layout.jsx` - Modal hosting pattern with createPersonMutation
- `/Users/joostdevalk/Code/stadion/src/pages/People/PeopleList.jsx` - Another example of useCreatePerson usage

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/.planning/ROADMAP.md` - Phase requirements and success criteria

### Tertiary (LOW confidence)
- None needed - all patterns well-established in codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using only existing libraries
- Architecture: HIGH - Following established codebase patterns
- Pitfalls: HIGH - Based on actual code review

**Research date:** 2026-01-17
**Valid until:** 30 days (stable frontend patterns)
