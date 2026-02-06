# Phase 126: Pro-rata & UI - Research

**Researched:** 2026-01-31
**Domain:** React list page with backend fee calculation API including pro-rata adjustments
**Confidence:** HIGH

## Summary

This phase adds a "Contributie" (Membership Fees) section to the sidebar and implements a list view showing calculated fees with pro-rata adjustments for mid-season joins. The list displays fee breakdowns (base fee, family discount, pro-rata percentage, final amount) and supports filtering by address mismatches to identify siblings registered at different addresses.

The implementation follows established patterns from the VOGList page (list view with filters) and Layout.jsx (sidebar navigation). Pro-rata calculation extends the existing MembershipFees service class with quarter-based percentage adjustments. The critical data dependency is a Sportlink registration date field which does not currently exist in the ACF schema and must be added.

**Primary recommendation:** Extend MembershipFees class with pro-rata calculation methods using a new `registration_date` custom field, create a new REST endpoint for fee list data, and build a React page following the VOGList pattern with filter dropdowns for address mismatch detection.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| React 18 | 18.x | UI components | Already used throughout frontend |
| TanStack Query | Latest | Data fetching/caching | Standard for all API calls |
| react-router-dom | 6.x | Navigation/routing | Already configured in App.jsx |
| Tailwind CSS | 3.4 | Styling | Consistent with all pages |
| lucide-react | Latest | Icons | Used in Layout.jsx, VOGList.jsx |

### Backend
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress REST API | Core | API endpoints | rondo/v1 namespace established |
| ACF Pro | Latest | Custom fields | All person data stored via ACF |
| MembershipFees class | Custom | Fee calculation | Phase 124-125 foundation |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| date-fns | 2.x | Date parsing | Already imported in dateFormat.js |

**Installation:**
No additional packages needed. Uses existing stack.

## Architecture Patterns

### Recommended Project Structure
```
src/
  pages/
    Contributie/
      ContributieList.jsx    # Main list page (new)
  hooks/
    useFees.js               # Fee data hook (new)
includes/
  class-membership-fees.php  # Extended with pro-rata methods
  class-rest-api.php         # New endpoint registered here
```

### Pattern 1: Sidebar Navigation Entry
**What:** Adding navigation items to Layout.jsx's navigation array
**When to use:** When adding new top-level sections
**Example:**
```javascript
// Source: src/components/layout/Layout.jsx lines 45-55
const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'Leden', href: '/people', icon: Users },
  { name: 'Contributie', href: '/contributie', icon: Coins, indent: true },  // NEW - below Leden
  { name: 'VOG', href: '/vog', icon: FileCheck, indent: true },
  { name: 'Teams', href: '/teams', icon: Building2 },
  // ...
];
```

### Pattern 2: List Page with Filters (VOGList pattern)
**What:** Tabular list with filter dropdown and data fetching
**When to use:** For list pages with server-side data and client filtering
**Example:**
```javascript
// Source: src/pages/VOG/VOGList.jsx - simplified pattern
export default function ContributieList() {
  const [filter, setFilter] = useState('all');
  const { data, isLoading, error } = useFeeList({ filter });

  // Loading/error states
  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorState onRetry={handleRefresh} />;
  if (!data?.length) return <EmptyState />;

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* Filter dropdown */}
        <FilterDropdown value={filter} onChange={setFilter} />

        {/* Table */}
        <div className="card overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            {/* Headers and rows */}
          </table>
        </div>
      </div>
    </PullToRefreshWrapper>
  );
}
```

### Pattern 3: Pro-rata Calculation
**What:** Quarter-based percentage adjustment based on registration date
**When to use:** After family discount is calculated, before final fee
**Example:**
```php
// Source: Phase 126 CONTEXT.md decisions
/**
 * Get pro-rata percentage based on registration month.
 *
 * July-Sept (Q1): 100%
 * Oct-Dec (Q2): 75%
 * Jan-Mar (Q3): 50%
 * Apr-Jun (Q4): 25%
 *
 * @param string $registration_date Date in Y-m-d format.
 * @return float Pro-rata percentage (0.25 to 1.0).
 */
public function get_prorata_percentage( string $registration_date ): float {
    $month = (int) date( 'n', strtotime( $registration_date ) );

    if ( $month >= 7 && $month <= 9 ) {
        return 1.0;   // July-September: 100%
    }
    if ( $month >= 10 && $month <= 12 ) {
        return 0.75;  // October-December: 75%
    }
    if ( $month >= 1 && $month <= 3 ) {
        return 0.50;  // January-March: 50%
    }
    return 0.25;      // April-June: 25%
}
```

### Pattern 4: Address Mismatch Detection
**What:** Identify siblings (same family key, different addresses) for data quality
**When to use:** Flagging data issues in the fee list
**Example:**
```php
// Source: Phase 125 RESEARCH.md family grouping pattern
/**
 * Detect address mismatches within families.
 *
 * Compares youth members with the same last name but different family keys.
 * This indicates potential siblings registered at different addresses.
 *
 * @return array Person IDs with potential mismatches.
 */
public function detect_address_mismatches(): array {
    // Group by last name + age range (youth only)
    // Compare family keys within each group
    // Return IDs where same last name has different family keys
}
```

### Anti-Patterns to Avoid
- **Calculating fees client-side:** Server should calculate; frontend only displays
- **Hardcoding pro-rata percentages in React:** Keep business logic in PHP
- **Fetching all people then filtering:** Use server-side endpoint with pagination
- **Adding registration date field to core ACF group:** Use custom fields system instead

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Fee base calculation | New calculation | MembershipFees::calculate_fee() | Already handles age groups, teams |
| Family discount | Custom grouping | MembershipFees::calculate_fee_with_family_discount() | Phase 125 complete |
| Address normalization | String matching | MembershipFees::get_family_key() | Handles Dutch postal codes |
| Navigation counts | Manual query | useVOGCount pattern | Established hook pattern |
| Table sorting | Custom logic | SortableHeader component | Used in VOGList |
| Pull to refresh | Event handling | PullToRefreshWrapper | Standard component |
| Date formatting | Manual format | format() from dateFormat.js | Consistent formatting |

**Key insight:** The MembershipFees class already handles 90% of the calculation logic. Pro-rata is a simple extension applied after family discount. The main work is creating the REST endpoint and React list page.

## Common Pitfalls

### Pitfall 1: Missing Registration Date Field
**What goes wrong:** Pro-rata cannot be calculated without Sportlink registration date
**Why it happens:** Field does not exist in ACF schema (verified by grep search)
**How to avoid:** Add `registration_date` custom field via existing custom fields system, or add to ACF schema
**Warning signs:** Null registration dates for most members

### Pitfall 2: Pro-rata Applied to Wrong Amount
**What goes wrong:** Pro-rata applied to base fee instead of after-discount fee
**Why it happens:** Incorrect order of operations
**How to avoid:** Calculate: base_fee -> apply family discount -> apply pro-rata -> final_fee
**Warning signs:** Pro-rata savings larger than expected

### Pitfall 3: Month Boundary Edge Cases
**What goes wrong:** July 1st treated as June, December 31st treated as January
**Why it happens:** Timezone issues in date parsing
**How to avoid:** Use date() with explicit timestamp from strtotime()
**Warning signs:** Members joining on month boundaries getting wrong percentage

### Pitfall 4: Address Mismatch False Positives
**What goes wrong:** Unrelated people with same last name flagged as mismatches
**Why it happens:** Matching on last name alone without additional validation
**How to avoid:** Consider additional signals: similar ages, relationship data, parent names
**Warning signs:** High false positive rate in mismatch filter

### Pitfall 5: Performance with Large Member Lists
**What goes wrong:** Slow page load when calculating fees for 1400+ members
**Why it happens:** Calculating all fees on page load
**How to avoid:** Server-side pagination, pre-compute family groups, cache results
**Warning signs:** Page takes >3 seconds to load

### Pitfall 6: Sidebar Navigation Order
**What goes wrong:** "Contributie" appears in wrong position
**Why it happens:** Array order in navigation constant
**How to avoid:** Insert between 'Leden' and 'VOG' in navigation array with `indent: true`
**Warning signs:** Visual inspection shows wrong ordering

## Code Examples

Verified patterns from existing codebase:

### REST Endpoint Registration Pattern
```php
// Source: includes/class-rest-api.php lines 22-40
register_rest_route(
    'rondo/v1',
    '/fees',
    [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_fee_list' ],
        'permission_callback' => [ $this, 'check_user_approved' ],
        'args'                => [
            'season' => [
                'default'           => null,
                'validate_callback' => function ( $param ) {
                    return preg_match( '/^\d{4}-\d{4}$/', $param );
                },
            ],
            'filter' => [
                'default'           => 'all',
                'validate_callback' => function ( $param ) {
                    return in_array( $param, [ 'all', 'mismatches', 'valid' ], true );
                },
            ],
        ],
    ]
);
```

### React Hook Pattern
```javascript
// Source: src/hooks/useVOGCount.js
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export const feeKeys = {
  all: ['fees'],
  list: (filters) => [...feeKeys.all, 'list', filters],
};

export function useFeeList(filters = {}) {
  return useQuery({
    queryKey: feeKeys.list(filters),
    queryFn: async () => {
      const response = await prmApi.getFeeList(filters);
      return response.data;
    },
  });
}
```

### Table Row with Pro-rata Highlight
```javascript
// Source: src/pages/VOG/VOGList.jsx VOGRow pattern
function FeeRow({ member, isOdd }) {
  const hasProrata = member.prorata_percentage < 1.0;

  return (
    <tr className={`${isOdd ? 'bg-gray-50' : 'bg-white'} ${
      hasProrata ? 'bg-amber-50 dark:bg-amber-900/10' : ''
    }`}>
      <td>{member.name}</td>
      <td>{member.age_group}</td>
      <td>{formatCurrency(member.base_fee)}</td>
      <td>{formatDiscount(member.family_discount_rate)}</td>
      <td>
        {hasProrata && (
          <span className="text-amber-600">
            {Math.round(member.prorata_percentage * 100)}%
          </span>
        )}
      </td>
      <td className="font-medium">{formatCurrency(member.final_fee)}</td>
    </tr>
  );
}
```

### Filter Dropdown Pattern
```javascript
// Source: src/pages/VOG/VOGList.jsx lines 630-650
<div className="relative" ref={filterRef}>
  <button
    onClick={() => setIsFilterOpen(!isFilterOpen)}
    className={`btn-secondary ${filter !== 'all' ? 'bg-accent-50 text-accent-700' : ''}`}
  >
    <Filter className="w-4 h-4 md:mr-2" />
    <span className="hidden md:inline">Filter</span>
  </button>

  {isFilterOpen && (
    <div className="absolute top-full left-0 mt-2 w-64 bg-white rounded-lg shadow-lg z-50">
      {/* Filter options */}
    </div>
  )}
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual fee assignment | Calculated from leeftijdsgroep | Phase 124 | Base fee automated |
| No family discount | Tiered 0%/25%/50% | Phase 125 | Family discount working |
| N/A | Pro-rata by quarter | Phase 126 | New feature |

**Deprecated/outdated:**
- None for this phase - building on fresh Phase 124-125 foundation

## Open Questions

Things that couldn't be fully resolved:

1. **Registration Date Field Source**
   - What we know: Sportlink registration date needed per PRO-01
   - What's unclear: Whether to use existing custom field or add new ACF field
   - Recommendation: Add as custom field via existing custom fields system. Name: `registration_date`, type: date

2. **Address Mismatch Definition**
   - What we know: Siblings at different addresses should be flagged
   - What's unclear: How to reliably detect siblings vs. unrelated people with same name
   - Recommendation: Compare youth members with same last name, different family keys. High false positive is acceptable for data quality review.

3. **Empty State When No Registration Dates**
   - What we know: Many members may lack registration dates initially
   - What's unclear: How to handle bulk of members without dates
   - Recommendation: Show in list with "Geen datum" indicator, calculate as 100% pro-rata (full fee)

4. **Season Boundary for Pro-rata**
   - What we know: Season is July-June per get_season_key()
   - What's unclear: Should pro-rata reset at season start or registration anniversary?
   - Recommendation: Pro-rata based on registration within current season. Members from previous seasons pay 100%.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-membership-fees.php` - Complete Phase 124-125 implementation
- `/Users/joostdevalk/Code/stadion/src/components/layout/Layout.jsx` - Sidebar navigation pattern
- `/Users/joostdevalk/Code/stadion/src/pages/VOG/VOGList.jsx` - Complete list page pattern
- `/Users/joostdevalk/Code/stadion/src/App.jsx` - Routing configuration
- `/Users/joostdevalk/Code/stadion/.planning/phases/125-family-discount/125-RESEARCH.md` - Family grouping patterns

### Secondary (MEDIUM confidence)
- `/Users/joostdevalk/Code/stadion/src/hooks/useVOGCount.js` - Hook pattern for list data
- `/Users/joostdevalk/Code/stadion/src/api/client.js` - API client patterns

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, no new dependencies
- Architecture: HIGH - Follows established VOGList pattern exactly
- Pro-rata calculation: HIGH - Simple month-based percentage logic
- Address mismatch: MEDIUM - Edge cases in sibling detection need refinement
- Registration date field: MEDIUM - Field doesn't exist yet, needs creation

**Research date:** 2026-01-31
**Valid until:** 2026-03-02 (30 days - stable domain, no external dependencies)

---

## Key Findings Summary

1. **Sidebar placement:** Add to navigation array after 'Leden' with `indent: true`, before 'VOG'
2. **Pro-rata is straightforward:** Month-based quarter lookup, applied after family discount
3. **Registration date field missing:** Must be created - recommend custom field named `registration_date`
4. **VOGList is the template:** Same list pattern, filter dropdown, table structure
5. **REST endpoint needed:** New `/rondo/v1/fees` endpoint returning calculated fee data
6. **Address mismatch detection:** Compare last names within youth categories with different family keys
7. **Pro-rata applies after family discount:** Order: base_fee -> discount -> prorata -> final
