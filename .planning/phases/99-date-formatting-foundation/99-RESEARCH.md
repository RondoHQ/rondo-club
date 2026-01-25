# Phase 99: Date Formatting Foundation - Research

**Researched:** 2026-01-25
**Domain:** Date formatting and localization with date-fns
**Confidence:** HIGH

## Summary

This phase involves implementing Dutch (nl) locale throughout the Stadion CRM application using date-fns v3.2.0 (already installed). The codebase currently uses date-fns extensively but without any locale configuration, resulting in English date displays. The task is to implement consistent Dutch formatting across all 30+ date formatting call sites.

The standard approach is to:
1. Create a centralized utility module that imports the Dutch locale
2. Export wrapper functions that pre-configure the locale parameter
3. Replace all existing date-fns direct imports with the wrapper functions
4. Ensure all date-fns functions that support locales use the Dutch locale consistently

**Primary recommendation:** Create a `src/utils/dateFormat.js` utility module with locale-aware wrapper functions. Replace all direct date-fns imports throughout the codebase with these wrappers. This centralizes locale configuration and prevents future mistakes where developers forget to pass the locale parameter.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| date-fns | 3.2.0 | Date formatting and manipulation | Already in use, modern alternative to Moment.js, excellent tree-shaking, strong TypeScript support |
| date-fns/locale/nl | 3.2.0 | Dutch locale for date-fns | Official locale, maintained by date-fns team, complete translations |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| N/A | - | - | No additional libraries needed |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| date-fns | Intl.DateTimeFormat | Native browser API, but less flexible formatting, no relative dates like "3 dagen geleden" |
| date-fns | Moment.js | Deprecated, much larger bundle size (~67KB vs ~15KB), no tree-shaking |
| date-fns | Luxon | Similar features but smaller ecosystem, less React integration examples |
| date-fns | Day.js | Smaller but less comprehensive, plugin system can be fragile |

**Installation:**
```bash
# No installation needed - date-fns@3.2.0 already installed
# Dutch locale is included in date-fns package
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── utils/
│   ├── dateFormat.js        # Centralized date formatting utilities
│   └── timeline.js           # Existing file to be updated
├── pages/                    # Update all date formatting calls
├── components/               # Update all date formatting calls
└── hooks/                    # Update all date formatting calls
```

### Pattern 1: Centralized Locale-Aware Wrappers
**What:** Create utility functions that wrap date-fns functions with pre-configured Dutch locale
**When to use:** For all date formatting throughout the application
**Example:**
```javascript
// src/utils/dateFormat.js
import { format as dateFnsFormat, formatDistance as dateFnsFormatDistance,
         formatDistanceToNow as dateFnsFormatDistanceToNow,
         formatRelative as dateFnsFormatRelative,
         isToday, isYesterday, isThisWeek, parseISO } from 'date-fns';
import { nl } from 'date-fns/locale';

// Configuration object with Dutch locale
const dateConfig = { locale: nl };

// Wrapper functions that always use Dutch locale
export function format(date, formatStr, options = {}) {
  return dateFnsFormat(date, formatStr, { ...dateConfig, ...options });
}

export function formatDistance(date, baseDate, options = {}) {
  return dateFnsFormatDistance(date, baseDate, { ...dateConfig, ...options });
}

export function formatDistanceToNow(date, options = {}) {
  return dateFnsFormatDistanceToNow(date, { ...dateConfig, ...options });
}

export function formatRelative(date, baseDate, options = {}) {
  return dateFnsFormatRelative(date, baseDate, { ...dateConfig, ...options });
}

// Re-export non-locale functions for convenience
export { isToday, isYesterday, isThisWeek, parseISO, addDays, subDays } from 'date-fns';
```

### Pattern 2: Update Import Statements
**What:** Replace all direct date-fns imports with wrapper imports
**When to use:** At every existing date-fns import site (17 files identified)
**Example:**
```javascript
// BEFORE
import { format, formatDistanceToNow, isToday } from 'date-fns';

// AFTER
import { format, formatDistanceToNow, isToday } from '@/utils/dateFormat';
```

### Pattern 3: Dutch Relative Date Labels
**What:** Use formatRelative for context-aware relative dates
**When to use:** Timeline views, activity feeds, anywhere showing recent dates
**Example:**
```javascript
import { formatRelative } from '@/utils/dateFormat';

// Returns Dutch labels:
// "vandaag om 14:30"    (today at 2:30 PM)
// "gisteren om 10:15"   (yesterday at 10:15 AM)
// "morgen om 09:00"     (tomorrow at 9:00 AM)
// "afgelopen maandag om 16:45" (last Monday at 4:45 PM)
const relativeDate = formatRelative(new Date(2026, 0, 24), new Date());
```

### Pattern 4: Dutch Distance Formatting
**What:** Use formatDistanceToNow with addSuffix for "ago" style dates
**When to use:** Activity timestamps, last updated times
**Example:**
```javascript
import { formatDistanceToNow } from '@/utils/dateFormat';

// Returns Dutch labels:
// "3 dagen geleden"     (3 days ago)
// "over 2 uur"          (in 2 hours)
// "ongeveer 1 jaar geleden" (about 1 year ago)
const timeAgo = formatDistanceToNow(date, { addSuffix: true });
```

### Anti-Patterns to Avoid
- **Direct date-fns imports:** Don't import format, formatDistance, etc. directly from 'date-fns' - always use the wrapper
- **Manual locale passing:** Don't pass `{ locale: nl }` manually at call sites - let the wrapper handle it
- **Inconsistent date labels:** Don't mix English relative dates ("Yesterday") with Dutch formatted dates - use consistent Dutch throughout
- **Forgetting function support:** Not all date-fns functions support locale (e.g., isToday, parseISO don't need it) - only wrap functions that accept locale parameter

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Dutch month/day names | Manual translation arrays | date-fns nl locale | Handles capitalization, abbreviations, genitive forms automatically |
| Relative date labels | Custom "if yesterday" logic | formatRelative with nl locale | Handles edge cases, week boundaries, proper Dutch phrasing |
| "X days ago" formatting | Manual date math + pluralization | formatDistanceToNow with nl locale | Handles all units (seconds to years), proper Dutch plurals, edge cases |
| Date parsing | Custom regex parsers | parseISO from date-fns | Robust ISO 8601 parsing, timezone aware |

**Key insight:** The Dutch locale includes sophisticated rules for date formatting (e.g., "afgelopen" vs "vorige", proper use of "om" for times) that are complex to replicate manually. The locale has been maintained and refined over years.

## Common Pitfalls

### Pitfall 1: Forgetting to Import Locale-Aware Wrapper
**What goes wrong:** Developer adds new date formatting code and imports directly from 'date-fns', bypassing the Dutch locale configuration
**Why it happens:** Muscle memory, autocomplete suggestions, copying old code patterns
**How to avoid:**
- Use path alias `@/utils/dateFormat` consistently
- Add ESLint rule to prevent direct date-fns imports (if desired)
- Code review checklist item
**Warning signs:** English month names or "Yesterday" appearing in UI

### Pitfall 2: Token Confusion (YYYY vs yyyy)
**What goes wrong:** Using YYYY (week-numbering year) instead of yyyy (calendar year), causing dates near year boundaries to show wrong year
**Why it happens:** Confusion with other date libraries or Java's SimpleDateFormat conventions
**How to avoid:**
- Use lowercase tokens: yyyy, MM, dd (not YYYY, DD)
- date-fns v3 requires explicit opt-in for legacy tokens via options
- Reference date-fns format documentation for token meanings
**Warning signs:** Dates in early January showing previous year, or late December showing next year

### Pitfall 3: Not Handling Time Display Inconsistencies
**What goes wrong:** Mixing 12-hour (h:mm a) and 24-hour (HH:mm) formats, or inconsistent between components
**Why it happens:** Copy-paste from different sources, unclear time format standard
**How to avoid:**
- Standardize on 24-hour format (HH:mm) which is Dutch convention
- Document time format patterns in dateFormat.js
- Current codebase uses 'HH:mm' correctly in Dashboard.jsx
**Warning signs:** "3:00 PM" appearing alongside "15:00" in different parts of UI

### Pitfall 4: formatRelative vs formatDistanceToNow Confusion
**What goes wrong:** Using wrong function for the use case, leading to awkward phrasing
**Why it happens:** Similar-sounding functions with overlapping use cases
**How to avoid:**
- **formatRelative**: Use for dates with context ("gisteren om 14:00") - good for timelines with time of day
- **formatDistanceToNow**: Use for elapsed time ("3 dagen geleden") - good for "last updated" displays
- Document examples in dateFormat.js comments
**Warning signs:** Seeing "over 1 dag" when you want "morgen om 10:00"

### Pitfall 5: Missing Locale on New Imports
**What goes wrong:** Adding new date-fns functions (e.g., differenceInDays) and importing directly, bypassing locale wrapper
**Why it happens:** Not all date-fns functions need locale, developer doesn't know which do
**How to avoid:**
- Re-export all commonly used date-fns functions from dateFormat.js (including non-locale ones)
- Functions that DON'T need locale: parseISO, isToday, isYesterday, differenceInDays, addDays, subDays
- Functions that DO need locale: format, formatDistance, formatDistanceToNow, formatRelative, formatDistanceStrict
**Warning signs:** Import statements from 'date-fns' appearing in new code

## Code Examples

Verified patterns from official sources:

### Current Timeline Formatting (needs update)
```javascript
// Source: src/utils/timeline.js lines 91-114
// Currently uses English formatting
export function formatTimelineDate(dateString) {
  if (!dateString) return '';

  try {
    const date = parseISO(dateString);
    const hasTime = dateString.includes('T') && dateString.length > 10;
    const timeFormat = hasTime ? ' at HH:mm' : '';

    if (isToday(date)) {
      return formatDistanceToNow(date, { addSuffix: true }); // Returns English "3 hours ago"
    }

    if (isYesterday(date)) {
      return hasTime ? format(date, `'Yesterday' 'at' HH:mm`) : 'Yesterday'; // English
    }

    return format(date, hasTime ? `MMM d, yyyy 'at' HH:mm` : 'MMM d, yyyy'); // English months
  } catch (error) {
    return dateString;
  }
}
```

### Updated Dutch Timeline Formatting
```javascript
// Source: Updated src/utils/timeline.js with Dutch locale
import { format, formatDistanceToNow, isToday, isYesterday, parseISO } from '@/utils/dateFormat';

export function formatTimelineDate(dateString) {
  if (!dateString) return '';

  try {
    const date = parseISO(dateString);
    const hasTime = dateString.includes('T') && dateString.length > 10;

    if (isToday(date)) {
      return formatDistanceToNow(date, { addSuffix: true }); // "3 uur geleden"
    }

    if (isYesterday(date)) {
      return hasTime ? format(date, `'gisteren om' HH:mm`) : 'gisteren'; // Dutch
    }

    return format(date, hasTime ? `d MMM yyyy 'om' HH:mm` : 'd MMM yyyy'); // "24 jan 2026"
  } catch (error) {
    return dateString;
  }
}
```

### Dashboard Reminder Card (needs update)
```javascript
// Source: src/pages/Dashboard.jsx line 89
// Currently: {format(new Date(reminder.next_occurrence), 'MMMM d, yyyy')}
// Returns: "January 24, 2026" (English)

// Updated with Dutch locale:
{format(new Date(reminder.next_occurrence), 'd MMMM yyyy')}
// Returns: "24 januari 2026" (Dutch)
```

### Date List Month Headers (needs update)
```javascript
// Source: src/pages/Dates/DatesList.jsx line 118
// Currently: const month = format(new Date(dayKey), 'MMMM yyyy');
// Returns: "January 2026" (English)

// Updated with Dutch locale:
const month = format(new Date(dayKey), 'MMMM yyyy');
// Returns: "januari 2026" (Dutch - note lowercase, which is Dutch convention)
```

### Meeting Time Display (already correct format)
```javascript
// Source: src/pages/Dashboard.jsx lines 246-247
const formattedStartTime = format(startTime, 'HH:mm'); // Good - 24-hour format
const formattedEndTime = format(endTime, 'HH:mm');
// Just needs to import from @/utils/dateFormat instead of date-fns
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Moment.js | date-fns v3 | 2020+ | Smaller bundles (~15KB vs ~67KB), better tree-shaking, immutable API |
| Global locale config | Explicit locale parameter | date-fns v1+ | More flexible but requires wrapper pattern for consistency |
| Default imports (v2) | Named imports (v3) | date-fns v3.0 (2023) | Breaking change in import syntax: `import { nl }` not `import nl` |
| Manual "X ago" logic | formatDistanceToNow | Always available | Proper pluralization, localization, edge cases |

**Deprecated/outdated:**
- **Moment.js**: Officially in maintenance mode, team recommends date-fns or Luxon for new projects
- **date-fns v2 import syntax**: Default imports `import nl from 'date-fns/locale/nl'` changed to named imports in v3
- **Format tokens DD, YYYY**: These represent day-of-year and week-year respectively, often mistaken for date and year

## Open Questions

Things that couldn't be fully resolved:

1. **Capitalization conventions**
   - What we know: Dutch typically uses lowercase for month names ("januari") except at sentence start
   - What's unclear: Should month names in date headers be capitalized ("Januari 2026" vs "januari 2026")?
   - Recommendation: Use lowercase per date-fns nl locale default, capitalize only if design/UX specifically requires it using CSS text-transform if needed

2. **Date format order preferences**
   - What we know: European format is day-month-year (24 januari 2026)
   - What's unclear: User preference for different formats (24-01-2026 vs 24 jan 2026 vs 24 januari 2026)
   - Recommendation: Start with "d MMMM yyyy" (24 januari 2026) for full dates, "d MMM yyyy" (24 jan 2026) for compact displays. Can add user preferences in future phase if needed

3. **Week start day (Sunday vs Monday)**
   - What we know: Dutch convention is Monday as first day of week (matches date-fns nl locale)
   - What's unclear: Whether calendar integrations (Google Calendar, Outlook) respect this
   - Recommendation: date-fns nl locale defaults to Monday as week start, which is correct for Netherlands. No action needed unless calendar widget added

## Sources

### Primary (HIGH confidence)
- [date-fns official i18n documentation](https://github.com/date-fns/date-fns/blob/main/docs/i18n.md) - Comprehensive locale usage guide
- [date-fns nl locale source code](https://github.com/date-fns/date-fns/tree/main/src/locale/nl) - Official Dutch locale implementation
- Raw source: formatRelative translations (vandaag, gisteren, morgen, afgelopen)
- Raw source: formatDistance translations (seconden, minuten, uur, dagen, weken, maanden, jaar with pluralization)

### Secondary (MEDIUM confidence)
- [React automatic date formatting with i18next + date-fns](https://dev.to/ekeijl/react-automatic-date-formatting-in-translations-i18next-date-fns-8df) - Centralized locale pattern
- [MUI X Date Pickers localization guide](https://mui.com/x/react-date-pickers/adapters-locale/) - date-fns v3 integration patterns
- [date-fns GitHub discussions](https://github.com/date-fns/date-fns/discussions/2724) - Locale availability and usage

### Tertiary (LOW confidence)
- [date-fns tree-shaking discussions](https://github.com/date-fns/date-fns/discussions/2521) - Bundle size optimization, but mostly v2 content

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - date-fns 3.2.0 already installed, nl locale officially maintained and complete
- Architecture: HIGH - Centralized wrapper pattern is documented best practice, verified in official docs and community articles
- Pitfalls: HIGH - Based on official changelog, GitHub issues, and common mistake patterns

**Research date:** 2026-01-25
**Valid until:** ~60 days (stable library, locale rarely changes, but check for date-fns patch updates)
