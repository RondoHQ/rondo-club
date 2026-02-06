# Phase 129: Backend Forecast Calculation - Research

**Researched:** 2026-02-02
**Domain:** WordPress REST API parameter handling, PHP date manipulation, forecast calculation patterns
**Confidence:** HIGH

## Summary

This phase extends the existing v12.0 membership fee calculation infrastructure to support next season forecasting. The research focused on WordPress REST API best practices for optional boolean parameters, PHP date manipulation for season key calculation, and performance optimization patterns for large datasets.

The standard approach is to add an optional `forecast` boolean parameter to the existing `/rondo/v1/fees` endpoint, calculate the next season key using simple year arithmetic, and leverage the existing fee calculation methods with 100% pro-rata override. The infrastructure already exists - this is primarily a conditional logic layer on top of proven calculation methods.

**Primary recommendation:** Extend the existing `get_fee_list()` REST endpoint with a `forecast` boolean parameter that modifies season key (+1 year) and forces 100% pro-rata, reusing all existing calculation logic from `MembershipFees` class.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress REST API | 6.0+ | API parameter handling, route registration | Native WordPress, handles validation/sanitization automatically |
| WP_Query | 6.0+ | Database queries with performance optimization | Native WordPress, mature caching and filter system |
| PHP DateTime/strtotime | PHP 8.0+ | Date calculations and season key derivation | Native PHP, simple for year arithmetic operations |
| ACF (Advanced Custom Fields) | Pro | Field data retrieval | Already used throughout codebase for person data |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| rest_sanitize_boolean() | WP 5.2+ | Boolean parameter sanitization | For forecast parameter validation |
| rest_ensure_response() | WP 4.4+ | Response standardization | For all REST endpoint returns |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Boolean parameter | New endpoint (/fees/forecast) | More RESTful but duplicates logic, existing pattern uses parameters |
| Year arithmetic | DateTime::add(DateInterval) | More robust but overkill for simple +1 year operation |
| Separate forecast method | Conditional in existing method | Cleaner separation but duplicates 90% of calculation logic |

**Installation:**
No new dependencies required - uses existing WordPress core and installed ACF Pro.

## Architecture Patterns

### Recommended Approach
```
Forecast Calculation Flow:
1. REST endpoint receives forecast=true parameter
2. Calculate next season key (current_year + 1 to current_year + 2)
3. Query all person posts (same as current season)
4. For each person:
   - Use existing calculate_fee() method
   - Use existing calculate_fee_with_family_discount() method
   - Override pro-rata to 100% (ignore lid-sinds field)
5. Exclude nikki_total and nikki_saldo from response
6. Return with next season key
```

### Pattern 1: REST API Parameter Extension
**What:** Add optional boolean parameter to existing endpoint
**When to use:** When new functionality is a variant of existing endpoint behavior
**Example:**
```php
// Source: WordPress REST API Handbook + existing /fees endpoint pattern
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
                    return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
                },
            ],
            'forecast' => [
                'default'           => false,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'validate_callback' => 'rest_is_boolean',
            ],
        ],
    ]
);
```

### Pattern 2: Conditional Fee Calculation Logic
**What:** Branch calculation based on forecast flag
**When to use:** When calculation differs only in specific parameters (pro-rata percentage)
**Example:**
```php
// Source: Existing MembershipFees::calculate_full_fee() pattern
public function get_fee_list( $request ) {
    $forecast = $request->get_param( 'forecast' );
    $fees     = new \Stadion\Fees\MembershipFees();

    if ( $forecast ) {
        // Calculate next season key
        $current_season = $fees->get_season_key();
        $season_start_year = (int) substr( $current_season, 0, 4 );
        $season = ($season_start_year + 1) . '-' . ($season_start_year + 2);
    } else {
        $season = $request->get_param( 'season' ) ?? $fees->get_season_key();
    }

    foreach ( $query->posts as $person ) {
        if ( $forecast ) {
            // Override pro-rata to 100%
            $fee_data = $fees->calculate_fee_with_family_discount( $person->ID, $season );
            if ( $fee_data !== null ) {
                $fee_data['prorata_percentage'] = 1.0;
                $fee_data['final_fee'] = $fee_data['fee_after_discount'];
                $fee_data['registration_date'] = null;
            }
        } else {
            // Use normal calculation with lid-sinds pro-rata
            $fee_data = $fees->get_fee_for_person_cached( $person->ID, $season );
        }

        // Build result array
        $result = [ /* person data */ ];

        // Exclude Nikki data for forecast
        if ( !$forecast ) {
            $nikki_year = substr( $season, 0, 4 );
            $result['nikki_total'] = get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_total', true );
            $result['nikki_saldo'] = get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_saldo', true );
        }
    }
}
```

### Pattern 3: Season Key Arithmetic
**What:** Calculate next season key using year extraction and simple arithmetic
**When to use:** When season boundaries are fixed (July 1) and simple year progression suffices
**Example:**
```php
// Source: Existing MembershipFees::get_season_key() pattern
public function get_next_season_key( ?string $current_season = null ): string {
    if ( $current_season === null ) {
        $current_season = $this->get_season_key();
    }

    // Extract start year from "2025-2026" format
    $season_start_year = (int) substr( $current_season, 0, 4 );

    // Next season is +1 year
    $next_start_year = $season_start_year + 1;

    return $next_start_year . '-' . ( $next_start_year + 1 );
}
```

### Anti-Patterns to Avoid
- **Creating duplicate endpoint:** Don't create `/fees/forecast` - extends existing endpoint with parameter
- **Duplicating calculation logic:** Don't copy-paste fee calculation - reuse existing methods with conditional overrides
- **Using DateTime for simple year arithmetic:** Don't use `DateTime::add(new DateInterval('P1Y'))` for +1 year - simple integer math suffices
- **Caching forecast calculations:** Don't save forecast fees to post meta - forecast is always ephemeral, calculated on-demand

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Boolean parameter validation | Custom regex or type checking | `rest_sanitize_boolean()` and `rest_is_boolean()` | WordPress handles '0', '1', 'true', 'false', true, false correctly |
| Date arithmetic for year progression | Manual timestamp calculation | Simple integer arithmetic on year string | Season key is string format "YYYY-YYYY", not timestamp |
| Family discount recalculation | Duplicate grouping logic | `MembershipFees::calculate_fee_with_family_discount()` | Already handles address grouping, sorting, discount tiers |
| Pro-rata override | New calculation method | Conditional override of prorata_percentage field | Existing method supports all scenarios, just override result |
| Query performance optimization | Custom SQL | WP_Query with `no_found_rows` and `fields => 'ids'` | WordPress has mature optimization patterns |

**Key insight:** The forecast calculation is 95% identical to current season calculation - only season key and pro-rata percentage differ. Reusing existing methods with minimal conditional logic prevents bugs and maintains consistency.

## Common Pitfalls

### Pitfall 1: Modifying Season Key Instead of Calculating New
**What goes wrong:** Using `strtotime('+1 year')` on season start date to calculate next season
**Why it happens:** Confusing season key (string "YYYY-YYYY") with date timestamp
**How to avoid:** Treat season key as string, extract year with `substr()`, do integer arithmetic
**Warning signs:** Code uses `strtotime()` or `DateTime` for season key calculation

### Pitfall 2: Caching Forecast Calculations
**What goes wrong:** Saving forecast fees to `stadion_fee_cache_2026-2027` post meta
**Why it happens:** Assuming forecast works like current season with cache invalidation
**How to avoid:** Forecast is always ephemeral - calculate on-demand, never cache
**Warning signs:** Code calls `save_fee_cache()` or `get_fee_for_person_cached()` for forecast

### Pitfall 3: Including Nikki Data in Forecast Response
**What goes wrong:** Returning null values for nikki_total and nikki_saldo in forecast
**Why it happens:** Following existing response structure without filtering
**How to avoid:** Conditionally exclude nikki fields from response array when forecast=true
**Warning signs:** Forecast response has nikki_total: null instead of omitting field

### Pitfall 4: Using suppress_filters => true in WP_Query
**What goes wrong:** Bypassing WordPress caching and plugin hooks
**Why it happens:** Misunderstanding purpose - it's for access control bypass, not performance
**How to avoid:** Use `no_found_rows => true` for performance, keep `suppress_filters => false`
**Warning signs:** Query performance degrades with caching plugins installed

### Pitfall 5: Calculating Age Group Progression
**What goes wrong:** Attempting to age up members (Onder 12 → Onder 14) for next season
**Why it happens:** Overthinking forecast - assuming members will move categories
**How to avoid:** Use current age groups as-is - member attrition balances age progression
**Warning signs:** Code modifies leeftijdsgroep field or has mapping logic for category changes

### Pitfall 6: Forecast Parameter Conflicts with Season Parameter
**What goes wrong:** Allowing both `forecast=true` and `season=2024-2025` simultaneously
**Why it happens:** Not validating parameter combinations
**How to avoid:** When forecast=true, ignore season parameter - forecast always means "next season"
**Warning signs:** Users confused about what season they're viewing in forecast mode

## Code Examples

Verified patterns from official sources:

### REST API Boolean Parameter Registration
```php
// Source: WordPress REST API Handbook - Adding Custom Endpoints
// https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
'args' => [
    'forecast' => [
        'default'           => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'validate_callback' => 'rest_is_boolean',
        'description'       => 'Calculate forecast for next season instead of current/specified season',
    ],
],
```

### Next Season Key Calculation
```php
// Source: Existing MembershipFees::get_season_key() pattern (class-membership-fees.php:383-392)
public function get_next_season_key( ?string $current_season = null ): string {
    if ( $current_season === null ) {
        $current_season = $this->get_season_key(); // e.g., "2025-2026"
    }

    // Extract start year: "2025-2026" → 2025
    $season_start_year = (int) substr( $current_season, 0, 4 );

    // Next season: 2025 + 1 = 2026 → "2026-2027"
    $next_start_year = $season_start_year + 1;

    return $next_start_year . '-' . ( $next_start_year + 1 );
}
```

### Forecast Fee Calculation with 100% Pro-rata
```php
// Source: Existing MembershipFees::calculate_fee_with_family_discount() pattern
// (class-membership-fees.php:986-1117)
if ( $forecast ) {
    // Get fee with family discount (reuses existing logic)
    $fee_data = $fees->calculate_fee_with_family_discount( $person->ID, $season );

    if ( $fee_data !== null ) {
        // Override pro-rata to 100% for forecast
        $prorata_percentage = 1.0;
        $fee_after_discount = $fee_data['final_fee'];
        $final_fee = $fee_after_discount; // No pro-rata reduction

        // Add pro-rata fields to result
        $fee_data = array_merge(
            $fee_data,
            [
                'registration_date'   => null, // Forecast doesn't use lid-sinds
                'prorata_percentage'  => $prorata_percentage,
                'fee_after_discount'  => $fee_after_discount,
                'final_fee'           => $final_fee,
            ]
        );
    }
}
```

### Conditional Nikki Field Exclusion
```php
// Source: Existing get_fee_list() pattern (class-rest-api.php:2676-2696)
$result = [
    'id'                     => $person->ID,
    'first_name'             => $first_name,
    'last_name'              => $last_name,
    'category'               => $fee_data['category'],
    'base_fee'               => $fee_data['base_fee'],
    'final_fee'              => $fee_data['final_fee'],
    // ... other fee fields
];

// Only include Nikki data for current season (not forecast)
if ( !$forecast ) {
    $nikki_year = substr( $season, 0, 4 );
    $result['nikki_total'] = get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_total', true );
    $result['nikki_saldo'] = get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_saldo', true );
}

$results[] = $result;
```

### Performance-Optimized WP_Query for Large Datasets
```php
// Source: WordPress VIP - Optimize core queries at scale
// https://docs.wpvip.com/databases/optimize-queries/optimize-core-queries-at-scale/
$query = new \WP_Query(
    [
        'post_type'      => 'person',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'meta_value',
        'meta_key'       => 'first_name',
        'order'          => 'ASC',
        'no_found_rows'  => true,  // Skip SQL_CALC_FOUND_ROWS for performance
        // NOTE: Do NOT use suppress_filters => true (breaks caching)
    ]
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `suppress_filters => true` for performance | `no_found_rows => true` for performance | WordPress 4.0+ | Maintains caching compatibility |
| DateTime for year arithmetic | Simple integer arithmetic on string | N/A - both valid | Simpler for fixed season format |
| Separate forecast endpoint | Parameter on existing endpoint | REST best practices | Reduces code duplication |
| Manual boolean parsing | `rest_sanitize_boolean()` | WordPress 5.2 | Handles '0', '1', 'true', 'false', true, false |

**Deprecated/outdated:**
- `suppress_filters => true` for performance: Use `no_found_rows => true` instead (maintains caching)
- Creating duplicate REST endpoints for variants: Use optional parameters for behavior variants

## Open Questions

Things that couldn't be fully resolved:

1. **Should forecast mode ignore season parameter?**
   - What we know: forecast=true should always mean "next season from today"
   - What's unclear: Should passing season=2024-2025 with forecast=true be an error, or should forecast override it?
   - Recommendation: Forecast always overrides season parameter - simpler mental model, prevents confusion

2. **Should forecast calculations be cached?**
   - What we know: Forecast is always based on current membership data, recalculated on each request
   - What's unclear: With 1400+ members, is on-demand calculation fast enough?
   - Recommendation: Start without caching (forecast inherits calculation speed from existing fee cache), add transient cache if performance degrades

3. **Should forecast advance age groups automatically?**
   - What we know: Requirements explicitly state "uses current age groups as base"
   - What's unclear: Is this a limitation or intentional simplification?
   - Recommendation: Per requirements, use current age groups - member attrition balances age progression, complexity not worth accuracy gain

## Sources

### Primary (HIGH confidence)
- WordPress REST API Handbook - [Adding Custom Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- WordPress REST API Handbook - [Global Parameters](https://developer.wordpress.org/rest-api/using-the-rest-api/global-parameters/)
- WordPress Function Reference - [rest_sanitize_boolean()](https://developer.wordpress.org/reference/functions/rest_sanitize_boolean/)
- WordPress Function Reference - [rest_is_boolean()](https://developer.wordpress.org/reference/functions/rest_is_boolean/)
- Existing codebase: `includes/class-membership-fees.php` (MembershipFees class)
- Existing codebase: `includes/class-rest-api.php` (get_fee_list endpoint)

### Secondary (MEDIUM confidence)
- WordPress VIP - [Optimize core queries at scale](https://docs.wpvip.com/databases/optimize-queries/optimize-core-queries-at-scale/)
- Mikey Arce - [Performant WordPress Queries](https://mikeyarce.com/2023/12/performant-wordpress-queries/)
- Spacedmonkey - [Enhancing WP_Query Performance in WordPress](https://www.spacedmonkey.com/2025/04/14/enhancing-wp_query-performance-in-wordpress/)

### Tertiary (LOW confidence)
- PHP Manual - [strtotime()](https://www.php.net/manual/en/function.strtotime.php) - Referenced but simple integer arithmetic preferred for season keys

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Uses existing WordPress core and installed dependencies
- Architecture: HIGH - Extends proven v12.0 infrastructure with minimal new patterns
- Pitfalls: MEDIUM - Based on WordPress best practices and codebase review, not production experience

**Research date:** 2026-02-02
**Valid until:** 30 days (stable WordPress REST API patterns, mature codebase)

**Key findings:**
1. No new dependencies required - purely extends existing v12.0 infrastructure
2. Forecast calculation is 95% identical to current season - reuse existing methods
3. Only differences: season key (+1 year) and pro-rata (forced 100%)
4. WordPress provides native boolean parameter handling (rest_sanitize_boolean)
5. Performance already optimized in v12.0 (cached calculations, no_found_rows)
6. Forecast should not be cached - always ephemeral, calculated on-demand
