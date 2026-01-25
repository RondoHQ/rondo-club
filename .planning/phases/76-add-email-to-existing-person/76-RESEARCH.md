# Phase 76: Add Email to Existing Person - Research

**Researched:** 2026-01-17
**Domain:** React UI flow, WordPress ACF field updates, Calendar re-matching
**Confidence:** HIGH

## Summary

This phase extends the Phase 74 "Add Person from Meeting" feature to allow users to add an unknown attendee's email address to an existing person instead of always creating a new person. The core challenge is introducing a choice popup when the Add button is clicked, implementing person search/select, and updating the existing person's contact_info ACF field.

The codebase already has all necessary primitives:
- Person search via `/stadion/v1/search` endpoint (used by SearchModal in Layout.jsx)
- Person update via `useUpdatePerson` hook using `wpApi.updatePerson`
- contact_info ACF repeater field for storing emails
- Calendar re-matching via `Matcher::on_person_saved()` hook (auto-triggers on acf/save_post)

**Primary recommendation:** Create a choice popup component (AddAttendeePopup), reuse the existing search pattern from SearchModal, update contact_info via useUpdatePerson, and let the existing ACF save hook handle calendar re-matching automatically.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | UI framework | Already in use throughout app |
| TanStack Query | 5.x | Server state/caching | Already used for all API calls |
| React Hook Form | 7.x | Form management | Already used in modals |
| Lucide React | Latest | Icons | Already used throughout app |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Tailwind CSS | 3.4 | Styling | All UI styling |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom search dropdown | react-select/headlessui | Overkill - SearchModal pattern already works well |
| Full modal for choice | Simple dropdown/popover | Modal would add unnecessary friction for 2-option choice |

**Installation:**
No new dependencies needed. All required libraries already installed.

## Architecture Patterns

### Recommended Component Structure
```
src/components/
  MeetingDetailModal.jsx        # Modified - integrates AddAttendeePopup
  AddAttendeePopup.jsx          # NEW - choice + search component
  PersonEditModal.jsx           # Unchanged - already supports prefillData
```

### Pattern 1: Choice Popup Pattern
**What:** Simple inline popup that appears when clicking Add button, showing two options
**When to use:** When user needs to make a quick binary choice before proceeding
**Example:**
```jsx
// Popup appears below/beside the Add button
<AddAttendeePopup
  isOpen={showPopup}
  onClose={() => setShowPopup(false)}
  onAddToExisting={handleAddToExisting}   // Opens search mode
  onCreateNew={handleCreateNew}           // Opens PersonEditModal
  attendee={selectedAttendee}
/>
```

### Pattern 2: Person Search Integration
**What:** Inline search within the popup, reusing the SearchModal logic
**When to use:** When user selects "Add to existing person"
**Example:**
```jsx
// Two states: choice mode and search mode
const [mode, setMode] = useState('choice'); // 'choice' | 'search'

{mode === 'choice' && (
  <>
    <button onClick={() => setMode('search')}>Add to existing person</button>
    <button onClick={handleCreateNew}>Create new person</button>
  </>
)}

{mode === 'search' && (
  <PersonSearch onSelect={handleSelectPerson} onBack={() => setMode('choice')} />
)}
```

### Pattern 3: contact_info Update via useUpdatePerson
**What:** Adding email to existing person's contact_info array
**When to use:** After user selects a person from search
**Example:**
```jsx
// Get current contact_info, append new email, save
const handleSelectPerson = async (person) => {
  const currentContacts = person.acf?.contact_info || [];
  const newContact = {
    contact_type: 'email',
    contact_value: attendee.email,
    contact_label: 'Email',
  };

  await updatePerson.mutateAsync({
    id: person.id,
    data: {
      acf: {
        contact_info: [...currentContacts, newContact],
      },
    },
  });
};
```

### Anti-Patterns to Avoid
- **Creating new endpoint for email addition:** Use existing updatePerson - WordPress REST API + ACF handles repeater fields correctly
- **Manual calendar re-matching:** The `acf/save_post` hook automatically triggers `Matcher::on_person_saved()` which invalidates cache and re-matches events
- **Complex modal-within-modal:** Keep the popup simple and inline; avoid opening PersonEditModal for the existing person case

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Person search | Custom search component | SearchModal pattern from Layout.jsx | Already has debouncing, loading states, keyboard nav |
| Email addition to person | Custom API endpoint | `wpApi.updatePerson` with ACF contact_info | WordPress REST + ACF handles repeater updates |
| Calendar re-matching | Custom re-match trigger | Existing `acf/save_post` hook | Already wired up in functions.php |
| Person data fetching | New query hook | usePeople() hook | Returns all people with thumbnails |

**Key insight:** The existing search pattern in SearchModal (lines 197-416 of Layout.jsx) provides the exact UI/UX pattern needed - input field, debounced search, results with thumbnails, keyboard navigation. Extract and reuse this pattern.

## Common Pitfalls

### Pitfall 1: Stale contact_info when adding email
**What goes wrong:** Person's contact_info array is outdated, overwriting other recent changes
**Why it happens:** Reading from cached person data instead of fetching fresh
**How to avoid:** Fetch fresh person data before adding email, or use functional update pattern
**Warning signs:** User adds email but another contact they just added disappears

### Pitfall 2: Popup positioning on mobile
**What goes wrong:** Popup appears off-screen or behind keyboard
**Why it happens:** Fixed positioning without considering viewport constraints
**How to avoid:** Use relative positioning near the Add button, or consider a bottom sheet on mobile
**Warning signs:** User can't see or interact with the choice popup

### Pitfall 3: Missing loading states during email addition
**What goes wrong:** User clicks rapidly, causing duplicate emails
**Why it happens:** No disabled state during mutation
**How to avoid:** Disable buttons and show loading state during updatePerson mutation
**Warning signs:** Same email appears multiple times in contact_info

### Pitfall 4: Calendar query not invalidated after adding email
**What goes wrong:** Attendee list doesn't update after adding email to existing person
**Why it happens:** Only invalidating on person create, not on update
**How to avoid:** Also invalidate meetingsKeys.today in useUpdatePerson or after the specific email-add mutation
**Warning signs:** Attendee still shows as unknown after adding their email to a person

## Code Examples

### Example 1: Choice Popup Component Structure
```jsx
// Source: Based on existing popup patterns in Layout.jsx (QuickAddMenu, UserMenu)
export default function AddAttendeePopup({
  isOpen,
  onClose,
  onAddToExisting,
  onCreateNew,
  anchorRef
}) {
  const popupRef = useRef(null);

  // Close on click outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (popupRef.current && !popupRef.current.contains(event.target)) {
        onClose();
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div
      ref={popupRef}
      className="absolute z-50 mt-1 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg"
    >
      <div className="py-1">
        <button
          onClick={onAddToExisting}
          className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
        >
          Add to existing person
        </button>
        <button
          onClick={onCreateNew}
          className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
        >
          Create new person
        </button>
      </div>
    </div>
  );
}
```

### Example 2: Person Search Pattern (from SearchModal)
```jsx
// Source: Layout.jsx SearchModal component (lines 197-416)
function PersonSearchDropdown({ onSelect, onBack }) {
  const [query, setQuery] = useState('');
  const { data: searchResults, isLoading } = useSearch(query);

  const people = searchResults?.people || [];

  return (
    <div>
      <div className="flex items-center border-b">
        <button onClick={onBack} className="p-2">
          <ChevronLeft className="w-4 h-4" />
        </button>
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search people..."
          className="flex-1 px-2 py-2 outline-none"
          autoFocus
        />
      </div>

      <div className="max-h-48 overflow-y-auto">
        {isLoading && <div className="p-3 text-center text-sm">Searching...</div>}
        {!isLoading && people.length === 0 && query.length >= 2 && (
          <div className="p-3 text-center text-sm text-gray-500">No people found</div>
        )}
        {people.map((person) => (
          <button
            key={person.id}
            onClick={() => onSelect(person)}
            className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50"
          >
            {person.thumbnail ? (
              <img src={person.thumbnail} className="w-8 h-8 rounded-full" />
            ) : (
              <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                <User className="w-4 h-4 text-gray-500" />
              </div>
            )}
            <span className="text-sm">{person.name}</span>
          </button>
        ))}
      </div>
    </div>
  );
}
```

### Example 3: Adding Email to Existing Person
```jsx
// Source: Pattern from PersonDetail.jsx handleSaveContacts + usePeople.js useUpdatePerson
async function addEmailToExistingPerson(personId, email) {
  // Fetch fresh person data to get current contact_info
  const response = await wpApi.getPerson(personId, { _embed: true });
  const person = response.data;

  const currentContacts = person.acf?.contact_info || [];

  // Check if email already exists
  const emailExists = currentContacts.some(
    c => c.contact_type === 'email' && c.contact_value.toLowerCase() === email.toLowerCase()
  );

  if (emailExists) {
    // Email already on this person - just close popup
    return { alreadyExists: true, person };
  }

  // Add new email
  const newContact = {
    contact_type: 'email',
    contact_value: email.toLowerCase(),
    contact_label: 'Email',
  };

  await updatePerson.mutateAsync({
    id: personId,
    data: {
      acf: {
        contact_info: [...currentContacts, newContact],
      },
    },
  });

  return { alreadyExists: false, person };
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Create new person only | Choice: add to existing or create new | Phase 76 | More efficient contact management |
| Manual lookup before adding | In-context choice popup | Phase 76 | Reduced friction for users |

**Deprecated/outdated:**
- N/A - This is a new feature

## Open Questions

No major open questions. The implementation path is clear:

1. **Resolved:** How to search people - use existing `/stadion/v1/search` endpoint via useSearch hook
2. **Resolved:** How to update contact_info - use existing useUpdatePerson with ACF data
3. **Resolved:** How to trigger calendar re-matching - automatic via existing `acf/save_post` hook
4. **Resolved:** Meeting query invalidation - need to add to useUpdatePerson or custom hook

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/components/MeetingDetailModal.jsx` - Current add person implementation from Phase 74
- `/Users/joostdevalk/Code/stadion/src/components/PersonEditModal.jsx` - prefillData pattern from Phase 74
- `/Users/joostdevalk/Code/stadion/src/components/layout/Layout.jsx` - SearchModal pattern (lines 197-416)
- `/Users/joostdevalk/Code/stadion/src/hooks/usePeople.js` - useUpdatePerson, useCreatePerson hooks
- `/Users/joostdevalk/Code/stadion/includes/class-calendar-matcher.php` - Matcher::on_person_saved() hook
- `/Users/joostdevalk/Code/stadion/src/components/ContactEditModal.jsx` - contact_info field structure

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/.planning/phases/74-add-person-from-meeting/74-01-PLAN.md` - Phase 74 implementation details

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components already exist in codebase
- Architecture: HIGH - Following established patterns from Phase 74 and SearchModal
- Pitfalls: HIGH - Based on actual code analysis of contact_info handling

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (30 days - stable patterns)
