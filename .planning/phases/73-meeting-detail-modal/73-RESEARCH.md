# Phase 73: Meeting Detail Modal - Research

**Researched:** 2026-01-17
**Domain:** React Modal Components, Calendar Integration, Meeting Display
**Confidence:** HIGH

## Summary

Phase 73 implements a Meeting Detail Modal that displays full meeting information when users click on a meeting card. The codebase already has well-established modal patterns (TodoModal, ShareModal, ImportantDateModal) that provide clear templates for implementation. The calendar integration (v4.0) already stores rich meeting data including attendees, location, description, and matched people.

The existing `MeetingCard` component in `PersonDetail.jsx` shows meeting cards but they are not clickable to open a detail modal. The Today's Meetings widget in Dashboard uses a simpler card that links to person profiles. The API returns comprehensive meeting data including `matched_people` (Stadion contacts) and `attendees` (raw calendar attendees), providing everything needed for the modal.

**Primary recommendation:** Create a read-only `MeetingDetailModal` component following the TodoModal pattern (view/edit modes), with attendee list showing matched vs unknown attendees, and a rich text notes section for meeting prep that stores to post meta.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18.x | Component framework | Already in use |
| lucide-react | 0.x | Icons | Already in use for modals |
| date-fns | 2.x | Date formatting | Already used in Dashboard/PersonDetail |
| @tiptap/react | 2.x | Rich text editor | Already used for RichTextEditor component |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| TanStack Query | 5.x | Data fetching/mutation | For meeting notes API |
| react-router-dom | 6.x | Navigation | Link to person profiles |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom modal | Headless UI Dialog | Existing patterns don't use Headless UI, consistency preferred |
| TipTap | Textarea | Simple notes don't need rich text, but consistency with other notes |

**Installation:**
```bash
# No new packages needed - all already installed
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── components/
│   └── MeetingDetailModal.jsx    # New modal component
├── hooks/
│   └── useMeetings.js            # Extend with notes mutation
└── pages/
    └── Dashboard.jsx             # Update MeetingCard to be clickable
```

### Pattern 1: Modal Component Structure (from TodoModal)
**What:** Modal with fixed positioning, backdrop, header, content sections, footer
**When to use:** All modal components in this codebase
**Example:**
```jsx
// Source: src/components/Timeline/TodoModal.jsx
export default function MeetingDetailModal({ isOpen, onClose, meeting }) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{title}</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content - scrollable */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* content sections */}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
          <button onClick={onClose} className="btn-secondary">Close</button>
        </div>
      </div>
    </div>
  );
}
```

### Pattern 2: Attendee List with Mixed Known/Unknown (from ShareModal)
**What:** List showing users with avatars, distinguishing different types
**When to use:** Displaying attendee lists with visual differentiation
**Example:**
```jsx
// Source: Adapted from ShareModal pattern
{attendees.map((attendee) => (
  <div key={attendee.email} className="flex items-center gap-3 p-3">
    {attendee.matched ? (
      // Known attendee - clickable, with photo
      <Link to={`/people/${attendee.person_id}`} className="flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-700 -mx-3 px-3 py-2 rounded-lg">
        <img src={attendee.thumbnail} className="w-8 h-8 rounded-full" />
        <span className="font-medium text-accent-600 dark:text-accent-400">{attendee.name}</span>
      </Link>
    ) : (
      // Unknown attendee - not clickable
      <div className="flex items-center gap-3">
        <div className="w-8 h-8 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
          <User className="w-4 h-4 text-gray-500" />
        </div>
        <span className="text-gray-600 dark:text-gray-400">{attendee.email || attendee.name}</span>
      </div>
    )}
  </div>
))}
```

### Pattern 3: Click Handler on Dashboard Card
**What:** Make existing MeetingCard clickable to open modal
**When to use:** Enabling click-to-open functionality
**Example:**
```jsx
// Dashboard.jsx - modify MeetingCard
function MeetingCard({ meeting, onClick }) {
  return (
    <button
      type="button"
      onClick={() => onClick(meeting)}
      className="w-full flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-left"
    >
      {/* existing card content */}
    </button>
  );
}
```

### Anti-Patterns to Avoid
- **Don't create separate modal for dashboard vs person detail:** Use same MeetingDetailModal in both locations
- **Don't fetch meeting data again in modal:** Modal receives meeting object as prop (data already available)
- **Don't use inline onclick navigation for known attendees:** Use Link component for proper routing

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date/time formatting | Custom formatters | date-fns (already used) | Timezone handling, locale support |
| Rich text notes | Plain textarea | RichTextEditor component | Consistency with other notes in app |
| Avatar fallback | Custom initials logic | Existing PersonCard pattern | Already handles missing thumbnails |
| Modal backdrop | Custom div | Existing modal pattern | Proper z-index, click handling |
| Scrollable content | CSS overflow | flex + overflow-y-auto pattern | Already used in ImportantDateModal |

**Key insight:** The codebase already has mature patterns for modals, avatars, date formatting, and rich text. Reuse these patterns exactly rather than creating variations.

## Common Pitfalls

### Pitfall 1: Data Shape Mismatch Between Dashboard and PersonDetail
**What goes wrong:** Dashboard's `format_today_meeting` returns different shape than PersonDetail's `format_meeting_event`
**Why it happens:** Two different API endpoints with slightly different response structures
**How to avoid:** Modal should handle both shapes gracefully:
- Dashboard returns: `matched_people: [{person_id, name, thumbnail}]`
- PersonDetail returns: Additional fields like `match_type`, `confidence`, `matched_attendee_email`, `other_attendees`
**Warning signs:** Modal works in one location but breaks in another

### Pitfall 2: Missing Attendee Data
**What goes wrong:** Raw attendees from calendar not available, only matched people
**Why it happens:** `_attendees` meta contains all attendees, `_matched_people` contains only Stadion matches
**How to avoid:** API needs to return both matched and unmatched attendees for ADD-01 requirement
**Warning signs:** Cannot show "unknown attendees" because data isn't in response

### Pitfall 3: Notes Persistence
**What goes wrong:** Notes don't save or load correctly
**Why it happens:** calendar_event post type doesn't have content support, need post meta
**How to avoid:** Store notes in `_meeting_notes` post meta, add API endpoint to save
**Warning signs:** Notes disappear on page refresh

### Pitfall 4: Timezone Display
**What goes wrong:** Times show incorrectly or in wrong timezone
**Why it happens:** ISO strings need proper parsing with timezone awareness
**How to avoid:** Use `new Date()` which handles ISO 8601 with timezone offsets correctly (already being done)
**Warning signs:** Meeting times off by hours

## Code Examples

Verified patterns from the codebase:

### Modal Header (from TodoModal)
```jsx
// Source: src/components/Timeline/TodoModal.jsx lines 443-456
<div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
  <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">{getModalTitle()}</h2>
  <button
    onClick={handleClose}
    className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
    disabled={isLoading}
  >
    <X className="w-5 h-5" />
  </button>
</div>
```

### Scrollable Content Area (from ImportantDateModal)
```jsx
// Source: src/components/ImportantDateModal.jsx lines 270-271
<div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden flex flex-col">
  {/* ... */}
  <div className="flex-1 overflow-y-auto p-4 space-y-4">
    {/* scrollable content */}
  </div>
</div>
```

### Avatar with Fallback (from Dashboard MeetingCard)
```jsx
// Source: src/pages/Dashboard.jsx lines 257-264
{person.thumbnail ? (
  <img key={person.person_id} src={person.thumbnail} alt={person.name} loading="lazy"
    className="w-8 h-8 rounded-full border-2 border-white dark:border-gray-800 object-cover" />
) : (
  <div key={person.person_id}
    className="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded-full border-2 border-white dark:border-gray-800 flex items-center justify-center">
    <span className="text-xs dark:text-gray-300">{person.name?.[0]}</span>
  </div>
)}
```

### Link to Person Profile (from ReminderCard)
```jsx
// Source: src/pages/Dashboard.jsx lines 117-125
if (hasRelatedPeople && firstPersonId) {
  return (
    <Link
      to={`/people/${firstPersonId}`}
      className="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
    >
      {cardContent}
    </Link>
  );
}
```

### RichTextEditor Usage (from TodoModal)
```jsx
// Source: src/components/Timeline/TodoModal.jsx lines 294-302
{showNotes && (
  <RichTextEditor
    value={notes}
    onChange={setNotes}
    placeholder="Add detailed notes..."
    disabled={isLoading}
    minHeight="80px"
  />
)}
```

### Date Formatting (from MeetingCard in PersonDetail)
```jsx
// Source: src/pages/People/PersonDetail.jsx extracted pattern
const formatTime = (date) => {
  if (!date) return '';
  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
};

const formatDate = (date) => {
  if (!date) return '';
  return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Separate modals per context | Shared modal component | v4.0 calendar integration | Code reuse, consistency |
| Plain textarea for notes | RichTextEditor | v3.x | Consistent notes experience |

**Deprecated/outdated:**
- None identified - modal patterns are current and consistent

## Data Structure Reference

### Meeting Object (from Dashboard API)
```javascript
// Source: class-rest-calendar.php format_today_meeting()
{
  id: 123,                    // calendar_event post ID
  title: "Team Standup",      // post_title
  start_time: "2026-01-17T10:00:00+01:00",  // ISO 8601 with timezone
  end_time: "2026-01-17T10:30:00+01:00",
  all_day: false,
  location: "Conference Room A",
  meeting_url: "https://meet.google.com/abc",
  matched_people: [           // People matched to Stadion contacts
    { person_id: 456, name: "John Doe", thumbnail: "url" }
  ],
  calendar_name: "Work Calendar"
}
```

### Meeting Object (from PersonDetail API)
```javascript
// Source: class-rest-calendar.php format_meeting_event()
{
  // All fields from above, plus:
  match_type: "email_exact",           // How this person was matched
  confidence: 100,                     // Match confidence %
  matched_attendee_email: "john@example.com",  // Email that matched
  other_attendees: ["jane@example.com"],       // Other attendee emails
  connection_id: "google_abc123",
  logged_as_activity: false
}
```

### Raw Attendees (from _attendees meta)
```javascript
// Not currently exposed via API - needs to be added
[
  { email: "john@example.com", name: "John Doe", status: "accepted" },
  { email: "unknown@team.com", name: "Unknown Person", status: "accepted" }
]
```

## API Additions Required

For meeting notes (MTG-08) and full attendee list (ADD-01), the API needs:

### 1. Get/Save Meeting Notes
```php
// GET /stadion/v1/calendar/events/{id}/notes
// Returns: { notes: "<html content>" }

// PUT /stadion/v1/calendar/events/{id}/notes
// Body: { notes: "<html content>" }
// Stores in _meeting_notes post meta
```

### 2. Extend Meeting Response with All Attendees
```php
// Add 'attendees' field to format_today_meeting() and format_meeting_event()
// Returns raw _attendees array with matched status added
'attendees' => array_map(function($attendee) use ($matched_people) {
    $email = strtolower($attendee['email'] ?? '');
    $match = null;
    foreach ($matched_people as $mp) {
        if (strtolower($mp['attendee_email'] ?? '') === $email) {
            $match = $mp;
            break;
        }
    }
    return [
        'email' => $attendee['email'],
        'name' => $attendee['name'],
        'matched' => $match !== null,
        'person_id' => $match['person_id'] ?? null,
        'person_name' => $match ? get_the_title($match['person_id']) : null,
        'thumbnail' => $match ? wp_get_attachment_image_url(get_post_thumbnail_id($match['person_id']), 'thumbnail') : null,
    ];
}, $attendees)
```

## Open Questions

Things that couldn't be fully resolved:

1. **Notes storage scope**
   - What we know: Need to store per-meeting notes
   - What's unclear: Should notes be per-user (user meta keyed by event ID) or per-event (post meta)?
   - Recommendation: Use post meta `_meeting_notes` since calendar events are user-scoped already

2. **Description vs Notes distinction**
   - What we know: Calendar events have post_content (description from calendar), we need user notes
   - What's unclear: Should we show both description AND notes, or just notes?
   - Recommendation: Show description as read-only if present, show notes as editable

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/src/components/Timeline/TodoModal.jsx` - Modal pattern
- `/Users/joostdevalk/Code/stadion/src/components/ImportantDateModal.jsx` - Modal with scrolling
- `/Users/joostdevalk/Code/stadion/src/components/ShareModal.jsx` - User list pattern
- `/Users/joostdevalk/Code/stadion/src/pages/Dashboard.jsx` - MeetingCard component
- `/Users/joostdevalk/Code/stadion/src/pages/People/PersonDetail.jsx` - Full MeetingCard component
- `/Users/joostdevalk/Code/stadion/includes/class-rest-calendar.php` - Meeting API endpoints
- `/Users/joostdevalk/Code/stadion/src/hooks/useMeetings.js` - Meeting hooks
- `/Users/joostdevalk/Code/stadion/src/api/client.js` - API client methods

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-calendar-matcher.php` - Attendee matching logic

### Tertiary (LOW confidence)
- None - all findings verified with codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use in codebase
- Architecture: HIGH - Clear patterns from existing modals
- Pitfalls: HIGH - Based on actual API response structures in code
- API additions: MEDIUM - Patterns clear but implementation details need validation

**Research date:** 2026-01-17
**Valid until:** 2026-02-17 (30 days - stable codebase patterns)
