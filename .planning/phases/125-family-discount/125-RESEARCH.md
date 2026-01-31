# Phase 125: Family Discount - Research

**Researched:** 2026-01-31
**Domain:** PHP service class extension for family-based fee discounts
**Confidence:** HIGH

## Summary

This phase extends the Phase 124 fee calculation engine with family discount logic. Youth members (under 18) living at the same address receive tiered discounts: 25% off for the second member, 50% off for third and beyond. The discount is applied to the cheapest members first, so the most expensive youth member pays full price.

The key technical challenge is address matching. Dutch addresses can be uniquely identified by postal code + house number alone. The address data is stored in an ACF repeater field with `street` and `postal_code` sub-fields. House numbers must be parsed from the `street` field since there is no dedicated house number field.

**Primary recommendation:** Extend the existing `MembershipFees` class with address normalization methods and family grouping logic. Use the established pattern from Phase 124 where `calculate_fee()` returns an array that can be augmented with discount information.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Post Meta API | Core | Address data access | ACF stores addresses in post meta |
| ACF Pro | Latest | Custom field access | Already required, provides get_field() |
| PHP Regex | Core | House number parsing | Native, no dependencies |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WP_Query | Core | Query all youth members | Fetching eligible members for grouping |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Regex parsing | External geocoding | Over-engineering for postal+house number match |
| Runtime calculation | Pre-computed groups | Runtime is simpler, no cache invalidation |

**Installation:**
No additional packages needed. Uses existing WordPress and ACF APIs.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-membership-fees.php     # Extended with family discount methods
```

### Pattern 1: Address Normalization
**What:** Extract and normalize address components for matching
**When to use:** When comparing addresses to find family groups
**Example:**
```php
// Source: Stadion codebase pattern + Dutch address format
/**
 * Normalize a Dutch postal code.
 *
 * Removes spaces, converts to uppercase.
 * "1234 ab" -> "1234AB"
 * "1234AB" -> "1234AB"
 *
 * @param string $postal_code Raw postal code.
 * @return string Normalized postal code.
 */
public function normalize_postal_code( string $postal_code ): string {
    // Remove all whitespace, convert to uppercase
    return strtoupper( preg_replace( '/\s+/', '', trim( $postal_code ) ) );
}

/**
 * Extract house number with addition from street address.
 *
 * Dutch street format: "Kerkstraat 12" or "Kerkstraat 12A" or "Kerkstraat 12-A"
 * Returns the house number WITH addition as-is (case-insensitive normalized).
 *
 * @param string $street Street address string.
 * @return string|null House number with addition, or null if not found.
 */
public function extract_house_number( string $street ): ?string {
    $street = trim( $street );

    // Match number at end of street, optionally followed by addition
    // Supports: "12", "12A", "12a", "12-A", "12 A", "12-1", "12/A"
    if ( preg_match( '/(\d+)\s*[-\/]?\s*([a-zA-Z0-9]*)$/', $street, $matches ) ) {
        $number = $matches[1];
        $addition = strtoupper( trim( $matches[2] ) );

        if ( ! empty( $addition ) ) {
            return $number . $addition; // e.g., "12A"
        }
        return $number; // e.g., "12"
    }

    return null;
}
```

### Pattern 2: Family Grouping Key
**What:** Generate a unique key for address-based family grouping
**When to use:** To group members by same address
**Example:**
```php
// Source: CONTEXT.md decisions
/**
 * Generate family grouping key from person's address.
 *
 * Uses postal code + house number only (street name ignored per CONTEXT.md).
 * Returns null if address data is incomplete.
 *
 * @param int $person_id Person post ID.
 * @return string|null Family group key (e.g., "1234AB-12A") or null.
 */
public function get_family_key( int $person_id ): ?string {
    $addresses = get_field( 'addresses', $person_id ) ?: [];

    // Use first address (primary)
    if ( empty( $addresses ) ) {
        return null;
    }

    $address = $addresses[0];
    $postal_code = $address['postal_code'] ?? '';
    $street = $address['street'] ?? '';

    if ( empty( $postal_code ) || empty( $street ) ) {
        return null; // Incomplete address, exclude from grouping
    }

    $normalized_postal = $this->normalize_postal_code( $postal_code );
    $house_number = $this->extract_house_number( $street );

    if ( empty( $house_number ) ) {
        return null; // Could not parse house number
    }

    // Validate postal code format (4 digits + 2 letters)
    if ( ! preg_match( '/^\d{4}[A-Z]{2}$/', $normalized_postal ) ) {
        return null; // Invalid postal code format
    }

    return $normalized_postal . '-' . $house_number;
}
```

### Pattern 3: Tiered Discount Application
**What:** Apply discount tiers based on family position
**When to use:** When calculating final fee with family discount
**Example:**
```php
// Source: FAM-02, FAM-03, FAM-04 requirements
/**
 * Get discount rate based on family position.
 *
 * Position 1 (most expensive): 0% discount (full fee)
 * Position 2: 25% discount
 * Position 3+: 50% discount
 *
 * @param int $position 1-indexed position in family (1=most expensive).
 * @return float Discount rate (0.0 to 0.5).
 */
public function get_family_discount_rate( int $position ): float {
    if ( $position <= 1 ) {
        return 0.0;  // First member pays full fee
    }
    if ( $position === 2 ) {
        return 0.25; // Second member gets 25% off
    }
    return 0.50;     // Third+ get 50% off
}
```

### Pattern 4: Family Group Sorting
**What:** Sort family members to apply discount to cheapest first
**When to use:** Before assigning discount positions
**Example:**
```php
// Source: FAM-04 requirement: Discount applies to youngest/cheapest first
// Interpretation: Sort DESCENDING by base_fee, so most expensive is position 1
$family_members = [
    ['person_id' => 101, 'base_fee' => 230], // Junior - most expensive
    ['person_id' => 102, 'base_fee' => 180], // Pupil
    ['person_id' => 103, 'base_fee' => 130], // Mini - cheapest
];

// Sort descending by base_fee (most expensive first)
usort( $family_members, function( $a, $b ) {
    return $b['base_fee'] <=> $a['base_fee'];
} );

// Now position 1 = most expensive (full fee)
// Position 2 = second most expensive (25% off)
// Position 3 = cheapest (50% off)
```

### Anti-Patterns to Avoid
- **Matching on full street address:** Too fragile, street names can have typos. Use postal code + house number only.
- **Ignoring house number additions:** "12A" and "12B" are different families, must be kept separate.
- **Including adults in family discount:** Only youth (mini/pupil/junior) are eligible per FAM-05.
- **Pre-computing family groups:** Stale data risk. Calculate on-demand like Phase 124.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Determining youth eligibility | Custom age logic | Phase 124 parse_age_group() | Already determines mini/pupil/junior |
| Getting base fee | New calculation | Phase 124 calculate_fee() | Already returns base_fee with category |
| Storing discount snapshots | New meta structure | Extend existing fee_snapshot pattern | Consistency with Phase 124 |

**Key insight:** Phase 124 already provides the foundation. The `calculate_fee()` method returns `category` and `base_fee`. Family discount should extend this by adding `family_discount_rate` and `final_fee` to the result.

## Common Pitfalls

### Pitfall 1: Case-Sensitive House Number Additions
**What goes wrong:** "12a" and "12A" treated as different addresses
**Why it happens:** Not normalizing house number additions to uppercase
**How to avoid:** Always uppercase the addition when building family key
**Warning signs:** Family members on same address not grouped

### Pitfall 2: Whitespace in Postal Codes
**What goes wrong:** "1234 AB" and "1234AB" treated as different addresses
**Why it happens:** Not stripping spaces from postal codes
**How to avoid:** Remove all whitespace before comparison
**Warning signs:** Same-address family members not grouped

### Pitfall 3: Including Non-Youth in Family Discount
**What goes wrong:** Seniors, recreants, or donateurs receiving family discount
**Why it happens:** Not filtering by category before grouping
**How to avoid:** Only include mini/pupil/junior categories in family grouping
**Warning signs:** Adults showing family discounts on fee calculations

### Pitfall 4: Discount Applied to Wrong Member
**What goes wrong:** Most expensive member gets discount instead of cheapest
**Why it happens:** Sorting ascending instead of descending by base_fee
**How to avoid:** Sort DESCENDING so position 1 = most expensive (full fee)
**Warning signs:** Junior paying 50% off, Mini paying full fee

### Pitfall 5: Empty Address Repeater
**What goes wrong:** PHP error when accessing first address
**Why it happens:** Not checking if addresses array is empty
**How to avoid:** Return null early if addresses array is empty
**Warning signs:** PHP notices/warnings in logs

### Pitfall 6: Multiple Addresses
**What goes wrong:** Using wrong address for family grouping
**Why it happens:** Person has multiple addresses (home, vacation, etc.)
**How to avoid:** Use first address only (assumed primary) or address labeled "Home"
**Warning signs:** Family members not grouped despite same primary address

## Code Examples

Verified patterns from existing codebase:

### Accessing Address Data (from ACF schema)
```php
// Source: acf-json/group_person_fields.json lines 168-230
$addresses = get_field( 'addresses', $person_id ) ?: [];

// Structure per address:
// [
//     'address_label' => 'Home',
//     'street' => 'Kerkstraat 12A',
//     'postal_code' => '1234 AB',
//     'city' => 'Amsterdam',
//     'state' => 'Noord-Holland',
//     'country' => 'Netherlands',
// ]

if ( ! empty( $addresses ) ) {
    $primary = $addresses[0];
    $street = $primary['street'] ?? '';
    $postal_code = $primary['postal_code'] ?? '';
}
```

### Checking Youth Category (using Phase 124 pattern)
```php
// Source: includes/class-membership-fees.php calculate_fee() method
$fee_data = $this->calculate_fee( $person_id );

if ( $fee_data === null ) {
    // Person excluded from calculation
    return null;
}

// Check if eligible for family discount
$is_youth = in_array( $fee_data['category'], [ 'mini', 'pupil', 'junior' ], true );

if ( ! $is_youth ) {
    // Seniors, recreants, donateurs do not get family discount
    $fee_data['family_discount_rate'] = 0.0;
    $fee_data['family_discount_amount'] = 0;
    $fee_data['final_fee'] = $fee_data['base_fee'];
    return $fee_data;
}
```

### Querying All Youth Members
```php
// Source: includes/class-membership-fees.php clear_all_snapshots_for_season() pattern
$query = new \WP_Query( [
    'post_type'      => 'person',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
] );

$youth_with_addresses = [];

foreach ( $query->posts as $person_id ) {
    $fee_data = $this->calculate_fee( $person_id );

    // Skip if not calculable or not youth
    if ( $fee_data === null ) {
        continue;
    }
    if ( ! in_array( $fee_data['category'], [ 'mini', 'pupil', 'junior' ], true ) ) {
        continue;
    }

    $family_key = $this->get_family_key( $person_id );
    if ( $family_key === null ) {
        // No valid address, pay full fee
        continue;
    }

    $youth_with_addresses[] = [
        'person_id'  => $person_id,
        'family_key' => $family_key,
        'base_fee'   => $fee_data['base_fee'],
        'category'   => $fee_data['category'],
    ];
}
```

### Building Family Groups
```php
// Group by family key
$families = [];
foreach ( $youth_with_addresses as $member ) {
    $key = $member['family_key'];
    if ( ! isset( $families[ $key ] ) ) {
        $families[ $key ] = [];
    }
    $families[ $key ][] = $member;
}

// Sort each family by base_fee descending (most expensive first)
foreach ( $families as $key => $members ) {
    usort( $members, function( $a, $b ) {
        return $b['base_fee'] <=> $a['base_fee'];
    } );
    $families[ $key ] = $members;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual fee assignment | Calculated from leeftijdsgroep | Phase 124 | Base fee now automated |
| N/A | Family discount on youth | Phase 125 | Extends base fee calculation |

**Deprecated/outdated:**
- None for this phase - building on fresh Phase 124 foundation

## Open Questions

Things that couldn't be fully resolved:

1. **Multiple Addresses: Which to Use?**
   - What we know: ACF allows multiple addresses per person
   - What's unclear: Should we use first address, or look for label "Home"?
   - Recommendation: Use first address (index 0) as primary. Most members have only one address. Document this assumption.

2. **Tie-breaker for Same Base Fee**
   - What we know: When sorting by base_fee, members with same fee need ordering
   - What's unclear: If two Junior siblings have same base_fee, which gets discount?
   - Recommendation: Use person_id as secondary sort (lower ID = older record = full fee). This is arbitrary but consistent.

3. **Existing Fee Snapshots**
   - What we know: Phase 124 stores fee snapshots in post meta
   - What's unclear: Should family discount be part of snapshot, or calculated on top?
   - Recommendation: Calculate family discount on-demand, not cached. Family composition can change (new baby, etc.), so caching creates stale data risk.

## Sources

### Primary (HIGH confidence)
- `includes/class-membership-fees.php` - Phase 124 implementation, provides patterns and integration points
- `acf-json/group_person_fields.json` - Address field structure (lines 168-230)
- `.planning/phases/125-family-discount/125-CONTEXT.md` - User decisions on address matching
- `.planning/phases/124-fee-calculation-engine/124-RESEARCH.md` - Phase 124 patterns

### Secondary (MEDIUM confidence)
- [Dutch Postcode Regex](https://github.com/martinvd/dutch-postcode-regex) - Dutch postal code format validation
- [How Dutch Addresses Work](https://itsnotamerica.com/how-dutch-addresses-work/) - Dutch address structure

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Uses existing WordPress/ACF patterns from Phase 124
- Architecture: HIGH - Clear extension of existing MembershipFees class
- Address parsing: HIGH - Dutch address format is well-documented, CONTEXT.md provides clear decisions
- Pitfalls: HIGH - Based on codebase analysis and Dutch address format research

**Research date:** 2026-01-31
**Valid until:** 2026-03-02 (30 days - stable domain, no external dependencies)

---

## Key Findings Summary

1. **Address matching uses postal code + house number only** - Street name ignored per CONTEXT.md decision
2. **House number additions (A, B, etc.) ARE significant** - Different families per decision
3. **Only youth (mini/pupil/junior) eligible** - FAM-05 explicitly excludes recreants/donateurs
4. **Discount to cheapest first** - Sort descending by base_fee, so most expensive is position 1 (full fee)
5. **Use first address as primary** - ACF allows multiple, we use index 0
6. **Calculate on-demand, don't cache family groups** - Family composition can change
