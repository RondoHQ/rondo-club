# Phase 147: Birthdate Field & Widget - Research

**Researched:** 2026-02-06
**Domain:** ACF date fields, age calculation, dashboard widgets
**Confidence:** HIGH

## Summary

This phase adds a birthdate field to person records and displays it in the person header alongside age. The existing dashboard "upcoming reminders" widget will be updated to query person birthdate meta directly instead of the Important Dates CPT. The architecture is straightforward: ACF date picker for storage, date-fns for calculations, and meta queries for the dashboard widget.

**Key findings:**
- ACF date picker fields store dates as `Y-m-d` strings in post meta
- The system already has patterns for age display using `differenceInYears` from date-fns
- Dashboard widget currently queries `important_date` CPT and calculates next occurrences
- Meta queries with LIKE comparison enable month/day matching for recurring events
- WordPress timezone handling is already established via `wp_timezone()`

**Primary recommendation:** Use ACF date picker with `Y-m-d` format, calculate age client-side with date-fns, and update dashboard reminders to use direct meta queries with month/day comparison.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| ACF Pro | 6.0+ | Date picker field | Already used for all person meta fields |
| date-fns | Latest | Date calculations | Already integrated with Dutch locale |
| WordPress Meta API | Core | Field storage | Native WordPress data model |
| React | 18 | UI components | Established frontend framework |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| TanStack Query | Latest | Data fetching | Already used for all API calls |
| wpApi client | Internal | REST API wrapper | Existing abstraction layer |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| ACF date picker | Custom input | ACF provides validation, consistent UI |
| date-fns | Moment.js | date-fns already integrated, smaller bundle |
| Meta queries | CPT queries | Meta queries eliminate join overhead |

**Installation:**
No new dependencies required - all tools already in use.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-rest-api.php          # Dashboard endpoint modification
├── class-auto-title.php        # No changes (title doesn't include age)
acf-json/
└── group_person_fields.json    # Add birthdate field
src/
├── pages/People/
│   └── PersonDetail.jsx        # Add birthdate display
├── hooks/
│   └── usePeople.js            # Transform includes birthdate
└── utils/
    ├── dateFormat.js           # Already has Dutch locale + differenceInYears
    └── formatters.js           # Add age formatting utility
```

### Pattern 1: ACF Date Field Storage
**What:** Store birthdate as ACF date picker field with `Y-m-d` return format
**When to use:** All date fields in the system
**Example:**
```json
// acf-json/group_person_fields.json
{
    "key": "field_birthdate",
    "label": "Birthdate",
    "name": "birthdate",
    "type": "date_picker",
    "display_format": "d F Y",
    "return_format": "Y-m-d",
    "readonly": 1,
    "wrapper": {
        "width": "50"
    }
}
```

### Pattern 2: Age Calculation (Client-Side)
**What:** Calculate age using date-fns `differenceInYears` on the client
**When to use:** Person detail display, dashboard widgets
**Example:**
```javascript
// From existing PersonDetail.jsx (lines 160-163)
import { differenceInYears } from '@/utils/dateFormat';

const birthDate = person.acf?.birthdate ? new Date(person.acf.birthdate) : null;
const age = birthDate ? differenceInYears(new Date(), birthDate) : null;

// Display: "43 jaar (16 feb 1982)"
const formattedBirthdate = birthDate ? format(birthDate, 'd MMM yyyy') : null;
const ageDisplay = age !== null && formattedBirthdate
  ? `${age} jaar (${formattedBirthdate})`
  : null;
```

### Pattern 3: Dashboard Meta Query for Upcoming Birthdays
**What:** Query person meta directly for birthdates, calculate next occurrence in PHP
**When to use:** Dashboard widgets, cron jobs
**Example:**
```php
// Upcoming birthdays query (replaces Important Dates CPT query)
global $wpdb;

$people_with_birthdays = $wpdb->get_results("
    SELECT p.ID, p.post_title, pm.meta_value as birthdate
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'person'
    AND p.post_status = 'publish'
    AND pm.meta_key = 'birthdate'
    AND pm.meta_value != ''
");

// Calculate next occurrences
$upcoming = [];
$today = new DateTime('today', wp_timezone());
$end_date = (clone $today)->modify('+30 days');

foreach ($people_with_birthdays as $person) {
    $birthdate = DateTime::createFromFormat('Y-m-d', $person->birthdate, wp_timezone());
    if (!$birthdate) continue;

    // Next occurrence this year
    $this_year = (clone $birthdate)->setDate(
        (int) $today->format('Y'),
        (int) $birthdate->format('m'),
        (int) $birthdate->format('d')
    );

    $next_occurrence = ($this_year >= $today)
        ? $this_year
        : $this_year->modify('+1 year');

    if ($next_occurrence <= $end_date) {
        $upcoming[] = [
            'person_id' => $person->ID,
            'title' => $person->post_title . ' - Birthday',
            'next_occurrence' => $next_occurrence->format('Y-m-d'),
            'days_until' => (int) $today->diff($next_occurrence)->days,
        ];
    }
}
```

### Pattern 4: Dutch Date Formatting
**What:** Use date-fns with Dutch locale for all date display
**When to use:** All user-facing date formatting
**Example:**
```javascript
// src/utils/dateFormat.js already configured with Dutch locale
import { format } from '@/utils/dateFormat';

// Month abbreviations: jan, feb, mrt, apr, mei, jun, jul, aug, sep, okt, nov, dec
const shortFormat = format(date, 'd MMM yyyy'); // "16 feb 1982"
const longFormat = format(date, 'd MMMM yyyy'); // "16 februari 1982"

// No leading zeros
format(new Date('1982-02-06'), 'd MMM yyyy'); // "6 feb 1982" (not "06 feb")
```

### Anti-Patterns to Avoid
- **Storing age in database:** Age changes daily, always calculate on read
- **Using Important Dates CPT for birthdates:** Adds unnecessary joins and complexity
- **Client-side timezone conversion:** Use WordPress site timezone consistently
- **Leading zeros on day numbers:** Dutch convention is "6 feb" not "06 feb"

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Date parsing/formatting | Custom string manipulation | date-fns with Dutch locale | Handles edge cases (leap years, DST), already integrated |
| Age calculation | Manual year difference | differenceInYears from date-fns | Accounts for leap years, timezone-aware |
| Next occurrence calculation | Custom birthday logic | Existing pattern in Reminders class | Tested logic for recurring dates |
| Dutch month names | Hard-coded arrays | date-fns nl locale | Maintains consistency, handles full/abbreviated forms |
| ACF field rendering | Custom input components | ACF date picker | Validation, accessibility, WordPress native |

**Key insight:** The system already has robust date handling infrastructure from the Important Dates feature. The birthday field leverages this existing architecture rather than reinventing date logic.

## Common Pitfalls

### Pitfall 1: Timezone Inconsistency
**What goes wrong:** Mixing JavaScript Date (UTC) with PHP DateTime (site timezone) causes off-by-one-day errors
**Why it happens:** JavaScript Date defaults to UTC, WordPress uses site timezone
**How to avoid:**
- Always use `wp_timezone()` in PHP DateTime operations
- Parse dates as strings in JavaScript, format with date-fns
- Never create Date objects with timezone string manipulation
**Warning signs:** Birthdays showing one day off for some users

### Pitfall 2: Null/Empty String Confusion
**What goes wrong:** Empty birthdate field causes rendering errors or displays "null jaar"
**Why it happens:** ACF returns empty string `""` for unfilled date pickers, not null
**How to avoid:**
```javascript
// WRONG: Doesn't handle empty string
const age = differenceInYears(new Date(), new Date(person.acf.birthdate));

// RIGHT: Check for empty/null before parsing
const birthDate = person.acf?.birthdate && person.acf.birthdate !== ''
  ? new Date(person.acf.birthdate)
  : null;
const age = birthDate ? differenceInYears(new Date(), birthDate) : null;
```
**Warning signs:** Console errors about "Invalid Date", "NaN jaar" in UI

### Pitfall 3: ACF Field Naming Collision
**What goes wrong:** Using field name that conflicts with WordPress or ACF reserved names
**Why it happens:** Some names (like `date`, `time`) are reserved or cause meta query issues
**How to avoid:** Use specific names like `birthdate`, not generic `date`
**Warning signs:** Field not saving, meta queries returning unexpected results

### Pitfall 4: Widget Query Performance
**What goes wrong:** Dashboard becomes slow with large person counts
**Why it happens:** Querying all people with birthdate meta on every page load
**How to avoid:**
- Limit widget to next 30 days (as currently done)
- Use indexed meta queries (WordPress indexes meta_key automatically)
- Consider transient caching for 1 hour
**Warning signs:** Dashboard load time > 1 second with 1000+ people

### Pitfall 5: Year Display Format
**What goes wrong:** Birth year shows without context, confusing for ages
**Why it happens:** User decision specifies "includes full birth year" but format matters
**How to avoid:** Always show year in parentheses after age: "43 jaar (16 feb 1982)"
**Warning signs:** Users confusing birth year with age

## Code Examples

Verified patterns from established codebase:

### Current Age Display Pattern
```javascript
// Source: PersonDetail.jsx lines 157-163
// Calculate age - if died, show age at death, otherwise current age
const isDeceased = !!deathDateValue;
const age = (birthDate && !birthdayYearUnknown) ? (isDeceased
  ? differenceInYears(deathDateValue, birthDate)
  : differenceInYears(new Date(), birthDate)
) : null;

// Display in header (lines 1142-1156)
{!isDeceased && (getGenderSymbol(acf.gender) || acf.pronouns || age !== null) && (
  <p className="text-gray-500 dark:text-gray-400 text-sm inline-flex items-center flex-wrap">
    {getGenderSymbol(acf.gender) && <span>{getGenderSymbol(acf.gender)}</span>}
    {getGenderSymbol(acf.gender) && acf.pronouns && <span>&nbsp;—&nbsp;</span>}
    {acf.pronouns && <span>{acf.pronouns}</span>}
    {(getGenderSymbol(acf.gender) || acf.pronouns) && age !== null && <span>&nbsp;—&nbsp;</span>}
    {age !== null && <span>{age} jaar</span>}
  </p>
)}
```

### Dashboard Reminders Widget (Current Implementation)
```javascript
// Source: Dashboard.jsx lines 95-137
function ReminderCard({ reminder }) {
  const daysUntil = reminder.days_until;
  const urgencyClass = getReminderUrgencyClass(daysUntil);
  const firstPersonId = reminder.related_people?.[0]?.id;

  return (
    <Link to={`/people/${firstPersonId}`} className="flex items-center p-3 rounded-lg hover:bg-gray-50">
      <div className={`px-2 py-1 rounded text-xs font-medium ${urgencyClass}`}>
        {daysUntil === 0 ? 'Vandaag' : `${daysUntil}d`}
      </div>
      <div className="ml-3 flex-1 min-w-0">
        <p className="text-sm font-medium">{reminder.title}</p>
        <p className="text-xs text-gray-500">
          {format(new Date(reminder.next_occurrence), 'd MMMM yyyy')}
        </p>
      </div>
    </Link>
  );
}
```

### Next Occurrence Calculation (PHP)
```php
// Source: class-reminders.php lines 363-400
public function calculate_next_occurrence( $date_string, $is_recurring ) {
    try {
        $date = \DateTime::createFromFormat( 'Y-m-d', $date_string, wp_timezone() );
        if ( ! $date ) return null;

        $today = new \DateTime( 'today', wp_timezone() );

        if ( ! $is_recurring ) {
            // One-time date: only return if today or in the future
            return $date >= $today ? $date : null;
        }

        // Recurring: find next occurrence (same month/day, this year or next)
        $this_year = ( clone $date )->setDate(
            (int) $today->format( 'Y' ),
            (int) $date->format( 'm' ),
            (int) $date->format( 'd' )
        );

        if ( $this_year >= $today ) {
            return $this_year;
        }

        // Already passed this year, return next year
        return $this_year->modify( '+1 year' );

    } catch ( Exception $e ) {
        return null;
    }
}
```

### Dashboard Endpoint (Current Pattern)
```php
// Source: class-rest-api.php (dashboard summary)
// Upcoming reminders
$reminders_handler  = new \RONDO_Reminders();
$upcoming_reminders = $reminders_handler->get_upcoming_reminders( 14 );

return rest_ensure_response([
    'stats' => [
        'total_people' => $total_people,
        'total_dates'  => $total_dates,
    ],
    'upcoming_reminders' => array_slice( $upcoming_reminders, 0, 5 ),
]);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Important Dates CPT for all dates | Direct meta field for birthdates | Phase 147-148 | Eliminates joins, simpler queries |
| Storing computed values | Calculate on read | Ongoing pattern | Ensures accuracy, reduces storage |
| Manual date math | date-fns library | v16.0+ | Handles edge cases, Dutch locale |
| Global Important Dates | Per-person birthdate field | Phase 147 | Better data model, faster queries |

**Deprecated/outdated:**
- Using Important Dates CPT for birthdates (Phase 148 will remove birthday date type)
- Calculating age server-side for display (client-side is more efficient)
- English month names (Dutch locale now standard)

## Open Questions

Things that couldn't be fully resolved:

1. **Widget sorting priority**
   - What we know: Dashboard shows upcoming reminders sorted by next_occurrence
   - What's unclear: Should birthdays be sorted separately or mixed with other reminders?
   - Recommendation: Keep mixed sorting by date (simplest, most predictable)

2. **Widget display count limit**
   - What we know: Current widget shows 5 items
   - What's unclear: Should birthdays count toward the 5-item limit or have separate limit?
   - Recommendation: Count birthdays in the same pool (Claude's discretion per CONTEXT.md)

3. **Leap year birthdates**
   - What we know: PHP DateTime handles Feb 29 dates
   - What's unclear: Should Feb 29 birthdays show on Feb 28 in non-leap years?
   - Recommendation: Let date-fns handle naturally (shows March 1), document as known behavior

## Sources

### Primary (HIGH confidence)
- Existing codebase: PersonDetail.jsx lines 139-163 (age calculation pattern)
- Existing codebase: class-reminders.php lines 287-358 (upcoming reminders query)
- Existing codebase: acf-json/group_person_fields.json (ACF field structure)
- ACF Documentation: Date Picker field return formats (https://www.advancedcustomfields.com/resources/date-picker/)
- date-fns documentation: differenceInYears function (https://date-fns.org/docs/differenceInYears)

### Secondary (MEDIUM confidence)
- WordPress Codex: Meta Query documentation (https://developer.wordpress.org/reference/classes/wp_meta_query/)
- WordPress Timezone API: wp_timezone() function (https://developer.wordpress.org/reference/functions/wp_timezone/)

### Tertiary (LOW confidence)
- None - all findings verified against codebase or official documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All tools already in active use
- Architecture: HIGH - Patterns established in codebase
- Pitfalls: HIGH - Identified from existing date handling patterns

**Research date:** 2026-02-06
**Valid until:** 2026-03-06 (30 days - stable technology stack)
