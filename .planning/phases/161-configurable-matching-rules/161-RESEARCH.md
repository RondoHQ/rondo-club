# Phase 161: Configurable Matching Rules - Research

**Researched:** 2026-02-09
**Domain:** WordPress options data model extension + PHP/React UI for configurable team and werkfunctie matching
**Confidence:** HIGH

## Summary

Phase 161 replaces hardcoded team name matching (`is_recreational_team()`) and werkfunctie checking (`is_donateur()`) with configurable per-category matching rules. Currently, `class-membership-fees.php` contains:
- Lines 335-345: `is_recreational_team()` checks if team title contains "recreant", "walking football", or "walking voetbal"
- Lines 355-364: `is_donateur()` checks if person has exactly one werkfunctie and it is "Donateur"
- Lines 405-446: `calculate_fee()` uses these hardcoded checks to assign 'recreant' or 'donateur' categories

This phase moves these matching rules into the per-category configuration within each season's data structure, allowing administrators to explicitly select which teams and werkfuncties trigger specific fee categories. This is the final phase of v21.0, completing the transition from all hardcoded fee logic to fully configurable per-season rules.

The implementation extends the existing infrastructure from Phases 155-160:
- Per-season category data structure (Phase 155)
- Age class matching via configuration (Phase 156)
- Category CRUD via REST API (Phase 157)
- Settings UI with drag-and-drop (Phase 158)
- Separate discount configuration pattern (Phase 160)

The primary work is: (1) extend category data structure with `matching_teams` and `matching_werkfuncties` arrays, (2) update `calculate_fee()` to check category config instead of calling hardcoded helper methods, (3) add multi-select team and werkfunctie inputs to the FeeCategorySettings UI, and (4) pre-populate existing categories with current hardcoded matching rules to ensure backward compatibility.

**Primary recommendation:** Store matching rules as arrays within each category object (`matching_teams` as post IDs, `matching_werkfuncties` as strings). Update `calculate_fee()` to check if person's current teams or werkfuncties match any rules in the category config. Extend FeeCategorySettings component with multi-select dropdowns that fetch available teams via `/wp/v2/team` and werkfuncties from a static list. Pre-populate 'recreant' category with recreational team IDs and 'donateur' category with ["Donateur"] werkfunctie on first load.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | Core | Per-season category storage | Already used for `rondo_membership_fees_{season}` |
| WordPress REST API | Core | Team data retrieval | Standard `/wp/v2/team` endpoint exists |
| react-hook-form | ^7.49.0 | Form state/validation | Project standard for all forms |
| @tanstack/react-query | ^5.17.0 | Server state management | Standard for all settings API calls |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ACF Pro | Latest | Read werkfuncties field | Already required, provides `get_field()` |
| Tailwind CSS | ^3.4.0 | Styling | Consistent design system |
| lucide-react | ^0.309.0 | Icons | Standard icon library |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Per-category rules | Global matching config | Per-category more flexible, allows overlap |
| Post IDs for teams | Team slugs or names | Post IDs stable, prevent rename issues |
| Array of werkfuncties | Single werkfunctie string | Array allows multiple matches per category |
| Multi-select dropdown | Text input with comma separation | Dropdown prevents typos, shows available options |

**Installation:**
No new packages needed. Uses existing WordPress APIs and installed React libraries.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-membership-fees.php     # Update calculate_fee() to check category matching rules

src/pages/Settings/
  FeeCategorySettings.jsx       # Add matching rules inputs to category edit modal
```

### Pattern 1: Category Data Structure Extension
**What:** Add matching_teams and matching_werkfuncties arrays to category objects
**When to use:** When existing data structure needs additional configuration fields
**Example:**
```php
// Source: Existing category structure (Phase 155-158)
$categories = [
    'recreant' => [
        'label'                   => 'Recreant',
        'amount'                  => 145,
        'age_classes'             => ['Senioren'],
        'is_youth'                => false,
        'sort_order'              => 2,
        // NEW: Matching rules
        'matching_teams'          => [123, 456, 789], // Team post IDs
        'matching_werkfuncties'   => [],
    ],
    'donateur' => [
        'label'                   => 'Donateur',
        'amount'                  => 75,
        'age_classes'             => [],
        'is_youth'                => false,
        'sort_order'              => 5,
        // NEW: Matching rules
        'matching_teams'          => [],
        'matching_werkfuncties'   => ['Donateur'],
    ],
];
```

### Pattern 2: Config-Driven Matching Logic
**What:** Replace hardcoded helper methods with category config checks
**When to use:** When business logic should be data-driven instead of code-driven
**Example:**
```php
// BEFORE (hardcoded):
if ( $this->is_recreational_team( $team_id ) ) {
    // Use recreant fee
}

// AFTER (config-driven):
$categories = $this->get_categories_for_season( $season );
foreach ( $categories as $slug => $category ) {
    $matching_teams = $category['matching_teams'] ?? [];
    if ( in_array( $team_id, $matching_teams, true ) ) {
        // This category matches this team
        return $slug;
    }
}
```

### Pattern 3: Multi-Select UI with Query Data
**What:** Use React Query to fetch teams, display as multi-select dropdown
**When to use:** When admin needs to select from dynamic database records
**Example:**
```jsx
// Source: Similar pattern in Phase 158 for age classes
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';

function TeamSelector({ value, onChange }) {
  const { data: teams } = useQuery({
    queryKey: ['teams'],
    queryFn: async () => {
      const response = await wpApi.get('/wp/v2/team', {
        params: { per_page: 100 }
      });
      return response.data;
    }
  });

  return (
    <select multiple value={value} onChange={onChange}>
      {teams?.map(team => (
        <option key={team.id} value={team.id}>
          {team.title.rendered}
        </option>
      ))}
    </select>
  );
}
```

### Pattern 4: Pre-Population Migration
**What:** One-time enrichment of existing categories with matching rules that replicate hardcoded behavior
**When to use:** When replacing hardcoded logic must not break existing calculations
**Example:**
```php
// In get_categories_for_season(), detect missing matching rules and populate defaults
private function maybe_migrate_matching_rules( array $categories ): array {
    $needs_migration = false;

    // Pre-populate 'recreant' with recreational teams
    if ( isset( $categories['recreant'] ) && ! isset( $categories['recreant']['matching_teams'] ) ) {
        $needs_migration = true;
        $categories['recreant']['matching_teams'] = $this->find_recreational_team_ids();
    }

    // Pre-populate 'donateur' with Donateur werkfunctie
    if ( isset( $categories['donateur'] ) && ! isset( $categories['donateur']['matching_werkfuncties'] ) ) {
        $needs_migration = true;
        $categories['donateur']['matching_werkfuncties'] = ['Donateur'];
    }

    // Ensure all other categories have empty arrays (no matching rules)
    foreach ( $categories as $slug => $category ) {
        if ( ! isset( $category['matching_teams'] ) ) {
            $categories[$slug]['matching_teams'] = [];
        }
        if ( ! isset( $category['matching_werkfuncties'] ) ) {
            $categories[$slug]['matching_werkfuncties'] = [];
        }
    }

    return $categories;
}

private function find_recreational_team_ids(): array {
    // Query all teams and filter by name matching current hardcoded logic
    $query = new \WP_Query([
        'post_type'      => 'team',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $recreational_ids = [];
    foreach ( $query->posts as $team_id ) {
        if ( $this->is_recreational_team( $team_id ) ) {
            $recreational_ids[] = $team_id;
        }
    }

    return $recreational_ids;
}
```

### Anti-Patterns to Avoid
- **Global matching config separate from categories:** Matching rules belong to categories, not in a separate option
- **Team names instead of IDs:** Names can change, IDs are stable
- **Single-select for werkfuncties:** Multiple werkfuncties could trigger the same category
- **Removing old helper methods immediately:** Keep `is_recreational_team()` and `is_donateur()` as deprecated private methods for migration logic, remove in future version

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Team dropdown list | Manual POST request loop | WordPress REST `/wp/v2/team` with per_page=100 | Standard WP endpoint, automatic serialization |
| Werkfuncties list | Database query for unique values | Static array of known values | Werkfuncties are limited set, querying all people is expensive |
| Multi-select UI | Custom checkbox list | React `<select multiple>` or react-select library | Native HTML multi-select is sufficient, react-select if better UX needed |
| Matching logic | Complex priority/scoring system | Simple `in_array()` check per category | Business logic is "does person match this category rule?" not "which category scores highest" |

**Key insight:** WordPress and React have built-in solutions for most of this. The only custom logic needed is the migration pre-population and the category matching checks in `calculate_fee()`.

## Common Pitfalls

### Pitfall 1: Forgetting to Migrate Existing Categories
**What goes wrong:** Existing 'recreant' and 'donateur' categories have no matching rules, so no one gets assigned those fees after upgrade
**Why it happens:** Phase 161 adds new fields but doesn't populate them for existing data
**How to avoid:** Use `maybe_migrate_matching_rules()` in `get_categories_for_season()` to detect missing fields and populate defaults on first load
**Warning signs:** After deployment, all recreational team members show as "senior" instead of "recreant" in fee calculations

### Pitfall 2: Werkfunctie String Matching Case Sensitivity
**What goes wrong:** Person has "donateur" werkfunctie but doesn't match category with "Donateur" (capital D)
**Why it happens:** ACF stores werkfuncties as user-entered strings, case could vary
**How to avoid:** Use `strcasecmp()` for case-insensitive string matching when checking werkfuncties
**Warning signs:** Some people with Donateur function get excluded from fee calculation

### Pitfall 3: Not Handling Multiple Matching Categories
**What goes wrong:** Person's team matches multiple categories, unclear which category wins
**Why it happens:** Admin configured overlapping matching rules
**How to avoid:** Use first-match-wins based on category sort_order (lowest sort_order has priority)
**Warning signs:** Fee calculation returns inconsistent categories for the same person

### Pitfall 4: Team Query Performance
**What goes wrong:** Loading `/wp/v2/team?per_page=100` is slow or times out if club has 100+ teams
**Why it happens:** Default pagination limit is 100, large clubs might exceed this
**How to avoid:** Check team count first, if >100 use search/filter UI instead of loading all teams at once
**Warning signs:** Settings page hangs when opening category edit modal

### Pitfall 5: Deleting a Team Breaks Category Config
**What goes wrong:** Category has matching_teams=[123] but team 123 was deleted, causes fee calculation errors
**Why it happens:** WordPress allows deleting posts without checking references
**How to avoid:** Filter out invalid team IDs in `calculate_fee()` by verifying post exists before checking match
**Warning signs:** PHP warnings "get_post() expects parameter 1 to be valid post ID" in error logs

## Code Examples

Verified patterns from existing codebase:

### Fetching Teams via REST API
```jsx
// Source: Standard WordPress REST API pattern
import { useQuery } from '@tanstack/react-query';
import { wpApi } from '@/api/client';

const { data: teams, isLoading } = useQuery({
  queryKey: ['teams', 'all'],
  queryFn: async () => {
    const response = await wpApi.get('/wp/v2/team', {
      params: {
        per_page: 100,
        orderby: 'title',
        order: 'asc',
      }
    });
    return response.data;
  },
});
```

### Category Matching in calculate_fee()
```php
// Source: Derived from existing calculate_fee() pattern (lines 383-469)
public function calculate_fee( int $person_id, ?string $season = null ): ?array {
    // Get leeftijdsgroep and check age class matching (existing logic)
    $leeftijdsgroep = get_field( 'leeftijdsgroep', $person_id );
    $category       = null;

    if ( ! empty( $leeftijdsgroep ) ) {
        $category = $this->get_category_by_age_class( $leeftijdsgroep, $season );
    }

    // Youth categories have priority (existing logic)
    $youth_categories = $this->get_youth_category_slugs( $season );
    if ( in_array( $category, $youth_categories, true ) ) {
        return [
            'category'       => $category,
            'base_fee'       => $this->get_fee( $category, $season ),
            'leeftijdsgroep' => $leeftijdsgroep,
            'person_id'      => $person_id,
        ];
    }

    // NEW: Check team matching rules
    $teams      = $this->get_current_teams( $person_id );
    $team_match = $this->get_category_by_team_match( $teams, $season );

    if ( $team_match !== null ) {
        return [
            'category'       => $team_match,
            'base_fee'       => $this->get_fee( $team_match, $season ),
            'leeftijdsgroep' => $leeftijdsgroep,
            'person_id'      => $person_id,
        ];
    }

    // NEW: Check werkfunctie matching rules
    $werkfuncties      = get_field( 'werkfuncties', $person_id ) ?: [];
    $werkfunctie_match = $this->get_category_by_werkfunctie_match( $werkfuncties, $season );

    if ( $werkfunctie_match !== null ) {
        return [
            'category'       => $werkfunctie_match,
            'base_fee'       => $this->get_fee( $werkfunctie_match, $season ),
            'leeftijdsgroep' => $leeftijdsgroep,
            'person_id'      => $person_id,
        ];
    }

    // No valid category - exclude from fee calculation
    return null;
}

/**
 * Get category by team matching rules
 */
private function get_category_by_team_match( array $team_ids, ?string $season = null ): ?string {
    if ( empty( $team_ids ) ) {
        return null;
    }

    $categories = $this->get_categories_for_season( $season );

    // Sort by sort_order so lowest sort_order wins on overlap
    uasort( $categories, function ( $a, $b ) {
        return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
    } );

    foreach ( $categories as $slug => $category ) {
        $matching_teams = $category['matching_teams'] ?? [];

        if ( empty( $matching_teams ) ) {
            continue;
        }

        // Check if any of person's teams match this category
        foreach ( $team_ids as $team_id ) {
            if ( in_array( $team_id, $matching_teams, true ) ) {
                return $slug;
            }
        }
    }

    return null;
}

/**
 * Get category by werkfunctie matching rules
 */
private function get_category_by_werkfunctie_match( array $werkfuncties, ?string $season = null ): ?string {
    if ( empty( $werkfuncties ) ) {
        return null;
    }

    $categories = $this->get_categories_for_season( $season );

    // Sort by sort_order so lowest sort_order wins on overlap
    uasort( $categories, function ( $a, $b ) {
        return ( $a['sort_order'] ?? 999 ) <=> ( $b['sort_order'] ?? 999 );
    } );

    foreach ( $categories as $slug => $category ) {
        $matching_werkfuncties = $category['matching_werkfuncties'] ?? [];

        if ( empty( $matching_werkfuncties ) ) {
            continue;
        }

        // Check if any of person's werkfuncties match this category (case-insensitive)
        foreach ( $werkfuncties as $werkfunctie ) {
            foreach ( $matching_werkfuncties as $match_werkfunctie ) {
                if ( strcasecmp( trim( $werkfunctie ), trim( $match_werkfunctie ) ) === 0 ) {
                    return $slug;
                }
            }
        }
    }

    return null;
}
```

### Multi-Select Team Input
```jsx
// Source: Derived from Phase 158 FeeCategorySettings pattern
function CategoryEditModal({ category, onSave, onClose }) {
  const { data: teams } = useQuery({
    queryKey: ['teams'],
    queryFn: async () => {
      const response = await wpApi.get('/wp/v2/team', {
        params: { per_page: 100 }
      });
      return response.data;
    }
  });

  const [matchingTeams, setMatchingTeams] = useState(category?.matching_teams || []);

  const handleTeamToggle = (teamId) => {
    setMatchingTeams(prev =>
      prev.includes(teamId)
        ? prev.filter(id => id !== teamId)
        : [...prev, teamId]
    );
  };

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium mb-2">
          Teams die deze categorie krijgen
        </label>
        <div className="border rounded-lg max-h-60 overflow-y-auto">
          {teams?.map(team => (
            <label key={team.id} className="flex items-center px-3 py-2 hover:bg-gray-50">
              <input
                type="checkbox"
                checked={matchingTeams.includes(team.id)}
                onChange={() => handleTeamToggle(team.id)}
                className="rounded"
              />
              <span className="ml-2">{team.title.rendered}</span>
            </label>
          ))}
        </div>
      </div>
    </div>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded `is_recreational_team()` string matching | Configurable matching_teams array per category | Phase 161 (2026-02) | Admin can add/remove recreational teams without code changes |
| Hardcoded `is_donateur()` werkfunctie check | Configurable matching_werkfuncties array per category | Phase 161 (2026-02) | Multiple werkfuncties can trigger same category, new special member types possible |
| Business logic in PHP methods | Business logic in data configuration | Phases 155-161 (2026-02) | All fee calculation rules now editable by admin via UI |

**Deprecated/outdated:**
- `is_recreational_team()`: Kept as private method for migration, but no longer used in `calculate_fee()`
- `is_donateur()`: Kept as private method for migration, but no longer used in `calculate_fee()`
- Hardcoded slug checks like `if ( $category === 'senior' )`: Replaced with category config checks

## Open Questions

1. **Should matching rules use AND or OR logic?**
   - What we know: Person can have multiple teams and multiple werkfuncties
   - What's unclear: If category has both matching_teams and matching_werkfuncties, does person need to match BOTH or EITHER?
   - Recommendation: Use OR logic (match either teams OR werkfuncties). Most flexible, matches current behavior where recreant checks only teams and donateur checks only werkfuncties.

2. **What happens if person matches multiple categories via different rules?**
   - What we know: Current logic has priority hierarchy (youth > senior/recreant > donateur)
   - What's unclear: With configurable rules, how do we determine priority when person matches multiple categories?
   - Recommendation: Use category sort_order for priority. Lowest sort_order wins. Admin can control priority by reordering categories.

3. **Should we validate that werkfuncties in matching rules actually exist?**
   - What we know: Werkfuncties are stored as free-text strings in ACF repeater, no centralized list
   - What's unclear: Should we query all existing werkfunctie values and validate against them?
   - Recommendation: No validation. Allow any string. Admin responsibility to ensure correct spelling. Querying all people for werkfunctie values is expensive.

4. **Should we show warning if recreational teams are missing from 'recreant' category?**
   - What we know: After migration, 'recreant' category will have current recreational teams pre-populated
   - What's unclear: If new recreational teams are created, should UI warn that they're not in any category's matching rules?
   - Recommendation: No automated warning. Too complex. Admin can manually add new teams to matching rules as needed.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-membership-fees.php` - Lines 335-469: Current hardcoded matching logic
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Settings/FeeCategorySettings.jsx` - Existing Phase 158 UI patterns
- `.planning/phases/155-fee-category-data-model/` - Category data structure foundation
- `.planning/phases/156-fee-category-backend-logic/` - Config-driven calculation patterns
- `.planning/phases/160-configurable-family-discount/` - Separate config option pattern
- `.planning/REQUIREMENTS.md` - v21.0 requirements (no formal requirements for Phase 161 yet)
- `.planning/ROADMAP.md` - Phase 161 success criteria

### Secondary (MEDIUM confidence)
- WordPress REST API documentation - `/wp/v2/team` endpoint capabilities
- React Query documentation - Standard patterns for data fetching
- Phase 158 implementation - Multi-select UI patterns, drag-and-drop, season selector

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, no new dependencies
- Architecture: HIGH - Extends established patterns from Phases 155-160
- Pitfalls: HIGH - Based on direct analysis of existing code and data flow
- Migration strategy: MEDIUM - Pre-population logic is straightforward but needs testing to ensure backward compatibility

**Research date:** 2026-02-09
**Valid until:** 60 days (stable domain, unlikely to change)

**Implementation notes:**
- This is the final phase of v21.0 Per-Season Fee Categories
- Success depends on correct migration to avoid breaking existing fee calculations
- Testing should verify that fee results match exactly before/after phase completion
- Consider adding admin notification after migration showing which teams were pre-populated in 'recreant' category
