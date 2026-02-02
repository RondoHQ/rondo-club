# Phase 130: Frontend Season Selector - Research

**Researched:** 2026-02-02
**Domain:** React controlled components, TanStack Query state management, conditional table rendering, Tailwind CSS UI patterns
**Confidence:** HIGH

## Summary

This phase implements a season selector dropdown UI in the existing ContributieList React component that switches between current and forecast data views. The research focused on React controlled component patterns for dropdowns, TanStack Query query key management for conditional API parameters, and Tailwind CSS styling patterns for visual distinction.

The standard approach uses React useState for dropdown state management, passes the forecast parameter through TanStack Query's queryKey to enable automatic cache separation, and conditionally renders table columns based on the selected season mode. The existing useFeeList hook accepts params, making integration straightforward without modifying the API client layer.

Phase 129 (backend) already implemented the `/stadion/v1/fees?forecast=true` endpoint that returns next season calculations with nikki fields excluded, so the frontend implementation is purely UI state management and conditional rendering.

**Primary recommendation:** Add controlled select dropdown with useState, pass `{ forecast: true }` through existing useFeeList params, conditionally render columns based on state, and add visual badge indicator for forecast mode using existing Tailwind patterns.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React | 18 | Controlled components, useState hook | Already used throughout frontend, native state management |
| TanStack Query | Latest | Server state management, automatic cache separation | Already integrated, queryKey changes trigger refetch |
| Tailwind CSS | 3.4 | UI styling, responsive design, dark mode | Project standard, extensive utility classes available |
| Lucide React | Latest | Icon library for visual indicators | Already used throughout app for consistent iconography |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Native `<select>` | HTML5 | Dropdown component | Simple use case, no complex features needed |
| React hooks | 18 | useState, useCallback for state management | Standard React patterns for component state |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Native select | React Select library | More features but unnecessary overhead for 2-option toggle |
| useState | URL search params | Would enable deep linking but adds complexity for simple toggle |
| Separate queries | Single query with parameter | More explicit but duplicates query management logic |
| Custom dropdown | Headless UI Listbox | Better accessibility but overkill for simple season toggle |

**Installation:**
No new dependencies required - uses existing React 18, TanStack Query, Tailwind CSS 3.4, and Lucide React.

## Architecture Patterns

### Recommended Project Structure
```
src/pages/Contributie/
├── ContributieList.jsx    # Main component - add dropdown and conditional rendering
```

No new files needed - all changes in existing ContributieList component.

### Pattern 1: Controlled Dropdown with useState
**What:** Manage dropdown selection with React controlled component pattern
**When to use:** For simple dropdowns with local state that drives API parameters
**Example:**
```jsx
// Source: React official docs - <select> controlled components
const [isForecast, setIsForecast] = useState(false);

<select
  value={isForecast ? 'forecast' : 'current'}
  onChange={(e) => setIsForecast(e.target.value === 'forecast')}
  className="form-select..."
>
  <option value="current">2025-2026 (huidig)</option>
  <option value="forecast">2026-2027 (prognose)</option>
</select>
```

**Why this works:**
- Every controlled select needs value + onChange paired together
- State update triggers re-render with new query parameter
- Boolean state is simpler than string matching in conditional logic

### Pattern 2: TanStack Query Parameter Passing
**What:** Pass forecast parameter through existing useFeeList params
**When to use:** When API endpoint accepts optional parameters that change data shape
**Example:**
```jsx
// Source: Existing useFeeList hook pattern (src/hooks/useFees.js:19-27)
const [isForecast, setIsForecast] = useState(false);

// Pass params to hook - query key automatically includes params
const { data, isLoading, error } = useFeeList(
  isForecast ? { forecast: true } : {}
);

// TanStack Query automatically:
// - Creates separate cache entries for forecast vs current
// - Refetches when isForecast changes (queryKey changes)
// - Maintains previous data during loading (no flash)
```

**Why this works:**
- useFeeList already accepts params object
- TanStack Query uses params in queryKey (feeKeys.list(params))
- Changing params triggers automatic refetch
- Separate cache entries prevent data mixing

### Pattern 3: Conditional Table Column Rendering
**What:** Show/hide columns by conditionally rendering `<th>` and `<td>` elements
**When to use:** When column visibility depends on data shape (forecast excludes nikki fields)
**Example:**
```jsx
// Source: React conditional rendering best practices
// Header row
<tr>
  <SortableHeader label="Voornaam" columnId="first_name" {...sortProps} />
  <SortableHeader label="Achternaam" columnId="last_name" {...sortProps} />
  {/* ... other columns ... */}
  <SortableHeader label="Bedrag" columnId="final_fee" {...sortProps} />

  {/* Conditionally render Nikki and Saldo columns */}
  {!isForecast && (
    <>
      <SortableHeader label="Nikki" columnId="nikki_total" {...sortProps} />
      <SortableHeader label="Saldo" columnId="nikki_saldo" {...sortProps} />
    </>
  )}
</tr>

// Data rows - must match header structure
<tr>
  <td>{member.first_name}</td>
  <td>{member.last_name}</td>
  {/* ... other columns ... */}
  <td>{formatCurrency(member.final_fee)}</td>

  {/* Same conditional rendering */}
  {!isForecast && (
    <>
      <td>{formatCurrency(member.nikki_total)}</td>
      <td>{formatCurrency(member.nikki_saldo)}</td>
    </>
  )}
</tr>
```

**Why this works:**
- Columns not rendered = instant hide, table reflows automatically
- No CSS display:none - element doesn't exist in DOM
- Remaining columns expand to fill space (CSS table auto-layout)
- Symmetrical behavior on toggle - consistent structure

**Performance note:** Conditional rendering is more performant than CSS hiding because:
- Fewer DOM nodes to maintain
- Table layout engine recalculates width distribution
- No style computation for hidden elements

### Pattern 4: Visual Forecast Indicator
**What:** Add badge or banner to clearly indicate forecast mode
**When to use:** When data view changes significantly and users need clear feedback
**Example:**
```jsx
// Source: Tailwind CSS badge patterns + existing category badge styling
{isForecast && (
  <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300">
    <TrendingUp className="w-4 h-4" />
    <span className="font-medium">Prognose</span>
    <span className="text-blue-600 dark:text-blue-400">
      - gebaseerd op huidige ledenstand
    </span>
  </div>
)}
```

**Placement options:**
- Inline with season dropdown and member count
- As banner above table
- As badge next to page title

### Pattern 5: Season Dropdown Styling
**What:** Style dropdown to match existing UI patterns in ContributieList
**When to use:** Maintaining visual consistency with filter buttons and controls
**Example:**
```jsx
// Source: Existing ContributieList filter buttons (lines 389-418)
<select
  value={isForecast ? 'forecast' : 'current'}
  onChange={(e) => setIsForecast(e.target.value === 'forecast')}
  className="btn-secondary appearance-none pr-8 bg-no-repeat bg-right"
  style={{
    backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
    backgroundSize: '1.25rem',
    paddingRight: '2rem',
  }}
>
  <option value="current">{data?.season || '2025-2026'} (huidig)</option>
  <option value="forecast">
    {/* Calculate next season from current */}
    {data?.season ? getNextSeasonLabel(data.season) : '2026-2027'} (prognose)
  </option>
</select>
```

**Helper function:**
```jsx
// Extract from backend pattern (129-RESEARCH.md:235-247)
const getNextSeasonLabel = (currentSeason) => {
  const startYear = parseInt(currentSeason.substring(0, 4));
  const nextStartYear = startYear + 1;
  return `${nextStartYear}-${nextStartYear + 1}`;
};
```

### Anti-Patterns to Avoid
- **Using URL search params for simple toggle:** Adds complexity without benefit, breaks back button expectations
- **CSS display:none for columns:** Prevents proper table reflow, columns don't expand to fill space
- **Separate API client methods:** useFeeList already accepts params, don't create `useForecastList()`
- **Manual cache management:** TanStack Query handles cache separation via queryKey automatically
- **Loading states without previous data:** TanStack Query's `keepPreviousData` prevents content flash during season toggle
- **Hardcoded season labels:** Use data.season from API response for current season label

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Custom dropdown component | Styled div with click handlers | Native `<select>` with Tailwind styling | Accessibility (keyboard nav, screen readers) built-in, mobile-optimized |
| Query parameter management | Manual fetch on toggle | TanStack Query with params in queryKey | Automatic cache separation, loading states, error handling |
| Season label calculation | Manual year extraction and formatting | Backend data.season field + simple arithmetic | Backend already calculates, don't duplicate logic |
| Loading indicators | Custom spinner logic | TanStack Query isLoading state | Handles race conditions, previous data display |
| Column width management | Manual CSS width calculations | CSS table auto-layout | Browser automatically distributes width when columns hide/show |
| Dark mode styling | Custom theme logic | Tailwind dark: variants | Consistent with existing app theme system |

**Key insight:** The existing infrastructure (useFeeList params, TanStack Query cache management, Tailwind utilities) handles all complexity. This phase is purely UI state wiring - no custom logic needed.

## Common Pitfalls

### Pitfall 1: Forgetting to Pair value with onChange
**What goes wrong:** Select renders but doesn't respond to user interaction
**Why it happens:** React controlled components require both value and onChange
**How to avoid:** Always pair `value={state}` with `onChange={(e) => setState(e.target.value)}`
**Warning signs:** Clicking dropdown options doesn't change selection, console warnings about controlled components

### Pitfall 2: Inconsistent Column Rendering Between Header and Rows
**What goes wrong:** Table columns misalign - headers don't match data cells
**Why it happens:** Conditional rendering logic differs between `<thead>` and `<tbody>`
**How to avoid:** Use identical conditional (`{!isForecast && ...}`) in header row and every data row
**Warning signs:** Columns shift when toggling forecast, data appears under wrong headers

### Pitfall 3: Not Excluding Nikki Columns from Totals Footer
**What goes wrong:** Footer row has empty cells for Nikki/Saldo in forecast mode
**Why it happens:** Forgetting to conditionally render footer cells matching header/body
**How to avoid:** Apply same `{!isForecast && ...}` to footer row `<td>` elements
**Warning signs:** Footer has misaligned or empty cells in forecast view

### Pitfall 4: Query Key Doesn't Include Forecast Parameter
**What goes wrong:** Toggling season doesn't refetch data, shows cached wrong data
**Why it happens:** useFeeList params not passed correctly to queryKey
**How to avoid:** Pass params object to useFeeList: `useFeeList(isForecast ? { forecast: true } : {})`
**Warning signs:** Toggle changes UI but data stays the same, or data from wrong season appears

### Pitfall 5: Hardcoding Season Labels
**What goes wrong:** Dropdown shows "2025-2026" when actual season is "2026-2027"
**Why it happens:** Not using data.season from API response
**How to avoid:** Use `data?.season` for current option, calculate forecast from it
**Warning signs:** Season labels don't update when season changes, labels wrong in different years

### Pitfall 6: Filter Buttons Not Disabled in Forecast Mode
**What goes wrong:** "Geen Nikki" and "Afwijking" filter buttons still show in forecast mode
**Why it happens:** Filter logic doesn't account for forecast mode where nikki fields don't exist
**How to avoid:** Hide or disable filter buttons when isForecast=true: `{!isForecast && noNikkiCount > 0 && <button>...}</button>`
**Warning signs:** Filter buttons appear but don't work, or cause errors in forecast mode

### Pitfall 7: Sort Columns Still Include Nikki Fields
**What goes wrong:** Clicking hidden Nikki column header in non-forecast mode keeps that sort when switching to forecast
**Why it happens:** sortField state persists across forecast toggle
**How to avoid:** Reset sortField when toggling forecast if it's 'nikki_total' or 'nikki_saldo'
**Warning signs:** Unexpected sort order in forecast mode, or sort doesn't work

### Pitfall 8: Loading State Shows Empty Table
**What goes wrong:** Table disappears during season toggle refetch
**Why it happens:** Not showing previous data while loading new data
**How to avoid:** TanStack Query automatically maintains previous data during refetch - ensure you're not clearing data on isLoading
**Warning signs:** Flash of empty state when toggling seasons

## Code Examples

Verified patterns from official sources:

### Controlled Select Dropdown
```jsx
// Source: React official docs - <select> controlled components
// https://react.dev/reference/react-dom/components/select
import { useState } from 'react';

const [isForecast, setIsForecast] = useState(false);

<select
  value={isForecast ? 'forecast' : 'current'}
  onChange={(e) => setIsForecast(e.target.value === 'forecast')}
  className="btn-secondary appearance-none"
>
  <option value="current">{data?.season || '2025-2026'} (huidig)</option>
  <option value="forecast">
    {getNextSeasonLabel(data?.season || '2025-2026')} (prognose)
  </option>
</select>
```

### TanStack Query with Conditional Parameters
```jsx
// Source: Existing useFeeList hook + TanStack Query patterns
// https://tanstack.com/query/v4/docs/framework/react/guides/query-functions
import { useFeeList } from '@/hooks/useFees';

const [isForecast, setIsForecast] = useState(false);

// Pass forecast parameter through params object
const { data, isLoading, error } = useFeeList(
  isForecast ? { forecast: true } : {}
);

// TanStack Query automatically:
// - Uses params in queryKey: feeKeys.list({ forecast: true })
// - Creates separate cache entry for forecast data
// - Refetches when isForecast changes (new queryKey)
// - Maintains previous data during loading (no flash)
```

### Conditional Table Column Rendering
```jsx
// Source: React conditional rendering patterns
// https://www.w3schools.com/react/react_conditional_rendering.asp
<thead>
  <tr>
    <SortableHeader label="Voornaam" columnId="first_name" {...sortProps} />
    <SortableHeader label="Bedrag" columnId="final_fee" {...sortProps} />

    {/* Only render Nikki columns in current season mode */}
    {!isForecast && (
      <>
        <SortableHeader label="Nikki" columnId="nikki_total" {...sortProps} />
        <SortableHeader label="Saldo" columnId="nikki_saldo" {...sortProps} />
      </>
    )}
  </tr>
</thead>

<tbody>
  {sortedMembers.map((member) => (
    <tr key={member.id}>
      <td>{member.first_name}</td>
      <td>{formatCurrency(member.final_fee)}</td>

      {/* Same conditional rendering in body */}
      {!isForecast && (
        <>
          <td>{formatCurrency(member.nikki_total)}</td>
          <td>{formatCurrency(member.nikki_saldo)}</td>
        </>
      )}
    </tr>
  ))}
</tbody>

<tfoot>
  <tr>
    <td colSpan="...">Totaal</td>
    <td>{formatCurrency(totals.finalFee)}</td>

    {/* Same conditional rendering in footer */}
    {!isForecast && (
      <>
        <td>{formatCurrency(totals.nikkiTotal)}</td>
        <td>{formatCurrency(totals.nikkiSaldo)}</td>
      </>
    )}
  </tr>
</tfoot>
```

### Visual Forecast Indicator Badge
```jsx
// Source: Existing ContributieList category badges + Tailwind badge patterns
// https://flowbite.com/docs/components/badge/
import { TrendingUp } from 'lucide-react';

{isForecast && (
  <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300">
    <TrendingUp className="w-4 h-4" />
    <span className="font-medium">Prognose voor {data?.season ? getNextSeasonLabel(data.season) : '2026-2027'}</span>
    <span className="text-blue-600 dark:text-blue-400">
      (o.b.v. huidige ledenstand)
    </span>
  </div>
)}
```

### Season Label Helper Function
```jsx
// Source: Backend pattern from Phase 129 (129-RESEARCH.md:235-247)
// Calculate next season label from current season string
const getNextSeasonLabel = (currentSeason) => {
  // Extract start year from "2025-2026" format
  const startYear = parseInt(currentSeason.substring(0, 4));

  // Next season is +1 year
  const nextStartYear = startYear + 1;

  return `${nextStartYear}-${nextStartYear + 1}`;
};
```

### Reset Sort When Toggling to Forecast
```jsx
// Source: React useEffect patterns for side effects
import { useEffect } from 'react';

const [isForecast, setIsForecast] = useState(false);
const [sortField, setSortField] = useState('last_name');

// Reset sort field if it's a Nikki column when entering forecast mode
useEffect(() => {
  if (isForecast && (sortField === 'nikki_total' || sortField === 'nikki_saldo')) {
    setSortField('last_name'); // Reset to default sort
  }
}, [isForecast, sortField]);
```

### Filter Buttons Conditional Display
```jsx
// Source: Existing ContributieList filter button pattern (lines 388-418)
// Only show Nikki-related filters in current season mode
{!isForecast && mismatchCount > 0 && (
  <button
    onClick={() => setShowMismatchOnly(!showMismatchOnly)}
    className="btn-secondary inline-flex items-center gap-1.5"
  >
    <Filter className="w-4 h-4" />
    <span className="text-xs">Afwijking ({mismatchCount})</span>
  </button>
)}

{!isForecast && noNikkiCount > 0 && (
  <button
    onClick={() => setShowNoNikkiOnly(!showNoNikkiOnly)}
    className="btn-secondary inline-flex items-center gap-1.5"
  >
    <Filter className="w-4 h-4" />
    <span className="text-xs">Geen Nikki ({noNikkiCount})</span>
  </button>
)}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| CSS display:none for columns | Conditional rendering (not in DOM) | React best practices | Better performance, automatic table reflow |
| Separate API endpoints | Parameter-based single endpoint | REST best practices 2020+ | Simpler API, automatic cache separation |
| Custom select components | Native `<select>` with Tailwind | Accessibility focus 2023+ | Better a11y, keyboard nav, mobile support |
| Manual cache invalidation | TanStack Query queryKey changes | TanStack Query v4+ | Automatic refetch, no manual invalidation |
| Redux/context for dropdown state | Local useState | React hooks 2019+ | Simpler, no global state pollution |

**Deprecated/outdated:**
- Custom dropdown libraries for simple toggles: Native `<select>` with Tailwind provides better accessibility
- URL search params for UI state: Adds complexity without benefit for non-shareable state
- CSS visibility toggling: Conditional rendering performs better and enables proper table layout

## Open Questions

Things that couldn't be fully resolved:

1. **Should forecast mode remember last selected state?**
   - What we know: useState initializes to false on component mount
   - What's unclear: Should we persist forecast selection to localStorage or user preferences?
   - Recommendation: Start with session-only state (no persistence) - forecast is infrequent use case, users expect default view

2. **Should dropdown be a toggle switch instead of select?**
   - What we know: Only 2 options (current vs forecast), could use toggle switch
   - What's unclear: Which is more intuitive for "season selection" concept?
   - Recommendation: Use `<select>` dropdown - more obvious what the options are, easier to extend if more seasons added later

3. **Should forecast indicator be dismissible?**
   - What we know: Badge provides clear visual distinction
   - What's unclear: Does it clutter the UI if always visible?
   - Recommendation: Always show when in forecast mode - critical context that prevents user confusion

4. **Should totals row show in forecast mode?**
   - What we know: Totals are still meaningful (projected total fees)
   - What's unclear: User expectations for forecast totals accuracy
   - Recommendation: Keep totals row in forecast mode - useful for budget planning, exclude only Nikki columns

## Sources

### Primary (HIGH confidence)
- React Official Docs - [&lt;select&gt; controlled components](https://react.dev/reference/react-dom/components/select)
- TanStack Query Docs - [Query Functions](https://tanstack.com/query/v4/docs/framework/react/guides/query-functions)
- Existing codebase: `src/hooks/useFees.js` (useFeeList hook)
- Existing codebase: `src/pages/Contributie/ContributieList.jsx` (current implementation)
- Existing codebase: `src/api/client.js` (prmApi.getFeeList)
- Phase 129 Research: `.planning/phases/129-backend-forecast-calculation/129-RESEARCH.md`

### Secondary (MEDIUM confidence)
- [React Select: A comprehensive guide - LogRocket Blog](https://blog.logrocket.com/react-select-comprehensive-guide/)
- [React conditional rendering: 9 methods with examples - LogRocket Blog](https://blog.logrocket.com/react-conditional-rendering-9-methods/)
- [Tailwind CSS Badges - Flowbite](https://flowbite.com/docs/components/badge/)
- [Column Visibility Guide - TanStack Table Docs](https://tanstack.com/table/v8/docs/guide/column-visibility)
- [React Conditional Rendering - W3Schools](https://www.w3schools.com/react/react_conditional_rendering.asp)

### Tertiary (LOW confidence)
- [10 Best Dropdown Components For React & React Native (2026 Update) - ReactScript](https://reactscript.com/best-dropdown/)
- [React Material Table Hide Column on View](https://forum.freecodecamp.org/t/react-material-table-hide-column-on-view-but-show-on-export/494061)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Uses existing project dependencies (React 18, TanStack Query, Tailwind CSS 3.4)
- Architecture: HIGH - Controlled component and conditional rendering are established React patterns
- Pitfalls: HIGH - Based on React official docs, existing codebase patterns, and Phase 129 backend implementation

**Research date:** 2026-02-02
**Valid until:** 30 days (stable React patterns, mature TanStack Query API)

**Key findings:**
1. No new dependencies required - purely React state management and conditional rendering
2. useFeeList hook already accepts params - pass `{ forecast: true }` directly
3. TanStack Query handles cache separation automatically via queryKey
4. Conditional rendering (`{!isForecast && ...}`) provides instant hide with automatic table reflow
5. Native `<select>` with Tailwind styling matches existing UI patterns and provides better accessibility than custom components
6. Backend (Phase 129) already excludes nikki fields from forecast response - frontend just conditionally renders columns
7. Visual indicator (badge) critical for preventing user confusion about data mode
8. Filter buttons (Geen Nikki, Afwijking) must be hidden in forecast mode - those fields don't exist
