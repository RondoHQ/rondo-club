# Membership Fees System

## Overview

The membership fees system manages per-season contribution settings for club members. It supports:
- Per-season fee storage (separate settings for each season)
- Automatic migration from legacy global settings
- Fee calculation with family discounts and pro-rata adjustments
- Fee snapshots for season locking

## Season Format

Seasons are represented as `YYYY-YYYY` format (e.g., `2025-2026`).

**Season start date:** July 1

- If current month >= July: season is current year to next year (e.g., `2025-2026`)
- If current month < July: season is previous year to current year (e.g., `2024-2025`)

## Storage

### Per-Season Option Keys

Fee settings are stored in WordPress options with season-specific keys:

| Option Key | Purpose |
|------------|---------|
| `rondo_membership_fees_YYYY-YYYY` | Fee settings for specific season (e.g., `rondo_membership_fees_2025-2026`) |
| `rondo_membership_fees` | Legacy global option (deprecated, auto-migrated on first read) |

### Fee Structure

Each season option stores an array of fee types:

```php
[
  'mini'     => 130,  // Ages 4-6
  'pupil'    => 180,  // Ages 7-12
  'junior'   => 230,  // Ages 13-17
  'senior'   => 255,  // Ages 18+
  'recreant' => 65,   // Recreational members
  'donateur' => 55,   // Donors
]
```

## Migration Behavior

**One-time automatic migration:**

When `get_settings_for_season()` is called for the **current season** and:
1. No season-specific option exists for current season
2. Legacy global option `rondo_membership_fees` exists

The system will:
1. Copy legacy global option → current season option (`rondo_membership_fees_2025-2026`)
2. Delete the legacy global option
3. Return the migrated values

**Next season defaults:**
- If no option exists for next season, returns default values
- No migration occurs (next season starts fresh with defaults)

## API Endpoints

### GET `/rondo/v1/membership-fees/settings`

Returns settings for both current and next season.

**Response:**
```json
{
  "current_season": {
    "key": "2025-2026",
    "fees": {
      "mini": 130,
      "pupil": 180,
      "junior": 230,
      "senior": 255,
      "recreant": 65,
      "donateur": 55
    }
  },
  "next_season": {
    "key": "2026-2027",
    "fees": {
      "mini": 130,
      "pupil": 180,
      "junior": 230,
      "senior": 255,
      "recreant": 65,
      "donateur": 55
    }
  }
}
```

### POST `/rondo/v1/membership-fees/settings`

Updates settings for a specific season.

**Request Body:**
```json
{
  "season": "2025-2026",
  "mini": 130,
  "pupil": 180,
  "junior": 230,
  "senior": 255,
  "recreant": 65,
  "donateur": 55
}
```

**Required Fields:**
- `season` (string) - Must be either current season or next season key

**Optional Fields:**
- Any fee type keys (updates only provided fields)

**Validation:**
- Season must match current or next season
- Fee amounts must be numeric and non-negative

**Response:**
Same structure as GET endpoint (returns both seasons after update)

## PHP Service Methods

### `MembershipFees` Class

```php
// Get option key for a season
public function get_option_key_for_season( string $season ): string

// Get settings for a specific season (with auto-migration)
public function get_settings_for_season( string $season ): array

// Update settings for a specific season
public function update_settings_for_season( array $fees, string $season ): bool

// Get current season settings (backward compatible)
public function get_all_settings(): array

// Update current season settings (backward compatible)
public function update_settings( array $fees ): bool

// Get single fee amount by type (uses current season)
public function get_fee( string $type ): int

// Calculate fee for a person (uses current season)
public function calculate_fee( int $person_id ): ?array
```

## Season Transition

On **July 1 of each year**, the season automatically transitions:

**Before July 1, 2026:**
- Current season: `2025-2026`
- Next season: `2026-2027`

**On/After July 1, 2026:**
- Current season: `2026-2027` (automatically becomes current)
- Next season: `2027-2028` (new season available for configuration)

**Pre-configuration workflow:**
1. Before June 2026: Admin configures next season (`2026-2027`) fees
2. July 1, 2026: System automatically uses `2026-2027` as current season
3. All fee calculations use new season rates
4. Admin can now configure `2027-2028` as next season

## Fee Calculation

### Base Fee Determination

Priority order:
1. **Youth categories** (mini/pupil/junior): Based on `leeftijdsgroep` ACF field
2. **Senior**: Regular senior fee (default)
3. **Recreant**: Senior with only recreational teams
4. **Donateur**: Only if no valid age group and no teams

### Family Discounts

Applied to youth members only:
- 1st child: 100% (full fee)
- 2nd child: 75% (25% discount)
- 3rd+ child: 50% (50% discount)

Family grouping: Postal code + house number from addresses field

### Pro-Rata Adjustment

Based on `lid-sinds` (registration date) field:
- **Before season start:** 100% (member since previous season)
- **Q1 (July-September):** 100%
- **Q2 (October-December):** 75%
- **Q3 (January-March):** 50%
- **Q4 (April-June):** 25%

### Calculation Flow

```
Base Fee → Family Discount → Pro-Rata → Final Fee
```

Example:
- Base fee (pupil): €180
- Family discount (2nd child): €180 × 75% = €135
- Pro-rata (joined October): €135 × 75% = €101.25
- Final fee: €101.25

## Fee Snapshots

Fees are cached per person per season to prevent recalculation:

```php
// Save snapshot for a season
public function save_fee_snapshot( int $person_id, array $fee_data, ?string $season = null ): bool

// Get snapshot for a season
public function get_fee_snapshot( int $person_id, ?string $season = null ): ?array

// Clear snapshot (triggers recalculation)
public function clear_fee_snapshot( int $person_id, ?string $season = null ): bool

// Clear all snapshots for a season (admin "recalculate all")
public function clear_all_snapshots_for_season( string $season ): int
```

**Snapshot meta key format:** `fee_snapshot_YYYY-YYYY`

## UI (Admin Settings)

Located at: **Settings → Admin → Contributie**

**Two-section interface:**

### Huidig seizoen: 2025-2026
- Mini: €130
- Pupil: €180
- Junior: €230
- Senior: €255
- Recreant: €65
- Donateur: €55
- [Opslaan] button

### Volgend seizoen: 2026-2027
- Mini: €130
- Pupil: €180
- Junior: €230
- Senior: €255
- Recreant: €65
- Donateur: €55
- [Opslaan] button

**Independent saves:** Each section saves independently to its season-specific option.

## Backward Compatibility

All existing code using the following methods continues to work unchanged:

```php
$membership_fees = new \Rondo\Fees\MembershipFees();

// These methods now use current season internally
$membership_fees->get_all_settings();        // Returns current season fees
$membership_fees->update_settings( $fees );  // Updates current season
$membership_fees->get_fee( 'senior' );       // Gets current season fee
$membership_fees->calculate_fee( $person_id ); // Uses current season
```

No code changes required for existing functionality.

## Version History

- **v18.1.0** (2026-02-05): Per-season fee storage with automatic migration
- Previous: Global fee settings (single option for all seasons)
