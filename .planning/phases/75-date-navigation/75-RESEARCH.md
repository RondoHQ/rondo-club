# Phase 75: Date Navigation - Research

**Researched:** 2026-01-17
**Domain:** React date navigation, date-fns manipulation, dashboard widget enhancement
**Confidence:** HIGH

## Summary

This phase adds date navigation to the existing "Today's meetings" dashboard widget, allowing users to browse meetings across different days. The implementation is straightforward - it extends the existing `useTodayMeetings` hook and REST API endpoint to accept a date parameter, and adds prev/next/today navigation buttons to the widget header.

The codebase already uses date-fns v3.2.0 extensively, and the patterns for date manipulation (`format`, `addDays`, `subDays`) are well-established. The meetings widget and REST API endpoint are already working correctly for "today" - this phase only needs to parameterize the date.

**Primary recommendation:** Add date state to Dashboard, pass to hook/API, and add navigation buttons to the existing widget header using the established UI patterns.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| date-fns | ^3.2.0 | Date manipulation | Already in use throughout codebase |
| lucide-react | ^0.309.0 | Icons | Already in use, has ChevronLeft/ChevronRight/Calendar |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| React useState | React 18 | Date state management | Track selected date |
| TanStack Query | ^5.17.0 | Data fetching with query key | Already used by useTodayMeetings |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| date-fns | dayjs | date-fns already used everywhere, no reason to add another library |
| Local state | URL params | Simpler to use local state; URL params would complicate navigation |

**Installation:**
```bash
# No new dependencies needed - all required libraries already installed
```

## Architecture Patterns

### Current Meeting Widget Structure
```
src/pages/Dashboard.jsx          # Contains meetings card renderer
  - useTodayMeetings()           # Hook that fetches today's meetings
    - prmApi.getTodayMeetings()  # API client method
      - /prm/v1/calendar/today-meetings  # REST endpoint (currently hardcoded to today)
```

### Target Meeting Widget Structure
```
src/pages/Dashboard.jsx          # Contains meetings card renderer
  - useState(selectedDate)       # Track selected date (default: today)
  - useDateMeetings(date)        # New/modified hook accepting date
    - prmApi.getMeetingsForDate(date)  # New/modified API method
      - /prm/v1/calendar/today-meetings?date=YYYY-MM-DD  # Add date param
```

### Pattern 1: Date Navigation State Management
**What:** Use React useState for selected date, default to today
**When to use:** Simple date navigation where persistence isn't needed
**Example:**
```javascript
// In Dashboard.jsx
import { format, addDays, subDays, isToday } from 'date-fns';

const [selectedDate, setSelectedDate] = useState(new Date());

const handlePrevDay = () => setSelectedDate(d => subDays(d, 1));
const handleNextDay = () => setSelectedDate(d => addDays(d, 1));
const handleToday = () => setSelectedDate(new Date());
```

### Pattern 2: Query Key with Date Parameter
**What:** Include date in TanStack Query key for proper caching/refetching
**When to use:** Any date-parameterized API call
**Example:**
```javascript
// In useMeetings.js
export const meetingsKeys = {
  all: ['meetings'],
  forDate: (date) => ['meetings', 'forDate', format(date, 'yyyy-MM-dd')],
  // ... existing keys
};

export function useDateMeetings(date) {
  const dateStr = format(date, 'yyyy-MM-dd');
  return useQuery({
    queryKey: meetingsKeys.forDate(date),
    queryFn: async () => {
      const response = await prmApi.getMeetingsForDate(dateStr);
      return response.data;
    },
    enabled: !!date,
    staleTime: 5 * 60 * 1000,
    refetchInterval: 15 * 60 * 1000,
  });
}
```

### Pattern 3: Widget Header with Navigation
**What:** Navigation buttons integrated into existing card header pattern
**When to use:** Any widget that needs date/page navigation
**Example:**
```javascript
// Navigation buttons in header
<div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
  <h2 className="font-semibold flex items-center dark:text-gray-50">
    <CalendarClock className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
    {isToday(selectedDate) ? "Today's meetings" : format(selectedDate, 'EEEE, MMMM d')}
  </h2>
  <div className="flex items-center gap-1">
    <button onClick={handlePrevDay} className="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
      <ChevronLeft className="w-4 h-4" />
    </button>
    {!isToday(selectedDate) && (
      <button onClick={handleToday} className="px-2 py-1 text-xs ...">
        Today
      </button>
    )}
    <button onClick={handleNextDay} className="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
      <ChevronRight className="w-4 h-4" />
    </button>
  </div>
</div>
```

### Anti-Patterns to Avoid
- **Mutating date state:** Always create new Date objects with addDays/subDays, never mutate
- **String concatenation for dates:** Use date-fns format() for consistent formatting
- **Timezone issues:** Use date-fns consistently; API already returns ISO 8601 with timezone

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date arithmetic | Manual day calculation | `date-fns addDays/subDays` | Handles month/year boundaries, leap years |
| Date formatting | Template literals | `date-fns format()` | Consistent localization-ready formatting |
| "Is today" check | Manual comparison | `date-fns isToday()` | Handles timezone correctly |
| Navigation icons | Custom SVGs | `lucide-react ChevronLeft/Right` | Already in use, consistent styling |

**Key insight:** date-fns is already used throughout the codebase - all date manipulation should use it for consistency.

## Common Pitfalls

### Pitfall 1: Date Comparison Timezone Issues
**What goes wrong:** Comparing dates across timezones gives unexpected results
**Why it happens:** JavaScript Date objects include time, not just date
**How to avoid:** Use date-fns comparison functions or format to YYYY-MM-DD for API calls
**Warning signs:** Meetings appearing on wrong day near midnight

### Pitfall 2: Query Cache Not Updating
**What goes wrong:** Navigating to new date shows stale data
**Why it happens:** Query key doesn't include date, so cache returns old results
**How to avoid:** Include formatted date string in query key: `['meetings', 'forDate', dateStr]`
**Warning signs:** Same meetings showing regardless of selected date

### Pitfall 3: "Today" Button Always Visible
**What goes wrong:** Today button clutters UI when already viewing today
**Why it happens:** Not checking if selected date is already today
**How to avoid:** Conditionally render: `{!isToday(selectedDate) && <TodayButton />}`
**Warning signs:** Clicking "Today" when already on today (no-op)

### Pitfall 4: Inconsistent Date Format in API
**What goes wrong:** PHP and JavaScript parse dates differently
**Why it happens:** Different default date format expectations
**How to avoid:** Always use ISO 8601 format (YYYY-MM-DD) for API parameters
**Warning signs:** Off-by-one day errors, especially around DST changes

## Code Examples

Verified patterns from the existing codebase:

### Date Formatting Pattern (from Dashboard.jsx, line 89)
```javascript
// Already in use for reminder dates
import { format } from 'date-fns';

// Format for display
format(new Date(reminder.next_occurrence), 'MMMM d, yyyy');

// Format for API calls (recommended)
format(selectedDate, 'yyyy-MM-dd');
```

### Widget Header Pattern (from Dashboard.jsx, line 629-635)
```javascript
// Current meetings card header
<div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
  <h2 className="font-semibold flex items-center dark:text-gray-50">
    <CalendarClock className="w-5 h-5 mr-2 text-gray-500 dark:text-gray-400" />
    Today's meetings
  </h2>
  <Link to="/settings?tab=connections&subtab=calendars" className="...">
    Settings <ArrowRight className="w-4 h-4 ml-1" />
  </Link>
</div>
```

### API Endpoint with Date Parameter (from class-rest-calendar.php)
```php
// Existing today-meetings endpoint (lines 1191-1255)
// Uses current_time('Y-m-d') for date range
$today_start = current_time( 'Y-m-d' ) . ' 00:00:00';
$today_end   = current_time( 'Y-m-d' ) . ' 23:59:59';

// Modification needed: accept date parameter
$date_param = $request->get_param('date');
$target_date = $date_param ? sanitize_text_field($date_param) : current_time('Y-m-d');
```

### Button Styling Pattern (from various components)
```javascript
// Small icon button pattern
<button
  onClick={handlePrevDay}
  className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
             hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
  aria-label="Previous day"
>
  <ChevronLeft className="w-4 h-4" />
</button>

// Text button pattern
<button
  onClick={handleToday}
  className="px-2 py-1 text-xs font-medium text-accent-600 dark:text-accent-400
             hover:bg-accent-50 dark:hover:bg-accent-900/30 rounded transition-colors"
>
  Today
</button>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| moment.js | date-fns | Years ago | Already using date-fns v3 |
| Manual date math | date-fns helpers | Always | Prevents bugs |

**Deprecated/outdated:**
- moment.js: Heavy, mutable API, not tree-shakeable - use date-fns instead (already in use)

## Open Questions

Things that couldn't be fully resolved:

1. **Date range limits**
   - What we know: Users can navigate to any date
   - What's unclear: Should there be limits (e.g., only show future 30 days, past 90 days)?
   - Recommendation: No limits initially; calendar sync already limits data (sync_from_days/sync_to_days settings)

2. **Empty state messaging**
   - What we know: "No meetings scheduled for today" is current message
   - What's unclear: Should message change for past vs future dates?
   - Recommendation: Update message to include date: "No meetings on {date}"

## Sources

### Primary (HIGH confidence)
- Codebase analysis: `/Users/joostdevalk/Code/caelis/src/pages/Dashboard.jsx` - existing widget implementation
- Codebase analysis: `/Users/joostdevalk/Code/caelis/src/hooks/useMeetings.js` - existing hook patterns
- Codebase analysis: `/Users/joostdevalk/Code/caelis/includes/class-rest-calendar.php` - API endpoint

### Secondary (MEDIUM confidence)
- [date-fns official documentation](https://date-fns.org/) - date manipulation functions
- [date-fns addDays](https://snyk.io/advisor/npm-package/date-fns/functions/date-fns.addDays) - addDays usage
- [date-fns subDays](https://snyk.io/advisor/npm-package/date-fns/functions/date-fns.subDays) - subDays usage

### Tertiary (LOW confidence)
- None - all patterns verified against existing codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - using existing libraries (date-fns, lucide-react) already in package.json
- Architecture: HIGH - straightforward extension of existing patterns
- Pitfalls: HIGH - based on common date handling issues and codebase patterns

**Research date:** 2026-01-17
**Valid until:** 60 days (stable patterns, no external API dependencies)
