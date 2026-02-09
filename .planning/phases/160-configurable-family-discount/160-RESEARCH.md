# Phase 160: Configurable Family Discount - Research

**Researched:** 2026-02-09
**Domain:** WordPress options data model extension + PHP/React UI for configurable discount tiers
**Confidence:** HIGH

## Summary

Phase 160 makes family discount percentages configurable per season instead of hardcoded. Currently, `get_family_discount_rate()` in `class-membership-fees.php` returns hardcoded values: 0% for 1st child, 25% for 2nd child, 50% for 3rd+ child (lines 1126-1134). This phase moves these percentages into the per-season WordPress options alongside fee categories, and adds UI controls in the existing Phase 158 settings interface.

The implementation is straightforward because the infrastructure is already in place:
- Per-season WordPress options storage pattern established (Phase 155)
- Settings UI with season selector exists (Phase 158)
- REST API for settings CRUD operational (Phase 157)
- Family discount calculation logic is isolated in one method

The primary work is extending the data model to include discount tiers in the season config, updating `get_family_discount_rate()` to read from config instead of hardcoded values, and adding form inputs to the Settings UI.

**Primary recommendation:** Store discount config as a `family_discount` object within each season's option data, containing `second_child_percent` and `third_child_percent` fields. Update the REST API to validate and persist these fields, extend the Settings UI with discount configuration inputs, and modify `get_family_discount_rate()` to read from season config with fallback to current defaults.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | Core | Per-season settings storage | Already used for `rondo_membership_fees_{season}` |
| react-hook-form | ^7.49.0 | Form state/validation | Project standard for all forms |
| @tanstack/react-query | ^5.17.0 | Server state management | Standard for all settings API calls |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Tailwind CSS | ^3.4.0 | Styling | Consistent design system |
| lucide-react | ^0.309.0 | Icons | Standard icon library |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Per-season config | Global option | Per-season is more flexible, matches category pattern |
| Percentage inputs (0-100) | Decimal inputs (0.00-1.00) | Percentages more intuitive for admins |
| Two fields (2nd, 3rd+) | Array of tiers | Two fields sufficient, simpler UX |

**Installation:**
No new packages needed. Uses existing WordPress APIs and installed React libraries.

## Architecture Patterns

### Recommended Project Structure
```
includes/
  class-membership-fees.php        # Extend: get_family_discount_rate() reads config
  class-rest-api.php               # Extend: validate/save family_discount in settings

src/pages/Settings/
  FeeCategorySettings.jsx          # Extend: add discount config inputs
```

### Pattern 1: Per-Season Config Extension (from Phase 155)

**What:** Extend existing per-season WordPress option with additional configuration fields
**When to use:** Adding new season-specific settings that pair with existing fee categories
**Example:**
```php
// Source: Phase 155 pattern for category storage

// Current structure (Phase 155-158):
// rondo_membership_fees_{season} = [
//   'senior' => [
//     'label' => 'Senior',
//     'amount' => 150,
//     'age_classes' => ['Senioren'],
//     'is_youth' => false,
//     'sort_order' => 0,
//   ],
//   // ... other categories
// ]

// Extended structure (Phase 160):
// rondo_membership_fees_{season} = [
//   'categories' => [
//     'senior' => [ ... ],
//     // ... other categories
//   ],
//   'family_discount' => [
//     'second_child_percent' => 25,
//     'third_child_percent' => 50,
//   ],
// ]

// OR (simpler, backward-compatible):
// Keep categories at root, add family_discount as sibling key
// rondo_membership_fees_{season} = [
//   'senior' => [ ... ],
//   'pupil' => [ ... ],
//   'family_discount' => [
//     'second_child_percent' => 25,
//     'third_child_percent' => 50,
//   ],
// ]
```

**Note:** The simpler approach (family_discount as sibling key) avoids breaking existing code that accesses categories directly from the root. Phase 157-158 already work with this structure, so we should extend it rather than restructure.

### Pattern 2: Config with Fallback Defaults (WordPress pattern)

**What:** Read from config with fallback to current behavior if config doesn't exist
**When to use:** Extending existing functionality to be configurable without breaking existing deployments
**Example:**
```php
// Source: WordPress core pattern for backward compatibility

/**
 * Get discount rate based on family position
 *
 * Reads discount percentages from season config. Falls back to
 * default values (0%/25%/50%) if config not set.
 *
 * @param int         $position 1-indexed position in family (1=most expensive, pays full).
 * @param string|null $season   Optional season key, defaults to current season.
 * @return float Discount rate (0.0 to 1.0).
 */
public function get_family_discount_rate( int $position, ?string $season = null ): float {
    if ( $position <= 1 ) {
        return 0.0;  // First member always pays full fee
    }

    // Get season config
    $season = $season ?? $this->get_season_key();
    $season_key = $this->get_option_key_for_season( $season );
    $season_data = get_option( $season_key, [] );

    // Read discount config with defaults
    $discount_config = $season_data['family_discount'] ?? null;
    $second_child_percent = $discount_config['second_child_percent'] ?? 25;
    $third_child_percent = $discount_config['third_child_percent'] ?? 50;

    if ( $position === 2 ) {
        return $second_child_percent / 100.0;  // Convert percentage to decimal
    }

    return $third_child_percent / 100.0;  // Position 3+
}
```

### Pattern 3: Settings UI Form Section (from Phase 158)

**What:** Add a configuration section within the existing FeeCategorySettings component
**When to use:** Settings that belong with fee categories but are conceptually separate
**Example:**
```jsx
// Source: Phase 158 FeeCategorySettings.jsx pattern

function FamilyDiscountSection({ season, seasonKey, discountConfig, onUpdate }) {
  const [secondChildPercent, setSecondChildPercent] = useState(
    discountConfig?.second_child_percent ?? 25
  );
  const [thirdChildPercent, setThirdChildPercent] = useState(
    discountConfig?.third_child_percent ?? 50
  );

  const handleSave = () => {
    const updatedConfig = {
      second_child_percent: parseFloat(secondChildPercent),
      third_child_percent: parseFloat(thirdChildPercent),
    };
    onUpdate(updatedConfig);
  };

  return (
    <div className="card p-6 mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
      <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
        Familiekorting configuratie
      </h3>
      <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
        Configureer de kortingspercentages voor het tweede en derde kind binnen een gezin.
        Het eerste kind betaalt altijd het volledige tarief.
      </p>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Tweede kind (%)
          </label>
          <input
            type="number"
            min="0"
            max="100"
            step="1"
            value={secondChildPercent}
            onChange={(e) => setSecondChildPercent(e.target.value)}
            className="input w-full"
          />
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Standaard: 25%
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Derde kind en verder (%)
          </label>
          <input
            type="number"
            min="0"
            max="100"
            step="1"
            value={thirdChildPercent}
            onChange={(e) => setThirdChildPercent(e.target.value)}
            className="input w-full"
          />
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Standaard: 50%
          </p>
        </div>
      </div>

      <div className="mt-4 flex gap-2">
        <button onClick={handleSave} className="btn-primary">
          Opslaan
        </button>
        <button
          onClick={() => {
            setSecondChildPercent(25);
            setThirdChildPercent(50);
          }}
          className="btn-secondary"
        >
          Reset naar standaard
        </button>
      </div>
    </div>
  );
}
```

### Pattern 4: REST API Validation (from Phase 157)

**What:** Validate discount percentages before saving to options
**When to use:** Extending `update_membership_fee_settings` with new config fields
**Example:**
```php
// Source: Phase 157 validate_category_config pattern

/**
 * Validate family discount configuration
 *
 * Ensures percentages are valid numbers between 0 and 100.
 *
 * @param mixed $discount_config The family_discount config to validate.
 * @return array Array with 'errors' and 'warnings' keys.
 */
private function validate_family_discount_config( $discount_config ) {
    $errors = [];
    $warnings = [];

    // Null/missing is valid (use defaults)
    if ( $discount_config === null ) {
        return [ 'errors' => [], 'warnings' => [] ];
    }

    // Must be an array
    if ( ! is_array( $discount_config ) ) {
        $errors[] = [
            'field' => 'family_discount',
            'message' => 'Family discount config must be an object',
        ];
        return [ 'errors' => $errors, 'warnings' => $warnings ];
    }

    // Validate second_child_percent
    if ( isset( $discount_config['second_child_percent'] ) ) {
        $value = $discount_config['second_child_percent'];
        if ( ! is_numeric( $value ) || $value < 0 || $value > 100 ) {
            $errors[] = [
                'field' => 'family_discount.second_child_percent',
                'message' => 'Second child discount must be between 0 and 100',
            ];
        }
    }

    // Validate third_child_percent
    if ( isset( $discount_config['third_child_percent'] ) ) {
        $value = $discount_config['third_child_percent'];
        if ( ! is_numeric( $value ) || $value < 0 || $value > 100 ) {
            $errors[] = [
                'field' => 'family_discount.third_child_percent',
                'message' => 'Third child discount must be between 0 and 100',
            ];
        }
    }

    // Warning if second child discount >= third child discount
    $second = $discount_config['second_child_percent'] ?? 25;
    $third = $discount_config['third_child_percent'] ?? 50;
    if ( $second >= $third ) {
        $warnings[] = [
            'field' => 'family_discount',
            'message' => 'Second child discount is typically lower than third child discount',
        ];
    }

    return [ 'errors' => $errors, 'warnings' => $warnings ];
}
```

### Anti-Patterns to Avoid

- **Storing as decimal (0.25) instead of percentage (25):** Admin UI shows percentages, store as percentages for clarity
- **Hardcoding discount values in multiple places:** All logic should read from season config, no fallback constants scattered
- **Not validating percentage range:** Must be 0-100, reject negative or >100 values
- **Breaking backward compatibility:** Ensure existing deployments without discount config continue working with defaults
- **Separate API endpoint for discount config:** Keep it in the same settings endpoint as categories (already has per-season structure)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Per-season storage | Custom table or separate options | Extend existing `rondo_membership_fees_{season}` option | Consistency with Phase 155-158 |
| Settings validation | Frontend-only validation | REST API validation (Phase 157 pattern) | Server-side validation is authoritative |
| Form state | useState for each field | React-hook-form (if form grows) | Project standard, but simple inputs OK too |
| Discount calculation | New method | Extend existing `get_family_discount_rate()` | Already called by `calculate_fee_with_family_discount()` |

**Key insight:** Phase 125 already built the family discount calculation engine. Phase 155-158 built the per-season config and settings UI infrastructure. Phase 160 is just connecting these two systems by moving the hardcoded values into the config.

## Common Pitfalls

### Pitfall 1: Not Handling Missing Config (Backward Compatibility)

**What goes wrong:** Deployments upgraded to Phase 160 crash because they don't have `family_discount` config yet
**Why it happens:** Not checking if config exists before accessing fields
**How to avoid:** Use `??` null coalescing operator with default values (25, 50) everywhere
**Warning signs:** PHP warnings about undefined array keys, fee calculations returning 0.0 for discount

### Pitfall 2: Storing Percentages as Decimals

**What goes wrong:** Admin enters "25" expecting 25%, but code interprets as 2500% (25.0 as decimal)
**Why it happens:** Mixing percentage (0-100) and decimal (0.0-1.0) representations
**How to avoid:** Store as integer percentage (0-100) in options, convert to decimal (divide by 100) only in `get_family_discount_rate()`
**Warning signs:** Discount amounts larger than base fee, negative final fees

### Pitfall 3: Forgetting to Pass Season to get_family_discount_rate()

**What goes wrong:** Fee calculations for non-current seasons always use current season's discount config
**Why it happens:** `get_family_discount_rate()` signature currently has only `$position`, no `$season` parameter
**How to avoid:** Add optional `$season` parameter to `get_family_discount_rate()`, default to current season
**Warning signs:** Future season forecasts use wrong discount percentages

### Pitfall 4: Not Copying Discount Config in Copy-Forward

**What goes wrong:** Admin configures discounts for 2025-2026, creates 2026-2027 settings, discount config reverts to defaults
**Why it happens:** Phase 155 copy-forward logic only copies categories array, not sibling keys
**How to avoid:** Update `get_categories_for_season()` to copy entire option data structure when copying from previous season, not just categories
**Warning signs:** Discount config needs to be re-entered every season

### Pitfall 5: Validating on Frontend Only

**What goes wrong:** Malicious/buggy requests set discount to 200%, fee calculations break
**Why it happens:** Trusting client-side validation without server-side checks
**How to avoid:** Always validate in REST API (Phase 157 pattern), frontend validation is UX only
**Warning signs:** Invalid data in WordPress options, fee calculation errors in logs

## Code Examples

Verified patterns from existing codebase:

### Current Implementation (Hardcoded)

```php
// Source: includes/class-membership-fees.php lines 1118-1134

/**
 * Get discount rate based on family position
 *
 * Position is 1-indexed where position 1 is the most expensive youth member
 * who pays full fee. Position 2 gets 25% off, position 3+ gets 50% off.
 *
 * @param int $position 1-indexed position in family (1=most expensive, pays full).
 * @return float Discount rate (0.0, 0.25, or 0.50).
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

### Proposed Implementation (Configurable)

```php
// Updated method in includes/class-membership-fees.php

/**
 * Get discount rate based on family position
 *
 * Position is 1-indexed where position 1 is the most expensive youth member
 * who pays full fee. Discount percentages are read from season config, with
 * fallback to default values (25% for 2nd child, 50% for 3rd+).
 *
 * @param int         $position 1-indexed position in family (1=most expensive, pays full).
 * @param string|null $season   Optional season key, defaults to current season.
 * @return float Discount rate (0.0 to 1.0).
 */
public function get_family_discount_rate( int $position, ?string $season = null ): float {
    if ( $position <= 1 ) {
        return 0.0;  // First member always pays full fee
    }

    // Get season discount config
    $season = $season ?? $this->get_season_key();
    $season_key = $this->get_option_key_for_season( $season );
    $season_data = get_option( $season_key, [] );

    // Read discount config with defaults
    $discount_config = $season_data['family_discount'] ?? null;
    $second_child_percent = $discount_config['second_child_percent'] ?? 25;
    $third_child_percent = $discount_config['third_child_percent'] ?? 50;

    if ( $position === 2 ) {
        return $second_child_percent / 100.0;  // Convert percentage to decimal
    }

    return $third_child_percent / 100.0;  // Position 3+
}
```

### REST API Extension

```php
// Extend update_membership_fee_settings() in includes/class-rest-api.php
// Around line 2600

public function update_membership_fee_settings( $request ) {
    $membership_fees = new \Rondo\Fees\MembershipFees();
    $current_season  = $membership_fees->get_season_key();
    $next_season     = $membership_fees->get_next_season_key();
    $season          = $request->get_param( 'season' );
    $categories      = $request->get_param( 'categories' );
    $family_discount = $request->get_param( 'family_discount' );  // NEW

    // Validate categories
    $validation = $this->validate_category_config( $categories );

    // Validate family discount config
    $discount_validation = $this->validate_family_discount_config( $family_discount );  // NEW

    // Merge validation results
    $all_errors = array_merge( $validation['errors'], $discount_validation['errors'] );
    $all_warnings = array_merge( $validation['warnings'], $discount_validation['warnings'] );

    if ( ! empty( $all_errors ) ) {
        return new \WP_Error(
            'invalid_settings',
            'Settings validation failed',
            [
                'status'   => 400,
                'errors'   => $all_errors,
                'warnings' => $all_warnings,
            ]
        );
    }

    // Read existing season data
    $season_key = $membership_fees->get_option_key_for_season( $season );
    $season_data = get_option( $season_key, [] );

    // Update categories
    foreach ( $categories as $slug => $category ) {
        $season_data[ $slug ] = $category;
    }

    // Update family discount config
    if ( $family_discount !== null ) {
        $season_data['family_discount'] = $family_discount;
    }

    // Save updated season data
    update_option( $season_key, $season_data );

    // Return updated settings
    $response = [
        'current_season' => [
            'key'             => $current_season,
            'categories'      => $membership_fees->get_categories_for_season( $current_season ),
            'family_discount' => $this->get_family_discount_for_season( $current_season ),  // NEW
        ],
        'next_season'    => [
            'key'             => $next_season,
            'categories'      => $membership_fees->get_categories_for_season( $next_season ),
            'family_discount' => $this->get_family_discount_for_season( $next_season ),  // NEW
        ],
    ];

    if ( ! empty( $all_warnings ) ) {
        $response['warnings'] = $all_warnings;
    }

    return rest_ensure_response( $response );
}

/**
 * Get family discount config for a season
 *
 * Helper method to extract family_discount from season data.
 *
 * @param string $season Season key.
 * @return array Family discount config with defaults.
 */
private function get_family_discount_for_season( string $season ): array {
    $membership_fees = new \Rondo\Fees\MembershipFees();
    $season_key = $membership_fees->get_option_key_for_season( $season );
    $season_data = get_option( $season_key, [] );

    $config = $season_data['family_discount'] ?? null;

    return [
        'second_child_percent' => $config['second_child_percent'] ?? 25,
        'third_child_percent'  => $config['third_child_percent'] ?? 50,
    ];
}
```

### React UI Integration

```jsx
// Extend FeeCategorySettings.jsx (src/pages/Settings/FeeCategorySettings.jsx)

function FeeCategorySettings() {
  const [selectedSeason, setSelectedSeason] = useState('current');

  // Fetch settings including family discount config
  const { data: settings, isLoading } = useQuery({
    queryKey: ['membership-fee-settings'],
    queryFn: async () => {
      const response = await prmApi.getMembershipFeeSettings();
      return response.data;
    },
  });

  const currentCategories = settings?.current_season?.categories || {};
  const nextCategories = settings?.next_season?.categories || {};
  const currentDiscount = settings?.current_season?.family_discount || {
    second_child_percent: 25,
    third_child_percent: 50
  };
  const nextDiscount = settings?.next_season?.family_discount || {
    second_child_percent: 25,
    third_child_percent: 50
  };

  const activeSeason = selectedSeason === 'current' ? settings?.current_season : settings?.next_season;
  const activeCategories = selectedSeason === 'current' ? currentCategories : nextCategories;
  const activeDiscount = selectedSeason === 'current' ? currentDiscount : nextDiscount;

  const updateMutation = useMutation({
    mutationFn: async ({ categories, family_discount }) => {
      return prmApi.updateMembershipFeeSettings(
        { categories, family_discount },
        activeSeason.key
      );
    },
    onSuccess: (response) => {
      queryClient.setQueryData(['membership-fee-settings'], response.data);
    },
  });

  const handleDiscountUpdate = (discountConfig) => {
    updateMutation.mutate({
      categories: activeCategories,
      family_discount: discountConfig,
    });
  };

  return (
    <div>
      {/* Season selector */}
      <SeasonSelector
        selected={selectedSeason}
        onChange={setSelectedSeason}
        currentKey={settings?.current_season?.key}
        nextKey={settings?.next_season?.key}
      />

      {/* Family discount config section */}
      <FamilyDiscountSection
        season={selectedSeason}
        seasonKey={activeSeason?.key}
        discountConfig={activeDiscount}
        onUpdate={handleDiscountUpdate}
      />

      {/* Categories table (existing) */}
      <CategoryTable
        categories={activeCategories}
        onUpdate={(categories) => updateMutation.mutate({
          categories,
          family_discount: activeDiscount
        })}
      />
    </div>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded 0%/25%/50% | Configurable per season | Phase 160 (Feb 2026) | Admins can adjust discount policy |
| Single global discount | Per-season discount config | Phase 160 (Feb 2026) | Supports policy changes over time |
| Two-tier discount (2nd, 3rd+) | Still two-tier, but configurable | Phase 160 (Feb 2026) | Maintains simplicity while adding flexibility |

**Deprecated/outdated:**
- Hardcoded return values in `get_family_discount_rate()` - will be replaced by config reads

## Open Questions

Things that couldn't be fully resolved:

1. **Should first child discount be configurable?**
   - What we know: Currently hardcoded to 0% (first child pays full)
   - What's unclear: Is there a use case for discounting the most expensive child?
   - Recommendation: Keep first child at 0% (not configurable) - simplifies UX and matches universal practice

2. **Should discount tiers be unlimited (4th child, 5th child, etc.)?**
   - What we know: Current implementation treats 3rd+ the same (50%)
   - What's unclear: Do any families have 4+ youth members with different discount needs?
   - Recommendation: Keep two-tier (2nd, 3rd+) for now - simpler UX, covers 99% of cases. Can extend later if needed.

3. **Should discount percentages be validated against each other?**
   - What we know: Typically 2nd child discount < 3rd child discount
   - What's unclear: Is this always true, or could admin want reverse (2nd=50%, 3rd=25%)?
   - Recommendation: Show warning (not error) if 2nd >= 3rd - allows flexibility while guiding users

4. **Should copy-forward preserve discount config?**
   - What we know: Phase 155 copies categories when creating new season
   - What's unclear: Should discount percentages also copy forward, or reset to defaults?
   - Recommendation: Copy forward - discount policy rarely changes year-to-year, easier for admins

## Sources

### Primary (HIGH confidence)
- `includes/class-membership-fees.php` - Current hardcoded implementation (lines 1118-1134)
- `.planning/phases/125-family-discount/125-RESEARCH.md` - Family discount calculation patterns
- `.planning/phases/155-fee-category-data-model/155-RESEARCH.md` - Per-season storage pattern
- `.planning/phases/157-fee-category-rest-api/157-RESEARCH.md` - Settings API patterns
- `.planning/phases/158-fee-category-settings-ui/158-RESEARCH.md` - Settings UI patterns
- `src/pages/Settings/FeeCategorySettings.jsx` - Existing settings UI implementation

### Secondary (MEDIUM confidence)
- `.planning/ROADMAP.md` - Phase 160 goals and success criteria
- `.planning/REQUIREMENTS.md` - Future requirement: "Per-category discount rules"

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - No new libraries, uses existing WordPress Options API and React patterns
- Architecture: HIGH - Clear extension of existing Phase 155-158 infrastructure
- Pitfalls: HIGH - Based on Phase 125 family discount implementation and Phase 155-158 config patterns
- Code examples: HIGH - Derived from current codebase implementation

**Research date:** 2026-02-09
**Valid until:** 2026-03-11 (30 days - stable domain, no external dependencies)

---

## Key Findings Summary

1. **Hardcoded values live in one method** - `get_family_discount_rate()` lines 1126-1134, easy to replace
2. **Per-season storage pattern already established** - Phase 155 created `rondo_membership_fees_{season}` structure
3. **Settings UI infrastructure exists** - Phase 158 built FeeCategorySettings.jsx with season selector
4. **REST API validation pattern mature** - Phase 157 provides errors/warnings structure to follow
5. **Copy-forward logic needs extension** - Must copy `family_discount` key when creating new season config
6. **Backward compatibility critical** - Must handle missing config gracefully with fallback to 25/50 defaults
