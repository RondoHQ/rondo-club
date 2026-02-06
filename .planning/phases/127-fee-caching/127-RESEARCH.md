# Phase 127: Fee Caching - Research

**Researched:** 2026-02-01
**Domain:** WordPress post meta caching and invalidation hooks for denormalized fee calculations
**Confidence:** HIGH

## Summary

This phase implements caching of calculated membership fees in WordPress post meta to solve two problems: (1) Fix the pro-rata bug where `registratiedatum` is used instead of the correct `lid-sinds` field, and (2) Improve list page performance by storing pre-calculated fees per person rather than calculating on every page load.

The implementation leverages WordPress native post meta storage with ACF hooks to automatically invalidate caches when relevant fields change. The MembershipFees class already has snapshot infrastructure from Phase 124-125, but it's only used for season locking, not performance caching. This phase extends it to store denormalized fee calculations (`_fee_base`, `_fee_family_discount`, `_fee_prorata`, `_fee_final`) and adds invalidation hooks that trigger recalculation when dependencies change (age group, address, team membership, lid-sinds, or settings).

The critical bug fix: PRO-04 requires switching from `registratiedatum` (Sportlink import date) to `lid-sinds` (actual membership start date). This affects 84 members currently receiving incorrect pro-rata calculations.

**Primary recommendation:** Extend MembershipFees class with cache invalidation hooks using ACF's `acf/update_value` filters, store denormalized fee fields in a single post meta option array (following VOGEmail pattern), and add admin "recalculate all" bulk action for settings changes.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Post Meta | Core | Cache storage | Native WordPress data storage |
| ACF Pro hooks | Latest | Change detection | Triggers for invalidation |
| WordPress Options API | Core | Settings storage | Already used for fee settings |
| WordPress Cron | Core | Bulk operations | For mass recalculation |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WP_Query | Core | Person post queries | Fetching people for bulk recalc |
| update_post_meta | Core | Cache writes | Storing denormalized fees |
| get_post_meta | Core | Cache reads | Retrieving cached fees |

**Installation:**
No additional packages needed. Uses WordPress core APIs.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-membership-fees.php     # Extend with cache methods
  class-fee-cache-invalidator.php # New: Invalidation hook manager
  class-rest-api.php            # Add bulk recalculate endpoint
```

### Pattern 1: Cache Storage in Post Meta Option Array
**What:** Store all fee fields in a single serialized array (like VOGEmail pattern)
**When to use:** When multiple related values need atomic updates
**Example:**
```php
// Source: includes/class-vog-email.php get_vog_data() pattern
// Recommended cache structure
$cache_data = [
    'base_fee'               => 180,
    'family_discount_rate'   => 0.25,
    'family_discount_amount' => 45,
    'fee_after_discount'     => 135,
    'prorata_percentage'     => 0.75,
    'final_fee'              => 101,
    'category'               => 'pupil',
    'leeftijdsgroep'         => 'Onder 10',
    'family_key'             => '1234AB-12',
    'family_size'            => 2,
    'family_position'        => 2,
    'lid_sinds'              => '2025-10-15',  // Used for pro-rata
    'season'                 => '2025-2026',
    'calculated_at'          => '2026-02-01 14:23:15',
];

// Single meta key with season suffix
$meta_key = 'stadion_fee_cache_2025-2026';
update_post_meta( $person_id, $meta_key, $cache_data );
```

### Pattern 2: ACF Update Value Hook for Invalidation
**What:** Detect field changes and clear cache using ACF filters
**When to use:** When specific field updates should trigger recalculation
**Example:**
```php
// Source: includes/class-inverse-relationships.php lines 28-31
class FeeCacheInvalidator {
    public function __construct() {
        // Hook into field updates that affect fees
        add_filter( 'acf/update_value/name=leeftijdsgroep', [ $this, 'invalidate_on_age_change' ], 10, 3 );
        add_filter( 'acf/update_value/name=addresses', [ $this, 'invalidate_on_address_change' ], 10, 3 );
        add_filter( 'acf/update_value/name=work_history', [ $this, 'invalidate_on_team_change' ], 10, 3 );
        add_filter( 'acf/update_value/name=lid-sinds', [ $this, 'invalidate_on_membership_date_change' ], 10, 3 );
    }

    public function invalidate_on_age_change( $value, $post_id, $field ) {
        // Skip non-person posts
        if ( get_post_type( $post_id ) !== 'person' ) {
            return $value;
        }

        // Clear fee cache for current season
        $fees = new \Stadion\Fees\MembershipFees();
        $fees->clear_fee_snapshot( $post_id );

        return $value;
    }
}
```

### Pattern 3: Volunteer Status Auto-Update Pattern
**What:** Calculate and store derived field on save using acf/save_post hook
**When to use:** When a field needs to be computed from other fields on save
**Example:**
```php
// Source: includes/class-volunteer-status.php lines 64-93
class VolunteerStatus {
    public function __construct() {
        // Hook into ACF save (priority 25 = after auto-title)
        add_action( 'acf/save_post', [ $this, 'update_volunteer_status' ], 25 );
    }

    public function update_volunteer_status( $post_id ) {
        // Skip autosaves and revisions
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Only for person post type
        if ( get_post_type( $post_id ) !== 'person' ) {
            return;
        }

        $this->calculate_and_update_status( $post_id );
    }
}
```

### Pattern 4: Bulk Settings Change Recalculation
**What:** Clear all caches and recalculate when fee settings change
**When to use:** When global settings affect all cached calculations
**Example:**
```php
// Source: includes/class-membership-fees.php clear_all_snapshots_for_season()
// Extend to trigger on settings update
add_action( 'update_option_stadion_membership_fees', [ $this, 'recalculate_all_fees' ], 10, 2 );

public function recalculate_all_fees( $old_value, $new_value ) {
    $season = $this->get_season_key();

    // Clear all caches
    $this->clear_all_snapshots_for_season( $season );

    // Queue background recalculation via cron
    wp_schedule_single_event( time(), 'stadion_recalculate_all_fees', [ $season ] );
}

// Hook the cron action
add_action( 'stadion_recalculate_all_fees', [ $this, 'recalculate_all_fees_background' ] );

public function recalculate_all_fees_background( $season ) {
    $people = get_posts([
        'post_type' => 'person',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    foreach ( $people as $person_id ) {
        $lid_sinds = get_field( 'lid-sinds', $person_id );
        $this->calculate_full_fee( $person_id, $lid_sinds, $season );
        // Note: calculate_full_fee already saves to cache
    }
}
```

### Anti-Patterns to Avoid
- **Using transients instead of post meta:** Transients can expire; fees need permanent storage
- **Caching in object cache:** Object cache is not persistent across requests without memcached
- **Separate meta keys per field:** Use single option array for atomic updates
- **Invalidating entire cache on any field change:** Only invalidate when relevant fields change
- **Synchronous bulk recalculation:** Use wp_schedule_single_event for large operations

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Cache storage | Custom table | WordPress post_meta | Native, indexed, versioned |
| Change detection | Custom tracking | ACF acf/update_value hooks | Triggers on field saves |
| Bulk operations | Inline loops | WordPress Cron + wp_schedule_single_event | Prevents timeouts |
| Season locking | Custom timestamps | Existing fee_snapshot_YYYY-YYYY pattern | Already implemented |
| Cache invalidation | Manual delete calls | Hook-based invalidation class | Automatic, can't be forgotten |
| Field dependency tracking | Manual mapping | Centralized invalidator with documented hooks | Single source of truth |

**Key insight:** WordPress post meta with ACF hooks provides a complete cache infrastructure. The MembershipFees class already has snapshot methods - just extend them for performance caching and add invalidation hooks.

## Common Pitfalls

### Pitfall 1: Infinite Loop in acf/update_value Hooks
**What goes wrong:** Invalidation hook updates a field, triggering itself recursively
**Why it happens:** Calling update_field() inside acf/update_value filter
**How to avoid:** Only call clear_fee_snapshot() (delete meta), never update_field() in invalidation hooks
**Warning signs:** PHP fatal error "maximum function nesting level reached"

### Pitfall 2: Missing lid-sinds Field
**What goes wrong:** Cannot calculate pro-rata without membership start date
**Why it happens:** Field doesn't exist in ACF schema yet
**How to avoid:** Add `lid-sinds` field to person ACF group before implementing cache
**Warning signs:** get_field('lid-sinds') returns null for all people

### Pitfall 3: Stale Cache After Family Member Changes
**What goes wrong:** Person A's cache not invalidated when Person B (sibling) is added/removed
**Why it happens:** Family discount depends on other family members, not just person's own fields
**How to avoid:** When person's address or team changes, invalidate entire family's cache
**Warning signs:** Family discount rates incorrect after adding new child

### Pitfall 4: Cache Invalidation Missing REST API Updates
**What goes wrong:** Cache not cleared when fields updated via REST API
**Why it happens:** ACF hooks only fire on admin saves, not REST
**How to avoid:** Add rest_after_insert_person hook in addition to acf/update_value
**Warning signs:** Frontend changes don't affect fee calculations

### Pitfall 5: Season Boundary Cache Confusion
**What goes wrong:** Old season cache used when new season starts
**Why it happens:** Cache key includes season, but endpoint doesn't pass season parameter
**How to avoid:** Always include season in cache key and REST endpoint defaults to current season
**Warning signs:** Fees from 2024-2025 shown in July 2025

### Pitfall 6: Bulk Recalculate Timeout
**What goes wrong:** Admin recalculate all times out with 1400+ members
**Why it happens:** Synchronous loop processing all people
**How to avoid:** Use wp_schedule_single_event for background processing
**Warning signs:** White screen or 504 timeout when clicking "Recalculate All"

### Pitfall 7: registratiedatum vs lid-sinds Confusion
**What goes wrong:** Wrong field used for pro-rata after cache implementation
**Why it happens:** Existing code uses registratiedatum (import date, not join date)
**How to avoid:** Explicitly use get_field('lid-sinds') everywhere, grep for 'registratiedatum' and remove
**Warning signs:** 84 members with incorrect pro-rata percentages

## Code Examples

Verified patterns from existing codebase:

### Cache Read with Fallback Pattern
```php
// Source: includes/class-membership-fees.php get_fee_for_person() lines 477-516
public function get_fee_for_person_cached( int $person_id, ?string $season = null ): ?array {
    $season = $season ?: $this->get_season_key();
    $cache_key = 'stadion_fee_cache_' . $season;

    // Try cache first
    $cached = get_post_meta( $person_id, $cache_key, true );

    if ( ! empty( $cached ) && is_array( $cached ) ) {
        // Return cached result with flag
        $cached['from_cache'] = true;
        return $cached;
    }

    // Cache miss - calculate fresh
    $lid_sinds = get_field( 'lid-sinds', $person_id );  // PRO-04: Use lid-sinds, not registratiedatum
    $result = $this->calculate_full_fee( $person_id, $lid_sinds, $season );

    if ( $result === null ) {
        return null;  // Not calculable
    }

    // Save to cache
    $result['calculated_at'] = current_time( 'Y-m-d H:i:s' );
    $result['from_cache'] = false;
    update_post_meta( $person_id, $cache_key, $result );

    return $result;
}
```

### Invalidation Hook Registration
```php
// New file: includes/class-fee-cache-invalidator.php
namespace Stadion\Fees;

class FeeCacheInvalidator {

    private $fees;

    public function __construct() {
        $this->fees = new MembershipFees();

        // Age group changes affect fee category
        add_filter( 'acf/update_value/name=leeftijdsgroep', [ $this, 'invalidate_person_cache' ], 10, 3 );

        // Address changes affect family grouping
        add_filter( 'acf/update_value/name=addresses', [ $this, 'invalidate_family_cache' ], 10, 3 );

        // Team changes affect senior vs recreant categorization
        add_filter( 'acf/update_value/name=work_history', [ $this, 'invalidate_person_cache' ], 10, 3 );

        // lid-sinds changes affect pro-rata calculation (PRO-04)
        add_filter( 'acf/update_value/name=lid-sinds', [ $this, 'invalidate_person_cache' ], 10, 3 );

        // Settings changes affect all people
        add_action( 'update_option_stadion_membership_fees', [ $this, 'invalidate_all_caches' ], 10, 2 );

        // REST API updates
        add_action( 'rest_after_insert_person', [ $this, 'invalidate_person_cache_rest' ], 10, 2 );
    }

    public function invalidate_person_cache( $value, $post_id, $field ) {
        if ( get_post_type( $post_id ) !== 'person' ) {
            return $value;
        }

        $this->fees->clear_fee_snapshot( $post_id );

        return $value;
    }

    public function invalidate_family_cache( $value, $post_id, $field ) {
        if ( get_post_type( $post_id ) !== 'person' ) {
            return $value;
        }

        // Clear this person's cache
        $this->fees->clear_fee_snapshot( $post_id );

        // Clear cache for all family members (same family key)
        $family_key = $this->fees->get_family_key( $post_id );

        if ( $family_key !== null ) {
            $groups = $this->fees->build_family_groups();
            $families = $groups['families'];

            if ( isset( $families[ $family_key ] ) ) {
                foreach ( $families[ $family_key ] as $member_id ) {
                    $this->fees->clear_fee_snapshot( $member_id );
                }
            }
        }

        return $value;
    }

    public function invalidate_all_caches( $old_value, $new_value ) {
        $season = $this->fees->get_season_key();
        $this->fees->clear_all_snapshots_for_season( $season );

        // Schedule background recalculation
        wp_schedule_single_event( time() + 10, 'stadion_recalculate_all_fees', [ $season ] );
    }

    public function invalidate_person_cache_rest( $post, $request ) {
        $this->fees->clear_fee_snapshot( $post->ID );
    }
}
```

### REST Endpoint for Bulk Recalculate
```php
// Source: includes/class-rest-api.php register_routes() pattern
register_rest_route(
    'rondo/v1',
    '/fees/recalculate',
    [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'recalculate_all_fees' ],
        'permission_callback' => [ $this, 'check_admin_permission' ],
        'args'                => [
            'season' => [
                'default'           => null,
                'validate_callback' => function ( $param ) {
                    return preg_match( '/^\d{4}-\d{4}$/', $param );
                },
            ],
        ],
    ]
);

public function recalculate_all_fees( $request ) {
    $season = $request->get_param( 'season' );
    $fees   = new \Stadion\Fees\MembershipFees();

    if ( $season === null ) {
        $season = $fees->get_season_key();
    }

    // Clear all caches
    $cleared = $fees->clear_all_snapshots_for_season( $season );

    // Schedule background recalculation
    wp_schedule_single_event( time() + 10, 'stadion_recalculate_all_fees', [ $season ] );

    return rest_ensure_response([
        'success' => true,
        'season' => $season,
        'cleared_count' => $cleared,
        'message' => sprintf(
            'Cleared %d fee caches for season %s. Recalculation scheduled.',
            $cleared,
            $season
        ),
    ]);
}
```

### Frontend Cache Indicator
```javascript
// Source: src/pages/Contributie/ContributieList.jsx
function FeeRow({ member }) {
  const fromCache = member.from_cache;
  const calculatedAt = member.calculated_at;

  return (
    <tr>
      <td>{member.name}</td>
      <td>{formatCurrency(member.final_fee)}</td>
      <td className="text-xs text-gray-500">
        {fromCache && (
          <span title={`Cached: ${calculatedAt}`}>
            📦 {formatDistanceToNow(new Date(calculatedAt))}
          </span>
        )}
      </td>
    </tr>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| registratiedatum for pro-rata | lid-sinds for pro-rata | Phase 127 | PRO-04 bug fixed, 84 members corrected |
| Calculate on every page load | Cache in post meta | Phase 127 | Sub-1s page load for 1400+ members |
| Manual recalculation | Auto-invalidation hooks | Phase 127 | Cache always fresh |
| No cache monitoring | Cache hit indicators | Phase 127 | Visibility into cache performance |

**Deprecated/outdated:**
- Using `registratiedatum` field for pro-rata calculation (replaced by `lid-sinds`)
- Calling calculate_full_fee() in REST endpoint without caching

## Open Questions

Things that couldn't be fully resolved:

1. **lid-sinds Field Existence**
   - What we know: PRO-04 requires this field, current code uses registratiedatum
   - What's unclear: Whether lid-sinds already exists in ACF schema or needs creation
   - Recommendation: Check acf-json/group_person_fields.json and add if missing. Type: date field.

2. **Family Cache Invalidation Scope**
   - What we know: Address change affects family discount for all siblings
   - What's unclear: Should we invalidate entire family or just person + siblings?
   - Recommendation: Invalidate all members with same family_key when address changes. Trade off: some unnecessary recalcs for broader correctness.

3. **Cache Warm-up Strategy**
   - What we know: First page load after bulk clear will be slow
   - What's unclear: Should we pre-warm cache during recalculation cron?
   - Recommendation: Yes - background cron should calculate and cache all fees, not just clear. REST endpoint returns immediately, cron runs async.

4. **Cache Expiry Policy**
   - What we know: Caches are season-specific via meta key
   - What's unclear: Should old season caches be deleted or kept for history?
   - Recommendation: Keep for historical reporting. Delete caches older than 3 years via yearly cron.

5. **Performance Target**
   - What we know: Success criteria is < 1 second load time
   - What's unclear: Is this realistic for 1400+ members with cached data?
   - Recommendation: With cached data, should be ~200ms. Add pagination if still slow (100 per page).

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/stadion/includes/class-membership-fees.php` - Complete fee calculation implementation with snapshot methods
- `/Users/joostdevalk/Code/stadion/includes/class-volunteer-status.php` - ACF hook pattern for auto-calculation
- `/Users/joostdevalk/Code/stadion/includes/class-inverse-relationships.php` - acf/update_value hook pattern
- `/Users/joostdevalk/Code/stadion/includes/class-vog-email.php` - Option array storage pattern
- `/Users/joostdevalk/Code/stadion/includes/class-rest-api.php` - Existing fee endpoint at line 2572 using registratiedatum

### Secondary (MEDIUM confidence)
- WordPress Codex: Post Meta API - https://developer.wordpress.org/reference/functions/update_post_meta/
- ACF Documentation: acf/update_value - https://www.advancedcustomfields.com/resources/acf-update_value/
- WordPress Codex: WP Cron - https://developer.wordpress.org/plugins/cron/

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress core APIs, no external dependencies
- Cache storage: HIGH - Post meta is proven pattern, VOGEmail precedent exists
- Invalidation hooks: HIGH - ACF hooks well-documented, VolunteerStatus precedent
- lid-sinds bug fix: HIGH - Clear requirement (PRO-04), straightforward search-replace
- Performance impact: MEDIUM - Cache should help, but 1400+ rows may need pagination

**Research date:** 2026-02-01
**Valid until:** 2026-03-03 (30 days - stable WordPress APIs, no version changes expected)

---

## Key Findings Summary

1. **Critical bug:** registratiedatum (import date) used instead of lid-sinds (join date) for pro-rata - affects 84 members
2. **Cache storage:** Use single post meta option array per season (`stadion_fee_cache_2025-2026`)
3. **Invalidation:** ACF `acf/update_value` hooks on 4 fields: leeftijdsgroep, addresses, work_history, lid-sinds
4. **Family invalidation:** Address changes must clear entire family's cache (affects siblings)
5. **Settings recalc:** update_option hook triggers wp_schedule_single_event for background processing
6. **Performance goal:** < 1s page load achievable with cached data for 1400+ members
7. **Pattern precedent:** VolunteerStatus class shows exact pattern for auto-calculation on save
8. **REST API gap:** rest_after_insert_person hook needed in addition to ACF hooks
