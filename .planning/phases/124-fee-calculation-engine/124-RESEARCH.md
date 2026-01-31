# Phase 124: Fee Calculation Engine - Research

**Researched:** 2026-01-31
**Domain:** PHP service class for membership fee calculation
**Confidence:** HIGH

## Summary

This phase implements a fee calculation engine that determines the correct membership fee for each person based on their age group (leeftijdsgroep), team membership, and member function. The calculation follows a priority hierarchy: Youth fees based on age group, then Recreant fees for seniors in recreational teams, then Donateur fees for supporting members.

The existing codebase already has the foundation in place from Phase 123: a `MembershipFees` service class that stores and retrieves fee settings. This phase extends that service with calculation logic that parses leeftijdsgroep values, determines member type, and applies the appropriate fee.

**Primary recommendation:** Extend the existing `MembershipFees` class with calculation methods that follow the established pattern in `VOGEmail` class. Use on-demand calculation with optional season-based caching via post meta for fee snapshots.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | Core | Fee settings storage | Already used by MembershipFees class |
| WordPress Post Meta API | Core | Season snapshot storage | Native WP pattern for entity-attached data |
| ACF Pro | Latest | Custom field access | Already required, provides get_field() |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WP_Query | Core | Query people with teams | Fetching members with work_history |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Post meta for snapshots | Custom DB table | Post meta is simpler, follows AGENTS.md rules |
| Calculate on-demand | Background cron | On-demand simpler, cron adds complexity |

**Installation:**
No additional packages needed. Uses existing WordPress and ACF APIs.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-membership-fees.php     # Extended with calculation methods
```

### Pattern 1: Service Class Extension
**What:** Add calculation methods to existing MembershipFees class
**When to use:** When building on existing service foundation
**Example:**
```php
// Source: Existing class-membership-fees.php pattern
namespace Stadion\Fees;

class MembershipFees {
    // Existing methods: get_all_settings(), get_fee(), update_settings()

    // NEW: Calculate fee for a person
    public function calculate_fee( int $person_id ): ?array {
        // Parse leeftijdsgroep
        // Determine member type
        // Return fee info
    }

    // NEW: Parse leeftijdsgroep to fee category
    public function parse_age_group( string $leeftijdsgroep ): ?string {
        // Strip suffixes, map to category
    }
}
```

### Pattern 2: Member Type Priority Resolution
**What:** Hierarchical determination of which fee applies
**When to use:** When member could qualify for multiple fee types
**Example:**
```php
// Priority: Youth > Recreant > Donateur
// If member has valid age group, they get age-based fee
// If senior in recreational team, they get recreant fee
// If only Donateur function, they get donateur fee
```

### Pattern 3: Season Snapshot Storage
**What:** Store calculated fees in post meta for season locking
**When to use:** When fees need to be frozen at season start
**Example:**
```php
// Store fee snapshot in post meta
$snapshot_key = 'fee_snapshot_' . $this->get_season_key(); // e.g., '2025-2026'
update_post_meta( $person_id, $snapshot_key, [
    'category'      => 'junior',
    'base_fee'      => 230,
    'calculated_at' => current_time( 'Y-m-d H:i:s' ),
]);

// Season key format: July 1, 2025 to June 30, 2026 = '2025-2026'
public function get_season_key( ?string $date = null ): string {
    $timestamp = $date ? strtotime( $date ) : time();
    $month = (int) date( 'n', $timestamp );
    $year = (int) date( 'Y', $timestamp );

    // July starts new season
    $season_start_year = $month >= 7 ? $year : $year - 1;
    return $season_start_year . '-' . ( $season_start_year + 1 );
}
```

### Anti-Patterns to Avoid
- **Custom database tables:** Against AGENTS.md rules, use post meta instead
- **Calculating during sync:** Calculate on-demand to avoid stale data
- **Hardcoding age ranges:** Use constants/configuration for maintainability

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Storing fee settings | Custom table | Options API | Already implemented in Phase 123 |
| Caching calculations | Custom cache | Post meta with season key | Native WP, no cache invalidation needed |
| Getting person's teams | Custom SQL | ACF work_history field | Already tracked via work_history repeater |

**Key insight:** The data model already exists. Persons have leeftijdsgroep via custom field and team assignments via work_history repeater. No new data structures needed for calculation.

## Common Pitfalls

### Pitfall 1: Incorrect Age Group Format
**What goes wrong:** Using "JO6" format instead of "Onder 6" format
**Why it happens:** Requirements documents use JO notation, actual data uses "Onder X"
**How to avoid:** Parse "Onder X" format, not "JOx" format
**Warning signs:** All age groups returning as unknown category

### Pitfall 2: Not Stripping Suffixes
**What goes wrong:** "Onder 11 Meiden" not matching to Pupil category
**Why it happens:** Comparing full string instead of base age group
**How to avoid:** Strip " Meiden" and " Vrouwen" suffixes before parsing
**Warning signs:** Female youth not getting correct fee

### Pitfall 3: Not Checking Team Names Case-Insensitively
**What goes wrong:** "Recreanten" team not triggering recreant fee
**Why it happens:** Case-sensitive string comparison
**How to avoid:** Use stripos() for case-insensitive contains check
**Warning signs:** Known recreational players getting Senior fee

### Pitfall 4: Werkfuncties vs Function Field
**What goes wrong:** Looking for wrong field to detect Donateur
**Why it happens:** Multiple similar fields exist (werkfuncties, type-lid)
**How to avoid:** CONTEXT.md specifies "Function = Donateur" - check werkfuncties array
**Warning signs:** Donateurs not being detected

### Pitfall 5: Season Boundary Edge Cases
**What goes wrong:** June 30 and July 1 assigned to wrong season
**Why it happens:** Off-by-one in month comparison
**How to avoid:** July (month 7+) = new season, June (month 6) = old season
**Warning signs:** Members assigned to wrong season at boundary

## Code Examples

Verified patterns from existing codebase:

### Age Group Parsing (from PeopleList.jsx leeftijdsgroep options)
```php
// Source: Analysis of src/pages/People/PeopleList.jsx options
// Data format: "Onder 6", "Onder 7", ..., "Onder 19", "Senioren", "Senioren Vrouwen"
// With Meiden suffix: "Onder 9 Meiden", "Onder 11 Meiden", etc.

public function parse_age_group( string $leeftijdsgroep ): ?string {
    // Normalize: strip suffixes
    $normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $leeftijdsgroep ) );

    // Handle Senioren
    if ( strtolower( $normalized ) === 'senioren' ) {
        return 'senior';
    }

    // Extract number from "Onder X" format
    if ( preg_match( '/^Onder\s+(\d+)$/i', $normalized, $matches ) ) {
        $age = (int) $matches[1];

        // Map to fee category
        if ( $age >= 6 && $age <= 7 ) {
            return 'mini';
        }
        if ( $age >= 8 && $age <= 11 ) {
            return 'pupil';
        }
        if ( $age >= 12 && $age <= 19 ) {
            return 'junior';
        }
    }

    return null; // Unknown format
}
```

### Team Name Check for Recreant (from CONTEXT.md)
```php
// Source: CONTEXT.md team-based fee determination
public function is_recreational_team( int $team_id ): bool {
    $team = get_post( $team_id );
    if ( ! $team || 'team' !== $team->post_type ) {
        return false;
    }

    $name = strtolower( $team->post_title );
    return ( stripos( $name, 'recreant' ) !== false ||
             stripos( $name, 'walking football' ) !== false );
}
```

### Donateur Detection (from PersonDetail.jsx)
```php
// Source: src/pages/People/PersonDetail.jsx lines 91-92
public function is_donateur( int $person_id ): bool {
    $werkfuncties = get_field( 'werkfuncties', $person_id ) ?: [];

    // If ONLY function is Donateur, they are Donateur
    if ( empty( $werkfuncties ) ) {
        return false;
    }

    // Check if Donateur is the only function
    return count( $werkfuncties ) === 1 &&
           in_array( 'Donateur', $werkfuncties, true );
}
```

### Getting Person's Current Teams (from work_history pattern)
```php
// Source: includes/class-rest-teams.php work_history processing
public function get_current_teams( int $person_id ): array {
    $work_history = get_field( 'work_history', $person_id ) ?: [];
    $current_teams = [];

    foreach ( $work_history as $job ) {
        if ( ! isset( $job['team'] ) || ! $job['team'] ) {
            continue;
        }

        // Check if current position
        $is_current = ! empty( $job['is_current'] );
        $end_date = $job['end_date'] ?? '';

        // If end_date is in future or empty with is_current, they're current
        if ( $is_current && ( empty( $end_date ) || strtotime( $end_date ) >= time() ) ) {
            $current_teams[] = (int) $job['team'];
        }
    }

    return array_unique( $current_teams );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded fee values | Configurable via Settings | Phase 123 | Fees now stored in wp_options |
| N/A | MembershipFees service class | Phase 123 | Foundation for calculation |

**Deprecated/outdated:**
- None for this phase - building on fresh Phase 123 foundation

## Open Questions

Things that couldn't be fully resolved:

1. **Exact werkfuncties values in production**
   - What we know: "Donateur" is one value, there are others
   - What's unclear: Complete list of possible werkfuncties values
   - Recommendation: Handle "Donateur" specifically, treat others as non-donateur

2. **Edge case: Senior without any team**
   - What we know: CONTEXT.md says exclude if no team assignment
   - What's unclear: Should Donateur function still apply if they have no team?
   - Recommendation: Donateur detection does not require team assignment

3. **Existing fee snapshot data**
   - What we know: Season snapshot is a new feature
   - What's unclear: Whether any legacy fee data exists to migrate
   - Recommendation: Start fresh, no migration needed for new feature

## Sources

### Primary (HIGH confidence)
- `includes/class-membership-fees.php` - Existing service class structure
- `includes/class-vog-email.php` - Similar service class pattern
- `includes/class-rest-people.php` - Data access patterns
- `src/pages/People/PeopleList.jsx` - Leeftijdsgroep value list
- `src/pages/People/PersonDetail.jsx` - Werkfuncties detection pattern
- `acf-json/group_person_fields.json` - Person ACF field structure

### Secondary (MEDIUM confidence)
- `124-CONTEXT.md` - User decisions (note: uses JO notation vs actual "Onder X" format)

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Uses existing WordPress/ACF patterns
- Architecture: HIGH - Extends existing MembershipFees class
- Data format: HIGH - Verified from actual codebase (PeopleList.jsx options)
- Pitfalls: MEDIUM - Based on codebase analysis, may discover more during implementation

**Research date:** 2026-01-31
**Valid until:** 2026-03-02 (30 days - stable domain, no external dependencies)

---

## Key Findings Summary

1. **Age group format is "Onder X", not "JOx"** - Parse "Onder 6" through "Onder 19" plus "Senioren"
2. **Werkfuncties field for Donateur detection** - Check if only function is "Donateur"
3. **Work_history for team assignment** - Filter by is_current flag and end_date
4. **Season runs July 1 - June 30** - Use season key like "2025-2026" for snapshot storage
5. **Case-insensitive team name matching** - Use stripos() for "recreant" and "walking football"
