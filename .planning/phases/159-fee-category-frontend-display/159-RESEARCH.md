# Phase 159: Fee Category Frontend Display - Research

**Researched:** 2026-02-09
**Domain:** React frontend refactoring, dynamic data-driven UI, Google Sheets export
**Confidence:** HIGH

## Summary

Phase 159 removes all hardcoded category definitions from the frontend, making the contributie list and Google Sheets export fully data-driven using the category metadata introduced in Phase 157. The research reveals:

1. **Current state:** The frontend has three hardcoded category artifacts to remove:
   - `FEE_CATEGORIES` object in `src/utils/formatters.js` (lines 36-43) with labels and Tailwind color classes
   - `categoryOrder` object in `ContributieList.jsx` (line 248) for client-side sorting
   - `category_labels` object in `class-rest-google-sheets.php` (lines 994-1001) for export column values

2. **API contract established:** Phase 157-02 added a `categories` key to the GET /rondo/v1/fees response containing `{ slug: { label, sort_order, is_youth } }` per category. This metadata is already being returned by the API but not yet consumed by the frontend.

3. **Color assignment strategy:** The existing `FEE_CATEGORIES` uses six distinct Tailwind color utilities. The new approach will auto-assign colors from a fixed palette based on `sort_order` position (0-based index maps to color array).

4. **Export refactoring:** The Google Sheets export method `build_fee_spreadsheet_data()` must derive category labels from the API response's categories metadata instead of the hardcoded `category_labels` array.

**Primary recommendation:** Use the `categories` metadata from the API response to dynamically generate category labels and colors in ContributieList.jsx, remove the hardcoded `FEE_CATEGORIES` object, and update the Google Sheets export to use the same API-derived category data.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React 18 | 18.x | Frontend framework | Existing project stack |
| TanStack Query | 5.x | Data fetching/caching | Already in use for fee list queries |
| Tailwind CSS | 3.4.x | Utility-first styling | Project's styling framework |
| WordPress REST API | 6.0+ | Backend data source | Established API contract from Phase 157 |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None required | - | All functionality uses existing stack | - |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Auto-assigned palette | User-configurable colors | Auto-palette is simpler and keeps UI consistent; user colors add complexity for minimal benefit (deferred per REQUIREMENTS.md) |
| API-derived colors | CSS-in-JS color generation | Tailwind utilities are project standard; dynamic CSS would break build optimization |
| Fixed palette array | Generate colors algorithmically | Fixed palette ensures accessibility and visual harmony; algorithmic colors risk poor contrast |

**Installation:**
None required - all dependencies already installed.

## Architecture Patterns

### Pattern 1: API Response as Single Source of Truth

**What:** Use the `categories` metadata from the fee list API response to drive all category-related display logic, eliminating frontend-side hardcoded mappings.

**When to use:** Any component rendering category labels, colors, or sort order.

**Example (from Phase 157-02 API contract):**
```javascript
// API response structure (from GET /rondo/v1/fees)
{
  season: "2025-2026",
  forecast: false,
  total: 42,
  members: [ /* member objects */ ],
  categories: {
    mini: { label: "Mini", sort_order: 0, is_youth: true },
    pupil: { label: "Pupil", sort_order: 1, is_youth: true },
    junior: { label: "Junior", sort_order: 2, is_youth: true },
    senior: { label: "Senior", sort_order: 3, is_youth: false },
    recreant: { label: "Recreant", sort_order: 4, is_youth: false },
    donateur: { label: "Donateur", sort_order: 5, is_youth: false }
  }
}

// Derive display properties from API data
const categoryMeta = data.categories[member.category];
const label = categoryMeta?.label ?? member.category;
const color = CATEGORY_COLOR_PALETTE[categoryMeta?.sort_order ?? 0];
```

### Pattern 2: Fixed Palette Indexed by Sort Order

**What:** Maintain a fixed array of Tailwind color utility class strings, index by `sort_order` to assign colors consistently across all categories.

**When to use:** For category badge rendering in the fee list and any future category-colored UI elements.

**Example (converting existing FEE_CATEGORIES colors):**
```javascript
// Fixed palette array (6 colors from existing FEE_CATEGORIES, expandable for future categories)
const CATEGORY_COLOR_PALETTE = [
  'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',   // sort_order 0
  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',       // sort_order 1
  'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300', // sort_order 2
  'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300', // sort_order 3
  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',          // sort_order 4
  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300', // sort_order 5
];

// Usage in FeeRow component
function FeeRow({ member, data }) {
  const categoryMeta = data.categories[member.category];
  const colorClasses = CATEGORY_COLOR_PALETTE[categoryMeta?.sort_order ?? 0]
    ?? 'bg-gray-100 text-gray-700'; // Fallback for categories beyond palette

  return (
    <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${colorClasses}`}>
      {categoryMeta?.label ?? member.category}
    </span>
  );
}
```

**Why not CSS-in-JS or dynamic colors?** Tailwind's JIT compiler requires class names to be statically present in source files. Dynamic class name construction (`bg-${color}-100`) breaks Tailwind's PurgeCSS/JIT detection.

### Pattern 3: API-Driven Sort Order

**What:** Replace hardcoded `categoryOrder` object with dynamic sort order derived from the API's `categories.sort_order` values.

**Current code (hardcoded):**
```javascript
// ContributieList.jsx line 248 (to be removed)
const categoryOrder = { mini: 1, pupil: 2, junior: 3, senior: 4, recreant: 5, donateur: 6 };

// Sort comparison
cmp = (categoryOrder[a.category] ?? 99) - (categoryOrder[b.category] ?? 99);
```

**New pattern (API-driven):**
```javascript
// Derive sort order from API response
const categoryOrder = {};
Object.entries(data.categories || {}).forEach(([slug, meta]) => {
  categoryOrder[slug] = meta.sort_order ?? 999;
});

// Sort comparison (same logic, different source)
cmp = (categoryOrder[a.category] ?? 999) - (categoryOrder[b.category] ?? 999);
```

### Pattern 4: Backend Export Uses API Response Categories

**What:** The Google Sheets export backend must use the same category metadata from the fee data API response instead of hardcoded labels.

**Current code (hardcoded):**
```php
// class-rest-google-sheets.php lines 994-1001 (to be removed)
$category_labels = [
    'mini'     => 'Mini',
    'pupil'    => 'Pupil',
    'junior'   => 'Junior',
    'senior'   => 'Senior',
    'recreant' => 'Recreant',
    'donateur' => 'Donateur',
];

// Usage
$category_labels[ $member['category'] ] ?? $member['category']
```

**New pattern (API-driven):**
```php
// Extract categories metadata from API response
$categories = $fee_data['categories'] ?? [];

// Build label map
$category_labels = [];
foreach ($categories as $slug => $meta) {
    $category_labels[$slug] = $meta['label'] ?? $slug;
}

// Usage (same as before)
$category_labels[ $member['category'] ] ?? $member['category']
```

### Anti-Patterns to Avoid

- **Dynamic Tailwind classes:** Never construct class names dynamically (`bg-${color}-100`) - Tailwind's JIT won't detect them.
- **Fetching categories separately:** Don't add a new API call for category metadata - it's already in the fee list response.
- **Hardcoding fallback labels:** Use `member.category` slug as fallback, not hardcoded strings like "Unknown".
- **Mutating API response:** Don't modify the `data.categories` object in place - derive display properties separately.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Category color generation | Random color generator, hash-based colors | Fixed palette indexed by sort_order | Ensures visual consistency, accessibility (contrast), and matches existing design system |
| Category sort order mapping | Complex memoization or state management | Derive from API response on each render | API data is already cached by TanStack Query; no need for extra state |
| Category metadata caching | Custom cache layer | TanStack Query's existing cache | Query cache already handles invalidation, stale data, and refetching |

**Key insight:** The API response from Phase 157 already contains all necessary category metadata. The frontend's job is to *display* this data, not to *manage* it. Any additional state or caching layer adds complexity without benefit.

## Common Pitfalls

### Pitfall 1: Tailwind Class Purging Breaks Dynamic Colors

**What goes wrong:** Attempting to dynamically construct Tailwind class names (e.g., `bg-${color}-100`) results in classes being purged from the production build, breaking the UI.

**Why it happens:** Tailwind's JIT compiler scans source files for class name literals at build time. Dynamically constructed strings are invisible to the scanner.

**How to avoid:**
- Use a fixed array of complete class name strings
- Index the array by `sort_order`
- Ensure all palette classes are present as string literals in the source file

**Warning signs:**
- Classes work in dev mode but disappear in production builds
- `npm run build` output shows fewer classes than expected
- Category badges lose styling after deployment

### Pitfall 2: Forgetting the Forecast Mode Toggle

**What goes wrong:** Updating category rendering for current season but forgetting that the fee list has a forecast mode that fetches different data.

**Why it happens:** The `isForecast` state toggle changes the API request parameters (`{ forecast: true }`), returning next season's data. Category metadata must work for both seasons.

**How to avoid:**
- Test with both `isForecast` states (current and next season)
- Verify category metadata is present in both API responses
- Check that sort order and labels are correct for next season

**Warning signs:**
- Categories display correctly in current season but break in forecast mode
- API response for forecast mode is missing `categories` key (shouldn't happen if Phase 157 is complete)

### Pitfall 3: Hardcoded Export Labels Out of Sync

**What goes wrong:** Updating the frontend to use API-driven categories but leaving the Google Sheets export's hardcoded `category_labels` array intact, causing exported data to show outdated labels.

**Why it happens:** The export code (`class-rest-google-sheets.php`) is in a separate file and uses a separate method (`build_fee_spreadsheet_data()`), making it easy to overlook during frontend changes.

**How to avoid:**
- Update both frontend rendering AND backend export in the same phase
- Use the same data source (API response's `categories` metadata) for both
- Test the export feature after making frontend changes

**Warning signs:**
- Frontend displays correct category labels but export shows old labels
- Adding a new category in settings works in the UI but export fails or shows generic slug
- Export column "Categorie" shows slugs instead of labels

## Code Examples

Verified patterns from existing codebase and Phase 157 API contract:

### Example 1: Fetching Fee Data with Categories Metadata

```javascript
// src/hooks/useFees.js (existing pattern)
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export function useFeeList(params = {}) {
  return useQuery({
    queryKey: ['fees', 'list', params],
    queryFn: async () => {
      const response = await prmApi.getFeeList(params);
      return response.data; // Includes categories metadata from Phase 157
    },
  });
}

// Usage in component
const { data, isLoading } = useFeeList({ forecast: isForecast });
// data.categories = { mini: { label, sort_order, is_youth }, ... }
```

### Example 2: Category Badge with Dynamic Color

```javascript
// ContributieList.jsx FeeRow component
const CATEGORY_COLOR_PALETTE = [
  'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
];

function FeeRow({ member, categories }) {
  const categoryMeta = categories?.[member.category];
  const label = categoryMeta?.label ?? member.category;
  const colorClasses = categoryMeta?.sort_order !== undefined
    ? CATEGORY_COLOR_PALETTE[categoryMeta.sort_order] ?? CATEGORY_COLOR_PALETTE[0]
    : 'bg-gray-100 text-gray-700';

  return (
    <tr>
      {/* ... */}
      <td className="px-4 py-3 whitespace-nowrap">
        <span className={`inline-flex px-2 py-0.5 text-xs rounded-full ${colorClasses}`}>
          {label}
        </span>
      </td>
      {/* ... */}
    </tr>
  );
}
```

### Example 3: Dynamic Category Sort Order

```javascript
// ContributieList.jsx sorting logic
const { data } = useFeeList({ forecast: isForecast });

// Build sort order map from API categories
const categoryOrder = {};
Object.entries(data?.categories || {}).forEach(([slug, meta]) => {
  categoryOrder[slug] = meta.sort_order ?? 999;
});

// Sort members (existing logic, new data source)
const sortedMembers = [...filteredMembers].sort((a, b) => {
  let cmp = 0;

  switch (sortField) {
    case 'category':
      cmp = (categoryOrder[a.category] ?? 999) - (categoryOrder[b.category] ?? 999);
      break;
    // ... other sort fields
  }

  return sortOrder === 'asc' ? cmp : -cmp;
});
```

### Example 4: Google Sheets Export with API Categories

```php
// class-rest-google-sheets.php build_fee_spreadsheet_data method
private function build_fee_spreadsheet_data( array $fee_data, bool $forecast = false ): array {
    $data = [];

    // Extract category metadata from API response
    $categories = $fee_data['categories'] ?? [];
    $category_labels = [];
    foreach ($categories as $slug => $meta) {
        $category_labels[$slug] = $meta['label'] ?? $slug;
    }

    // Header row (existing)
    $headers = ['Naam', 'Relatiecode', 'Categorie', 'Leeftijdsgroep', 'Basis', 'Gezinskorting', 'Pro-rata %', 'Bedrag'];
    if (!$forecast) {
        $headers[] = 'Nikki Total';
        $headers[] = 'Saldo';
    }
    $data[] = $headers;

    // Data rows (use API-derived labels)
    foreach ($fee_data['members'] as $member) {
        $row = [
            $member['name'],
            $member['relatiecode'] ?: '',
            $category_labels[$member['category']] ?? $member['category'], // API-driven label
            $member['leeftijdsgroep'] ?: '',
            $member['base_fee'],
            $member['family_discount_rate'],
            $member['prorata_percentage'],
            $member['final_fee'],
        ];
        // ... rest of row
        $data[] = $row;
    }

    // Totals row (existing)
    // ...

    return $data;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded `FEE_CATEGORIES` object | API-driven category metadata | Phase 157 (v21.0) | Frontend no longer needs code changes when categories are added/edited |
| Hardcoded `categoryOrder` in frontend | `sort_order` from API | Phase 157 (v21.0) | Category ordering is configurable via settings UI, no deploy needed |
| Hardcoded `category_labels` in export | Categories metadata in API response | Phase 159 (v21.0) | Export automatically reflects current category configuration |

**Deprecated/outdated:**
- `FEE_CATEGORIES` object in `src/utils/formatters.js`: Replaced by API `categories` metadata and fixed palette
- `categoryOrder` object in `ContributieList.jsx`: Replaced by API `categories.sort_order` values
- `category_labels` array in `class-rest-google-sheets.php`: Replaced by API `categories.label` values

## Open Questions

1. **Palette expansion for future categories**
   - What we know: Current system has 6 categories, palette has 6 colors
   - What's unclear: If admin adds a 7th category, should palette repeat colors or add new ones?
   - Recommendation: Allow palette to repeat (use modulo: `sort_order % PALETTE.length`) - ensures all categories are displayable, color overlap is acceptable for edge case

2. **Category metadata presence guarantee**
   - What we know: Phase 157-02 adds categories to API response
   - What's unclear: Is categories metadata guaranteed to be present, or should we handle missing metadata gracefully?
   - Recommendation: Handle gracefully with fallbacks (`categoryMeta?.label ?? member.category`) - defensive programming for API backwards compatibility

## Sources

### Primary (HIGH confidence)
- `src/utils/formatters.js` lines 36-43 - Current FEE_CATEGORIES implementation
- `src/pages/Contributie/ContributieList.jsx` line 248 - Current categoryOrder hardcoded mapping
- `includes/class-rest-google-sheets.php` lines 994-1001 - Current hardcoded category_labels
- `.planning/phases/157-fee-category-rest-api/157-02-PLAN.md` - API contract for categories metadata
- `.planning/phases/157-fee-category-rest-api/157-02-SUMMARY.md` - Phase 157 completion confirmation
- `.planning/REQUIREMENTS.md` - DISPLAY-01, DISPLAY-02, DISPLAY-03 requirements

### Secondary (MEDIUM confidence)
- `.planning/phases/158-fee-category-settings-ui/158-01-PLAN.md` - Settings UI patterns for category management
- `src/hooks/useFees.js` - Existing fee data fetching patterns

### Tertiary (LOW confidence)
- None - all findings verified against codebase or established Phase 157 contracts

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All existing project stack, no new dependencies
- Architecture: HIGH - Patterns verified in existing codebase (TanStack Query, Tailwind utilities)
- Pitfalls: HIGH - Based on known Tailwind JIT behavior and existing export code structure
- Code examples: HIGH - All examples derived from actual project files or Phase 157 API contract

**Research date:** 2026-02-09
**Valid until:** 2026-03-09 (30 days - stable patterns, well-established API contract)
