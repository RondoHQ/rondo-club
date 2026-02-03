# Phase 134: Discipline Cases UI - Research

**Researched:** 2026-02-03
**Domain:** React UI for read-only discipline case display with table view and person integration
**Confidence:** HIGH

## Summary

This phase implements a read-only UI for discipline cases created in Phase 132 (data foundation) with access control from Phase 133 (fairplay capability). The standard approach is a table-based list view with inline expandable details, season filtering using taxonomy terms, and person detail tab integration following existing Stadion patterns.

**Key findings:**
- Stadion has consistent table UI patterns (PeopleList, TeamsList, DatesList) using sticky headers, sortable columns, and Dutch locale formatting
- Expandable row pattern: click row to toggle inline details panel showing additional fields
- Season filtering via WordPress REST API taxonomy query parameters (seizoen=term_id)
- Currency formatting uses existing `formatCurrency(amount, 2)` utility with Dutch locale (€25,00)

**Primary recommendation:** Build reusable DisciplineCaseTable component shared between list page and person tab, following PeopleList sticky header pattern with season dropdown using get_current_season helper for default.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React Router | 6 | Routing | Already integrated, handles /discipline-cases route |
| TanStack Query | Latest | Data fetching | Existing pattern for all list views, automatic caching |
| Lucide React | Latest | Icons | Stadion standard (Gavel icon already added in Phase 133) |
| Tailwind CSS | 3.4 | Styling | Stadion design system, card/table classes defined |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| date-fns | Latest (via utils) | Date formatting | Format match_date for display (format function from @/utils/dateFormat) |
| Axios | Latest (wpApi client) | HTTP requests | REST API calls via wpApi.getDisciplineCases() |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom table | TanStack Table library | TanStack Table adds complexity; Stadion uses custom tables consistently |
| Accordion component | Detail modal | Inline expansion keeps context; modal disrupts flow for read-only view |
| Client-side season filter | Server-side taxonomy param | Server-side filtering is standard WordPress pattern, reduces payload |

**Installation:**
```bash
# No new dependencies - all libraries already installed
```

## Architecture Patterns

### Recommended Project Structure
```
src/pages/DisciplineCases/
├── DisciplineCasesList.jsx      # List page (already placeholder)
└── DisciplineCaseTable.jsx      # Shared table component (new)
```

### Pattern 1: Shared Table Component
**What:** Extract table into reusable component consumed by both list page and person tab
**When to use:** When same UI appears in multiple contexts with minor variations
**Example:**
```jsx
// DisciplineCaseTable.jsx
export default function DisciplineCaseTable({
  cases,
  isLoading,
  showPersonColumn = true,
  showSeasonFilter = false,
  selectedSeason,
  onSeasonChange
}) {
  const [expandedRowId, setExpandedRowId] = useState(null);

  return (
    <div className="space-y-4">
      {showSeasonFilter && (
        <SeasonFilter value={selectedSeason} onChange={onSeasonChange} />
      )}
      <div className="card overflow-x-auto">
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          {/* Table implementation */}
        </table>
      </div>
    </div>
  );
}

// DisciplineCasesList.jsx
export default function DisciplineCasesList() {
  return <DisciplineCaseTable showPersonColumn showSeasonFilter />;
}

// PersonDetail.jsx - Tuchtzaken tab
<DisciplineCaseTable
  cases={personCases}
  showPersonColumn={false}
  showSeasonFilter={false}
/>
```

### Pattern 2: Expandable Table Rows
**What:** Click row to toggle inline details panel below the row
**When to use:** Show additional fields without navigating away or opening modal
**Example:**
```jsx
// Source: TanStack Table expanding pattern (2026)
// https://tanstack.com/table/v8/docs/framework/react/examples/expanding

function DisciplineCaseRow({ case, isExpanded, onToggle, showPersonColumn }) {
  return (
    <>
      <tr
        onClick={() => onToggle(case.id)}
        className="hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
      >
        <td className="px-4 py-3">
          {showPersonColumn && (
            <Link to={`/people/${case.acf.person}`} className="hover:text-accent-600">
              {personName}
            </Link>
          )}
        </td>
        <td className="px-4 py-3">
          <div className="font-medium">{case.acf.match_description}</div>
          <div className="text-sm text-gray-500">
            {format(new Date(case.acf.match_date), 'dd-MM-yyyy')}
          </div>
        </td>
        <td className="px-4 py-3">{case.acf.sanction_description}</td>
        <td className="px-4 py-3">{formatCurrency(case.acf.administrative_fee, 2)}</td>
        <td className="px-4 py-3 text-right">
          <ChevronDown className={`w-4 h-4 transition-transform ${isExpanded ? 'rotate-180' : ''}`} />
        </td>
      </tr>
      {isExpanded && (
        <tr className="bg-gray-50 dark:bg-gray-800/50">
          <td colSpan="5" className="px-4 py-3">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <span className="text-xs font-semibold text-gray-500 uppercase">Aanklacht</span>
                <p className="text-sm">{case.acf.charge_description}</p>
              </div>
              <div>
                <span className="text-xs font-semibold text-gray-500 uppercase">Volledige sanctie</span>
                <p className="text-sm">{case.acf.sanction_description}</p>
              </div>
            </div>
          </td>
        </tr>
      )}
    </>
  );
}
```

### Pattern 3: Season Filter with Current Season Default
**What:** Dropdown filter above table, defaults to current season from taxonomy term meta
**When to use:** Filtering by WordPress taxonomy with default selection
**Example:**
```jsx
// Source: Phase 132 deliverables - get_current_season helper
function SeasonFilter({ value, onChange, seasons }) {
  return (
    <div className="flex items-center gap-2">
      <label className="text-sm font-medium">Seizoen:</label>
      <select
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
        className="input w-48"
      >
        <option value="">Alle seizoenen</option>
        {seasons.map(season => (
          <option key={season.id} value={season.id}>
            {season.name}
          </option>
        ))}
      </select>
    </div>
  );
}

// In parent component
const { data: seasons } = useQuery({
  queryKey: ['seasons'],
  queryFn: async () => {
    const response = await wpApi.getSeasons(); // wp/v2/seizoen
    return response.data;
  },
});

// Get current season for default
const currentSeasonId = seasons?.find(s => s.meta?.is_current_season === '1')?.id;
const [selectedSeason, setSelectedSeason] = useState(currentSeasonId);

// Query with season filter
const { data: cases } = useQuery({
  queryKey: ['discipline-cases', selectedSeason],
  queryFn: async () => {
    const params = selectedSeason ? { seizoen: selectedSeason } : {};
    const response = await wpApi.get('/wp/v2/discipline-cases', { params });
    return response.data;
  },
});
```

### Pattern 4: Sortable Table Columns
**What:** Click column header to toggle sort direction (ascending/descending)
**When to use:** Any table with multiple columns where users need to sort
**Example:**
```jsx
// Source: PeopleList.jsx pattern - client-side sorting
const [sortField, setSortField] = useState('match_date');
const [sortOrder, setSortOrder] = useState('desc'); // Most recent first

const handleSort = (field) => {
  if (field === sortField) {
    setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
  } else {
    setSortField(field);
    setSortOrder('asc');
  }
};

const sortedCases = useMemo(() => {
  if (!cases) return [];
  return [...cases].sort((a, b) => {
    const dateA = new Date(a.acf.match_date);
    const dateB = new Date(b.acf.match_date);
    return sortOrder === 'asc' ? dateA - dateB : dateB - dateA;
  });
}, [cases, sortField, sortOrder]);

// Column header
<th onClick={() => handleSort('match_date')} className="cursor-pointer">
  <div className="flex items-center gap-1">
    Datum
    {sortField === 'match_date' && (
      sortOrder === 'asc' ? <ArrowUp className="w-3 h-3" /> : <ArrowDown className="w-3 h-3" />
    )}
  </div>
</th>
```

### Anti-Patterns to Avoid
- **Full page refresh for season filter:** Use TanStack Query with queryKey dependency instead
- **Modal for case details:** Inline expansion is lighter for read-only display
- **Custom date formatting:** Use existing format() utility from @/utils/dateFormat
- **Separate API calls per person:** Use _embed parameter to include person data in initial query

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Currency formatting | Custom €-prefix logic | `formatCurrency(amount, 2)` from formatters.js | Handles Dutch locale (€25,00 not €25.00), null handling, Intl.NumberFormat |
| Date formatting | moment.js or custom | `format()` from @/utils/dateFormat | date-fns already integrated, consistent across app |
| Season dropdown | Custom select | WordPress taxonomy REST endpoint | Auto-populates from seizoen terms, standard WP pattern |
| Person name display | String concatenation | `getPersonName(person)` from formatters.js | Handles HTML entity decoding, multiple API formats |
| Loading spinner | Custom CSS animation | Existing Stadion spinner classes | `animate-spin rounded-full border-b-2 border-accent-600` |
| Empty state | Custom message div | Existing card pattern with icon | See DatesList.jsx empty state for reference |

**Key insight:** Stadion has utilities for all common formatting operations. Check formatters.js before implementing custom logic.

## Common Pitfalls

### Pitfall 1: Not Using _embed for Person Data
**What goes wrong:** Separate API call per discipline case to fetch person name, causing N+1 queries
**Why it happens:** WordPress REST API doesn't include related post objects by default
**How to avoid:** Use `_embed=true` parameter in initial query to include person data
**Warning signs:** Seeing multiple HTTP requests in DevTools for a single table load

```jsx
// WRONG - N+1 queries
const { data: cases } = useQuery(['discipline-cases'], fetchCases);
cases.forEach(case => {
  const { data: person } = useQuery(['person', case.acf.person], () => fetchPerson(case.acf.person));
});

// RIGHT - Single query with embed
const { data: cases } = useQuery(['discipline-cases'], async () => {
  const response = await wpApi.get('/wp/v2/discipline-cases', {
    params: { _embed: true, per_page: 100 }
  });
  return response.data;
});

// Access person via _embedded
const person = case._embedded?.['wp:term']?.[0]; // If person was taxonomy
// OR if person is post_object field, query separately but batch
```

### Pitfall 2: Currency Formatting Without Dutch Locale
**What goes wrong:** Displays €25.00 (English) instead of €25,00 (Dutch)
**Why it happens:** Default JavaScript locale uses period as decimal separator
**How to avoid:** Use `formatCurrency(amount, 2)` utility which specifies 'nl-NL' locale
**Warning signs:** Decimal separator is period instead of comma in UI

```jsx
// WRONG - English formatting
<td>€{parseFloat(case.acf.administrative_fee).toFixed(2)}</td>
// Shows: €25.00

// RIGHT - Dutch formatting
import { formatCurrency } from '@/utils/formatters';
<td>{formatCurrency(case.acf.administrative_fee, 2)}</td>
// Shows: €25,00
```

### Pitfall 3: Not Filtering Empty Seasons
**What goes wrong:** Dropdown shows seasons with zero discipline cases, confusing users
**Why it happens:** get_terms() returns all terms regardless of usage
**How to avoid:** Use `hide_empty=true` in taxonomy query OR filter client-side after loading cases
**Warning signs:** User selects season and sees "Geen tuchtzaken" message

```jsx
// Approach 1: WordPress query (server-side)
const { data: seasons } = useQuery(['seasons-with-cases'], async () => {
  const response = await wpApi.get('/wp/v2/seizoen', {
    params: { hide_empty: true, post_type: 'discipline_case' }
  });
  return response.data;
});

// Approach 2: Client-side filter (simpler for small datasets)
const seasonsWithCases = useMemo(() => {
  if (!seasons || !cases) return [];
  const seasonIds = new Set(cases.map(c => c.seizoen?.[0]).filter(Boolean));
  return seasons.filter(s => seasonIds.has(s.id));
}, [seasons, cases]);
```

### Pitfall 4: Conditionally Hiding Person Tab vs Entire Table
**What goes wrong:** Person tab shows but table is empty, or tab hidden when should show empty state
**Why it happens:** Confusion between "hide tab if no cases" vs "show empty state in tab"
**How to avoid:** Per CONTEXT.md decision: hide tab entirely if person has zero cases
**Warning signs:** Tab visible but shows confusing empty state for person who never had cases

```jsx
// In PersonDetail.jsx
const { data: personCases = [] } = useQuery({
  queryKey: ['discipline-cases', 'person', person.id],
  queryFn: async () => {
    const response = await wpApi.get('/wp/v2/discipline-cases', {
      params: { 'acf.person': person.id }
    });
    return response.data;
  },
  enabled: !!person?.id && canAccessFairplay, // Only query if have access
});

// Hide tab if no cases
{canAccessFairplay && personCases.length > 0 && (
  <TabButton
    active={activeTab === 'tuchtzaken'}
    onClick={() => setActiveTab('tuchtzaken')}
    icon={Gavel}
    label="Tuchtzaken"
  />
)}
```

### Pitfall 5: Date Sorting with String Comparison
**What goes wrong:** Dates sort incorrectly (20240915 sorts before 20231101 as strings)
**Why it happens:** ACF date fields return YYYYMMDD string format (Ymd), not Date objects
**How to avoid:** Convert to Date objects before comparison in sort function
**Warning signs:** Clicking date column header produces unexpected sort order

```jsx
// WRONG - String comparison
const sorted = [...cases].sort((a, b) => {
  return sortOrder === 'asc'
    ? a.acf.match_date.localeCompare(b.acf.match_date)
    : b.acf.match_date.localeCompare(a.acf.match_date);
});

// RIGHT - Date object comparison
const sorted = [...cases].sort((a, b) => {
  const dateA = new Date(a.acf.match_date.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'));
  const dateB = new Date(b.acf.match_date.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'));
  return sortOrder === 'asc' ? dateA - dateB : dateB - dateA;
});
```

## Code Examples

Verified patterns from official sources and existing codebase:

### Season Filter Component
```jsx
// Source: Phase 132 SUMMARY.md - get_current_season helper exists
// Source: Stadion formatters.js - decodeHtml for term names

import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';
import { decodeHtml } from '@/utils/formatters';

function SeasonFilter({ value, onChange }) {
  const { data: seasons = [] } = useQuery({
    queryKey: ['seasons'],
    queryFn: async () => {
      const response = await wpApi.get('/wp/v2/seizoen', {
        params: { per_page: 100, orderby: 'name', order: 'desc' }
      });
      return response.data;
    },
  });

  // Filter to seasons with cases (hide empty)
  const { data: caseCounts } = useQuery({
    queryKey: ['discipline-cases', 'counts'],
    queryFn: async () => {
      const response = await wpApi.get('/wp/v2/discipline-cases', {
        params: { per_page: 100, _fields: 'id,seizoen' }
      });
      const counts = {};
      response.data.forEach(c => {
        const seasonId = c.seizoen?.[0];
        if (seasonId) counts[seasonId] = (counts[seasonId] || 0) + 1;
      });
      return counts;
    },
  });

  const seasonsWithCases = seasons.filter(s => caseCounts?.[s.id] > 0);

  // Find current season for default
  const currentSeason = seasons.find(s => s.meta?.is_current_season === '1');

  // Set default on mount if not set
  React.useEffect(() => {
    if (!value && currentSeason) {
      onChange(currentSeason.id);
    }
  }, [currentSeason, value, onChange]);

  return (
    <div className="flex items-center gap-2">
      <label htmlFor="season-filter" className="text-sm font-medium text-gray-700 dark:text-gray-300">
        Seizoen:
      </label>
      <select
        id="season-filter"
        value={value || ''}
        onChange={(e) => onChange(e.target.value ? parseInt(e.target.value, 10) : null)}
        className="input w-48"
      >
        <option value="">Alle seizoenen</option>
        {seasonsWithCases.map(season => (
          <option key={season.id} value={season.id}>
            {decodeHtml(season.name)}
          </option>
        ))}
      </select>
    </div>
  );
}
```

### Expandable Table Row
```jsx
// Source: Material React Table expanding pattern
// https://www.material-react-table.com/docs/guides/detail-panel
// Adapted to Stadion patterns

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronDown } from 'lucide-react';
import { format } from '@/utils/dateFormat';
import { formatCurrency } from '@/utils/formatters';

function DisciplineCaseRow({ case: disciplineCase, showPersonColumn }) {
  const [isExpanded, setIsExpanded] = useState(false);

  // Parse match_date from Ymd format (20240915) to Date
  const matchDate = disciplineCase.acf.match_date
    ? new Date(disciplineCase.acf.match_date.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'))
    : null;

  // Get person name from embedded data if available
  const personName = disciplineCase._embedded?.['author']?.[0]?.name || 'Onbekend';
  const personId = disciplineCase.acf.person;

  return (
    <>
      <tr
        onClick={() => setIsExpanded(!isExpanded)}
        className="hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors"
      >
        {showPersonColumn && (
          <td className="px-4 py-3 whitespace-nowrap">
            <Link
              to={`/people/${personId}`}
              onClick={(e) => e.stopPropagation()} // Prevent row expand on link click
              className="text-accent-600 hover:text-accent-700 dark:text-accent-400 dark:hover:text-accent-300"
            >
              {personName}
            </Link>
          </td>
        )}
        <td className="px-4 py-3">
          <div className="font-medium text-gray-900 dark:text-gray-50">
            {disciplineCase.acf.match_description || '-'}
          </div>
          {matchDate && (
            <div className="text-sm text-gray-500 dark:text-gray-400">
              {format(matchDate, 'dd-MM-yyyy')}
            </div>
          )}
        </td>
        <td className="px-4 py-3 text-sm text-gray-900 dark:text-gray-50">
          {disciplineCase.acf.sanction_description || '-'}
        </td>
        <td className="px-4 py-3 text-sm text-gray-900 dark:text-gray-50">
          {formatCurrency(disciplineCase.acf.administrative_fee, 2)}
        </td>
        <td className="px-4 py-3 text-right">
          <ChevronDown
            className={`w-4 h-4 text-gray-400 transition-transform ${
              isExpanded ? 'rotate-180' : ''
            }`}
          />
        </td>
      </tr>
      {isExpanded && (
        <tr className="bg-gray-50 dark:bg-gray-800/50">
          <td colSpan={showPersonColumn ? 5 : 4} className="px-4 py-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div>
                <span className="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                  Aanklacht
                </span>
                <p className="text-gray-700 dark:text-gray-300">
                  {disciplineCase.acf.charge_description || 'Geen aanklacht beschrijving'}
                </p>
              </div>
              <div>
                <span className="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                  Volledige Sanctie
                </span>
                <p className="text-gray-700 dark:text-gray-300">
                  {disciplineCase.acf.sanction_description || 'Geen sanctie beschrijving'}
                </p>
              </div>
              {disciplineCase.acf.charge_codes && (
                <div>
                  <span className="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">
                    Aanklacht Code
                  </span>
                  <p className="text-gray-700 dark:text-gray-300">
                    {disciplineCase.acf.charge_codes}
                  </p>
                </div>
              )}
            </div>
          </td>
        </tr>
      )}
    </>
  );
}
```

### Loading and Empty States
```jsx
// Source: DatesList.jsx pattern

// Loading state
{isLoading && (
  <div className="flex items-center justify-center h-64">
    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-600 dark:border-accent-400"></div>
  </div>
)}

// Empty state - no cases for selected season
{!isLoading && !error && cases.length === 0 && selectedSeason && (
  <div className="card p-12 text-center">
    <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
      <Gavel className="w-6 h-6 text-gray-400 dark:text-gray-500" />
    </div>
    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">
      Geen tuchtzaken in dit seizoen
    </h3>
    <p className="text-gray-500 dark:text-gray-400">
      Selecteer een ander seizoen of bekijk alle seizoenen.
    </p>
  </div>
)}

// Empty state - no cases at all
{!isLoading && !error && cases.length === 0 && !selectedSeason && (
  <div className="card p-12 text-center">
    <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
      <Gavel className="w-6 h-6 text-gray-400 dark:text-gray-500" />
    </div>
    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">
      Geen tuchtzaken gevonden
    </h3>
    <p className="text-gray-500 dark:text-gray-400">
      Tuchtzaken worden gesynchroniseerd vanuit Sportlink.
    </p>
  </div>
)}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Separate list/detail pages | Inline expandable rows | 2024-2025 | Keeps context visible, reduces navigation |
| Client-side CSV export | Google Sheets integration | Phase 128 (2025) | Not needed for discipline cases (read-only) |
| Manual season management | Taxonomy with term meta flag | Phase 132 (2026) | Current season auto-detected via get_current_season |
| English currency format | Dutch locale formatting | Existing in formatters.js | €25,00 not €25.00 |

**Deprecated/outdated:**
- Modal detail views for read-only data: Inline expansion is lighter, maintains scroll position
- Custom table libraries: Stadion uses native HTML tables with Tailwind classes consistently

## Open Questions

Things that couldn't be fully resolved:

1. **Person field REST API format**
   - What we know: Phase 132 used post_object field for person link, returns integer ID
   - What's unclear: Whether _embed includes person data or requires separate query
   - Recommendation: Test REST endpoint with _embed=true to confirm person data availability; fallback to batched person queries if needed

2. **Season term ordering**
   - What we know: Taxonomy registered but no ordering specified
   - What's unclear: Should seasons sort chronologically (2023-2024 before 2024-2025) or reverse chronologically
   - Recommendation: Use `orderby=name&order=desc` to show most recent season first (matches user expectation)

3. **Empty state vs hidden tab on person detail**
   - What we know: CONTEXT.md says "hide Tuchtzaken tab entirely if person has zero discipline cases"
   - What's unclear: Should tab appear with empty state during loading, or wait until data loads to show/hide
   - Recommendation: Show tab skeleton during loading, hide after data confirms zero cases (prevents tab flicker)

## Sources

### Primary (HIGH confidence)
- Stadion codebase:
  - PeopleList.jsx - Table patterns, sortable columns, filter dropdowns
  - DatesList.jsx - Empty states, loading spinners, date formatting
  - TeamsList.jsx - Inline editing, bulk actions patterns
  - formatters.js - formatCurrency, decodeHtml, date utilities
  - Phase 132 SUMMARY.md - Data foundation deliverables (CPT, taxonomy, ACF fields)
  - Phase 133 SUMMARY.md - Access control implementation (fairplay capability)
- WordPress REST API documentation - Taxonomy query parameters

### Secondary (MEDIUM confidence)
- [TanStack Table Expanding Example](https://tanstack.com/table/v8/docs/framework/react/examples/expanding) - React expandable row pattern
- [Material React Table Detail Panel Guide](https://www.material-react-table.com/docs/guides/detail-panel) - Inline detail panel UX pattern
- [TanStack Table Sorting Guide](https://tanstack.com/table/v8/docs/guide/sorting) - Column sorting state management

### Tertiary (LOW confidence)
- None - all findings verified with existing codebase or official documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already integrated, no new dependencies
- Architecture: HIGH - Clear patterns from existing list pages (PeopleList, DatesList, TeamsList)
- Pitfalls: HIGH - Identified from Phase 132/133 deliverables and common WordPress REST API issues

**Research date:** 2026-02-03
**Valid until:** 30 days (stable domain - React/WordPress patterns don't change rapidly)
