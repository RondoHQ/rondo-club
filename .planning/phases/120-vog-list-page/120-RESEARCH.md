# Phase 120: VOG List Page - Research

**Researched:** 2026-01-30
**Domain:** React list view with WordPress REST API filtering
**Confidence:** HIGH

## Summary

This phase creates a filtered list view for volunteers needing VOG (Verklaring Omtrent Gedrag) compliance. The implementation follows established patterns from the People List page with server-side filtering, pagination, and sortable columns. The backend filtering infrastructure already exists (Phase 119), so this is primarily a frontend implementation that adds a new route, navigation item, and reuses existing list patterns.

**Key findings:**
- Backend filtering endpoint `/rondo/v1/people/filtered` already supports VOG filters (`vog_missing` and `vog_older_than_years`)
- `huidig-vrijwilliger` and `datum-vog` custom fields exist and are indexed
- FileCheck icon already imported in Settings.jsx
- PeopleList.jsx provides complete pattern for sortable, filterable list with custom field display
- Navigation supports sub-items (seen in Settings tabs) and count badges

**Primary recommendation:** Create a new VOG list route that reuses the `useFilteredPeople` hook with VOG-specific filters, following the PeopleList.jsx component pattern but simplified for the required columns only.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React Router | 6.21.0 | SPA routing with lazy loading | Already used throughout app for navigation |
| TanStack Query | 5.17.0 | Server state management with caching | Existing `useFilteredPeople` hook uses this |
| Tailwind CSS | 3.4.0 | Utility-first styling | Entire app uses Tailwind patterns |
| Lucide React | 0.309.0 | Icon library | FileCheck icon already available |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| date-fns | 3.2.0 | Date formatting | Display VOG dates in list |
| clsx | 2.1.0 | Conditional classes | Dynamic badge and row styling |

### Alternatives Considered
None - all choices are dictated by existing codebase patterns. No alternatives needed as infrastructure is established.

**Installation:**
No new dependencies required - all libraries already installed.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── pages/
│   └── VOG/
│       └── VOGList.jsx          # Main list component
├── hooks/
│   └── usePeople.js             # Existing, add VOG helper if needed
├── App.jsx                      # Add route
└── components/layout/Layout.jsx # Add navigation item
```

### Pattern 1: List Component with Server-Side Filtering

**What:** React component that uses `useFilteredPeople` hook with preset filters for VOG-specific data

**When to use:** For displaying filtered lists of people with pagination and sorting

**Example:**
```javascript
// Simplified from PeopleList.jsx
function VOGList() {
  const [page, setPage] = useState(1);
  const [orderby, setOrderby] = useState('custom_datum-vog'); // Default sort
  const [order, setOrder] = useState('asc');

  // Pre-configure filters for VOG view
  const { data, isLoading } = useFilteredPeople({
    page,
    perPage: 100,
    huidigeVrijwilliger: '1', // Only current volunteers
    orderby,
    order,
    // Backend combines: no datum-vog OR datum-vog older than 3 years
    vogMissing: '1',
    vogOlderThanYears: 3,
  });

  // Render table with Name, KNVB ID, Email, Phone, Datum VOG columns
}
```

**Source:** `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PeopleList.jsx` lines 1-400

### Pattern 2: Navigation Sub-Item with Count Badge

**What:** Indented navigation item under parent with dynamic count

**When to use:** For section-specific views that are subset of parent section

**Example:**
```javascript
// In Layout.jsx navigation array (modify existing structure)
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  {
    name: 'Leden',
    href: '/people',
    icon: Users,
    subItems: [
      { name: 'VOG', href: '/people/vog', icon: FileCheck, badge: vogCount }
    ]
  },
  // ... rest of navigation
];
```

**Source:** Pattern derived from Settings tabs in `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/Settings.jsx` lines 750-765

### Pattern 3: Badge Indicators for Row Status

**What:** Colored badges next to names indicating status (new vs renewal)

**When to use:** Visual distinction of different states in list rows

**Example:**
```javascript
// Determine badge type
const getBadge = (person) => {
  const hasDatumVog = person.acf?.['datum-vog'];
  if (!hasDatumVog) {
    return { text: 'Nieuw', color: 'blue' };
  }
  // Has old VOG (3+ years)
  return { text: 'Vernieuwing', color: 'purple' };
};

// Render in row
<span className="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
  Nieuw
</span>
```

**Source:** Badge pattern from `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PeopleList.jsx` lines 159-175 (label badges)

### Pattern 4: Custom Field Column Display

**What:** Reusable component for rendering ACF custom fields in table columns

**When to use:** Displaying custom field values with proper formatting

**Example:**
```javascript
import CustomFieldColumn from '@/components/CustomFieldColumn';

// In table cell
<td className="px-4 py-3 text-sm">
  <CustomFieldColumn
    field={customFieldsMap['datum-vog']}
    value={person.acf?.['datum-vog']}
  />
</td>
```

**Source:** `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PeopleList.jsx` lines 126-136

### Anti-Patterns to Avoid

- **Don't fetch all people client-side and filter:** Use server-side filtering via `useFilteredPeople` for performance
- **Don't hardcode column widths:** Use dynamic width patterns from PeopleList.jsx
- **Don't create custom backend endpoint:** Existing `/rondo/v1/people/filtered` handles VOG filters
- **Don't duplicate list logic:** Reuse established patterns from PeopleList.jsx

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| VOG filtering logic | Custom WP_Query with meta_query | `useFilteredPeople` hook with `huidigeVrijwilliger`, `vogMissing`, `vogOlderThanYears` params | Backend already implements complex filtering with proper indexing |
| Date formatting for VOG dates | Manual date string parsing | `date-fns` format() or CustomFieldColumn component | ACF stores dates in Y-m-d format, needs proper formatting and timezone handling |
| Sortable table headers | Custom sort state management | Pattern from PeopleList.jsx with `orderby`/`order` state | Handles server-side sorting, proper icons, accessibility |
| Empty state message | Plain text div | Pattern from PeopleList.jsx with icon and styled card | Consistent UX with celebratory tone |
| Badge color mapping | Inline conditional classes | clsx utility with defined color constants | Maintainable and supports dark mode |

**Key insight:** The PeopleList.jsx component (600+ lines) already solves all common problems: sorting, filtering, pagination, custom fields, selection state, dark mode, loading states. Extract relevant patterns rather than reimplementing.

## Common Pitfalls

### Pitfall 1: Incorrect VOG Filter Logic

**What goes wrong:** Assuming backend requires separate queries for "no VOG" vs "old VOG"

**Why it happens:** The requirement states "no datum-vog OR datum-vog 3+ years ago" which sounds like OR logic needs client-side merging

**How to avoid:**
- Backend endpoint already implements this correctly in `class-rest-people.php` lines 1136-1144
- Use `vogMissing: '1'` for missing, OR `vogOlderThanYears: 3` for old
- Backend handles the OR logic - don't duplicate it client-side

**Warning signs:** If you're making multiple API calls or merging results arrays, you're doing it wrong

### Pitfall 2: Navigation Count Not Updating

**What goes wrong:** VOG count badge shows stale data after actions

**Why it happens:** Count is derived from list query which has separate cache key

**How to avoid:**
- Use the same query result for both list display AND count derivation
- When count needed in navigation, pass as prop from list component or use shared query
- Alternative: Add count to dashboard stats endpoint (requires backend change)

**Warning signs:** Count updates on page refresh but not after user actions

### Pitfall 3: Custom Field Access Patterns

**What goes wrong:** Accessing `datum-vog` field incorrectly (hyphen in key name)

**Why it happens:** JavaScript object property access with hyphens requires bracket notation

**How to avoid:**
```javascript
// WRONG
const datumVog = person.acf.datum-vog; // Syntax error

// CORRECT
const datumVog = person.acf?.['datum-vog'];
```

**Warning signs:** Undefined errors or syntax errors when accessing custom fields

### Pitfall 4: Badge Logic Edge Cases

**What goes wrong:** Badge shows "Vernieuwing" for volunteers without VOG date

**Why it happens:** Logic only checks if date exists, not if it's old

**How to avoid:**
```javascript
// CORRECT logic
const getBadgeType = (person) => {
  const datumVog = person.acf?.['datum-vog'];

  // No VOG at all = new
  if (!datumVog) return 'new';

  // Has VOG and in this list = must be expired
  // (backend filter ensures only expired VOGs appear)
  return 'renewal';
};
```

**Warning signs:** Volunteers without VOG showing "Vernieuwing" badge

### Pitfall 5: Sortable Custom Field Column Names

**What goes wrong:** Sorting by VOG date doesn't work

**Why it happens:** Custom fields require `custom_` prefix for orderby parameter

**How to avoid:**
```javascript
// Backend expects: custom_datum-vog (not datum-vog)
const handleSort = (columnId) => {
  if (columnId === 'datum-vog') {
    setOrderby('custom_datum-vog'); // Add custom_ prefix
  }
};
```

**Warning signs:** Sort parameter sent but no sorting happens server-side

**Source:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-people.php` lines 1159-1196 (custom field sorting logic)

## Code Examples

Verified patterns from official sources:

### Server-Side Filtered List Query
```javascript
// Source: src/hooks/usePeople.js lines 112-146
import { useFilteredPeople } from '@/hooks/usePeople';

function VOGList() {
  const { data, isLoading, error } = useFilteredPeople({
    page: 1,
    perPage: 100,
    huidigeVrijwilliger: '1',          // Only current volunteers
    vogMissing: '1',                    // No VOG date
    vogOlderThanYears: 3,               // OR older than 3 years
    orderby: 'custom_datum-vog',        // Sort by VOG date
    order: 'asc',                       // Oldest first (nulls first)
  });

  if (isLoading) return <div>Loading...</div>;
  if (error) return <div>Error loading volunteers</div>;

  const { people, total } = data || { people: [], total: 0 };

  return (
    <div>
      {people.map(person => (
        <PersonRow key={person.id} person={person} />
      ))}
    </div>
  );
}
```

### Sortable Table Header
```javascript
// Source: src/pages/People/PeopleList.jsx pattern
import { ArrowUp, ArrowDown } from 'lucide-react';

function SortableHeader({ label, columnId, currentSort, onSort }) {
  const isActive = currentSort.orderby === columnId;
  const nextOrder = isActive && currentSort.order === 'asc' ? 'desc' : 'asc';

  return (
    <th
      onClick={() => onSort(columnId, nextOrder)}
      className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
    >
      <div className="flex items-center gap-1">
        {label}
        {isActive && (
          currentSort.order === 'asc'
            ? <ArrowUp className="w-4 h-4" />
            : <ArrowDown className="w-4 h-4" />
        )}
      </div>
    </th>
  );
}
```

### Status Badge Component
```javascript
// Pattern derived from PeopleList.jsx label badges
function VOGBadge({ person }) {
  const datumVog = person.acf?.['datum-vog'];

  // No VOG = new volunteer
  const isNew = !datumVog;

  return (
    <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${
      isNew
        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
    }`}>
      {isNew ? 'Nieuw' : 'Vernieuwing'}
    </span>
  );
}
```

### Empty State with Success Message
```javascript
// Source: Pattern from PeopleList.jsx and other list views
import { CheckCircle } from 'lucide-react';

function VOGEmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <div className="flex justify-center mb-4">
        <div className="p-3 bg-green-100 rounded-full dark:bg-green-900/30">
          <CheckCircle className="w-8 h-8 text-green-600 dark:text-green-400" />
        </div>
      </div>
      <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
        Alle vrijwilligers hebben een geldige VOG
      </h3>
      <p className="text-sm text-gray-600 dark:text-gray-400">
        Er zijn momenteel geen vrijwilligers die een nieuwe of vernieuwde VOG nodig hebben.
      </p>
    </div>
  );
}
```

### Custom Field Column Rendering
```javascript
// Source: src/pages/People/PeopleList.jsx lines 126-136
import CustomFieldColumn from '@/components/CustomFieldColumn';

function VOGRow({ person, customFieldsMap }) {
  return (
    <tr>
      {/* Name with badge */}
      <td className="px-4 py-3">
        <Link to={`/people/${person.id}`}>
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium">
              {person.first_name} {person.last_name}
            </span>
            <VOGBadge person={person} />
          </div>
        </Link>
      </td>

      {/* KNVB ID (custom field) */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        <CustomFieldColumn
          field={customFieldsMap['knvb-id']}
          value={person.acf?.['knvb-id']}
        />
      </td>

      {/* Email (from contact_info) */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {getFirstContactByType(person, 'email') || '-'}
      </td>

      {/* Phone (from contact_info) */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {getFirstPhone(person) || '-'}
      </td>

      {/* Datum VOG (custom field) */}
      <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        <CustomFieldColumn
          field={customFieldsMap['datum-vog']}
          value={person.acf?.['datum-vog']}
        />
      </td>
    </tr>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Client-side filtering with WP_Query | Server-side filtering with `/rondo/v1/people/filtered` | Phase 111-113 (v9.0) | Massive performance improvement for 1400+ contacts |
| Separate endpoints per filter | Single flexible endpoint with query params | Phase 111 | Cleaner API, better caching |
| Manual meta_query construction | Named filter parameters (`vogMissing`, `vogOlderThanYears`) | Phase 119 | Type-safe, self-documenting |

**Deprecated/outdated:**
- `usePeople()` hook for large filtered lists - Use `useFilteredPeople()` instead (more efficient)
- Direct WP_Query with meta_query for custom fields - Backend handles this with proper indexing

## Open Questions

None - all technical questions resolved:

1. **VOG filtering implementation:** Confirmed in `class-rest-people.php` lines 1136-1144
2. **Custom field access:** Verified hyphens require bracket notation
3. **Navigation pattern:** Settings tabs provide sub-navigation pattern
4. **Badge styling:** Consistent with existing label badge patterns
5. **Icon availability:** FileCheck already imported in Settings.jsx

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/People/PeopleList.jsx` - Complete list component pattern (lines 1-600)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/hooks/usePeople.js` - useFilteredPeople hook implementation (lines 88-146)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-people.php` - Backend filtering logic (lines 1007-1269)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/components/layout/Layout.jsx` - Navigation structure (lines 43-52, 85-107)
- `/Users/joostdevalk/Code/rondo/rondo-club/.planning/phases/120-vog-list-page/120-CONTEXT.md` - Phase requirements and decisions

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/.planning/phases/119-backend-foundation/119-01-PLAN.md` - VOG backend implementation details
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-vog-email.php` - VOG email service (lines 1-335)
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/Settings.jsx` - FileCheck icon import and tab pattern (lines 3, 22, 750-765)

### Tertiary (LOW confidence)
None - all findings verified with codebase inspection

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, no new dependencies
- Architecture: HIGH - Exact patterns exist in PeopleList.jsx
- Pitfalls: HIGH - Derived from actual backend code inspection (custom field access, sorting logic)

**Research date:** 2026-01-30
**Valid until:** 2026-03-30 (60 days - stable patterns, low churn in list view architecture)
