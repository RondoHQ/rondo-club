---
phase: 124
plan: 02
type: summary
subsystem: membership-fees
tags: [fees, caching, seasons, php, wordpress]

dependency_graph:
  requires: ["124-01"]
  provides: ["season-key-calculation", "fee-snapshot-storage", "get-fee-for-person-api", "bulk-operations"]
  affects: ["125-01", "125-02", "126-01", "126-02", "126-03"]

tech_stack:
  added: []
  patterns:
    - "Season-based caching with July 1 boundary"
    - "Post meta for fee snapshot storage"
    - "Options pattern for API methods"

key_files:
  created: []
  modified:
    - includes/class-membership-fees.php

decisions:
  - id: "season-key-format"
    choice: "YYYY-YYYY format (e.g., 2025-2026)"
    rationale: "Human-readable, standard sports season format"

metrics:
  duration: "4 min"
  completed: "2026-01-31"
---

# Phase 124 Plan 02: Season Snapshot Storage Summary

**One-liner:** Season-based fee caching with July 1 boundary using post meta snapshots and public API for retrieval.

## What Was Built

Extended the MembershipFees service class with season management and caching capabilities:

### Season Key Calculation
- `get_season_key(?string $date = null): string` - Returns season string (e.g., "2025-2026")
- Season boundary: July 1 (month >= 7 starts new season)
- Supports arbitrary date input for testing/historical lookups

### Snapshot Storage
- `get_snapshot_meta_key(?string $season = null): string` - Generates meta key (e.g., "fee_snapshot_2025-2026")
- `save_fee_snapshot(int $person_id, array $fee_data, ?string $season = null): bool` - Stores fee with timestamp
- `get_fee_snapshot(int $person_id, ?string $season = null): ?array` - Retrieves stored fee
- `clear_fee_snapshot(int $person_id, ?string $season = null): bool` - Removes snapshot for recalculation

### Public API
- `get_fee_for_person(int $person_id, array $options = []): ?array` - Primary API for fee retrieval
  - Options: `use_cache`, `save_snapshot`, `season`, `force_recalculate`
  - Returns: category, base_fee, leeftijdsgroep, person_id, season, from_cache, calculated_at

### Bulk Operations
- `clear_all_snapshots_for_season(string $season): int` - Clears all snapshots for admin recalculation

### Diagnostic Methods
- `get_calculation_status(int $person_id): array` - Returns diagnostic info for excluded members
  - Identifies: unknown_age_group, has_team_but_no_age_group, no_age_group_no_team_not_donateur

## Technical Decisions

1. **Season Key Format**: Used "YYYY-YYYY" (e.g., "2025-2026") for human readability and sports convention
2. **Post Meta Storage**: Fee snapshots stored per-person, per-season in post meta
3. **Caching by Default**: `get_fee_for_person` caches by default (use_cache=true, save_snapshot=true)
4. **Diagnostic Reasons**: Three specific reasons for non-calculable members for UI flagging

## Files Changed

| File | Change |
|------|--------|
| `includes/class-membership-fees.php` | +231 lines: 8 new methods for season/snapshot management |

## Commits

| Hash | Message |
|------|---------|
| ecace353 | feat(124-02): add season key and snapshot methods |
| 2848c37f | feat(124-02): add get_fee_for_person public API method |
| 90488fdd | feat(124-02): add bulk operations and diagnostic methods |

## Deviations from Plan

None - plan executed exactly as written.

## API Reference

### get_fee_for_person Options

```php
$fees->get_fee_for_person($person_id, [
    'use_cache'         => true,  // Check snapshot first
    'save_snapshot'     => true,  // Save result to snapshot
    'season'            => null,  // Use current season
    'force_recalculate' => false, // Bypass cache
]);
```

### Return Format

```php
[
    'category'       => 'junior',
    'base_fee'       => 230,
    'leeftijdsgroep' => 'Onder 14',
    'person_id'      => 123,
    'season'         => '2025-2026',
    'from_cache'     => true,
    'calculated_at'  => '2025-07-01 00:00:00',
]
```

## Next Phase Readiness

Phase 124 (Fee Calculation Engine) is now complete:
- Plan 01: Core calculation logic (calculate_fee, parse_age_group, team detection)
- Plan 02: Season caching (get_fee_for_person, snapshots, bulk operations)

Ready for Phase 125 (Family Discounts) which will extend this foundation with:
- Household detection
- Discount tier calculation
- Pro-rata calculations for mid-season joins

## Testing Notes

The class can be manually tested via WP-CLI:

```bash
# Get season key
wp eval 'echo (new \Stadion\Fees\MembershipFees())->get_season_key();'

# Calculate fee for person
wp eval 'print_r((new \Stadion\Fees\MembershipFees())->get_fee_for_person(123));'

# Get diagnostic status
wp eval 'print_r((new \Stadion\Fees\MembershipFees())->get_calculation_status(123));'
```
